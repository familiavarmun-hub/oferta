<?php
/**
 * shop-process-qr-producto.php
 * Procesar escaneo de QR y liberar pago al vendedor de productos
 */

session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

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
$qr_code = $data['qr_code'] ?? '';

if ($delivery_id <= 0 || empty($qr_code)) {
    echo json_encode(['success' => false, 'message' => 'Parámetros faltantes']);
    exit;
}

try {
    $conexion->beginTransaction();

    // Obtener información de la entrega de producto
    $sql = "SELECT d.*,
                   oi.unit_price as offered_price, oi.currency as offered_currency, oi.quantity,
                   pay.amount_to_transporter, pay.stripe_account_id, pay.estado as payment_status, pay.payment_intent_id as stripe_payment_intent_id,
                   seller.full_name as seller_name,
                   seller.email as seller_email
            FROM shop_product_deliveries d
            LEFT JOIN shop_order_items oi ON d.order_item_id = oi.id
            LEFT JOIN payments_in_custody pay ON d.payment_id = pay.id
            LEFT JOIN accounts seller ON d.seller_id = seller.id
            WHERE d.id = :delivery_id AND d.buyer_id = :user_id";

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
    if ($delivery['qr_code_unique_id'] !== $qr_code) {
        throw new Exception('El código QR no coincide con esta entrega');
    }

    // Validar que el estado actual permita el escaneo
    if ($delivery['delivery_state'] !== 'at_destination') {
        throw new Exception('La entrega debe estar en estado "En Destino" para ser confirmada');
    }

    // Actualizar estado de la entrega a "delivered"
    $sql_update = "UPDATE shop_product_deliveries
                   SET delivery_state = 'delivered',
                       qr_scanned_at = NOW(),
                       qr_scanned_by = :scanned_by
                   WHERE id = :id";

    $stmt_update = $conexion->prepare($sql_update);
    $stmt_update->execute([
        ':scanned_by' => $user_id,
        ':id' => $delivery_id
    ]);

    // Registrar en historial
    $sql_history = "INSERT INTO shop_product_delivery_state_history
                    (delivery_id, previous_state, new_state, changed_by, change_method, notes)
                    VALUES (:delivery_id, :previous_state, 'delivered', :changed_by, 'qr_scan', 'QR escaneado por comprador')";

    $stmt_history = $conexion->prepare($sql_history);
    $stmt_history->execute([
        ':delivery_id' => $delivery_id,
        ':previous_state' => $delivery['delivery_state'],
        ':changed_by' => $user_id
    ]);

    error_log("✅ QR PRODUCTO ESCANEADO - Delivery #{$delivery_id}");
    error_log("   Estado actualizado a: delivered");

    // ====================================================================
    // LIBERAR PAGO SI ESTÁ EN CUSTODIA
    // ====================================================================

    $payment_released = false;
    $release_amount = 0;
    $release_currency = '';

    // Calcular monto total y monto al vendedor
    $total_amount = (float)$delivery['offered_price'] * (int)$delivery['quantity'];
    $platform_fee = $total_amount * 0.10;
    $seller_amount = $total_amount - $platform_fee;

    if ($delivery['payment_status'] === 'PENDING') {
        // El pago está en custodia, necesitamos liberarlo

        error_log("💰 LIBERANDO PAGO PRODUCTO - Delivery #{$delivery_id}");
        error_log("   Monto vendedor: {$seller_amount} {$delivery['offered_currency']}");

        \Stripe\Stripe::setApiKey(STRIPE_SECRET);

        // Si el vendedor tiene Stripe Connect, hacer transferencia
        if (!empty($delivery['stripe_account_id'])) {
            try {
                $amount_to_transfer = round($seller_amount * 100);

                $transfer = \Stripe\Transfer::create([
                    'amount' => $amount_to_transfer,
                    'currency' => strtolower($delivery['offered_currency']),
                    'destination' => $delivery['stripe_account_id'],
                    'source_transaction' => $delivery['stripe_payment_intent_id'],
                    'description' => "Pago producto liberado - Delivery #{$delivery_id}",
                    'metadata' => [
                        'delivery_id' => $delivery_id,
                        'seller_id' => $delivery['seller_id'],
                        'product_id' => $delivery['product_id']
                    ]
                ]);

                error_log("✅ TRANSFERENCIA STRIPE PRODUCTO EXITOSA");
                error_log("   Transfer ID: {$transfer->id}");
                error_log("   Destination: {$delivery['stripe_account_id']}");

                $payment_released = true;
                $release_amount = $seller_amount;
                $release_currency = $delivery['offered_currency'];

            } catch (\Stripe\Exception\ApiErrorException $e) {
                error_log("❌ Error en transferencia Stripe producto: " . $e->getMessage());
                throw new Exception('Error al liberar el pago via Stripe: ' . $e->getMessage());
            }
        } else {
            // Vendedor sin Stripe Connect - marcar para proceso manual
            error_log("⚠️ Vendedor sin Stripe Connect - Requiere liberación manual");

            // Se marca como liberado para indicar que está pendiente de proceso manual
            $payment_released = true;
            $release_amount = $seller_amount;
            $release_currency = $delivery['offered_currency'];
        }

        // Actualizar registro de entrega
        $sql_payment = "UPDATE shop_product_deliveries
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

        error_log("✅ PAGO PRODUCTO LIBERADO Y REGISTRADO");
    } else {
        // Pago con división automática - ya fue liberado en el momento del cargo
        error_log("ℹ️ Pago producto con división automática - Ya liberado previamente");
        $payment_released = true;
        $release_amount = $seller_amount;
        $release_currency = $delivery['offered_currency'];

        // Marcar como liberado en el registro
        $sql_payment = "UPDATE shop_product_deliveries
                        SET payment_released = 1,
                            payment_released_at = NOW()
                        WHERE id = :id";

        $stmt_payment = $conexion->prepare($sql_payment);
        $stmt_payment->execute([':id' => $delivery_id]);
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

    error_log("❌ Error en shop-process-qr-producto: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>