<?php
/**
 * shop-realizar-pago-producto.php
 * Procesar pago con Stripe para productos y generar QR de entrega
 */

session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use chillerlan\QRCode\{QRCode, QROptions};
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$user_id = $_SESSION['usuario_id'];

try {
    // Obtener datos del POST
    $offer_id = (int)($_POST['offer_id'] ?? 0);
    $payment_method_id = $_POST['payment_method_id'] ?? '';

    if ($offer_id <= 0 || empty($payment_method_id)) {
        throw new Exception('Datos de pago inválidos');
    }

    // Obtener información de la oferta aceptada
    $sql = "SELECT o.*, p.name as product_name, p.description, p.seller_id,
                   COALESCE(seller.full_name, seller.username) as seller_name,
                   COALESCE(seller.email, '') as seller_email,
                   seller.stripe_account_id,
                   COALESCE(buyer.full_name, buyer.username) as buyer_name,
                   COALESCE(buyer.email, '') as buyer_email
            FROM shop_product_offers o
            JOIN shop_products p ON o.product_id = p.id
            LEFT JOIN accounts seller ON p.seller_id = seller.id
            LEFT JOIN accounts buyer ON o.buyer_id = buyer.id
            WHERE o.id = :offer_id
            AND o.buyer_id = :user_id
            AND o.status = 'accepted'";

    $stmt = $conexion->prepare($sql);
    $stmt->execute([
        ':offer_id' => $offer_id,
        ':user_id' => $user_id
    ]);

    $offer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$offer) {
        throw new Exception('Oferta no encontrada o no está aceptada');
    }

    // Calcular montos
    $total_amount = (float)$offer['offered_price'] * (int)$offer['quantity'];
    $currency = strtolower($offer['offered_currency']);

    // Convertir a centavos para Stripe
    $amount_cents = (int)($total_amount * 100);

    // Comisión de SendVialo (10%)
    $platform_fee = (int)($amount_cents * 0.10);

    // Inicializar Stripe
    \Stripe\Stripe::setApiKey(STRIPE_SECRET);

    $conexion->beginTransaction();

    // Determinar modo de pago
    $custody_mode = empty($offer['stripe_account_id']) ? 1 : 0;

    // Calcular montos para el vendedor y la plataforma
    $platform_fee_amount = $total_amount * 0.10;
    $seller_amount = $total_amount - $platform_fee_amount;

    if ($custody_mode) {
        // MODO CUSTODIA: Vendedor sin Stripe Connect
        $payment_intent = \Stripe\PaymentIntent::create([
            'amount' => $amount_cents,
            'currency' => $currency,
            'payment_method' => $payment_method_id,
            'confirm' => true,
            'return_url' => 'https://sendvialo.com/shop/shop-confirmacion-pago-producto.php',
            'automatic_payment_methods' => [
                'enabled' => true,
                'allow_redirects' => 'never'
            ],
            'description' => "Pago por {$offer['product_name']} (Custodia)",
            'metadata' => [
                'offer_id' => $offer_id,
                'product_id' => $offer['product_id'],
                'seller_id' => $offer['seller_id'],
                'buyer_id' => $user_id,
                'custody_mode' => 'true'
            ]
        ]);

        // Guardar en payments_in_custody con la estructura correcta
        $sql_custody = "INSERT INTO payments_in_custody
                        (id_viaje, payment_intent_id, monto_total, amount_to_transporter,
                         amount_to_company, comprador_username, transportista_email,
                         stripe_account_id, estado, created_at)
                        VALUES (:offer_id, :pi_id, :amount, :seller_amount, :platform_fee,
                                :buyer_email, :seller_email, :stripe_account, 'PENDING', NOW())";

        $stmt_custody = $conexion->prepare($sql_custody);
        $stmt_custody->execute([
            ':offer_id' => $offer_id,
            ':pi_id' => $payment_intent->id,
            ':amount' => $total_amount,
            ':seller_amount' => $seller_amount,
            ':platform_fee' => $platform_fee_amount,
            ':buyer_email' => $offer['buyer_email'],
            ':seller_email' => $offer['seller_email'],
            ':stripe_account' => $offer['stripe_account_id'] ?? null
        ]);

        $payment_id = $conexion->lastInsertId();

    } else {
        // MODO DIVISIÓN AUTOMÁTICA: Vendedor con Stripe Connect
        $payment_intent = \Stripe\PaymentIntent::create([
            'amount' => $amount_cents,
            'currency' => $currency,
            'payment_method' => $payment_method_id,
            'confirm' => true,
            'return_url' => 'https://sendvialo.com/shop/shop-confirmacion-pago-producto.php',
            'automatic_payment_methods' => [
                'enabled' => true,
                'allow_redirects' => 'never'
            ],
            'description' => "Pago por {$offer['product_name']}",
            'application_fee_amount' => $platform_fee,
            'transfer_data' => [
                'destination' => $offer['stripe_account_id'],
            ],
            'metadata' => [
                'offer_id' => $offer_id,
                'product_id' => $offer['product_id'],
                'seller_id' => $offer['seller_id'],
                'buyer_id' => $user_id
            ]
        ]);

        $payment_id = null;
    }

    // Generar QR único
    $qr_unique_id = 'PROD-' . $offer_id . '-' . time() . '-' . bin2hex(random_bytes(4));

    // Crear orden en shop_orders
    $order_number = 'SVO-' . date('YmdHis') . '-' . $offer_id;
    $sql_order = "INSERT INTO shop_orders
                  (order_number, buyer_id, status, total_amount, currency, created_at)
                  VALUES (:order_num, :buyer_id, 'paid', :amount, :currency, NOW())";

    $stmt_order = $conexion->prepare($sql_order);
    $stmt_order->execute([
        ':order_num' => $order_number,
        ':buyer_id' => $user_id,
        ':amount' => $total_amount,
        ':currency' => strtoupper($currency)
    ]);

    $order_id = $conexion->lastInsertId();

    // Crear item de orden en shop_order_items
    $sql_item = "INSERT INTO shop_order_items
                 (order_id, product_id, seller_id, quantity, unit_price, subtotal, currency, status, created_at)
                 VALUES (:order_id, :product_id, :seller_id, :quantity, :unit_price, :subtotal, :currency, 'paid', NOW())";

    $stmt_item = $conexion->prepare($sql_item);
    $stmt_item->execute([
        ':order_id' => $order_id,
        ':product_id' => $offer['product_id'],
        ':seller_id' => $offer['seller_id'],
        ':quantity' => $offer['quantity'],
        ':unit_price' => $offer['offered_price'],
        ':subtotal' => $total_amount,
        ':currency' => strtoupper($currency)
    ]);

    $order_item_id = $conexion->lastInsertId();

    // Preparar datos para QR JSON
    $qr_data = [
        'offer_id' => $offer_id,
        'order_id' => $order_id,
        'order_item_id' => $order_item_id,
        'product_id' => $offer['product_id'],
        'buyer_id' => $user_id,
        'seller_id' => $offer['seller_id'],
        'qr_id' => $qr_unique_id
    ];

    // Guardar en shop_product_deliveries
    $sql_delivery = "INSERT INTO shop_product_deliveries
                     (qr_code_unique_id, qr_code_path, qr_data_json, order_id, order_item_id,
                      product_id, buyer_id, seller_id, payment_id, delivery_state,
                      payment_released, created_at)
                     VALUES
                     (:qr_id, :qr_path, :qr_data, :order_id, :order_item_id,
                      :product_id, :buyer_id, :seller_id, :payment_id, 'pending',
                      0, NOW())";

    $qr_relative_path = 'qr_codes/products/qr_' . $qr_unique_id . '.png';

    $stmt_delivery = $conexion->prepare($sql_delivery);
    $stmt_delivery->execute([
        ':qr_id' => $qr_unique_id,
        ':qr_path' => $qr_relative_path,
        ':qr_data' => json_encode($qr_data),
        ':order_id' => $order_id,
        ':order_item_id' => $order_item_id,
        ':product_id' => $offer['product_id'],
        ':buyer_id' => $user_id,
        ':seller_id' => $offer['seller_id'],
        ':payment_id' => $payment_id
    ]);

    $delivery_id = $conexion->lastInsertId();

    // Generar imagen QR
    $qr_dir = __DIR__ . '/qr_codes/products';
    if (!is_dir($qr_dir)) {
        mkdir($qr_dir, 0755, true);
    }

    $qr_file = $qr_dir . '/qr_' . $qr_unique_id . '.png';

    $qr_options = new QROptions([
        'outputType' => QRCode::OUTPUT_IMAGE_PNG,
        'eccLevel' => QRCode::ECC_L,
        'scale' => 5,
        'imageBase64' => false,
    ]);

    $qrcode = new QRCode($qr_options);
    $qrcode->render($qr_unique_id, $qr_file);

    // Enviar emails
    if (file_exists($qr_file)) {
        enviarEmailVendedorConQR(
            $offer['seller_email'],
            $offer['seller_name'],
            [
                'product_name' => $offer['product_name'],
                'quantity' => $offer['quantity'],
                'amount' => $total_amount,
                'currency' => strtoupper($currency),
                'qr_unique_id' => $qr_unique_id,
                'buyer_name' => $offer['buyer_name']
            ],
            $qr_file
        );
    }

    enviarEmailCompradorConfirmacion(
        $offer['buyer_email'],
        $offer['buyer_name'],
        [
            'product_name' => $offer['product_name'],
            'quantity' => $offer['quantity'],
            'amount' => $total_amount,
            'currency' => strtoupper($currency),
            'seller_name' => $offer['seller_name']
        ]
    );

    $conexion->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Pago procesado exitosamente',
        'payment_intent_id' => $payment_intent->id,
        'delivery_id' => $delivery_id,
        'redirect' => "shop-confirmacion-pago-producto.php?delivery_id={$delivery_id}"
    ]);

} catch (\Stripe\Exception\CardException $e) {
    if (isset($conexion) && $conexion->inTransaction()) {
        $conexion->rollBack();
    }
    echo json_encode([
        'success' => false,
        'error' => 'Error con la tarjeta: ' . $e->getError()->message
    ]);
} catch (Exception $e) {
    if (isset($conexion) && $conexion->inTransaction()) {
        $conexion->rollBack();
    }
    error_log("Error en pago producto: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Enviar email al vendedor con QR
 */
function enviarEmailVendedorConQR($email, $nombre, $detalles, $qrFilePath) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USER'];
        $mail->Password = $_ENV['SMTP_PASS'];
        $mail->SMTPSecure = $_ENV['SMTP_SECURE'];
        $mail->Port = $_ENV['SMTP_PORT'];
        $mail->CharSet = 'UTF-8';

        $mail->setFrom('no-reply@sendvialo.com', 'SendVialo Shop');
        $mail->addAddress($email, $nombre);
        $mail->isHTML(true);
        $mail->Subject = '📦 Nueva Venta - Tu Código QR - SendVialo Shop';

        $mail->Body = "
        <div style='max-width:600px;margin:20px auto;background:#fff;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.1);'>
            <div style='background:linear-gradient(135deg,#42ba25,#37a01f);color:white;padding:30px 20px;text-align:center;'>
                <h1 style='margin:0;font-size:28px;'>📦 Nueva Venta Confirmada</h1>
                <p>Tu código QR para la entrega</p>
            </div>
            <div style='padding:30px 20px;'>
                <h3>¡Hola " . htmlspecialchars($nombre) . "!</h3>
                <p>Has vendido: <strong>" . htmlspecialchars($detalles['product_name']) . "</strong></p>
                <p>Cantidad: <strong>{$detalles['quantity']}</strong></p>
                <div style='background:#dcfce7;border-left:4px solid #42ba25;padding:20px;margin:20px 0;text-align:center;'>
                    <p>💰 Recibirás al completar la entrega:</p>
                    <div style='font-size:32px;font-weight:bold;color:#42ba25;'>
                        {$detalles['amount']} {$detalles['currency']}
                    </div>
                </div>
                <div style='border:3px dashed #42ba25;padding:30px;text-align:center;margin:30px 0;'>
                    <h3>📱 TU CÓDIGO QR DE ENTREGA</h3>
                    <p>El comprador debe escanear este QR al recibir el producto</p>
                    <img src='cid:qr_code' alt='Código QR' style='max-width:250px;'>
                    <p style='font-size:12px;color:#999;'>ID: " . htmlspecialchars($detalles['qr_unique_id']) . "</p>
                </div>
                <p><strong>Instrucciones:</strong></p>
                <ol>
                    <li>Prepara el producto según lo acordado</li>
                    <li>Entrega el producto a: {$detalles['buyer_name']}</li>
                    <li>Al entregar, muestra este QR al comprador</li>
                    <li>Una vez escaneado, recibirás tu pago automáticamente</li>
                </ol>
            </div>
        </div>";

        if (file_exists($qrFilePath)) {
            $mail->AddEmbeddedImage($qrFilePath, 'qr_code');
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Error email vendedor: ' . $e->getMessage());
        return false;
    }
}

/**
 * Enviar email al comprador con confirmación
 */
function enviarEmailCompradorConfirmacion($email, $nombre, $detalles) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USER'];
        $mail->Password = $_ENV['SMTP_PASS'];
        $mail->SMTPSecure = $_ENV['SMTP_SECURE'];
        $mail->Port = $_ENV['SMTP_PORT'];
        $mail->CharSet = 'UTF-8';

        $mail->setFrom('no-reply@sendvialo.com', 'SendVialo Shop');
        $mail->addAddress($email, $nombre);
        $mail->isHTML(true);
        $mail->Subject = '✅ Pago Confirmado - SendVialo Shop';

        $mail->Body = "
        <div style='max-width:600px;margin:20px auto;background:#fff;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.1);'>
            <div style='background:linear-gradient(135deg,#42ba25,#37a01f);color:white;padding:30px 20px;text-align:center;'>
                <h1 style='margin:0;font-size:28px;'>✅ Pago Confirmado</h1>
            </div>
            <div style='padding:30px 20px;'>
                <h3>¡Hola " . htmlspecialchars($nombre) . "!</h3>
                <p>Tu pago ha sido procesado exitosamente.</p>
                <p><strong>Producto:</strong> " . htmlspecialchars($detalles['product_name']) . "</p>
                <p><strong>Cantidad:</strong> {$detalles['quantity']}</p>
                <div style='background:#dcfce7;padding:20px;margin:20px 0;text-align:center;'>
                    <p>💰 Total pagado:</p>
                    <div style='font-size:32px;font-weight:bold;color:#42ba25;'>
                        {$detalles['amount']} {$detalles['currency']}
                    </div>
                </div>
                <p><strong>Vendedor:</strong> {$detalles['seller_name']}</p>
                <p>El vendedor ha sido notificado. Recibirás el producto pronto.</p>
                <p>Cuando recibas el producto, escanea el código QR del vendedor para confirmar la entrega.</p>
            </div>
        </div>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Error email comprador: ' . $e->getMessage());
        return false;
    }
}
?>