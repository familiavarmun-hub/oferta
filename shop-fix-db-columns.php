<?php
/**
 * shop-fix-db-columns.php
 * Agregar columnas faltantes a payments_in_custody
 */

require_once '../config.php';

header('Content-Type: text/plain; charset=utf-8');

echo "═══════════════════════════════════════════════════════\n";
echo "REPARACIÓN DE BASE DE DATOS - payments_in_custody\n";
echo "═══════════════════════════════════════════════════════\n\n";

try {
    // Verificar columnas existentes
    $existing_columns = [];
    $result = $conexion->query("SHOW COLUMNS FROM payments_in_custody");
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $existing_columns[] = $row['Field'];
    }
    
    echo "📋 Columnas actuales:\n";
    foreach ($existing_columns as $col) {
        echo "   - {$col}\n";
    }
    echo "\n";
    
    // Columnas requeridas
    $required_columns = [
        'stripe_transfer_id' => "VARCHAR(255) NULL COMMENT 'ID del transfer de Stripe'",
        'paypal_payout_id' => "VARCHAR(255) NULL COMMENT 'ID del payout de PayPal'",
        'fecha_liberacion' => "DATETIME NULL COMMENT 'Fecha de liberación del pago'"
    ];
    
    echo "🔧 Agregando columnas faltantes:\n\n";
    
    foreach ($required_columns as $column_name => $definition) {
        if (!in_array($column_name, $existing_columns)) {
            echo "➕ Agregando columna: {$column_name}... ";
            
            try {
                $sql = "ALTER TABLE payments_in_custody ADD COLUMN {$column_name} {$definition}";
                $conexion->exec($sql);
                echo "✅ OK\n";
            } catch (Exception $e) {
                echo "❌ ERROR: " . $e->getMessage() . "\n";
            }
        } else {
            echo "✓ Columna {$column_name} ya existe\n";
        }
    }
    
    // Verificar columnas después de la actualización
    echo "\n📋 Columnas después de actualización:\n";
    $result = $conexion->query("SHOW COLUMNS FROM payments_in_custody");
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo "   - {$row['Field']} ({$row['Type']})\n";
    }
    
    echo "\n✅ Reparación completada\n";
    echo "═══════════════════════════════════════════════════════\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR CRÍTICO: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}