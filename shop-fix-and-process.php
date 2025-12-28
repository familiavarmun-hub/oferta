<?php
/**
 * shop-fix-and-process.php
 * Actualizar estados y procesar liberaciones pendientes
 */

require_once '../config.php';
require_once '../vendor/autoload.php';

header('Content-Type: text/plain; charset=utf-8');

echo "═══════════════════════════════════════════════════════\n";
echo "CORRECCIÓN Y PROCESAMIENTO DE PAGOS\n";
echo "═══════════════════════════════════════════════════════\n\n";

try {
    \Stripe\Stripe::setApiKey(STRIPE_SECRET);
    
    // 1. ACTUALIZAR ESTADOS VACÍOS A 'en_custodia'
    echo "1️⃣ Actualizando estados vacíos...\n\n";
    
    $sql_update = "
        UPDATE payments_in_custody 
        SET estado = 'en_custodia'
        WHERE (estado IS NULL OR estado = '' OR estado = 'PENDING' OR estado = 'AUTHORIZED')
            AND payment_intent_id IS NOT NULL
    ";
    
    $updated = $conexion->exec($sql_update);
    echo "   ✅ Actualizados {$updated} registros con Payment Intent\n\n";
    
    // 2. BUSCAR ENTREGAS PENDIENTES DE LIBERACIÓN
    echo "2️⃣ Buscando entregas pendientes...\n\n";
    
    $sql = "
        SELECT 
            d.id as delivery_id,
            d.proposal_id,
            d.qr_scanned_at,
            pay.id as payment_id,
            pay.payment_intent_id,
            pay.charge_id,
            pay.monto_total,
            pay.estado,
            p.proposed_currency,
            r.title,
            COALESCE(trav.full_name, trav.username) as traveler_name,
            trav.email as traveler_email,
            pm.stripe_account_id as traveler_stripe_account,
            TIMESTAMPDIFF(MINUTE, d.qr_scanned_at, NOW()) as minutos
        FROM shop_deliveries d
        INNER JOIN shop_request_proposals p ON d.proposal_id = p.id
        INNER JOIN shop_requests r ON p.request_id = r.id
        INNER JOIN payments_in_custody pay ON d.payment_id = pay.id
        INNER JOIN accounts trav ON d.traveler_id = trav.id
        LEFT JOIN payment_methods pm ON trav.id = pm.user_id 
            AND pm.method_type = 'stripe_connect'
            AND pm.is_primary = 1
        WHERE d.delivery_state = 'delivered'
            AND d.payment_released = 0
            AND d.qr_scanned_at IS NOT NULL
            AND pay.estado = 'en_custodia'
            AND TIMESTAMPDIFF(MINUTE, d.qr_scanned_at, NOW()) >= 1
        ORDER BY d.qr_scanned_at ASC
    ";
    
    $stmt = $conexion->prepare($sql);
    $stmt->execute();
    $entregas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($entregas)) {
        echo "   ℹ️ No hay entregas pendientes\n\n";
    } else {
        echo "   🎯 Encontradas " . count($entregas) . " entregas para procesar\n\n";
        
        foreach ($entregas as $entrega) {
            echo "───────────────────────────────────────────────────\n";
            echo "📦 PROCESANDO DELIVERY #{$entrega['delivery_id']}\n";
            echo "   Producto: {$entrega['title']}\n";
            echo "   Viajero: {$entrega['traveler_name']}\n";
            echo "   Payment Intent: {$entrega['payment_intent_id']}\n";
            echo "   Monto: €{$entrega['monto_total']}\n";
            echo "   Tiempo desde QR: {$entrega['minutos']} minutos\n\n";
            
            try {
                $conexion->beginTransaction();
                
                // Calcular montos
                $monto_total = floatval($entrega['monto_total']);
                $monto_viajero = round($monto_total * 0.80, 2);
                $monto_plataforma = round($monto_total * 0.20, 2);
                
                echo "   💰 Distribución:\n";
                echo "      Viajero (80%): €{$monto_viajero}\n";
                echo "      Plataforma (20%): €{$monto_plataforma}\n\n";
                
                // Capturar Payment Intent
                echo "   🔍 Recuperando Payment Intent...\n";
                $pi = \Stripe\PaymentIntent::retrieve($entrega['payment_intent_id']);
                echo "      Estado: {$pi->status}\n";
                
                if ($pi->status === 'requires_capture') {
                    echo "   💰 Capturando...\n";
                    $pi->capture();
                    echo "      ✅ Capturado\n\n";
                } elseif ($pi->status === 'succeeded') {
                    echo "      ℹ️ Ya capturado\n\n";
                } else {
                    throw new Exception("Estado inesperado: {$pi->status}");
                }
                
                // Obtener Charge ID
                $charge_id = null;
                if (!empty($pi->charges->data)) {
                    $charge_id = $pi->charges->data[0]->id;
                    echo "   🔖 Charge ID: {$charge_id}\n\n";
                }
                
                // Transfer si tiene Stripe Connect
                $transfer_id = null;
                if (!empty($entrega['traveler_stripe_account']) && !empty($charge_id)) {
                    echo "   💸 Creando Transfer a {$entrega['traveler_stripe_account']}...\n";
                    
                    $transfer = \Stripe\Transfer::create([
                        'amount' => (int)($monto_viajero * 100),
                        'currency' => strtolower($entrega['proposed_currency'] ?? 'eur'),
                        'destination' => $entrega['traveler_stripe_account'],
                        'source_transaction' => $charge_id,
                        'description' => "Shop Delivery #{$entrega['delivery_id']}",
                        'metadata' => [
                            'delivery_id' => $entrega['delivery_id'],
                            'payment_id' => $entrega['payment_id'],
                            'release_type' => 'MANUAL_FIX'
                        ]
                    ]);
                    
                    $transfer_id = $transfer->id;
                    echo "      ✅ Transfer: {$transfer_id}\n\n";
                } else {
                    echo "   ⚠️ Sin Stripe Connect - sin transfer automático\n\n";
                }
                
                // Actualizar BD
                echo "   💾 Actualizando base de datos...\n";
                
                $sql_payment = "
                    UPDATE payments_in_custody 
                    SET estado = 'completado',
                        amount_to_transporter = :amount_traveler,
                        amount_to_company = :amount_platform,
                        stripe_transfer_id = :transfer_id,
                        fecha_liberacion = NOW()
                    WHERE id = :payment_id
                ";
                
                $stmt = $conexion->prepare($sql_payment);
                $stmt->execute([
                    ':amount_traveler' => $monto_viajero,
                    ':amount_platform' => $monto_plataforma,
                    ':transfer_id' => $transfer_id,
                    ':payment_id' => $entrega['payment_id']
                ]);
                
                $sql_delivery = "
                    UPDATE shop_deliveries 
                    SET payment_released = 1,
                        payment_released_at = NOW()
                    WHERE id = :delivery_id
                ";
                
                $stmt = $conexion->prepare($sql_delivery);
                $stmt->execute([':delivery_id' => $entrega['delivery_id']]);
                
                $sql_proposal = "
                    UPDATE shop_request_proposals 
                    SET status = 'completed',
                        updated_at = NOW()
                    WHERE id = :proposal_id
                ";
                
                $stmt = $conexion->prepare($sql_proposal);
                $stmt->execute([':proposal_id' => $entrega['proposal_id']]);
                
                $conexion->commit();
                
                echo "      ✅ payments_in_custody\n";
                echo "      ✅ shop_deliveries\n";
                echo "      ✅ shop_request_proposals\n\n";
                
                echo "   🎉 PAGO LIBERADO EXITOSAMENTE\n";
                echo "      Viajero: {$entrega['traveler_name']}\n";
                echo "      Email: {$entrega['traveler_email']}\n";
                echo "      Monto: €{$monto_viajero}\n";
                
                if ($transfer_id) {
                    echo "      Transfer ID: {$transfer_id}\n";
                } else {
                    echo "      ⚠️ TRANSFERIR MANUALMENTE €{$monto_viajero}\n";
                }
                
                echo "\n";
                
            } catch (Exception $e) {
                $conexion->rollBack();
                echo "   ❌ ERROR: " . $e->getMessage() . "\n\n";
            }
            
            sleep(1);
        }
    }
    
    // 3. RESUMEN FINAL
    echo "\n3️⃣ RESUMEN FINAL:\n";
    echo "─────────────────────────────────────────────────────\n";
    
    $stats = [
        'Pagos liberados' => "SELECT COUNT(*) FROM shop_deliveries WHERE payment_released = 1",
        'Pagos pendientes' => "SELECT COUNT(*) FROM shop_deliveries WHERE payment_released = 0 AND delivery_state = 'delivered'",
        'Pagos en custodia' => "SELECT COUNT(*) FROM payments_in_custody WHERE estado = 'en_custodia'",
        'Pagos completados' => "SELECT COUNT(*) FROM payments_in_custody WHERE estado = 'completado'"
    ];
    
    foreach ($stats as $label => $query) {
        $count = $conexion->query($query)->fetchColumn();
        echo "   {$label}: {$count}\n";
    }
    
    echo "\n✅ PROCESO COMPLETADO\n";
    echo "═══════════════════════════════════════════════════════\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR CRÍTICO: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}