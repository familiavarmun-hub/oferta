<?php
// shop-payment.php - Página de pago para propuestas aceptadas
session_start();
require_once '../config.php';
require_once 'insignias1.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

$proposal_id = (int)($_GET['proposal_id'] ?? 0);
$user_id = $_SESSION['usuario_id'];

if ($proposal_id <= 0) {
    echo '<div class="alert alert-danger">ID de propuesta inválido</div>';
    exit;
}

// Cargar propuesta base
$sql = "SELECT p.*,
               r.id as request_id, r.title, r.description, r.category, r.quantity,
               r.origin_country, r.destination_city, r.user_id as requester_id,
               COALESCE(req_user.full_name, req_user.username, 'Solicitante') as requester_name,
               COALESCE(trav_user.full_name, trav_user.username, 'Viajero') as traveler_name,
               trav_user.id as traveler_user_id
        FROM shop_request_proposals p
        JOIN shop_requests r ON r.id = p.request_id
        LEFT JOIN accounts req_user ON req_user.id = r.user_id
        LEFT JOIN accounts trav_user ON trav_user.id = p.traveler_id
        WHERE p.id = :proposal_id AND r.user_id = :user_id AND p.status = 'pending'";

$stmt = $conexion->prepare($sql);
$stmt->execute([':proposal_id' => $proposal_id, ':user_id' => $user_id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    echo '<div class="alert alert-danger">Propuesta no encontrada o ya procesada</div>';
    exit;
}

// ✅ OBTENER PRECIO DE LA ÚLTIMA NEGOCIACIÓN
$table_exists = false;
try {
    $check = $conexion->query("SHOW TABLES LIKE 'shop_proposal_negotiations'");
    $table_exists = ($check->rowCount() > 0);
} catch (Exception $e) {
    $table_exists = false;
}

$final_price = (float)$data['proposed_price'];
$final_currency = $data['proposed_currency'];
$final_delivery = $data['estimated_delivery'];
$counteroffer_count = 0;

if ($table_exists) {
    $sql_negotiations = "SELECT proposed_price, proposed_currency, estimated_delivery, created_at
                         FROM shop_proposal_negotiations
                         WHERE proposal_id = :proposal_id
                         ORDER BY created_at ASC";
    
    $stmt_neg = $conexion->prepare($sql_negotiations);
    $stmt_neg->execute([':proposal_id' => $proposal_id]);
    $all_negotiations = $stmt_neg->fetchAll(PDO::FETCH_ASSOC);
    
    if ($all_negotiations && count($all_negotiations) > 0) {
        $counteroffer_count = count($all_negotiations) - 1;
        $latest = end($all_negotiations);
        $final_price = (float)$latest['proposed_price'];
        $final_currency = $latest['proposed_currency'];
        $final_delivery = $latest['estimated_delivery'] ?: $data['estimated_delivery'];
    }
}

$has_negotiation = ($counteroffer_count > 0);

// Calcular totales
$subtotal = $final_price;
$comision = $subtotal * 0.20;
$total = $subtotal + $comision;
$moneda = $final_currency;

// Verificar método de pago del viajero
$traveler_user_id = $data['traveler_user_id'];
$sql_payment = "SELECT method_type, stripe_account_id, stripe_email
                FROM payment_methods
                WHERE user_id = :user_id AND method_type = 'stripe_connect' AND is_verified = 1
                LIMIT 1";
$stmt_payment = $conexion->prepare($sql_payment);
$stmt_payment->execute([':user_id' => $traveler_user_id]);
$payment_method = $stmt_payment->fetch(PDO::FETCH_ASSOC);

$has_stripe_connect = false;
$stripe_connect_id = null;
$traveler_email = 'No configurado';

if ($payment_method && !empty($payment_method['stripe_account_id'])) {
    $account_id = $payment_method['stripe_account_id'];
    if (preg_match('/^acct_[A-Za-z0-9]{14,}$/', $account_id)) {
        $has_stripe_connect = true;
        $stripe_connect_id = $account_id;
        $traveler_email = $payment_method['stripe_email'] ?? 'No configurado';
    }
}

// Obtener rating del viajero
$sql_rating = "SELECT AVG(rating) as rating, COUNT(*) as total
               FROM shop_seller_ratings
               WHERE seller_id = :seller_id";
$stmt_rating = $conexion->prepare($sql_rating);
$stmt_rating->execute([':seller_id' => $traveler_user_id]);
$rating_data = $stmt_rating->fetch(PDO::FETCH_ASSOC);
$rating = $rating_data ? round($rating_data['rating'], 1) : 0;
$total_ratings = $rating_data ? $rating_data['total'] : 0;

// Verificación del viajero
$sql_verified = "SELECT verificado FROM accounts WHERE id = :id";
$stmt_verified = $conexion->prepare($sql_verified);
$stmt_verified->execute([':id' => $traveler_user_id]);
$verificado = (int)$stmt_verified->fetchColumn();
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
      --color1: #41ba0d;
      --color2: #5dcb2a;
      --primary-color: #2196F3;
      --success-color: #4CAF50;
      --danger-color: #f44336;
      --warning-color: #ff9800;
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
      padding: 0;
    }

    .payment-container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 40px 20px;
    }

    .page-header {
      text-align: center;
      margin-bottom: 40px;
    }

    .page-header h1 {
      font-size: 2.5rem;
      font-weight: 700;
      background: linear-gradient(135deg, var(--color1) 0%, var(--color2) 100%);
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

    .traveler-card {
      grid-column: 1 / -1;
      margin-bottom: 20px;
    }

    .traveler-header {
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
      border: 4px solid rgba(65, 186, 13, 0.2);
    }

    .traveler-info h2 {
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
      font-size: 1.3rem;
      font-weight: 700;
      margin-bottom: 15px;
      color: var(--color1);
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

    .summary-card {
      position: sticky;
      top: 20px;
    }

    .summary-header {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 25px;
      padding-bottom: 20px;
      border-bottom: 2px solid var(--border-color);
    }

    .summary-header h3 {
      font-size: 1.4rem;
      font-weight: 700;
      margin: 0;
    }

    .price-row {
      display: flex;
      justify-content: space-between;
      padding: 12px 0;
      font-size: 1rem;
    }

    .price-row.subtotal {
      color: var(--text-secondary);
    }

    .price-total {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 20px 0;
      margin-top: 20px;
      border-top: 2px solid var(--border-color);
      font-size: 1.3rem;
      font-weight: 700;
    }

    .total-amount {
      color: var(--color1);
      font-size: 1.8rem;
    }

    .payment-section h3 {
      font-size: 1.5rem;
      font-weight: 700;
      margin-bottom: 25px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .card-form-group {
      margin-bottom: 20px;
    }

    .card-form-group label {
      display: block;
      font-weight: 600;
      margin-bottom: 8px;
      color: var(--text-primary);
    }

    .card-form-group #card-number-element,
    .card-form-group #card-expiry-element,
    .card-form-group #card-cvc-element {
      border: 2px solid var(--border-color);
      border-radius: 8px;
      padding: 14px;
      font-size: 1rem;
    }

    .card-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 15px;
    }

    #card-errors {
      color: var(--danger-color);
      font-size: 0.9rem;
      margin-top: 10px;
    }

    .btn-pay {
      background: linear-gradient(135deg, var(--color1) 0%, var(--color2) 100%);
      color: #fff;
      border: none;
      padding: 16px;
      border-radius: 10px;
      font-size: 1.1rem;
      font-weight: 700;
      width: 100%;
      cursor: pointer;
      transition: all 0.3s ease;
      box-shadow: 0 4px 12px rgba(65, 186, 13, 0.3);
    }

    .btn-pay:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(65, 186, 13, 0.4);
    }

    .btn-pay:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none;
    }

    .alert-info {
      background: #E8F5E9;
      border-left: 4px solid var(--color1);
      padding: 15px;
      border-radius: 8px;
      margin-top: 20px;
    }

    .alert-warning {
      background: #FFF3E0;
      border-left: 4px solid var(--warning-color);
      padding: 15px;
      border-radius: 8px;
      margin-top: 20px;
    }

    .alert-warning strong {
      color: var(--warning-color);
    }

    .negotiated-badge {
      display: inline-block;
      background: linear-gradient(135deg, #FF9800, #FB8C00);
      color: white;
      padding: 8px 16px;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 600;
      margin-top: 10px;
    }

    @media (max-width: 992px) {
      .payment-grid {
        grid-template-columns: 1fr;
      }

      .summary-card {
        position: static;
      }
    }
  </style>
</head>
<body>
  <?php if (file_exists('header1.php')) include 'header1.php'; ?>

  <div class="payment-container">
    <div class="page-header">
      <h1>Confirmación de Pago</h1>
      <p style="color: var(--text-secondary); font-size: 1.1rem;">Completa el pago para aceptar la propuesta</p>
    </div>

    <?php if (!$has_stripe_connect): ?>
      <div class="card-modern" style="margin-bottom: 30px;">
        <div class="alert-warning">
          <i class="bi bi-exclamation-triangle-fill"></i>
          <strong>Advertencia: Método de pago no configurado</strong><br>
          El viajero aún no ha configurado su cuenta de Stripe Connect. El pago se realizará a la cuenta principal de SendVialo
          y se le transferirá manualmente al viajero una vez complete su configuración.<br><br>
          <strong>Viajero:</strong> <?php echo htmlspecialchars($data['traveler_name']); ?><br>
          <strong>Email:</strong> <?php echo htmlspecialchars($traveler_email); ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="payment-grid">
      <div class="card-modern traveler-card">
        <div class="traveler-header">
          <div class="profile-wrapper">
            <?php
            $imagen_viajero = "../mostrar_imagen.php?id={$traveler_user_id}";
            echo mostrarImagenConLaurel($imagen_viajero, $rating, $verificado);
            ?>
          </div>

          <div class="traveler-info">
            <h2>
              <?php echo htmlspecialchars($data['traveler_name']); ?>
              <?php if ($verificado): ?>
                <i class="bi bi-check-circle-fill" style="color: var(--color1);"></i>
              <?php endif; ?>
            </h2>
            <div class="rating-display">
              <?php if ($rating > 0): ?>
                <?php
                $full_stars = floor($rating);
                $half_star = ($rating - $full_stars) >= 0.5 ? 1 : 0;
                $empty_stars = 5 - $full_stars - $half_star;

                for ($i = 0; $i < $full_stars; $i++) echo '<i class="bi bi-star-fill"></i>';
                if ($half_star) echo '<i class="bi bi-star-half"></i>';
                for ($i = 0; $i < $empty_stars; $i++) echo '<i class="bi bi-star"></i>';
                ?>
                <span style="color: var(--text-primary); margin-left: 5px;">
                  <?php echo number_format($rating, 1); ?> (<?php echo $total_ratings; ?>)
                </span>
              <?php else: ?>
                <span style="color: var(--text-secondary);">Sin valoraciones</span>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="product-info">
          <h3><i class="bi bi-box-seam"></i> Producto Solicitado</h3>
          <div class="info-row">
            <span><strong>Título:</strong></span>
            <span><?php echo htmlspecialchars($data['title']); ?></span>
          </div>
          <div class="info-row">
            <span><strong>Categoría:</strong></span>
            <span><?php echo htmlspecialchars($data['category']); ?></span>
          </div>
          <div class="info-row">
            <span><strong>Cantidad:</strong></span>
            <span><?php echo $data['quantity']; ?> unidad(es)</span>
          </div>
          <div class="info-row">
            <span><strong>Origen:</strong></span>
            <span><?php echo htmlspecialchars($data['origin_country'] ?: 'Flexible'); ?></span>
          </div>
          <div class="info-row">
            <span><strong>Destino:</strong></span>
            <span><?php echo htmlspecialchars($data['destination_city']); ?></span>
          </div>
          <?php if ($final_delivery): ?>
          <div class="info-row">
            <span><strong>Entrega estimada:</strong></span>
            <span><?php echo date('d/m/Y', strtotime($final_delivery)); ?></span>
          </div>
          <?php endif; ?>
          
          <?php if ($has_negotiation): ?>
          <div style="margin-top: 15px;">
            <span class="negotiated-badge">
              <i class="bi bi-arrow-left-right"></i> Precio Negociado
            </span>
            <p style="margin-top: 10px; color: var(--text-secondary); font-size: 0.9rem;">
              <i class="bi bi-info-circle"></i> 
              Este precio fue acordado tras <?php echo $counteroffer_count + 1; ?> oferta(s)
            </p>
          </div>
          <?php endif; ?>
        </div>

        <div class="alert-info">
          <i class="bi bi-shield-check"></i>
          <strong>Sistema de Custodia Activado</strong><br>
          Tu dinero estará protegido hasta que confirmes la recepción del producto.
        </div>
      </div>

      <div class="summary-card">
        <div class="card-modern">
          <div class="summary-header">
            <i class="bi bi-receipt" style="font-size: 1.5rem; color: var(--color1);"></i>
            <h3>Resumen del Pago</h3>
          </div>

          <div class="price-row subtotal">
            <span>Pago al viajero:</span>
            <span><?php echo number_format($subtotal, 2); ?> <?php echo $moneda; ?></span>
          </div>

          <div class="price-row subtotal">
            <span>Comisión SendVialo (20%):</span>
            <span><?php echo number_format($comision, 2); ?> <?php echo $moneda; ?></span>
          </div>

          <div class="price-total">
            <span>Total:</span>
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
        const {token, error} = await stripe.createToken(cardNumber);

        if (error) {
          throw new Error(error.message);
        }

        const response = await fetch('shop-realizar-pago-stripe.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({
            token: token.id,
            proposal_id: <?php echo $proposal_id; ?>,
            amount: <?php echo $total; ?>,
            currency: '<?php echo strtolower($moneda); ?>',
            subtotal: <?php echo $subtotal; ?>,
            comision: <?php echo $comision; ?>,
            stripe_connect_id: <?php echo $has_stripe_connect ? json_encode($stripe_connect_id) : 'null'; ?>,
            traveler_id: <?php echo $traveler_user_id; ?>
          })
        });

        const data = await response.json();

        if (data.success) {
          Swal.fire({
            icon: 'success',
            title: '¡Pago exitoso!',
            text: 'Redirigiendo a confirmación...',
            timer: 2000,
            showConfirmButton: false,
            iconColor: '#41ba0d',
            confirmButtonColor: '#41ba0d'
          }).then(() => {
            window.location.href = `shop-confirmacion-pago.php?proposal_id=<?php echo $proposal_id; ?>&payment_id=${data.payment_id}`;
          });
        } else {
          throw new Error(data.message || 'Error al procesar el pago');
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