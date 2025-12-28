<?php
// registrar_metodo_pago.php - VERSIÓN SIMPLIFICADA (SOLO IBAN)
// Ubicación: /shop/
declare(strict_types=1);

// Ocultar warnings de deprecación (Google API Client con PHP 8.0)
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

function logDebug($message, $data = null) {
    $logFile = __DIR__ . '/logs/payment_debug_' . date('Y-m-d') . '.log';
    @mkdir(dirname($logFile), 0755, true);
    $ts = date('Y-m-d H:i:s');
    $line = "[$ts] $message";
    if ($data !== null) $line .= " - Data: " . json_encode($data, JSON_UNESCAPED_UNICODE);
    @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND);
}

session_start();

try {
    // Rutas actualizadas para carpeta shop
    require_once __DIR__ . "/../config.php";
    require_once __DIR__ . "/../vendor/autoload.php";
} catch (Exception $e) {
    die("Error cargando archivos: " . $e->getMessage());
}

use Stripe\Stripe;
use Stripe\Account;
use Stripe\AccountLink;

if (!defined('STRIPE_SECRET') || !STRIPE_SECRET) {
    die('Error: STRIPE_SECRET no configurado en config.php');
}

try {
    Stripe::setApiKey(STRIPE_SECRET);
} catch (Exception $e) {
    die('Error al inicializar Stripe: ' . $e->getMessage());
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptUrl = $scheme . '://' . $host . '/shop/registrar_metodo_pago.php';

$RETURN_URL = $scriptUrl . '?action=return';
$REFRESH_URL = $scriptUrl . '?action=refresh';

if (!isset($_SESSION['usuario_id'])) {
    $current_url = 'shop/registrar_metodo_pago.php' . (!empty($_GET) ? ('?' . http_build_query($_GET)) : '');
    echo "<script>localStorage.setItem('redirect_after_login', '$current_url');window.location.href='shop-login.php';</script>";
    exit;
}

$userId = (int)$_SESSION['usuario_id'];
$userEmail = trim($_SESSION['email'] ?? '');

function getCurrentMethod(PDO $db, int $userId) {
    try {
        $st = $db->prepare("SELECT * FROM payment_methods WHERE user_id=? ORDER BY is_primary DESC, created_at DESC LIMIT 1");
        $st->execute([$userId]); 
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        logDebug("Error getCurrentMethod: " . $e->getMessage());
        return null;
    }
}

function getStripeMethod(PDO $db, int $userId) {
    try {
        $st = $db->prepare("SELECT * FROM payment_methods WHERE user_id=? AND method_type='stripe_connect' LIMIT 1");
        $st->execute([$userId]); 
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        logDebug("Error getStripeMethod: " . $e->getMessage());
        return null;
    }
}

function upsertStripeMethod(PDO $db, int $userId, array $fields) {
    try {
        $exists = getStripeMethod($db, $userId);
        if ($exists) {
            $st = $db->prepare("UPDATE payment_methods
                SET stripe_email=:e, stripe_account_id=:a, account_number=:iban, 
                    is_primary=:p, is_verified=:v, account_email=:ae, updated_at=NOW()
                WHERE id=:id");
            $st->execute([
                ':e' => $fields['stripe_email'] ?? $exists['stripe_email'],
                ':a' => $fields['stripe_account_id'] ?? $exists['stripe_account_id'],
                ':iban' => $fields['account_number'] ?? $exists['account_number'],
                ':p' => $fields['is_primary'] ?? 1,
                ':v' => $fields['is_verified'] ?? 0,
                ':ae' => $fields['account_email'] ?? $exists['account_email'],
                ':id' => $exists['id'],
            ]);
            return (int)$exists['id'];
        } else {
            $st = $db->prepare("INSERT INTO payment_methods
                (user_id, method_type, stripe_email, stripe_account_id, account_number, account_email, is_primary, is_verified, created_at)
                VALUES (:u,'stripe_connect',:e,:a,:iban,:ae,:p,:v,NOW())");
            $st->execute([
                ':u' => $userId,
                ':e' => $fields['stripe_email'] ?? null,
                ':a' => $fields['stripe_account_id'] ?? null,
                ':iban' => $fields['account_number'] ?? null,
                ':ae' => $fields['account_email'] ?? null,
                ':p' => $fields['is_primary'] ?? 1,
                ':v' => $fields['is_verified'] ?? 0,
            ]);
            return (int)$db->lastInsertId();
        }
    } catch (Exception $e) {
        logDebug("Error upsertStripeMethod: " . $e->getMessage());
        throw $e;
    }
}

function clearPrimaries(PDO $db, int $userId) {
    try {
        $db->prepare("UPDATE payment_methods SET is_primary=0 WHERE user_id=?")->execute([$userId]);
    } catch (Exception $e) {
        logDebug("Error clearPrimaries: " . $e->getMessage());
    }
}

function isStripeTestMode(): bool {
    $key = STRIPE_SECRET;
    return strpos($key, 'sk_test_') === 0;
}

function validateIBAN(string $iban): bool {
    $iban = strtoupper(str_replace(' ', '', $iban));
    
    if (isStripeTestMode()) {
        $testIbans = [
            'ES0700120345030000067890',
            'ES1420805801180000200000',
            'ES9121000418450200051332',
        ];
        return in_array($iban, $testIbans);
    }
    
    return preg_match('/^ES\d{22}$/', $iban) === 1;
}

function createExpressAccountWithIBAN(string $email, string $iban): \Stripe\Account {
    $iban = strtoupper(str_replace(' ', '', $iban));
    
    if (!validateIBAN($iban)) {
        throw new Exception('El IBAN debe ser válido');
    }
    
    $params = [
        'type' => 'express',
        'country' => 'ES',
        'email' => $email,
        'business_type' => 'individual',
        'capabilities' => [
            'transfers' => ['requested' => true],
            'card_payments' => ['requested' => true],
        ],
        'business_profile' => [
            'product_description' => 'Servicios de envío entre particulares',
            'mcc' => '4215',
        ],
        'external_account' => [
            'object' => 'bank_account',
            'country' => 'ES',
            'currency' => 'eur',
            'account_number' => $iban,
        ],
    ];

    $acct = Account::create($params);
    logDebug('Cuenta Express creada', ['acct' => $acct->id, 'iban' => substr($iban, 0, 8) . '****']);
    return $acct;
}

$action = $_GET['action'] ?? null;

try {
    if ($action === 'get_onboarding_url') {
        header('Content-Type: application/json');
        
        $stripeMethod = getStripeMethod($conexion, $userId);
        if (!$stripeMethod || empty($stripeMethod['stripe_account_id'])) {
            echo json_encode(['success' => false, 'error' => 'No se encontró cuenta Stripe']);
            exit;
        }
        
        try {
            $accountLink = AccountLink::create([
                'account' => $stripeMethod['stripe_account_id'],
                'refresh_url' => $REFRESH_URL,
                'return_url' => $RETURN_URL,
                'type' => 'account_onboarding',
            ]);
            
            echo json_encode(['success' => true, 'url' => $accountLink->url]);
            exit;
        } catch (Exception $e) {
            logDebug("Error creando AccountLink: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
    
    if ($action === 'complete_account') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: registrar_metodo_pago.php?error=metodo_invalido");
            exit;
        }

        $iban = strtoupper(str_replace(' ', '', trim($_POST['iban'] ?? '')));
        
        if (empty($iban)) {
            header("Location: registrar_metodo_pago.php?error=iban_requerido");
            exit;
        }
        
        if (!validateIBAN($iban)) {
            header("Location: registrar_metodo_pago.php?error=iban_invalido");
            exit;
        }
        
        if (!$userEmail) {
            header("Location: registrar_metodo_pago.php?error=no_email_session");
            exit;
        }
        
        $conexion->beginTransaction();
        clearPrimaries($conexion, $userId);
        
        $stripeMethod = getStripeMethod($conexion, $userId);
        
        try {
            if ($stripeMethod && !empty($stripeMethod['stripe_account_id'])) {
                try {
                    $acct = Account::retrieve($stripeMethod['stripe_account_id']);
                    
                    if ($iban !== $stripeMethod['account_number']) {
                        try {
                            \Stripe\Account::createExternalAccount(
                                $acct->id,
                                [
                                    'external_account' => [
                                        'object' => 'bank_account',
                                        'country' => 'ES',
                                        'currency' => 'eur',
                                        'account_number' => $iban,
                                    ],
                                ]
                            );
                        } catch (Exception $e) {
                            logDebug('Error actualizando IBAN: ' . $e->getMessage());
                        }
                    }
                    
                    upsertStripeMethod($conexion, $userId, [
                        'stripe_email' => $userEmail,
                        'stripe_account_id' => $acct->id,
                        'account_email' => $userEmail,
                        'account_number' => $iban,
                        'is_primary' => 1,
                        'is_verified' => 0,
                    ]);
                    
                    $conexion->commit();
                    header("Location: registrar_metodo_pago.php?success=stripe_created_redirect");
                    exit;
                    
                } catch (Exception $e) {
                    logDebug('Error con cuenta existente: ' . $e->getMessage());
                }
            }
            
            $acct = createExpressAccountWithIBAN($userEmail, $iban);
            
            upsertStripeMethod($conexion, $userId, [
                'stripe_email' => $userEmail,
                'stripe_account_id' => $acct->id,
                'account_email' => $userEmail,
                'account_number' => $iban,
                'is_primary' => 1,
                'is_verified' => 0,
            ]);
            
            $conexion->commit();
            header("Location: registrar_metodo_pago.php?success=stripe_created_redirect");
            exit;
            
        } catch (Exception $e) {
            $conexion->rollBack();
            logDebug("ERROR Stripe: " . $e->getMessage());
            header("Location: registrar_metodo_pago.php?error=stripe_error&msg=" . urlencode($e->getMessage()));
            exit;
        }
    }

    if ($action === 'return') {
        $stripeMethod = getStripeMethod($conexion, $userId);
        if (!$stripeMethod || empty($stripeMethod['stripe_account_id'])) {
            header("Location: registrar_metodo_pago.php?error=no_stripe_account"); 
            exit;
        }
        
        $acct = Account::retrieve($stripeMethod['stripe_account_id']);
        $verified = ($acct->charges_enabled && $acct->details_submitted) ? 1 : 0;

        $conexion->beginTransaction();
        upsertStripeMethod($conexion, $userId, [
            'stripe_email' => $stripeMethod['stripe_email'],
            'stripe_account_id' => $acct->id,
            'account_email' => $stripeMethod['account_email'],
            'account_number' => $stripeMethod['account_number'],
            'is_primary' => 1,
            'is_verified' => $verified,
        ]);
        $conexion->commit();

        $msg = $verified ? 'stripe_connected' : 'stripe_pending';
        header("Location: registrar_metodo_pago.php?success=$msg"); 
        exit;
    }
    
    if ($action === 'refresh') {
        $stripeMethod = getStripeMethod($conexion, $userId);
        if ($stripeMethod && !empty($stripeMethod['stripe_account_id'])) {
            header("Location: registrar_metodo_pago.php?step=onboarding_info");
            exit;
        }
        header("Location: registrar_metodo_pago.php");
        exit;
    }
    
} catch (Exception $e) {
    if (isset($conexion) && $conexion->inTransaction()) $conexion->rollBack();
    logDebug("ERROR general: " . $e->getMessage());
    die("Error: " . $e->getMessage() . " en línea " . $e->getLine());
}

$mensajeError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['action'])) {
    try {
        $methodType = $_POST['method_type'] ?? '';
        $accountEmail = trim($_POST['account_email'] ?? '');

        if ($methodType === 'paypal') {
            if (!$accountEmail || !filter_var($accountEmail, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Ingresa un correo de PayPal válido.');
            }
            $conexion->beginTransaction();
            clearPrimaries($conexion, $userId);
            $exists = $conexion->prepare("SELECT id FROM payment_methods WHERE user_id = ? AND method_type='paypal' LIMIT 1");
            $exists->execute([$userId]);
            if ($exists->fetch()) {
                $st = $conexion->prepare("UPDATE payment_methods
                    SET account_email=?, stripe_email=NULL, stripe_account_id=NULL,
                        is_primary=1, is_verified=1, updated_at=NOW()
                    WHERE user_id=? AND method_type='paypal'");
                $st->execute([$accountEmail, $userId]);
            } else {
                $st = $conexion->prepare("INSERT INTO payment_methods
                    (user_id, method_type, account_email, is_primary, is_verified, created_at)
                    VALUES (?, 'paypal', ?, 1, 1, NOW())");
                $st->execute([$userId, $accountEmail]);
            }
            $conexion->commit();
            header("Location: registrar_metodo_pago.php?success=saved"); 
            exit;
        }
        throw new Exception('Selecciona un método válido.');
    } catch (Exception $e) {
        if ($conexion->inTransaction()) $conexion->rollBack();
        $mensajeError = 'Error: ' . $e->getMessage();
    }
}

$metodo_actual = getCurrentMethod($conexion, $userId);
$stripeData = getStripeMethod($conexion, $userId);

$errorMessages = [
    'iban_requerido' => 'Por favor, ingresa tu IBAN.',
    'iban_invalido' => isStripeTestMode() 
        ? 'MODO TEST: Usa ES07 0012 0345 0300 0006 7890' 
        : 'El IBAN debe ser español (ES + 22 dígitos).',
    'no_email_session' => 'No se encontró tu email. Cierra sesión y vuelve a iniciar.',
    'stripe_error' => 'Error al conectar con Stripe.',
    'metodo_invalido' => 'Método inválido.',
    'unexpected' => 'Error inesperado.',
];

$errorMsg = isset($_GET['error']) ? ($errorMessages[$_GET['error']] ?? $errorMessages['unexpected']) : '';
if (isset($_GET['msg'])) {
    $errorMsg .= '<br><strong>Detalle:</strong> ' . htmlspecialchars($_GET['msg']);
}

$successMessages = [
    'stripe_connected' => 'Cuenta bancaria conectada y verificada exitosamente!',
    'stripe_created_redirect' => 'redirect_to_onboarding',
    'stripe_pending' => 'Cuenta creada pero requiere verificación adicional.',
    'stripe_updated' => 'Información actualizada correctamente.',
    'saved' => 'Método de pago guardado correctamente.',
];

$successMsg = isset($_GET['success']) ? ($successMessages[$_GET['success']] ?? '') : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Método de Cobro - SendVialo</title>
    <link rel="icon" href="../Imagenes/globo5.png" type="image/png">
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/footer.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; background-color: #f5f7fa; color: #333; line-height: 1.6; }
        .container { max-width: 900px; margin: 20px auto; padding: 30px; background: white; box-shadow: 0 0 20px rgba(0,0,0,0.1); border-radius: 12px; }
        .header-section { text-align: center; margin-bottom: 40px; padding-bottom: 20px; border-bottom: 3px solid #42ba25; }
        .header-section h1 { color: #2c3e50; font-size: 2.5em; font-weight: 300; margin-bottom: 10px; }
        .header-section p { color: #7f8c8d; font-size: 1.1em; }
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 25px; display: flex; align-items: center; gap: 12px; }
        .alert-success { background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); border-left: 4px solid #28a745; color: #155724; }
        .alert-danger { background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%); border-left: 4px solid #dc3545; color: #721c24; }
        .alert-info { background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%); border-left: 4px solid #17a2b8; color: #0c5460; }
        
        .payment-methods { display: grid; gap: 25px; }
        .method-card { border: 2px solid #e9ecef; border-radius: 12px; overflow: hidden; transition: all 0.3s ease; background: white; }
        .method-card.active { border-color: #42ba25; box-shadow: 0 4px 15px rgba(66, 186, 37, 0.2); }
        .method-header { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 20px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; }
        .method-card.active .method-header { background: linear-gradient(135deg, #42ba25 0%, #37a01f 100%); color: white; }
        .method-info { display: flex; align-items: center; gap: 15px; }
        .method-icon { width: 50px; height: 50px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.5em; color: white; }
        .icon-stripe { background: linear-gradient(135deg, #4a90e2 0%, #357abd 100%); }
        .icon-paypal { background: linear-gradient(135deg, #0070ba 0%, #005a94 100%); }
        .method-details h3 { font-size: 1.2em; margin-bottom: 5px; }
        .method-card.active .method-details h3 { color: white; }
        .method-details p { font-size: 0.9em; color: #6c757d; }
        .method-card.active .method-details p { color: rgba(255, 255, 255, 0.9); }
        .radio-indicator { width: 24px; height: 24px; border: 3px solid #dee2e6; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .method-card.active .radio-indicator { border-color: white; background: white; }
        .method-card.active .radio-indicator::after { content: ''; width: 12px; height: 12px; background: #42ba25; border-radius: 50%; }
        .method-body { max-height: 0; overflow: hidden; transition: max-height 0.4s ease, padding 0.4s ease; padding: 0 20px; }
        .method-body.active { max-height: 800px; padding: 25px; border-top: 1px solid #e9ecef; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; color: #2c3e50; margin-bottom: 8px; font-size: 0.95em; }
        .form-group label .required { color: #dc3545; }
        .form-group input, .form-group select { width: 100%; padding: 12px 15px; border: 2px solid #ecf0f1; border-radius: 8px; font-size: 1em; transition: border-color 0.3s ease; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #42ba25; box-shadow: 0 0 0 3px rgba(66, 186, 37, 0.1); }
        .form-group small { color: #6c757d; display: block; margin-top: 5px; font-size: 0.875em; }
        
        .btn-actions { display: flex; gap: 15px; justify-content: flex-end; margin-top: 20px; }
        .btn { padding: 12px 25px; border: none; border-radius: 8px; cursor: pointer; font-size: 1em; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s ease; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
        .btn-primary { background: linear-gradient(135deg, #42ba25 0%, #37a01f 100%); color: white; }
        .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
        .btn-secondary { background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%); color: white; }
        
        .info-box { background: #e7f3ff; border-left: 4px solid #2196f3; padding: 15px; border-radius: 4px; margin-bottom: 20px; color: #1565c0; }
        
        /* Padding para barra inferior móvil */
        body.has-bottom-nav { padding-bottom: 80px; }
        
        @media (max-width: 768px) {
            .container { margin: 10px; padding: 20px; }
            .header-section h1 { font-size: 2em; }
            .btn-actions { flex-direction: column; }
            .btn { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/shop-header.php'; ?>

    <div class="container">
        <div class="header-section">
            <h1>Configurar Método de Cobro</h1>
            <p>Elige cómo quieres recibir tus pagos</p>
        </div>

        <?php if ($errorMsg): ?>
            <div class="alert alert-danger">❌ <?= $errorMsg ?></div>
        <?php endif; ?>

        <?php if ($mensajeError): ?>
            <div class="alert alert-danger">❌ <?= htmlspecialchars($mensajeError) ?></div>
        <?php endif; ?>

        <?php if ($successMsg && $successMsg !== 'redirect_to_onboarding'): ?>
            <div class="alert alert-success">✓ <?= $successMsg ?></div>
        <?php endif; ?>

        <div class="payment-methods">
            <!-- TRANSFERENCIA BANCARIA -->
            <div class="method-card <?= ($metodo_actual && $metodo_actual['method_type'] === 'stripe_connect') ? 'active' : '' ?>" data-method="stripe">
                <div class="method-header" onclick="toggleMethod('stripe')">
                    <div class="method-info">
                        <div class="method-icon icon-stripe">🏦</div>
                        <div class="method-details">
                            <h3>Transferencia Bancaria</h3>
                            <p>
                                <?php if ($stripeData && !empty($stripeData['account_number'])): ?>
                                    IBAN: <?= substr($stripeData['account_number'], 0, 8) . '****' . substr($stripeData['account_number'], -4) ?>
                                <?php else: ?>
                                    Recibe pagos en tu cuenta
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <div class="radio-indicator"></div>
                </div>
                <div class="method-body <?= ($metodo_actual && $metodo_actual['method_type'] === 'stripe_connect') ? 'active' : '' ?>" id="panel-stripe">
                    <?php if (!$userEmail): ?>
                        <div class="alert alert-danger">
                            No se encontró tu email. Cierra sesión y vuelve a iniciar.
                        </div>
                    <?php else: ?>
                        <?php if ($stripeData && !empty($stripeData['account_number'])): ?>
                            <div class="alert <?= $stripeData['is_verified'] == 1 ? 'alert-success' : 'alert-info' ?>">
                                <?php if ($stripeData['is_verified'] == 1): ?>
                                    Cuenta bancaria verificada
                                <?php else: ?>
                                    Cuenta creada - Pendiente de verificación
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="post" action="registrar_metodo_pago.php?action=complete_account" id="stripe-complete-form">
                            <div class="form-group">
                                <label>IBAN <span class="required">*</span></label>
                                <input type="text" name="iban" id="iban-input" 
                                       placeholder="ES07 0012 0345 0300 0006 7890" 
                                       maxlength="29" required oninput="formatIBAN(this); validateIBANMatch()">
                                <small><?= isStripeTestMode() ? 'Test: ES07 0012 0345 0300 0006 7890' : 'ES + 22 dígitos' ?></small>
                            </div>
                            
                            <div class="form-group">
                                <label>Confirmar IBAN <span class="required">*</span></label>
                                <input type="text" name="iban_confirm" id="iban-confirm" 
                                       placeholder="ES07 0012 0345 0300 0006 7890" 
                                       maxlength="29" required oninput="formatIBAN(this); validateIBANMatch()">
                                <small>Vuelve a escribir tu IBAN para confirmar</small>
                                <div id="iban-match-message" style="margin-top: 8px; font-weight: 600; display: none;"></div>
                            </div>
                            
                            <?php if (isStripeTestMode()): ?>
                                <button type="button" onclick="copyTestIBAN()" class="btn btn-secondary" style="margin-bottom: 20px; width: 100%; justify-content: center;">
                                    Copiar IBAN Test
                                </button>
                            <?php endif; ?>
                            
                            <div class="btn-actions">
                                <a href="perfil_shop.php" class="btn btn-secondary">Cancelar</a>
                                <button class="btn btn-primary" type="submit" disabled>
                                    Continuar
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        <?php if ($successMsg === 'redirect_to_onboarding'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'success',
                title: 'IBAN Confirmado',
                text: 'Redirigiendo a verificación segura...',
                showConfirmButton: false,
                allowOutsideClick: false,
                timer: 2000,
                timerProgressBar: true,
                didOpen: () => Swal.showLoading()
            }).then(() => {
                fetch('registrar_metodo_pago.php?action=get_onboarding_url')
                    .then(r => r.json())
                    .then(data => {
                        if (data.success && data.url) {
                            window.location.href = data.url;
                        } else {
                            Swal.fire('Error', 'No se pudo iniciar la verificación', 'error');
                        }
                    })
                    .catch(() => Swal.fire('Error', 'Error al conectar', 'error'));
            });
        });
        <?php endif; ?>

        function toggleMethod(method) {
            document.querySelectorAll('.method-card').forEach(card => card.classList.remove('active'));
            document.querySelectorAll('.method-body').forEach(body => body.classList.remove('active'));
            const selectedCard = document.querySelector(`[data-method="${method}"]`);
            const selectedBody = document.getElementById(`panel-${method}`);
            if (selectedCard && selectedBody) {
                selectedCard.classList.add('active');
                selectedBody.classList.add('active');
            }
        }

        function formatIBAN(input) {
            let value = input.value.replace(/\s/g, '').toUpperCase();
            if (!value.startsWith('ES')) {
                value = 'ES' + value.replace(/[^0-9]/g, '');
            } else {
                value = 'ES' + value.substring(2).replace(/[^0-9]/g, '');
            }
            value = value.substring(0, 24);
            value = value.match(/.{1,4}/g)?.join(' ') || value;
            input.value = value;
        }

        function validateIBANMatch() {
            const iban1 = document.getElementById('iban-input').value.replace(/\s/g, '');
            const iban2 = document.getElementById('iban-confirm').value.replace(/\s/g, '');
            const message = document.getElementById('iban-match-message');
            const submitBtn = document.querySelector('#stripe-complete-form .btn-primary');
            
            if (iban1.length === 0 || iban2.length === 0) {
                message.style.display = 'none';
                if (submitBtn) submitBtn.disabled = true;
                return;
            }
            
            if (iban1 === iban2 && iban1.length === 24) {
                message.textContent = '✓ Los IBAN coinciden';
                message.style.color = '#28a745';
                message.style.display = 'block';
                if (submitBtn) submitBtn.disabled = false;
            } else {
                message.textContent = '✗ Los IBAN no coinciden';
                message.style.color = '#dc3545';
                message.style.display = 'block';
                if (submitBtn) submitBtn.disabled = true;
            }
        }

        function copyTestIBAN() {
            const testIBAN = 'ES07 0012 0345 0300 0006 7890';
            document.getElementById('iban-input').value = testIBAN;
            document.getElementById('iban-confirm').value = testIBAN;
            validateIBANMatch();
            
            Swal.fire({
                icon: 'info',
                title: 'IBAN Test copiado',
                text: 'Se ha rellenado automáticamente el IBAN de prueba',
                timer: 2000,
                showConfirmButton: false
            });
        }
        
        // Activar clase para padding inferior en móvil
        if (document.querySelector('.mobile-bottom-nav')) {
            document.body.classList.add('has-bottom-nav');
        }
    </script>

    <?php include __DIR__ . '/shop-footer.php'; ?>
</body>
</html>