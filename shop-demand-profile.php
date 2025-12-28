<?php
session_start();
require_once 'insignias1.php';
require_once '../config.php';

$requester_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($requester_id <= 0) {
    header('Location: shop-requests-index.php');
    exit;
}

$user_logged_in = isset($_SESSION['usuario_id']);
$current_user_id = $user_logged_in ? $_SESSION['usuario_id'] : null;

try {
    $requester_sql = "SELECT a.id, a.full_name, a.username, a.verificado, a.provincia, a.pais, 
                      a.created_at, a.ruta_imagen, a.idiomas, a.estudios, a.trabajo, a.email 
                      FROM accounts a WHERE a.id = ?";
    $requester_stmt = $conexion->prepare($requester_sql);
    $requester_stmt->execute([$requester_id]);
    $requester = $requester_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$requester) {
        header('Location: shop-requests-index.php');
        exit;
    }
    
    $rating_sql = "SELECT AVG(valoracion) as promedio_valoracion, COUNT(*) as total_valoraciones 
                   FROM comentarios WHERE usuario_id = ? AND bloqueado = 0";
    $rating_stmt = $conexion->prepare($rating_sql);
    $rating_stmt->execute([$requester_id]);
    $rating_data = $rating_stmt->fetch(PDO::FETCH_ASSOC);
    $requester_rating = $rating_data['promedio_valoracion'] ? round($rating_data['promedio_valoracion'], 1) : 0;
    $total_ratings = $rating_data['total_valoraciones'] ?: 0;
    
    // Estadísticas
    $stats_sql = "SELECT COUNT(*) as total_requests FROM shop_requests WHERE user_id = ? AND status NOT IN ('completed', 'cancelled')";
    $stats_stmt = $conexion->prepare($stats_sql);
    $stats_stmt->execute([$requester_id]);
    $total_requests = $stats_stmt->fetchColumn();
    
    $completed_sql = "SELECT COUNT(*) FROM shop_requests WHERE user_id = ? AND status = 'completed'";
    $completed_stmt = $conexion->prepare($completed_sql);
    $completed_stmt->execute([$requester_id]);
    $completed_purchases = $completed_stmt->fetchColumn();
    
    // Contar entregas realizadas como viajero (propuestas aceptadas y completadas)
    $deliveries_sql = "SELECT COUNT(*) 
                       FROM shop_request_proposals srp
                       INNER JOIN shop_requests sr ON srp.request_id = sr.id
                       WHERE srp.traveler_id = ? 
                       AND sr.status = 'completed'";
    $deliveries_stmt = $conexion->prepare($deliveries_sql);
    $deliveries_stmt->execute([$requester_id]);
    $total_deliveries = $deliveries_stmt->fetchColumn() ?: 0;
    
    $requests_sql = "SELECT sr.*, 
        (SELECT COUNT(*) FROM shop_request_proposals WHERE request_id = sr.id) as proposal_count
        FROM shop_requests sr 
        WHERE sr.user_id = ? AND sr.status NOT IN ('completed', 'cancelled') 
        ORDER BY sr.created_at DESC 
        LIMIT 10";
    $requests_stmt = $conexion->prepare($requests_sql);
    $requests_stmt->execute([$requester_id]);
    $requests = $requests_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $imageUrl = '../Imagenes/user-default.jpg';
    if (!empty($requester['ruta_imagen'])) {
        $imageUrl = "../mostrar_imagen.php?id=" . $requester_id;
    }
    
} catch (PDOException $e) {
    error_log("Error en shop-requester-profile.php (Línea " . $e->getLine() . "): " . $e->getMessage());
    error_log("Query: " . $e->getTraceAsString());
    
    if (isset($_GET['debug'])) {
        die("Error SQL: " . $e->getMessage() . "<br>Línea: " . $e->getLine());
    }
    
    header('Location: shop-requests-index.php');
    exit;
}

function formatDate($d) { return $d ? date('d M Y', strtotime($d)) : 'N/A'; }
function formatMonthYear($d) { 
    if (!$d) return 'N/A';
    $months_es = [
        'January' => 'Enero', 'February' => 'Febrero', 'March' => 'Marzo',
        'April' => 'Abril', 'May' => 'Mayo', 'June' => 'Junio',
        'July' => 'Julio', 'August' => 'Agosto', 'September' => 'Septiembre',
        'October' => 'Octubre', 'November' => 'Noviembre', 'December' => 'Diciembre'
    ];
    $date_obj = new DateTime($d);
    $month = $date_obj->format('F');
    $year = $date_obj->format('Y');
    return ($months_es[$month] ?? $month) . ' ' . $year;
}
function currency($c) { return ['EUR'=>'€','USD'=>'$','BOB'=>'Bs'][$c] ?? '€'; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($requester['full_name']) ?> - SendVialo Shop</title>
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="../Imagenes/globo5.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <?php incluirEstilosInsignias(); ?>
    
    <style>
        :root {
            --primary: #41ba0d;
            --primary-dark: #369a0b;
            --primary-light: #5dcb2a;
            --secondary: #1a1a2e;
            --accent: #79dd46;
            --dark: #1a1a2e;
            --dark-light: #2d2d44;
            --gray: #64748b;
            --gray-light: #94a3b8;
            --light: #f8f9fa;
            --white: #ffffff;
            --gradient-1: linear-gradient(135deg, #41ba0d 0%, #5dcb2a 100%);
            --gradient-2: linear-gradient(135deg, #1a1a2e 0%, #2d2d44 100%);
            --gradient-3: linear-gradient(135deg, #5dcb2a 0%, #79dd46 100%);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--light);
            color: var(--dark);
            min-height: 100vh;
            overflow-x: hidden;
            padding-top: 120px;
            padding-bottom: 250px !important; /* Espacio generoso para footer + navegación móvil */
        }

        /* ==================== HERO SECTION ==================== */
        .hero {
            background: var(--light);
            padding: clamp(20px, 4vw, 40px);
            padding-bottom: 20px;
            overflow: hidden;
            width: 100%;
            max-width: 100%;
        }

        .hero-content {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 20px;
            overflow: visible;
        }

        /* ==================== PROFILE CARD ==================== */
        .profile-card {
            background: var(--white);
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            padding: clamp(24px, 4vw, 32px);
            display: flex;
            align-items: center;
            gap: clamp(16px, 3vw, 28px);
            width: 100%;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            overflow: visible !important;
        }

        .profile-avatar {
            position: relative;
            flex-shrink: 0;
            overflow: visible !important;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 180px;
            height: 180px;
        }
        
        .profile-avatar .profile-img-container {
            position: relative;
            display: inline-block;
            width: 130px;
            height: 130px;
        }

        .profile-avatar .profile-img {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            object-fit: cover;
            position: relative;
            z-index: 2;
        }

        .profile-avatar .laurel-crown {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 200px;
            height: 200px;
            z-index: 1;
        }

        .profile-avatar .verificacion-wrapper {
            position: absolute;
            bottom: -10px;
            right: -10px;
            z-index: 6;
        }

        .profile-avatar .verificacion-insignia {
            width: 50px;
            height: 50px;
            z-index: 5;
        }

        .profile-info {
            flex: 1;
            color: var(--dark);
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .profile-info h1 {
            font-size: clamp(1.5rem, 4vw, 2.2rem);
            font-weight: 700;
            margin: 0;
            color: var(--dark);
            line-height: 1.2;
        }
        
        .profile-info .username {
            font-size: clamp(0.9rem, 2vw, 1rem);
            color: var(--gray);
            font-weight: 500;
            margin: 0;
        }

        .profile-info .location {
            color: var(--gray);
            font-size: clamp(0.85rem, 2vw, 0.95rem);
            display: flex;
            align-items: center;
            gap: 6px;
            margin: 4px 0;
        }

        .rating-display {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-top: 8px;
        }

        .rating-number {
            font-size: clamp(2.5rem, 5vw, 3.5rem);
            font-weight: 800;
            background: var(--gradient-1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
        }
        
        .rating-details {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .rating-stars {
            display: flex;
            gap: 3px;
        }

        .rating-stars i {
            color: #ffd700;
            font-size: clamp(16px, 2vw, 20px);
        }

        .rating-count {
            color: var(--gray);
            font-size: clamp(0.8rem, 1.5vw, 0.9rem);
        }

        /* ==================== QUICK STATS ==================== */
        .quick-stats {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            justify-content: center;
            margin-top: -10px; /* Acercar a la card de arriba */
        }

        .stat-pill {
            background: var(--white);
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--dark);
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            min-width: auto;
        }

        .stat-pill:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .stat-pill i {
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        
        .stat-pill .stat-content {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .stat-pill .num {
            font-size: 1.3rem;
            font-weight: 700;
            line-height: 1;
        }

        .stat-pill .label {
            font-size: 0.7rem;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .stat-pill.green i { color: var(--primary); }
        .stat-pill.purple i { color: var(--primary-dark); }
        .stat-pill.blue i { color: #3b82f6; }
        .stat-pill.orange i { color: var(--accent); }

        /* ==================== FOOTER FIX ==================== */
        footer {
            margin-top: 80px !important;
            position: relative !important;
            z-index: 1 !important;
            clear: both !important;
        }
        
        /* Forzar que el footer no sea fijo */
        footer, 
        footer * {
            position: relative !important;
        }
        
        /* Asegurar que la navegación móvil no tape el footer */
        .mobile-bottom-nav,
        .bottom-navigation,
        .mobile-nav,
        nav[style*="position: fixed"][style*="bottom"] {
            z-index: 9999 !important;
        }
        
        /* Asegurar que el contenido de solicitudes no se superponga */
        .requests-column {
            margin-bottom: 40px;
        }
        
        /* Espaciado adicional después del último elemento */
        .requests-column:last-child,
        .left-column:last-child {
            padding-bottom: 40px;
        }

        /* ==================== MAIN CONTAINER ==================== */
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: clamp(16px, 4vw, 40px);
            margin-bottom: 80px; /* Más espacio extra antes del footer */
        }

        /* Layout de 2 columnas: izquierda info (más ancha), derecha solicitudes */
        .content-layout {
            display: grid;
            grid-template-columns: 480px 1fr;
            gap: 30px;
            align-items: start;
        }
        
        .left-column {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        /* ==================== SECTION CARD ==================== */
        .section-card {
            background: var(--white);
            border-radius: 20px;
            padding: clamp(20px, 3vw, 28px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            transition: all 0.3s ease;
            overflow: hidden;
            width: 100%;
        }

        .section-card:hover {
            box-shadow: 0 8px 40px rgba(0,0,0,0.1);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: clamp(16px, 3vw, 24px);
            gap: 12px;
            flex-wrap: wrap;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: clamp(1.1rem, 2.5vw, 1.3rem);
            font-weight: 700;
            color: var(--dark);
        }

        .section-title .icon-box {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.1rem;
        }

        .section-title .icon-box.green { background: var(--gradient-1); }
        .section-title .icon-box.purple { background: var(--gradient-2); }

        .section-badge {
            background: var(--light);
            color: var(--gray);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .edit-button {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: white;
            border: 2px solid var(--primary);
            color: var(--primary);
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .edit-button:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(65, 186, 13, 0.3);
        }
        
        .edit-button i {
            font-size: 0.9rem;
        }

        /* ==================== INFO ITEMS ==================== */
        .info-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .info-row {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 16px;
            background: var(--light);
            border-radius: 12px;
            transition: all 0.2s ease;
        }

        .info-row:hover {
            background: #e2e8f0;
            transform: translateX(4px);
        }

        .info-row .icon {
            width: 38px;
            height: 38px;
            background: var(--white);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }

        .info-row .text {
            flex: 1;
        }

        .info-row .label {
            font-size: 0.75rem;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 2px;
        }

        .info-row .value {
            font-weight: 600;
            color: var(--dark);
        }

        /* ==================== REQUESTS GRID (COLUMNA DERECHA) ==================== */
        .requests-column {
            background: var(--white);
            border-radius: 20px;
            padding: 28px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        }

        .requests-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        /* ==================== REQUEST CARDS ==================== */
        .request-card {
            background: var(--light);
            border-radius: 16px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            width: 100%;
        }

        .request-card:hover {
            border-color: var(--primary);
            transform: translateY(-4px);
            box-shadow: 0 10px 30px rgba(65, 186, 13, 0.15);
        }

        .request-image {
            width: 100%;
            height: 180px;
            object-fit: cover;
        }

        .request-image-placeholder {
            width: 100%;
            height: 180px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
        }

        .request-content {
            padding: 16px;
        }

        .request-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 12px;
        }

        .request-title {
            font-weight: 700;
            font-size: 1rem;
            color: var(--dark);
            flex: 1;
            line-height: 1.3;
        }

        .request-price {
            font-weight: 800;
            font-size: 1.2rem;
            color: var(--primary);
            white-space: nowrap;
        }

        .request-route {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.8rem;
            color: var(--gray);
            margin-bottom: 12px;
            padding: 8px 10px;
            background: var(--white);
            border-radius: 10px;
            flex-wrap: wrap;
        }

        .request-route i { color: var(--primary); }

        .request-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .request-meta {
            display: flex;
            gap: 16px;
            font-size: 0.8rem;
            color: var(--gray);
            flex-wrap: wrap;
        }

        .request-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .urgency-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .urgency-badge.urgency-flexible { background: #dcfce7; color: #166534; }
        .urgency-badge.urgency-moderate { background: #fef3c7; color: #92400e; }
        .urgency-badge.urgency-urgent { background: #fee2e2; color: #991b1b; }

        /* ==================== EMPTY STATE ==================== */
        .empty-state {
            text-align: center;
            padding: clamp(40px, 6vw, 60px) 20px;
        }

        .empty-state .icon {
            font-size: clamp(3rem, 8vw, 4rem);
            color: #e2e8f0;
            margin-bottom: 16px;
        }

        .empty-state h3 {
            font-size: 1.2rem;
            color: var(--dark);
            margin-bottom: 8px;
        }

        .empty-state p {
            color: var(--gray);
        }

        /* ==================== RESPONSIVE ==================== */
        @media (max-width: 1200px) {
            .content-layout {
                grid-template-columns: 400px 1fr;
            }
            
            .requests-grid {
                grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            }
        }

        @media (max-width: 1024px) {
            .content-layout {
                grid-template-columns: 1fr;
            }
            
            .left-column {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 20px;
            }
            
            .requests-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            }
        }

        @media (max-width: 768px) {
            body {
                padding-top: 70px;
                padding-bottom: 280px !important; /* Mucho más espacio en móvil para footer + barra navegación fija */
            }
            
            .hero {
                padding: 30px 15px 20px 15px;
                overflow: visible;
            }
            
            .hero-content {
                flex-direction: column;
                align-items: stretch;
                overflow: visible;
                gap: 20px;
            }

            .profile-card {
                flex-direction: column;
                text-align: center;
                padding: 30px 20px;
                overflow: visible;
                align-items: center;
            }
            
            .profile-avatar {
                margin: 0 auto 20px auto;
                width: 160px;
                height: 160px;
            }
            
            .profile-avatar .profile-img-container {
                width: 110px;
                height: 110px;
            }
            
            .profile-avatar .profile-img {
                width: 110px !important;
                height: 110px !important;
            }
            
            .profile-avatar .laurel-crown {
                width: 170px !important;
                height: 170px !important;
            }
            
            .profile-avatar .verificacion-insignia {
                width: 42px !important;
                height: 42px !important;
            }

            .profile-info {
                align-items: center;
                text-align: center;
            }
            
            .profile-info h1 { 
                font-size: 1.4rem;
            }
            
            .profile-info .username {
                font-size: 0.9rem;
            }
            
            .profile-info .location { 
                justify-content: center; 
                font-size: 0.85rem;
            }
            
            .rating-display { 
                justify-content: center;
                gap: 12px;
                flex-wrap: wrap;
            }
            
            .rating-number {
                font-size: 2.2rem;
            }
            
            .rating-details {
                align-items: center;
            }

            .quick-stats {
                justify-content: center;
                gap: 12px;
                flex-wrap: wrap;
            }
            
            .stat-pill {
                padding: 14px 18px;
                min-width: 130px;
                flex: 1;
                max-width: 160px;
            }
            
            .stat-pill i {
                font-size: 1.3rem;
            }
            
            .stat-pill .num {
                font-size: 1.4rem;
            }
            
            .left-column {
                grid-template-columns: 1fr;
            }
            
            .requests-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            body {
                padding-bottom: 300px !important; /* Máximo espacio en móviles pequeños */
            }
            
            .profile-info h1 {
                font-size: 1.2rem;
            }
            
            .profile-info .username {
                font-size: 0.85rem;
            }
            
            .rating-number {
                font-size: 2rem;
            }
            
            .rating-stars i {
                font-size: 14px;
            }

            .stat-pill {
                padding: 12px 14px;
                min-width: 110px;
                gap: 8px;
            }
            
            .stat-pill i {
                font-size: 1.2rem;
            }
            
            .stat-pill .num {
                font-size: 1.2rem;
            }

            .stat-pill .label {
                font-size: 0.7rem;
            }
        }
    </style>
</head>
<body>
    <?php if (file_exists('header1.php')) include 'header1.php'; ?>

    <!-- HERO SECTION -->
    <section class="hero">
        <div class="hero-content">
            <div class="profile-card">
                <div class="profile-avatar">
                    <?php 
                    echo mostrarImagenConLaurel($imageUrl, $requester_rating, $requester['verificado'] ?? 0);
                    ?>
                </div>
                <div class="profile-info">
                    <h1><?= $requester_id === $current_user_id ? htmlspecialchars($requester['full_name']) : '@' . htmlspecialchars($requester['username']) ?></h1>
                    <?php if ($requester_id === $current_user_id): ?>
                        <div class="username">@<?= htmlspecialchars($requester['username']) ?></div>
                    <?php endif; ?>
                    <?php if ($requester['provincia'] || $requester['pais']): ?>
                        <div class="location">
                            <i class="fas fa-map-marker-alt"></i>
                            <?= htmlspecialchars($requester['provincia'] ?: '') ?><?= $requester['provincia'] && $requester['pais'] ? ', ' : '' ?><?= htmlspecialchars($requester['pais'] ?: '') ?>
                        </div>
                    <?php endif; ?>
                    <div class="rating-display">
                        <span class="rating-number"><?= $requester_rating ?: '0.0' ?></span>
                        <div class="rating-details">
                            <div class="rating-stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fa<?= $i <= round($requester_rating) ? 's' : 'r' ?> fa-star"></i>
                                <?php endfor; ?>
                            </div>
                            <div class="rating-count"><?= $total_ratings ?> valoraciones</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Stats dentro de hero-content pero fuera de profile-card -->
            <div class="quick-stats">
                <div class="stat-pill green">
                    <i class="fas fa-shopping-bag"></i>
                    <div class="stat-content">
                        <span class="num"><?= $total_requests ?></span>
                        <span class="label">Solicitudes</span>
                    </div>
                </div>
                <div class="stat-pill purple">
                    <i class="fas fa-check-circle"></i>
                    <div class="stat-content">
                        <span class="num"><?= $completed_purchases ?></span>
                        <span class="label">Compras</span>
                    </div>
                </div>
                <div class="stat-pill blue">
                    <i class="fas fa-box"></i>
                    <div class="stat-content">
                        <span class="num"><?= $total_deliveries ?></span>
                        <span class="label">Entregas</span>
                    </div>
                </div>
               
            </div>
        </div>
    </section>

    <!-- MAIN CONTENT -->
    <div class="main-container">
        <div class="content-layout">
            <!-- COLUMNA IZQUIERDA: Información -->
            <div class="left-column">
                <!-- MI INFORMACIÓN / INFORMACIÓN DEL PERFIL - VISIBLE PARA TODOS -->
                <div class="section-card">
                    <div class="section-header">
                        <div class="section-title">
                            <div class="icon-box green"><i class="fas fa-user"></i></div>
                            <?= $requester_id === $current_user_id ? 'Mi Información' : 'Información del Perfil' ?>
                        </div>
                        <?php if ($requester_id === $current_user_id): ?>
                        <a href="../mi_informacion.php" class="edit-button">
                            <i class="fas fa-edit"></i> Editar
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="info-list">
                        <?php if (!empty($requester['idiomas'])): ?>
                        <div class="info-row">
                            <div class="icon"><i class="fas fa-globe"></i></div>
                            <div class="text">
                                <div class="label">Idiomas</div>
                                <div class="value"><?= htmlspecialchars($requester['idiomas']) ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($requester['estudios'])): ?>
                        <div class="info-row">
                            <div class="icon"><i class="fas fa-graduation-cap"></i></div>
                            <div class="text">
                                <div class="label">Estudios</div>
                                <div class="value"><?= htmlspecialchars($requester['estudios']) ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($requester['trabajo'])): ?>
                        <div class="info-row">
                            <div class="icon"><i class="fas fa-briefcase"></i></div>
                            <div class="text">
                                <div class="label">Trabajo</div>
                                <div class="value"><?= htmlspecialchars($requester['trabajo']) ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($requester_id === $current_user_id && !empty($requester['email'])): ?>
                        <div class="info-row">
                            <div class="icon"><i class="fas fa-envelope"></i></div>
                            <div class="text">
                                <div class="label">Email</div>
                                <div class="value"><?= htmlspecialchars($requester['email']) ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- INFORMACIÓN DEL USUARIO -->
                <div class="section-card">
                    <div class="section-header">
                        <div class="section-title">
                            <div class="icon-box purple"><i class="fas fa-user-circle"></i></div>
                            Información del Usuario
                        </div>
                    </div>
                    <div class="info-list">
                        <div class="info-row">
                            <div class="icon"><i class="fas fa-user-plus"></i></div>
                            <div class="text">
                                <div class="label">Miembro desde</div>
                                <div class="value"><?= formatMonthYear($requester['created_at']) ?></div>
                            </div>
                        </div>
                        
                        <div class="info-row">
                            <div class="icon"><i class="fas fa-star"></i></div>
                            <div class="text">
                                <div class="label">Valoración promedio</div>
                                <div class="value"><?= $requester_rating ?> de 5 estrellas</div>
                            </div>
                        </div>
                        
                        <?php if ($requester['verificado']): ?>
                        <div class="info-row">
                            <div class="icon"><i class="fas fa-check-circle"></i></div>
                            <div class="text">
                                <div class="label">Estado</div>
                                <div class="value">✓ Usuario Verificado</div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- COLUMNA DERECHA: Solicitudes en Grid Responsive -->
            <div class="requests-column">
                <div class="section-header">
                    <div class="section-title">
                        <div class="icon-box green"><i class="fas fa-shopping-bag"></i></div>
                        Solicitudes Activas
                    </div>
                    <span class="section-badge"><?= count($requests) ?> solicitudes</span>
                </div>

                <?php if (count($requests) > 0): ?>
                    <div class="requests-grid">
                        <?php foreach ($requests as $r):
                            $images = json_decode($r['reference_images'], true);
                            $first_image = !empty($images) && is_array($images) ? $images[0] : null;
                        ?>
                        <div class="request-card" onclick="location.href='shop-request-detail.php?id=<?= $r['id'] ?>'">
                            <?php if ($first_image): ?>
                                <img src="<?= htmlspecialchars($first_image) ?>" alt="" class="request-image">
                            <?php else: ?>
                                <div class="request-image-placeholder">
                                    <i class="fas fa-shopping-bag"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="request-content">
                                <div class="request-top">
                                    <div class="request-title"><?= htmlspecialchars($r['title']) ?></div>
                                    <div class="request-price"><?= currency($r['budget_currency']) ?><?= number_format($r['budget_amount'], 2) ?></div>
                                </div>
                                <div class="request-route">
                                    <i class="fas fa-plane-departure"></i>
                                    <?= $r['origin_flexible'] ? 'Cualquier origen' : htmlspecialchars($r['origin_country'] ?? 'N/A') ?>
                                    <i class="fas fa-arrow-right"></i>
                                    <i class="fas fa-plane-arrival"></i>
                                    <?= htmlspecialchars($r['destination_city']) ?>
                                </div>
                                <div class="request-bottom">
                                    <div class="request-meta">
                                        <span><i class="far fa-calendar"></i> <?= formatDate($r['created_at']) ?></span>
                                        <span><i class="fas fa-users"></i> <?= $r['proposal_count'] ?> propuestas</span>
                                    </div>
                                    <span class="urgency-badge urgency-<?= $r['urgency'] ?>">
                                        <?php
                                        $urgency_text = [
                                            'flexible' => 'Flexible',
                                            'moderate' => 'Moderada',
                                            'urgent' => 'Urgente'
                                        ];
                                        echo $urgency_text[$r['urgency']] ?? 'Flexible';
                                        ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="icon"><i class="fas fa-inbox"></i></div>
                        <h3>Sin solicitudes activas</h3>
                        <p>Este usuario no tiene solicitudes activas actualmente</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>


    <script>
        // Script para asegurar que el footer no tape el contenido
        (function() {
            function fixFooterSpacing() {
                const footer = document.querySelector('footer');
                const mainContainer = document.querySelector('.main-container');
                const mobileNav = document.querySelector('.mobile-bottom-nav, .bottom-navigation, nav[style*="position: fixed"][style*="bottom"]');
                
                if (footer && mainContainer) {
                    // Calcular altura necesaria
                    let requiredPadding = 150; // Base
                    
                    if (mobileNav) {
                        requiredPadding += mobileNav.offsetHeight + 20;
                    }
                    
                    // Forzar el padding-bottom
                    document.body.style.paddingBottom = requiredPadding + 'px';
                    
                    // Asegurar que el footer sea relativo
                    footer.style.position = 'relative';
                    footer.style.marginTop = '80px';
                    
                    console.log('✅ Footer spacing fixed:', requiredPadding + 'px');
                }
            }
            
            // Ejecutar al cargar y al redimensionar
            window.addEventListener('load', fixFooterSpacing);
            window.addEventListener('resize', fixFooterSpacing);
            
            // Ejecutar inmediatamente también
            if (document.readyState === 'complete') {
                fixFooterSpacing();
            }
        })();
    </script>
</body>
</html>