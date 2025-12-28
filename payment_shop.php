<?php
// payment_shop.php - Sistema de pago para productos de SendVialo Shop
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Iniciar sesión primero
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Require files con rutas absolutas
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/insignias1.php';

// Verificar que el usuario esté logueado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php?redirect=shop');
    exit;
}

$comprador_id = $_SESSION['usuario_id'];
$comprador_username = $_SESSION['usuario_nombre'] ?? $_SESSION['full_name'] ?? 'Usuario';

// Obtener datos del carrito desde POST
if (!isset($_POST['cart']) || empty($_POST['cart'])) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error - SendVialo Shop</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container mt-5">
            <div class="alert alert-danger" role="alert">
                <h4 class="alert-heading">Carrito Vacío</h4>
                <p>No hay productos en el carrito.</p>
                <hr>
                <a href="shop-index.php" class="btn btn-primary">Volver a la tienda</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$cart = json_decode($_POST['cart'], true);

if (!is_array($cart) || empty($cart)) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error - SendVialo Shop</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container mt-5">
            <div class="alert alert-danger" role="alert">
                <h4 class="alert-heading">Error en el Carrito</h4>
                <p>Los datos del carrito son inválidos.</p>
                <hr>
                <a href="shop-index.php" class="btn btn-primary">Volver a la tienda</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Calcular totales y agrupar por vendedor
$sellers_data = [];
$total_general = 0;

foreach ($cart as $item) {
    try {
        $sql_product = "SELECT p.*, a.username as seller_username, a.id as seller_id, a.verificado as seller_verified
                        FROM shop_products p 
                        JOIN accounts a ON p.seller_id = a.id
                        WHERE p.id = ? AND p.status = 'active'";
        $stmt_product = $conexion->prepare($sql_product);
        $stmt_product->execute([$item['product_id']]);
        $product = $stmt_product->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) continue;
        
        $seller_id = $product['seller_id'];
        $item_total = floatval($item['price']) * intval($item['quantity']);
        
        if (!isset($sellers_data[$seller_id])) {
            $sellers_data[$seller_id] = [
                'seller_id' => $seller_id,
                'seller_username' => $product['seller_username'],
                'seller_verified' => $product['seller_verified'],
                'items' => [],
                'subtotal' => 0,
                'currency' => $item['currency'] ?? 'EUR'
            ];
        }
        
        $sellers_data[$seller_id]['items'][] = [
            'product_id' => $item['product_id'],
            'name' => $item['name'],
            'price' => floatval($item['price']),
            'quantity' => intval($item['quantity']),
            'image' => $item['image'] ?? null,
            'currency' => $item['currency'] ?? 'EUR'
        ];
        
        $sellers_data[$seller_id]['subtotal'] += $item_total;
        $total_general += $item_total;
    } catch (Exception $e) {
        error_log("Error procesando item: " . $e->getMessage());
        continue;
    }
}

if (empty($sellers_data)) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error - SendVialo Shop</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container mt-5">
            <div class="alert alert-warning" role="alert">
                <h4 class="alert-heading">Productos No Disponibles</h4>
                <p>Los productos del carrito ya no están disponibles.</p>
                <hr>
                <a href="shop-index.php" class="btn btn-primary">Volver a la tienda</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Obtener primer vendedor
$first_seller = reset($sellers_data);
$vendedor_principal_id = $first_seller['seller_id'];
$vendedor_principal_username = $first_seller['seller_username'];
$moneda_viaje = $first_seller['currency'];

// Calcular totales
$subtotal = $total_general;
$comisionSendVialo = $subtotal * 0.20;
$totalAPagar = $subtotal + $comisionSendVialo;

// Obtener métodos de pago del vendedor
$metodos_configurados = [];
$metodoCobro = null;

try {
    $sql_metodos = "SELECT method_type, account_email, stripe_email, stripe_account_id, is_primary
                    FROM payment_methods
                    WHERE user_id = ? AND is_verified = 1
                    ORDER BY is_primary DESC";
    
    $stmt_metodos = $conexion->prepare($sql_metodos);
    $stmt_metodos->execute([$vendedor_principal_id]);
    
    while ($metodo = $stmt_metodos->fetch(PDO::FETCH_ASSOC)) {
        $metodos_configurados[$metodo['method_type']] = $metodo;
        if ($metodo['is_primary'] == 1) {
            $metodoCobro = $metodo;
        }
    }
} catch (Exception $e) {
    error_log("Error obteniendo métodos de pago: " . $e->getMessage());
}

if (!$metodoCobro) {
    $metodoCobro = [
        'method_type' => 'no definido',
        'account_email' => '—',
        'stripe_email' => '—',
        'stripe_account_id' => null
    ];
}

$transporter_email = 'No configurado';
if ($metodoCobro && $metodoCobro['method_type'] !== 'no definido') {
    switch ($metodoCobro['method_type']) {
        case 'paypal':
        case 'paypal_bank':
            $transporter_email = $metodoCobro['account_email'] ?? 'No configurado';
            break;
        case 'stripe_connect':
            $transporter_email = $metodoCobro['stripe_email'] ?? 'No configurado';
            break;
    }
}

// Determinar métodos disponibles
$mostrar_stripe = true;
$mostrar_google_pay = true;
$mostrar_apple_pay = true;

// Obtener calificación del vendedor
$seller_rating = 0;
$total_ratings = 0;

try {
    $sql_rating = "SELECT AVG(rating) as avg_rating, COUNT(*) as total_ratings 
                   FROM shop_ratings 
                   WHERE seller_id = ?";
    $stmt_rating = $conexion->prepare($sql_rating);
    $stmt_rating->execute([$vendedor_principal_id]);
    $rating_data = $stmt_rating->fetch(PDO::FETCH_ASSOC);
    $seller_rating = $rating_data['avg_rating'] ? round($rating_data['avg_rating'], 1) : 0;
    $total_ratings = $rating_data['total_ratings'] ?? 0;
} catch (Exception $e) {
    error_log("Error obteniendo rating: " . $e->getMessage());
}

// Obtener imagen del vendedor
$seller_image = 'user-default-nf.png';
try {
    $sql_imagen = "SELECT ruta_imagen FROM accounts WHERE id = ?";
    $stmt_imagen = $conexion->prepare($sql_imagen);
    $stmt_imagen->execute([$vendedor_principal_id]);
    $tiene_imagen = $stmt_imagen->fetchColumn();
    if ($tiene_imagen) {
        $seller_image = "../mostrar_imagen.php?id=" . $vendedor_principal_id;
    }
} catch (Exception $e) {
    error_log("Error obteniendo imagen: " . $e->getMessage());
}

// Definir constantes para JavaScript
$STRIPE_PUBLISHABLE_KEY = defined('STRIPE_PUBLISHABLE_KEY') ? STRIPE_PUBLISHABLE_KEY : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - SendVialo Shop</title>
    <link rel="stylesheet" href="../css/estilos.css">
    <link rel="icon" href="../Imagenes/globo5.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    
    <?php if ($mostrar_google_pay): ?>
    <script async src="https://pay.google.com/gp/p/js/pay.js" onload="onGooglePayLoaded()"></script>
    <?php endif; ?>
    
    <?php if ($mostrar_stripe): ?>
    <script src="https://js.stripe.com/v3/"></script>
    <?php endif; ?>
    
    <?php incluirEstilosInsignias(); ?>
    
    <style>
        :root{
            --primary-color: #42ba25;
            --success-color: #00D924;
            --danger-color: #FF4757;
            --text-primary: #1A1F36;
            --text-secondary: #697386;
            --border-color: #E3E8EE;
            --bg-gray: #F7FAFC;
        }
        
        * { box-sizing: border-box; }
        
        body {
            background: linear-gradient(180deg, #F7FAFC 0%, #FFFFFF 100%);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            color: var(--text-primary);
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }
        
        .checkout-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            width: 100%;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 40px;
            padding: 30px 0;
        }
        
        .page-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            background: linear-gradient(135deg, var(--primary-color) 0%, #00D924 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            padding: 10px 0;
        }
        
        .page-header p {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }
        
        .checkout-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 30px;
            align-items: start;
            width: 100%;
        }
        
        .card-modern {
            background: #fff;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,.04), 0 8px 24px rgba(0,0,0,.06);
            border: 1px solid var(--border-color);
            transition: all .3s cubic-bezier(.4,0,.2,1);
            width: 100%;
            overflow: hidden;
            animation: fadeInUp .6s ease-out backwards;
        }
        
        .card-modern:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,.08), 0 12px 32px rgba(0,0,0,.12);
            transform: translateY(-2px);
        }
        
        .seller-card {
            grid-column: 1 / -1;
            background: #fff !important;
            color: #000 !important;
            margin-bottom: 20px;
        }
        
        .seller-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .profile-wrapper {
            position: relative;
            width: 90px;
            height: 90px;
            flex: 0 0 90px;
        }
        
        .seller-info h2 {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0 0 10px 0;
        }
        
        .rating-display {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 5px;
        }
        
        .rating-display .stars-container {
            display: flex;
            align-items: center;
            gap: 3px;
            color: #ffd700;
        }
        
        .order-summary {
            position: sticky;
            top: 20px;
            width: 100%;
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
        
        .order-items {
            margin-bottom: 20px;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .order-item {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid var(--bg-gray);
        }
        
        .item-image {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
            background: #f0f0f0;
        }
        
        .item-details {
            flex: 1;
        }
        
        .item-name {
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .item-quantity {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        
        .item-price {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .price-breakdown {
            margin: 25px 0;
            padding: 20px 0;
            border-top: 2px solid var(--border-color);
            border-bottom: 2px solid var(--border-color);
        }
        
        .price-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            font-size: 1rem;
        }
        
        .price-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            font-size: 1.3rem;
            font-weight: 700;
        }
        
        .total-amount {
            color: var(--primary-color);
            font-size: 1.8rem;
        }
        
        .payment-section {
            margin-top: 20px;
        }
        
        .payment-section h3 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 25px;
        }
        
        .payment-option {
            background: #fff;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            transition: all .3s ease;
            cursor: pointer;
        }
        
        .payment-option:hover {
            border-color: var(--primary-color);
            box-shadow: 0 4px 16px rgba(66, 186, 37, .15);
        }
        
        .payment-option.active {
            border-color: var(--primary-color);
            box-shadow: 0 4px 20px rgba(66, 186, 37, .2);
        }
        
        .payment-header-row {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .payment-icon-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            flex-shrink: 0;
        }
        
        .payment-icon-circle.card {
            background: linear-gradient(135deg, var(--primary-color) 0%, #00D924 100%);
            color: #fff;
        }
        
        .payment-info-text {
            flex: 1;
        }
        
        .payment-info-text h4 {
            font-size: 1.2rem;
            font-weight: 600;
            margin: 0 0 5px 0;
        }
        
        .expand-icon {
            font-size: 1.5rem;
            color: var(--text-secondary);
            transition: transform .3s ease;
            margin-left: auto;
        }
        
        .payment-option.active .expand-icon {
            transform: rotate(180deg);
        }
        
        .card-form-wrapper {
            max-height: 0;
            overflow: hidden;
            transition: max-height .4s ease, opacity .3s ease;
            opacity: 0;
        }
        
        .card-form-wrapper.expanded {
            max-height: 600px;
            opacity: 1;
            margin-top: 20px;
        }
        
        .card-form-group {
            margin-bottom: 20px;
        }
        
        .card-form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .card-form-group #card-number-element,
        .card-form-group #card-expiry-element,
        .card-form-group #card-cvc-element {
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 14px;
            font-size: 1rem;
            width: 100%;
        }
        
        .card-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .btn-pay {
            background: linear-gradient(135deg, var(--primary-color) 0%, #00D924 100%);
            color: #fff;
            border: none;
            padding: 16px;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 700;
            width: 100%;
            cursor: pointer;
            transition: all .3s ease;
            box-shadow: 0 4px 12px rgba(66, 186, 37, .3);
        }
        
        .btn-pay:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(66, 186, 37, .4);
        }
        
        #card-errors {
            color: var(--danger-color);
            font-size: .9rem;
            margin-top: 10px;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @media (max-width: 992px) {
            .checkout-grid {
                grid-template-columns: 1fr;
            }
            
            .order-summary {
                position: static;
            }
            
            .seller-header {
                flex-direction: column;
                text-align: center;
            }
        }
        
        @media (max-width: 576px) {
            .checkout-container {
                padding: 10px;
            }
            
            .page-header h1 {
                font-size: 1.8rem;
            }
            
            .card-modern {
                padding: 20px;
            }
        }
    </style>
</head>

<body>
    <?php if (file_exists(__DIR__ . '/shop-header.php')) include __DIR__ . '/shop-header.php'; ?>
    
    <div class="checkout-container">
        <div class="page-header">
            <h1>🛒 Confirmación de Compra</h1>
            <p>Finaliza tu pedido de forma segura</p>
        </div>
        
        <div class="checkout-grid">
            <!-- Seller Card -->
            <div class="card-modern seller-card">
                <div class="seller-header">
                    <div class="profile-wrapper">
                        <?php echo mostrarImagenConLaurel($seller_image, $seller_rating, $first_seller['seller_verified']); ?>
                    </div>
                    
                    <div class="seller-info">
                        <h2><?php echo htmlspecialchars($vendedor_principal_username); ?></h2>
                        <div class="rating-display">
                            <?php if ($seller_rating > 0): ?>
                                <span style="font-size: 1.2rem; font-weight: 700;"><?php echo $seller_rating; ?></span>
                                <div class="stars-container">
                                    <?php
                                    $fullStars = floor($seller_rating);
                                    $hasHalfStar = ($seller_rating - $fullStars) >= 0.5;
                                    $emptyStars = 5 - $fullStars - ($hasHalfStar ? 1 : 0);
                                    
                                    for ($i = 0; $i < $fullStars; $i++) echo '<i class="bi bi-star-fill"></i>';
                                    if ($hasHalfStar) echo '<i class="bi bi-star-half"></i>';
                                    for ($i = 0; $i < $emptyStars; $i++) echo '<i class="bi bi-star"></i>';
                                    ?>
                                    <span style="color: #666; font-size: 0.9rem; margin-left: 5px;">
                                        (<?php echo $total_ratings; ?>)
                                    </span>
                                </div>
                            <?php else: ?>
                                <span>Sin valoraciones</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <i class="bi bi-shield-check"></i>
                    <strong>Compra protegida:</strong> Tu dinero estará en custodia hasta que confirmes la recepción de los productos.
                </div>
            </div>
            
            <!-- Order Summary -->
            <div class="order-summary">
                <div class="card-modern">
                    <div class="summary-header">
                        <i class="bi bi-bag-check-fill" style="color: var(--primary-color); font-size: 1.5rem;"></i>
                        <h3>Resumen del Pedido</h3>
                    </div>
                    
                    <div class="order-items">
                        <?php foreach ($first_seller['items'] as $item): ?>
                            <div class="order-item">
                                <?php if ($item['image']): ?>
                                    <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="" class="item-image">
                                <?php else: ?>
                                    <div class="item-image" style="display: flex; align-items: center; justify-content: center;">
                                        <i class="bi bi-box" style="font-size: 1.5rem; color: #999;"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="item-details">
                                    <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                    <div class="item-quantity">Cantidad: <?php echo $item['quantity']; ?></div>
                                    <div class="item-price">
                                        <?php 
                                        $symbols = ['EUR' => '€', 'USD' => '$', 'BOB' => 'Bs', 'GBP' => '£'];
                                        $symbol = $symbols[$item['currency']] ?? '€';
                                        echo $symbol . number_format($item['price'] * $item['quantity'], 2); 
                                        ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="price-breakdown">
                        <div class="price-row">
                            <span>Subtotal productos</span>
                            <span><?php 
                                $symbols = ['EUR' => '€', 'USD' => '$', 'BOB' => 'Bs', 'GBP' => '£'];
                                $symbol = $symbols[$moneda_viaje] ?? '€';
                                echo $symbol . number_format($subtotal, 2); 
                            ?></span>
                        </div>
                        <div class="price-row">
                            <span>Comisión SendVialo (20%)</span>
                            <span><?php echo $symbol . number_format($comisionSendVialo, 2); ?></span>
                        </div>
                    </div>
                    
                    <div class="price-total">
                        <span>Total</span>
                        <span class="total-amount" id="total-display">
                            <?php echo $symbol . number_format($totalAPagar, 2); ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Payment Methods -->
            <div class="payment-section">
                <h3><i class="bi bi-credit-card me-2"></i>Métodos de Pago</h3>
                
                <?php if ($mostrar_stripe): ?>
                <div class="payment-option" id="card-payment-option">
                    <div class="payment-header-row" onclick="toggleCardForm()">
                        <div class="payment-icon-circle card">
                            <i class="bi bi-credit-card-fill"></i>
                        </div>
                        <div class="payment-info-text">
                            <h4>Tarjeta de Crédito/Débito</h4>
                            <div style="display: flex; gap: 10px; margin-top: 5px;">
                                <img src="https://upload.wikimedia.org/wikipedia/commons/5/5e/Visa_Inc._logo.svg" alt="Visa" height="20">
                                <img src="https://upload.wikimedia.org/wikipedia/commons/2/2a/Mastercard-logo.svg" alt="Mastercard" height="20">
                            </div>
                        </div>
                        <i class="bi bi-chevron-down expand-icon"></i>
                    </div>
                    
                    <div class="card-form-wrapper" id="card-form-wrapper">
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
                        
                        <button id="card-button" class="btn-pay">
                            <i class="bi bi-lock-fill me-2"></i>Pagar <?php echo $symbol . number_format($totalAPagar, 2); ?>
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php if (file_exists(__DIR__ . '/footer1.php')) include __DIR__ . '/footer1.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        const comprador_id = <?php echo json_encode($comprador_id); ?>;
        const comprador_username = <?php echo json_encode($comprador_username); ?>;
        const vendedor_id = <?php echo json_encode($vendedor_principal_id); ?>;
        const vendedor_username = <?php echo json_encode($vendedor_principal_username); ?>;
        const subtotal = <?php echo $subtotal; ?>;
        const comisionSendVialo = <?php echo $comisionSendVialo; ?>;
        const totalAPagar = <?php echo $totalAPagar; ?>;
        const moneda_viaje = <?php echo json_encode($moneda_viaje); ?>;
        const cart_items = <?php echo json_encode($first_seller['items']); ?>;
        const transporter_email = <?php echo json_encode($transporter_email); ?>;
        const transportista_has_stripe_connect = <?php echo json_encode(isset($metodos_configurados['stripe_connect'])); ?>;
        const transporter_stripe_connect_id = <?php echo json_encode($metodos_configurados['stripe_connect']['stripe_account_id'] ?? null); ?>;
        
        function toggleCardForm() {
            document.getElementById('card-payment-option').classList.toggle('active');
            document.getElementById('card-form-wrapper').classList.toggle('expanded');
        }
    </script>
    
    <?php if ($mostrar_stripe && !empty($STRIPE_PUBLISHABLE_KEY)): ?>
    <script>
    (function() {
        'use strict';
        
        const stripe = Stripe('<?php echo htmlspecialchars($STRIPE_PUBLISHABLE_KEY); ?>');
        const elements = stripe.elements();
        
        const style = {
            base: {
                color: '#32325d',
                fontFamily: '"Segoe UI", sans-serif',
                fontSize: '16px',
                '::placeholder': { color: '#aab7c4' }
            },
            invalid: { color: '#fa755a' }
        };
        
        const cardNumber = elements.create('cardNumber', {style});
        const cardExpiry = elements.create('cardExpiry', {style});
        const cardCvc = elements.create('cardCvc', {style});
        
        cardNumber.mount('#card-number-element');
        cardExpiry.mount('#card-expiry-element');
        cardCvc.mount('#card-cvc-element');
        
        [cardNumber, cardExpiry, cardCvc].forEach(element => {
            element.on('change', function(event) {
                const displayError = document.getElementById('card-errors');
                if (event.error) {
                    displayError.innerHTML = `<i class="bi bi-exclamation-triangle me-2"></i>${event.error.message}`;
                } else {
                    displayError.textContent = '';
                }
            });
        });
        
        document.getElementById('card-button').addEventListener('click', function(e) {
            e.preventDefault();
            procesarPago();
        });
        
        function procesarPago() {
            const button = document.getElementById('card-button');
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Procesando...';
            
            Swal.fire({
                title: 'Procesando pago...',
                html: '<div class="spinner-border text-success"></div>',
                allowOutsideClick: false,
                showConfirmButton: false
            });
            
            stripe.createToken(cardNumber).then(function(result) {
                if (result.error) {
                    Swal.close();
                    document.getElementById('card-errors').innerHTML = 
                        `<i class="bi bi-exclamation-triangle me-2"></i>${result.error.message}`;
                    button.disabled = false;
                    button.innerHTML = '<i class="bi bi-lock-fill me-2"></i>Pagar';
                } else {
                    enviarPagoAlServidor(result.token.id);
                }
            });
        }
        
        function enviarPagoAlServidor(tokenId) {
            const payload = {
                comprador_id: comprador_id,
                comprador_username: comprador_username,
                vendedor_id: vendedor_id,
                vendedor_username: vendedor_username,
                items: cart_items,
                subtotal: subtotal,
                comision: comisionSendVialo,
                total: totalAPagar,
                moneda: moneda_viaje,
                payment_token: tokenId,
                payment_method: 'stripe',
                codigo_unico: 'SHOP_' + Date.now()
            };
            
            fetch('shop-process-payment.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Pago Exitoso!',
                        text: 'Tu pedido ha sido procesado correctamente',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = 'shop-order-confirmation.php?order_id=' + data.order_id;
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error en el pago',
                        text: data.message
                    });
                    document.getElementById('card-button').disabled = false;
                    document.getElementById('card-button').innerHTML = '<i class="bi bi-lock-fill me-2"></i>Pagar';
                }
            })
            .catch(err => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error al procesar el pago'
                });
                document.getElementById('card-button').disabled = false;
                document.getElementById('card-button').innerHTML = '<i class="bi bi-lock-fill me-2"></i>Pagar';
            });
        }
    })();
    </script>
    <?php endif; ?>
</body>
</html>