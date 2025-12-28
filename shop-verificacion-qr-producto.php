<?php
/**
 * shop-verificacion-qr-producto.php
 * Página para ver estado de entregas de productos y escanear códigos QR
 */

session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['usuario_id'];

try {
    // Obtener todas las entregas del usuario (como comprador o vendedor)
    $sql = "SELECT d.*,
                   oi.unit_price as offered_price, oi.currency as offered_currency, oi.quantity,
                   p.name as product_name, p.description, p.category,
                   COALESCE(buyer.full_name, buyer.username) as buyer_name,
                   COALESCE(seller.full_name, seller.username) as seller_name,
                   pay.monto_total, pay.estado as payment_status,
                   (CASE WHEN pay.estado = 'PENDING' THEN 1 ELSE 0 END) as custody_mode,
                   CASE
                       WHEN d.buyer_id = :user_id THEN 'buyer'
                       WHEN d.seller_id = :user_id THEN 'seller'
                       ELSE 'unknown'
                   END as user_role
            FROM shop_product_deliveries d
            LEFT JOIN shop_order_items oi ON d.order_item_id = oi.id
            LEFT JOIN shop_products p ON d.product_id = p.id
            LEFT JOIN accounts buyer ON d.buyer_id = buyer.id
            LEFT JOIN accounts seller ON d.seller_id = seller.id
            LEFT JOIN payments_in_custody pay ON d.payment_id = pay.id
            WHERE d.buyer_id = :user_id OR d.seller_id = :user_id
            ORDER BY d.created_at DESC";

    $stmt = $conexion->prepare($sql);
    $stmt->execute([':user_id' => $user_id]);
    $deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error en shop-verificacion-qr-producto: " . $e->getMessage());
    die('<div style="padding: 20px; background: #f8d7da; color: #721c24; border-radius: 5px; margin: 20px;">Error: ' . htmlspecialchars($e->getMessage()) . '</div>');
}

// Función para calcular progreso
function getProgressInfo($state) {
    $progressMap = [
        'pending' => [
            'percentage' => 25,
            'label' => 'Pendiente',
            'next' => 'in_transit',
            'needsQR' => false,
            'icon' => '📦',
            'color' => '#6c757d'
        ],
        'in_transit' => [
            'percentage' => 50,
            'label' => 'En Tránsito',
            'next' => 'at_destination',
            'needsQR' => false,
            'icon' => '✈️',
            'color' => '#007bff'
        ],
        'at_destination' => [
            'percentage' => 75,
            'label' => 'En Destino',
            'next' => 'delivered',
            'needsQR' => true,
            'icon' => '📍',
            'color' => '#ffc107'
        ],
        'delivered' => [
            'percentage' => 100,
            'label' => 'Entregado',
            'next' => null,
            'needsQR' => false,
            'icon' => '✅',
            'color' => '#28a745'
        ]
    ];

    return $progressMap[$state] ?? $progressMap['pending'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de Entregas - SendVialo Shop</title>
    <link rel="stylesheet" href="../css/estilos.css">
    <link rel="icon" href="../Imagenes/globo5.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #42ba25 0%, #37a01f 100%);
            min-height: 100vh;
            padding: 20px 20px 40px;
        }

        /* Ocultar el header de navegación completo en esta página */
        header {
            display: none !important;
        }

        .header-spacer {
            display: none !important;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .page-header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin-bottom: 30px;
            text-align: center;
        }

        .page-header h1 {
            color: #333;
            font-size: 32px;
            margin-bottom: 10px;
        }

        .page-header p {
            color: #666;
            font-size: 16px;
        }

        .deliveries-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }

        .delivery-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .delivery-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.3);
        }

        .role-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .role-badge.buyer {
            background: #e3f2fd;
            color: #1976d2;
        }

        .role-badge.seller {
            background: #dcfce7;
            color: #42ba25;
        }

        .delivery-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }

        .delivery-info {
            margin: 15px 0;
            font-size: 14px;
            color: #666;
        }

        .delivery-info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .delivery-info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: #333;
        }

        .progress-section {
            margin: 20px 0;
        }

        .progress-bar-container {
            background: #e0e0e0;
            height: 8px;
            border-radius: 10px;
            overflow: hidden;
            margin: 15px 0;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #42ba25 0%, #37a01f 100%);
            transition: width 0.5s ease;
        }

        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
        }

        .progress-step {
            text-align: center;
            flex: 1;
            font-size: 12px;
        }

        .progress-step-icon {
            font-size: 24px;
            margin-bottom: 5px;
        }

        .progress-step.active {
            font-weight: 600;
            color: #42ba25;
        }

        .progress-step.completed {
            color: #28a745;
        }

        .btn-scan-qr {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #42ba25 0%, #37a01f 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 15px;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn-scan-qr:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(66, 186, 37, 0.4);
        }

        .btn-scan-qr:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .btn-update-state {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin: 10px 0;
        }

        .no-deliveries {
            background: white;
            padding: 60px 30px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .no-deliveries i {
            font-size: 80px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .no-deliveries h2 {
            color: #666;
            margin-bottom: 10px;
        }

        .no-deliveries p {
            color: #999;
        }

        .btn-back {
            position: fixed;
            top: 20px;
            left: 20px;
            background: white;
            color: #42ba25;
            padding: 12px 20px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
            z-index: 1000;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-back:hover {
            background: #42ba25;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(66, 186, 37, 0.4);
        }

        @media (max-width: 768px) {
            .deliveries-grid {
                grid-template-columns: 1fr;
            }

            body {
                padding: 20px 15px 30px;
            }
        }
    </style>
</head>
<body>
    <?php include 'shop-header.php'; ?>

    <!-- Botón para volver -->
    <a href="index.php" class="btn-back">
        <i class="fas fa-arrow-left"></i> Volver a SendVialo Shop
    </a>

    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-qrcode"></i> Verificación de Entregas de Productos</h1>
            <p>Gestiona y verifica tus entregas con código QR</p>
        </div>

        <?php if (empty($deliveries)): ?>
            <div class="no-deliveries">
                <i class="fas fa-box-open"></i>
                <h2>No tienes entregas activas</h2>
                <p>Cuando realices una compra o vendas un producto, las entregas aparecerán aquí</p>
            </div>
        <?php else: ?>
            <div class="deliveries-grid">
                <?php foreach ($deliveries as $delivery):
                    $progress = getProgressInfo($delivery['delivery_state']);
                    $isBuyer = $delivery['user_role'] === 'buyer';
                    $isSeller = $delivery['user_role'] === 'seller';
                    $canScanQR = $isBuyer && $progress['needsQR'];
                    $canUpdateState = $isSeller && $delivery['delivery_state'] !== 'delivered';
                    $totalAmount = (float)$delivery['offered_price'] * (int)$delivery['quantity'];
                ?>
                    <div class="delivery-card" data-delivery-id="<?php echo $delivery['id']; ?>">
                        <div class="role-badge <?php echo $delivery['user_role']; ?>">
                            <?php echo $isBuyer ? '🛒 Comprador' : '📦 Vendedor'; ?>
                        </div>

                        <div class="delivery-title">
                            <?php echo htmlspecialchars($delivery['product_name']); ?>
                        </div>

                        <div class="delivery-info">
                            <?php if ($delivery['category']): ?>
                            <div class="delivery-info-row">
                                <span class="info-label">Categoría:</span>
                                <span><?php echo htmlspecialchars($delivery['category']); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="delivery-info-row">
                                <span class="info-label"><?php echo $isBuyer ? 'Vendedor:' : 'Comprador:'; ?></span>
                                <span><?php echo htmlspecialchars($isBuyer ? $delivery['seller_name'] : $delivery['buyer_name']); ?></span>
                            </div>
                            <div class="delivery-info-row">
                                <span class="info-label">Cantidad:</span>
                                <span><?php echo $delivery['quantity']; ?> unidad(es)</span>
                            </div>
                            <div class="delivery-info-row">
                                <span class="info-label">Monto Total:</span>
                                <span><?php echo number_format($totalAmount, 2); ?> <?php echo strtoupper($delivery['offered_currency']); ?></span>
                            </div>
                        </div>

                        <div class="progress-section">
                            <div style="text-align: center; margin-bottom: 10px;">
                                <span class="status-badge" style="background: <?php echo $progress['color']; ?>20; color: <?php echo $progress['color']; ?>;">
                                    <?php echo $progress['icon']; ?> <?php echo $progress['label']; ?>
                                </span>
                            </div>

                            <div class="progress-bar-container">
                                <div class="progress-bar" style="width: <?php echo $progress['percentage']; ?>%"></div>
                            </div>

                            <div class="progress-steps">
                                <div class="progress-step <?php echo $delivery['delivery_state'] === 'pending' ? 'active' : ($progress['percentage'] > 25 ? 'completed' : ''); ?>">
                                    <div class="progress-step-icon">📦</div>
                                    <div>Pendiente</div>
                                </div>
                                <div class="progress-step <?php echo $delivery['delivery_state'] === 'in_transit' ? 'active' : ($progress['percentage'] > 50 ? 'completed' : ''); ?>">
                                    <div class="progress-step-icon">✈️</div>
                                    <div>En Tránsito</div>
                                </div>
                                <div class="progress-step <?php echo $delivery['delivery_state'] === 'at_destination' ? 'active' : ($progress['percentage'] > 75 ? 'completed' : ''); ?>">
                                    <div class="progress-step-icon">📍</div>
                                    <div>En Destino</div>
                                </div>
                                <div class="progress-step <?php echo $delivery['delivery_state'] === 'delivered' ? 'active completed' : ''; ?>">
                                    <div class="progress-step-icon">✅</div>
                                    <div>Entregado</div>
                                </div>
                            </div>
                        </div>

                        <?php if ($canScanQR): ?>
                            <button class="btn-scan-qr" onclick="openQRScanner(<?php echo $delivery['id']; ?>, '<?php echo $delivery['qr_code_unique_id']; ?>')">
                                <i class="fas fa-qrcode"></i> Escanear QR para Confirmar Entrega
                            </button>
                        <?php elseif ($canUpdateState): ?>
                            <?php if ($delivery['delivery_state'] === 'pending'): ?>
                                <button class="btn-update-state" onclick="updateDeliveryState(<?php echo $delivery['id']; ?>, 'in_transit')">
                                    <i class="fas fa-plane"></i> Marcar como "En Tránsito"
                                </button>
                            <?php elseif ($delivery['delivery_state'] === 'in_transit'): ?>
                                <button class="btn-update-state" onclick="updateDeliveryState(<?php echo $delivery['id']; ?>, 'at_destination')">
                                    <i class="fas fa-map-marker-alt"></i> Marcar como "En Destino"
                                </button>
                            <?php endif; ?>
                        <?php elseif ($delivery['delivery_state'] === 'delivered'): ?>
                            <div style="text-align: center; padding: 15px; background: #dcfce7; border-radius: 8px; margin-top: 15px;">
                                <i class="fas fa-check-circle" style="color: #28a745; font-size: 24px;"></i>
                                <p style="margin-top: 10px; color: #28a745; font-weight: 600;">
                                    Entrega completada
                                </p>
                                <?php if ($delivery['payment_released']): ?>
                                    <p style="margin-top: 5px; color: #666; font-size: 14px;">
                                        Pago liberado
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="shop-verificacion-qr-producto.js"></script>
</body>
</html>