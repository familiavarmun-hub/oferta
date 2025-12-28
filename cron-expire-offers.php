#!/usr/bin/php
<?php
/**
 * CRON JOB PARA EXPIRAR OFERTAS
 *
 * Configurar en Hostinger cPanel → Advanced → Cron Jobs:
 * Comando: /usr/bin/php /home/usuario/public_html/cron-expire-offers.php
 * Frecuencia: Cada hora (0 * * * *)
 *
 * O cada 15 minutos: * /15 * * * *
 */

// Solo permitir ejecución desde CLI o cron
if (php_sapi_name() !== 'cli' && !isset($_SERVER['CRON'])) {
    // Permitir también acceso directo con token secreto
    if (!isset($_GET['secret']) || $_GET['secret'] !== 'tu_token_secreto_aqui') {
        http_response_code(403);
        exit("Acceso denegado");
    }
}

// Registrar inicio
$logFile = __DIR__ . '/logs/cron-expire-offers.log';
@mkdir(__DIR__ . '/logs', 0755, true);

function log_message($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

log_message("Iniciando proceso de expiración de ofertas");

// Cargar configuración
$config_paths = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php'
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
    log_message("ERROR: No se pudo cargar config.php");
    exit(1);
}

try {
    // La conexión $conexion ya está disponible desde config.php
    if (!isset($conexion)) {
        throw new Exception("No hay conexión a la base de datos");
    }

    // Marcar ofertas como expiradas
    $stmt = $conexion->prepare("
        UPDATE shop_price_offer_history
        SET status = 'expired'
        WHERE status = 'pending'
        AND expires_at < NOW()
    ");
    $stmt->execute();
    $expiredOffers = $stmt->rowCount();

    log_message("Ofertas expiradas en shop_price_offer_history: $expiredOffers");

    // Actualizar mensajes correspondientes
    if ($expiredOffers > 0) {
        $stmt2 = $conexion->prepare("
            UPDATE shop_messages m
            JOIN shop_price_offer_history poh ON m.id = poh.message_id
            SET m.price_offer_status = 'expired'
            WHERE poh.status = 'expired'
            AND m.price_offer_status = 'pending'
        ");
        $stmt2->execute();
        $expiredMessages = $stmt2->rowCount();

        log_message("Mensajes actualizados a 'expired': $expiredMessages");
    }

    // OPCIONAL: Limpiar ofertas muy antiguas (más de 30 días)
    $cleanupStmt = $conexion->prepare("
        DELETE FROM shop_price_offer_history
        WHERE status = 'expired'
        AND expires_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $cleanupStmt->execute();
    $cleaned = $cleanupStmt->rowCount();

    if ($cleaned > 0) {
        log_message("Ofertas antiguas eliminadas: $cleaned");
    }

    log_message("Proceso completado exitosamente");

    // Si se ejecuta desde web, mostrar resultado
    if (php_sapi_name() !== 'cli') {
        echo json_encode([
            'success' => true,
            'expired_offers' => $expiredOffers,
            'updated_messages' => $expiredMessages ?? 0,
            'cleaned_old' => $cleaned
        ]);
    }

    exit(0);

} catch(Exception $e) {
    log_message("ERROR: " . $e->getMessage());

    if (php_sapi_name() !== 'cli') {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }

    exit(1);
}
?>
