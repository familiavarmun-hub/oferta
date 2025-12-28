<?php
/**
 * Script de migración para arreglar la estructura de la base de datos del chat
 * Ejecutar una sola vez para agregar las columnas necesarias
 */

require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $conexion->beginTransaction();

    $results = [];

    // 1. Verificar y agregar columna conversation_id a shop_messages
    $results[] = addColumnIfNotExists('shop_messages', 'conversation_id', "INT(11) NULL COMMENT 'FK a shop_conversations.id' AFTER id");

    // 2. Verificar y agregar columna message_type
    $results[] = addColumnIfNotExists('shop_messages', 'message_type', "ENUM('text', 'price_offer', 'system') DEFAULT 'text' COMMENT 'Tipo de mensaje'");

    // 3. Verificar y agregar columna price_offer_amount
    $results[] = addColumnIfNotExists('shop_messages', 'price_offer_amount', "DECIMAL(10,2) NULL COMMENT 'Monto de la oferta de precio'");

    // 4. Verificar y agregar columna price_offer_currency
    $results[] = addColumnIfNotExists('shop_messages', 'price_offer_currency', "VARCHAR(3) NULL COMMENT 'Moneda de la oferta'");

    // 5. Verificar y agregar columna price_offer_status
    $results[] = addColumnIfNotExists('shop_messages', 'price_offer_status', "ENUM('pending', 'accepted', 'rejected', 'countered', 'expired') NULL COMMENT 'Estado de la oferta'");

    // 6. Verificar y agregar columna price_offer_expires_at
    $results[] = addColumnIfNotExists('shop_messages', 'price_offer_expires_at', "TIMESTAMP NULL COMMENT 'Fecha de expiración de la oferta'");

    // 7. Verificar y agregar columna replied_to_message_id
    $results[] = addColumnIfNotExists('shop_messages', 'replied_to_message_id', "INT(11) NULL COMMENT 'ID del mensaje al que responde'");

    // 8. Verificar y agregar columna metadata
    $results[] = addColumnIfNotExists('shop_messages', 'metadata', "JSON NULL COMMENT 'Datos adicionales'");

    // 9. Agregar índices si no existen
    addIndexIfNotExists('shop_messages', 'idx_conversation', 'conversation_id');
    addIndexIfNotExists('shop_messages', 'idx_message_type', 'message_type');
    addIndexIfNotExists('shop_messages', 'idx_price_offer_status', 'price_offer_status');

    $conexion->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Migración completada exitosamente',
        'details' => $results
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    $conexion->rollBack();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}

/**
 * Agregar columna si no existe
 */
function addColumnIfNotExists($table, $column, $definition) {
    global $conexion;

    try {
        // Verificar si la columna existe
        $stmt = $conexion->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        $exists = $stmt->fetch();

        if (!$exists) {
            $sql = "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}";
            $conexion->exec($sql);
            return "✅ Columna '{$column}' agregada a '{$table}'";
        } else {
            return "⚠️ Columna '{$column}' ya existe en '{$table}'";
        }
    } catch (PDOException $e) {
        return "❌ Error agregando '{$column}': " . $e->getMessage();
    }
}

/**
 * Agregar índice si no existe
 */
function addIndexIfNotExists($table, $indexName, $column) {
    global $conexion;

    try {
        // Verificar si el índice existe
        $stmt = $conexion->query("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$indexName}'");
        $exists = $stmt->fetch();

        if (!$exists) {
            $sql = "ALTER TABLE `{$table}` ADD INDEX `{$indexName}` (`{$column}`)";
            $conexion->exec($sql);
            return "✅ Índice '{$indexName}' agregado a '{$table}'";
        }
        return "⚠️ Índice '{$indexName}' ya existe en '{$table}'";
    } catch (PDOException $e) {
        return "❌ Error agregando índice '{$indexName}': " . $e->getMessage();
    }
}
?>
