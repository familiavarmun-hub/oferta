<?php
// perfil_shop.php - Perfil de usuario para SendVialo Shop
// Diseño moderno con glassmorphism y layout innovador

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';

// IMPORTANTE: Incluir insignias1.php ANTES de cualquier otra cosa
require_once __DIR__ . '/insignias1.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: shop-login.php');
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$imageUrl = '../Imagenes/user-default.jpg';

try {
    $stmt = $conexion->prepare("SELECT * FROM accounts WHERE id = ?");
    $stmt->execute([$usuario_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$usuario) die("Usuario no encontrado.");
    
    if (!empty($usuario['ruta_imagen'])) {
        $imageUrl = "../mostrar_imagen.php?id=" . $usuario_id;
    }

    // Valoraciones
    $stmt = $conexion->prepare("SELECT AVG(valoracion) as promedio, COUNT(*) as total FROM comentarios WHERE usuario_id = ? AND bloqueado = 0");
    $stmt->execute([$usuario_id]);
    $rating = $stmt->fetch(PDO::FETCH_ASSOC);
    $promedio_valoracion = $rating['promedio'] ? round($rating['promedio'], 1) : 0;
    $total_comentarios = $rating['total'] ?? 0;

    // Solicitudes
    $stmt = $conexion->prepare("SELECT sr.*, (SELECT COUNT(*) FROM shop_request_proposals WHERE request_id = sr.id) as total_propuestas FROM shop_requests sr WHERE sr.user_id = ? AND sr.status NOT IN ('completed', 'cancelled') ORDER BY sr.created_at DESC LIMIT 10");
    $stmt->execute([$usuario_id]);
    $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $conexion->prepare("SELECT COUNT(*) FROM shop_requests WHERE user_id = ? AND status NOT IN ('completed', 'cancelled')");
    $stmt->execute([$usuario_id]);
    $total_solicitudes = $stmt->fetchColumn();

    // Propuestas
    $stmt = $conexion->prepare("SELECT srp.*, sr.title as request_title, sr.destination_city, sr.origin_country, sr.reference_images, a.full_name as requester_name FROM shop_request_proposals srp JOIN shop_requests sr ON srp.request_id = sr.id JOIN accounts a ON sr.user_id = a.id WHERE srp.traveler_id = ? AND srp.status IN ('pending', 'accepted') ORDER BY srp.created_at DESC LIMIT 10");
    $stmt->execute([$usuario_id]);
    $propuestas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $conexion->prepare("SELECT COUNT(*) FROM shop_request_proposals WHERE traveler_id = ? AND status IN ('pending', 'accepted')");
    $stmt->execute([$usuario_id]);
    $total_propuestas = $stmt->fetchColumn();

    // Stats
    $stmt = $conexion->prepare("SELECT COUNT(*) FROM shop_requests WHERE user_id = ? AND status = 'completed'");
    $stmt->execute([$usuario_id]);
    $compras = $stmt->fetchColumn();

    $stmt = $conexion->prepare("SELECT COUNT(*) FROM shop_request_proposals WHERE traveler_id = ? AND status = 'accepted'");
    $stmt->execute([$usuario_id]);
    $entregas = $stmt->fetchColumn();

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

function getBadgeType($r) {
    if ($r >= 4.8) return 'diamond';
    if ($r >= 4.5) return 'gold';
    if ($r >= 4.0) return 'silver';
    if ($r >= 3.5) return 'bronze';
    return 'basic';
}

function formatDate($d) { return $d ? date('d M Y', strtotime($d)) : 'N/A'; }
function currency($c) { return ['EUR'=>'€','USD'=>'$','BOB'=>'Bs'][$c] ?? '€'; }

$badge = getBadgeType($promedio_valoracion);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - SendVialo Shop</title>
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="../Imagenes/globo5.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <?php 
    // Incluir estilos de insignias
    incluirEstilosInsignias(); 
    ?>
    
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
            align-items: center;
            gap: clamp(20px, 4vw, 40px);
            flex-wrap: wrap;
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
            flex: 1;
            min-width: 300px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            overflow: visible !important; /* CRÍTICO: permitir que el laurel se salga */
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
        
        /* Usar las mismas clases que mi_perfil.php */
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
            gap: 12px;
            flex-wrap: wrap;
        }

        .stat-pill {
            background: var(--white);
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--dark);
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            min-width: 140px;
        }

        .stat-pill:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .stat-pill i {
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        
        .stat-pill .stat-content {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .stat-pill .num {
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1;
        }

        .stat-pill .label {
            font-size: 0.75rem;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .stat-pill.green i { color: var(--primary); }
        .stat-pill.purple i { color: var(--primary-dark); }
        .stat-pill.orange i { color: var(--accent); }

        /* ==================== MAIN CONTAINER ==================== */
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: clamp(16px, 4vw, 40px);
        }

        /* ==================== ACTION BUTTONS ==================== */
        .action-bar {
            display: flex;
            gap: 12px;
            margin-bottom: clamp(24px, 4vw, 40px);
            flex-wrap: wrap;
        }

        .btn {
            padding: clamp(12px, 2vw, 16px) clamp(20px, 3vw, 32px);
            border-radius: 12px;
            font-weight: 600;
            font-size: clamp(0.85rem, 2vw, 0.95rem);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--gradient-1);
            color: white;
            box-shadow: 0 4px 20px rgba(65, 186, 13, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(65, 186, 13, 0.4);
        }

        .btn-secondary {
            background: var(--white);
            color: var(--dark);
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.12);
        }

        .btn-outline {
            background: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
        }

        .btn-outline:hover {
            background: var(--primary);
            color: white;
        }

        /* ==================== GRID LAYOUT ==================== */
        .grid-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: clamp(16px, 3vw, 28px);
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
            max-width: 100%;
        }

        .section-card:hover {
            box-shadow: 0 8px 40px rgba(0,0,0,0.1);
        }

        .section-card.full-width {
            grid-column: 1 / -1;
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
        .section-title .icon-box.orange { background: var(--gradient-3); }

        .section-badge {
            background: var(--light);
            color: var(--gray);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
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
        }

        .info-row .value {
            font-weight: 600;
            color: var(--dark);
        }

        /* ==================== REQUEST/PROPOSAL CARDS ==================== */
        .item-card {
            background: var(--light);
            border-radius: 16px;
            padding: 0;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            margin-bottom: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .item-card:hover {
            border-color: var(--primary);
            transform: translateY(-4px);
            box-shadow: 0 10px 30px rgba(65, 186, 13, 0.15);
        }

        .item-card:last-child { margin-bottom: 0; }
        
        .item-image {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }
        
        .item-image-placeholder {
            width: 100%;
            height: 150px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
        }
        
        .item-content {
            padding: 14px;
        }

        .item-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 12px;
        }

        .item-title {
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--dark);
            flex: 1;
        }

        .item-price {
            font-weight: 800;
            font-size: 1.1rem;
            color: var(--primary);
            white-space: nowrap;
        }

        .item-route {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.8rem;
            color: var(--gray);
            margin-bottom: 12px;
            padding: 8px 10px;
            background: var(--white);
            border-radius: 10px;
        }

        .item-route i { color: var(--primary); }

        .item-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .item-meta {
            display: flex;
            gap: 16px;
            font-size: 0.8rem;
            color: var(--gray);
        }

        .item-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status.open { background: #dcfce7; color: #166534; }
        .status.negotiating { background: #fef3c7; color: #92400e; }
        .status.pending { background: #dbeafe; color: #1e40af; }
        .status.accepted { background: #dcfce7; color: #166534; }

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
            margin-bottom: 20px;
        }

        /* ==================== SLIDER ==================== */
        .slider-controls {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .slider-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 2px solid var(--primary);
            background: transparent;
            color: var(--primary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .slider-btn:hover:not(:disabled) {
            background: var(--primary);
            color: white;
        }

        .slider-btn:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }

        .slider-counter {
            font-weight: 600;
            color: var(--gray);
            min-width: 50px;
            text-align: center;
        }

        .requests-slider-wrapper {
            overflow: hidden;
            border-radius: 16px;
            width: 100%;
            max-width: 100%;
        }

        .requests-slider-track {
            display: flex;
            transition: transform 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            width: 100%;
        }

        .requests-slider-slide {
            flex: 0 0 100%;
            padding: 0 2px;
            box-sizing: border-box;
            max-width: 100%;
        }

        /* ==================== RESPONSIVE ==================== */
        @media (max-width: 1024px) {
            .grid-layout {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            body {
                padding-top: 70px;
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

            .action-bar {
                flex-direction: column;
                gap: 10px;
            }

            .btn { 
                justify-content: center;
                width: 100%;
                padding: 12px 20px;
            }

            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .section-card {
                padding: 20px 15px;
                margin-bottom: 20px;
                overflow: hidden;
            }
        }

        @media (max-width: 480px) {
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

        /* ==================== ANIMATIONS ==================== */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .section-card {
            animation: fadeUp 0.6s ease forwards;
        }

        .section-card:nth-child(1) { animation-delay: 0.1s; }
        .section-card:nth-child(2) { animation-delay: 0.2s; }
        .section-card:nth-child(3) { animation-delay: 0.3s; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/shop-header.php'; ?>

    <!-- HERO SECTION -->
    <section class="hero">
        <div class="hero-content">
            <div class="profile-card">
                <div class="profile-avatar">
                    <?php 
                    // Usar LA MISMA función que en mi_perfil.php
                    echo mostrarImagenConLaurel($imageUrl, $promedio_valoracion, $usuario['verificado'] ?? 0);
                    ?>
                </div>
                <div class="profile-info">
                    <h1><?= htmlspecialchars($usuario['full_name']) ?></h1>
                    <div class="username">@<?= htmlspecialchars($usuario['username'] ?? 'usuario') ?></div>
                    <div class="location">
                        <i class="fas fa-map-marker-alt"></i>
                        <?= htmlspecialchars($usuario['provincia'] ?? 'Sin ubicación') ?>, <?= htmlspecialchars($usuario['pais'] ?? '') ?>
                    </div>
                    <div class="rating-display">
                        <span class="rating-number"><?= $promedio_valoracion ?: '0.0' ?></span>
                        <div class="rating-details">
                            <div class="rating-stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fa<?= $i <= round($promedio_valoracion) ? 's' : 'r' ?> fa-star"></i>
                                <?php endfor; ?>
                            </div>
                            <div class="rating-count"><?= $total_comentarios ?> valoraciones</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="quick-stats">
                <div class="stat-pill green">
                    <i class="fas fa-shopping-bag"></i>
                    <div class="stat-content">
                        <span class="num"><?= $compras ?></span>
                        <span class="label">Compras</span>
                    </div>
                </div>
                <div class="stat-pill purple">
                    <i class="fas fa-truck"></i>
                    <div class="stat-content">
                        <span class="num"><?= $entregas ?></span>
                        <span class="label">Entregas</span>
                    </div>
                </div>
                <div class="stat-pill orange">
                    <i class="fas fa-star"></i>
                    <div class="stat-content">
                        <span class="num"><?= $total_comentarios ?></span>
                        <span class="label">Reviews</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- MAIN CONTENT -->
    <div class="main-container">
        <!-- ACTION BAR -->
     

        <!-- GRID -->
        <div class="grid-layout">
            <!-- INFO PERSONAL -->
            <div class="section-card">
                <div class="section-header">
                    <div class="section-title">
                        <div class="icon-box purple"><i class="fas fa-user"></i></div>
                        Mi Información
                    </div>
                    <a href="../mi_informacion.php" class="btn btn-outline" style="padding:8px 16px; font-size:0.8rem;">
                        <i class="fas fa-edit"></i> Editar
                    </a>
                </div>
                <div class="info-list">
                    <div class="info-row">
                        <div class="icon"><i class="fas fa-globe"></i></div>
                        <div class="text">
                            <div class="label">Idiomas</div>
                            <div class="value"><?= htmlspecialchars($usuario['idiomas'] ?? 'No especificado') ?></div>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="icon"><i class="fas fa-graduation-cap"></i></div>
                        <div class="text">
                            <div class="label">Estudios</div>
                            <div class="value"><?= htmlspecialchars($usuario['estudios'] ?? 'No especificado') ?></div>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="icon"><i class="fas fa-briefcase"></i></div>
                        <div class="text">
                            <div class="label">Trabajo</div>
                            <div class="value"><?= htmlspecialchars($usuario['trabajo'] ?? 'No especificado') ?></div>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="icon"><i class="fas fa-envelope"></i></div>
                        <div class="text">
                            <div class="label">Email</div>
                            <div class="value"><?= htmlspecialchars($usuario['email'] ?? 'No especificado') ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SOLICITUDES -->
            <div class="section-card">
                <div class="section-header">
                    <div class="section-title">
                        <div class="icon-box green"><i class="fas fa-shopping-bag"></i></div>
                        Mis Solicitudes
                    </div>
                    <div class="slider-controls" <?= count($solicitudes) <= 1 ? 'style="display:none"' : '' ?>>
                        <button class="slider-btn" id="prevSol" onclick="prevSol()"><i class="fas fa-chevron-left"></i></button>
                        <span class="slider-counter" id="solCounter">1/<?= count($solicitudes) ?></span>
                        <button class="slider-btn" id="nextSol" onclick="nextSol()"><i class="fas fa-chevron-right"></i></button>
                    </div>
                </div>

                <?php if (count($solicitudes) > 0): ?>
                    <div class="requests-slider-wrapper">
                        <div class="requests-slider-track" id="solTrack">
                            <?php foreach ($solicitudes as $s): 
                                $images = json_decode($s['reference_images'], true);
                                $first_image = !empty($images) && is_array($images) ? $images[0] : null;
                            ?>
                            <div class="requests-slider-slide">
                                <div class="item-card" onclick="location.href='shop-request-detail.php?id=<?= $s['id'] ?>'">
                                    <?php if ($first_image): ?>
                                        <img src="<?= htmlspecialchars($first_image) ?>" alt="" class="item-image">
                                    <?php else: ?>
                                        <div class="item-image-placeholder">
                                            <i class="fas fa-shopping-bag"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="item-content">
                                        <div class="item-top">
                                            <div class="item-title"><?= htmlspecialchars($s['title']) ?></div>
                                            <div class="item-price"><?= currency($s['budget_currency']) ?><?= number_format($s['budget_amount'], 2) ?></div>
                                        </div>
                                        <div class="item-route">
                                            <i class="fas fa-plane-departure"></i>
                                            <?= $s['origin_flexible'] ? 'Cualquier origen' : htmlspecialchars($s['origin_country'] ?? 'N/A') ?>
                                            <i class="fas fa-arrow-right"></i>
                                            <i class="fas fa-plane-arrival"></i>
                                            <?= htmlspecialchars($s['destination_city']) ?>
                                        </div>
                                        <div class="item-bottom">
                                            <div class="item-meta">
                                                <span><i class="far fa-calendar"></i> <?= formatDate($s['created_at']) ?></span>
                                                <span><i class="fas fa-users"></i> <?= $s['total_propuestas'] ?> propuestas</span>
                                            </div>
                                            <span class="status <?= $s['status'] ?>"><?= $s['status'] === 'open' ? 'Abierta' : 'Negociando' ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="icon"><i class="fas fa-shopping-bag"></i></div>
                        <h3>Sin solicitudes activas</h3>
                        <p>Crea tu primera solicitud y recibe propuestas</p>
                        <a href="shop-request-create.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Crear Solicitud
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- PROPUESTAS -->
            <div class="section-card full-width">
                <div class="section-header">
                    <div class="section-title">
                        <div class="icon-box orange"><i class="fas fa-paper-plane"></i></div>
                        Mis Propuestas Enviadas
                    </div>
                    <span class="section-badge"><?= $total_propuestas ?> activas</span>
                </div>

                <?php if (count($propuestas) > 0): ?>
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:16px;">
                        <?php foreach (array_slice($propuestas, 0, 4) as $p): 
                            $images = json_decode($p['reference_images'], true);
                            $first_image = !empty($images) && is_array($images) ? $images[0] : null;
                        ?>
                        <div class="item-card" onclick="location.href='shop-request-detail.php?id=<?= $p['request_id'] ?>'">
                            <?php if ($first_image): ?>
                                <img src="<?= htmlspecialchars($first_image) ?>" alt="" class="item-image">
                            <?php else: ?>
                                <div class="item-image-placeholder">
                                    <i class="fas fa-shopping-bag"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="item-content">
                                <div class="item-top">
                                <div class="item-title"><?= htmlspecialchars($p['request_title']) ?></div>
                                <div class="item-price"><?= currency($p['proposed_currency']) ?><?= number_format($p['proposed_price'], 2) ?></div>
                            </div>
                            <div class="item-route">
                                <i class="fas fa-user"></i> <?= htmlspecialchars($p['requester_name']) ?>
                                <span style="margin-left:auto; display:flex; align-items:center; gap:6px;">
                                    <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($p['destination_city']) ?>
                                </span>
                            </div>
                            <div class="item-bottom">
                                <div class="item-meta">
                                    <span><i class="far fa-calendar"></i> <?= formatDate($p['created_at']) ?></span>
                                </div>
                                <span class="status <?= $p['status'] ?>"><?= $p['status'] === 'pending' ? 'Pendiente' : 'Aceptada' ?></span>
                            </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($propuestas) > 4): ?>
                        <div style="text-align:center; margin-top:20px;">
                            <a href="shop-my-proposals.php" class="btn btn-outline">Ver todas las propuestas</a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="icon"><i class="fas fa-paper-plane"></i></div>
                        <h3>Sin propuestas enviadas</h3>
                        <p>Explora solicitudes y ofrece tus servicios como viajero</p>
                        <a href="shop-requests-index.php" class="btn btn-primary">
                            <i class="fas fa-search"></i> Explorar Solicitudes
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (file_exists(__DIR__ . '/shop-footer.php')) include __DIR__ . '/shop-footer.php'; ?>

    <script>
        // Slider de Solicitudes
        let solIdx = 0;
        const solTotal = <?= count($solicitudes) ?>;

        function updateSol() {
            const track = document.getElementById('solTrack');
            if (!track) return;
            track.style.transform = `translateX(-${solIdx * 100}%)`;
            const counter = document.getElementById('solCounter');
            if (counter) counter.textContent = `${solIdx + 1}/${solTotal}`;
            const prevBtn = document.getElementById('prevSol');
            const nextBtn = document.getElementById('nextSol');
            if (prevBtn) prevBtn.disabled = solIdx === 0;
            if (nextBtn) nextBtn.disabled = solIdx >= solTotal - 1;
        }

        function nextSol() { 
            if (solIdx < solTotal - 1) { 
                solIdx++; 
                updateSol(); 
            } 
        }
        
        function prevSol() { 
            if (solIdx > 0) { 
                solIdx--; 
                updateSol(); 
            } 
        }

        document.addEventListener('DOMContentLoaded', () => {
            if (solTotal > 0) updateSol();

            // Touch support para swipe
            const track = document.getElementById('solTrack');
            if (track) {
                let startX = 0;
                let isDragging = false;
                
                track.addEventListener('touchstart', e => {
                    startX = e.touches[0].clientX;
                    isDragging = true;
                }, {passive: true});
                
                track.addEventListener('touchend', e => {
                    if (!isDragging) return;
                    const diff = e.changedTouches[0].clientX - startX;
                    if (Math.abs(diff) > 50) {
                        diff > 0 ? prevSol() : nextSol();
                    }
                    isDragging = false;
                }, {passive: true});
            }
        });
    </script>
</body>
</html>