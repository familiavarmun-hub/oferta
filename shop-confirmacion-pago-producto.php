<?php
/**
 * shop-confirmacion-pago-producto.php
 * Confirmación de pago completado para productos
 */
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

$delivery_id = (int)($_GET['delivery_id'] ?? 0);
$user_id = $_SESSION['usuario_id'];

if ($delivery_id <= 0) {
    echo '<div class="alert alert-danger">ID inválido</div>';
    exit;
}

try {
    // Cargar datos completos del producto y pago
    $sql = "SELECT d.*,
                   oi.unit_price as offered_price, oi.currency as offered_currency, oi.quantity,
                   p.name as product_name, p.description as product_description,
                   p.category, p.weight, p.dimensions,
                   COALESCE(seller.full_name, seller.username, 'Vendedor') as seller_name,
                   COALESCE(buyer.full_name, buyer.username, 'Comprador') as buyer_name,
                   seller.id as seller_user_id, seller.verificado as seller_verified,
                   buyer.id as buyer_user_id,
                   pay.estado as payment_status, pay.payment_intent_id as stripe_payment_intent_id,
                   pay.monto_total, pay.created_at as fecha_pago,
                   (CASE WHEN pay.estado = 'PENDING' THEN 1 ELSE 0 END) as custody_mode
            FROM shop_product_deliveries d
            JOIN shop_order_items oi ON d.order_item_id = oi.id
            JOIN shop_products p ON d.product_id = p.id
            LEFT JOIN accounts seller ON d.seller_id = seller.id
            LEFT JOIN accounts buyer ON d.buyer_id = buyer.id
            LEFT JOIN payments_in_custody pay ON d.payment_id = pay.id
            WHERE d.id = :delivery_id AND d.buyer_id = :user_id";

    $stmt = $conexion->prepare($sql);
    $stmt->execute([':delivery_id' => $delivery_id, ':user_id' => $user_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        echo '<div class="alert alert-danger">Datos no encontrados para delivery_id='.$delivery_id.'</div>';
        exit;
    }
} catch (Exception $e) {
    error_log("Error en confirmacion pago: " . $e->getMessage());
    echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

// Calcular montos
$total_amount = (float)$data['offered_price'] * (int)$data['quantity'];
$platform_fee = $total_amount * 0.10;
$seller_amount = $total_amount - $platform_fee;
$currency = strtoupper($data['offered_currency']);

// Obtener rating del vendedor
$sql_rating = "SELECT AVG(valoracion) as rating, COUNT(*) as total
               FROM comentarios WHERE usuario_id = :seller_id";
$stmt_rating = $conexion->prepare($sql_rating);
$stmt_rating->execute([':seller_id' => $data['seller_user_id']]);
$rating_data = $stmt_rating->fetch(PDO::FETCH_ASSOC);
$rating = $rating_data ? round($rating_data['rating'], 1) : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pago Confirmado - SendVialo Shop</title>
  <link rel="stylesheet" href="../css/estilos.css">
  <link rel="icon" href="../Imagenes/globo5.png" type="image/png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <style>
    :root {
      --primary-color: #42ba25;
      --success-color: #4CAF50;
      --warning-color: #FF9800;
    }

    * { box-sizing: border-box; }

    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(180deg, #F7FAFC 0%, #FFFFFF 100%);
      min-height: 100vh;
      padding: 120px 20px 40px;
    }

    .confirmation-container {
      max-width: 800px;
      margin: 0 auto;
    }

    .success-icon {
      text-align: center;
      margin-bottom: 30px;
    }

    .success-icon i {
      font-size: 5rem;
      color: var(--success-color);
      animation: scaleIn 0.5s ease-out;
    }

    @keyframes scaleIn {
      from { transform: scale(0); }
      to { transform: scale(1); }
    }

    .card-modern {
      background: #fff;
      border-radius: 16px;
      padding: 40px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
      margin-bottom: 30px;
    }

    .confirmation-header {
      text-align: center;
      margin-bottom: 40px;
    }

    .confirmation-header h1 {
      font-size: 2.2rem;
      font-weight: 700;
      color: var(--success-color);
      margin-bottom: 10px;
    }

    .confirmation-header p {
      font-size: 1.1rem;
      color: #666;
    }

    .info-section {
      margin-bottom: 30px;
      padding-bottom: 30px;
      border-bottom: 2px solid #f0f0f0;
    }

    .info-section:last-child {
      border-bottom: none;
      padding-bottom: 0;
      margin-bottom: 0;
    }

    .info-section h2 {
      font-size: 1.4rem;
      font-weight: 700;
      margin-bottom: 20px;
      color: var(--primary-color);
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .info-row {
      display: flex;
      justify-content: space-between;
      padding: 12px 0;
      font-size: 1rem;
    }

    .info-row strong {
      color: #333;
    }

    .info-row span {
      color: #666;
      text-align: right;
    }

    .highlight-box {
      background: linear-gradient(135deg, #dcfce7 0%, #f0fdf4 100%);
      padding: 20px;
      border-radius: 12px;
      border-left: 4px solid var(--success-color);
      margin: 20px 0;
    }

    .highlight-box h3 {
      font-size: 1.1rem;
      font-weight: 700;
      margin-bottom: 10px;
      color: var(--success-color);
    }

    .qr-box {
      background: linear-gradient(135deg, #fff9e6 0%, #fffbf0 100%);
      padding: 25px;
      border-radius: 12px;
      border: 3px dashed var(--primary-color);
      margin: 25px 0;
      text-align: center;
    }

    .qr-box h3 {
      font-size: 1.3rem;
      font-weight: 700;
      margin-bottom: 15px;
      color: var(--primary-color);
    }

    .qr-box .qr-code {
      font-family: 'Courier New', monospace;
      font-size: 1.1rem;
      background: white;
      padding: 15px 20px;
      border-radius: 8px;
      display: inline-block;
      margin: 10px 0;
      font-weight: 700;
      color: var(--primary-color);
      border: 2px solid var(--primary-color);
    }

    .seller-info {
      display: flex;
      align-items: center;
      gap: 20px;
      padding: 20px;
      background: #f8f9fa;
      border-radius: 12px;
      margin-top: 15px;
    }

    .seller-avatar {
      width: 70px;
      height: 70px;
      border-radius: 50%;
      border: 3px solid var(--primary-color);
      object-fit: cover;
    }

    .seller-details h3 {
      font-size: 1.3rem;
      font-weight: 700;
      margin-bottom: 5px;
    }

    .rating-stars {
      color: #ffd700;
    }

    .action-buttons {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 15px;
      margin-top: 30px;
    }

    .btn-action {
      padding: 16px;
      border: none;
      border-radius: 12px;
      font-size: 1.1rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      text-decoration: none;
      text-align: center;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }

    .btn-primary {
      background: linear-gradient(135deg, var(--primary-color) 0%, var(--success-color) 100%);
      color: #fff;
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(66, 186, 37, 0.4);
      color: #fff;
    }

    .btn-secondary {
      background: #f0f0f0;
      color: #333;
    }

    .btn-secondary:hover {
      background: #e0e0e0;
    }

    @media (max-width: 768px) {
      .action-buttons {
        grid-template-columns: 1fr;
      }

      .card-modern {
        padding: 25px;
      }

      body {
        padding: 100px 15px 30px;
      }
    }
  </style>
</head>
<body>
  <?php include 'shop-header.php'; ?>

  <div class="confirmation-container">
    <div class="success-icon">
      <i class="bi bi-check-circle-fill"></i>
    </div>

    <div class="card-modern">
      <div class="confirmation-header">
        <h1>¡Pago Confirmado!</h1>
        <p>Tu compra ha sido procesada exitosamente</p>
      </div>

      <!-- INFORMACIÓN DEL PRODUCTO -->
      <div class="info-section">
        <h2>
          <i class="bi bi-box-seam"></i>
          Producto Comprado
        </h2>

        <div class="info-row">
          <strong>Nombre:</strong>
          <span><?php echo htmlspecialchars($data['product_name']); ?></span>
        </div>

        <?php if ($data['product_description']): ?>
        <div class="info-row">
          <strong>Descripción:</strong>
          <span><?php echo htmlspecialchars($data['product_description']); ?></span>
        </div>
        <?php endif; ?>

        <?php if ($data['category']): ?>
        <div class="info-row">
          <strong>Categoría:</strong>
          <span><?php echo htmlspecialchars($data['category']); ?></span>
        </div>
        <?php endif; ?>

        <div class="info-row">
          <strong>Cantidad:</strong>
          <span><?php echo $data['quantity']; ?> unidad(es)</span>
        </div>

        <?php if ($data['weight']): ?>
        <div class="info-row">
          <strong>Peso:</strong>
          <span><?php echo htmlspecialchars($data['weight']); ?></span>
        </div>
        <?php endif; ?>

        <?php if ($data['dimensions']): ?>
        <div class="info-row">
          <strong>Dimensiones:</strong>
          <span><?php echo htmlspecialchars($data['dimensions']); ?></span>
        </div>
        <?php endif; ?>
      </div>

      <!-- INFORMACIÓN DEL VENDEDOR -->
      <div class="info-section">
        <h2>
          <i class="bi bi-person-circle"></i>
          Vendedor
        </h2>

        <div class="seller-info">
          <img src="../mostrar_imagen.php?id=<?php echo $data['seller_user_id']; ?>"
               alt="Avatar" class="seller-avatar">
          <div class="seller-details">
            <h3>
              <?php echo htmlspecialchars($data['seller_name']); ?>
              <?php if ($data['seller_verified']): ?>
                <i class="bi bi-check-circle-fill" style="color: var(--primary-color); font-size: 1.2rem;"></i>
              <?php endif; ?>
            </h3>
            <?php if ($rating > 0): ?>
              <div class="rating-stars">
                <?php
                $full_stars = floor($rating);
                $half_star = ($rating - $full_stars) >= 0.5 ? 1 : 0;
                $empty_stars = 5 - $full_stars - $half_star;

                for ($i = 0; $i < $full_stars; $i++) echo '<i class="bi bi-star-fill"></i>';
                if ($half_star) echo '<i class="bi bi-star-half"></i>';
                for ($i = 0; $i < $empty_stars; $i++) echo '<i class="bi bi-star"></i>';
                ?>
                <span style="color: #333; margin-left: 5px;"><?php echo number_format($rating, 1); ?></span>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- INFORMACIÓN DEL PAGO -->
      <div class="info-section">
        <h2>
          <i class="bi bi-credit-card"></i>
          Detalles del Pago
        </h2>

        <div class="info-row">
          <strong>Precio por unidad:</strong>
          <span><?php echo number_format($data['offered_price'], 2); ?> <?php echo $currency; ?></span>
        </div>

        <div class="info-row">
          <strong>Cantidad:</strong>
          <span><?php echo $data['quantity']; ?> × <?php echo number_format($data['offered_price'], 2); ?> <?php echo $currency; ?></span>
        </div>

        <div class="info-row">
          <strong>Subtotal:</strong>
          <span><?php echo number_format($total_amount, 2); ?> <?php echo $currency; ?></span>
        </div>

        <div class="info-row">
          <strong>Comisión SendVialo (10%):</strong>
          <span><?php echo number_format($platform_fee, 2); ?> <?php echo $currency; ?></span>
        </div>

        <div class="info-row" style="font-size: 1.2rem; font-weight: 700; color: var(--success-color); border-top: 2px solid #f0f0f0; padding-top: 15px; margin-top: 10px;">
          <strong>Total Pagado:</strong>
          <span><?php echo number_format($total_amount, 2); ?> <?php echo $currency; ?></span>
        </div>

        <?php if ($data['fecha_pago']): ?>
        <div class="info-row">
          <strong>Fecha de pago:</strong>
          <span><?php echo date('d/m/Y H:i', strtotime($data['fecha_pago'])); ?></span>
        </div>
        <?php endif; ?>
      </div>

      <!-- CÓDIGO QR -->
      <div class="qr-box">
        <h3><i class="bi bi-qr-code"></i> Tu Código de Entrega</h3>
        <p style="margin: 10px 0; color: #666;">
          Cuando recibas el producto, escanea el código QR del vendedor para confirmar la entrega
        </p>
        <div class="qr-code">
          <?php echo htmlspecialchars($data['qr_code_unique_id']); ?>
        </div>
        <p style="margin: 10px 0; font-size: 0.9rem; color: #888;">
          El vendedor ha recibido este código. Al escanearlo, confirmarás que recibiste el producto.
        </p>
      </div>

      <!-- ESTADO DEL PAGO -->
      <div class="highlight-box">
        <?php if ($data['custody_mode'] == 0): ?>
          <h3><i class="bi bi-lightning-charge-fill"></i> División Automática Completada</h3>
          <p style="margin: 0;">
            El pago ha sido dividido automáticamente. El vendedor ha recibido <?php echo number_format($seller_amount, 2); ?> <?php echo $currency; ?> inmediatamente.
          </p>
        <?php else: ?>
          <h3><i class="bi bi-shield-check"></i> Pago en Custodia</h3>
          <p style="margin: 0;">
            Tu dinero está protegido. Se liberará al vendedor (<?php echo number_format($seller_amount, 2); ?> <?php echo $currency; ?>) cuando confirmes la recepción del producto mediante código QR.
          </p>
        <?php endif; ?>
      </div>

      <!-- INSTRUCCIONES -->
      <div class="info-section">
        <h2>
          <i class="bi bi-info-circle"></i>
          Próximos Pasos
        </h2>
        <ol style="padding-left: 20px; line-height: 1.8;">
          <li>El vendedor ha sido notificado de tu compra</li>
          <li>El vendedor preparará el producto según lo acordado</li>
          <li>Coordina con el vendedor para la entrega del producto</li>
          <li>Cuando recibas el producto, escanea el código QR del vendedor</li>
          <li>Una vez confirmado, <?php echo ($data['custody_mode'] == 1) ? 'el pago se liberará automáticamente al vendedor' : 'la transacción estará completada'; ?></li>
        </ol>
      </div>

      <!-- BOTONES DE ACCIÓN -->
      <div class="action-buttons">
        <a href="shop-verificacion-qr-producto.php" class="btn-action btn-primary">
          <i class="bi bi-qr-code-scan"></i>
          Escanear Código QR
        </a>
        <a href="shop-my-product-offers.php" class="btn-action btn-secondary">
          <i class="bi bi-list-ul"></i>
          Ver mis compras
        </a>
      </div>

      <div class="action-buttons" style="margin-top: 15px;">
        <a href="index.php" class="btn-action btn-secondary">
          <i class="bi bi-house"></i>
          Volver a la tienda
        </a>
        <a href="../chatpage.php?username=<?php echo urlencode($data['seller_name']); ?>" class="btn-action btn-secondary">
          <i class="bi bi-chat-dots"></i>
          Contactar vendedor
        </a>
      </div>
    </div>
  </div>
</body>
</html>