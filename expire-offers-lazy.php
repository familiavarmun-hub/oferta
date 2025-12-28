<?php
/**
 * EXPIRACIÓN LAZY DE OFERTAS
 * Incluye este archivo en cualquier script que consulte ofertas
 * Se ejecuta automáticamente cuando se necesita
 */

// Protección contra acceso directo
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    header('HTTP/1.1 403 Forbidden');
    exit("Acceso denegado");
}

function expireOldOffers($conexion) {
    try {
        // Marcar ofertas como expiradas
        $stmt = $conexion->prepare("
            UPDATE shop_price_offer_history
            SET status = 'expired'
            WHERE status = 'pending'
            AND expires_at < NOW()
        ");
        $stmt->execute();

        $expiredCount = $stmt->rowCount();

        // Actualizar mensajes correspondientes
        if ($expiredCount > 0) {
            $stmt2 = $conexion->prepare("
                UPDATE shop_messages m
                JOIN shop_price_offer_history poh ON m.id = poh.message_id
                SET m.price_offer_status = 'expired'
                WHERE poh.status = 'expired'
                AND m.price_offer_status = 'pending'
            ");
            $stmt2->execute();
        }

        return $expiredCount;
    } catch(PDOException $e) {
        error_log("Error expirando ofertas: " . $e->getMessage());
        return 0;
    }
}

// Auto-ejecutar si hay conexión disponible
if (isset($conexion) && $conexion instanceof PDO) {
    // Solo ejecutar cada 5 minutos (cacheo simple)
    $cacheFile = sys_get_temp_dir() . '/last_expire_check.txt';
    $shouldRun = true;

    if (file_exists($cacheFile)) {
        $lastCheck = (int)file_get_contents($cacheFile);
        if ((time() - $lastCheck) < 300) { // 5 minutos
            $shouldRun = false;
        }
    }

    if ($shouldRun) {
        expireOldOffers($conexion);
        file_put_contents($cacheFile, time());
    }
}
?>
