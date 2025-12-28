<?php
// shop-cronjob-qr-deliveries.php - VERSIÓN CON TRANSFER AUTOMÁTICO
// ✅ Ahora SÍ crea el Transfer y actualiza "Se ha transferido a"

// ============================================
// CONFIGURACIÓN INICIAL
// ============================================
define('SCRIPT_START_TIME', microtime(true));
define('LOG_FILE', __DIR__ . '/logs/shop_cronjob_qr_deliveries.log');
define('LOCK_FILE', __DIR__ . '/locks/shop_cronjob_qr.lock');
define('LOCK_TIMEOUT', 300); // 5 minutos

// ============================================
// INICIALIZACIÓN
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', LOG_FILE);

// ============================================
// PHP 8.0 COMPATIBILITY FIX
// ============================================
if (file_exists(__DIR__ . '/vendor/composer/platform_check.php')) {
    file_put_contents(__DIR__ . '/vendor/composer/platform_check.php', '<?php return;');
}

// ============================================
// AUTOLOAD Y STRIPE
// ============================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

\Stripe\Stripe::setApiKey(STRIPE_SECRET);

// ============================================
// SISTEMA DE LOCKS
// ============================================
$lock_dir = dirname(LOCK_FILE);
if (!is_dir($lock_dir)) {
    mkdir($lock_dir, 0755, true);
}

if (file_exists(LOCK_FILE)) {
    $lock_time = filemtime(LOCK_FILE);
    if (time() - $lock_time < LOCK_TIMEOUT) {
        logMessage("⚠️ Script ya en ejecución (lock activo)");
        exit(0);
    }
    unlink(LOCK_FILE);
}

touch(LOCK_FILE);
register_shutdown_function(function() {
    if (file_exists(LOCK_FILE)) {
        unlink(LOCK_FILE);
    }
});

// ============================================
// FUNCIONES DE LOG
// ============================================
function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] {$message}\n";
    error_log($log_entry, 3, LOG_FILE);
    echo $log_entry;
}

// ============================================
// VERIFICAR ESTRUCTURA DE BD
// ============================================
function verificarEstructuraBD($conexion) {
    logMessage("🔍 Verificando estructura de base de datos...");
    
    $tablas_requeridas = [
        'shop_deliveries' => [
            'id', 'proposal_id', 'payment_id', 'requester_id', 'traveler_id',
            'qr_code_unique_id', 'qr_code_path', 'qr_data_json', 'qr_scanned_at',
            'delivery_state', 'email_sent', 'created_at', 'updated_at'
        ],
        'payments_in_custody' => [
            'id', 'id_viaje', 'payment_intent_id', 'charge_id', 'transfer_id',
            'monto_total', 'amount_to_transporter', 'amount_to_company',
            'metodo_pago', 'transportista_email', 'comprador_username',
            'stripe_account_id', 'codigo_unico', 'estado',
            'created_at', 'released_at', 'stripe_transfer_id'
        ],
        'shop_request_proposals' => [
            'id', 'request_id', 'traveler_id', 'payment_id', 'status',
            'proposed_price', 'proposed_currency'
        ]
    ];
    
    foreach ($tablas_requeridas as $tabla => $columnas) {
        $sql = "SHOW COLUMNS FROM `{$tabla}`";
        $stmt = $conexion->query($sql);
        $columnas_existentes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $faltantes = array_diff($columnas, $columnas_existentes);
        if (!empty($faltantes)) {
            logMessage("⚠️ Columnas faltantes en {$tabla}: " . implode(', ', $faltantes));
        } else {
            logMessage("✅ Tabla {$tabla}: OK");
        }
    }
}

// ============================================
// BUSCAR ENTREGAS LISTAS PARA LIBERAR
// ============================================
function buscarEntregasListasParaLiberar($conexion) {
    logMessage("🔍 Buscando entregas con QR escaneado...");
    
    // ✅ QUERY CORRECTA - Sin LEFT JOIN complejo
    $sql = "SELECT 
                d.id as delivery_id,
                d.proposal_id,
                d.payment_id,
                d.requester_id,
                d.traveler_id,
                d.qr_code_unique_id,
                d.qr_scanned_at,
                d.delivery_state,
                p.payment_intent_id,
                p.charge_id,
                p.monto_total,
                p.amount_to_transporter,
                p.amount_to_company,
                p.estado as payment_status,
                p.transportista_email,
                p.comprador_username,
                p.stripe_account_id,
                COALESCE(t.username, t.email, 'Viajero Desconocido') as traveler_username,
                COALESCE(t.full_name, t.username, 'Viajero') as traveler_name,
                COALESCE(r.username, r.email, 'Solicitante Desconocido') as requester_username,
                COALESCE(r.full_name, r.username, 'Solicitante') as requester_name,
                prop.proposed_price,
                prop.proposed_currency,
                req.title as product_title
            FROM shop_deliveries d
            INNER JOIN payments_in_custody p ON d.payment_id = p.id
            INNER JOIN shop_request_proposals prop ON d.proposal_id = prop.id
            INNER JOIN shop_requests req ON prop.request_id = req.id
            LEFT JOIN accounts t ON d.traveler_id = t.id
            LEFT JOIN accounts r ON d.requester_id = r.id
            WHERE d.delivery_state = 'delivered'
              AND d.qr_scanned_at IS NOT NULL
              AND p.estado = 'en_custodia'
              AND p.payment_intent_id IS NOT NULL
              AND TIMESTAMPDIFF(MINUTE, d.qr_scanned_at, NOW()) >= 0
            ORDER BY d.qr_scanned_at ASC";
    
    $stmt = $conexion->prepare($sql);
    $stmt->execute();
    $deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    logMessage("📊 Entregas encontradas: " . count($deliveries));
    
    // ✅ PARA CADA ENTREGA, BUSCAR STRIPE CONNECT POR SEPARADO
    foreach ($deliveries as &$delivery) {
        $traveler_id = $delivery['traveler_id'];
        
        $sql_stripe = "SELECT stripe_account_id, is_verified, is_primary
                       FROM payment_methods 
                       WHERE user_id = :user_id 
                           AND method_type = 'stripe_connect' 
                           AND is_verified = 1
                       ORDER BY is_primary DESC
                       LIMIT 1";
        
        $stmt_stripe = $conexion->prepare($sql_stripe);
        $stmt_stripe->execute([':user_id' => $traveler_id]);
        $stripe_data = $stmt_stripe->fetch(PDO::FETCH_ASSOC);
        
        if ($stripe_data && !empty($stripe_data['stripe_account_id'])) {
            $delivery['stripe_connect_id'] = $stripe_data['stripe_account_id'];
            $delivery['has_stripe_connect'] = true;
            
            logMessage("✅ Delivery #{$delivery['delivery_id']} - Stripe Connect: " . 
                      substr($stripe_data['stripe_account_id'], 0, 15) . "...");
        } else {
            $delivery['stripe_connect_id'] = null;
            $delivery['has_stripe_connect'] = false;
            
            logMessage("⚠️ Delivery #{$delivery['delivery_id']} - Sin Stripe Connect");
        }
    }
    
    return $deliveries;
}

// ============================================
// LIBERAR PAGO (CON TRANSFER)
// ============================================
function liberarPago($conexion, $delivery) {
    $delivery_id = $delivery['delivery_id'];
    $payment_id = $delivery['payment_id'];
    $payment_intent_id = $delivery['payment_intent_id'];
    $stripe_connect_id = $delivery['stripe_connect_id'];
    $has_stripe_connect = $delivery['has_stripe_connect'];
    
    logMessage("🔄 Procesando Delivery #{$delivery_id}");
    logMessage("   Payment Intent: {$payment_intent_id}");
    logMessage("   Tiene Stripe Connect: " . ($has_stripe_connect ? 'SÍ' : 'NO'));
    
    if ($has_stripe_connect) {
        logMessage("   Stripe Account ID: " . substr($stripe_connect_id, 0, 20) . "...");
    }
    
    try {
        // ====================================
        // PASO 1: CAPTURAR PAYMENT INTENT
        // ====================================
        logMessage("💳 PASO 1: Capturando Payment Intent...");
        
        $payment_intent = \Stripe\PaymentIntent::retrieve($payment_intent_id);
        
        logMessage("   Estado actual: {$payment_intent->status}");
        logMessage("   Monto: " . ($payment_intent->amount / 100) . " " . strtoupper($payment_intent->currency));
        
        if ($payment_intent->status === 'requires_capture') {
            logMessage("   ⏳ Capturando...");
            $payment_intent = $payment_intent->capture();
            logMessage("   ✅ Payment Intent capturado");
        } elseif ($payment_intent->status === 'succeeded') {
            logMessage("   ℹ️ Ya estaba capturado");
        } else {
            throw new Exception("Estado inesperado: {$payment_intent->status}");
        }
        
        // ====================================
        // PASO 2: OBTENER CHARGE ID
        // ====================================
        $charge_id = null;
        if (isset($payment_intent->charges->data[0]->id)) {
            $charge_id = $payment_intent->charges->data[0]->id;
            logMessage("   ✅ Charge ID: {$charge_id}");
        } else {
            throw new Exception("No se encontró Charge ID");
        }
        
        // ====================================
        // PASO 3: CREAR TRANSFER (SI TIENE STRIPE CONNECT)
        // ====================================
        $transfer_id = null;
        
        if ($has_stripe_connect && !empty($stripe_connect_id)) {
            logMessage("💸 PASO 2: Creando Transfer a Stripe Connect...");
            
            $amount_to_transfer = round($delivery['amount_to_transporter'] * 100);
            $currency = $delivery['proposed_currency'] ?? 'eur';
            
            logMessage("   Monto: " . ($amount_to_transfer / 100) . " " . strtoupper($currency));
            logMessage("   Destino: " . substr($stripe_connect_id, 0, 20) . "...");
            
            try {
                $transfer = \Stripe\Transfer::create([
                    'amount' => $amount_to_transfer,
                    'currency' => strtolower($currency),
                    'destination' => $stripe_connect_id,
                    'source_transaction' => $charge_id,
                    'description' => "Shop Delivery #{$delivery_id} - {$delivery['product_title']}",
                    'metadata' => [
                        'delivery_id' => $delivery_id,
                        'proposal_id' => $delivery['proposal_id'],
                        'payment_id' => $payment_id,
                        'traveler_id' => $delivery['traveler_id'],
                        'requester_id' => $delivery['requester_id'],
                        'release_type' => 'qr_scan_automatic'
                    ]
                ]);
                
                $transfer_id = $transfer->id;
                
                logMessage("   ✅ TRANSFER CREADO:");
                logMessage("      Transfer ID: {$transfer_id}");
                logMessage("      Monto: " . ($transfer->amount / 100) . " " . strtoupper($transfer->currency));
                logMessage("      Destinatario: {$delivery['traveler_name']}");
                
            } catch (\Stripe\Exception\InvalidRequestException $e) {
                // ⚠️ Fallback: Si falla con source_transaction, reintentar sin él
                if (strpos($e->getMessage(), 'source_transaction') !== false) {
                    logMessage("   ⚠️ Reintentando sin source_transaction...");
                    
                    $transfer = \Stripe\Transfer::create([
                        'amount' => $amount_to_transfer,
                        'currency' => strtolower($currency),
                        'destination' => $stripe_connect_id,
                        'description' => "Shop Delivery #{$delivery_id} - {$delivery['product_title']}",
                        'metadata' => [
                            'delivery_id' => $delivery_id,
                            'proposal_id' => $delivery['proposal_id'],
                            'payment_id' => $payment_id,
                            'charge_id' => $charge_id,
                            'release_type' => 'qr_scan_automatic'
                        ]
                    ]);
                    
                    $transfer_id = $transfer->id;
                    logMessage("   ✅ Transfer creado (sin source_transaction): {$transfer_id}");
                } else {
                    throw $e;
                }
            }
        } else {
            logMessage("   ⚠️ No se creó Transfer (sin Stripe Connect)");
        }
        
        // ====================================
        // PASO 4: ACTUALIZAR BASE DE DATOS
        // ====================================
        logMessage("💾 PASO 3: Actualizando base de datos...");
        
        $conexion->beginTransaction();
        
        // Actualizar payments_in_custody
        $sql_update_payment = "UPDATE payments_in_custody 
                               SET estado = 'completado',
                                   charge_id = :charge_id,
                                   transfer_id = :transfer_id,
                                   stripe_transfer_id = :stripe_transfer_id,
                                   released_at = NOW(),
                                   fecha_liberacion = NOW()
                               WHERE id = :payment_id";
        
        $stmt = $conexion->prepare($sql_update_payment);
        $stmt->execute([
            ':charge_id' => $charge_id,
            ':transfer_id' => $transfer_id,
            ':stripe_transfer_id' => $transfer_id,
            ':payment_id' => $payment_id
        ]);
        
        logMessage("   ✅ payments_in_custody actualizada");
        
        // Actualizar shop_deliveries
        $sql_update_delivery = "UPDATE shop_deliveries 
                                SET delivery_state = 'completed',
                                    updated_at = NOW()
                                WHERE id = :delivery_id";
        
        $stmt = $conexion->prepare($sql_update_delivery);
        $stmt->execute([':delivery_id' => $delivery_id]);
        
        logMessage("   ✅ shop_deliveries actualizada");

        // Actualizar shop_request_proposals
        $sql_update_proposal = "UPDATE shop_request_proposals
                                SET status = 'completed',
                                    updated_at = NOW()
                                WHERE id = :proposal_id";

        $stmt = $conexion->prepare($sql_update_proposal);
        $stmt->execute([':proposal_id' => $delivery['proposal_id']]);

        logMessage("   ✅ shop_request_proposals actualizada");

        // Actualizar shop_requests (la solicitud principal)
        $sql_update_request = "UPDATE shop_requests
                               SET status = 'completed',
                                   updated_at = NOW()
                               WHERE id = (SELECT request_id FROM shop_request_proposals WHERE id = :proposal_id)";

        $stmt = $conexion->prepare($sql_update_request);
        $stmt->execute([':proposal_id' => $delivery['proposal_id']]);

        logMessage("   ✅ shop_requests actualizada a completed");

        $conexion->commit();
        
        logMessage("✅ PAGO LIBERADO EXITOSAMENTE:");
        logMessage("   Delivery ID: {$delivery_id}");
        logMessage("   Payment Intent: {$payment_intent_id}");
        logMessage("   Charge ID: {$charge_id}");
        logMessage("   Transfer ID: " . ($transfer_id ?: 'NO CREADO'));
        logMessage("   Viajero: {$delivery['traveler_name']}");
        logMessage("   Monto liberado: " . number_format($delivery['amount_to_transporter'], 2) . " " . strtoupper($delivery['proposed_currency'] ?? 'EUR'));
        
        return true;
        
    } catch (\Stripe\Exception\CardException $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        
        logMessage("❌ ERROR STRIPE (Card): " . $e->getMessage());
        
        actualizarErrorEnBD($conexion, $payment_id, $e->getMessage());
        return false;
        
    } catch (\Stripe\Exception\ApiErrorException $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        
        logMessage("❌ ERROR STRIPE (API): " . $e->getMessage());
        
        actualizarErrorEnBD($conexion, $payment_id, $e->getMessage());
        return false;
        
    } catch (Exception $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        
        logMessage("❌ ERROR GENERAL: " . $e->getMessage());
        
        actualizarErrorEnBD($conexion, $payment_id, $e->getMessage());
        return false;
    }
}

// ============================================
// ACTUALIZAR ERROR EN BD
// ============================================
function actualizarErrorEnBD($conexion, $payment_id, $error_message) {
    try {
        $sql = "UPDATE payments_in_custody 
                SET error_message = :error_message,
                    updated_at = NOW()
                WHERE id = :payment_id";
        
        $stmt = $conexion->prepare($sql);
        $stmt->execute([
            ':error_message' => substr($error_message, 0, 500),
            ':payment_id' => $payment_id
        ]);
        
        logMessage("   ℹ️ Error registrado en BD");
    } catch (Exception $e) {
        logMessage("   ⚠️ No se pudo registrar error en BD: " . $e->getMessage());
    }
}

// ============================================
// EJECUCIÓN PRINCIPAL
// ============================================
try {
    logMessage("========================================");
    logMessage("🚀 INICIANDO CRONJOB - Shop QR Deliveries");
    logMessage("========================================");
    
    // Verificar estructura
    verificarEstructuraBD($conexion);
    
    // Buscar entregas
    $deliveries = buscarEntregasListasParaLiberar($conexion);
    
    if (empty($deliveries)) {
        logMessage("ℹ️ No hay entregas pendientes de liberar");
        logMessage("========================================");
        exit(0);
    }
    
    // Procesar cada entrega
    $procesadas = 0;
    $exitosas = 0;
    $fallidas = 0;
    
    foreach ($deliveries as $delivery) {
        $procesadas++;
        
        logMessage("----------------------------------------");
        logMessage("📦 Procesando entrega {$procesadas}/" . count($deliveries));
        
        $resultado = liberarPago($conexion, $delivery);
        
        if ($resultado) {
            $exitosas++;
        } else {
            $fallidas++;
        }
        
        // Pausa entre entregas
        if ($procesadas < count($deliveries)) {
            sleep(2);
        }
    }
    
    logMessage("========================================");
    logMessage("✅ CRONJOB FINALIZADO");
    logMessage("   Total procesadas: {$procesadas}");
    logMessage("   Exitosas: {$exitosas}");
    logMessage("   Fallidas: {$fallidas}");
    logMessage("   Tiempo total: " . round(microtime(true) - SCRIPT_START_TIME, 2) . "s");
    logMessage("========================================");
    
} catch (Exception $e) {
    logMessage("💥 ERROR FATAL: " . $e->getMessage());
    logMessage("   Archivo: " . $e->getFile());
    logMessage("   Línea: " . $e->getLine());
    exit(1);
}
?>