<?php
// get-user-trips.php - VERSIÓN CORREGIDA
header('Content-Type: application/json; charset=UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir configuración - buscar en múltiples ubicaciones
$config_paths = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php',
    dirname(__DIR__) . '/config.php'
];

$config_loaded = false;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $config_loaded = true;
        break;
    }
}

if (!$config_loaded) {
    echo json_encode(['success' => false, 'error' => 'Archivo de configuración no encontrado', 'trips' => []]);
    exit;
}

if (empty($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'Usuario no autenticado', 'trips' => []]);
    exit;
}

try {
    $uid = (int)$_SESSION['usuario_id'];
    
    // Corregir la consulta SQL - usar los nombres de columna correctos
    $sql = "SELECT 
                id, 
                search_input as origin, 
                destination_input as destination, 
                DATE_FORMAT(datepicker, '%Y-%m-%d') as date,
                datepicker as fecha_salida
            FROM transporting 
            WHERE id_transporting = :u 
            ORDER BY datepicker DESC 
            LIMIT 100";
    
    $stmt = $conexion->prepare($sql);
    $stmt->execute([':u' => $uid]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Procesar los datos para mejor formato
    $trips = [];
    foreach ($rows as $row) {
        // Extraer ciudad origen y destino (quitar país si está incluido)
        $origen_parts = explode(',', $row['origin']);
        $destino_parts = explode(',', $row['destination']);
        
        $trips[] = [
            'id' => (int)$row['id'],
            'origin' => $row['origin'],
            'destination' => $row['destination'],
            'origen_ciudad' => trim($origen_parts[0]),
            'destino_ciudad' => trim($destino_parts[0]),
            'date' => $row['date'],
            'fecha_salida' => $row['fecha_salida']
        ];
    }
    
    echo json_encode(['success' => true, 'trips' => $trips]);
    
} catch (Throwable $e) {
    error_log("[GET_USER_TRIPS] Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor', 'trips' => []]);
}
?>