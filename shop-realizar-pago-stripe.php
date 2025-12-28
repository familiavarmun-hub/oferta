<?php
// shop-realizar-pago-stripe.php - Procesar pago con Stripe para propuestas
session_start();
require_once '../config.php';
require_once '../vendor/autoload.php';

use chillerlan\QRCode\{QRCode, QROptions};
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

header('Content-Type: application/json');

/**
 * Generar código QR para la entrega
 */
function generarCodigoQR($data, $filePath) {
    $directory = dirname($filePath);
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $options = new QROptions([
        'outputType'   => QRCode::OUTPUT_IMAGE_PNG,
        'eccLevel'     => QRCode::ECC_L,
        'scale'        => 5,
        'imageBase64'  => false,
    ]);

    $qrcode = new QRCode($options);
    $qrcode->render($data, $filePath);

    return file_exists($filePath);
}

/**
 * Enviar email al SOLICITANTE con su código QR de entrega
 */
function enviarEmailSolicitanteConQR($email, $nombre, $detalles, $qrFilePath) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USER'];
        $mail->Password   = $_ENV['SMTP_PASS'];
        $mail->SMTPSecure = $_ENV['SMTP_SECURE'];
        $mail->Port       = $_ENV['SMTP_PORT'];

        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        $mail->setFrom('no-reply@sendvialo.com', 'SendVialo Shop');
        $mail->addAddress($email, $nombre);

        $mail->isHTML(true);
        $mail->Subject = '✅ Pago Confirmado - Tu Código QR de Entrega - SendVialo Shop';

        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f4f4; }
                .container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #41ba0d 0%, #5dcb2a 50%, #79dd46 100%); color: white; padding: 30px 20px; text-align: center; }
                .header h1 { margin: 0; font-size: 28px; font-weight: 600; }
                .content { padding: 30px 20px; }
                .qr-section { background: #fff; padding: 30px; border-radius: 8px; margin: 30px 0; text-align: center; border: 3px dashed #41ba0d; }
                .qr-frame img { max-width: 250px; height: auto; display: block; margin: 0 auto; }
                .amount-box { background: linear-gradient(135deg, #f8f9fa, #e9ecef); border-left: 4px solid #41ba0d; padding: 20px; margin: 20px 0; text-align: center; }
                .amount { font-size: 32px; font-weight: bold; color: #41ba0d; }
                .chat-cta { background: #e9f8f3; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center; }
                .btn-chat { display: inline-block; background: linear-gradient(135deg, #41ba0d, #5dcb2a); color: white; padding: 12px 30px; border-radius: 8px; text-decoration: none; font-weight: bold; margin-top: 10px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>✅ Pago Confirmado</h1>
                    <p>Tu código QR de entrega</p>
                </div>
                <div class='content'>
                    <h3>¡Hola " . htmlspecialchars($nombre) . "! 👋</h3>
                    <p>Tu pago ha sido <strong>confirmado exitosamente</strong>.</p>
                    <p><strong>Producto:</strong> " . htmlspecialchars($detalles['title']) . "</p>
                    <p><strong>Viajero:</strong> " . htmlspecialchars($detalles['traveler_name']) . "</p>
                    <p><strong>Destino:</strong> " . htmlspecialchars($detalles['destination']) . "</p>
                    <div class='amount-box'>
                        <p>💰 Monto Pagado:</p>
                        <div class='amount'>" . number_format($detalles['monto_total'], 2) . " " . strtoupper($detalles['moneda']) . "</div>
                    </div>
                    <div class='chat-cta'>
                        <h3>💬 Chat Habilitado</h3>
                        <p>Coordina la entrega con " . htmlspecialchars($detalles['traveler_name']) . "</p>
                        <a href='https://sendvialo.com/shop/shop-chat-list.php' class='btn-chat'>Ir al Chat</a>
                    </div>
                    <div class='qr-section'>
                        <h3>📱 TU CÓDIGO QR DE ENTREGA</h3>
                        <p><strong>IMPORTANTE:</strong> Muestra este QR al viajero al momento de recibir el producto</p>
                        <div class='qr-frame'>
                            <img src='cid:qr_code_delivery' alt='Código QR'>
                        </div>
                        <p style='font-size: 12px; color: #999;'>ID: " . htmlspecialchars($detalles['qr_unique_id']) . "</p>
                    </div>
                    <p>📋 <strong>Próximos Pasos:</strong></p>
                    <ol>
                        <li>El viajero adquirirá el producto según tu solicitud</li>
                        <li>Coordina por chat cuándo y dónde recibirás el producto</li>
                        <li>Al momento de la entrega, <strong>MUESTRA este QR al viajero</strong></li>
                        <li>El viajero escaneará el QR para confirmar la entrega</li>
                        <li>El pago será liberado al viajero automáticamente</li>
                    </ol>
                    <p>🔒 Tu pago está seguro hasta que el viajero escanee tu QR</p>
                </div>
            </div>
        </body>
        </html>";

        if (file_exists($qrFilePath)) {
            $mail->AddEmbeddedImage($qrFilePath, 'qr_code_delivery');
        }

        $mail->send();
        return true;
    } catch (PHPMailerException $e) {
        error_log('Error email solicitante con QR: ' . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Enviar email al VIAJERO con instrucciones (SIN QR)
 */
function enviarEmailViajeroInstrucciones($email, $nombre, $detalles) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USER'];
        $mail->Password   = $_ENV['SMTP_PASS'];
        $mail->SMTPSecure = $_ENV['SMTP_SECURE'];
        $mail->Port       = $_ENV['SMTP_PORT'];

        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        $mail->setFrom('no-reply@sendvialo.com', 'SendVialo Shop');
        $mail->addAddress($email, $nombre);

        $mail->isHTML(true);
        $mail->Subject = '🎒 Nueva Entrega - Instrucciones - SendVialo Shop';

        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f4f4; }
                .container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #41ba0d 0%, #5dcb2a 50%, #79dd46 100%); color: white; padding: 30px 20px; text-align: center; }
                .header h1 { margin: 0; font-size: 28px; font-weight: 600; }
                .content { padding: 30px 20px; }
                .amount-box { background: linear-gradient(135deg, #e8f8e5, #d4f1d0); border-left: 4px solid #41ba0d; padding: 20px; margin: 20px 0; text-align: center; }
                .amount { font-size: 32px; font-weight: bold; color: #41ba0d; }
                .chat-cta { background: #e9f8f3; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center; }
                .btn-chat { display: inline-block; background: linear-gradient(135deg, #41ba0d, #5dcb2a); color: white; padding: 12px 30px; border-radius: 8px; text-decoration: none; font-weight: bold; margin-top: 10px; }
                .alert-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎒 Nueva Entrega Confirmada</h1>
                    <p>Instrucciones para completar la entrega</p>
                </div>
                <div class='content'>
                    <h3>¡Hola " . htmlspecialchars($nombre) . "! 👋</h3>
                    <p>Has recibido el pago por: <strong>" . htmlspecialchars($detalles['title']) . "</strong></p>
                    <p><strong>Solicitante:</strong> " . htmlspecialchars($detalles['requester_name']) . "</p>
                    <p><strong>Destino:</strong> " . htmlspecialchars($detalles['destination']) . "</p>
                    <div class='amount-box'>
                        <p>💰 Recibirás al completar la entrega:</p>
                        <div class='amount'>" . number_format($detalles['monto_transportista'], 2) . " " . strtoupper($detalles['moneda']) . "</div>
                    </div>
                    <div class='chat-cta'>
                        <h3>💬 Chat Habilitado</h3>
                        <p>Coordina la entrega con " . htmlspecialchars($detalles['requester_name']) . "</p>
                        <a href='https://sendvialo.com/shop/shop-chat-list.php' class='btn-chat'>Ir al Chat</a>
                    </div>
                    <div class='alert-box'>
                        <p><strong>⚠️ IMPORTANTE:</strong> El solicitante tiene un código QR que deberás <strong>ESCANEAR</strong> al momento de la entrega para confirmar y recibir tu pago.</p>
                    </div>
                    <p>📋 <strong>Instrucciones:</strong></p>
                    <ol>
                        <li>Adquiere el producto según la descripción de la solicitud</li>
                        <li>Llévalo al destino: " . htmlspecialchars($detalles['destination']) . "</li>
                        <li>Coordina con el solicitante por chat cuándo y dónde entregarás</li>
                        <li>Al momento de la entrega, <strong>ESCANEA el QR del solicitante</strong></li>
                        <li>Una vez escaneado, recibirás tu pago automáticamente</li>
                    </ol>
                    <p>🔒 El pago está en custodia y será liberado automáticamente cuando escanees el QR del solicitante</p>
                </div>
            </div>
        </body>
        </html>";

        $mail->send();
        return true;
    } catch (PHPMailerException $e) {
        error_log('Error email viajero: ' . $mail->ErrorInfo);
        return false;
    }
}

/**
 * ✅ Crear mensajes de bienvenida en el chat
 */
function crearMensajesBienvenidaChat($conexion, $proposal_id, $requester_id, $traveler_id, $product_title) {
    try {
        $check_sql = "SELECT COUNT(*) FROM shop_chat_messages WHERE proposal_id = ?";
        $check_stmt = $conexion->prepare($check_sql);
        $check_stmt->execute([$proposal_id]);
        $message_count = $check_stmt->fetchColumn();
        
        if ($message_count > 0) {
            error_log("ℹ️ Ya existen mensajes en el chat de la propuesta #{$proposal_id}");
            return true;
        }
        
        $sql = "SELECT full_name, username FROM accounts WHERE id = ?";
        
        $stmt = $conexion->prepare($sql);
        $stmt->execute([$requester_id]);
        $requester = $stmt->fetch(PDO::FETCH_ASSOC);
        $requester_name = $requester['full_name'] ?: $requester['username'];
        
        $stmt->execute([$traveler_id]);
        $traveler = $stmt->fetch(PDO::FETCH_ASSOC);
        $traveler_name = $traveler['full_name'] ?: $traveler['username'];
        
        $mensaje1 = "¡Hola {$traveler_name}! 👋\n\n";
        $mensaje1 .= "He aceptado tu propuesta para: {$product_title}\n\n";
        $mensaje1 .= "El pago está en custodia segura 🔒. Ahora podemos coordinar los detalles de la entrega. 📦";
        
        $sql = "INSERT INTO shop_chat_messages 
                (proposal_id, sender_id, receiver_id, message, is_read, created_at) 
                VALUES (?, ?, ?, ?, 0, NOW())";
        $stmt = $conexion->prepare($sql);
        $stmt->execute([$proposal_id, $requester_id, $traveler_id, $mensaje1]);
        
        usleep(100000);
        
        $mensaje2 = "¡Hola {$requester_name}! 😊\n\n";
        $mensaje2 .= "Gracias por confiar en mí. Estoy listo para traer tu pedido de forma segura.\n\n";
        $mensaje2 .= "¿Cuándo y dónde te gustaría recibirlo?";
        
        $stmt->execute([$proposal_id, $traveler_id, $requester_id, $mensaje2]);
        
        error_log("✅ Mensajes de bienvenida creados - Propuesta #{$proposal_id}");
        
        return true;
        
    } catch (Exception $e) {
        error_log("⚠️ Error al crear mensajes de bienvenida: " . $e->getMessage());
        return false;
    }
}

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$user_id = $_SESSION['usuario_id'];

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

$token = $data['token'] ?? '';
$proposal_id = (int)($data['proposal_id'] ?? 0);
$amount = (float)($data['amount'] ?? 0);
$currency = strtolower($data['currency'] ?? 'eur');
$subtotal = (float)($data['subtotal'] ?? 0);
$comision = (float)($data['comision'] ?? 0);
$stripe_connect_id = $data['stripe_connect_id'] ?? null;

if (empty($token) || $proposal_id <= 0 || $amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Parámetros faltantes']);
    exit;
}

try {
    \Stripe\Stripe::setApiKey(STRIPE_SECRET);

    $sql = "SELECT p.*, r.title, r.destination_city, r.user_id as requester_id, p.traveler_id,
                   COALESCE(t.full_name, t.username) as traveler_name,
                   COALESCE(t.email) as traveler_email,
                   COALESCE(req.full_name, req.username) as requester_name,
                   COALESCE(req.email) as requester_email
            FROM shop_request_proposals p
            JOIN shop_requests r ON r.id = p.request_id
            LEFT JOIN accounts t ON t.id = p.traveler_id
            LEFT JOIN accounts req ON req.id = r.user_id
            WHERE p.id = :id AND r.user_id = :user_id AND p.status = 'pending'";

    $stmt = $conexion->prepare($sql);
    $stmt->execute([':id' => $proposal_id, ':user_id' => $user_id]);
    $proposal = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$proposal) {
        throw new Exception('Propuesta no encontrada o ya procesada');
    }

    $requester_id = $proposal['requester_id'];
    $traveler_id = $proposal['traveler_id'];
    $request_title = $proposal['title'];

    $conexion->beginTransaction();

    $codigo_unico = 'SHOP_' . time() . '_' . $proposal_id;

    $has_stripe_connect = !empty($stripe_connect_id);

    // ⭐⭐⭐ SIEMPRE USAR CUSTODIA MANUAL ⭐⭐⭐
    // Solo cambia si tiene Stripe Connect en la liberación, NO en el pago inicial

    $amount_cents = round($amount * 100);

    $payment_method = \Stripe\PaymentMethod::create([
        'type' => 'card',
        'card' => ['token' => $token]
    ]);

    $payment_intent = \Stripe\PaymentIntent::create([
        'amount' => $amount_cents,
        'currency' => $currency,
        'payment_method' => $payment_method->id,
        'payment_method_types' => ['card'],
        'confirmation_method' => 'automatic',
        'confirm' => true,
        'capture_method' => 'manual', // ⭐ SIEMPRE MANUAL
        'description' => "SendVialo Shop - {$request_title} (En Custodia QR)",
        'metadata' => [
            'proposal_id' => $proposal_id,
            'requester_id' => $requester_id,
            'traveler_id' => $traveler_id,
            'codigo_unico' => $codigo_unico,
            'type' => 'shop_payment',
            'custody' => 'true',
            'requires_qr_delivery' => 'true',
            'has_stripe_connect' => $has_stripe_connect ? 'true' : 'false',
            'stripe_connect_id' => $stripe_connect_id ?: 'none'
        ]
    ]);

    if ($payment_intent->status !== 'requires_capture') {
        throw new Exception("Payment Intent no quedó en estado requires_capture. Estado actual: {$payment_intent->status}");
    }

    $payment_status = 'en_custodia'; // ⭐ SIEMPRE EN CUSTODIA
    $custody_mode = true; // ⭐ SIEMPRE TRUE
    $payment_intent_id = $payment_intent->id;
    $charge_id = null;

    error_log("✅ CUSTODIA ACTIVADA - Shop Payment");
    error_log("   Payment Intent: {$payment_intent->id}");
    error_log("   Status: {$payment_intent->status}");
    error_log("   Total retenido: {$amount} {$currency}");
    error_log("   Tiene Stripe Connect: " . ($has_stripe_connect ? 'SÍ' : 'NO'));
    error_log("   Se liberará tras escaneo QR");

    // REGISTRAR PAGO EN payments_in_custody
    $sql_payment = "INSERT INTO payments_in_custody (
                        id_viaje, payment_intent_id, charge_id,
                        transportista_email, comprador_username, monto_total, amount_to_transporter,
                        amount_to_company, metodo_pago, codigo_unico,
                        estado, stripe_account_id, created_at
                    ) VALUES (
                        :id_viaje, :payment_intent_id, :charge_id,
                        :transportista_email, :comprador_username, :monto_total, :amount_to_transporter,
                        :amount_to_company, :metodo_pago, :codigo_unico,
                        :estado, :stripe_account_id, NOW()
                    )";

    $stmt_payment = $conexion->prepare($sql_payment);
    $stmt_payment->execute([
        ':id_viaje' => $proposal['request_id'],
        ':payment_intent_id' => $payment_intent_id,
        ':charge_id' => $charge_id,
        ':transportista_email' => $proposal['traveler_email'],
        ':comprador_username' => $proposal['requester_email'],
        ':monto_total' => $amount,
        ':amount_to_transporter' => $subtotal,
        ':amount_to_company' => $comision,
        ':metodo_pago' => 'stripe',
        ':codigo_unico' => $codigo_unico,
        ':estado' => $payment_status,
        ':stripe_account_id' => $stripe_connect_id
    ]);

    $payment_id = $conexion->lastInsertId();

    $sql_update = "UPDATE shop_request_proposals
                   SET status = 'accepted',
                       payment_id = :payment_id,
                       updated_at = NOW()
                   WHERE id = :id";
    $stmt_update = $conexion->prepare($sql_update);
    $stmt_update->execute([':payment_id' => $payment_id, ':id' => $proposal_id]);

    $sql_request = "UPDATE shop_requests SET status = 'accepted' WHERE id = :id";
    $stmt_request = $conexion->prepare($sql_request);
    $stmt_request->execute([':id' => $proposal['request_id']]);

    // GENERAR QR CODE
    $qr_unique_id = 'SHOPQR_' . time() . '_' . $proposal_id . '_' . bin2hex(random_bytes(4));

    $qr_data = [
        'delivery_type' => 'shop_delivery',
        'qr_unique_id' => $qr_unique_id,
        'proposal_id' => $proposal_id,
        'payment_id' => $payment_id,
        'traveler_id' => $traveler_id,
        'requester_id' => $requester_id,
        'title' => $request_title,
        'destination' => $proposal['destination_city'] ?? 'N/A',
        'timestamp' => time()
    ];

    $qr_data_json = json_encode($qr_data);

    $qr_directory = __DIR__ . '/uploads/shop_qr_codes';
    $qr_filename = "{$qr_unique_id}.png";
    $qr_file_path = "{$qr_directory}/{$qr_filename}";

    $qr_generated = generarCodigoQR($qr_data_json, $qr_file_path);

    if (!$qr_generated) {
        throw new Exception('Error al generar código QR');
    }

    $qr_path_db = "uploads/shop_qr_codes/{$qr_filename}";

    $sql_delivery = "INSERT INTO shop_deliveries (
                        proposal_id, payment_id, requester_id, traveler_id,
                        qr_code_unique_id, qr_code_path, qr_data_json,
                        delivery_state, email_sent, created_at
                    ) VALUES (
                        :proposal_id, :payment_id, :requester_id, :traveler_id,
                        :qr_code_unique_id, :qr_code_path, :qr_data_json,
                        'pending', 0, NOW()
                    )";

    $stmt_delivery = $conexion->prepare($sql_delivery);
    $stmt_delivery->execute([
        ':proposal_id' => $proposal_id,
        ':payment_id' => $payment_id,
        ':requester_id' => $requester_id,
        ':traveler_id' => $traveler_id,
        ':qr_code_unique_id' => $qr_unique_id,
        ':qr_code_path' => $qr_path_db,
        ':qr_data_json' => $qr_data_json
    ]);

    $delivery_id = $conexion->lastInsertId();

    error_log("✅ QR CODE GENERADO - Shop Delivery");
    error_log("   Delivery ID: {$delivery_id}");
    error_log("   QR Unique ID: {$qr_unique_id}");

    // CREAR MENSAJES DE CHAT
    $chat_created = crearMensajesBienvenidaChat(
        $conexion, 
        $proposal_id, 
        $requester_id, 
        $traveler_id, 
        $request_title
    );

    $conexion->commit();

    // ENVIAR EMAILS
    $qr_file_absolute = __DIR__ . '/' . $qr_path_db;

    // Email al SOLICITANTE con QR (para que lo muestre al viajero)
    $email_solicitante_enviado = enviarEmailSolicitanteConQR(
        $proposal['requester_email'],
        $proposal['requester_name'],
        [
            'title' => $request_title,
            'description' => $proposal['description'] ?? '',
            'destination' => $proposal['destination_city'],
            'traveler_name' => $proposal['traveler_name'],
            'proposed_price' => $proposal['proposed_price'],
            'currency' => $proposal['proposed_currency'],
            'monto_total' => $amount,
            'moneda' => $currency,
            'qr_unique_id' => $qr_unique_id
        ],
        $qr_file_absolute
    );

    // Email al VIAJERO con instrucciones SIN QR (para que escanee el QR del solicitante)
    $email_viajero_enviado = enviarEmailViajeroInstrucciones(
        $proposal['traveler_email'],
        $proposal['traveler_name'],
        [
            'title' => $request_title,
            'description' => $proposal['description'] ?? '',
            'destination' => $proposal['destination_city'],
            'requester_name' => $proposal['requester_name'],
            'proposed_price' => $proposal['proposed_price'],
            'currency' => $proposal['proposed_currency'],
            'monto_transportista' => $subtotal,
            'moneda' => $currency
        ]
    );

    if ($email_viajero_enviado && $email_solicitante_enviado) {
        try {
            $sql_email = "UPDATE shop_deliveries SET email_sent = 1, email_sent_at = NOW() WHERE id = :id";
            $stmt_email = $conexion->prepare($sql_email);
            $stmt_email->execute([':id' => $delivery_id]);
        } catch (Exception $e) {
            error_log("⚠️ Error al actualizar estado de emails: " . $e->getMessage());
        }
    }

    echo json_encode([
        'success' => true,
        'payment_id' => $payment_id,
        'payment_intent_id' => $payment_intent_id,
        'charge_id' => $charge_id,
        'payment_status' => $payment_status,
        'automatic_division' => false, // ⭐ SIEMPRE FALSE - se divide al escanear QR
        'custody_mode' => $custody_mode,
        'codigo_unico' => $codigo_unico,
        'delivery_id' => $delivery_id,
        'qr_unique_id' => $qr_unique_id,
        'qr_path' => $qr_path_db,
        'emails_sent' => $email_viajero_enviado && $email_solicitante_enviado,
        'chat_created' => $chat_created
    ]);

} catch (\Stripe\Exception\CardException $e) {
    if (isset($conexion) && $conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log("❌ Stripe Card Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Tarjeta rechazada: ' . $e->getError()->message
    ]);

} catch (\Stripe\Exception\ApiErrorException $e) {
    if (isset($conexion) && $conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log("❌ Stripe API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error de Stripe: ' . $e->getMessage()
    ]);

} catch (Exception $e) {
    if (isset($conexion) && $conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log("❌ General Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>