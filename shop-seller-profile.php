<?php
// shop-seller-profile.php - Perfil público del vendedor estilo Amazon/Temu
session_start();

// ✅ FIX CHROME: Definir ruta base para imágenes (soluciona errores 404 en Chrome)
define('SHOP_BASE_PATH', '/shop/');

// Incluir sistema de insignias unificado
require_once 'insignias1.php';
require_once '../config.php';

// Obtener ID del vendedor
$seller_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($seller_id <= 0) {
    header('Location: index.php');
    exit;
}

// Variables de usuario actual
$user_logged_in = isset($_SESSION['usuario_id']);
$current_user_id = $user_logged_in ? $_SESSION['usuario_id'] : null;

// Obtener información del vendedor
try {
    // Información básica del vendedor
    $seller_sql = "SELECT 
                    a.id, a.full_name, a.username, a.verificado,
                    a.estudios, a.trabajo, a.gustos, a.idiomas, a.viajes,
                    a.provincia, a.pais, a.created_at, a.ruta_imagen
                   FROM accounts a
                   WHERE a.id = ?";
    
    $seller_stmt = $conexion->prepare($seller_sql);
    $seller_stmt->execute([$seller_id]);
    $seller = $seller_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$seller) {
        header('Location: index.php');
        exit;
    }
    
    // Obtener valoraciones del vendedor usando el sistema unificado
    $rating_sql = "SELECT 
                    AVG(valoracion) as promedio_valoracion,
                    COUNT(*) as total_valoraciones
                   FROM comentarios 
                   WHERE usuario_id = ? AND bloqueado = 0";
    $rating_stmt = $conexion->prepare($rating_sql);
    $rating_stmt->execute([$seller_id]);
    $rating_data = $rating_stmt->fetch(PDO::FETCH_ASSOC);
    
    $seller_rating = $rating_data['promedio_valoracion'] ? round($rating_data['promedio_valoracion'], 1) : 0;
    $total_ratings = $rating_data['total_valoraciones'] ?: 0;
    
    // Obtener productos del vendedor
    $products_sql = "SELECT 
                        sp.*,
                        t.search_input as origin_country,
                        t.destination_input as destination_city,
                        (SELECT image_path FROM shop_product_images spi WHERE spi.product_id = sp.id LIMIT 1) as primary_image
                     FROM shop_products sp
                     LEFT JOIN transporting t ON sp.trip_id = t.id
                     WHERE sp.seller_id = ? AND sp.active = 1
                     ORDER BY sp.created_at DESC";
    
    $products_stmt = $conexion->prepare($products_sql);
    $products_stmt->execute([$seller_id]);
    $products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Estadísticas del vendedor
    $stats_sql = "SELECT 
                    COUNT(DISTINCT sp.id) as total_products,
                    COUNT(DISTINCT sp.category) as total_categories,
                    COUNT(DISTINCT sp.trip_id) as total_trips_with_products,
                    MIN(sp.price) as min_price,
                    MAX(sp.price) as max_price,
                    sp.currency as main_currency
                  FROM shop_products sp 
                  WHERE sp.seller_id = ? AND sp.active = 1";
    
    $stats_stmt = $conexion->prepare($stats_sql);
    $stats_stmt->execute([$seller_id]);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener comentarios recientes (primeros 5)
    $comments_sql = "SELECT 
                        c.comentario, c.valoracion, c.fecha,
                        c.cliente_id, c.nombre_usuario,
                        a.full_name as comentador_nombre,
                        a.ruta_imagen as comentador_imagen
                     FROM comentarios c
                     LEFT JOIN accounts a ON c.cliente_id = a.id
                     WHERE c.usuario_id = ? AND c.bloqueado = 0
                     ORDER BY c.fecha DESC 
                     LIMIT 5";
    
    $comments_stmt = $conexion->prepare($comments_sql);
    $comments_stmt->execute([$seller_id]);
    $recent_comments = $comments_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener viajes recientes del vendedor
    $trips_sql = "SELECT 
                    t.id, t.search_input as origin, t.destination_input as destination,
                    t.datepicker as fecha_viaje, t.precio, t.moneda
                  FROM transporting t
                  WHERE t.id_transporting = ? AND t.estado = 'activo'
                  ORDER BY t.datepicker DESC
                  LIMIT 3";
    
    $trips_stmt = $conexion->prepare($trips_sql);
    $trips_stmt->execute([$seller_id]);
    $recent_trips = $trips_stmt->fetchAll(PDO::FETCH_ASSOC);
    
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

// Función para extraer ciudad de ubicación
function extractCity($location) {
    if (!$location) return '';
    return trim(explode(',', $location)[0]);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($seller['full_name']) ?> - Vendedor SendVialo</title>
    <link rel="stylesheet" href="../css/estilos.css">
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
            color: #41ba0d;
            text-decoration: none;
        }

        .breadcrumb-nav a:hover {
            text-decoration: underline;
        }

        /* LAYOUT PRINCIPAL */
        .seller-profile-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        /* HEADER DEL VENDEDOR */
        .seller-header {
            background: linear-gradient(135deg, #0f0f0f 0%, #1a1a1a 50%, #0f0f0f 100%);
            color: white;
            padding: 50px 40px;
            border-radius: 20px;
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
        }

        .seller-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 20%, rgba(76, 175, 80, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(76, 175, 80, 0.05) 0%, transparent 50%);
        }

        .seller-header-content {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 40px;
            align-items: center;
            position: relative;
            z-index: 2;
        }

        .seller-avatar-section {
            text-align: center;
        }

        .seller-avatar-section .profile-image-laurel img {
            width: 120px;
            height: 120px;
        }

        .seller-avatar-section .laurel-svg {
            width: 168px;
            height: 168px;
        }

        .seller-main-info h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .verified-icon {
            color: #41ba0d;
            font-size: 1.8rem;
        }

        .seller-rating-display {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            font-size: 1.2rem;
        }

        .rating-stars {
            color: #ffd700;
            font-size: 1.5rem;
        }

        .rating-value {
            font-size: 1.8rem;
            font-weight: 700;
        }

        .seller-location {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .seller-since {
            font-size: 0.95rem;
            opacity: 0.8;
        }

        .seller-stats-summary {
            background: rgba(255, 255, 255, 0.1);
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            min-width: 200px;
            backdrop-filter: blur(10px);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #41ba0d;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        /* GRID PRINCIPAL */
        .profile-main-grid {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 40px;
        }

        /* SIDEBAR */
        .profile-sidebar {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .info-card {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .info-card h3 {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 20px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #f0f0f0;
        }

        .info-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .info-icon {
            color: #41ba0d;
            font-size: 1.2rem;
            margin-top: 3px;
        }

        .info-content {
            flex: 1;
        }

        .info-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }

        .info-value {
            color: #666;
            line-height: 1.5;
        }

        /* COMENTARIOS SIDEBAR */
        .comment-item {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 15px;
        }

        .comment-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
        }

        .comment-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .comment-author {
            font-weight: 600;
            font-size: 0.95rem;
            color: #333;
        }

        .comment-rating {
            color: #ffd700;
            font-size: 0.85rem;
            margin-bottom: 8px;
        }

        .comment-text {
            font-size: 0.9rem;
            color: #666;
            line-height: 1.4;
        }

        /* CONTENIDO PRINCIPAL */
        .profile-main-content {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }

        .section-card {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .section-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 30px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        /* GRID DE PRODUCTOS */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
        }

        .product-card {
            background: #f8f9fa;
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid transparent;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
            border-color: #41ba0d;
        }

        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: linear-gradient(45deg, #f0f0f0, #e0e0e0);
        }

        .product-info {
            padding: 20px;
        }

        .product-name {
            font-weight: 700;
            margin-bottom: 10px;
            color: #333;
            font-size: 1.1rem;
            line-height: 1.3;
        }

        .product-price {
            color: #41ba0d;
            font-weight: 700;
            font-size: 1.2rem;
            margin-bottom: 8px;
        }

        .product-route {
            font-size: 0.85rem;
            color: #666;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* TRIPS SECTION */
        .trips-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .trip-card {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
            border-left: 4px solid #41ba0d;
            transition: all 0.3s ease;
        }

        .trip-card:hover {
            background: #f0f0f0;
            transform: translateY(-2px);
        }

        .trip-route {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: #333;
        }

        .trip-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #666;
            font-size: 0.9rem;
        }

        .trip-date {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .trip-price {
            font-weight: 600;
            color: #41ba0d;
        }

        /* EMPTY STATES */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-state i {
            font-size: 3.5rem;
            margin-bottom: 20px;
            color: #ddd;
        }

        .empty-state h3 {
            font-size: 1.3rem;
            margin-bottom: 10px;
            color: #333;
        }

        /* ACTIONS */
        .contact-actions {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            font-size: 0.95rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #41ba0d, #5dcb2a);
            color: white;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.4);
            color: white;
            text-decoration: none;
        }

        .btn-secondary {
            background: white;
            color: #41ba0d;
            border: 2px solid #41ba0d;
        }

        .btn-secondary:hover {
            background: #41ba0d;
            color: white;
            text-decoration: none;
        }

        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .profile-main-grid {
                grid-template-columns: 1fr;
                gap: 30px;
            }

            .seller-header-content {
                grid-template-columns: 1fr;
                text-align: center;
                gap: 25px;
            }

            .seller-avatar-section .profile-image-laurel img {
                width: 100px;
                height: 100px;
            }

            .seller-avatar-section .laurel-svg {
                width: 140px;
                height: 140px;
            }
        }

        @media (max-width: 768px) {
            .seller-profile-container {
                padding: 20px 15px;
            }

            .seller-header {
                padding: 30px 25px;
            }

            .seller-main-info h1 {
                font-size: 2rem;
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }

            .section-card, .info-card {
                padding: 25px 20px;
            }

            .products-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }

            .trips-grid {
                grid-template-columns: 1fr;
            }

            .contact-actions {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .products-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body data-user-logged="<?= $user_logged_in ? 'true' : 'false' ?>" 
      data-seller-id="<?= $seller_id ?>"
      <?php if ($user_logged_in): ?>
      data-user-id="<?= $current_user_id ?>" 
      <?php endif; ?>>
      
    <?php include 'header2.php'; ?>

    <!-- BREADCRUMB -->
    <div class="breadcrumb">
        <div class="breadcrumb-container">
            <nav class="breadcrumb-nav">
                <a href="../index.php"><i class="fas fa-home"></i> Inicio</a>
                <i class="fas fa-chevron-right"></i>
                <a href="index.php">Shop</a>
                <i class="fas fa-chevron-right"></i>
                <span><?= htmlspecialchars($seller['full_name']) ?></span>
            </nav>
        </div>
    </div>

    <div class="seller-profile-container">
        <!-- HEADER DEL VENDEDOR -->
        <div class="seller-header">
            <div class="seller-header-content">
                <!-- Avatar del vendedor -->
                <div class="seller-avatar-section">
                    <?php 
                    // ✅ FIX CHROME: Usar ruta absoluta para las imágenes
                    $seller_avatar = !empty($seller['ruta_imagen']) ? 
                        SHOP_BASE_PATH . "mostrar_imagen.php?id=" . $seller_id : 
                        "https://ui-avatars.com/api/?name=" . urlencode($seller['full_name']) . "&background=667eea&color=fff&size=120";
                    
                    echo mostrarImagenConLaurelShop(
                        $seller_avatar, 
                        $seller_rating, 
                        $seller['verificado'] ?? false, 
                        120
                    );
                    ?>
                </div>
                
                <!-- Información principal -->
                <div class="seller-main-info">
                    <h1>
                        <?= htmlspecialchars($seller['full_name']) ?>
                        <?php if ($seller['verificado']): ?>
                        <i class="fas fa-check-circle verified-icon" title="Usuario verificado"></i>
                        <?php endif; ?>
                    </h1>
                    
                    <div class="seller-rating-display">
                        <?php if ($seller_rating > 0): ?>
                            <span class="rating-stars"><?= str_repeat('★', floor($seller_rating)) ?><?= str_repeat('☆', 5 - floor($seller_rating)) ?></span>
                            <span class="rating-value <?= $badge_type ?>-text"><?= $seller_rating ?></span>
                            <span>(<?= $total_ratings ?> valoraciones)</span>
                        <?php else: ?>
                            <span class="no-rating">Sin valoraciones aún</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($seller['provincia'] || $seller['pais']): ?>
                    <div class="seller-location">
                        <i class="fas fa-map-marker-alt"></i>
                        <?= htmlspecialchars($seller['provincia'] ?: 'Ubicación') ?>, 
                        <?= htmlspecialchars($seller['pais'] ?: 'Mundo') ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="seller-since">
                        <i class="fas fa-calendar-alt"></i>
                        Vendedor desde <?= date('F Y', strtotime($seller['created_at'])) ?>
                    </div>
                </div>
                
                <!-- Estadísticas resumidas -->
                <div class="seller-stats-summary">
                    <div class="stat-number"><?= $stats['total_products'] ?: 0 ?></div>
                    <div class="stat-label">Productos Activos</div>
                    
                    <div style="margin: 20px 0;"></div>
                    
                    <div class="stat-number"><?= $stats['total_categories'] ?: 0 ?></div>
                    <div class="stat-label">Categorías</div>
                </div>
            </div>
        </div>

        <!-- GRID PRINCIPAL -->
        <div class="profile-main-grid">
            <!-- SIDEBAR -->
            <div class="profile-sidebar">
                <!-- Información personal -->
                <div class="info-card">
                    <h3><i class="fas fa-user"></i> Información Personal</h3>
                    
                    <?php if ($seller['idiomas']): ?>
                    <div class="info-item">
                        <i class="fas fa-language info-icon"></i>
                        <div class="info-content">
                            <div class="info-label">Idiomas</div>
                            <div class="info-value"><?= htmlspecialchars($seller['idiomas']) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($seller['estudios']): ?>
                    <div class="info-item">
                        <i class="fas fa-graduation-cap info-icon"></i>
                        <div class="info-content">
                            <div class="info-label">Estudios</div>
                            <div class="info-value"><?= htmlspecialchars($seller['estudios']) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($seller['trabajo']): ?>
                    <div class="info-item">
                        <i class="fas fa-briefcase info-icon"></i>
                        <div class="info-content">
                            <div class="info-label">Trabajo</div>
                            <div class="info-value"><?= htmlspecialchars($seller['trabajo']) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($seller['gustos']): ?>
                    <div class="info-item">
                        <i class="fas fa-heart info-icon"></i>
                        <div class="info-content">
                            <div class="info-label">Intereses</div>
                            <div class="info-value"><?= htmlspecialchars($seller['gustos']) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($seller['viajes']): ?>
                    <div class="info-item">
                        <i class="fas fa-plane info-icon"></i>
                        <div class="info-content">
                            <div class="info-label">Experiencia de Viajes</div>
                            <div class="info-value"><?= htmlspecialchars($seller['viajes']) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Comentarios recientes -->
                <?php if (!empty($recent_comments)): ?>
                <div class="info-card">
                    <h3><i class="fas fa-star"></i> Comentarios Recientes</h3>
                    
                    <?php foreach ($recent_comments as $comment): ?>
                    <div class="comment-item">
                        <div class="comment-header">
                            <?php 
                            // ✅ FIX CHROME: Usar ruta absoluta para las imágenes
                            $comentador_avatar = !empty($comment['comentador_imagen']) && !empty($comment['cliente_id']) ? 
                                SHOP_BASE_PATH . "mostrar_imagen.php?id=" . $comment['cliente_id'] : 
                                SHOP_BASE_PATH . 'user-default.jpg';
                            ?>
                            <img src="<?= htmlspecialchars($comentador_avatar) ?>" alt="Avatar" class="comment-avatar" onerror="this.src='<?= SHOP_BASE_PATH ?>user-default.jpg';">
                            <div class="comment-author"><?= htmlspecialchars($comment['comentador_nombre'] ?: $comment['nombre_usuario']) ?></div>
                        </div>
                        <div class="comment-rating">
                            <?= str_repeat('★', $comment['valoracion']) ?><?= str_repeat('☆', 5 - $comment['valoracion']) ?>
                        </div>
                        <div class="comment-text"><?= htmlspecialchars($comment['comentario']) ?></div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if ($total_ratings > 5): ?>
                    <a href="mi_perfil.php?id=<?= $seller_id ?>" class="btn btn-secondary" style="width: 100%; margin-top: 15px;">
                        <i class="fas fa-eye"></i> Ver todos los comentarios
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- CONTENIDO PRINCIPAL -->
            <div class="profile-main-content">
                <!-- Productos del vendedor -->
                <div class="section-card">
                    <h2 class="section-title">
                        <i class="fas fa-shopping-bag"></i> 
                        Productos (<?= count($products) ?>)
                    </h2>
                    
                    <?php if (!empty($products)): ?>
                    <div class="products-grid">
                        <?php foreach ($products as $product): ?>
                        <div class="product-card" onclick="window.location.href='shop-product-detail.php?id=<?= $product['id'] ?>'">
                            <?php 
                            $product_image = !empty($product['primary_image']) ? 
                                $product['primary_image'] : 
                                'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=400&h=400&fit=crop';
                            ?>
                            <img src="<?= htmlspecialchars($product_image) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="product-image">
                            <div class="product-info">
                                <div class="product-name"><?= htmlspecialchars($product['name']) ?></div>
                                <div class="product-price">
                                    <?php
                                    $currency_symbol = '';
                                    switch ($product['currency']) {
                                        case 'EUR': $currency_symbol = '€'; break;
                                        case 'USD': $currency_symbol = '$'; break;
                                        case 'BOB': $currency_symbol = 'Bs'; break;
                                        default: $currency_symbol = '€'; break;
                                    }
                                    echo $currency_symbol . number_format($product['price'], 2);
                                    ?>
                                </div>
                                <?php if ($product['origin_country'] && $product['destination_city']): ?>
                                <div class="product-route">
                                    <i class="fas fa-route"></i>
                                    <?= htmlspecialchars(extractCity($product['origin_country'])) ?> → <?= htmlspecialchars(extractCity($product['destination_city'])) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-box-open"></i>
                        <h3>No hay productos disponibles</h3>
                        <p>Este vendedor aún no ha publicado productos</p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Viajes recientes -->
                <?php if (!empty($recent_trips)): ?>
                <div class="section-card">
                    <h2 class="section-title">
                        <i class="fas fa-plane"></i> 
                        Viajes Recientes
                    </h2>
                    
                    <div class="trips-grid">
                        <?php foreach ($recent_trips as $trip): ?>
                        <div class="trip-card">
                            <div class="trip-route">
                                <?= htmlspecialchars(extractCity($trip['origin'])) ?> → <?= htmlspecialchars(extractCity($trip['destination'])) ?>
                            </div>
                            <div class="trip-details">
                                <div class="trip-date">
                                    <i class="fas fa-calendar"></i>
                                    <?= date('d/m/Y', strtotime($trip['fecha_viaje'])) ?>
                                </div>
                                <div class="trip-price">
                                    <?php
                                    $trip_currency_symbol = '';
                                    switch ($trip['moneda']) {
                                        case 'EUR': $trip_currency_symbol = '€'; break;
                                        case 'USD': $trip_currency_symbol = '$'; break;
                                        case 'BOB': $trip_currency_symbol = 'Bs'; break;
                                        default: $trip_currency_symbol = '€'; break;
                                    }
                                    echo $trip_currency_symbol . $trip['precio'];
                                    ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Acciones de contacto -->
                <div class="section-card">
                    <h2 class="section-title">
                        <i class="fas fa-handshake"></i> 
                        Conectar con <?= htmlspecialchars($seller['full_name']) ?>
                    </h2>
                    
                    <p style="margin-bottom: 25px; color: #666; font-size: 1.1rem; line-height: 1.6;">
                        ¿Interesado en los productos de este vendedor? Ponte en contacto para coordinar la compra 
                        y el transporte de tus productos favoritos.
                    </p>
                    
                    <div class="contact-actions">
                        <button class="btn btn-primary" onclick="contactSeller()">
                            <i class="fas fa-comment"></i>
                            Enviar Mensaje
                        </button>
                        
                        <button class="btn btn-secondary" onclick="followSeller()">
                            <i class="fas fa-heart"></i>
                            Seguir Vendedor
                        </button>
                        
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i>
                            Volver al Shop
                        </a>
                    </div>
                    
                    <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 10px; border-left: 4px solid #41ba0d;">
                        <h4 style="margin-bottom: 10px; color: #333;">
                            <i class="fas fa-shield-alt" style="color: #41ba0d;"></i>
                            Compra Segura
                        </h4>
                        <p style="margin: 0; color: #666; font-size: 0.95rem; line-height: 1.5;">
                            Todas las transacciones están protegidas por nuestro sistema de garantía. 
                            Los pagos se liberan únicamente cuando recibes tu producto en perfectas condiciones.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (file_exists('footer1.php')) include 'footer1.php'; ?>

    <script>
    // Variables globales
    const sellerId = <?= $seller_id ?>;
    const userLoggedIn = <?= $user_logged_in ? 'true' : 'false' ?>;
    const currentUserId = <?= $current_user_id ?? 'null' ?>;
    let isFollowing = false;

    console.log('👤 Perfil del vendedor cargado:', sellerId);

    // Función para contactar al vendedor (Enviar Mensaje)
    function contactSeller() {
        if (!userLoggedIn) {
            Swal.fire({
                icon: 'info',
                title: 'Iniciar sesión requerido',
                text: 'Necesitas iniciar sesión para contactar al vendedor',
                showCancelButton: true,
                confirmButtonText: 'Ir al login',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#41ba0d'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../login.php';
                }
            });
            return;
        }

        // Verificar que no sea el mismo usuario
        if (currentUserId === sellerId) {
            Swal.fire({
                icon: 'info',
                title: 'No puedes contactarte a ti mismo',
                text: 'Este es tu propio perfil',
                confirmButtonColor: '#41ba0d'
            });
            return;
        }

        // Cargar productos del vendedor para iniciar chat
        Swal.fire({
            title: 'Selecciona un producto',
            html: '<div id="product-loading" style="padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Cargando productos...</div>',
            showCancelButton: true,
            showConfirmButton: false,
            cancelButtonText: 'Cancelar',
            width: '700px',
            didOpen: () => {
                // Cargar productos del vendedor
                fetch(`shop-seller-actions.php?action=get_products&seller_id=${sellerId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.products && data.products.length > 0) {
                            let productsHtml = `
                                <div style="text-align: left; max-height: 400px; overflow-y: auto;">
                                    <p style="margin-bottom: 15px; color: #666;">
                                        Para iniciar una conversación, selecciona el producto sobre el que quieres hablar:
                                    </p>
                                    <div style="display: grid; gap: 10px;">
                            `;

                            data.products.forEach(product => {
                                const imageUrl = product.image || '../Imagenes/default-product.png';
                                const price = parseFloat(product.price).toFixed(2);
                                productsHtml += `
                                    <div class="product-select-item" onclick="openChatWithProduct(${product.id})"
                                         style="display: flex; gap: 15px; padding: 15px; border: 2px solid #e0e0e0;
                                                border-radius: 8px; cursor: pointer; transition: all 0.2s;
                                                background: white;">
                                        <img src="${imageUrl}" alt="${product.name}"
                                             style="width: 80px; height: 80px; object-fit: cover; border-radius: 6px;">
                                        <div style="flex: 1;">
                                            <h4 style="margin: 0 0 5px 0; color: #333; font-size: 1rem;">
                                                ${product.name}
                                            </h4>
                                            <p style="margin: 0; color: #666; font-size: 0.9rem; line-height: 1.4;">
                                                ${product.description ? product.description.substring(0, 80) + '...' : 'Sin descripción'}
                                            </p>
                                            <div style="margin-top: 8px; font-size: 1.1rem; font-weight: bold; color: #09B83E;">
                                                ${product.currency === 'VES' ? 'Bs.' : '$'} ${price}
                                            </div>
                                        </div>
                                        <div style="align-self: center;">
                                            <i class="fas fa-chevron-right" style="color: #999;"></i>
                                        </div>
                                    </div>
                                `;
                            });

                            productsHtml += '</div></div>';
                            document.getElementById('product-loading').innerHTML = productsHtml;

                            // Agregar hover effect
                            document.querySelectorAll('.product-select-item').forEach(item => {
                                item.addEventListener('mouseenter', function() {
                                    this.style.borderColor = '#09B83E';
                                    this.style.boxShadow = '0 2px 8px rgba(9,184,62,0.2)';
                                });
                                item.addEventListener('mouseleave', function() {
                                    this.style.borderColor = '#e0e0e0';
                                    this.style.boxShadow = 'none';
                                });
                            });
                        } else {
                            document.getElementById('product-loading').innerHTML = `
                                <div style="text-align: center; padding: 40px; color: #999;">
                                    <i class="fas fa-box-open" style="font-size: 3rem; margin-bottom: 15px;"></i>
                                    <p>Este vendedor no tiene productos disponibles en este momento.</p>
                                </div>
                            `;
                        }
                    })
                    .catch(error => {
                        console.error('Error cargando productos:', error);
                        document.getElementById('product-loading').innerHTML = `
                            <div style="text-align: center; padding: 20px; color: #f44336;">
                                <i class="fas fa-exclamation-triangle"></i>
                                Error al cargar los productos
                            </div>
                        `;
                    });
            }
        });
    }

    // Abrir chat con un producto específico
    window.openChatWithProduct = function(productId) {
        Swal.close();
        window.location.href = `shop-chat.php?product_id=${productId}`;
    }

    // Enviar mensaje al servidor
    function sendMessageToSeller(subject, message) {
        // Mostrar loading
        Swal.fire({
            title: 'Enviando mensaje...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Enviar via AJAX
        fetch('shop-seller-actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'send_message',
                seller_id: sellerId,
                subject: subject,
                message: message
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Mensaje enviado!',
                    text: data.message,
                    confirmButtonColor: '#41ba0d'
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message,
                    confirmButtonColor: '#f44336'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error de conexión',
                text: 'No se pudo enviar el mensaje. Intenta de nuevo.',
                confirmButtonColor: '#f44336'
            });
        });
    }

    // Función para seguir al vendedor
    function followSeller() {
        if (!userLoggedIn) {
            Swal.fire({
                icon: 'info',
                title: 'Iniciar sesión requerido',
                text: 'Necesitas iniciar sesión para seguir vendedores',
                showCancelButton: true,
                confirmButtonText: 'Ir al login',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#41ba0d'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../login.php';
                }
            });
            return;
        }

        // Verificar que no sea el mismo usuario
        if (currentUserId === sellerId) {
            Swal.fire({
                icon: 'info',
                title: 'No puedes seguirte a ti mismo',
                text: 'Este es tu propio perfil',
                confirmButtonColor: '#41ba0d'
            });
            return;
        }

        // Determinar acción: seguir o dejar de seguir
        const action = isFollowing ? 'unfollow' : 'follow';
        const actionText = isFollowing ? 'Dejando de seguir...' : 'Siguiendo...';

        // Mostrar loading
        Swal.fire({
            title: actionText,
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Enviar via AJAX
        fetch('shop-seller-actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: action,
                seller_id: sellerId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                isFollowing = data.following;
                updateFollowButton();
                
                // Actualizar contador si existe
                updateFollowersCount(data.followers_count);

                Swal.fire({
                    icon: 'success',
                    title: isFollowing ? '¡Siguiendo!' : 'Dejaste de seguir',
                    text: data.message,
                    confirmButtonColor: '#41ba0d',
                    timer: 2000
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message,
                    confirmButtonColor: '#f44336'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error de conexión',
                text: 'No se pudo completar la acción. Intenta de nuevo.',
                confirmButtonColor: '#f44336'
            });
        });
    }

    // Actualizar botón de seguir
    function updateFollowButton() {
        const followBtn = document.querySelector('button[onclick="followSeller()"]');
        if (followBtn) {
            if (isFollowing) {
                followBtn.innerHTML = '<i class="fas fa-heart-broken"></i> Dejar de Seguir';
                followBtn.classList.remove('btn-secondary');
                followBtn.classList.add('btn-danger');
            } else {
                followBtn.innerHTML = '<i class="fas fa-heart"></i> Seguir Vendedor';
                followBtn.classList.remove('btn-danger');
                followBtn.classList.add('btn-secondary');
            }
        }
    }

    // Actualizar contador de seguidores
    function updateFollowersCount(count) {
        const countElement = document.getElementById('followers-count');
        if (countElement) {
            countElement.textContent = count;
        }
    }

    // Verificar estado de seguimiento al cargar
    function checkFollowStatus() {
        if (!userLoggedIn) return;

        fetch(`shop-seller-actions.php?action=check_follow&seller_id=${sellerId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    isFollowing = data.following;
                    updateFollowButton();
                    updateFollowersCount(data.followers_count);
                }
            })
            .catch(error => console.error('Error checking follow status:', error));
    }

    // Inicialización
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🎯 Inicializando perfil del vendedor...');
        
        // Verificar estado de seguimiento
        checkFollowStatus();
        
        // Animaciones de entrada
        const cards = document.querySelectorAll('.info-card, .section-card');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            setTimeout(() => {
                card.style.transition = 'all 0.6s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });
        
        console.log('✅ Perfil del vendedor inicializado');
    });
</script>

<!-- ============================================
     CSS ADICIONAL PARA LOS BOTONES
     Agregar en el <style> del archivo
     ============================================ -->
<style>
.btn-danger {
    background-color: #f44336;
    border-color: #f44336;
}

.btn-danger:hover {
    background-color: #d32f2f;
    border-color: #d32f2f;
}

/* Animación para el contador de seguidores */
#followers-count {
    transition: all 0.3s ease;
}

#followers-count.updated {
    animation: pulse 0.5s ease;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.2); }
}
</style>
</body>
</html>