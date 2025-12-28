<?php
/**
 * shop-verificacion-qr.php
 * Versión Final: Compacta, Profesional y con Iconografía en Color
 */

session_start();
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['usuario_id'];

// SQL idéntico para mantener funcionalidad
$sql = "SELECT d.*,
               p.proposed_price, p.proposed_currency, p.estimated_delivery,
               r.title, r.description, r.destination_city, r.reference_images,
               COALESCE(req.full_name, req.username) as requester_name,
               COALESCE(trav.full_name, trav.username) as traveler_name,
               pay.monto_total, pay.amount_to_transporter,
               pay.estado as payment_status,
               r.id as request_id,
               CASE
                   WHEN d.requester_id = :user_id THEN 'requester'
                   WHEN d.traveler_id = :user_id THEN 'traveler'
                   ELSE 'unknown'
               END as user_role
        FROM shop_deliveries d
        LEFT JOIN shop_request_proposals p ON d.proposal_id = p.id
        LEFT JOIN shop_requests r ON p.request_id = r.id
        LEFT JOIN accounts req ON d.requester_id = req.id
        LEFT JOIN accounts trav ON d.traveler_id = trav.id
        LEFT JOIN payments_in_custody pay ON d.payment_id = pay.id
        WHERE d.requester_id = :user_id OR d.traveler_id = :user_id
        ORDER BY d.created_at DESC";

$stmt = $conexion->prepare($sql);
$stmt->execute([':user_id' => $user_id]);
$deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getFirstRequestImage($reference_images) {
    if (empty($reference_images)) return 'https://via.placeholder.com/350x200?text=Sin+Imagen';
    $images = json_decode($reference_images, true);
    if (is_array($images) && !empty($images)) {
        $first = $images[0];
        return is_string($first) ? $first : ($first['data'] ?? 'https://via.placeholder.com/350x200?text=Sin+Imagen');
    }
    return 'https://via.placeholder.com/350x200?text=Sin+Imagen';
}

function getProgressInfo($state) {
    $progressMap = [
        'pending' => ['percentage' => 12, 'label' => 'Pendiente', 'color' => '#94a3b8'],
        'in_transit' => ['percentage' => 42, 'label' => 'En Tránsito', 'color' => '#3b82f6'],
        'at_destination' => ['percentage' => 72, 'label' => 'En Destino', 'color' => '#f59e0b'],
        'delivered' => ['percentage' => 100, 'label' => 'Entregado', 'color' => '#10b981']
    ];
    return $progressMap[$state] ?? $progressMap['pending'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Seguimiento de Entregas | SendVialo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #41ba0d;
            --primary-dark: #2d8518;
            --slate-900: #0f172a;
            --slate-600: #475569;
            --slate-200: #e2e8f0;
            --bg-body: #f8fafc;
            /* Tracking Colors */
            --color-pending: #94a3b8;
            --color-transit: #3b82f6;
            --color-destin: #f59e0b;
            --color-success: #10b981;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-body);
            color: var(--slate-900);
            -webkit-font-smoothing: antialiased;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 90px 20px 60px; /* Ajuste para que el título baje un poco */
        }

        .page-header { margin-bottom: 40px; }
        .page-header h1 { font-size: 32px; font-weight: 900; letter-spacing: -1px; margin-bottom: 5px; }
        .page-header p { color: var(--slate-600); font-size: 14px; font-weight: 500; }

        /* --- GRID COMPACTO --- */
        .deliveries-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); /* Más compacto en PC */
            gap: 20px;
        }

        /* --- TARJETA COMPACTA --- */
        .delivery-card {
            background: white;
            border-radius: 20px;
            border: 1px solid var(--slate-200);
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        .delivery-card:hover { transform: translateY(-4px); box-shadow: 0 15px 30px -10px rgba(0,0,0,0.1); }

        .image-wrapper { height: 160px; position: relative; overflow: hidden; background: #f1f5f9; }
        .image-wrapper img { width: 100%; height: 100%; object-fit: cover; }

        .role-pill {
            position: absolute; top: 12px; left: 12px; padding: 4px 12px;
            border-radius: 10px; font-size: 10px; font-weight: 800; text-transform: uppercase;
            background: white; color: var(--slate-900); box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .card-content { padding: 20px; flex-grow: 1; }
        .delivery-title { font-size: 17px; font-weight: 800; margin-bottom: 12px; color: var(--slate-900); text-decoration: none; display: block; line-height: 1.3; }

        .info-tiles { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 20px; }
        .info-tile { background: #f1f5f9; border-radius: 12px; padding: 8px 10px; display: flex; align-items: center; gap: 8px; }
        .info-tile i { font-size: 12px; color: var(--slate-600); opacity: 0.7; }
        .tile-data { display: flex; flex-direction: column; }
        .tile-label { font-size: 8px; font-weight: 700; color: var(--slate-600); text-transform: uppercase; }
        .tile-value { font-size: 11px; font-weight: 800; color: var(--slate-900); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        /* --- PROGRESS BAR COMPACTA --- */
        .progress-section { margin-bottom: 10px; }
        .progress-bar-bg { background: var(--slate-200); height: 5px; border-radius: 10px; position: relative; margin-bottom: 15px; }
        .progress-bar-fill { height: 100%; border-radius: 10px; transition: width 0.8s ease; }
        
        .progress-steps { display: flex; justify-content: space-between; }
        .step { flex: 1; display: flex; flex-direction: column; align-items: center; }
        .step-circle { 
            width: 26px; height: 26px; background: white; border: 2px solid var(--slate-200); 
            border-radius: 50%; display: flex; align-items: center; justify-content: center; 
            font-size: 11px; margin-bottom: 5px; transition: 0.3s; color: var(--slate-200);
        }

        /* Colores dinámicos para iconos */
        .step-pending.active .step-circle, .step-pending.completed .step-circle { border-color: var(--color-pending); color: var(--color-pending); }
        .step-transit.active .step-circle, .step-transit.completed .step-circle { border-color: var(--color-transit); color: var(--color-transit); }
        .step-destin.active .step-circle, .step-destin.completed .step-circle { border-color: var(--color-destin); color: var(--color-destin); }
        .step-success.active .step-circle, .step-success.completed .step-circle { border-color: var(--color-success); color: var(--color-success); }
        
        /* Icono de check para completados */
        .step.completed .step-circle { background: white; }
        .step.active .step-circle { transform: scale(1.15); box-shadow: 0 0 10px rgba(0,0,0,0.05); }

        .step-label { font-size: 9px; font-weight: 700; color: var(--slate-600); }
        .step.active .step-label { font-weight: 800; }

        /* --- BUTTONS --- */
        .card-actions { padding: 0 20px 20px; }
        .btn-action { width: 100%; padding: 12px; border-radius: 14px; border: none; font-weight: 800; font-size: 13px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: 0.2s; text-decoration: none; }
        
        .btn-scan { background: var(--slate-900); color: white; }
        .btn-update { background: var(--primary); color: white; }
        .btn-update:hover { background: var(--primary-dark); }
        
        .delivered-msg { background: #f0fdf4; color: var(--color-success); padding: 10px; border-radius: 12px; text-align: center; font-weight: 800; font-size: 12px; border: 1px solid #dcfce7; }

        @media (max-width: 480px) {
            .container { padding-top: 80px; }
            .deliveries-grid { grid-template-columns: 1fr; gap: 15px; }
            .delivery-card { border-radius: 0; border-left: none; border-right: none; }
        }
    </style>
</head>
<body>
    <?php if (file_exists('header1.php')) include 'header1.php'; ?>

    <div class="container">
        <header class="page-header">
            <h1>Mis Entregas</h1>
            <p>Control de envíos activos y validación segura por QR.</p>
        </header>

        <?php if (empty($deliveries)): ?>
            <div style="text-align:center; padding:100px 20px; color:var(--slate-600); background:white; border-radius:24px; border:2px dashed var(--slate-200);">
                <i class="fas fa-box-open" style="font-size:40px; margin-bottom:15px; opacity:0.3;"></i>
                <h3 style="font-weight:800;">No tienes entregas activas</h3>
            </div>
        <?php else: ?>
            <div class="deliveries-grid">
                <?php foreach ($deliveries as $delivery):
                    $isRequester = $delivery['user_role'] === 'requester';
                    $isTraveler = $delivery['user_role'] === 'traveler';
                    $state = $delivery['delivery_state'];
                    $prog = getProgressInfo($state);
                    $productImage = getFirstRequestImage($delivery['reference_images']);
                    
                    $canScanQR = $isTraveler && $state === 'at_destination';
                    $canUpdateState = $isTraveler && $state !== 'delivered' && $state !== 'at_destination';
                ?>
                    <div class="delivery-card">
                        <div class="image-wrapper">
                            <img src="<?php echo htmlspecialchars($productImage); ?>" alt="Item">
                            <div class="role-pill">
                                <?php echo $isRequester ? '<i class="fas fa-shopping-bag"></i> Solicitante' : '<i class="fas fa-plane"></i> Viajero'; ?>
                            </div>
                        </div>

                        <div class="card-content">
                            <a href="shop-request-detail.php?id=<?php echo $delivery['request_id']; ?>" class="delivery-title">
                                <?php echo htmlspecialchars($delivery['title']); ?>
                            </a>

                            <div class="info-tiles">
                                <div class="info-tile">
                                    <i class="fas fa-map-pin"></i>
                                    <div class="tile-data">
                                        <span class="tile-label">Ciudad</span>
                                        <span class="tile-value"><?php echo htmlspecialchars($delivery['destination_city']); ?></span>
                                    </div>
                                </div>
                                <div class="info-tile">
                                    <i class="fas fa-wallet"></i>
                                    <div class="tile-data">
                                        <span class="tile-label">Monto</span>
                                        <span class="tile-value"><?php echo number_format($delivery['monto_total'], 0); ?> <?php echo strtoupper($delivery['proposed_currency']); ?></span>
                                    </div>
                                </div>
                                <div class="info-tile" style="grid-column: span 2;">
                                    <i class="fas fa-id-card"></i>
                                    <div class="tile-data">
                                        <span class="tile-label"><?php echo $isRequester ? 'Trae el viajero' : 'Solicitado por'; ?></span>
                                        <span class="tile-value"><?php echo htmlspecialchars($isRequester ? $delivery['traveler_name'] : $delivery['requester_name']); ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="progress-section">
                                <div class="progress-bar-bg">
                                    <div class="progress-bar-fill" style="width: <?php echo $prog['percentage']; ?>%; background-color: <?php echo $prog['color']; ?>;"></div>
                                </div>
                                <div class="progress-steps">
                                    <?php 
                                    $states_list = [
                                        ['key' => 'pending', 'label' => 'Pack', 'icon' => 'fa-box', 'class' => 'step-pending'],
                                        ['key' => 'in_transit', 'label' => 'Viaje', 'icon' => 'fa-plane', 'class' => 'step-transit'],
                                        ['key' => 'at_destination', 'label' => 'Destino', 'icon' => 'fa-location-dot', 'class' => 'step-destin'],
                                        ['key' => 'delivered', 'label' => 'Listo', 'icon' => 'fa-check-double', 'class' => 'step-success']
                                    ];
                                    
                                    $found_current = false;
                                    foreach ($states_list as $s):
                                        $isActive = ($state === $s['key']);
                                        $isCompleted = false;
                                        // Lógica simple para marcar completados anteriores
                                        if (!$found_current && $state !== $s['key']) $isCompleted = true;
                                        if ($isActive) $found_current = true;
                                    ?>
                                        <div class="step <?php echo $s['class']; ?> <?php echo $isActive ? 'active' : ''; ?> <?php echo $isCompleted ? 'completed' : ''; ?>">
                                            <div class="step-circle">
                                                <i class="fas <?php echo $s['icon']; ?>"></i>
                                            </div>
                                            <span class="step-label"><?php echo $s['label']; ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="card-actions">
                            <?php if ($canScanQR): ?>
                                <button class="btn-action btn-scan" onclick="openQRScanner(<?php echo $delivery['id']; ?>, '<?php echo $delivery['qr_code_unique_id']; ?>')">
                                    <i class="fas fa-qrcode"></i> Escanear para Entregar
                                </button>
                            <?php elseif ($canUpdateState): ?>
                                <?php if ($state === 'pending'): ?>
                                    <button class="btn-action btn-update" onclick="updateDeliveryState(<?php echo $delivery['id']; ?>, 'in_transit')">
                                        <i class="fas fa-plane"></i> Iniciar Tránsito
                                    </button>
                                <?php elseif ($state === 'in_transit'): ?>
                                    <button class="btn-action btn-update" onclick="updateDeliveryState(<?php echo $delivery['id']; ?>, 'at_destination')">
                                        <i class="fas fa-location-arrow"></i> Llegué a Destino
                                    </button>
                                <?php endif; ?>
                            <?php elseif ($state === 'delivered'): ?>
                                <div class="delivered-msg">
                                    <i class="fas fa-check-circle"></i> Entrega Finalizada
                                </div>
                            <?php else: ?>
                                <div style="text-align: center; font-size: 11px; color: var(--slate-600); font-weight: 700; padding: 10px;">
                                    <i class="fas fa-hourglass-half"></i> Proceso en curso...
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="shop-verificacion-qr.js"></script>
</body>
</html>