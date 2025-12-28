<?php
// shop/pagos_cobros.php - Pagos y Cobros del Shop
// Usa la tabla payments_in_custody para transacciones del Shop

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['usuario_id'])) {
    $current_url = 'shop/pagos_cobros.php';
    if (!empty($_SERVER['QUERY_STRING'])) {
        $current_url .= '?' . $_SERVER['QUERY_STRING'];
    }
    header('Content-Type: text/html; charset=UTF-8');
    echo "<!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <script>
            localStorage.setItem('redirect_after_login', '$current_url');
            window.location.href = 'shop-login.php';
        </script>
    </head>
    <body></body>
    </html>";
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$usuario_email = $_SESSION['email'] ?? '';
$usuario_nombre = $_SESSION['usuario_nombre'] ?? $_SESSION['username'] ?? '';

// Inicializar variables de filtro
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : 'todos';
$filtro_fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '';
$filtro_fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '';
$filtro_tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'pagos';

try {
    $pagos = [];
    $cobros = [];

    if ($filtro_tipo === 'pagos') {
        // PAGOS REALIZADOS: donde el usuario es el comprador (comprador_username = email del usuario)
        $sql_pagos = "
            SELECT 
                pic.id,
                pic.id_viaje as request_id,
                pic.payment_intent_id,
                pic.charge_id,
                pic.monto_total,
                pic.amount_to_transporter,
                pic.amount_to_company as comision,
                pic.metodo_pago,
                pic.transportista_email,
                pic.comprador_username,
                pic.codigo_unico,
                pic.estado,
                pic.created_at as fecha,
                pic.fecha_liberacion,
                pic.stripe_account_id,
                sr.title as producto,
                sr.destination_city as destino,
                sr.origin_country as origen,
                COALESCE(a.full_name, a.username, pic.transportista_email) as viajero_nombre
            FROM payments_in_custody pic
            LEFT JOIN shop_requests sr ON sr.id = pic.id_viaje
            LEFT JOIN accounts a ON a.email = pic.transportista_email
            WHERE pic.comprador_username = :usuario_email
            ORDER BY pic.created_at DESC
        ";

        $stmt_pagos = $conexion->prepare($sql_pagos);
        $stmt_pagos->bindParam(':usuario_email', $usuario_email, PDO::PARAM_STR);
        $stmt_pagos->execute();
        $pagos = $stmt_pagos->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($filtro_tipo === 'cobros') {
        // COBROS RECIBIDOS: donde el usuario es el viajero/transportista
        $sql_cobros = "
            SELECT 
                pic.id,
                pic.id_viaje as request_id,
                pic.payment_intent_id,
                pic.charge_id,
                pic.monto_total,
                pic.amount_to_transporter as mi_ganancia,
                pic.amount_to_company as comision,
                pic.metodo_pago,
                pic.transportista_email,
                pic.comprador_username,
                pic.codigo_unico,
                pic.estado,
                pic.created_at as fecha,
                pic.fecha_liberacion,
                pic.stripe_account_id,
                pic.stripe_transfer_id,
                sr.title as producto,
                sr.destination_city as destino,
                sr.origin_country as origen,
                COALESCE(a.full_name, a.username, pic.comprador_username) as cliente_nombre
            FROM payments_in_custody pic
            LEFT JOIN shop_requests sr ON sr.id = pic.id_viaje
            LEFT JOIN accounts a ON a.email = pic.comprador_username
            WHERE pic.transportista_email = :usuario_email
            ORDER BY pic.created_at DESC
        ";

        $stmt_cobros = $conexion->prepare($sql_cobros);
        $stmt_cobros->bindParam(':usuario_email', $usuario_email, PDO::PARAM_STR);
        $stmt_cobros->execute();
        $cobros = $stmt_cobros->fetchAll(PDO::FETCH_ASSOC);
    }

    // Aplicar filtros
    $data_to_filter = $filtro_tipo === 'pagos' ? $pagos : $cobros;
    $filtered_data = [];

    foreach ($data_to_filter as $item) {
        // Filtrar por estado
        if ($filtro_estado !== 'todos' && strtolower($item['estado']) !== strtolower($filtro_estado)) {
            continue;
        }
        
        // Filtrar por fechas
        $fecha_item = strtotime($item['fecha']);
        $inicio = !empty($filtro_fecha_inicio) ? strtotime($filtro_fecha_inicio . ' 00:00:00') : null;
        $fin = !empty($filtro_fecha_fin) ? strtotime($filtro_fecha_fin . ' 23:59:59') : null;

        if (($inicio && $fecha_item < $inicio) || ($fin && $fecha_item > $fin)) {
            continue;
        }
        
        $filtered_data[] = $item;
    }

    // Actualizar las variables originales
    if ($filtro_tipo === 'pagos') {
        $pagos = $filtered_data;
    } else {
        $cobros = $filtered_data;
    }

    // Calcular totales para el resumen (solo cobros)
    $total_en_custodia = 0;
    $total_liberado = 0;
    $total_cancelado = 0;
    
    if ($filtro_tipo === 'cobros' && !empty($cobros)) {
        foreach ($cobros as $cobro) {
            $ganancia = $cobro['mi_ganancia'] ?? 0;
            $estado = strtolower($cobro['estado']);
            
            switch ($estado) {
                case 'en_custodia':
                    $total_en_custodia += $ganancia;
                    break;
                case 'completado':
                case 'liberado':
                case 'released':
                    $total_liberado += $ganancia;
                    break;
                case 'cancelado':
                case 'reembolsado':
                case 'refunded':
                    $total_cancelado += $ganancia;
                    break;
            }
        }
    }

} catch (PDOException $e) {
    error_log("Error en pagos_cobros.php: " . $e->getMessage());
    die("Error al obtener los datos. Por favor, intenta más tarde.");
}

// Función para obtener badge del estado
function getEstadoBadge($estado) {
    $estado = strtolower($estado);
    switch ($estado) {
        case 'en_custodia':
            return "<span class='status-badge custodia'><i class='fas fa-lock'></i> En Custodia</span>";
        case 'completado':
        case 'liberado':
        case 'released':
            return "<span class='status-badge liberado'><i class='fas fa-check-circle'></i> Completado</span>";
        case 'cancelado':
            return "<span class='status-badge cancelado'><i class='fas fa-times-circle'></i> Cancelado</span>";
        case 'reembolsado':
        case 'refunded':
            return "<span class='status-badge reembolsado'><i class='fas fa-undo'></i> Reembolsado</span>";
        case 'pending':
        case 'pendiente':
            return "<span class='status-badge pendiente'><i class='fas fa-clock'></i> Pendiente</span>";
        default:
            return "<span class='status-badge otros'>" . htmlspecialchars(ucfirst($estado)) . "</span>";
    }
}

// Función para obtener icono del método de pago
function getPaymentIcon($metodo) {
    $metodo = strtolower($metodo ?? 'stripe');
    switch ($metodo) {
        case 'stripe':
        case 'tarjeta':
        case 'card':
            return '<i class="fas fa-credit-card" style="color: #6772e5;"></i>';
        case 'paypal':
            return '<i class="fab fa-paypal" style="color: #003087;"></i>';
        default:
            return '<i class="fas fa-money-bill-wave" style="color: #42ba25;"></i>';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagos y Cobros - SendVialo Shop</title>
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/footer.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="../Imagenes/globo5.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }
        
        body.has-bottom-nav {
            padding-bottom: 80px;
        }

        .container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 30px;
            background: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            border-radius: 12px;
        }

        .header-section {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 3px solid #42ba25;
        }

        .header-section h1 {
            color: #2c3e50;
            font-size: 2.5em;
            font-weight: 700;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #42ba25 0%, #2d8518 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header-section p {
            color: #7f8c8d;
            font-size: 1.1em;
        }

        .tabs {
            display: flex;
            margin-bottom: 30px;
            border-bottom: 3px solid #ecf0f1;
            overflow-x: auto;
        }

        .tab {
            padding: 15px 30px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.1em;
            font-weight: 600;
            color: #7f8c8d;
            text-decoration: none;
            white-space: nowrap;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: -3px;
        }

        .tab:hover {
            color: #42ba25;
            background: rgba(66, 186, 37, 0.05);
        }

        .tab.active {
            color: #42ba25;
            border-bottom-color: #42ba25;
            background: rgba(66, 186, 37, 0.08);
        }

        /* Resumen colapsable */
        .summary-collapsible {
            margin-bottom: 30px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            overflow: hidden;
            background: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        .collapsible-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 24px;
            background: linear-gradient(135deg, #42ba25 0%, #37a01f 100%);
            color: white;
            cursor: pointer;
            transition: all 0.3s;
        }

        .collapsible-header:hover {
            background: linear-gradient(135deg, #37a01f 0%, #2e8a19 100%);
        }

        .collapsible-title {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .collapsible-total {
            font-size: 1.2rem;
            font-weight: 700;
        }

        .collapsible-icon {
            font-size: 1.2rem;
            transition: transform 0.3s;
        }

        .collapsible-icon.rotated {
            transform: rotate(180deg);
        }

        .collapsible-body {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease;
        }

        .collapsible-body.expanded {
            max-height: 500px;
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            padding: 20px;
        }

        .summary-card {
            background: white;
            padding: 20px 15px;
            border-radius: 12px;
            text-align: center;
            transition: all 0.3s ease;
            border: 3px solid;
            position: relative;
        }

        .summary-card:hover {
            transform: translateY(-3px);
        }

        .summary-card.custodia {
            border-color: #ffc107;
            background: linear-gradient(180deg, white 0%, #fffef9 100%);
        }

        .summary-card.liberado {
            border-color: #28a745;
            background: linear-gradient(180deg, white 0%, #f9fef9 100%);
        }

        .summary-card.cancelado {
            border-color: #dc3545;
            background: linear-gradient(180deg, white 0%, #fef9f9 100%);
        }

        .summary-icon {
            font-size: 2rem;
            margin-bottom: 8px;
        }

        .summary-title {
            font-size: 0.75rem;
            font-weight: 700;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .summary-card.custodia .summary-title { color: #856404; }
        .summary-card.liberado .summary-title { color: #155724; }
        .summary-card.cancelado .summary-title { color: #721c24; }

        .summary-amount {
            font-size: 1.5rem;
            font-weight: 900;
            margin-bottom: 6px;
        }

        .summary-card.custodia .summary-amount { color: #ffc107; }
        .summary-card.liberado .summary-amount { color: #28a745; }
        .summary-card.cancelado .summary-amount { color: #dc3545; }

        .summary-description {
            font-size: 0.7rem;
            color: #6c757d;
            font-weight: 500;
        }

        /* Filtros */
        .filters-section {
            padding: 20px 0;
            margin-bottom: 20px;
        }

        .btn-abrir-filtros {
            background: linear-gradient(135deg, #42ba25 0%, #37a01f 100%);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 1em;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(66, 186, 37, 0.3);
            transition: all 0.3s ease;
        }

        .btn-abrir-filtros:hover {
            background: linear-gradient(135deg, #37a01f 0%, #2e8a19 100%);
            transform: translateY(-2px);
        }

        /* Modal filtros */
        .modal-filtros {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }

        .modal-filtros.active {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .modal-contenido {
            background-color: white;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            background: linear-gradient(135deg, #42ba25 0%, #37a01f 100%);
            color: white;
            padding: 20px 25px;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.3em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-cerrar-modal {
            background: none;
            border: none;
            color: white;
            font-size: 1.8em;
            cursor: pointer;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            transition: background 0.3s;
        }

        .btn-cerrar-modal:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .modal-body {
            padding: 25px;
        }

        .filtro-grupo {
            margin-bottom: 18px;
        }

        .filtro-grupo label {
            display: block;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 0.95em;
        }

        .filtro-grupo select,
        .filtro-grupo input {
            width: 100%;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 12px;
            font-size: 1em;
            transition: border-color 0.3s;
        }

        .filtro-grupo select:focus,
        .filtro-grupo input:focus {
            outline: none;
            border-color: #42ba25;
        }

        .filtros-acciones {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-aplicar {
            flex: 1;
            background: linear-gradient(135deg, #42ba25 0%, #37a01f 100%);
            color: white;
            padding: 14px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-limpiar {
            padding: 14px 20px;
            background: #ecf0f1;
            color: #7f8c8d;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Tabla */
        .table-wrapper {
            overflow-x: auto;
            margin-bottom: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        thead {
            background: linear-gradient(135deg, #42ba25 0%, #37a01f 100%);
            color: white;
        }

        thead th {
            padding: 16px;
            text-align: left;
            font-weight: 600;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        tbody tr {
            border-bottom: 1px solid #e0e0e0;
            transition: background 0.3s;
        }

        tbody tr:hover {
            background: #f8f9fa;
        }

        tbody td {
            padding: 16px;
            font-size: 0.95em;
            vertical-align: middle;
        }

        .product-cell {
            max-width: 200px;
        }

        .product-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 4px;
        }

        .product-route {
            font-size: 0.85em;
            color: #7f8c8d;
        }

        .amount {
            font-weight: 700;
            color: #42ba25;
            font-size: 1.1em;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .status-badge.custodia {
            background: #fff3cd;
            color: #856404;
        }

        .status-badge.liberado {
            background: #d4edda;
            color: #155724;
        }

        .status-badge.cancelado {
            background: #f8d7da;
            color: #721c24;
        }

        .status-badge.reembolsado {
            background: #e2e3e5;
            color: #383d41;
        }

        .status-badge.pendiente {
            background: #cce5ff;
            color: #004085;
        }

        .status-badge.otros {
            background: #f8f9fa;
            color: #6c757d;
        }

        .btn-action {
            background: linear-gradient(135deg, #42ba25 0%, #37a01f 100%);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.85em;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(66, 186, 37, 0.3);
        }

        /* Mobile Cards */
        .mobile-cards {
            display: none;
        }

        .payment-card-mobile {
            background: white;
            border-radius: 12px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .card-header-mobile {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px;
            cursor: pointer;
            background: #f8f9fa;
            border-bottom: 1px solid #e0e0e0;
        }

        .product-mobile {
            flex: 1;
        }

        .product-mobile .title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 4px;
        }

        .product-mobile .route {
            font-size: 0.85em;
            color: #7f8c8d;
        }

        .amount-mobile {
            font-weight: 700;
            color: #42ba25;
            font-size: 1.1em;
        }

        .expand-icon {
            color: #42ba25;
            font-size: 1.2em;
            transition: transform 0.3s;
            margin-left: 10px;
        }

        .expand-icon.rotated {
            transform: rotate(180deg);
        }

        .card-details-mobile {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }

        .card-details-mobile.expanded {
            max-height: 400px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 16px;
            border-bottom: 1px solid #f0f0f0;
        }

        .detail-label {
            color: #7f8c8d;
            font-size: 0.9em;
        }

        .detail-value {
            font-weight: 500;
            color: #2c3e50;
        }

        .actions-mobile {
            padding: 12px 16px;
            display: flex;
            gap: 10px;
        }

        .actions-mobile .btn-action {
            flex: 1;
            justify-content: center;
        }

        .no-data {
            text-align: center;
            padding: 60px 20px;
            background: linear-gradient(135deg, #f8f9fa, #ffffff);
            border-radius: 15px;
            border: 2px dashed #bdc3c7;
        }

        .no-data-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.6;
        }

        .no-data h3 {
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .no-data p {
            color: #7f8c8d;
        }

        @media (max-width: 992px) {
            .table-wrapper {
                display: none;
            }

            .mobile-cards {
                display: block;
            }

            .container {
                padding: 15px;
                margin: 10px;
            }

            .header-section h1 {
                font-size: 1.8em;
            }

            .tabs {
                gap: 0;
            }

            .tab {
                padding: 12px 16px;
                font-size: 0.95em;
                flex: 1;
                justify-content: center;
            }

            .summary-cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/shop-header.php'; ?>

    <div class="container">
        <div class="header-section">
            <h1><i class="fas fa-wallet"></i> Pagos y Cobros</h1>
            <p>Gestiona todos tus movimientos financieros en SendVialo Shop</p>
        </div>

        <div class="tabs">
            <a href="?tipo=pagos" class="tab <?= $filtro_tipo === 'pagos' ? 'active' : '' ?>">
                <i class="fas fa-arrow-up"></i> Pagos Realizados
            </a>
            <a href="?tipo=cobros" class="tab <?= $filtro_tipo === 'cobros' ? 'active' : '' ?>">
                <i class="fas fa-arrow-down"></i> Cobros Recibidos
            </a>
        </div>

        <!-- Resumen solo para cobros -->
        <?php if ($filtro_tipo === 'cobros'): ?>
        <div class="summary-collapsible">
            <div class="collapsible-header" onclick="toggleSummary()">
                <div class="collapsible-title">
                    <i class="fas fa-chart-pie"></i>
                    <span>Resumen de Cobros</span>
                </div>
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div class="collapsible-total">Total: €<?= number_format($total_en_custodia + $total_liberado, 2) ?></div>
                    <i class="fas fa-chevron-down collapsible-icon" id="summaryIcon"></i>
                </div>
            </div>
            
            <div class="collapsible-body" id="summaryBody">
                <div class="summary-cards">
                    <div class="summary-card custodia">
                        <div class="summary-icon">⏳</div>
                        <div class="summary-title">En Custodia</div>
                        <div class="summary-amount">€<?= number_format($total_en_custodia, 2) ?></div>
                        <div class="summary-description">Pendiente de entrega QR</div>
                    </div>
                    <div class="summary-card liberado">
                        <div class="summary-icon">✅</div>
                        <div class="summary-title">Completado</div>
                        <div class="summary-amount">€<?= number_format($total_liberado, 2) ?></div>
                        <div class="summary-description">Pagos recibidos</div>
                    </div>
                    <div class="summary-card cancelado">
                        <div class="summary-icon">❌</div>
                        <div class="summary-title">Cancelado</div>
                        <div class="summary-amount">€<?= number_format($total_cancelado, 2) ?></div>
                        <div class="summary-description">Entregas canceladas</div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Botón filtros -->
        <div class="filters-section">
            <button type="button" id="btnAbrirFiltros" class="btn-abrir-filtros">
                <i class="fas fa-filter"></i> Filtros
            </button>
        </div>

        <!-- Modal Filtros -->
        <div id="modalFiltros" class="modal-filtros">
            <div class="modal-contenido">
                <div class="modal-header">
                    <h3><i class="fas fa-filter"></i> Filtros</h3>
                    <button type="button" id="btnCerrarModal" class="btn-cerrar-modal">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="GET" action="">
                        <input type="hidden" name="tipo" value="<?= htmlspecialchars($filtro_tipo) ?>">
                        
                        <div class="filtro-grupo">
                            <label for="estado">Estado:</label>
                            <select name="estado" id="estado">
                                <option value="todos" <?= $filtro_estado === 'todos' ? 'selected' : '' ?>>Todos</option>
                                <option value="en_custodia" <?= $filtro_estado === 'en_custodia' ? 'selected' : '' ?>>En Custodia</option>
                                <option value="completado" <?= $filtro_estado === 'completado' ? 'selected' : '' ?>>Completado</option>
                                <option value="cancelado" <?= $filtro_estado === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                            </select>
                        </div>
                        
                        <div class="filtro-grupo">
                            <label for="fecha_inicio">Desde:</label>
                            <input type="date" name="fecha_inicio" id="fecha_inicio" value="<?= htmlspecialchars($filtro_fecha_inicio) ?>">
                        </div>
                        
                        <div class="filtro-grupo">
                            <label for="fecha_fin">Hasta:</label>
                            <input type="date" name="fecha_fin" id="fecha_fin" value="<?= htmlspecialchars($filtro_fecha_fin) ?>">
                        </div>
                        
                        <div class="filtros-acciones">
                            <button type="submit" class="btn-aplicar">
                                <i class="fas fa-search"></i> Aplicar
                            </button>
                            <a href="?tipo=<?= $filtro_tipo ?>" class="btn-limpiar">
                                <i class="fas fa-times"></i> Limpiar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- CONTENIDO: PAGOS -->
        <?php if ($filtro_tipo === 'pagos'): ?>
            <?php if (!empty($pagos)): ?>
                <!-- Desktop Table -->
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Fecha</th>
                                <th>Monto Total</th>
                                <th>Viajero</th>
                                <th>Estado</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pagos as $pago): ?>
                            <tr>
                                <td class="product-cell">
                                    <div class="product-title"><?= htmlspecialchars($pago['producto'] ?? 'Producto #' . $pago['request_id']) ?></div>
                                    <div class="product-route">
                                        <?= htmlspecialchars($pago['origen'] ?? 'N/A') ?> → <?= htmlspecialchars($pago['destino'] ?? 'N/A') ?>
                                    </div>
                                </td>
                                <td><?= date('d/m/Y H:i', strtotime($pago['fecha'])) ?></td>
                                <td class="amount">€<?= number_format($pago['monto_total'], 2) ?></td>
                                <td><?= htmlspecialchars($pago['viajero_nombre'] ?? 'N/A') ?></td>
                                <td><?= getEstadoBadge($pago['estado']) ?></td>
                                <td>
                                    <button class="btn-action" onclick="verDetalle(<?= $pago['id'] ?>, 'pago')">
                                        <i class="fas fa-eye"></i> Ver
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards -->
                <div class="mobile-cards">
                    <?php foreach ($pagos as $pago): ?>
                    <div class="payment-card-mobile">
                        <div class="card-header-mobile" onclick="toggleCard(this)">
                            <div class="product-mobile">
                                <div class="title"><?= htmlspecialchars($pago['producto'] ?? 'Producto #' . $pago['request_id']) ?></div>
                                <div class="route"><?= htmlspecialchars($pago['origen'] ?? '') ?> → <?= htmlspecialchars($pago['destino'] ?? '') ?></div>
                            </div>
                            <div class="amount-mobile">€<?= number_format($pago['monto_total'], 2) ?></div>
                            <i class="fas fa-chevron-down expand-icon"></i>
                        </div>
                        <div class="card-details-mobile">
                            <div class="detail-row">
                                <span class="detail-label">Fecha</span>
                                <span class="detail-value"><?= date('d/m/Y H:i', strtotime($pago['fecha'])) ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Viajero</span>
                                <span class="detail-value"><?= htmlspecialchars($pago['viajero_nombre'] ?? 'N/A') ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Estado</span>
                                <span><?= getEstadoBadge($pago['estado']) ?></span>
                            </div>
                            <div class="actions-mobile">
                                <button class="btn-action" onclick="verDetalle(<?= $pago['id'] ?>, 'pago')">
                                    <i class="fas fa-eye"></i> Ver Detalle
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <div class="no-data-icon">💳</div>
                    <h3>No hay pagos realizados</h3>
                    <p>Aún no has realizado ningún pago en SendVialo Shop</p>
                </div>
            <?php endif; ?>

        <!-- CONTENIDO: COBROS -->
        <?php else: ?>
            <?php if (!empty($cobros)): ?>
                <!-- Desktop Table -->
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Fecha</th>
                                <th>Mi Ganancia</th>
                                <th>Cliente</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cobros as $cobro): ?>
                            <tr>
                                <td class="product-cell">
                                    <div class="product-title"><?= htmlspecialchars($cobro['producto'] ?? 'Producto #' . $cobro['request_id']) ?></div>
                                    <div class="product-route">
                                        <?= htmlspecialchars($cobro['origen'] ?? 'N/A') ?> → <?= htmlspecialchars($cobro['destino'] ?? 'N/A') ?>
                                    </div>
                                </td>
                                <td><?= date('d/m/Y H:i', strtotime($cobro['fecha'])) ?></td>
                                <td class="amount">€<?= number_format($cobro['mi_ganancia'], 2) ?></td>
                                <td><?= htmlspecialchars($cobro['cliente_nombre'] ?? 'N/A') ?></td>
                                <td><?= getEstadoBadge($cobro['estado']) ?></td>
                                <td>
                                    <button class="btn-action" onclick="verDetalle(<?= $cobro['id'] ?>, 'cobro')">
                                        <i class="fas fa-eye"></i> Ver
                                    </button>
                                    <?php if (strtolower($cobro['estado']) === 'en_custodia'): ?>
                                    <button class="btn-action" onclick="verQR(<?= $cobro['id'] ?>)" style="background: linear-gradient(135deg, #6772e5 0%, #5469d4 100%);">
                                        <i class="fas fa-qrcode"></i> QR
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards -->
                <div class="mobile-cards">
                    <?php foreach ($cobros as $cobro): ?>
                    <div class="payment-card-mobile">
                        <div class="card-header-mobile" onclick="toggleCard(this)">
                            <div class="product-mobile">
                                <div class="title"><?= htmlspecialchars($cobro['producto'] ?? 'Producto #' . $cobro['request_id']) ?></div>
                                <div class="route"><?= htmlspecialchars($cobro['origen'] ?? '') ?> → <?= htmlspecialchars($cobro['destino'] ?? '') ?></div>
                            </div>
                            <div class="amount-mobile">€<?= number_format($cobro['mi_ganancia'], 2) ?></div>
                            <i class="fas fa-chevron-down expand-icon"></i>
                        </div>
                        <div class="card-details-mobile">
                            <div class="detail-row">
                                <span class="detail-label">Fecha</span>
                                <span class="detail-value"><?= date('d/m/Y H:i', strtotime($cobro['fecha'])) ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Cliente</span>
                                <span class="detail-value"><?= htmlspecialchars($cobro['cliente_nombre'] ?? 'N/A') ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Estado</span>
                                <span><?= getEstadoBadge($cobro['estado']) ?></span>
                            </div>
                            <div class="actions-mobile">
                                <button class="btn-action" onclick="verDetalle(<?= $cobro['id'] ?>, 'cobro')">
                                    <i class="fas fa-eye"></i> Ver
                                </button>
                                <?php if (strtolower($cobro['estado']) === 'en_custodia'): ?>
                                <button class="btn-action" onclick="verQR(<?= $cobro['id'] ?>)" style="background: linear-gradient(135deg, #6772e5 0%, #5469d4 100%);">
                                    <i class="fas fa-qrcode"></i> QR
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <div class="no-data-icon">💰</div>
                    <h3>No hay cobros recibidos</h3>
                    <p>Aún no has recibido ningún cobro como viajero en SendVialo Shop</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php include __DIR__ . '/shop-footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function toggleSummary() {
            const body = document.getElementById('summaryBody');
            const icon = document.getElementById('summaryIcon');
            if (body && icon) {
                body.classList.toggle('expanded');
                icon.classList.toggle('rotated');
            }
        }

        function toggleCard(header) {
            const details = header.nextElementSibling;
            const icon = header.querySelector('.expand-icon');
            details.classList.toggle('expanded');
            icon.classList.toggle('rotated');
        }

        function verDetalle(id, tipo) {
            Swal.fire({
                title: tipo === 'pago' ? 'Detalle del Pago' : 'Detalle del Cobro',
                html: `
                    <div style="text-align: left; padding: 10px;">
                        <p><strong>ID Transacción:</strong> #${id}</p>
                        <p><strong>Tipo:</strong> ${tipo === 'pago' ? 'Pago realizado' : 'Cobro recibido'}</p>
                        <hr style="margin: 15px 0; border: none; border-top: 1px solid #eee;">
                        <p style="font-size: 0.9em; color: #666;">
                            <i class="fas fa-info-circle"></i> 
                            ${tipo === 'pago' 
                                ? 'Tu pago está protegido hasta confirmar la recepción del producto.' 
                                : 'Recibirás el pago cuando el solicitante confirme la entrega escaneando el QR.'}
                        </p>
                    </div>
                `,
                icon: 'info',
                confirmButtonText: 'Cerrar',
                confirmButtonColor: '#42ba25'
            });
        }

        function verQR(paymentId) {
            // Buscar el QR asociado a este pago
            fetch(`shop-get-qr.php?payment_id=${paymentId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.qr_path) {
                        Swal.fire({
                            title: 'Tu Código QR de Entrega',
                            html: `
                                <div style="text-align: center;">
                                    <img src="${data.qr_path}" alt="Código QR" style="max-width: 250px; border: 3px solid #42ba25; border-radius: 10px; padding: 10px; background: white;">
                                    <p style="margin-top: 15px; font-size: 0.9em; color: #666;">
                                        <i class="fas fa-info-circle"></i> 
                                        Muestra este QR al solicitante para confirmar la entrega
                                    </p>
                                </div>
                            `,
                            confirmButtonText: 'Cerrar',
                            confirmButtonColor: '#42ba25'
                        });
                    } else {
                        Swal.fire('Error', data.message || 'No se encontró el código QR', 'error');
                    }
                })
                .catch(error => {
                    Swal.fire('Error', 'Error al cargar el código QR', 'error');
                });
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Modal filtros
            const btnAbrir = document.getElementById('btnAbrirFiltros');
            const modal = document.getElementById('modalFiltros');
            const btnCerrar = document.getElementById('btnCerrarModal');
            
            if (btnAbrir && modal) {
                btnAbrir.addEventListener('click', () => {
                    modal.classList.add('active');
                    document.body.style.overflow = 'hidden';
                });

                btnCerrar.addEventListener('click', () => {
                    modal.classList.remove('active');
                    document.body.style.overflow = '';
                });

                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        modal.classList.remove('active');
                        document.body.style.overflow = '';
                    }
                });

                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && modal.classList.contains('active')) {
                        modal.classList.remove('active');
                        document.body.style.overflow = '';
                    }
                });
            }

            // Padding para barra inferior
            if (document.querySelector('.mobile-bottom-nav')) {
                document.body.classList.add('has-bottom-nav');
            }
        });

        // Auto-refresh si hay pagos en custodia
        <?php if ($total_en_custodia > 0): ?>
        setInterval(() => location.reload(), 120000);
        <?php endif; ?>
    </script>
</body>
</html>