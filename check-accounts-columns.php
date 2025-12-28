<?php
// check-accounts-columns.php - Ver columnas de la tabla accounts
require_once 'config.php';

echo "<h1>Columnas de la tabla accounts</h1>";

try {
    $sql = "DESCRIBE accounts";
    $stmt = $conexion->query($sql);
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<pre>";
    foreach ($columns as $col) {
        echo "{$col['Field']} - {$col['Type']}\n";
    }
    echo "</pre>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
