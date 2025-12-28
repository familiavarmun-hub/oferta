<?php
// shop-requests-index-elite-final.php
session_start();
require_once 'insignias1.php';
require_once '../config.php';

$user_logged_in = isset($_SESSION['usuario_id']);
$user_id = $user_logged_in ? $_SESSION['usuario_id'] : null;
$user_name = $user_logged_in ? ($_SESSION['usuario_nombre'] ?? $_SESSION['full_name'] ?? 'Usuario') : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
  <title>Marketplace Elite | SendVialo</title>

  <link rel="stylesheet" href="../css/estilos.css"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link rel="icon" href="../Imagenes/globo5.png" type="image/png"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

  <?php incluirEstilosInsignias(); ?>

<style>
:root {
  --primary: #41ba0d;
  --primary-dark: #2d8518;
  --primary-soft: #f1fcf0;
  --slate-900: #0f172a;
  --slate-600: #475569;
  --slate-400: #94a3b8;
  --zinc-100: #f1f5f9;
  --bg-body: #f8fafc;
  --danger: #ef4444;
}

* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Inter', sans-serif; background-color: var(--bg-body); color: var(--slate-900); -webkit-font-smoothing: antialiased; }

/* ========== HERO & FULL-WIDTH SEARCH ========== */
.hero-header { 
  background: white; 
  padding: 85px 20px 20px; 
  border-bottom: 1px solid #eee; 
}
.hero-header h1 { font-size: 36px; font-weight: 900; letter-spacing: -1.5px; margin-bottom: 10px; text-align: center;}

.search-wrapper { max-width: 1400px; margin: 20px auto 0; position: relative; z-index: 1000; width: 100%; padding: 0 20px; }

/* Buscador Pill */
.search-pill {
  background: white; border: 1px solid #e2e8f0; border-radius: 100px;
  display: flex; align-items: center; box-shadow: 0 4px 15px rgba(0,0,0,0.05);
  cursor: pointer; padding: 16px 40px; width: 100%; transition: 0.3s; max-width: 100%;
}
.search-pill:hover { border-color: var(--primary); box-shadow: 0 6px 20px rgba(65,186,13,0.15); }
.search-pill i { color: var(--primary); margin-right: 15px; font-size: 18px; }
.search-pill span { font-weight: 600; font-size: 15px; color: var(--slate-600); }

/* MODAL DE BÚSQUEDA */
.search-modal-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 3000;
  display: none; backdrop-filter: blur(8px); align-items: center; justify-content: center;
  padding: 20px;
}
.search-modal-overlay.show { display: flex; }

.search-modal-content {
  background: white; border-radius: 24px; width: 100%; max-width: 700px;
  max-height: 90vh; overflow-y: auto; position: relative;
  animation: modalSlideIn 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes modalSlideIn {
  from { opacity: 0; transform: translateY(-30px) scale(0.95); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}

.modal-header {
  padding: 30px 30px 20px; border-bottom: 1px solid #eee;
  display: flex; justify-content: space-between; align-items: center;
  position: sticky; top: 0; background: white; z-index: 10;
  border-radius: 24px 24px 0 0;
}

.modal-header h2 {
  font-size: 24px; font-weight: 900; color: var(--slate-900); letter-spacing: -1px;
}

.modal-close {
  background: var(--zinc-100); border: none; width: 36px; height: 36px;
  border-radius: 50%; display: flex; align-items: center; justify-content: center;
  cursor: pointer; color: var(--slate-600); transition: 0.2s;
}
.modal-close:hover { background: #e2e8f0; }

.modal-body {
  padding: 30px;
}

.search-section {
  margin-bottom: 30px;
}

.search-section-title {
  font-size: 13px; font-weight: 900; text-transform: uppercase;
  color: var(--slate-900); margin-bottom: 15px; letter-spacing: 0.5px;
  display: flex; align-items: center; gap: 8px;
}

.search-section-title i {
  color: var(--primary); font-size: 14px;
}

/* Input Principal de Búsqueda */
.search-input-main {
  width: 100%; padding: 16px 20px; border: 2px solid #e2e8f0;
  border-radius: 14px; font-size: 15px; font-weight: 600;
  outline: none; transition: 0.2s; background: white;
}
.search-input-main:focus {
  border-color: var(--primary); background: var(--primary-soft);
}

/* Grid de Ubicaciones */
.location-grid {
  display: grid; grid-template-columns: 1fr 1fr; gap: 12px;
}

.input-wrapper {
  position: relative;
}

.input-icon {
  position: absolute; left: 16px; top: 50%; transform: translateY(-50%);
  color: var(--primary); font-size: 14px; pointer-events: none;
}

.input-field {
  width: 100%; padding: 14px 16px 14px 42px; border: 2px solid #e2e8f0;
  border-radius: 12px; font-size: 14px; font-weight: 600;
  outline: none; transition: 0.2s;
}
.input-field:focus {
  border-color: var(--primary); background: var(--primary-soft);
}

/* Pills de Categorías */
.category-pills {
  display: flex; flex-wrap: wrap; gap: 10px;
}

.category-pill {
  padding: 10px 18px; border-radius: 20px; border: 2px solid #e2e8f0;
  background: white; font-size: 13px; font-weight: 700;
  color: var(--slate-600); cursor: pointer; transition: 0.2s;
  display: inline-flex; align-items: center; gap: 6px;
}
.category-pill:hover {
  border-color: var(--primary); background: var(--primary-soft);
}
.category-pill.active {
  background: var(--slate-900); color: white; border-color: var(--slate-900);
}

/* Moneda */
.currency-selector {
  display: flex; gap: 12px;
}

.currency-btn {
  flex: 1; padding: 14px; border-radius: 12px; border: 2px solid #e2e8f0;
  background: white; font-weight: 800; cursor: pointer; transition: 0.2s;
  font-size: 14px; color: var(--slate-600);
}
.currency-btn:hover {
  border-color: var(--primary); background: var(--primary-soft);
}
.currency-btn.active {
  border-color: var(--primary); background: var(--primary);
  color: white;
}

/* Presupuesto */
.budget-grid {
  display: grid; grid-template-columns: 1fr 1fr; gap: 12px;
}

/* Botones de Acción */
.modal-footer {
  padding: 20px 30px; border-top: 1px solid #eee;
  display: flex; gap: 12px; position: sticky; bottom: 0;
  background: white; border-radius: 0 0 24px 24px;
}

.btn-secondary {
  flex: 1; padding: 16px; border-radius: 14px; border: 2px solid #e2e8f0;
  background: white; font-weight: 800; cursor: pointer; transition: 0.2s;
  font-size: 14px; color: var(--slate-900);
}
.btn-secondary:hover {
  background: var(--zinc-100);
}

.btn-primary {
  flex: 2; padding: 16px; border-radius: 14px; border: none;
  background: var(--slate-900); color: white; font-weight: 800;
  cursor: pointer; transition: 0.3s; font-size: 14px;
  display: flex; align-items: center; justify-content: center; gap: 8px;
}
.btn-primary:hover {
  background: var(--primary);
}

.btn-primary i {
  font-size: 16px;
}

/* ========== FILTROS BAR STICKY ========== */
.filter-bar { background: white; padding: 12px 20px; border-bottom: 1px solid #eee; position: sticky; top: 0; z-index: 900; }
.filter-bar .container { max-width: 1400px; margin: 0 auto; display: flex; align-items: center; }
.tabs { display: flex; gap: 8px; overflow-x: auto; scrollbar-width: none; width: 100%; }
.tab { padding: 8px 18px; border-radius: 12px; border: none; background: transparent; font-weight: 700; font-size: 12px; color: var(--slate-600); cursor: pointer; transition: 0.3s; white-space: nowrap; }
.tab.active { background: var(--slate-900); color: white; }

/* ========== GRID & CARDS (Efecto 3D) ========== */
.main-container { max-width: 1400px; margin: 0 auto; padding: 30px 20px; }
.products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 25px; }

.product-card { 
    background: white; border-radius: 24px; border: 1px solid #e2e8f0; 
    overflow: hidden; display: flex; flex-direction: column; 
    transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.4s ease; 
    position: relative; 
}
.product-card:hover { 
    transform: translateY(-10px) scale(1.01);
    box-shadow: 0 30px 60px -15px rgba(0,0,0,0.15); 
    border-color: var(--primary);
}

.card-img-zone { position: relative; height: 190px; background: #f3f4f6; overflow: hidden; cursor: pointer; }
.card-img-zone img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.8s ease; }
.product-card:hover .card-img-zone img { transform: scale(1.1); }

/* CORAZÓN ELITE */
.favorite-btn {
  position: absolute; top: 15px; right: 15px; background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(8px);
  border: 1px solid rgba(0,0,0,0.05); width: 38px; height: 38px; border-radius: 50%; color: #94a3b8;
  cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 20; transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.favorite-btn.active { color: var(--danger) !important; transform: scale(1.1); background: white; }
.favorite-btn i { font-size: 18px; pointer-events: none; }
.favorite-btn:active { transform: scale(0.9); }

.urgency-tag { 
  position: absolute; top: 15px; left: 15px; background: var(--danger); color: white; 
  padding: 5px 12px; border-radius: 10px; font-size: 10px; font-weight: 900; text-transform: uppercase; 
  box-shadow: 0 4px 10px rgba(239, 68, 68, 0.3);
}

.card-body { padding: 24px; flex-grow: 1; cursor: pointer; }
.req-question { font-size: 11px; font-weight: 800; color: var(--primary); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
.product-title { font-size: 19px; font-weight: 800; margin-bottom: 12px; line-height: 1.3; color: var(--slate-900); }

.product-description {
  font-size: 13px;
  line-height: 1.6;
  color: var(--slate-600);
  margin-bottom: 15px;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* PROPOSALS BADGE */
.proposals-badge {
  display: inline-flex; align-items: center; gap: 6px; background: #f1f5f9;
  padding: 5px 12px; border-radius: 10px; font-size: 11px; font-weight: 700; margin: 0; color: var(--slate-600);
}
.proposals-badge i { color: var(--primary); }

.route-box { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; background: #f8fafc; padding: 15px; border-radius: 18px; margin-bottom: 20px; border: 1px solid #f1f5f9; }
.route-label { font-size: 9px; font-weight: 800; color: #41ba0d; text-transform: uppercase; margin-bottom: 4px; }
.route-val { font-size: 13px; font-weight: 700; color: var(--slate-900); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.user-info { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; border-top: 1px solid #f1f5f9; padding-top: 15px; }
.user-info > div:nth-child(2) { flex: 1; }
.avatar-img { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; border: 2px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }

/* ========================================
   CÓDIGO CSS PARA ACTUALIZAR
   Reemplaza estos estilos en shop-requests-index-elite-final.php
   ======================================== */
/* ========================================
   CÓDIGO CSS ACTUALIZADO - VERSIÓN MOBILE OPTIMIZADA
   Reemplaza la sección de precio en shop-requests-index-elite-final.php
   ======================================== */

/* NUEVA SECCIÓN PRECIO + CANTIDAD - DISEÑO REFINADO */
.price-action { 
  display: flex; 
  justify-content: space-between; 
  align-items: center; 
  margin-top: auto;
  gap: 20px;
}

.price-section {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.price-label { 
  font-size: 11px; 
  font-weight: 800; 
  color: var(--slate-400); 
  text-transform: uppercase; 
  margin-bottom: 4px;
  letter-spacing: 0.8px;
}

.price-container {
  display: flex;
  align-items: baseline;
  gap: 6px;
}

.price-amt { 
  font-size: 48px; 
  font-weight: 900; 
  color: var(--slate-900); 
  line-height: 1;
  letter-spacing: -1.5px;
}

.price-amt span { 
  font-size: 16px; 
  color: var(--primary); 
  font-weight: 700;
}

/* Badge de cantidad - Estilo refinado */
.quantity-badge {
  background: #eef9e7;
  color: var(--primary);
  padding: 6px 14px;
  border-radius: 100px;
  font-size: 13px;
  font-weight: 800;
  display: inline-block;
  margin-top: 8px;
  width: fit-content;
}

.btn-apply { 
  background: var(--slate-900); 
  color: white; 
  border: none; 
  padding: 18px 28px; 
  border-radius: 14px; 
  font-weight: 800; 
  font-size: 14px; 
  text-transform: uppercase; 
  letter-spacing: 0.5px;
  cursor: pointer; 
  transition: all 0.2s ease;
  white-space: nowrap;
  flex-shrink: 0;
}
.btn-apply:hover { 
  background: var(--primary); 
  transform: translateY(-2px);
  box-shadow: 0 8px 15px rgba(65, 186, 13, 0.2);
}
.btn-apply:active {
  transform: translateY(0);
}
.btn-apply:disabled { 
  opacity: 0.5; 
  cursor: not-allowed; 
}

/* Responsive Mobile - OPTIMIZADO PARA NO DESPLAZAR EL BOTÓN */
@media (max-width: 768px) {
  .price-action {
    flex-direction: column;
    gap: 16px;
    align-items: stretch;
  }

  .price-section {
    width: 100%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
  }

  .price-container {
    flex-shrink: 0;
  }

  .price-amt {
    font-size: 32px;
  }

  .quantity-badge {
    margin-top: 0;
    flex-shrink: 0;
  }

  .btn-apply {
    width: 100%;
    padding: 16px;
  }
}

/* Responsive para pantallas muy pequeñas */
@media (max-width: 400px) {
  .price-amt {
    font-size: 28px;
  }
  
  .quantity-badge {
    font-size: 11px;
    padding: 5px 12px;
  }
}



/* ========== CÓMO FUNCIONA ========== */
.how-it-works {
  background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
  padding: 80px 20px;
  margin-top: 60px;
}
.how-it-works .container {
  max-width: 1200px;
  margin: 0 auto;
}
.how-it-works h2 {
  text-align: center;
  font-size: 42px;
  font-weight: 900;
  color: white;
  margin-bottom: 60px;
  letter-spacing: -1.5px;
}
.steps-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 40px;
}
.step-card {
  background: rgba(255, 255, 255, 0.05);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 24px;
  padding: 40px;
  transition: transform 0.3s ease, background 0.3s ease;
}
.step-card:hover {
  transform: translateY(-10px);
  background: rgba(255, 255, 255, 0.08);
}
.step-number {
  display: inline-block;
  background: var(--primary);
  color: white;
  width: 50px;
  height: 50px;
  border-radius: 50%;
  font-size: 24px;
  font-weight: 900;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 20px;
}
.step-title {
  font-size: 24px;
  font-weight: 800;
  color: white;
  margin-bottom: 15px;
}
.step-description {
  font-size: 15px;
  line-height: 1.7;
  color: rgba(255, 255, 255, 0.8);
}

@media (max-width: 768px) {
  .hero-header { padding: 60px 15px 15px; }
  .hero-header h1 { font-size: 28px; }
  
  /* Buscador con mismo ancho que las tarjetas */
  .search-wrapper { 
    margin-top: 10px; 
    padding: 0; /* Sin padding para máximo ancho */
  }
  
  /* Buscador más elegante en mobile */
  .search-pill {
    padding: 12px 20px;
    border-radius: 50px;
    margin: 0 16px; /* Mismo margen que las tarjetas */
  }
  
  .search-pill i {
    font-size: 16px;
    margin-right: 12px;
  }
  
  .search-pill span {
    font-size: 14px; /* Mantener legible */
    font-weight: 500;
  }
  
  /* Tabs con mismo ancho que las tarjetas */
  .filter-bar {
    padding: 10px 0; /* Sin padding lateral */
  }
  
  .filter-bar .container {
    padding: 0 16px; /* Mismo padding que las tarjetas */
  }
  
  .tabs {
    gap: 8px;
  }
  
  .tab {
    padding: 8px 16px;
    font-size: 12px; /* Mantener legible */
    border-radius: 12px;
  }
  
  /* Grid de productos con mismo padding */
  .main-container {
    padding: 20px 16px;
  }
  
  .how-it-works h2 { font-size: 32px; }
  .steps-grid { gap: 30px; }
  
  /* Modal en mobile - Pantalla completa */
  .search-modal-overlay {
    padding: 0;
  }
  
  .search-modal-content { 
    margin: 0; 
    max-width: 100%; 
    max-height: 100vh;
    height: 100vh;
    border-radius: 0;
    display: flex;
    flex-direction: column;
  }
  
  .modal-header { 
    padding: 16px 20px;
    border-radius: 0;
  }
  
  .modal-header h2 {
    font-size: 20px;
  }
  
  .modal-body { 
    padding: 20px;
    flex: 1;
    overflow-y: auto;
    padding-bottom: 90px; /* Espacio para botones flotantes más pequeños */
  }
  
  /* Botones flotantes MÁS PEQUEÑOS y HACIA ARRIBA en mobile */
  .modal-footer { 
    padding: 12px 16px; /* Más compacto */
    position: fixed !important;
    bottom: 0;
    left: 0;
    right: 0;
    border-radius: 0;
    box-shadow: 0 -2px 15px rgba(0,0,0,0.1); /* Shadow más sutil */
    z-index: 20;
    background: white;
    transform: translateY(0); /* Para animación futura */
  }
  
  .btn-secondary,
  .btn-primary {
    padding: 12px 16px; /* Más pequeños */
    font-size: 13px; /* Texto más pequeño */
    border-radius: 10px;
    font-weight: 800;
  }
  
  .location-grid { grid-template-columns: 1fr; }
  .budget-grid { grid-template-columns: 1fr; }
  
  .category-pills {
    flex-wrap: wrap;
  }
  
  .category-pill {
    flex: 0 0 calc(50% - 5px);
    justify-content: center;
  }
}
</style>
</head>
<body>

<?php if (file_exists('header1.php')) include 'header1.php'; ?>

<header class="hero-header">
  <div class="container">
    <h1>MARKETPLACE</h1>
    
    <div class="search-wrapper" id="search-wrapper">
      <div class="search-pill" onclick="openSearchModal()">
        <i class="fas fa-search"></i>
        <span>Buscar productos, rutas, ciudades...</span>
      </div>
    </div>
  </div>
</header>

<div class="filter-bar">
  <div class="container">
    <div class="tabs">
      <button class="tab active" onclick="changeTab('recent', this)">RECIENTES</button>
      <button class="tab" onclick="changeTab('popular', this)">POPULARES</button>
      <button class="tab" onclick="changeTab('urgent', this)">URGENTES</button>
    </div>
  </div>
</div>

<main class="main-container">
  <div class="products-grid" id="products-grid"></div>
  <div id="loading" style="text-align:center; padding:50px;"><i class="fas fa-circle-notch fa-spin fa-2x text-success"></i></div>
</main>

<!-- Cómo funciona -->
<section class="how-it-works">
  <div class="container">
    <h2>Cómo funciona SendVialo Shop</h2>
    <div class="steps-grid">
      <div class="step-card">
        <div class="step-number">01</div>
        <h3 class="step-title">Pide lo que quieras</h3>
        <p class="step-description">Publica tu solicitud indicando qué producto necesitas de otro país y cuánto pagarás por el envío.</p>
      </div>
      <div class="step-card">
        <div class="step-number">02</div>
        <h3 class="step-title">Recibe ofertas</h3>
        <p class="step-description">Viajeros que coincidan con tu ruta te enviarán propuestas. Tú eliges la mejor según su reputación.</p>
      </div>
      <div class="step-card">
        <div class="step-number">03</div>
        <h3 class="step-title">Recibe y confirma</h3>
        <p class="step-description">Tu dinero se queda protegido. Solo se libera al viajero cuando escaneas el código QR al recibir tu pedido.</p>
      </div>
    </div>
  </div>
</section>

<!-- MODAL DE BÚSQUEDA -->
<div class="search-modal-overlay" id="searchModal" onclick="closeSearchModal(event)">
  <div class="search-modal-content" onclick="event.stopPropagation()">
    
    <!-- Header -->
    <div class="modal-header">
      <h2>Buscar Solicitudes</h2>
      <button class="modal-close" onclick="closeSearchModal()">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <!-- Body -->
    <div class="modal-body">
      
      <!-- Búsqueda Principal -->
      <div class="search-section">
        <div class="search-section-title">
          <i class="fas fa-search"></i>
          ¿Qué buscas?
        </div>
        <input 
          type="text" 
          class="search-input-main" 
          id="searchQuery" 
          placeholder="Buscar por producto, marca, descripción..."
          autocomplete="off"
        >
      </div>

      <!-- Ubicaciones -->
      <div class="search-section">
        <div class="search-section-title">
          <i class="fas fa-route"></i>
          Rutas
        </div>
        <div class="location-grid">
          <div class="input-wrapper">
            <i class="fas fa-map-marker-alt input-icon"></i>
            <input 
              type="text" 
              class="input-field" 
              id="originInput" 
              placeholder="Desde (país/ciudad)"
              autocomplete="off"
            >
          </div>
          <div class="input-wrapper">
            <i class="fas fa-map-marker-alt input-icon"></i>
            <input 
              type="text" 
              class="input-field" 
              id="destinationInput" 
              placeholder="Hasta (ciudad)"
              autocomplete="off"
            >
          </div>
        </div>
      </div>

      <!-- Categorías -->
      <div class="search-section">
        <div class="search-section-title">
          <i class="fas fa-tags"></i>
          Categorías
        </div>
        <div class="category-pills">
          <button class="category-pill" data-category="food" onclick="toggleCategoryPill(this)">
            🍕 Comida
          </button>
          <button class="category-pill" data-category="fashion" onclick="toggleCategoryPill(this)">
            👗 Moda
          </button>
          <button class="category-pill" data-category="electronics" onclick="toggleCategoryPill(this)">
            💻 Tecnología
          </button>
          <button class="category-pill" data-category="crafts" onclick="toggleCategoryPill(this)">
            🎨 Artesanía
          </button>
          <button class="category-pill" data-category="books" onclick="toggleCategoryPill(this)">
            📚 Libros
          </button>
        </div>
      </div>

      <!-- Moneda -->
      <div class="search-section">
        <div class="search-section-title">
          <i class="fas fa-coins"></i>
          Moneda
        </div>
        <div class="currency-selector">
          <button class="currency-btn active" data-currency="EUR" onclick="selectCurrency(this)">
            EUR (€)
          </button>
          <button class="currency-btn" data-currency="USD" onclick="selectCurrency(this)">
            USD ($)
          </button>
        </div>
      </div>

      <!-- Presupuesto -->
      <div class="search-section">
        <div class="search-section-title">
          <i class="fas fa-wallet"></i>
          Presupuesto
        </div>
        <div class="budget-grid">
          <div class="input-wrapper">
            <i class="fas fa-euro-sign input-icon"></i>
            <input 
              type="number" 
              class="input-field" 
              id="minBudget" 
              placeholder="Mínimo"
              min="0"
            >
          </div>
          <div class="input-wrapper">
            <i class="fas fa-euro-sign input-icon"></i>
            <input 
              type="number" 
              class="input-field" 
              id="maxBudget" 
              placeholder="Máximo"
              min="0"
            >
          </div>
        </div>
      </div>

    </div>

    <!-- Footer -->
    <div class="modal-footer">
      <button class="btn-secondary" onclick="clearAllFilters()">
        Limpiar filtros
      </button>
      <button class="btn-primary" onclick="executeSearch()">
        <i class="fas fa-search"></i>
        Buscar
      </button>
    </div>

  </div>
</div>

<script>
let requests = [];
let currentFilter = 'recent';
let selectedCats = new Set();
let currentCurr = 'EUR';
const userFavorites = new Set();

async function cargarSolicitudes() {
  try {
    const res = await fetch('shop-requests-actions.php?action=get_requests&status=open');
    const data = await res.json();
    if (data.success) {
      requests = data.requests || [];
      // Cargar favoritos del usuario
      requests.forEach(r => {
        if (r.is_favorited) userFavorites.add(r.id);
      });
      aplicarFiltros();
    }
  } catch (e) { console.error(e); }
  document.getElementById('loading').style.display = 'none';
}

// MODAL DE BÚSQUEDA
function openSearchModal() {
  document.getElementById('searchModal').classList.add('show');
  document.body.style.overflow = 'hidden';
  setTimeout(() => {
    document.getElementById('searchQuery').focus();
  }, 100);
}

function closeSearchModal(event) {
  if (event && event.target !== event.currentTarget) return;
  document.getElementById('searchModal').classList.remove('show');
  document.body.style.overflow = '';
}

function toggleCategoryPill(btn) {
  const category = btn.dataset.category;
  if (btn.classList.contains('active')) {
    btn.classList.remove('active');
    selectedCats.delete(category);
  } else {
    btn.classList.add('active');
    selectedCats.add(category);
  }
}

function selectCurrency(btn) {
  document.querySelectorAll('.currency-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  currentCurr = btn.dataset.currency;
}

function clearAllFilters() {
  // Limpiar inputs
  document.getElementById('searchQuery').value = '';
  document.getElementById('originInput').value = '';
  document.getElementById('destinationInput').value = '';
  document.getElementById('minBudget').value = '';
  document.getElementById('maxBudget').value = '';
  
  // Limpiar categorías
  document.querySelectorAll('.category-pill').forEach(btn => btn.classList.remove('active'));
  selectedCats.clear();
  
  // Reset moneda a EUR
  currentCurr = 'EUR';
  document.querySelectorAll('.currency-btn').forEach(btn => {
    btn.classList.remove('active');
    if (btn.dataset.currency === 'EUR') btn.classList.add('active');
  });
}

function executeSearch() {
  aplicarFiltros();
  closeSearchModal();
  
  // Scroll suave a resultados
  const grid = document.getElementById('products-grid');
  if (grid) {
    setTimeout(() => {
      grid.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 300);
  }
}

function aplicarFiltros() {
  const searchQuery = document.getElementById('searchQuery')?.value.toLowerCase() || '';
  const ori = document.getElementById('originInput')?.value.toLowerCase() || '';
  const des = document.getElementById('destinationInput')?.value.toLowerCase() || '';
  const min = parseFloat(document.getElementById('minBudget')?.value) || 0;
  const max = parseFloat(document.getElementById('maxBudget')?.value) || 999999;

  let list = requests.filter(r => {
    // Búsqueda general
    const mSearch = !searchQuery || 
      r.title?.toLowerCase().includes(searchQuery) || 
      r.description?.toLowerCase().includes(searchQuery) ||
      r.origin_country?.toLowerCase().includes(searchQuery) ||
      r.destination_city?.toLowerCase().includes(searchQuery);
    
    const mOri = !ori || r.origin_country?.toLowerCase().includes(ori) || r.origin_city?.toLowerCase().includes(ori);
    const mDes = !des || r.destination_city?.toLowerCase().includes(des) || r.destination_country?.toLowerCase().includes(des);
    const mUrg = currentFilter !== 'urgent' || r.urgency === 'urgent';
    const mCat = selectedCats.size === 0 || selectedCats.has(r.category);
    const mCur = r.budget_currency === currentCurr;
    const mPri = r.budget_amount >= min && r.budget_amount <= max;
    
    return mSearch && mOri && mDes && mUrg && mCat && mCur && mPri;
  });

  if (currentFilter === 'popular') list.sort((a,b) => b.proposal_count - a.proposal_count);
  else list.sort((a,b) => new Date(b.created_at) - new Date(a.created_at));

  const grid = document.getElementById('products-grid');
  grid.innerHTML = list.map(req => {
    const rating = parseFloat(req.requester_rating) || 0;
    const isFav = userFavorites.has(req.id);
    const avatar = req.requester_avatar_id > 0 ? `../mostrar_imagen.php?id=${req.requester_avatar_id}` : `https://ui-avatars.com/api/?name=${req.requester_username}&background=41ba0d&color=fff`;
    const description = req.description || 'Sin descripción disponible';
const quantity = parseInt(req.quantity) || 1;
    return `
      <div class="product-card">
        <div class="card-img-zone" onclick="verDetalle(${req.id})">
          <img src="${req.reference_images?.[0] || 'https://via.placeholder.com/400x250'}" alt="">
          ${req.urgency === 'urgent' ? '<div class="urgency-tag">Urgente</div>' : ''}
          <button class="favorite-btn ${isFav ? 'active' : ''}" onclick="event.stopPropagation(); toggleFav(this, ${req.id})">
            <i class="${isFav ? 'fas' : 'far'} fa-heart"></i>
          </button>
        </div>
        <div class="card-body" onclick="verDetalle(${req.id})">
          <div class="req-question">¿QUIÉN ME LO TRAE?</div>
          <h3 class="product-title">${req.title}</h3>
          
          <p class="product-description">${description}</p>

          <div class="route-box">
            <div>
              <div class="route-label">Comprarlo en</div>
              <div class="route-val">${req.origin_country || 'Cualquier país'}</div>
            </div>
            <div style="border-left:1px solid #eee; padding-left:10px;">
              <div class="route-label">Traerlo a</div>
              <div class="route-val">${req.destination_city}</div>
            </div>
          </div>
          
          <div class="user-info">
            <img src="${avatar}" class="avatar-img">
            <div>
              <div style="font-weight:600; font-size:13px;">@${req.requester_username}</div>
              <div style="font-size:11px; color:#f59e0b;">★ ${rating.toFixed(1)}</div>
            </div>
            <div class="proposals-badge">
              <i class="fas fa-users"></i> ${req.proposal_count}
            </div>
          </div>
          
          
          
    <div class="price-action">
  <div class="price-section">
    <div class="price-label">Dispuesto a pagar</div>
    <div class="price-container">
      <div class="price-amt">${Math.floor(req.budget_amount)}<span>${req.budget_currency}</span></div>
    </div>
    ${quantity > 1 ? `
      <div class="quantity-badge">
        ${quantity} unidades
      </div>
    ` : ''}
  </div>
  <button class="btn-apply" ${req.user_has_applied ? 'disabled' : ''} onclick="event.stopPropagation(); aplicarPropuesta(${req.id})">
    ${req.user_has_applied ? 'Ya aplicaste' : 'Yo te lo llevo'}
  </button>
</div>
          
          
          
        </div>
      </div>
    `;
  }).join('');
}

function verDetalle(id) {
  window.location.href = `shop-request-detail.php?id=${id}`;
}

function openFilters() { 
  openSearchModal();
}

function changeTab(f, btn) { 
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active')); 
    btn.classList.add('active'); 
    currentFilter = f; 
    aplicarFiltros(); 
}

async function toggleFav(btn, id) {
    <?php if (!$user_logged_in): ?>
    Swal.fire({ icon: 'warning', title: 'Inicia sesión', text: 'Debes iniciar sesión para guardar favoritos' });
    return;
    <?php endif; ?>

    const icon = btn.querySelector('i');
    const wasActive = btn.classList.contains('active');
    
    try {
        const res = await fetch('shop-requests-actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=${wasActive ? 'remove_favorite' : 'add_favorite'}&request_id=${id}`
        });
        const data = await res.json();
        
        if (data.success) {
            if (wasActive) {
                btn.classList.remove('active');
                icon.classList.replace('fas', 'far');
                userFavorites.delete(id);
            } else {
                btn.classList.add('active');
                icon.classList.replace('far', 'fas');
                userFavorites.add(id);
            }
        }
    } catch (e) {
        console.error('Error al guardar favorito:', e);
    }
}

async function aplicarPropuesta(id) {
    <?php if (!$user_logged_in): ?>
    Swal.fire({ icon: 'warning', title: 'Inicia sesión', text: 'Debes iniciar sesión para enviar propuestas' });
    return;
    <?php endif; ?>
    
    window.location.href = `shop-request-detail.php?id=${id}`;
}

// Cerrar modal con ESC
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    closeSearchModal();
  }
});

document.addEventListener('DOMContentLoaded', cargarSolicitudes);
</script>
</body>
</html>