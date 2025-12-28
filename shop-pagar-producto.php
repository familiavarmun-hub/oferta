<?php
/**
 * shop-pagar-producto.php
 * Página de pago con Stripe para productos
 */
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

$offer_id = (int)($_GET['offer_id'] ?? 0);
$user_id = $_SESSION['usuario_id'];

if ($offer_id <= 0) {
    echo '<div class="alert alert-danger">ID de oferta inválido</div>';
    exit;
}

// Cargar oferta aceptada
$sql = "SELECT o.*,
               p.id as product_id, p.name as product_name, p.description as product_description,
               p.category, p.weight, p.dimensions, p.seller_id,
               COALESCE(seller.full_name, seller.username, 'Vendedor') as seller_name,
               COALESCE(buyer.full_name, buyer.username, 'Comprador') as buyer_name,
               seller.id as seller_user_id,
               seller.verificado as seller_verified
        FROM shop_product_offers o
        JOIN shop_products p ON p.id = o.product_id
        LEFT JOIN accounts seller ON seller.id = o.seller_id
        LEFT JOIN accounts buyer ON buyer.id = o.buyer_id
        WHERE o.id = :offer_id AND o.buyer_id = :user_id AND o.status = 'accepted'";

$stmt = $conexion->prepare($sql);
$stmt->execute([':offer_id' => $offer_id, ':user_id' => $user_id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    echo '<div class="alert alert-danger">Oferta no encontrada o no está aceptada</div>';
    exit;
}

// Calcular precios
$subtotal = (float)$data['offered_price'] * (int)$data['quantity'];
$comision = $subtotal * 0.10; // 10% de comisión
$total = $subtotal;
$moneda = $data['offered_currency'] ?? 'EUR';

// Verificar método de pago del vendedor
$seller_user_id = $data['seller_user_id'];
$sql_payment = "SELECT stripe_account_id
                FROM accounts
                WHERE id = :user_id
                LIMIT 1";
$stmt_payment = $conexion->prepare($sql_payment);
$stmt_payment->execute([':user_id' => $seller_user_id]);
$payment_data = $stmt_payment->fetch(PDO::FETCH_ASSOC);

$has_stripe_connect = $payment_data && !empty($payment_data['stripe_account_id']);
$stripe_connect_id = $has_stripe_connect ? $payment_data['stripe_account_id'] : null;

// Obtener rating del vendedor
$sql_rating = "SELECT AVG(valoracion) as rating, COUNT(*) as total
               FROM comentarios
               WHERE usuario_id = :seller_id";
$stmt_rating = $conexion->prepare($sql_rating);
$stmt_rating->execute([':seller_id' => $seller_user_id]);
$rating_data = $stmt_rating->fetch(PDO::FETCH_ASSOC);
$rating = $rating_data ? round($rating_data['rating'], 1) : 0;
$total_ratings = $rating_data ? $rating_data['total'] : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pago - SendVialo Shop</title>
  <link rel="stylesheet" href="../css/estilos.css">
  <link rel="icon" href="../Imagenes/globo5.png" type="image/png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <script src="https://js.stripe.com/v3/"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <style>
    :root {
      --primary-color: #42ba25;
      --success-color: #4CAF50;
      --danger-color: #f44336;
      --text-primary: #1A1F36;
      --text-secondary: #697386;
      --border-color: #E3E8EE;
      --bg-gray: #F7FAFC;
    }

    * { box-sizing: border-box; }

    body {
      background: linear-gradient(180deg, #F7FAFC 0%, #FFFFFF 100%);
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      color: var(--text-primary);
      min-height: 100vh;
      margin: 0;
      padding: 120px 20px 40px;
    }

    .payment-container {
      max-width: 1200px;
      margin: 0 auto;
    }

    .page-header {
      text-align: center;
      margin-bottom: 40px;
    }

    .page-header h1 {
      font-size: 2.5rem;
      font-weight: 700;
      background: linear-gradient(135deg, var(--primary-color) 0%, var(--success-color) 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .payment-grid {
      display: grid;
      grid-template-columns: 1.5fr 1fr;
      gap: 30px;
      align-items: start;
    }

    .card-modern {
      background: #fff;
      border-radius: 16px;
      padding: 30px;
      box-shadow: 0 2px 8px rgba(0,0,0,.04), 0 8px 24px rgba(0,0,0,.06);
      border: 1px solid var(--border-color);
      animation: fadeInUp 0.6s ease-out;
    }

    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(30px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .seller-card {
      grid-column: 1 / -1;
      margin-bottom: 20px;
    }

    .seller-header {
      display: flex;
      align-items: center;
      gap: 20px;
      margin-bottom: 20px;
    }

    .profile-wrapper {
      position: relative;
      width: 90px;
      height: 90px;
      flex-shrink: 0;
    }

    .profile-img {
      width: 90px;
      height: 90px;
      border-radius: 50%;
      object-fit: cover;
      border: 4px solid rgba(66, 186, 37, 0.2);
    }

    .seller-info h2 {
      font-size: 1.8rem;
      font-weight: 700;
      margin: 0 0 10px 0;
    }

    .rating-display {
      display: flex;
      align-items: center;
      gap: 8px;
      color: #ffd700;
    }

    .product-info {
      background: var(--bg-gray);
      padding: 20px;
      border-radius: 12px;
      margin-top: 20px;
    }

    .product-info h3 {
      font-size: 1.2rem;
      font-weight: 700;
      margin-bottom: 15px;
      color: var(--text-primary);
    }

    .info-row {
      display: flex;
      justify-content: space-between;
      padding: 10px 0;
      border-bottom: 1px solid #e0e0e0;
    }

    .info-row:last-child {
      border-bottom: none;
    }

    .alert-info {
      background: #dcfce7;
      border-left: 4px solid var(--primary-color);
      padding: 15px;
      border-radius: 8px;
      margin-top: 20px;
      color: #333;
    }

    .summary-header {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 25px;
    }

    .summary-header h3 {
      margin: 0;
      font-size: 1.4rem;
      font-weight: 700;
    }

    .price-row {
      display: flex;
      justify-content: space-between;
      padding: 12px 0;
      color: var(--text-secondary);
    }

    .price-row.subtotal {
      border-bottom: 1px solid var(--border-color);
    }

    .price-total {
      display: flex;
      justify-content: space-between;
      padding: 20px 0;
      font-size: 1.3rem;
      font-weight: 700;
      border-top: 2px solid var(--border-color);
      margin-top: 15px;
      color: var(--text-primary);
    }

    .total-amount {
      color: var(--primary-color);
    }

    .payment-section h3 {
      font-size: 1.2rem;
      font-weight: 700;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .card-form-group {
      margin-bottom: 20px;
    }

    .card-form-group label {
      display: block;
      margin-bottom: 8px;
      font-weight: 600;
      color: var(--text-primary);
    }

    .card-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 15px;
    }

    #card-number-element,
    #card-expiry-element,
    #card-cvc-element {
      padding: 12px;
      border: 1px solid var(--border-color);
      border-radius: 8px;
      background: white;
      transition: border-color 0.3s;
    }

    #card-errors {
      color: var(--danger-color);
      margin: 15px 0;
      font-size: 0.9rem;
    }

    .btn-pay {
      width: 100%;
      padding: 16px;
      background: linear-gradient(135deg, var(--primary-color) 0%, var(--success-color) 100%);
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 1.1rem;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.3s ease;
      margin-top: 20px;
    }

    .btn-pay:hover:not(:disabled) {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(66, 186, 37, 0.4);
    }

    .btn-pay:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }

    @media (max-width: 768px) {
      body {
        padding: 100px 15px 30px;
      }

      .payment-grid {
        grid-template-columns: 1fr;
      }

      .card-row {
        grid-template-columns: 1fr;
      }

      .page-header h1 {
        font-size: 2rem;
      }
    }
  </style>
</head>
<body>
  <?php include 'shop-header.php'; ?>

  <div class="payment-container">
    <div class="page-header">
      <h1><i class="bi bi-credit-card"></i> Completar Pago</h1>
    </div>

    <div class="payment-grid">
      <!-- INFORMACIÓN DEL VENDEDOR Y PRODUCTO -->
      <div class="seller-card card-modern">
        <div class="seller-header">
          <div class="profile-wrapper">
            <img src="../mostrar_imagen.php?id=<?php echo $seller_user_id; ?>"
                 alt="<?php echo htmlspecialchars($data['seller_name']); ?>"
                 class="profile-img">
          </div>
          <div class="seller-info">
            <h2>
              <?php echo htmlspecialchars($data['seller_name']); ?>
              <?php if ($data['seller_verified']): ?>
                <i class="bi bi-check-circle-fill" style="color: var(--primary-color);"></i>
              <?php endif; ?>
            </h2>
            <?php if ($rating > 0): ?>
              <div class="rating-display">
                <?php
                $full_stars = floor($rating);
                $half_star = ($rating - $full_stars) >= 0.5 ? 1 : 0;
                $empty_stars = 5 - $full_stars - $half_star;

                for ($i = 0; $i < $full_stars; $i++) echo '<i class="bi bi-star-fill"></i>';
                if ($half_star) echo '<i class="bi bi-star-half"></i>';
                for ($i = 0; $i < $empty_stars; $i++) echo '<i class="bi bi-star"></i>';
                ?>
                <span style="color: #333; font-weight: 600;"><?php echo number_format($rating, 1); ?></span>
                <span style="color: #999; font-size: 0.9rem;">(<?php echo $total_ratings; ?> valoraciones)</span>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="product-info">
          <h3><i class="bi bi-box-seam"></i> Producto</h3>
          <div class="info-row">
            <span><strong>Nombre:</strong></span>
            <span><?php echo htmlspecialchars($data['product_name']); ?></span>
          </div>
          <?php if ($data['category']): ?>
          <div class="info-row">
            <span><strong>Categoría:</strong></span>
            <span><?php echo htmlspecialchars($data['category']); ?></span>
          </div>
          <?php endif; ?>
          <div class="info-row">
            <span><strong>Cantidad:</strong></span>
            <span><?php echo $data['quantity']; ?> unidad(es)</span>
          </div>
          <div class="info-row">
            <span><strong>Precio unitario:</strong></span>
            <span><?php echo number_format($data['offered_price'], 2); ?> <?php echo $moneda; ?></span>
          </div>
          <?php if ($data['weight']): ?>
          <div class="info-row">
            <span><strong>Peso:</strong></span>
            <span><?php echo htmlspecialchars($data['weight']); ?></span>
          </div>
          <?php endif; ?>
          <?php if ($data['dimensions']): ?>
          <div class="info-row">
            <span><strong>Dimensiones:</strong></span>
            <span><?php echo htmlspecialchars($data['dimensions']); ?></span>
          </div>
          <?php endif; ?>
        </div>

        <div class="alert-info">
          <i class="bi bi-shield-check"></i>
          <strong>Pago Protegido</strong><br>
          <?php if (!$has_stripe_connect): ?>
          Tu dinero estará en custodia hasta que confirmes la recepción del producto escaneando el código QR.
          <?php else: ?>
          El pago se procesará de forma segura. Confirma la entrega escaneando el código QR.
          <?php endif; ?>
        </div>
      </div>

      <!-- RESUMEN Y PAGO -->
      <div class="summary-card">
        <div class="card-modern">
          <div class="summary-header">
            <i class="bi bi-receipt" style="font-size: 1.5rem; color: var(--primary-color);"></i>
            <h3>Resumen del Pago</h3>
          </div>

          <div class="price-row subtotal">
            <span>Subtotal (<?php echo $data['quantity']; ?> × <?php echo number_format($data['offered_price'], 2); ?> <?php echo $moneda; ?>):</span>
            <span><?php echo number_format($subtotal, 2); ?> <?php echo $moneda; ?></span>
          </div>

          <div class="price-row subtotal">
            <span>Comisión SendVialo (10%):</span>
            <span><?php echo number_format($comision, 2); ?> <?php echo $moneda; ?></span>
          </div>

          <div class="price-total">
            <span>Total a pagar:</span>
            <span class="total-amount">
              <?php echo number_format($total, 2); ?> <?php echo $moneda; ?>
            </span>
          </div>
        </div>

        <div class="card-modern" style="margin-top: 20px;">
          <div class="payment-section">
            <h3>
              <i class="bi bi-credit-card"></i>
              Pago con Tarjeta
            </h3>

            <form id="payment-form">
              <div class="card-form-group">
                <label>Número de tarjeta</label>
                <div id="card-number-element"></div>
              </div>

              <div class="card-row">
                <div class="card-form-group">
                  <label>Fecha de expiración</label>
                  <div id="card-expiry-element"></div>
                </div>
                <div class="card-form-group">
                  <label>CVV</label>
                  <div id="card-cvc-element"></div>
                </div>
              </div>

              <div id="card-errors"></div>

              <button type="submit" id="card-button" class="btn-pay">
                <i class="bi bi-lock-fill"></i>
                Pagar <?php echo number_format($total, 2); ?> <?php echo $moneda; ?>
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    const stripe = Stripe('<?php echo htmlspecialchars(STRIPE_PUBLISHABLE_KEY); ?>');
    const elements = stripe.elements();

    const style = {
      base: {
        color: '#32325d',
        fontFamily: '"Inter", sans-serif',
        fontSmoothing: 'antialiased',
        fontSize: '16px',
        '::placeholder': { color: '#aab7c4' }
      },
      invalid: {
        color: '#fa755a',
        iconColor: '#fa755a'
      }
    };

    const cardNumber = elements.create('cardNumber', {style: style});
    const cardExpiry = elements.create('cardExpiry', {style: style});
    const cardCvc = elements.create('cardCvc', {style: style});

    cardNumber.mount('#card-number-element');
    cardExpiry.mount('#card-expiry-element');
    cardCvc.mount('#card-cvc-element');

    [cardNumber, cardExpiry, cardCvc].forEach(element => {
      element.on('change', function(event) {
        const displayError = document.getElementById('card-errors');
        if (event.error) {
          displayError.innerHTML = `<i class="bi bi-exclamation-triangle"></i> ${event.error.message}`;
        } else {
          displayError.textContent = '';
        }
      });
    });

    document.getElementById('payment-form').addEventListener('submit', async function(e) {
      e.preventDefault();

      const button = document.getElementById('card-button');
      button.disabled = true;
      button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Procesando...';

      Swal.fire({
        title: 'Procesando pago...',
        html: '<div class="spinner-border text-success"></div><p class="mt-3">Por favor espera...</p>',
        allowOutsideClick: false,
        showConfirmButton: false
      });

      try {
        const {paymentMethod, error} = await stripe.createPaymentMethod({
          type: 'card',
          card: cardNumber,
        });

        if (error) {
          throw new Error(error.message);
        }

        const formData = new FormData();
        formData.append('offer_id', <?php echo $offer_id; ?>);
        formData.append('payment_method_id', paymentMethod.id);

        const response = await fetch('shop-realizar-pago-producto.php', {
          method: 'POST',
          body: formData
        });

        const data = await response.json();

        if (data.success) {
          Swal.fire({
            icon: 'success',
            title: '¡Pago exitoso!',
            text: 'Redirigiendo a confirmación...',
            timer: 2000,
            showConfirmButton: false
          }).then(() => {
            window.location.href = data.redirect;
          });
        } else {
          throw new Error(data.error || 'Error al procesar el pago');
        }
      } catch (error) {
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: error.message,
          confirmButtonColor: '#f44336'
        });
        button.disabled = false;
        button.innerHTML = '<i class="bi bi-lock-fill"></i> Pagar <?php echo number_format($total, 2); ?> <?php echo $moneda; ?>';
      }
    });
  </script>
</body>
</html>