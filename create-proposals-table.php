<?php
/**
 * Script para crear la tabla shop_request_proposals
 * Ejecutar UNA SOLA VEZ desde el navegador o CLI
 */

// Cargar configuración
require_once __DIR__ . '/config.php';

// Verificar conexión
if (!isset($conexion) || !($conexion instanceof PDO)) {
    die('❌ Error: No hay conexión a la base de datos');
}

echo "<h1>🔧 Creación de Tablas Shop-Request</h1>";
echo "<pre>";

// =====================================================
// 1. Verificar si shop_request_proposals ya existe
// =====================================================

try {
    $result = $conexion->query("SELECT COUNT(*) FROM shop_request_proposals");
    echo "✅ La tabla shop_request_proposals YA EXISTE\n";
    echo "   Filas actuales: " . $result->fetchColumn() . "\n\n";
    $tableExists = true;
} catch (PDOException $e) {
    echo "ℹ️  La tabla shop_request_proposals NO existe\n";
    echo "   Procediendo a crearla...\n\n";
    $tableExists = false;
}

// =====================================================
// 2. Crear tabla shop_request_proposals si no existe
// =====================================================

if (!$tableExists) {
    $sql_proposals = "
    CREATE TABLE `shop_request_proposals` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `request_id` int(11) NOT NULL COMMENT 'ID de la solicitud',
      `traveler_id` int(11) NOT NULL COMMENT 'ID del viajero que hace la propuesta',
      `proposed_price` decimal(10,2) NOT NULL COMMENT 'Precio propuesto por el viajero',
      `proposed_currency` varchar(3) DEFAULT 'EUR' COMMENT 'Moneda del precio propuesto',
      `estimated_delivery` date DEFAULT NULL COMMENT 'Fecha estimada de entrega',
      `message` text DEFAULT NULL COMMENT 'Mensaje del viajero explicando su propuesta',
      `status` enum('pending','accepted','rejected','cancelled') DEFAULT 'pending' COMMENT 'Estado de la propuesta',
      `created_at` timestamp DEFAULT current_timestamp(),
      `updated_at` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `idx_request` (`request_id`),
      KEY `idx_traveler` (`traveler_id`),
      KEY `idx_status` (`status`),
      KEY `idx_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Propuestas de viajeros para solicitudes';
    ";

    try {
        $conexion->exec($sql_proposals);
        echo "✅ Tabla shop_request_proposals creada exitosamente\n\n";
    } catch (PDOException $e) {
        echo "❌ Error al crear shop_request_proposals:\n";
        echo "   " . $e->getMessage() . "\n\n";
        die();
    }
}

// =====================================================
// 3. Verificar si shop_request_messages ya existe
// =====================================================

try {
    $result = $conexion->query("SELECT COUNT(*) FROM shop_request_messages");
    echo "✅ La tabla shop_request_messages YA EXISTE\n";
    echo "   Filas actuales: " . $result->fetchColumn() . "\n\n";
    $messagesTableExists = true;
} catch (PDOException $e) {
    echo "ℹ️  La tabla shop_request_messages NO existe\n";
    echo "   Procediendo a crearla...\n\n";
    $messagesTableExists = false;
}

// =====================================================
// 4. Crear tabla shop_request_messages si no existe
// =====================================================

if (!$messagesTableExists) {
    $sql_messages = "
    CREATE TABLE `shop_request_messages` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `request_id` int(11) NOT NULL COMMENT 'ID de la solicitud',
      `sender_id` int(11) NOT NULL COMMENT 'ID del usuario que envía el mensaje',
      `receiver_id` int(11) NOT NULL COMMENT 'ID del usuario que recibe el mensaje',
      `message` text NOT NULL COMMENT 'Contenido del mensaje',
      `is_read` tinyint(1) DEFAULT 0 COMMENT 'Si el mensaje ha sido leído',
      `created_at` timestamp DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `idx_request` (`request_id`),
      KEY `idx_sender` (`sender_id`),
      KEY `idx_receiver` (`receiver_id`),
      KEY `idx_read` (`is_read`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Mensajes entre solicitantes y viajeros';
    ";

    try {
        $conexion->exec($sql_messages);
        echo "✅ Tabla shop_request_messages creada exitosamente\n\n";
    } catch (PDOException $e) {
        echo "❌ Error al crear shop_request_messages:\n";
        echo "   " . $e->getMessage() . "\n\n";
        die();
    }
}

// =====================================================
// 5. Verificación final
// =====================================================

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "✅ VERIFICACIÓN FINAL\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$tables = ['shop_requests', 'shop_request_proposals', 'shop_request_messages', 'accounts'];
foreach ($tables as $table) {
    try {
        $result = $conexion->query("SELECT COUNT(*) FROM $table");
        $count = $result->fetchColumn();
        echo "✅ $table: $count filas\n";
    } catch (PDOException $e) {
        echo "❌ $table: ERROR - " . $e->getMessage() . "\n";
    }
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🎉 PROCESO COMPLETADO\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "Ahora puedes probar el sistema:\n";
echo "- Selfcheck: shop-requests-actions.php?action=selfcheck\n";
echo "- Listado:   shop-requests-index.php\n";
echo "- Crear:     shop-request-create.php\n\n";

echo "</pre>";

echo "<h2>✅ ¡Tablas creadas exitosamente!</h2>";
echo "<p><a href='shop-requests-actions.php?action=selfcheck'>➡️ Ejecutar Selfcheck</a></p>";
echo "<p><a href='shop-requests-index.php'>➡️ Ver Solicitudes</a></p>";
?>