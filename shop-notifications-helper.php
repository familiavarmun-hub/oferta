<?php
/**
 * shop-notifications-helper.php
 * Helper compatible con tabla que tiene columna 'data' en lugar de 'reference_id' y 'action_url'
 */

require_once '../config.php';

/**
 * Crear notificación genérica
 */
function createShopNotification($user_id, $type, $title, $message, $reference_id = null, $action_url = null) {
    global $conexion;
    
    try {
        // Crear array de datos para guardar en JSON
        $data = json_encode([
            'reference_id' => $reference_id,
            'action_url' => $action_url
        ]);
        
        $sql = "INSERT INTO shop_notifications 
                (user_id, type, title, message, data, is_read, created_at) 
                VALUES (?, ?, ?, ?, ?, 0, NOW())";
        
        $stmt = $conexion->prepare($sql);
        return $stmt->execute([$user_id, $type, $title, $message, $data]);
    } catch (Exception $e) {
        error_log("Error creando notificación: " . $e->getMessage());
        return false;
    }
}

/**
 * NOTIFICACIONES DE PROPUESTAS
 */

// Nueva propuesta recibida (al solicitante)
function notifyNewProposal($requester_id, $request_id, $traveler_name, $request_title, $proposed_price, $currency) {
    return createShopNotification(
        $requester_id,
        'new_proposal',
        '📝 Nueva propuesta recibida',
        "{$traveler_name} ha enviado una propuesta de {$proposed_price} {$currency} para tu solicitud \"{$request_title}\"",
        $request_id,
        "shop-request-detail.php?id={$request_id}#proposals"
    );
}

// Propuesta aceptada (al viajero)
function notifyProposalAccepted($traveler_id, $request_id, $requester_name, $request_title) {
    return createShopNotification(
        $traveler_id,
        'proposal_accepted',
        '✅ ¡Propuesta aceptada!',
        "{$requester_name} ha aceptado tu propuesta para \"{$request_title}\". El pago está en custodia y puedes proceder con la entrega.",
        $request_id,
        "shop-verificacion-qr.php"
    );
}

// Propuesta rechazada (al viajero)
function notifyProposalRejected($traveler_id, $request_id, $request_title) {
    return createShopNotification(
        $traveler_id,
        'proposal_rejected',
        '❌ Propuesta no aceptada',
        "Tu propuesta para \"{$request_title}\" no ha sido aceptada. Puedes enviar propuestas a otras solicitudes.",
        $request_id,
        "shop-requests-index.php"
    );
}

/**
 * NOTIFICACIONES DE PAGOS
 */

// Pago recibido en custodia (al viajero)
function notifyPaymentInCustody($traveler_id, $payment_id, $amount, $currency, $request_title) {
    return createShopNotification(
        $traveler_id,
        'payment_received',
        '💰 Pago recibido en custodia',
        "Se ha recibido el pago de {$amount} {$currency} por \"{$request_title}\". El dinero se liberará cuando confirmes la entrega con el código QR.",
        $payment_id,
        "shop-verificacion-qr.php"
    );
}

// Confirmación de pago al solicitante
function notifyPaymentConfirmed($requester_id, $payment_id, $amount, $currency, $request_title) {
    return createShopNotification(
        $requester_id,
        'payment_received',
        '✅ Pago confirmado',
        "Tu pago de {$amount} {$currency} por \"{$request_title}\" está en custodia y será liberado al viajero cuando recibas el producto.",
        $payment_id,
        "shop-verificacion-qr.php"
    );
}

// Pago liberado al viajero
function notifyPaymentReleased($traveler_id, $payment_id, $amount, $currency, $request_title) {
    return createShopNotification(
        $traveler_id,
        'payment_released',
        '💸 Pago liberado',
        "Se ha liberado el pago de {$amount} {$currency} por \"{$request_title}\". El dinero será transferido a tu cuenta en breve.",
        $payment_id,
        "shop-verificacion-qr.php"
    );
}

/**
 * NOTIFICACIONES DE ENTREGAS
 */

// Actualización de estado de entrega
function notifyDeliveryStateChange($user_id, $delivery_id, $new_state, $request_title, $is_requester = true) {
    $states = [
        'pending' => '📦 Pendiente',
        'in_transit' => '✈️ En tránsito',
        'at_destination' => '📍 En destino',
        'delivered' => '✅ Entregado'
    ];
    
    $state_label = $states[$new_state] ?? $new_state;
    
    $messages = [
        'in_transit' => $is_requester 
            ? "Tu pedido \"{$request_title}\" está en tránsito. El viajero lo lleva consigo."
            : "Has marcado \"{$request_title}\" como en tránsito.",
        'at_destination' => $is_requester 
            ? "Tu pedido \"{$request_title}\" ha llegado a destino. El viajero te contactará pronto."
            : "Has marcado \"{$request_title}\" como llegado a destino. Coordina la entrega con el solicitante.",
        'delivered' => $is_requester 
            ? "Has confirmado la entrega de \"{$request_title}\". ¡Gracias por usar SendVialo!"
            : "El solicitante ha confirmado la entrega de \"{$request_title}\". Tu pago será liberado."
    ];
    
    return createShopNotification(
        $user_id,
        'delivery_update',
        $state_label,
        $messages[$new_state] ?? "Actualización de entrega: {$state_label}",
        $delivery_id,
        "shop-verificacion-qr.php"
    );
}

// Entrega completada
function notifyDeliveryCompleted($traveler_id, $requester_id, $delivery_id, $request_title) {
    // Notificar al viajero
    createShopNotification(
        $traveler_id,
        'delivery_completed',
        '🎉 ¡Entrega confirmada!',
        "La entrega de \"{$request_title}\" ha sido confirmada por el solicitante. Tu pago será liberado en breve.",
        $delivery_id,
        "shop-verificacion-qr.php"
    );
    
    // Notificar al solicitante
    createShopNotification(
        $requester_id,
        'delivery_completed',
        '✅ Entrega completada',
        "Has confirmado la recepción de \"{$request_title}\". ¡Gracias por confiar en SendVialo!",
        $delivery_id,
        "shop-verificacion-qr.php"
    );
    
    return true;
}

/**
 * NOTIFICACIONES DEL SISTEMA
 */

// Bienvenida a nuevo usuario
function notifyWelcome($user_id, $username) {
    return createShopNotification(
        $user_id,
        'system',
        '🎉 ¡Bienvenido a SendVialo Shop!',
        "Hola {$username}, gracias por unirte a nuestra plataforma. Explora solicitudes de productos y conecta con viajeros de todo el mundo.",
        null,
        "shop-requests-index.php"
    );
}

// Recordatorio de propuesta pendiente (al solicitante)
function notifyPendingProposalReminder($requester_id, $request_id, $proposal_count, $request_title) {
    return createShopNotification(
        $requester_id,
        'new_proposal',
        '⏰ Tienes propuestas pendientes',
        "Tu solicitud \"{$request_title}\" tiene {$proposal_count} propuesta(s) esperando tu revisión.",
        $request_id,
        "shop-request-detail.php?id={$request_id}#proposals"
    );
}

// Solicitud a punto de expirar
function notifyRequestExpiring($requester_id, $request_id, $request_title, $days_left) {
    return createShopNotification(
        $requester_id,
        'system',
        '⚠️ Tu solicitud expira pronto',
        "Tu solicitud \"{$request_title}\" expirará en {$days_left} día(s). Considera extender el plazo o aceptar una propuesta.",
        $request_id,
        "shop-request-detail.php?id={$request_id}"
    );
}

/**
 * FUNCIÓN PARA OBTENER CONTADOR DE NO LEÍDAS
 */
function getUnreadNotificationsCount($user_id) {
    global $conexion;
    
    try {
        $sql = "SELECT COUNT(*) as count FROM shop_notifications 
                WHERE user_id = ? AND is_read = 0";
        
        $stmt = $conexion->prepare($sql);
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int)$result['count'];
    } catch (Exception $e) {
        error_log("Error obteniendo contador de notificaciones: " . $e->getMessage());
        return 0;
    }
}