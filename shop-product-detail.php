<?php
// shop-product-detail.php - Página de detalles del producto estilo Amazon/Temu
session_start();

// Incluir sistema de insignias unificado
require_once 'insignias1.php';
require_once '../config.php';

// Obtener ID del producto
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($product_id <= 0) {
    header('Location: index.php');
    exit;
}

// Variables de usuario
$user_logged_in = isset($_SESSION['usuario_id']);
$user_id = $user_logged_in ? $_SESSION['usuario_id'] : null;

// Obtener producto con todos los detalles
try {
    $sql = "SELECT 
                sp.*,
                a.full_name as seller_name,
                a.username as seller_username,
                a.verificado as seller_verified,
                a.ruta_imagen as seller_avatar,
                t.search_input as origin_country,
                t.destination_input as destination_city,
                t.datepicker as fecha_viaje
            FROM shop_products sp
            LEFT JOIN accounts a ON sp.seller_id = a.id
            LEFT JOIN transporting t ON sp.trip_id = t.id
            WHERE sp.id = ? AND sp.active = 1";
    
    $stmt = $conexion->prepare($sql);
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        header('Location: index.php');
        exit;
    }
    
    // Obtener valoraciones del vendedor
    $rating_sql = "SELECT 
                    AVG(valoracion) as promedio_valoracion,
                    COUNT(*) as total_valoraciones
                   FROM comentarios 
                   WHERE usuario_id = ? AND bloqueado = 0";
    $rating_stmt = $conexion->prepare($rating_sql);
    $rating_stmt->execute([$product['seller_id']]);
    $rating_data = $rating_stmt->fetch(PDO::FETCH_ASSOC);
    
    $seller_rating = $rating_data['promedio_valoracion'] ? round($rating_data['promedio_valoracion'], 1) : 0;
    $total_ratings = $rating_data['total_valoraciones'] ?: 0;
    
    // Obtener imágenes del producto
    $img_stmt = $conexion->prepare("SELECT image_path FROM shop_product_images WHERE product_id = ? ORDER BY is_primary DESC, id ASC");
    $img_stmt->execute([$product_id]);
    $images = $img_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Obtener productos relacionados del mismo vendedor
    $related_sql = "SELECT id, name, price, currency, 
                           (SELECT image_path FROM shop_product_images spi WHERE spi.product_id = sp.id LIMIT 1) as primary_image
                    FROM shop_products sp 
                    WHERE seller_id = ? AND id != ? AND active = 1 
                    ORDER BY created_at DESC LIMIT 6";
    $related_stmt = $conexion->prepare($related_sql);
    $related_stmt->execute([$product['seller_id'], $product_id]);
    $related_products = $related_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener productos similares (misma categoría)
    $similar_sql = "SELECT id, name, price, currency, seller_id,
                           (SELECT image_path FROM shop_product_images spi WHERE spi.product_id = sp.id LIMIT 1) as primary_image
                    FROM shop_products sp 
                    WHERE category = ? AND id != ? AND active = 1 
                    ORDER BY RAND() LIMIT 8";
    $similar_stmt = $conexion->prepare($similar_sql);
    $similar_stmt->execute([$product['category'], $product_id]);
    $similar_products = $similar_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    header('Location: index.php');
    exit;
}

// Función para determinar tipo de insignia
function getBadgeType($rating) {
    if ($rating >= 4.8) return 'diamond';
    if ($rating >= 4.5) return 'gold';
    if ($rating >= 4.0) return 'silver';
    if ($rating >= 3.5) return 'bronze';
    return 'basic';
}

$badge_type = getBadgeType($seller_rating);

// Verificar si el usuario actual es el vendedor
$is_seller = $user_logged_in && $user_id == $product['seller_id'];

// OBTENER OFERTAS RECIBIDAS (si el usuario es el vendedor)
$offers_received = [];
$offers_count = 0;
if ($is_seller) {
    try {
        $offers_sql = "SELECT
                        o.*,
                        COALESCE(buyer.full_name, buyer.username, 'Comprador') as buyer_name,
                        buyer.username as buyer_username,
                        buyer.verificado as buyer_verified,
                        (SELECT COUNT(*) FROM shop_product_price_negotiation
                         WHERE offer_id = o.id) as negotiations_count,
                        (SELECT AVG(valoracion) FROM comentarios
                         WHERE usuario_id = o.buyer_id AND bloqueado = 0) as buyer_rating
                       FROM shop_product_offers o
                       LEFT JOIN accounts buyer ON o.buyer_id = buyer.id
                       WHERE o.product_id = :product_id AND o.status = 'pending'
                       ORDER BY o.created_at DESC";

        $offers_stmt = $conexion->prepare($offers_sql);
        $offers_stmt->execute([':product_id' => $product_id]);
        $offers_received = $offers_stmt->fetchAll(PDO::FETCH_ASSOC);
        $offers_count = count($offers_received);
    } catch (PDOException $e) {
        error_log("Error obteniendo ofertas: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?> - SendVialo Shop</title>
    <link rel="stylesheet" href="shop-profile-fix.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.3.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="icon" href="../Imagenes/globo5.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <?php incluirEstilosInsignias(); ?>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f8f9fa;
        }

        /* BREADCRUMB */
        .breadcrumb {
            background: white;
            padding: 15px 0;
            border-bottom: 1px solid #e9ecef;
        }

        .breadcrumb-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .breadcrumb-nav {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
            color: #666;
        }

        .breadcrumb-nav a {
            color: #4CAF50;
            text-decoration: none;
        }

        .breadcrumb-nav a:hover {
            text-decoration: underline;
        }

        /* LAYOUT PRINCIPAL */
        .product-detail-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .product-main {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px;
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            margin-bottom: 40px;
        }

        /* GALERÍA DE IMÁGENES */
        .product-gallery {
            position: relative;
        }

        .main-image {
            width: 100%;
            height: 500px;
            object-fit: cover;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .image-thumbnails {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding: 5px 0;
        }

        .thumbnail {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            border: 3px solid transparent;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }

        .thumbnail.active,
        .thumbnail:hover {
            border-color: #4CAF50;
        }

        /* INFORMACIÓN DEL PRODUCTO */
        .product-info {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .product-title {
            font-size: 2.2rem;
            font-weight: 700;
            color: #333;
            line-height: 1.3;
        }

        .product-price {
            font-size: 2.5rem;
            font-weight: 700;
            color: #4CAF50;
        }

        .stock-info {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
        }

        .stock-available {
            color: #28a745;
        }

        .stock-low {
            color: #ffc107;
        }

        .stock-out {
            color: #dc3545;
        }

        .route-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            border-left: 4px solid #4CAF50;
        }

        .route-info h4 {
            margin-bottom: 10px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* INFORMACIÓN DEL VENDEDOR */
        .seller-info {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
            border: 2px solid #e9ecef;
        }

        .seller-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }

        .seller-avatar .profile-image-laurel img {
            width: 80px;
            height: 80px;
        }

        .seller-avatar .laurel-svg {
            width: 112px;
            height: 112px;
        }

        .seller-details h3 {
            font-size: 1.3rem;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .verified-icon {
            color: #4CAF50;
        }

        .seller-rating {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 5px;
        }

        .rating-stars {
            color: #ffd700;
            font-size: 16px;
        }

        .seller-actions {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        /* ACCIONES DE COMPRA */
        .purchase-actions {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
            border: 2px solid #4CAF50;
        }

        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .qty-controls {
            display: flex;
            align-items: center;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            overflow: hidden;
        }

        .qty-btn {
            background: #f8f9fa;
            border: none;
            padding: 10px 15px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .qty-btn:hover {
            background: #e9ecef;
        }

        .qty-input {
            border: none;
            padding: 10px 8px;
            text-align: center;
            font-size: 16px;
            font-weight: 600;
            width: 70px;
            background: white;
            -moz-appearance: textfield;
        }
        
        .qty-input::-webkit-outer-spin-button,
        .qty-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4CAF50, #66BB6A);
            color: white;
            box-shadow: 0 6px 20px rgba(76, 175, 80, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(76, 175, 80, 0.4);
            color: white;
        }

        .btn-secondary {
            background: #fff;
            color: #4CAF50;
            border: 2px solid #4CAF50;
        }

        .btn-secondary:hover {
            background: #4CAF50;
            color: white;
        }

        .btn-warning {
            background: linear-gradient(135deg, #FF9800, #FB8C00);
            color: white;
        }

        .btn-warning:hover {
            transform: translateY(-2px);
        }

        /* DESCRIPCIÓN Y DETALLES */
        .product-details {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            margin-bottom: 40px;
        }

        .details-tabs {
            display: flex;
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 30px;
        }

        .tab-btn {
            padding: 15px 25px;
            background: none;
            border: none;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }

        .tab-btn.active {
            color: #4CAF50;
            border-bottom-color: #4CAF50;
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .tab-content.active {
            display: block;
        }

        /* ESTILOS PARA OFERTAS RECIBIDAS */
        .offers-header {
            margin-bottom: 30px;
        }

        .offer-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 20px;
            border: 3px solid #e9ecef;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .offer-card:hover {
            border-color: #FF9800;
            box-shadow: 0 8px 30px rgba(255, 152, 0, 0.15);
            transform: translateY(-3px);
        }

        .offer-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .offer-price {
            text-align: right;
        }

        .offer-message {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #FF9800;
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }

        .offer-message i {
            color: #FF9800;
            margin-top: 3px;
        }

        .offer-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .offer-actions button {
            flex: 1;
            min-width: 140px;
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-offer-counteroffer {
            background: #FF9800;
            color: white;
        }

        .btn-offer-counteroffer:hover {
            background: #FB8C00;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 152, 0, 0.3);
        }

        .btn-offer-view-history {
            background: #2196F3;
            color: white;
        }

        .btn-offer-view-history:hover {
            background: #1E88E5;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(33, 150, 243, 0.3);
        }

        .btn-offer-accept {
            background: #4CAF50;
            color: white;
        }

        .btn-offer-accept:hover {
            background: #66BB6A;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76, 175, 80, 0.3);
        }

        .btn-offer-reject {
            background: transparent;
            color: #666;
            border: 2px solid #ddd;
        }

        .btn-offer-reject:hover {
            border-color: #f44336;
            color: #f44336;
            background: rgba(244, 67, 54, 0.05);
        }

        .specifications {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .spec-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
        }

        .spec-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .spec-value {
            color: #666;
        }

        /* PRODUCTOS RELACIONADOS */
        .related-section {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 30px;
            color: #333;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .product-card-mini {
            background: #f8f9fa;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid transparent;
        }

        .product-card-mini:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.12);
            border-color: #4CAF50;
        }

        .product-card-mini img {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }

        .product-card-mini .info {
            padding: 15px;
        }

        .product-card-mini .name {
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
            font-size: 0.95rem;
            line-height: 1.3;
        }

        .product-card-mini .price {
            color: #4CAF50;
            font-weight: 700;
            font-size: 1.1rem;
        }

        /* MODAL DE IMÁGENES */
        .image-modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.9);
            backdrop-filter: blur(5px);
        }

        .modal-content-image {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            max-width: 90%;
            max-height: 90%;
            border-radius: 10px;
        }

        .close-modal {
            position: absolute;
            top: 30px;
            right: 50px;
            color: white;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
            z-index: 10001;
        }

        .close-modal:hover {
            color: #4CAF50;
        }

        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .product-main {
                grid-template-columns: 1fr;
                gap: 30px;
                padding: 30px 25px;
            }

            .main-image {
                height: 400px;
            }
        }

        @media (max-width: 768px) {
            .product-detail-container {
                padding: 20px 15px;
            }

            .product-main {
                padding: 25px 20px;
            }

            .product-title {
                font-size: 1.8rem;
            }

            .product-price {
                font-size: 2rem;
            }

            .seller-avatar .profile-image-laurel img {
                width: 60px;
                height: 60px;
            }

            .seller-avatar .laurel-svg {
                width: 84px;
                height: 84px;
            }

            .products-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }

            .action-buttons {
                position: sticky;
                bottom: 20px;
                background: white;
                padding: 20px;
                margin: 0 -20px -25px -20px;
                border-radius: 15px 15px 0 0;
                box-shadow: 0 -5px 20px rgba(0,0,0,0.1);
            }
        }

        @media (max-width: 480px) {
            .products-grid {
                grid-template-columns: 1fr;
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* FIX FOTOS CIRCULARES */
        .profile-image-laurel,
        .seller-avatar .profile-image-laurel {
            position: relative !important;
            display: inline-block !important;
            line-height: 0 !important;
            overflow: hidden !important;
            border-radius: 50% !important;
        }

        .profile-image-laurel img,
        .seller-avatar .profile-image-laurel img,
        img[src*="user-default.jpg"],
        img[src*="mostrar_imagen.php"],
        img[alt="Perfil"] {
            object-fit: cover !important;
            border-radius: 50% !important;
            display: block !important;
            width: 80px !important;
            height: 80px !important;
            max-width: 80px !important;
            max-height: 80px !important;
            border: none !important;
        }

        @media (max-width: 768px) {
            .profile-image-laurel img {
                width: 60px !important;
                height: 60px !important;
                max-width: 60px !important;
                max-height: 60px !important;
            }
        }

        /* Estilos del modal de ofertas */
        .cart-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,.6);
            z-index: 10000;
            backdrop-filter: blur(8px);
        }

        .cart-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #fff;
            border-radius: 20px;
            width: 90%;
            max-width: 550px;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px rgba(0,0,0,.25);
        }

        .cart-header {
            padding: 30px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #FF9800, #FB8C00);
            border-radius: 20px 20px 0 0;
        }

        .cart-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            color: white;
        }

        .close-cart {
            background: none;
            border: none;
            font-size: 1.8rem;
            cursor: pointer;
            color: white;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all .3s ease;
        }

        .close-cart:hover {
            background: rgba(255,255,255,.2);
        }
    </style>
</head>
<body data-user-logged="<?= $user_logged_in ? 'true' : 'false' ?>" 
      data-product-id="<?= $product_id ?>"
      data-is-seller="<?= $is_seller ? 'true' : 'false' ?>"
      <?php if ($user_logged_in): ?>
      data-user-id="<?= $user_id ?>" 
      <?php endif; ?>>
      
    <?php if (file_exists('header1.php')) include 'header1.php'; ?>

    <!-- BREADCRUMB -->
    <div class="breadcrumb">
        <div class="breadcrumb-container">
            <nav class="breadcrumb-nav">
                <a href="../index.php"><i class="fas fa-home"></i> Inicio</a>
                <i class="fas fa-chevron-right"></i>
                <a href="index.php">Shop</a>
                <i class="fas fa-chevron-right"></i>
                <span><?= htmlspecialchars($product['name']) ?></span>
            </nav>
        </div>
    </div>

    <!-- Botón Volver al Shop -->
    <div style="max-width: 1400px; margin: 20px auto; padding: 0 20px;">
        <a href="index.php" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 8px;">
            <i class="fas fa-arrow-left"></i>
            Volver al Shop
        </a>
    </div>

    <div class="product-detail-container">
        <!-- INFORMACIÓN PRINCIPAL DEL PRODUCTO -->
        <div class="product-main">
            <!-- GALERÍA DE IMÁGENES -->
            <div class="product-gallery">
                <?php 
                $main_image = !empty($images) ? $images[0] : 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=600&h=600&fit=crop';
                ?>
                <img src="<?= htmlspecialchars($main_image) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="main-image" id="mainImage" onclick="openImageModal(this.src)">
                
                <?php if (count($images) > 1): ?>
                <div class="image-thumbnails">
                    <?php foreach ($images as $index => $image): ?>
                    <img src="<?= htmlspecialchars($image) ?>" 
                         alt="Imagen <?= $index + 1 ?>" 
                         class="thumbnail <?= $index === 0 ? 'active' : '' ?>" 
                         onclick="changeMainImage(this)">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- INFORMACIÓN DEL PRODUCTO -->
            <div class="product-info">
                <h1 class="product-title"><?= htmlspecialchars($product['name']) ?></h1>
                
                <div class="product-price">
                    <?= $product['currency'] === 'EUR' ? '€' : ($product['currency'] === 'USD' ? '$' : 'Bs') ?><?= number_format($product['price'], 2) ?>
                </div>

                <!-- INFORMACIÓN DE STOCK -->
                <div class="stock-info">
                    <i class="fas fa-box"></i>
                    <?php if ($product['stock'] > 10): ?>
                        <span class="stock-available">En stock (<?= $product['stock'] ?> disponibles)</span>
                    <?php elseif ($product['stock'] > 0): ?>
                        <span class="stock-low">¡Solo quedan <?= $product['stock'] ?>!</span>
                    <?php else: ?>
                        <span class="stock-out">Agotado</span>
                    <?php endif; ?>
                </div>

                <!-- INFORMACIÓN DE RUTA -->
                <?php if ($product['origin_country'] && $product['destination_city']): ?>
                <div class="route-info">
                    <h4><i class="fas fa-route"></i> Información del Viaje</h4>
                    <p><strong>Origen:</strong> <?= htmlspecialchars($product['origin_country']) ?></p>
                    <p><strong>Destino:</strong> <?= htmlspecialchars($product['destination_city']) ?></p>
                    <?php if ($product['fecha_viaje']): ?>
                    <p><strong>Fecha:</strong> <?= date('d/m/Y', strtotime($product['fecha_viaje'])) ?></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- INFORMACIÓN DEL VENDEDOR -->
                <div class="seller-info">
                    <div class="seller-header">
                        <div class="seller-avatar">
                            <?php 
                            $seller_avatar = !empty($product['seller_avatar']) ? 
                                "mostrar_imagen.php?id=" . $product['seller_id'] : 
                                "https://ui-avatars.com/api/?name=" . urlencode($product['seller_name']) . "&background=667eea&color=fff&size=80";
                            
                            echo mostrarImagenConLaurelShop(
                                $seller_avatar, 
                                $seller_rating, 
                                $product['seller_verified'] ?? false, 
                                80
                            );
                            ?>
                        </div>
                        <div class="seller-details">
                            <h3>
                                <?= htmlspecialchars($product['seller_name']) ?>
                                <?php if ($product['seller_verified']): ?>
                                <i class="fas fa-check-circle verified-icon" title="Usuario verificado"></i>
                                <?php endif; ?>
                            </h3>
                            <div class="seller-rating">
                                <?php if ($seller_rating > 0): ?>
                                    <span class="rating-stars"><?= str_repeat('★', floor($seller_rating)) ?><?= str_repeat('☆', 5 - floor($seller_rating)) ?></span>
                                    <span class="<?= $badge_type ?>-text"><?= $seller_rating ?></span>
                                    <span>(<?= $total_ratings ?> valoraciones)</span>
                                <?php else: ?>
                                    <span class="no-rating">Sin valoraciones aún</span>
                                <?php endif; ?>
                            </div>
                            <div class="seller-actions">
                                <a href="shop-seller-profile.php?id=<?= $product['seller_id'] ?>" class="btn btn-secondary" style="padding:10px 20px;font-size:0.95rem;">
                                    <i class="fas fa-user"></i> Ver Perfil
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ACCIONES DE COMPRA -->
                <div class="purchase-actions">
                    <?php if (!$is_seller && $product['stock'] > 0): ?>
                    <div class="quantity-selector">
                        <label><strong>Cantidad:</strong></label>
                        <div class="qty-controls">
                            <button type="button" class="qty-btn" id="qtyMinus">-</button>
                            <input type="number" class="qty-input" id="quantity" value="1" min="1" max="<?= $product['stock'] ?>">
                            <button type="button" class="qty-btn" id="qtyPlus">+</button>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="action-buttons">
                        <?php if ($is_seller): ?>
                            <button class="btn btn-secondary" disabled style="opacity:0.6; cursor:not-allowed;">
                                <i class="fas fa-store"></i>
                                Este es tu producto
                            </button>
                        <?php elseif ($product['stock'] > 0): ?>
                            <?php if ($user_logged_in): ?>
                            <button type="button" class="btn btn-warning" onclick="openOfferModal()">
                                <i class="fas fa-hand-holding-usd"></i>
                                Hacer Oferta
                            </button>
                            <?php endif; ?>
                            
                            <button type="button" class="btn btn-primary" id="addToCartBtn">
                                <i class="fas fa-cart-plus"></i>
                                Añadir al Carrito
                            </button>
                            
                            <button type="button" class="btn btn-secondary" id="buyNowBtn">
                                <i class="fas fa-bolt"></i>
                                Comprar Ahora
                            </button>
                        <?php else: ?>
                            <button class="btn btn-primary" disabled>
                                <i class="fas fa-times"></i>
                                Producto Agotado
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- DETALLES Y DESCRIPCIÓN -->
        <div class="product-details">
            <div class="details-tabs">
                <button class="tab-btn active" onclick="openTab(event, 'description')">Descripción</button>
                <button class="tab-btn" onclick="openTab(event, 'specifications')">Especificaciones</button>
                <?php if ($product['weight'] || $product['dimensions']): ?>
                <button class="tab-btn" onclick="openTab(event, 'shipping')">Envío</button>
                <?php endif; ?>
                <?php if ($is_seller && $offers_count > 0): ?>
                <button class="tab-btn" onclick="openTab(event, 'offers')">
                    Ofertas Recibidas
                    <span class="badge"><?= $offers_count ?></span>
                </button>
                <?php endif; ?>
            </div>

            <div id="description" class="tab-content active">
                <p><?= nl2br(htmlspecialchars($product['description'])) ?></p>
            </div>

            <div id="specifications" class="tab-content">
                <div class="specifications">
                    <div class="spec-item">
                        <div class="spec-label">Categoría</div>
                        <div class="spec-value"><?= ucfirst($product['category']) ?></div>
                    </div>
                    <div class="spec-item">
                        <div class="spec-label">Moneda Original</div>
                        <div class="spec-value"><?= $product['currency'] ?></div>
                    </div>
                    <div class="spec-item">
                        <div class="spec-label">Stock Disponible</div>
                        <div class="spec-value"><?= $product['stock'] ?> unidades</div>
                    </div>
                    <?php if ($product['weight']): ?>
                    <div class="spec-item">
                        <div class="spec-label">Peso Aproximado</div>
                        <div class="spec-value"><?= $product['weight'] ?> kg</div>
                    </div>
                    <?php endif; ?>
                    <?php if ($product['dimensions']): ?>
                    <div class="spec-item">
                        <div class="spec-label">Dimensiones</div>
                        <div class="spec-value"><?= htmlspecialchars($product['dimensions']) ?> cm</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($product['weight'] || $product['dimensions']): ?>
            <div id="shipping" class="tab-content">
                <h3>Información de Envío</h3>
                <p>Este producto será transportado por el viajero en su equipaje personal.</p>
                <?php if ($product['weight']): ?>
                <p><strong>Peso:</strong> <?= $product['weight'] ?> kg aproximadamente</p>
                <?php endif; ?>
                <?php if ($product['dimensions']): ?>
                <p><strong>Dimensiones:</strong> <?= htmlspecialchars($product['dimensions']) ?> cm</p>
                <?php endif; ?>
                <p><strong>Tiempo de entrega:</strong> Según la fecha del viaje programado</p>
            </div>
            <?php endif; ?>

            <?php if ($is_seller && $offers_count > 0): ?>
            <div id="offers" class="tab-content">
                <div class="offers-header">
                    <h3 style="font-size: 1.5rem; color: #333; margin-bottom: 20px;">
                        <i class="fas fa-hand-holding-usd" style="color: #FF9800;"></i>
                        Ofertas Recibidas (<?= $offers_count ?>)
                    </h3>
                    <p style="color: #666; margin-bottom: 30px;">Estas son las ofertas que los compradores han hecho por tu producto.</p>
                </div>

                <?php foreach ($offers_received as $offer):
                    $buyer_badge = getBadgeType($offer['buyer_rating'] ?? 0);
                ?>
                <div class="offer-card" data-offer-id="<?= $offer['id'] ?>">
                    <div class="offer-header">
                        <div style="display: flex; align-items: center; gap: 15px; flex: 1;">
                            <?php
                            $avatar_class = getAvatarClass($buyer_badge);
                            ?>
                            <div class="<?= $avatar_class ?>">
                                <img src="../mostrar_imagen.php?id=<?= $offer['buyer_id'] ?>"
                                     alt="<?= htmlspecialchars($offer['buyer_name']) ?>"
                                     onerror="this.src='../Imagenes/default-avatar.png'">
                            </div>
                            <div style="flex: 1;">
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
                                    <h4 style="margin: 0; font-size: 1.2rem; color: #333;">
                                        <?= htmlspecialchars($offer['buyer_name']) ?>
                                    </h4>
                                    <?php if ($offer['buyer_verified']): ?>
                                    <i class="fas fa-check-circle" style="color: #4CAF50;" title="Verificado"></i>
                                    <?php endif; ?>
                                </div>
                                <div style="display: flex; align-items: center; gap: 15px; color: #666; font-size: 0.9rem;">
                                    <span>
                                        <i class="fas fa-star" style="color: #FFD700;"></i>
                                        <?= number_format($offer['buyer_rating'] ?? 0, 1) ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-calendar"></i>
                                        <?= date('d/m/Y H:i', strtotime($offer['created_at'])) ?>
                                    </span>
                                    <?php if ($offer['negotiations_count'] > 0): ?>
                                    <span style="background: #FF9800; color: white; padding: 3px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: 600;">
                                        <i class="fas fa-comments"></i> <?= $offer['negotiations_count'] ?> negociaciones
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="offer-price">
                            <div style="font-size: 2rem; font-weight: 700; color: #FF9800;">
                                <?= number_format($offer['offered_price'], 2) ?> <?= $offer['offered_currency'] ?>
                            </div>
                            <div style="font-size: 0.9rem; color: #666; text-align: right;">
                                Cantidad: <?= $offer['quantity'] ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($offer['message']): ?>
                    <div class="offer-message">
                        <i class="fas fa-comment-dots"></i>
                        <span><?= nl2br(htmlspecialchars($offer['message'])) ?></span>
                    </div>
                    <?php endif; ?>

                    <div class="offer-actions">
                        <button type="button" class="btn-offer-counteroffer" onclick="openCounterofferModal(<?= $offer['id'] ?>, <?= $offer['offered_price'] ?>, '<?= $offer['offered_currency'] ?>')">
                            <i class="fas fa-exchange-alt"></i>
                            Contraoferta
                        </button>
                        <button type="button" class="btn-offer-view-history" onclick="viewNegotiationHistory(<?= $offer['id'] ?>)">
                            <i class="fas fa-history"></i>
                            Ver Historial
                        </button>
                        <button type="button" class="btn-offer-accept" onclick="acceptOffer(<?= $offer['id'] ?>)">
                            <i class="fas fa-check"></i>
                            Aceptar
                        </button>
                        <button type="button" class="btn-offer-reject" onclick="rejectOffer(<?= $offer['id'] ?>)">
                            <i class="fas fa-times"></i>
                            Rechazar
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- PRODUCTOS RELACIONADOS DEL MISMO VENDEDOR -->
        <?php if (!empty($related_products)): ?>
        <div class="related-section">
            <h2 class="section-title">Más productos de <?= htmlspecialchars($product['seller_name']) ?></h2>
            <div class="products-grid">
                <?php foreach ($related_products as $related): ?>
                <div class="product-card-mini" onclick="window.location.href='shop-product-detail.php?id=<?= $related['id'] ?>'">
                    <img src="<?= htmlspecialchars($related['primary_image'] ?: 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=300&h=300&fit=crop') ?>" alt="<?= htmlspecialchars($related['name']) ?>">
                    <div class="info">
                        <div class="name"><?= htmlspecialchars($related['name']) ?></div>
                        <div class="price"><?= $related['currency'] === 'EUR' ? '€' : ($related['currency'] === 'USD' ? '$' : 'Bs') ?><?= number_format($related['price'], 2) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- PRODUCTOS SIMILARES -->
        <?php if (!empty($similar_products)): ?>
        <div class="related-section">
            <h2 class="section-title">Productos Similares</h2>
            <div class="products-grid">
                <?php foreach ($similar_products as $similar): ?>
                <div class="product-card-mini" onclick="window.location.href='shop-product-detail.php?id=<?= $similar['id'] ?>'">
                    <img src="<?= htmlspecialchars($similar['primary_image'] ?: 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=300&h=300&fit=crop') ?>" alt="<?= htmlspecialchars($similar['name']) ?>">
                    <div class="info">
                        <div class="name"><?= htmlspecialchars($similar['name']) ?></div>
                        <div class="price"><?= $similar['currency'] === 'EUR' ? '€' : ($similar['currency'] === 'USD' ? '$' : 'Bs') ?><?= number_format($similar['price'], 2) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- MODAL DE IMAGEN -->
    <div id="imageModal" class="image-modal">
        <span class="close-modal" onclick="closeImageModal()">&times;</span>
        <img class="modal-content-image" id="modalImage">
    </div>

    <!-- Modal de Hacer Oferta -->
    <div class="cart-modal" id="offer-modal" style="display: none;">
        <div class="cart-content" style="max-width: 600px;">
            <div class="cart-header">
                <h3 class="cart-title">
                    <i class="fas fa-hand-holding-usd"></i> Hacer Oferta
                </h3>
                <button class="close-cart" onclick="closeOfferModal()">×</button>
            </div>
            <form id="offer-form">
                <div style="padding: 30px;">
                    <input type="hidden" id="offer-product-id" name="product_id" value="<?= $product_id ?>">

                    <div style="background: #f8f9fa; padding: 20px; border-radius: 12px; margin-bottom: 25px; border: 2px solid #FF9800;">
                        <div style="display: flex; align-items: center; gap: 20px;">
                            <img src="<?= $images[0] ?? 'default.jpg' ?>" style="width: 80px; height: 80px; border-radius: 12px; object-fit: cover; border: 3px solid #FF9800;">
                            <div style="flex: 1;">
                                <h4 style="margin: 0 0 8px 0; font-size: 1.1rem; color: #333;"><?= htmlspecialchars($product['name']) ?></h4>
                                <div style="font-size: 1.3rem; font-weight: 700; color: #FF9800;">
                                    <?= number_format($product['price'], 2) ?> <?= $product['currency'] ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #333;">
                            Tu Oferta *
                        </label>
                        <input type="number" id="offer-price" name="offered_price" step="0.01" min="0.01" required
                               style="width: 100%; padding: 14px; border: 2px solid #e1e5e9; border-radius: 12px; font-size: 15px; font-family: 'Inter', sans-serif;"
                               placeholder="Ingresa tu precio">
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #333;">
                            Moneda *
                        </label>
                        <select id="offer-currency" name="offered_currency" required
                                style="width: 100%; padding: 14px; border: 2px solid #e1e5e9; border-radius: 12px; font-size: 15px; font-family: 'Inter', sans-serif;">
                            <option value="EUR" <?= $product['currency'] == 'EUR' ? 'selected' : '' ?>>€ Euros</option>
                            <option value="USD" <?= $product['currency'] == 'USD' ? 'selected' : '' ?>>$ Dólares</option>
                            <option value="BOB" <?= $product['currency'] == 'BOB' ? 'selected' : '' ?>>Bs Bolivianos</option>
                        </select>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #333;">
                            Cantidad *
                        </label>
                        <input type="number" id="offer-quantity" name="quantity" min="1" max="<?= $product['stock'] ?>" value="1" required
                               style="width: 100%; padding: 14px; border: 2px solid #e1e5e9; border-radius: 12px; font-size: 15px; font-family: 'Inter', sans-serif;">
                    </div>

                    <div style="margin-bottom: 25px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #333;">
                            Mensaje (opcional)
                        </label>
                        <textarea id="offer-message" name="message" rows="4"
                                  style="width: 100%; padding: 14px; border: 2px solid #e1e5e9; border-radius: 12px; font-size: 15px; font-family: 'Inter', sans-serif; resize: vertical;"
                                  placeholder="Explica por qué haces esta oferta..."></textarea>
                    </div>

                    <div style="display: flex; gap: 15px;">
                        <button type="button" onclick="closeOfferModal()"
                                style="flex: 1; padding: 16px; border: 2px solid #ddd; background: transparent; border-radius: 12px; cursor: pointer; font-weight: 600; transition: all 0.3s ease; font-family: 'Inter', sans-serif;">
                            Cancelar
                        </button>
                        <button type="submit"
                                style="flex: 1; background: linear-gradient(135deg, #FF9800, #FB8C00); color: white; border: none; padding: 16px; border-radius: 12px; font-weight: 600; font-size: 1.1rem; cursor: pointer; transition: all 0.3s ease; font-family: 'Inter', sans-serif;">
                            <i class="fas fa-paper-plane"></i> Enviar Oferta
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if (file_exists('footer1.php')) include 'footer1.php'; ?>

    <script>
        // Variables globales
        const productId = <?= $product_id ?>;
        const maxStock = <?= $product['stock'] ?>;
        const userLoggedIn = document.body.dataset.userLogged === 'true';
        const isSeller = document.body.dataset.isSeller === 'true';

        console.log('🛒 Producto:', productId, 'Stock:', maxStock, 'User:', userLoggedIn, 'IsSeller:', isSeller);

        // FUNCIONES DE NAVEGACIÓN POR TABS
        function openTab(evt, tabName) {
            const tabcontent = document.getElementsByClassName("tab-content");
            const tablinks = document.getElementsByClassName("tab-btn");
            
            for (let i = 0; i < tabcontent.length; i++) {
                tabcontent[i].classList.remove("active");
            }
            
            for (let i = 0; i < tablinks.length; i++) {
                tablinks[i].classList.remove("active");
            }
            
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("active");
        }

        // FUNCIONES DE GALERÍA DE IMÁGENES
        function changeMainImage(thumbnail) {
            const mainImage = document.getElementById('mainImage');
            const thumbnails = document.querySelectorAll('.thumbnail');
            
            mainImage.src = thumbnail.src;
            
            thumbnails.forEach(thumb => thumb.classList.remove('active'));
            thumbnail.classList.add('active');
        }

        function openImageModal(imageSrc) {
            const modal = document.getElementById('imageModal');
            const modalImg = document.getElementById('modalImage');
            
            modal.style.display = 'block';
            modalImg.src = imageSrc;
        }

        function closeImageModal() {
            document.getElementById('imageModal').style.display = 'none';
        }

        // FUNCIÓN AUXILIAR PARA OBTENER CANTIDAD ACTUAL
        function getCurrentQuantity() {
            const qtyInput = document.getElementById('quantity');
            if (!qtyInput) return 1;
            const qty = parseInt(qtyInput.value) || 1;
            return qty;
        }

        // FUNCIONES DE CARRITO
        function getCart() {
            return JSON.parse(localStorage.getItem('sendvialo_cart') || '[]');
        }

        function setCart(cart) {
            localStorage.setItem('sendvialo_cart', JSON.stringify(cart));
            updateCartBadge();
        }

        function updateCartBadge() {
            const cartCount = document.querySelector('.cart-count');
            if (cartCount) {
                const count = getCart().reduce((total, item) => total + item.quantity, 0);
                cartCount.textContent = count;
                cartCount.style.display = count > 0 ? 'flex' : 'none';
            }
        }

        function addToCart() {
            if (isSeller) {
                Swal.fire({
                    icon: 'info',
                    title: 'No puedes comprar tu propio producto',
                    confirmButtonColor: '#4CAF50'
                });
                return;
            }
            
            const quantity = getCurrentQuantity();
            const cart = getCart();
            const existingIndex = cart.findIndex(item => item.product_id === productId);
            
            const productData = {
                product_id: productId,
                name: '<?= addslashes($product['name']) ?>',
                price: <?= $product['price'] ?>,
                currency: '<?= $product['currency'] ?>',
                quantity: quantity,
                image: '<?= addslashes($main_image) ?>',
                seller_id: <?= $product['seller_id'] ?>
            };
            
            if (existingIndex > -1) {
                cart[existingIndex].quantity += quantity;
            } else {
                cart.push(productData);
            }
            
            setCart(cart);
            
            Swal.fire({
                icon: 'success',
                title: '¡Añadido al carrito!',
                text: `${quantity} x <?= addslashes($product['name']) ?>`,
                timer: 2000,
                showConfirmButton: false,
                toast: true,
                position: 'top-end',
                background: '#4CAF50',
                color: '#fff'
            });
        }

        function buyNow() {
            if (isSeller) {
                Swal.fire({
                    icon: 'info',
                    title: 'No puedes comprar tu propio producto',
                    confirmButtonColor: '#4CAF50'
                });
                return;
            }
            
            if (!userLoggedIn) {
                Swal.fire({
                    icon: 'info',
                    title: 'Iniciar sesión requerido',
                    text: 'Necesitas iniciar sesión para comprar productos',
                    showCancelButton: true,
                    confirmButtonText: 'Ir al login',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#4CAF50'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = '../login.php?redirect=shop';
                    }
                });
                return;
            }

            const quantity = getCurrentQuantity();
            const directPurchase = [{
                product_id: productId,
                name: '<?= addslashes($product['name']) ?>',
                price: <?= $product['price'] ?>,
                currency: '<?= $product['currency'] ?>',
                quantity: quantity,
                image: '<?= addslashes($main_image) ?>',
                seller_id: <?= $product['seller_id'] ?>
            }];
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'shop-checkout.php';
            form.style.display = 'none';
            
            const cartInput = document.createElement('input');
            cartInput.type = 'hidden';
            cartInput.name = 'cart';
            cartInput.value = JSON.stringify(directPurchase);
            
            form.appendChild(cartInput);
            document.body.appendChild(form);
            form.submit();
        }

        function openOfferModal() {
            if (isSeller) {
                Swal.fire({
                    icon: 'info',
                    title: 'No puedes ofertar en tu propio producto',
                    confirmButtonColor: '#FF9800'
                });
                return;
            }
            
            if (!userLoggedIn) {
                Swal.fire({
                    icon: 'info',
                    title: 'Iniciar sesión requerido',
                    text: 'Necesitas iniciar sesión para hacer una oferta',
                    showCancelButton: true,
                    confirmButtonText: 'Ir al login',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#FF9800'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = '../login.php?redirect=' + encodeURIComponent(window.location.href);
                    }
                });
                return;
            }

            document.getElementById('offer-price').value = '';
            document.getElementById('offer-message').value = '';
            document.getElementById('offer-quantity').value = 1;
            document.getElementById('offer-modal').style.display = 'block';
        }

        function closeOfferModal() {
            document.getElementById('offer-modal').style.display = 'none';
        }

        // INICIALIZACIÓN
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🎯 Inicializando...');
            updateCartBadge();
            
            if (!isSeller) {
                function setVal(v) {
                    if (v < 1) v = 1;
                    if (v > maxStock) v = maxStock;
                    const qtyInput = document.getElementById('quantity');
                    if (qtyInput) {
                        qtyInput.value = v;
                        document.getElementById('qtyMinus').disabled = v <= 1;
                        document.getElementById('qtyPlus').disabled = v >= maxStock;
                    }
                }
                
                const minusBtn = document.getElementById('qtyMinus');
                const plusBtn = document.getElementById('qtyPlus');
                const qtyInput = document.getElementById('quantity');
                
                if (minusBtn) minusBtn.onclick = (e) => { e.preventDefault(); setVal(getCurrentQuantity() - 1); };
                if (plusBtn) plusBtn.onclick = (e) => { e.preventDefault(); setVal(getCurrentQuantity() + 1); };
                if (qtyInput) qtyInput.onchange = () => setVal(getCurrentQuantity());
                
                setVal(1);
            }
            
            const addCartBtn = document.getElementById('addToCartBtn');
            const buyBtn = document.getElementById('buyNowBtn');
            
            if (addCartBtn) addCartBtn.onclick = (e) => { e.preventDefault(); addToCart(); };
            if (buyBtn) buyBtn.onclick = (e) => { e.preventDefault(); buyNow(); };
            
            // Manejar envío de oferta
            const offerForm = document.getElementById('offer-form');
            if (offerForm) {
                offerForm.addEventListener('submit', async function(e) {
                    e.preventDefault();

                    const formData = new FormData(this);
                    formData.append('action', 'submit_product_offer');

                    const submitBtn = this.querySelector('button[type="submit"]');
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
                    submitBtn.disabled = true;

                    try {
                        const response = await fetch('shop-negotiations-backend.php', {
                            method: 'POST',
                            body: formData
                        });

                        const data = await response.json();

                        if (data.success) {
                            closeOfferModal();
                            Swal.fire({
                                icon: 'success',
                                title: '¡Oferta enviada!',
                                html: '<p>El vendedor revisará tu oferta</p><a href="shop-my-product-offers.php" style="display: inline-block; margin-top: 15px; padding: 10px 20px; background: #FF9800; color: white; text-decoration: none; border-radius: 8px; font-weight: 600;"><i class="fas fa-eye"></i> Ver mis ofertas</a>',
                                confirmButtonColor: '#FF9800'
                            });
                            this.reset();
                        } else {
                            throw new Error(data.error || 'Error al enviar oferta');
                        }
                    } catch (error) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: error.message,
                            confirmButtonColor: '#FF9800'
                        });
                    } finally {
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }
                });
            }
            
            document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { closeImageModal(); closeOfferModal(); } });
            document.getElementById('imageModal').addEventListener('click', function(e) { if (e.target === this) closeImageModal(); });
            document.getElementById('offer-modal').addEventListener('click', function(e) { if (e.target === this) closeOfferModal(); });

            console.log('✅ Inicializado');
        });

        // ========================================
        // FUNCIONES PARA MANEJAR OFERTAS RECIBIDAS
        // ========================================

        function acceptOffer(offerId) {
            Swal.fire({
                title: '¿Aceptar esta oferta?',
                html: '<p>Al aceptar, el comprador deberá proceder con el pago.</p><p style="color: #FF9800; font-weight: 600;">Esta acción no se puede deshacer.</p>',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-check"></i> Sí, aceptar',
                cancelButtonText: '<i class="fas fa-times"></i> Cancelar',
                confirmButtonColor: '#4CAF50',
                cancelButtonColor: '#999',
                reverseButtons: true
            }).then(async (result) => {
                if (result.isConfirmed) {
                    try {
                        const response = await fetch('shop-negotiations-backend.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `action=accept_product_offer&offer_id=${offerId}`
                        });

                        const data = await response.json();

                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: '¡Oferta aceptada!',
                                text: 'Se ha notificado al comprador para que proceda con el pago.',
                                confirmButtonColor: '#4CAF50'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            throw new Error(data.error || 'Error al aceptar oferta');
                        }
                    } catch (error) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: error.message,
                            confirmButtonColor: '#FF9800'
                        });
                    }
                }
            });
        }

        function rejectOffer(offerId) {
            Swal.fire({
                title: '¿Rechazar esta oferta?',
                text: 'Se notificará al comprador que su oferta fue rechazada.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-times"></i> Sí, rechazar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#f44336',
                cancelButtonColor: '#999',
                reverseButtons: true
            }).then(async (result) => {
                if (result.isConfirmed) {
                    try {
                        const response = await fetch('shop-negotiations-backend.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `action=reject_product_offer&offer_id=${offerId}`
                        });

                        const data = await response.json();

                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Oferta rechazada',
                                text: 'Se ha notificado al comprador.',
                                confirmButtonColor: '#4CAF50'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            throw new Error(data.error || 'Error al rechazar oferta');
                        }
                    } catch (error) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: error.message,
                            confirmButtonColor: '#FF9800'
                        });
                    }
                }
            });
        }

        function openCounterofferModal(offerId, currentPrice, currency) {
            Swal.fire({
                title: '<i class="fas fa-exchange-alt"></i> Hacer Contraoferta',
                html: `
                    <div style="text-align: left; padding: 15px;">
                        <p style="margin-bottom: 20px; color: #666;">
                            Oferta actual: <strong style="color: #FF9800; font-size: 1.3rem;">${parseFloat(currentPrice).toFixed(2)} ${currency}</strong>
                        </p>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px;">Tu contraoferta:</label>
                        <input type="number" id="swal-counteroffer-price" class="swal2-input" placeholder="Ingresa tu precio" step="0.01" min="0.01" value="${parseFloat(currentPrice).toFixed(2)}" style="width: 100%; margin-bottom: 15px;">

                        <label style="display: block; font-weight: 600; margin-bottom: 8px;">Mensaje (opcional):</label>
                        <textarea id="swal-counteroffer-message" class="swal2-textarea" placeholder="Explica tu contraoferta..." style="width: 100%; min-height: 100px;"></textarea>
                    </div>
                `,
                width: '600px',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-paper-plane"></i> Enviar Contraoferta',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#FF9800',
                cancelButtonColor: '#999',
                preConfirm: () => {
                    const price = document.getElementById('swal-counteroffer-price').value;
                    const message = document.getElementById('swal-counteroffer-message').value;

                    if (!price || parseFloat(price) <= 0) {
                        Swal.showValidationMessage('Por favor ingresa un precio válido');
                        return false;
                    }

                    return { price, message };
                }
            }).then(async (result) => {
                if (result.isConfirmed) {
                    try {
                        const formData = new URLSearchParams();
                        formData.append('action', 'submit_product_counteroffer');
                        formData.append('offer_id', offerId);
                        formData.append('offered_price', result.value.price);
                        formData.append('offered_currency', currency);
                        formData.append('quantity', 1); // Mantener la cantidad original
                        formData.append('message', result.value.message);

                        const response = await fetch('shop-negotiations-backend.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: formData.toString()
                        });

                        const data = await response.json();

                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: '¡Contraoferta enviada!',
                                text: 'Se ha notificado al comprador sobre tu contraoferta.',
                                confirmButtonColor: '#FF9800'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            throw new Error(data.error || 'Error al enviar contraoferta');
                        }
                    } catch (error) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: error.message,
                            confirmButtonColor: '#FF9800'
                        });
                    }
                }
            });
        }

        async function viewNegotiationHistory(offerId) {
            try {
                const response = await fetch(`shop-negotiations-backend.php?action=get_product_negotiation_history&offer_id=${offerId}`);
                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.error || 'Error al obtener historial');
                }

                const history = data.history || [];

                if (history.length === 0) {
                    Swal.fire({
                        icon: 'info',
                        title: 'Sin historial',
                        text: 'Esta oferta aún no tiene historial de negociación.',
                        confirmButtonColor: '#FF9800'
                    });
                    return;
                }

                // Crear HTML del historial
                let historyHTML = '<div style="text-align: left; max-height: 500px; overflow-y: auto; padding: 15px;">';

                history.forEach((item, index) => {
                    const userType = item.user_type === 'buyer' ? 'Comprador' : 'Vendedor';
                    const bgColor = item.user_type === 'buyer' ? '#E3F2FD' : '#FFF3E0';
                    const iconColor = item.user_type === 'buyer' ? '#2196F3' : '#FF9800';
                    const isInitial = item.offer_type === 'initial';

                    historyHTML += `
                        <div style="background: ${bgColor}; padding: 15px; border-radius: 12px; margin-bottom: 15px; border-left: 4px solid ${iconColor};">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <div style="font-weight: 600; color: #333;">
                                    <i class="fas fa-user" style="color: ${iconColor};"></i>
                                    ${item.user_name} (${userType})
                                    ${isInitial ? '<span style="background: #4CAF50; color: white; padding: 2px 8px; border-radius: 8px; font-size: 0.75rem; margin-left: 8px;">INICIAL</span>' : ''}
                                </div>
                                <div style="font-size: 0.85rem; color: #666;">
                                    ${new Date(item.created_at).toLocaleString('es-ES')}
                                </div>
                            </div>
                            <div style="font-size: 1.5rem; font-weight: 700; color: ${iconColor}; margin-bottom: 8px;">
                                ${parseFloat(item.offered_price).toFixed(2)} ${item.offered_currency}
                            </div>
                            ${item.message ? `<div style="color: #555; font-size: 0.95rem; font-style: italic;">"${item.message}"</div>` : ''}
                        </div>
                    `;
                });

                historyHTML += '</div>';

                Swal.fire({
                    title: '<i class="fas fa-history"></i> Historial de Negociación',
                    html: historyHTML,
                    width: '700px',
                    confirmButtonText: 'Cerrar',
                    confirmButtonColor: '#FF9800'
                });

            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message,
                    confirmButtonColor: '#FF9800'
                });
            }
        }
    </script>
</body>
</html>