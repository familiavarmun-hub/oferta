<?php
/**
 * shop-process-qr.php
 * Procesar escaneo de QR y liberar pago al viajero
 */

session_start();
require_once 'config.php';
require_once '../vendor/autoload.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$user_id = $_SESSION['usuario_id'];

// Leer datos del POST
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

$delivery_id = (int)($data['delivery_id'] ?? 0);
$qr_data = $data['qr_data'] ?? null;

if ($delivery_id <= 0 || !$qr_data) {
    echo json_encode(['success' => false, 'message' => 'Parámetros faltantes']);
    exit;
}

try {
    $conexion->beginTransaction();

    // Obtener información de la entrega
    $sql = "SELECT d.*,
                   p.proposed_price, p.proposed_currency,
                   pay.amount_to_transporter, pay.stripe_account_id, pay.estado, pay.charge_id
            FROM shop_deliveries d
            LEFT JOIN shop_request_proposals p ON d.proposal_id = p.id
            LEFT JOIN payments_in_custody pay ON d.payment_id = pay.id
            WHERE d.id = :delivery_id AND d.traveler_id = :user_id";

    $stmt = $conexion->prepare($sql);
    $stmt->execute([
        ':delivery_id' => $delivery_id,
        ':user_id' => $user_id
    ]);

    $delivery = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$delivery) {
        throw new Exception('Entrega no encontrada o no autorizada');
    }

    // Validar que la entrega no esté ya completada
    if ($delivery['delivery_state'] === 'delivered') {
        throw new Exception('Esta entrega ya ha sido completada');
    }

    // Validar que el QR coincida
    if ($delivery['qr_code_unique_id'] !== $qr_data['qr_unique_id']) {
        throw new Exception('El código QR no coincide con esta entrega');
    }

    // Validar que el estado actual permita el escaneo
    if ($delivery['delivery_state'] !== 'at_destination') {
        throw new Exception('La entrega debe estar en estado "En Destino" para ser confirmada');
    }

    // Actualizar estado de la entrega a "delivered"
    $sql_update = "UPDATE shop_deliveries
                   SET delivery_state = 'delivered',
                       qr_scanned_at = NOW(),
                       qr_scanned_by = :scanned_by,
                       updated_at = NOW()
                   WHERE id = :id";

    $stmt_update = $conexion->prepare($sql_update);
    $stmt_update->execute([
        ':scanned_by' => $user_id,
        ':id' => $delivery_id
    ]);

    // Registrar en historial
    $sql_history = "INSERT INTO shop_delivery_state_history
                    (delivery_id, previous_state, new_state, changed_by, change_method, notes)
                    VALUES (:delivery_id, :previous_state, 'delivered', :changed_by, 'qr_scan', 'QR del solicitante escaneado por viajero')";

    $stmt_history = $conexion->prepare($sql_history);
    $stmt_history->execute([
        ':delivery_id' => $delivery_id,
        ':previous_state' => $delivery['delivery_state'],
        ':changed_by' => $user_id
    ]);

    error_log("✅ QR ESCANEADO - Delivery #{$delivery_id}");
    error_log("   Estado actualizado a: delivered");
    
     $sql_request = "UPDATE shop_requests r
                    JOIN shop_request_proposals p ON p.request_id = r.id
                    SET r.status = 'completed', 
                        r.completed_at = NOW(),
                        r.updated_at = NOW()
                    WHERE p.id = :proposal_id";
    
    $stmt_request = $conexion->prepare($sql_request);
    $stmt_request->execute([':proposal_id' => $delivery['proposal_id']]);
    
    error_log("✅ SOLICITUD COMPLETADA - Request actualizado a 'completed'");

    // ====================================================================
    // LIBERAR PAGO SI ESTÁ EN CUSTODIA
    // ====================================================================

    $payment_released = false;
    $release_amount = 0;
    $release_currency = '';

    if ($delivery['estado'] === 'IN_CUSTODY') {
        // El pago está en custodia, necesitamos liberarlo

        error_log("💰 LIBERANDO PAGO - Delivery #{$delivery_id}");
        error_log("   Monto transportista: {$delivery['amount_to_transporter']} {$delivery['proposed_currency']}");

        \Stripe\Stripe::setApiKey(STRIPE_SECRET);

        // Si el viajero tiene Stripe Connect, hacer transferencia
        if (!empty($delivery['stripe_account_id'])) {
            try {
                $amount_to_transfer = round($delivery['amount_to_transporter'] * 100);

                $transfer = \Stripe\Transfer::create([
                    'amount' => $amount_to_transfer,
                    'currency' => strtolower($delivery['proposed_currency']),
                    'destination' => $delivery['stripe_account_id'],
                    'source_transaction' => $delivery['charge_id'],
                    'description' => "Pago liberado por entrega confirmada - Delivery #{$delivery_id}"
                ]);

                error_log("✅ TRANSFERENCIA STRIPE EXITOSA");
                error_log("   Transfer ID: {$transfer->id}");
                error_log("   Destination: {$delivery['stripe_account_id']}");

                $payment_released = true;
                $release_amount = $delivery['amount_to_transporter'];
                $release_currency = $delivery['proposed_currency'];

            } catch (\Stripe\Exception\ApiErrorException $e) {
                error_log("❌ Error en transferencia Stripe: " . $e->getMessage());
                throw new Exception('Error al liberar el pago via Stripe: ' . $e->getMessage());
            }
        } else {
            // Viajero sin Stripe Connect - marcar para proceso manual
            error_log("⚠️ Viajero sin Stripe Connect - Requiere liberación manual");

            // Se marca como liberado para indicar que está pendiente de proceso manual
            $payment_released = true;
            $release_amount = $delivery['amount_to_transporter'];
            $release_currency = $delivery['proposed_currency'];
        }

        // Actualizar registro de entrega
        $sql_payment = "UPDATE shop_deliveries
                        SET payment_released = 1,
                            payment_released_at = NOW()
                        WHERE id = :id";

        $stmt_payment = $conexion->prepare($sql_payment);
        $stmt_payment->execute([':id' => $delivery_id]);

        // Actualizar estado del pago en payments_in_custody
        $sql_custody = "UPDATE payments_in_custody
                        SET estado = 'RELEASED',
                            released_at = NOW()
                        WHERE id = :payment_id";

        $stmt_custody = $conexion->prepare($sql_custody);
        $stmt_custody->execute([':payment_id' => $delivery['payment_id']]);

        error_log("✅ PAGO LIBERADO Y REGISTRADO");
    } else {
        // Pago con división automática - ya fue liberado en el momento del cargo
        error_log("ℹ️ Pago con división automática - Ya liberado previamente");
        $payment_released = true;
        $release_amount = $delivery['amount_to_transporter'];
        $release_currency = $delivery['proposed_currency'];
    }

    $conexion->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Entrega confirmada exitosamente',
        'delivery_id' => $delivery_id,
        'delivery_state' => 'delivered',
        'payment_released' => $payment_released,
        'amount' => number_format($release_amount, 2),
        'currency' => strtoupper($release_currency)
    ]);

} catch (Exception $e) {
    if (isset($conexion) && $conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log("❌ Error en shop-process-qr: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>