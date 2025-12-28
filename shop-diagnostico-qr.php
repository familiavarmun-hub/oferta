<?php
/**
 * shop-diagnostico-qr.php
 * Script de diagnóstico para verificar estado de entregas y pagos
 */

require_once '../config.php';

header('Content-Type: text/plain; charset=utf-8');

echo "═══════════════════════════════════════════════════════\n";
echo "DIAGNÓSTICO DE ENTREGAS QR - SHOP\n";
echo "═══════════════════════════════════════════════════════\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // 1. VERIFICAR ENTREGAS PENDIENTES
    echo "1️⃣ ENTREGAS PENDIENTES DE LIBERACIÓN:\n";
    echo "─────────────────────────────────────────────────────\n";
    
    $sql = "
        SELECT 
            d.id as delivery_id,
            d.delivery_state,
            d.qr_scanned_at,
            d.payment_released,
            d.payment_released_at,
            d.created_at,
            
            p.id as proposal_id,
            p.status as proposal_status,
            
            pay.id as payment_id,
            pay.payment_intent_id,
            pay.charge_id,
            pay.estado as payment_status,
            pay.monto_total,
            pay.stripe_account_id,
            
            r.title,
            
            COALESCE(trav.full_name, trav.username) as traveler_name,
            
            pm.stripe_account_id as traveler_stripe_connect,
            pm.is_verified as payment_verified,
            
            TIMESTAMPDIFF(MINUTE, d.qr_scanned_at, NOW()) as minutos_desde_escaneo
            
        FROM shop_deliveries d
        LEFT JOIN shop_request_proposals p ON d.proposal_id = p.id
        LEFT JOIN shop_requests r ON p.request_id = r.id
        LEFT JOIN payments_in_custody pay ON d.payment_id = pay.id
        LEFT JOIN accounts trav ON d.traveler_id = trav.id
        LEFT JOIN payment_methods pm ON trav.id = pm.user_id 
            AND pm.method_type = 'stripe_connect'
            AND pm.is_primary = 1
        ORDER BY d.id DESC
        LIMIT 10
    ";
    
    $stmt = $conexion->prepare($sql);
    $stmt->execute();
    $deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($deliveries)) {
        echo "❌ No hay entregas registradas\n\n";
    } else {
        foreach ($deliveries as $d) {
            echo "\n📦 DELIVERY #{$d['delivery_id']}:\n";
            echo "   Producto: {$d['title']}\n";
            echo "   Viajero: {$d['traveler_name']}\n";
            echo "   Estado entrega: {$d['delivery_state']}\n";
            echo "   QR escaneado: " . ($d['qr_scanned_at'] ? date('Y-m-d H:i:s', strtotime($d['qr_scanned_at'])) . " ({$d['minutos_desde_escaneo']} min)" : 'NO') . "\n";
            echo "   Pago liberado: " . ($d['payment_released'] ? 'SÍ' : 'NO') . "\n";
            
            echo "\n   💳 PAGO #{$d['payment_id']}:\n";
            echo "      Estado: {$d['payment_status']}\n";
            echo "      Payment Intent: " . ($d['payment_intent_id'] ?: 'NULL') . "\n";
            echo "      Charge ID: " . ($d['charge_id'] ?: 'NULL') . "\n";
            echo "      Monto: {$d['monto_total']}\n";
            
            echo "\n   👤 STRIPE CONNECT:\n";
            echo "      Account ID (payment): " . ($d['stripe_account_id'] ?: 'NULL') . "\n";
            echo "      Account ID (traveler): " . ($d['traveler_stripe_connect'] ?: 'NULL') . "\n";
            echo "      Verificado: " . ($d['payment_verified'] ? 'SÍ' : 'NO') . "\n";
            
            echo "\n   📊 PROPUESTA #{$d['proposal_id']}:\n";
            echo "      Estado: {$d['proposal_status']}\n";
            
            // VERIFICAR SI CUMPLE CONDICIONES PARA LIBERACIÓN
            echo "\n   ✅ CONDICIONES PARA LIBERACIÓN:\n";
            $condiciones = [
                'delivery_state = delivered' => $d['delivery_state'] === 'delivered',
                'qr_scanned_at IS NOT NULL' => !empty($d['qr_scanned_at']),
                'payment_released = 0' => $d['payment_released'] == 0,
                'payment_status = en_custodia' => $d['payment_status'] === 'en_custodia',
                'payment_intent_id IS NOT NULL' => !empty($d['payment_intent_id']),
                'minutos >= 1' => $d['minutos_desde_escaneo'] >= 1,
                'stripe_connect configurado' => !empty($d['traveler_stripe_connect'])
            ];
            
            $cumple_todas = true;
            foreach ($condiciones as $nombre => $cumple) {
                echo "      " . ($cumple ? '✅' : '❌') . " {$nombre}\n";
                if (!$cumple) $cumple_todas = false;
            }
            
            echo "\n   🎯 " . ($cumple_todas ? '✅ LISTO PARA LIBERAR' : '⚠️ NO CUMPLE CONDICIONES') . "\n";
            echo "   " . str_repeat('─', 50) . "\n";
        }
    }
    
    // 2. VERIFICAR PAYMENT INTENTS EN STRIPE
    echo "\n\n2️⃣ VERIFICACIÓN DE PAYMENT INTENTS EN STRIPE:\n";
    echo "─────────────────────────────────────────────────────\n";
    
    $sql_pending = "
        SELECT 
            pay.id,
            pay.payment_intent_id,
            pay.estado,
            pay.monto_total,
            d.id as delivery_id,
            d.qr_scanned_at
        FROM payments_in_custody pay
        INNER JOIN shop_deliveries d ON pay.id = d.payment_id
        WHERE pay.estado = 'en_custodia'
            AND pay.payment_intent_id IS NOT NULL
            AND d.qr_scanned_at IS NOT NULL
        LIMIT 5
    ";
    
    $stmt_pending = $conexion->prepare($sql_pending);
    $stmt_pending->execute();
    $pending_payments = $stmt_pending->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($pending_payments)) {
        echo "ℹ️ No hay pagos en custodia con QR escaneado\n";
    } else {
        require_once __DIR__ . '/../vendor/autoload.php';
        \Stripe\Stripe::setApiKey(STRIPE_SECRET);
        
        foreach ($pending_payments as $payment) {
            echo "\n💳 Payment #{$payment['id']}:\n";
            echo "   Payment Intent: {$payment['payment_intent_id']}\n";
            echo "   Delivery: #{$payment['delivery_id']}\n";
            echo "   QR escaneado: " . date('Y-m-d H:i:s', strtotime($payment['qr_scanned_at'])) . "\n";
            
            try {
                $pi = \Stripe\PaymentIntent::retrieve($payment['payment_intent_id']);
                echo "   Estado en Stripe: {$pi->status}\n";
                echo "   Monto: " . ($pi->amount / 100) . " {$pi->currency}\n";
                
                if ($pi->status === 'requires_capture') {
                    echo "   ✅ LISTO PARA CAPTURAR\n";
                } elseif ($pi->status === 'succeeded') {
                    echo "   ℹ️ YA CAPTURADO\n";
                } else {
                    echo "   ⚠️ Estado inesperado: {$pi->status}\n";
                }
                
            } catch (Exception $e) {
                echo "   ❌ Error Stripe: " . $e->getMessage() . "\n";
            }
        }
    }
    
    // 3. ESTADÍSTICAS GENERALES
    echo "\n\n3️⃣ ESTADÍSTICAS GENERALES:\n";
    echo "─────────────────────────────────────────────────────\n";
    
    $stats_queries = [
        'Total entregas' => "SELECT COUNT(*) FROM shop_deliveries",
        'Entregas entregadas (delivered)' => "SELECT COUNT(*) FROM shop_deliveries WHERE delivery_state = 'delivered'",
        'Entregas con QR escaneado' => "SELECT COUNT(*) FROM shop_deliveries WHERE qr_scanned_at IS NOT NULL",
        'Pagos liberados' => "SELECT COUNT(*) FROM shop_deliveries WHERE payment_released = 1",
        'Pagos en custodia' => "SELECT COUNT(*) FROM payments_in_custody WHERE estado = 'en_custodia'",
        'Pagos completados' => "SELECT COUNT(*) FROM payments_in_custody WHERE estado = 'completado'"
    ];
    
    foreach ($stats_queries as $label => $query) {
        $count = $conexion->query($query)->fetchColumn();
        echo "   {$label}: {$count}\n";
    }
    
    // 4. ÚLTIMAS ENTREGAS CON QR ESCANEADO
    echo "\n\n4️⃣ ÚLTIMAS 5 ENTREGAS CON QR ESCANEADO:\n";
    echo "─────────────────────────────────────────────────────\n";
    
    $sql_recent = "
        SELECT 
            d.id,
            d.delivery_state,
            d.qr_scanned_at,
            d.payment_released,
            r.title,
            TIMESTAMPDIFF(MINUTE, d.qr_scanned_at, NOW()) as minutos
        FROM shop_deliveries d
        LEFT JOIN shop_request_proposals p ON d.proposal_id = p.id
        LEFT JOIN shop_requests r ON p.request_id = r.id
        WHERE d.qr_scanned_at IS NOT NULL
        ORDER BY d.qr_scanned_at DESC
        LIMIT 5
    ";
    
    $stmt_recent = $conexion->prepare($sql_recent);
    $stmt_recent->execute();
    $recent = $stmt_recent->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($recent)) {
        echo "ℹ️ No hay entregas con QR escaneado\n";
    } else {
        foreach ($recent as $r) {
            echo "\n   Delivery #{$r['id']}: {$r['title']}\n";
            echo "   Escaneado: " . date('Y-m-d H:i:s', strtotime($r['qr_scanned_at'])) . " (hace {$r['minutos']} min)\n";
            echo "   Estado: {$r['delivery_state']}\n";
            echo "   Liberado: " . ($r['payment_released'] ? 'SÍ' : 'NO') . "\n";
        }
    }
    
    // 5. VERIFICAR ESTRUCTURA DE TABLA payments_in_custody
    echo "\n\n5️⃣ ESTRUCTURA DE TABLA payments_in_custody:\n";
    echo "─────────────────────────────────────────────────────\n";
    
    $columns = $conexion->query("SHOW COLUMNS FROM payments_in_custody")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "   {$col['Field']} ({$col['Type']})\n";
    }
    
    echo "\n\n═══════════════════════════════════════════════════════\n";
    echo "FIN DEL DIAGNÓSTICO\n";
    echo "═══════════════════════════════════════════════════════\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}