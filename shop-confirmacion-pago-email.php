<?php
/**
 * shop-confirmacion-pago-email.php
 * Envía correos de confirmación de pago con QR code al viajero
 * y confirmación al solicitante
 */

session_start();
require_once '../config.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Leer datos del POST
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

$delivery_id = (int)($data['delivery_id'] ?? 0);

if ($delivery_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de entrega no válido']);
    exit;
}

try {
    // Obtener información completa de la entrega
    $sql = "SELECT d.*,
                   p.proposed_price, p.proposed_currency, p.estimated_delivery,
                   r.title, r.description, r.destination_city,
                   COALESCE(req.full_name, req.username) as requester_name,
                   req.email as requester_email,
                   COALESCE(trav.full_name, trav.username) as traveler_name,
                   trav.email as traveler_email,
                   pay.monto_total, pay.monto_transportista, pay.monto_sendvialo, pay.moneda,
                   pay.payment_status, pay.codigo_unico
            FROM shop_deliveries d
            LEFT JOIN shop_request_proposals p ON d.proposal_id = p.id
            LEFT JOIN shop_requests r ON p.request_id = r.id
            LEFT JOIN accounts req ON d.requester_id = req.id
            LEFT JOIN accounts trav ON d.traveler_id = trav.id
            LEFT JOIN payments_in_custody pay ON d.payment_id = pay.id
            WHERE d.id = :delivery_id";

    $stmt = $conexion->prepare($sql);
    $stmt->execute([':delivery_id' => $delivery_id]);
    $delivery = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$delivery) {
        throw new Exception('Entrega no encontrada');
    }

    // Preparar ruta completa del QR
    $qr_path = __DIR__ . '/' . $delivery['qr_code_path'];

    if (!file_exists($qr_path)) {
        throw new Exception('Archivo QR no encontrado: ' . $qr_path);
    }

    // ====================================================================
    // ENVIAR EMAIL AL SOLICITANTE con QR CODE
    // ====================================================================

    $email_solicitante_sent = enviarEmailSolicitanteConQR(
        $delivery['requester_email'],
        $delivery['requester_name'],
        [
            'title' => $delivery['title'],
            'description' => $delivery['description'],
            'destination' => $delivery['destination_city'],
            'traveler_name' => $delivery['traveler_name'],
            'proposed_price' => $delivery['proposed_price'],
            'currency' => $delivery['proposed_currency'],
            'monto_total' => $delivery['monto_total'],
            'moneda' => $delivery['moneda'],
            'codigo_unico' => $delivery['codigo_unico'],
            'qr_unique_id' => $delivery['qr_code_unique_id']
        ],
        $qr_path
    );

    // ====================================================================
    // ENVIAR EMAIL AL VIAJERO con instrucciones (SIN QR)
    // ====================================================================

    $email_viajero_sent = enviarEmailViajeroInstrucciones(
        $delivery['traveler_email'],
        $delivery['traveler_name'],
        [
            'title' => $delivery['title'],
            'description' => $delivery['description'],
            'destination' => $delivery['destination_city'],
            'requester_name' => $delivery['requester_name'],
            'proposed_price' => $delivery['proposed_price'],
            'currency' => $delivery['proposed_currency'],
            'monto_transportista' => $delivery['monto_transportista'],
            'moneda' => $delivery['moneda'],
            'codigo_unico' => $delivery['codigo_unico']
        ]
    );

    // Marcar email como enviado
    if ($email_viajero_sent && $email_solicitante_sent) {
        $sql_update = "UPDATE shop_deliveries
                       SET email_sent = 1, email_sent_at = NOW()
                       WHERE id = :id";
        $stmt_update = $conexion->prepare($sql_update);
        $stmt_update->execute([':id' => $delivery_id]);

        error_log("✅ EMAILS ENVIADOS - Shop Delivery #{$delivery_id}");
        error_log("   Viajero: {$delivery['traveler_email']}");
        error_log("   Solicitante: {$delivery['requester_email']}");

        echo json_encode([
            'success' => true,
            'message' => 'Emails enviados exitosamente',
            'delivery_id' => $delivery_id
        ]);
    } else {
        throw new Exception('Error al enviar uno o ambos emails');
    }

} catch (Exception $e) {
    error_log("❌ Error en shop-confirmacion-pago-email: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// ========================================================================
// FUNCIÓN 1: Email al SOLICITANTE con QR CODE
// ========================================================================
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
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <style>
                body { margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f4f4; }
                .container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #41ba0d 0%, #5dcb2a 50%, #79dd46 100%); color: white; padding: 30px 20px; text-align: center; }
                .logo { max-width: 180px; height: auto; margin-bottom: 15px; filter: brightness(0) invert(1); }
                .header h1 { margin: 0; font-size: 28px; font-weight: 600; text-shadow: 0 2px 4px rgba(0,0,0,0.2); }
                .header p { margin: 10px 0 0 0; font-size: 14px; opacity: 0.95; }
                .content { padding: 30px 20px; }
                .success-box { background: #e8f8e5; border-left: 4px solid #41ba0d; padding: 15px; margin: 20px 0; border-radius: 4px; }
                .success-box h3 { margin: 0 0 10px 0; color: #155724; font-size: 18px; }
                .success-box p { margin: 0; color: #155724; line-height: 1.6; }
                .info-section { background: #f8f9fa; padding: 20px; border-radius: 6px; margin: 20px 0; border: 1px solid #e9ecef; }
                .info-section h3 { margin-top: 0; color: #000000; border-bottom: 2px solid #41ba0d; padding-bottom: 10px; }
                .info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #dee2e6; }
                .info-row:last-child { border-bottom: none; }
                .info-label { font-weight: 600; color: #000000; }
                .info-value { color: #495057; }
                .qr-section { background: #fff; padding: 30px; border-radius: 8px; margin: 30px 0; text-align: center; border: 3px dashed #41ba0d; }
                .qr-section h3 { margin-top: 0; color: #41ba0d; font-size: 22px; }
                .qr-frame { background: white; padding: 20px; border-radius: 8px; display: inline-block; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
                .qr-frame img { max-width: 250px; height: auto; display: block; }
                .qr-instructions { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px; }
                .qr-instructions h4 { margin: 0 0 10px 0; color: #856404; font-size: 18px; }
                .qr-instructions ol { margin: 10px 0; padding-left: 20px; color: #856404; }
                .qr-instructions li { margin: 8px 0; line-height: 1.6; }
                .amount-box { background: linear-gradient(135deg, #e8f8e5, #d4f1d0); border-left: 4px solid #41ba0d; padding: 20px; margin: 20px 0; border-radius: 4px; text-align: center; }
                .amount-box h3 { margin: 0 0 10px 0; color: #155724; font-size: 16px; }
                .amount-box .amount { font-size: 32px; font-weight: bold; color: #41ba0d; margin: 10px 0; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #6c757d; font-size: 13px; border-top: 3px solid #41ba0d; }
                .footer a { color: #41ba0d; text-decoration: none; font-weight: 600; }
                @media only screen and (max-width: 600px) {
                    .content { padding: 20px 15px; }
                    .qr-section { padding: 20px 10px; }
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <img src='https://sendvialo.com/Imagenes/globo5.png' alt='SendVialo Shop' class='logo'>
                    <h1>✅ Pago Confirmado</h1>
                    <p>Tu código QR de entrega</p>
                </div>

                <div class='content'>
                    <div class='success-box'>
                        <h3>¡Hola " . htmlspecialchars($nombre) . "! 👋</h3>
                        <p>Tu pago ha sido <strong>confirmado exitosamente</strong>. El viajero ha sido notificado y gestionará tu solicitud.</p>
                    </div>

                    <div class='info-section'>
                        <h3>📦 Detalles del Producto</h3>
                        <div class='info-row'>
                            <span class='info-label'>Producto:</span>
                            <span class='info-value'>" . htmlspecialchars($detalles['title']) . "</span>
                        </div>
                        <div class='info-row'>
                            <span class='info-label'>Descripción:</span>
                            <span class='info-value'>" . htmlspecialchars($detalles['description']) . "</span>
                        </div>
                        <div class='info-row'>
                            <span class='info-label'>Destino:</span>
                            <span class='info-value'>" . htmlspecialchars($detalles['destination']) . "</span>
                        </div>
                        <div class='info-row'>
                            <span class='info-label'>Viajero:</span>
                            <span class='info-value'>" . htmlspecialchars($detalles['traveler_name']) . "</span>
                        </div>
                    </div>

                    <div class='amount-box'>
                        <h3>💰 Monto Pagado</h3>
                        <div class='amount'>" . number_format($detalles['monto_total'], 2) . " " . strtoupper($detalles['moneda']) . "</div>
                        <p style='margin: 0; color: #495057;'>Tu pago está en custodia segura</p>
                    </div>

                    <div class='qr-section'>
                        <h3>📱 TU CÓDIGO QR DE ENTREGA</h3>
                        <p style='color: #666; margin: 15px 0;'><strong>IMPORTANTE:</strong> Muestra este QR al viajero al momento de recibir el producto</p>
                        <div class='qr-frame'>
                            <img src='cid:qr_code_delivery' alt='Código QR de Entrega'>
                        </div>
                        <p style='margin: 15px 0; font-size: 12px; color: #999;'>ID: " . htmlspecialchars($detalles['qr_unique_id']) . "</p>
                    </div>

                    <div class='qr-instructions'>
                        <h4>📋 Próximos Pasos</h4>
                        <ol>
                            <li><strong>El viajero adquirirá el producto</strong> según tu solicitud</li>
                            <li><strong>Coordina por chat</strong> cuándo y dónde recibirás el producto</li>
                            <li><strong>Guarda este email</strong> con el código QR en tu teléfono</li>
                            <li><strong>Al momento de la entrega</strong>, MUESTRA este código QR al viajero</li>
                            <li><strong>El viajero escaneará el QR</strong> para confirmar la entrega</li>
                            <li><strong>El pago será liberado</strong> al viajero automáticamente</li>
                        </ol>
                    </div>

                    <div style='background: #e8f8e5; border-radius: 6px; padding: 15px; margin: 20px 0; text-align: center;'>
                        <p style='margin: 0; color: #155724; font-weight: 500; line-height: 1.6;'>
                            🔒 <strong>Protección SendVialo</strong><br>
                            Tu pago está seguro hasta que el viajero escanee tu QR
                        </p>
                    </div>
                </div>

                <div class='footer'>
                    <p>¿Necesitas ayuda? Contáctanos en <a href='mailto:soporte@sendvialo.com'>soporte@sendvialo.com</a></p>
                    <p style='margin: 10px 0 0 0;'>© 2024 SendVialo Shop - <a href='https://sendvialo.com'>www.sendvialo.com</a></p>
                </div>
            </div>
        </body>
        </html>";

        // Adjuntar QR code
        if (file_exists($qrFilePath)) {
            $mail->AddEmbeddedImage($qrFilePath, 'qr_code_delivery');
        }

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log('Error al enviar email al viajero: ' . $mail->ErrorInfo);
        return false;
    }
}

// ========================================================================
// FUNCIÓN 2: Email al VIAJERO con instrucciones (SIN QR)
// ========================================================================
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
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <style>
                body { margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f4f4; }
                .container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #41ba0d 0%, #5dcb2a 50%, #79dd46 100%); color: white; padding: 30px 20px; text-align: center; }
                .logo { max-width: 180px; height: auto; margin-bottom: 15px; filter: brightness(0) invert(1); }
                .header h1 { margin: 0; font-size: 28px; font-weight: 600; text-shadow: 0 2px 4px rgba(0,0,0,0.2); }
                .content { padding: 30px 20px; }
                .success-box { background: #e8f8e5; border-left: 4px solid #41ba0d; padding: 15px; margin: 20px 0; border-radius: 4px; }
                .success-box h3 { margin: 0 0 10px 0; color: #155724; font-size: 18px; }
                .success-box p { margin: 0; color: #155724; line-height: 1.6; }
                .info-section { background: #f8f9fa; padding: 20px; border-radius: 6px; margin: 20px 0; border: 1px solid #e9ecef; }
                .info-section h3 { margin-top: 0; color: #000000; border-bottom: 2px solid #41ba0d; padding-bottom: 10px; }
                .info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #dee2e6; }
                .info-row:last-child { border-bottom: none; }
                .info-label { font-weight: 600; color: #000000; }
                .info-value { color: #495057; }
                .amount-box { background: linear-gradient(135deg, #f8f9fa, #e9ecef); border-left: 4px solid #41ba0d; padding: 20px; margin: 20px 0; border-radius: 4px; text-align: center; }
                .amount { font-size: 32px; font-weight: bold; color: #41ba0d; margin: 10px 0; }
                .next-steps { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px; }
                .next-steps h4 { margin: 0 0 10px 0; color: #856404; font-size: 18px; }
                .next-steps ol { margin: 10px 0; padding-left: 20px; color: #856404; }
                .next-steps li { margin: 8px 0; line-height: 1.6; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #6c757d; font-size: 13px; border-top: 3px solid #41ba0d; }
                .footer a { color: #41ba0d; text-decoration: none; font-weight: 600; }
                @media only screen and (max-width: 600px) {
                    .content { padding: 20px 15px; }
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <img src='https://sendvialo.com/Imagenes/globo5.png' alt='SendVialo Shop' class='logo'>
                    <h1>🎒 Nueva Entrega Confirmada</h1>
                    <p>Instrucciones para completar la entrega</p>
                </div>

                <div class='content'>
                    <div class='success-box'>
                        <h3>¡Hola " . htmlspecialchars($nombre) . "! 👋</h3>
                        <p>Has recibido el pago por: <strong>" . htmlspecialchars($detalles['title']) . "</strong></p>
                    </div>

                    <div class='info-section'>
                        <h3>📦 Detalles de la Entrega</h3>
                        <div class='info-row'>
                            <span class='info-label'>Producto:</span>
                            <span class='info-value'>" . htmlspecialchars($detalles['title']) . "</span>
                        </div>
                        <div class='info-row'>
                            <span class='info-label'>Descripción:</span>
                            <span class='info-value'>" . htmlspecialchars($detalles['description']) . "</span>
                        </div>
                        <div class='info-row'>
                            <span class='info-label'>Destino:</span>
                            <span class='info-value'>" . htmlspecialchars($detalles['destination']) . "</span>
                        </div>
                        <div class='info-row'>
                            <span class='info-label'>Solicitante:</span>
                            <span class='info-value'>" . htmlspecialchars($detalles['requester_name']) . "</span>
                        </div>
                    </div>

                    <div class='amount-box'>
                        <h3>💰 Recibirás al completar la entrega</h3>
                        <div class='amount'>" . number_format($detalles['monto_transportista'], 2) . " " . strtoupper($detalles['moneda']) . "</div>
                        <p style='margin: 0; color: #155724;'>El pago está en custodia segura</p>
                    </div>

                    <div class='alert-box' style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px;'>
                        <p style='margin: 0; color: #856404; font-weight: 500; line-height: 1.6;'><strong>⚠️ IMPORTANTE:</strong> El solicitante tiene un código QR que deberás <strong>ESCANEAR</strong> al momento de la entrega para confirmar y recibir tu pago.</p>
                    </div>

                    <div class='next-steps'>
                        <h4>📋 Instrucciones</h4>
                        <ol>
                            <li><strong>Adquiere el producto</strong> según la descripción de la solicitud</li>
                            <li><strong>Llévalo al destino</strong>: " . htmlspecialchars($detalles['destination']) . "</li>
                            <li><strong>Coordina con el solicitante</strong> por chat cuándo y dónde entregarás</li>
                            <li><strong>Al momento de la entrega</strong>, ESCANEA el QR del solicitante</li>
                            <li><strong>Una vez escaneado</strong>, recibirás tu pago automáticamente</li>
                        </ol>
                    </div>

                    <div style='background: #e8f8e5; border-radius: 6px; padding: 15px; margin: 20px 0; text-align: center;'>
                        <p style='margin: 0; color: #155724; font-weight: 500; line-height: 1.6;'>
                            🔒 <strong>Protección SendVialo</strong><br>
                            El pago está en custodia y será liberado automáticamente cuando escanees el QR del solicitante
                        </p>
                    </div>
                </div>

                <div class='footer'>
                    <p>¿Necesitas ayuda? Contáctanos en <a href='mailto:soporte@sendvialo.com'>soporte@sendvialo.com</a></p>
                    <p style='margin: 10px 0 0 0;'>© 2024 SendVialo Shop - <a href='https://sendvialo.com'>www.sendvialo.com</a></p>
                </div>
            </div>
        </body>
        </html>";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log('Error al enviar email al solicitante: ' . $mail->ErrorInfo);
        return false;
    }
}
?>