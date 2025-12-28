<?php
/**
 * shop-request-detail-elite-final-v5.php 
 * ✅ Layout Optimizado PC con Perfil Completo
 * ✅ Distribución Grid 60/40 en Desktop
 * ✅ Perfil del solicitante + Rating + Verificación
 * ✅ Botón contraoferta + Acceso a perfil
 * ✅ Sistema de contraoferta CORREGIDO
 * ✅ Muestra información de "incluye costo del producto"
 */
session_start();
require_once 'insignias1.php';
require_once '../config.php';

$request_id = $_GET['id'] ?? 0;
$user_logged_in = isset($_SESSION['usuario_id']);
$user_id = $user_logged_in ? $_SESSION['usuario_id'] : null;

if ($request_id <= 0) { header('Location: shop-requests-index.php'); exit; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Detalle Premium | SendVialo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php incluirEstilosInsignias(); ?>
    <style>
        :root {
            --primary: #41ba0d;
            --primary-dark: #2d8518;
            --primary-soft: #f0fdf4;
            --initial: #3b82f6;
            --slate-900: #0f172a;
            --slate-800: #1e293b;
            --slate-600: #475569;
            --slate-400: #94a3b8;
            --zinc-100: #f1f5f9;
        }

        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter', sans-serif; background:#f8fafc; color:var(--slate-900); -webkit-font-smoothing: antialiased; }
        
        .container-elite { max-width: 1400px; margin: 0 auto; padding: 110px 20px 60px; }

        /* === HEADER COMPACTO === */
        .price-hero-box { 
            background: var(--slate-900); 
            padding: 18px 30px; 
            border-radius: 20px; 
            color: white; 
            display: grid; 
            grid-template-columns: repeat(4, 1fr); 
            gap: 20px; 
            align-items: center; 
            margin-bottom: 20px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .price-hero-box .stat-item { text-align: center; }
        .price-hero-box .val { font-size: 24px; font-weight: 900; line-height: 1; }
        .price-hero-box .val span { color: var(--primary); font-size: 14px; margin-left: 3px; }
        .label-mini { font-size: 9px; font-weight: 800; text-transform: uppercase; color: var(--slate-400); letter-spacing: 1px; display: block; margin-bottom: 4px; }
        .stat-divider { border-left: 1px solid rgba(255,255,255,0.15); padding-left: 20px; }

        /* === GRID PRINCIPAL === */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }

        /* === GALERÍA === */
        .gallery-card { background: white; border-radius: 24px; border: 1px solid #e2e8f0; padding: 20px; }
        .main-img-wrap { 
            position: relative; 
            aspect-ratio: 16/10; 
            border-radius: 16px; 
            overflow: hidden; 
            background: #f3f4f6; 
            margin-bottom: 12px;
            cursor: pointer;
        }
        .main-img { 
            width: 100%; 
            height: 100%; 
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        .main-img-wrap:hover .main-img { transform: scale(1.04); }
        
        .urgency-badge {
            position: absolute;
            top: 12px;
            left: 12px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 6px 14px;
            border-radius: 100px;
            display: flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .urgency-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--primary);
        }
        .urgency-dot.urgent {
            background: #ef4444;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .urgency-text {
            font-size: 8px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: var(--slate-900);
        }
        
        .thumb-nav { 
            display: flex; 
            gap: 8px; 
            overflow-x: auto; 
            padding-bottom: 3px; 
            scrollbar-width: thin;
        }
        .thumb-nav::-webkit-scrollbar { height: 3px; }
        .thumb-nav::-webkit-scrollbar-track { background: #f1f5f9; }
        .thumb-nav::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 2px; }
        .thumb-nav img { 
            width: 65px; 
            height: 50px; 
            border-radius: 10px; 
            object-fit: cover; 
            cursor: pointer; 
            opacity: 0.4; 
            border: 2px solid transparent; 
            transition: 0.3s; 
            flex-shrink: 0;
        }
        .thumb-nav img.active { 
            opacity: 1; 
            border-color: var(--primary); 
            transform: scale(1.05);
            box-shadow: 0 3px 10px rgba(65, 186, 13, 0.3);
        }
        .thumb-nav img:hover { opacity: 0.8; }
        .no-image-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            color: var(--slate-400);
            height: 100%;
        }
        .no-image-placeholder i { font-size: 40px; margin-bottom: 12px; }

        /* === SIDEBAR DE CONTENIDO === */
        .content-card { background: white; border-radius: 24px; border: 1px solid #e2e8f0; padding: 25px; }
        .product-title { font-size: 26px; font-weight: 900; letter-spacing: -1px; margin-bottom: 12px; line-height: 1.1; }
        .product-desc { font-size: 14px; line-height: 1.7; color: var(--slate-600); white-space: pre-wrap; margin-bottom: 20px; }

/* === PRECIO === */
.price-section {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid #f1f5f9;
}
.price-row {
    display: flex;
    align-items: center;
    gap: 16px;
}
.price-main {
    font-size: 48px;
    font-weight: 900;
    letter-spacing: -2px;
    color: #000;
}
.price-badge {
    font-size: 10px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 2px;
    color: var(--primary);
    background: rgba(65, 186, 13, 0.1);
    padding: 6px 14px;
    border-radius: 100px;
    border: 1px solid rgba(65, 186, 13, 0.2);
}
.quantity-badge-detail {
    background: #eef9e7;
    color: var(--primary);
    padding: 8px 16px;
    border-radius: 100px;
    font-size: 13px;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.quantity-badge-detail i {
    font-size: 12px;
}
        /* === LOGÍSTICA === */
        .logistics-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px; }
        .log-item { background: #f1f5f9; padding: 14px; border-radius: 16px; display: flex; align-items: center; gap: 10px; }
        .log-item i { width: 32px; height: 32px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; color: var(--slate-400); box-shadow: 0 2px 5px rgba(0,0,0,0.05); flex-shrink: 0; }
        .log-val { font-size: 12px; font-weight: 800; color: var(--slate-900); text-transform: uppercase; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        /* === INFORMACIÓN DE PRESUPUESTO === */
        .budget-info-card {
            background: var(--primary-soft);
            border: 1px solid rgba(65, 186, 13, 0.2);
            border-radius: 16px;
            padding: 16px;
            margin: 20px 0;
        }
        .budget-info-card .info-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }
        .budget-info-card .info-header i {
            color: var(--primary);
            font-size: 16px;
        }
        .budget-info-card .info-content {
            font-size: 13px;
            line-height: 1.6;
            color: var(--slate-600);
            font-weight: 600;
        }
        .budget-info-card .info-content i {
            color: var(--primary);
            margin-right: 6px;
        }

        /* === PERFIL DEL SOLICITANTE === */
        .profile-card-premium {
            background: #f8fafc;
            padding: 24px;
            border-radius: 24px;
            border: 1px solid #f1f5f9;
            margin-bottom: 24px;
        }

        .profile-header-premium {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
        }

        .profile-avatar-premium {
            position: relative;
            width: 64px;
            height: 64px;
            border-radius: 50%;
            overflow: hidden;
            box-shadow: 0 6px 16px rgba(0,0,0,0.1);
            flex-shrink: 0;
        }

        .profile-avatar-premium img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .verified-badge-premium {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 22px;
            height: 22px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 11px;
            border: 3px solid #fff;
        }

        .profile-info-premium {
            flex: 1;
        }

        .profile-name-premium {
            font-size: 16px;
            font-weight: 900;
            color: #000;
            margin-bottom: 6px;
        }

        .profile-rating-premium {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .stars-premium {
            display: flex;
            color: var(--primary);
            font-size: 12px;
            gap: 2px;
        }

        .rating-count-premium {
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--slate-400);
        }

        .profile-btn-premium {
            width: 100%;
            padding: 14px;
            background: #fff;
            border: 2px solid #000;
            border-radius: 16px;
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #000;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .profile-btn-premium:hover {
            background: #000;
            color: #fff;
        }

        /* === NEGOCIACIÓN === */
        .neg-tabs-nav { display: flex; gap: 10px; overflow-x: auto; padding: 8px 5px; margin-bottom: 20px; scrollbar-width: none; }
        .tab-btn { background: white; border: 1.5px solid #e2e8f0; padding: 10px 16px; border-radius: 20px; cursor: pointer; display: flex; align-items: center; gap: 10px; min-width: 220px; transition: 0.3s; }
        .tab-btn.active { border-color: var(--primary); background: var(--primary-soft); box-shadow: 0 8px 16px rgba(65, 186, 13, 0.1); min-width: 280px; }
        .tab-avatar { width: 42px; height: 42px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 6px rgba(0,0,0,0.1); object-fit: cover; }
        .tab-meta { text-align: left; }
        
        .turn-badge { font-size: 8px; font-weight: 900; text-transform: uppercase; padding: 3px 8px; border-radius: 6px; margin-top: 4px; display: inline-block; }
        .turn-me { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
        .turn-them { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }

        .neg-panel { background: white; border-radius: 24px; border: 1px solid #e2e8f0; padding: 25px; display: grid; grid-template-columns: 1.3fr 1fr; gap: 30px; }
        .history-item { padding: 12px 16px; border-radius: 14px; border-left: 4px solid #ddd; margin-bottom: 10px; }
        .history-item.initial { background: #eff6ff; border-color: var(--initial); }
        .history-item.current { background: var(--primary-soft); border-color: var(--primary); border-width: 2px; }

        /* === BOTONES === */
        .btn-elite { width: 100%; padding: 14px; border-radius: 14px; font-weight: 800; font-size: 12px; text-transform: uppercase; border: none; cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 6px 16px rgba(65, 186, 13, 0.3); }
        .btn-dark { background: var(--slate-900); color: white; }
        .btn-outline { background: white; border: 2px solid var(--slate-900); color: var(--slate-900); }
        .btn-outline:hover { background: var(--slate-900); color: white; }
        .btn-reject { background: #fef2f2; color: #ef4444; border: 1px solid #fee2e2; }
        .btn-reject:hover { background: #ef4444; color: white; }

        .action-buttons-premium { display: flex; flex-direction: column; gap: 12px; }

        /* === ALERTS === */
        .alert-status { padding: 16px; border-radius: 16px; text-align: center; font-weight: 700; margin-bottom: 16px; font-size: 13px; }
        .alert-waiting { background: #f1f5f9; color: var(--slate-600); border: 2px dashed #e2e8f0; }
        .alert-action { background: #eff6ff; color: #1e40af; border: 2px solid #bfdbfe; }

        /* === MODAL === */
        .modal-elite { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(8px); z-index: 9999; align-items: center; justify-content: center; padding: 20px; }
        .modal-content { background: white; border-radius: 28px; width: 100%; max-width: 460px; padding: 35px; }
        .elite-input { width: 100%; padding: 14px; border: 2px solid #f1f5f9; border-radius: 12px; margin-bottom: 12px; font-family: inherit; font-weight: 600; outline: none; font-size: 14px; }
        .elite-input:focus { border-color: var(--primary); }

        /* === RESPONSIVE DESKTOP === */
        @media (min-width: 1024px) {
            .container-elite { padding: 100px 30px 60px; }
            
            .content-grid {
                grid-template-columns: 1.3fr 1fr;
                gap: 25px;
            }
            
            .product-title { font-size: 28px; }
            .product-desc { font-size: 15px; }
            
            .neg-panel { gap: 35px; }
        }

        @media (max-width: 768px) {
            .container-elite { padding-top: 85px; }
            .price-hero-box { grid-template-columns: 1fr 1fr; padding: 16px; }
            .price-hero-box .val { font-size: 20px; }
            .price-hero-box .stat-divider { border: none; padding: 0; }
            .neg-panel { grid-template-columns: 1fr; gap: 20px; }
            .product-title { font-size: 24px; }
            .logistics-grid { grid-template-columns: 1fr; }
            .content-grid { grid-template-columns: 1fr; }
          .price-section { gap: 10px; }
.price-row { flex-wrap: wrap; }
.price-main { font-size: 36px; }
            
            .profile-card-premium { padding: 16px; border-radius: 20px; margin-bottom: 20px; }
            .profile-header-premium { gap: 12px; margin-bottom: 16px; }
            .profile-avatar-premium { width: 48px; height: 48px; }
            .verified-badge-premium { width: 16px; height: 16px; font-size: 8px; }
            .profile-name-premium { font-size: 14px; }
            .stars-premium { font-size: 10px; }
        }

        /* UTILIDADES */
        .loading-state { text-align: center; padding: 60px 20px; }
        .loading-state i { font-size: 2.5rem; color: var(--primary); animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>

<?php if(file_exists('header1.php')) include 'header1.php'; ?>

<main class="container-elite">
    <!-- HEADER COMPACTO -->
    <div class="price-hero-box">
        <div class="stat-item">
            <span class="label-mini">Presupuesto</span>
            <div class="val" id="price-text">0 <span>...</span></div>
        </div>
        <div class="stat-item stat-divider">
            <span class="label-mini">Propuestas</span>
            <div class="val" id="count-text">0</div>
        </div>
        <div class="stat-item stat-divider">
            <span class="label-mini">Origen</span>
            <div class="val" id="origin-text" style="font-size: 13px; font-weight: 700;">...</div>
        </div>
        <div class="stat-item stat-divider">
            <span class="label-mini">Destino</span>
            <div class="val" id="dest-text" style="font-size: 13px; font-weight: 700;">...</div>
        </div>
    </div>

    <!-- GRID PRINCIPAL: GALERÍA + CONTENIDO -->
    <div class="content-grid">
        <!-- GALERÍA -->
        <div class="gallery-card" id="gallery-root">
            <div class="loading-state"><i class="fas fa-circle-notch fa-spin"></i></div>
        </div>

        <!-- SIDEBAR: TÍTULO + INFO + PERFIL + ACCIONES -->
        <div class="content-card">
            <h1 class="product-title" id="title-text">Cargando...</h1>
            
          <div class="price-section">
    <div class="price-row">
        <div class="price-main" id="price-main-text">0€</div>
        <div class="price-badge">Presupuesto</div>
    </div>
    <div id="quantity-badge-container"></div>
</div>
            
            <div class="logistics-grid">
                <div class="log-item">
                    <i class="fas fa-calendar"></i>
                    <div style="overflow:hidden"><span class="label-mini">Urgencia</span><div class="log-val" id="urgency-text">...</div></div>
                </div>
                <div class="log-item">
                    <i class="fas fa-box" style="color:var(--primary)"></i>
                    <div style="overflow:hidden"><span class="label-mini">Estado</span><div class="log-val" id="status-text">...</div></div>
                </div>
            </div>

            <!-- INFORMACIÓN DE PRESUPUESTO -->
            <div class="budget-info-card">
                <div class="info-header">
                    <i class="fas fa-info-circle"></i>
                    <span class="label-mini" style="color: var(--slate-900);">INFORMACIÓN DE PRESUPUESTO</span>
                </div>
                <div class="info-content" id="budget-info-text"></div>
            </div>

            <h3 class="label-mini" style="margin-bottom:8px; color:var(--slate-900)">Descripción</h3>
            <p class="product-desc" id="desc-text"></p>

            <!-- PERFIL DEL SOLICITANTE -->
            <div id="profile-section"></div>

            <!-- ACCIONES -->
            <div id="actions-sidebar"></div>
        </div>
    </div>

    <!-- NEGOCIACIÓN -->
    <div class="negotiation-section" id="neg-section" style="display:none;">
        <h2 style="font-weight:900; font-size:22px; margin-bottom:16px;">Panel de Negociación</h2>
        <div class="neg-tabs-nav" id="tabs-nav"></div>
        <div id="proposal-content-root"></div>
    </div>
</main>

<!-- MODAL CONTRAOFERTA -->
<div class="modal-elite" id="negotiation-modal">
    <div class="modal-content">
        <h3 style="font-weight:900; margin-bottom:18px; font-size:20px;">Hacer Contraoferta</h3>
        <form id="counteroffer-form">
            <input type="hidden" name="proposal_id" id="modal-proposal-id">
            <input type="hidden" name="request_id" id="modal-request-id">
            <div style="display:grid; grid-template-columns: 1fr 100px; gap:10px;">
                <input type="number" name="proposed_price" class="elite-input" placeholder="Precio" required step="0.01">
                <select name="proposed_currency" class="elite-input"><option value="EUR">EUR</option><option value="USD">USD</option><option value="BOB">BOB</option></select>
            </div>
            <input type="date" name="estimated_delivery" class="elite-input" required min="<?=date('Y-m-d',strtotime('+1 day'))?>">
            <textarea name="message" class="elite-input" style="height:90px; resize:none;" placeholder="Tu mensaje..." required></textarea>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                <button type="button" class="btn-elite btn-dark" onclick="closeNegModal()">Cancelar</button>
                <button type="submit" class="btn-elite btn-primary">Enviar</button>
            </div>
        </form>
    </div>
</div>

<script>
const reqId = <?=$request_id?>, curUserId = <?=$user_id??'null'?>;
let requestData = null;

document.addEventListener('DOMContentLoaded', loadData);

async function loadData() {
    try {
        const res = await fetch(`shop-requests-actions.php?action=get_request_detail&id=${reqId}`);
        const d = await res.json();
        if(d.success) {
            requestData = d.request;
            renderBase();
            if(requestData.proposals && requestData.proposals.length > 0) renderNegotiationTabs();
        } else {
            showError('No se pudo cargar la solicitud');
        }
    } catch(e) { 
        console.error(e);
        showError('Error al cargar');
    }
}

function renderBase() {
    const r = requestData;
    
    // Datos del perfil
    const rating = parseFloat(r.requester_rating) || 0;
    const isVerified = r.requester_verified || false;
    const requesterAvatar = r.requester_avatar_id > 0 
        ? `../mostrar_imagen.php?id=${r.requester_avatar_id}`
        : `https://ui-avatars.com/api/?name=${encodeURIComponent(r.requester_name)}&background=41ba0d&color=fff&size=64`;
    
    document.getElementById('title-text').textContent = r.title;
    document.getElementById('desc-text').textContent = r.description || 'Sin descripción';
    document.getElementById('price-text').innerHTML = `${formatPrice(r.budget_amount, r.budget_currency)}`;
    document.getElementById('price-main-text').innerHTML = formatPrice(r.budget_amount, r.budget_currency);
    document.getElementById('count-text').textContent = r.proposals ? r.proposals.length : 0;
    document.getElementById('origin-text').textContent = r.origin_flexible ? 'Global' : (r.origin_country || 'N/A');
    document.getElementById('dest-text').textContent = r.destination_city;
    document.getElementById('urgency-text').textContent = {flexible: 'Flexible', moderate: 'Moderada', urgent: 'Urgente'}[r.urgency] || 'Flexible';
    document.getElementById('status-text').textContent = {open: 'Abierta', in_progress: 'En Curso', completed: 'Completada', cancelled: 'Cancelada'}[r.status] || r.status;
    // MOSTRAR CANTIDAD
const quantity = parseInt(r.quantity) || 1;
if(quantity > 1) {
    document.getElementById('quantity-badge-container').innerHTML = `
        <div class="quantity-badge-detail">
            <span>${quantity} unidades </span>
        </div>
    `;
}

    // INFORMACIÓN DE PRESUPUESTO
    const includesProduct = r.includes_product_cost == 1 || r.includes_product_cost === true || r.includes_product_cost === '1';
    document.getElementById('budget-info-text').innerHTML = includesProduct 
        ? '<i class="fas fa-shopping-bag"></i><b>Incluye costo del producto:</b> El viajero comprará el artículo con este presupuesto.'
        : '<i class="fas fa-hand-holding-usd"></i><b>Solo comisión:</b> Este monto es únicamente la comisión por el envío. El producto se proveerá por separado.';

    // GALERÍA
    const imgs = getRequestImages(r);
    const urgencyClass = r.urgency === 'urgent' ? 'urgent' : '';
    const urgencyText = {flexible: 'Flexible', moderate: 'Moderada', urgent: 'Urgente'}[r.urgency] || 'Flexible';
    
    if(imgs.length > 0) {
        let html = `
            <div class="main-img-wrap" onclick="openImageModal('${imgs[0]}')">
                <img src="${imgs[0]}" id="main-v" class="main-img" onerror="this.src='https://via.placeholder.com/800x500/f3f4f6/94a3b8?text=Imagen+No+Disponible'">
                <div class="urgency-badge">
                    <div class="urgency-dot ${urgencyClass}"></div>
                    <span class="urgency-text">Urgencia: ${urgencyText}</span>
                </div>
            </div>
        `;
        
        if(imgs.length > 1) {
            html += `<div class="thumb-nav">`;
            imgs.forEach((img, i) => {
                html += `<img src="${img}" onclick="setMainImage('${img}', this)" class="${i === 0 ? 'active' : ''}" onerror="this.style.display='none'">`;
            });
            html += `</div>`;
        }
        
        document.getElementById('gallery-root').innerHTML = html;
    } else {
        document.getElementById('gallery-root').innerHTML = `
            <div class="main-img-wrap">
                <div class="no-image-placeholder">
                    <i class="fas fa-image"></i>
                    <p style="font-weight:700; text-transform:uppercase; letter-spacing:1px; font-size:10px; margin-top:8px;">Sin Fotos</p>
                </div>
            </div>
        `;
    }

    // PERFIL DEL SOLICITANTE
    const profileHTML = `
        <div class="profile-card-premium">
            <div class="profile-header-premium">
                <div class="profile-avatar-premium">
                    <img src="${requesterAvatar}" alt="${r.requester_name}">
                    ${isVerified ? '<div class="verified-badge-premium"><i class="fas fa-check"></i></div>' : ''}
                </div>
                <div class="profile-info-premium">
                    <h4 class="profile-name-premium">${r.requester_name}</h4>
                    <div class="profile-rating-premium">
                        <div class="stars-premium">${generateStars(rating)}</div>
                        <span class="rating-count-premium">(${r.requester_total_ratings || 0} valoraciones)</span>
                    </div>
                </div>
            </div>
            <a href="shop-demand-profile.php?id=${r.user_id}" class="profile-btn-premium">
                <i class="fas fa-user-circle"></i> Ver Perfil Completo
            </a>
        </div>
    `;
    document.getElementById('profile-section').innerHTML = profileHTML;

    // ACCIONES
    const isOwner = curUserId == r.user_id;
    const hasApplied = r.proposals && r.proposals.some(p => p.traveler_id == curUserId);
    
    if(curUserId && !isOwner && r.status === 'open' && !hasApplied) {
        document.getElementById('actions-sidebar').innerHTML = `
            <div class="action-buttons-premium">
                <button class="btn-elite btn-primary" onclick="acceptDirect()">
                    <i class="fas fa-handshake"></i> Aceptar Presupuesto
                </button>
                <button class="btn-elite btn-outline" onclick="openNegModal()">
                    <i class="fas fa-exchange-alt"></i> Hacer Contraoferta
                </button>
            </div>
        `;
    } else if(!curUserId) {
        document.getElementById('actions-sidebar').innerHTML = `
            <div class="action-buttons-premium">
                <a href="shop-login.php" class="btn-elite btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Iniciar sesión
                </a>
            </div>
        `;
    }
}

function renderNegotiationTabs() {
    const r = requestData;
    const isOwner = curUserId == r.user_id;
    const props = isOwner ? r.proposals : r.proposals.filter(p => p.traveler_id == curUserId);
    if(props.length === 0) return;

    document.getElementById('neg-section').style.display = 'block';
    
    document.getElementById('tabs-nav').innerHTML = props.map((p, i) => {
        const isMyTurn = (p.last_offer_by && p.last_offer_by != curUserId && p.status === 'pending');
        const avatar = p.traveler_avatar_id > 0 
            ? `../mostrar_imagen.php?id=${p.traveler_avatar_id}` 
            : `https://ui-avatars.com/api/?name=${encodeURIComponent(p.traveler_name)}&background=41ba0d&color=fff&size=42`;
        
        const isActive = i === 0;
        const travelerRating = p.traveler_rating || 0;
        const travelerIsVerified = p.traveler_verificado === 1;
        
        return `<div class="tab-btn ${isActive?'active':''}" onclick="switchTab(${p.id}, this)" style="${isActive ? 'flex-direction: column; align-items: stretch; padding: 20px;' : ''}">
            <div style="display: flex; align-items: center; gap: 10px;">
                <img src="${avatar}" class="tab-avatar">
                <div class="tab-meta" style="flex: 1;">
                    <div style="font-size:12px; font-weight:700; color:var(--slate-900);">${p.traveler_name}</div>
                    <div style="font-weight:900; font-size:13px;">${formatPrice(p.current_price || p.proposed_price, p.current_currency || p.proposed_currency)}</div>
                    <div class="turn-badge ${isMyTurn?'turn-me':'turn-them'}">${isMyTurn?'Tu Turno':'Esperando'}</div>
                </div>
            </div>
            ${isActive ? `
                <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid rgba(0,0,0,0.05);">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                        <div class="stars-premium">${generateStars(travelerRating)}</div>
                        <span style="font-size: 9px; font-weight: 800; text-transform: uppercase; letter-spacing: 1.5px; color: var(--slate-400);">(${p.traveler_total_ratings || 0} valoraciones)</span>
                    </div>
                    <a href="shop-demand-profile.php?id=${p.traveler_id}" class="profile-btn-premium" onclick="event.stopPropagation();" style="font-size: 11px; padding: 12px;">
                        <i class="fas fa-user-circle"></i> Ver Perfil Completo
                    </a>
                </div>
            ` : ''}
        </div>`;
    }).join('');
    
    renderProposalPanel(props[0]);
}

function switchTab(pid, btn) {
    // Regenerar las pestañas con la nueva activa
    const r = requestData;
    const isOwner = curUserId == r.user_id;
    const props = isOwner ? r.proposals : r.proposals.filter(p => p.traveler_id == curUserId);
    
    document.getElementById('tabs-nav').innerHTML = props.map((p, i) => {
        const isMyTurn = (p.last_offer_by && p.last_offer_by != curUserId && p.status === 'pending');
        const avatar = p.traveler_avatar_id > 0 
            ? `../mostrar_imagen.php?id=${p.traveler_avatar_id}` 
            : `https://ui-avatars.com/api/?name=${encodeURIComponent(p.traveler_name)}&background=41ba0d&color=fff&size=42`;
        
        const isActive = p.id === pid;
        const travelerRating = p.traveler_rating || 0;
        const travelerIsVerified = p.traveler_verificado === 1;
        
        return `<div class="tab-btn ${isActive?'active':''}" onclick="switchTab(${p.id}, this)" style="${isActive ? 'flex-direction: column; align-items: stretch; padding: 20px;' : 'cursor: pointer;'}">
            <div style="display: flex; align-items: center; gap: 10px;">
                <img src="${avatar}" class="tab-avatar">
                <div class="tab-meta" style="flex: 1;">
                    <div style="font-size:12px; font-weight:700; color:var(--slate-900);">${p.traveler_name}</div>
                    <div style="font-weight:900; font-size:13px;">${formatPrice(p.current_price || p.proposed_price, p.current_currency || p.proposed_currency)}</div>
                    <div class="turn-badge ${isMyTurn?'turn-me':'turn-them'}">${isMyTurn?'Tu Turno':'Esperando'}</div>
                </div>
            </div>
            ${isActive ? `
                <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid rgba(0,0,0,0.05);">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                        <div class="stars-premium">${generateStars(travelerRating)}</div>
                        <span style="font-size: 9px; font-weight: 800; text-transform: uppercase; letter-spacing: 1.5px; color: var(--slate-400);">(${p.traveler_total_ratings || 0} valoraciones)</span>
                    </div>
                    <a href="shop-demand-profile.php?id=${p.traveler_id}" class="profile-btn-premium" onclick="event.stopPropagation();" style="font-size: 11px; padding: 12px;">
                        <i class="fas fa-user-circle"></i> Ver Perfil Completo
                    </a>
                </div>
            ` : ''}
        </div>`;
    }).join('');
    
    renderProposalPanel(requestData.proposals.find(i => i.id == pid));
}

function renderProposalPanel(p) {
    const isOwner = curUserId == requestData.user_id;
    const isTraveler = curUserId == p.traveler_id;
    const isMyTurn = (p.last_offer_by && p.last_offer_by != curUserId);
    
    let actionsHTML = '';
    
    if(p.status === 'pending') {
        if(isMyTurn) {
            actionsHTML = `
                <div class="alert-status alert-action"><i class="fas fa-bolt"></i> ¡Es tu turno!</div>
                <div style="display:flex; flex-direction:column; gap:10px;">
                    <button class="btn-elite btn-primary" onclick="${isOwner ? 'acceptProp' : 'acceptCounterOffer'}(${p.id})">
                        <i class="fas fa-check"></i> ${isOwner ? 'Aceptar y Pagar' : 'Aceptar Contraoferta'}
                    </button>
                    <button class="btn-elite btn-outline" onclick="openNegModal(${p.id})">
                        <i class="fas fa-exchange-alt"></i> Contraoferta
                    </button>
                    <button class="btn-elite btn-reject" onclick="rejectProp(${p.id})">
                        <i class="fas fa-times"></i> ${isOwner ? 'Rechazar' : 'Retirar'}
                    </button>
                </div>`;
        } else {
            actionsHTML = `
                <div class="alert-status alert-waiting">
                    <i class="fas fa-clock"></i> Esperando respuesta...
                </div>
                ${isTraveler ? `<button class="btn-elite btn-reject" onclick="rejectProp(${p.id})"><i class="fas fa-times"></i> Retirar</button>` : ''}
            `;
        }
    } else if(p.status === 'accepted') {
        actionsHTML = `
            <div class="alert-status" style="background:var(--primary-soft); color:var(--primary); border:2px solid var(--primary);">
                <i class="fas fa-handshake" style="font-size:20px; display:block; margin-bottom:6px;"></i>
                ¡ACEPTADA!
            </div>
        `;
    } else if(p.status === 'rejected') {
        actionsHTML = `
            <div class="alert-status" style="background:#fef2f2; color:#ef4444; border:2px solid #fee2e2;">
                <i class="fas fa-times-circle" style="font-size:20px; display:block; margin-bottom:6px;"></i>
                RECHAZADA
            </div>
        `;
    }

    document.getElementById('proposal-content-root').innerHTML = `
        <div class="neg-panel">
            <div>
                <span class="label-mini" style="color:var(--primary)">Oferta Vigente</span>
                <div style="font-size:36px; font-weight:900; margin:8px 0;">${formatPrice(p.current_price || p.proposed_price, p.current_currency || p.proposed_currency)}</div>
                ${p.current_estimated_delivery || p.estimated_delivery ? `
                    <p style="color:var(--slate-600); font-size:13px; margin-bottom:8px;">
                        <i class="fas fa-calendar"></i> ${new Date(p.current_estimated_delivery || p.estimated_delivery).toLocaleDateString('es-ES')}
                    </p>
                ` : ''}
                <p style="color:var(--slate-600); margin-bottom:20px; line-height:1.6; font-size:14px;">${p.message || 'Sin mensaje'}</p>
                ${actionsHTML}
            </div>
            <div id="hist-list-${p.id}">
                <div style="text-align:center; padding:30px; color:var(--slate-400);">
                    <i class="fas fa-spinner fa-spin" style="font-size:20px;"></i>
                </div>
            </div>
        </div>
    `;
    loadHistory(p.id);
}

async function loadHistory(pid) {
    try {
        const res = await fetch(`shop-requests-actions.php?action=get_negotiation_history&proposal_id=${pid}`);
        const d = await res.json();
        
        if(d.success && d.history && d.history.length > 0) {
            const histHtml = d.history.map((h, i) => {
                const isInitial = i === 0;
                const isCurrent = i === d.history.length - 1;
                const cls = isInitial ? 'initial' : (isCurrent ? 'current' : 'mid');
                
                return `<div class="history-item ${cls}">
                    <span class="label-mini">${isInitial ? 'INICIO' : (isCurrent ? '● ACTUAL' : 'Paso ' + i)}</span>
                    <div style="font-size:16px; font-weight:800;">${formatPrice(h.proposed_price, h.proposed_currency)}</div>
                    ${h.message ? `<p style="font-size:12px; color:var(--slate-600); margin:6px 0; line-height:1.4;">${h.message}</p>` : ''}
                    <div style="font-size:10px; color:var(--slate-400); margin-top:4px;">
                        <b>${h.nombre_usuario}</b> • ${new Date(h.created_at).toLocaleDateString('es-ES')}
                    </div>
                </div>`;
            }).reverse().join('');
            
            document.getElementById(`hist-list-${pid}`).innerHTML = `
                <span class="label-mini" style="margin-bottom:12px; display:block;">Historial</span>
                ${histHtml}
            `;
        } else {
            document.getElementById(`hist-list-${pid}`).innerHTML = `
                <span class="label-mini" style="margin-bottom:12px; display:block;">Historial</span>
                <div style="text-align:center; padding:25px; color:var(--slate-400); font-size:12px;">Sin historial</div>
            `;
        }
    } catch(e) {
        console.error(e);
    }
}

function getRequestImages(r) {
    if(r.image_paths && Array.isArray(r.image_paths) && r.image_paths.length > 0) {
        return r.image_paths;
    }
    
    if(r.reference_images) {
        let ref = r.reference_images;
        
        if(typeof ref === 'string') {
            try {
                ref = JSON.parse(ref);
            } catch(e) {
                if(ref.startsWith('http') || ref.startsWith('data:') || ref.startsWith('../')) {
                    return [ref];
                }
                return [];
            }
        }
        
        if(Array.isArray(ref)) {
            let imgs = [];
            ref.forEach(item => {
                if(!item) return;
                
                if(typeof item === 'string') {
                    imgs.push(item);
                } else if(item.data) {
                    imgs.push(item.data);
                } else if(item.url) {
                    imgs.push(item.url);
                } else if(item.path) {
                    imgs.push(item.path);
                }
            });
            return imgs;
        }
    }
    
    return [];
}

function setMainImage(src, elem) { 
    document.getElementById('main-v').src = src; 
    document.querySelectorAll('.thumb-nav img').forEach(t => t.classList.remove('active')); 
    elem.classList.add('active'); 
}

function openImageModal(src) {
    Swal.fire({
        imageUrl: src,
        imageAlt: 'Imagen',
        showConfirmButton: false,
        showCloseButton: true,
        background: 'rgba(0,0,0,.95)',
        backdrop: 'rgba(0,0,0,.9)'
    });
}

function openNegModal(id) { 
    if(id) document.getElementById('modal-proposal-id').value = id;
    document.getElementById('modal-request-id').value = reqId;
    document.getElementById('negotiation-modal').style.display = 'flex'; 
}

function closeNegModal() { 
    document.getElementById('negotiation-modal').style.display = 'none';
    document.getElementById('counteroffer-form').reset();
}

document.getElementById('negotiation-modal').addEventListener('click', function(e) {
    if(e.target === this) closeNegModal();
});

document.addEventListener('keydown', (e) => {
    if(e.key === 'Escape') closeNegModal();
});

document.getElementById('counteroffer-form').onsubmit = async (e) => {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    const origHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
    btn.disabled = true;
    
    try {
        const fd = new FormData(e.target);
        fd.append('action', 'submit_counteroffer');
        const res = await fetch('shop-requests-actions.php', { method:'POST', body:fd });
        const d = await res.json();
        
        if(d.success) { 
            closeNegModal();
            Swal.fire({
                icon: 'success',
                title: '¡Contraoferta Enviada!',
                confirmButtonColor: '#41ba0d'
            }).then(() => location.reload()); 
        } else {
            throw new Error(d.error || 'Error al enviar');
        }
    } catch(err) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: err.message,
            confirmButtonColor: '#41ba0d'
        });
        btn.innerHTML = origHTML;
        btn.disabled = false;
    }
};

function formatPrice(a, c) {
    const s = {EUR:'€', USD:'$', BOB:'Bs', BRL:'R$', ARS:'$', COP:'$', MXN:'$', PEN:'S/'};
    return `${Math.floor(a)} <span style="font-size:13px;">${s[c] || c}</span>`;
}

function generateStars(rating) {
    const full = Math.floor(rating);
    let stars = '';
    for(let i = 0; i < 5; i++) {
        stars += `<i class="fas fa-star" style="opacity:${i < full ? 1 : 0.2}"></i>`;
    }
    return stars;
}

async function acceptProp(id) { 
    location.href = `shop-payment.php?proposal_id=${id}`; 
}

async function acceptCounterOffer(id) {
    const res = await Swal.fire({ 
        title: '¿Aceptar contraoferta?', 
        text: 'Aceptarás las condiciones propuestas', 
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#41ba0d',
        confirmButtonText: 'Sí, aceptar'
    });
    
    if(res.isConfirmed) {
        const fd = new FormData(); 
        fd.append('action', 'accept_counteroffer'); 
        fd.append('proposal_id', id);
        
        try {
            const response = await fetch('shop-requests-actions.php', { method:'POST', body:fd });
            const d = await response.json();
            
            if(d.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Aceptada!',
                    confirmButtonColor: '#41ba0d'
                }).then(() => location.reload());
            } else {
                throw new Error(d.error || 'Error');
            }
        } catch(err) {
            Swal.fire({icon: 'error', title: 'Error', text: err.message, confirmButtonColor: '#41ba0d'});
        }
    }
}

async function rejectProp(id) {
    const res = await Swal.fire({ 
        title: '¿Cerrar negociación?', 
        icon: 'warning', 
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Sí, cerrar'
    });
    
    if(res.isConfirmed) {
        const fd = new FormData(); 
        fd.append('action', 'reject_proposal'); 
        fd.append('proposal_id', id);
        
        try {
            await fetch('shop-requests-actions.php', { method:'POST', body:fd });
            location.reload();
        } catch(err) {
            Swal.fire({icon: 'error', title: 'Error', text: err.message, confirmButtonColor: '#41ba0d'});
        }
    }
}

async function acceptDirect() {
    const { value: msg } = await Swal.fire({ 
        title: 'Aceptar Presupuesto', 
        text: 'Confirmar envío al precio base', 
        input: 'textarea',
        inputPlaceholder: 'Explica cómo conseguirás el producto...',
        showCancelButton: true,
        confirmButtonColor: '#41ba0d',
        confirmButtonText: 'Enviar',
        inputValidator: (value) => {
            if (!value) return '¡Escribe un mensaje!';
        }
    });
    
    if(msg) {
        const fd = new FormData();
        fd.append('action', 'submit_proposal');
        fd.append('request_id', reqId);
        fd.append('proposed_price', requestData.budget_amount);
        fd.append('proposed_currency', requestData.budget_currency);
        fd.append('message', msg);
        
        try {
            await fetch('shop-requests-actions.php', { method:'POST', body:fd });
            location.reload();
        } catch(err) {
            Swal.fire({icon: 'error', title: 'Error', text: err.message, confirmButtonColor: '#41ba0d'});
        }
    }
}

function showError(msg) {
    document.getElementById('gallery-root').innerHTML = `
        <div style="text-align:center; padding:50px 20px;">
            <i class="fas fa-exclamation-triangle" style="font-size:40px; color:#ef4444; margin-bottom:16px;"></i>
            <h3 style="font-size:18px; font-weight:900; margin-bottom:10px;">${msg}</h3>
            <a href="shop-requests-index.php" class="btn-elite btn-primary" style="display:inline-flex; max-width:180px; margin-top:16px;">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    `;
}
</script>
</body>
</html>