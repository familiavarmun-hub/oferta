<?php
require_once '../config.php';

echo "<h2>📊 Verificación de Tablas</h2><pre>";

try {
    $stmt = $conexion->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Total de tablas: " . count($tables) . "\n\n";
    echo "Tablas encontradas:\n";
    echo "==================\n";
    
    foreach ($tables as $table) {
        echo "- " . $table . "\n";
        
        // Si contiene "shop", "message", "conversation", "product" o "user"
        if (stripos($table, 'shop') !== false || 
            stripos($table, 'message') !== false || 
            stripos($table, 'conversation') !== false ||
            stripos($table, 'product') !== false ||
            stripos($table, 'user') !== false) {
            
            echo "  ⭐ TABLA RELEVANTE\n";
            
            // Mostrar estructura
            $stmt2 = $conexion->query("DESCRIBE `{$table}`");
            $columns = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            echo "  Columnas:\n";
            foreach ($columns as $col) {
                echo "    • {$col['Field']} ({$col['Type']})\n";
            }
            echo "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

echo "</pre>";
?>
