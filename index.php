<?php
// shop-index.php – Página pública de la tienda SendVialo Shop con sistema de valoraciones unificado
session_start();

// Incluir sistema de insignias unificado (corregido)
require_once 'insignias1.php';
require_once '../config.php';

// Variables de usuario para JavaScript (opcional si está logueado)
$user_logged_in = isset($_SESSION['usuario_id']);
$user_id = $user_logged_in ? $_SESSION['usuario_id'] : null;
$user_name = $user_logged_in ? ($_SESSION['usuario_nombre'] ?? $_SESSION['full_name'] ?? 'Usuario') : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>SendVialo Shop - ¿Quién quiere? Ofertas de Viajeros</title>
  <link rel="stylesheet" href="../css/estilos.css"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.3.0/css/all.min.css"/>
  <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link rel="icon" href="../Imagenes/globo5.png" type="image/png"/>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  
  <?php incluirEstilosInsignias(); ?>
  
  <style>
/* ========================================
   RESET Y BASE - CRÍTICO PARA EVITAR PANTALLA NEGRA
   ======================================== */
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

html {
  scroll-behavior: smooth;
}

body {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  background: #ffffff;
  color: #2d3748;
  line-height: 1.6;
  min-height: 100vh;
}

/* ========================================
   SENDVIALO SHOP - HEADER
   Verde personalizado: #42ba25
   ======================================== */

/* === HEADER FRESCO === */
.shop-header {
  background: linear-gradient(135deg, #e8f8e4 0%, #d4f1cd 100%);
  color: #1a1a1a;
  padding: 90px 0;
  position: relative;
  overflow: hidden;
  min-height: 550px;
  display: flex;
  align-items: center;
}

.shop-header::before {
  content: '';
  position: absolute;
  top: -100px;
  right: -100px;
  width: 400px;
  height: 400px;
  background: #42ba25;
  opacity: 0.05;
  border-radius: 50%;
}

.shop-header::after {
  content: '';
  position: absolute;
  bottom: -80px;
  left: -80px;
  width: 350px;
  height: 350px;
  background: #5cd63e;
  opacity: 0.05;
  border-radius: 50%;
}

/* === LOGO CON ANIMACIÓN === */
.logo-container {
  text-align: center;
  margin-bottom: 40px;
  position: relative;
  z-index: 2;
  animation: float 3s ease-in-out infinite;
}

@keyframes float {
  0%, 100% { 
    transform: translateY(0px); 
  }
  50% { 
    transform: translateY(-10px); 
  }
}

.logo-container img {
  max-width: 380px;
  height: auto;
  filter: drop-shadow(0 10px 25px rgba(66, 186, 37, 0.2));
}

/* === CONTENIDO DEL HEADER === */
.shop-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 20px;
  position: relative;
  z-index: 2;
}

.header-content {
  text-align: center;
  position: relative;
  z-index: 2;
}

.premium-badge {
  display: inline-block;
  background: white;
  color: #42ba25;
  padding: 10px 26px;
  border-radius: 25px;
  font-size: 0.85rem;
  font-weight: 700;
  margin-bottom: 30px;
  text-transform: uppercase;
  letter-spacing: 1.5px;
  box-shadow: 0 4px 15px rgba(66, 186, 37, 0.15);
  border: 2px solid #42ba25;
}

.shop-header h1 {
  font-size: 3.8rem;
  font-weight: 900;
  color: #1a1a1a;
  margin-bottom: 20px;
  line-height: 1.15;
  letter-spacing: -1.5px;
}

.shop-header h1 .accent {
  color: #42ba25;
  position: relative;
}

.shop-subtitle {
  font-size: 1.35rem;
  color: #2d2d2d;
  margin-bottom: 15px;
  font-weight: 500;
  max-width: 700px;
  margin-left: auto;
  margin-right: auto;
}

.shop-description {
  font-size: 1.1rem;
  color: #666;
  margin-bottom: 40px;
  max-width: 600px;
  margin-left: auto;
  margin-right: auto;
  line-height: 1.7;
}

/* === BOTONES DEL HEADER === */
.cta-section {
  margin-top: 40px;
  display: flex;
  justify-content: center;
  gap: 15px;
  flex-wrap: wrap;
}

.btn-primary {
  background: linear-gradient(135deg, #42ba25 0%, #37a01f 100%);
  color: white;
  padding: 18px 38px;
  border: none;
  border-radius: 30px;
  font-size: 1.05rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.3s ease;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 10px;
  box-shadow: 0 6px 20px rgba(66, 186, 37, 0.35);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-family: 'Inter', sans-serif;
}

.btn-primary:hover {
  transform: translateY(-3px) scale(1.02);
  box-shadow: 0 10px 30px rgba(66, 186, 37, 0.45);
  color: white;
  text-decoration: none;
}

.btn-secondary {
  background: white;
  color: #42ba25;
  padding: 18px 38px;
  border: 3px solid #42ba25;
  border-radius: 30px;
  font-size: 1.05rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.3s ease;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 10px;
  box-shadow: 0 4px 15px rgba(66, 186, 37, 0.15);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-family: 'Inter', sans-serif;
}

.btn-secondary:hover {
  background: #42ba25;
  color: white;
  transform: translateY(-2px);
  text-decoration: none;
}

.btn-secondary.login-required {
  border-color: #FFC107;
  color: #FFC107;
}

.btn-secondary.login-required:hover {
  background: #FFC107;
  color: white;
}

/* === FILTROS SECTION (CON COLAPSABLE MÓVIL) === */
.prefilters-title {
  background: #f8f9fa;
  padding: 40px 0 20px;
  text-align: center;
}

.prefilters-title h2 {
  font-size: 2rem;
  font-weight: 700;
  color: #1a1a1a;
  margin-bottom: 10px;
}

.prefilters-subtitle {
  font-size: 1.1rem;
  color: #666;
}

.filters-section {
  background: #f8f9fa;
  padding: 60px 0;
  border-top: 1px solid #e9ecef;
}

.filters-wrapper {
  position: relative;
}

/* === TOGGLE BUTTON PARA MÓVILES === */
.filters-toggle {
  display: none;
  background: linear-gradient(135deg, #42ba25 0%, #37a01f 100%);
  color: white;
  border: none;
  padding: 16px 25px;
  border-radius: 15px;
  font-weight: 600;
  font-size: 1rem;
  cursor: pointer;
  width: 100%;
  margin-bottom: 15px;
  transition: all 0.3s ease;
  font-family: 'Inter', sans-serif;
  align-items: center;
  justify-content: space-between;
  box-shadow: 0 4px 15px rgba(66, 186, 37, 0.3);
}

.filters-toggle:active {
  transform: scale(0.98);
}

.filters-toggle i {
  transition: transform 0.3s ease;
  font-size: 1.2rem;
}

.filters-toggle.active i.fa-chevron-down {
  transform: rotate(180deg);
}

.filters-toggle-text {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 1.05rem;
}

.filters-container {
  background: #fff;
  padding: 35px;
  border-radius: 20px;
  box-shadow: 0 10px 40px rgba(0,0,0,.08);
  margin-bottom: 0;
  border: 2px solid #42ba25;
  transition: all 0.3s ease;
}

.filters-content {
  display: flex;
  flex-wrap: wrap;
  gap: 25px;
  align-items: center;
  justify-content: space-between;
}

/* === FILTROS - ESTILOS ORIGINALES === */
.filter-group {
  display: flex;
  flex-direction: column;
  min-width: 160px;
  flex: 1;
}

.filter-group label {
  font-weight: 600;
  margin-bottom: 8px;
  color: #333;
  font-size: 0.9rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.filter-group select,
.filter-group input {
  padding: 12px 16px;
  border: 2px solid #e1e5e9;
  border-radius: 10px;
  font-size: 14px;
  transition: all .3s ease;
  font-family: 'Inter', sans-serif;
  background: #fff;
}

.filter-group select:focus,
.filter-group input:focus {
  outline: none;
  border-color: #42ba25;
  box-shadow: 0 0 0 3px rgba(66, 186, 37, 0.1);
}

.sell-btn {
  background: linear-gradient(135deg, #42ba25 0%, #37a01f 100%);
  color: #fff;
  padding: 14px 28px;
  border: none;
  border-radius: 12px;
  font-weight: 600;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 10px;
  transition: all .3s ease;
  white-space: nowrap;
  font-family: 'Inter', sans-serif;
  box-shadow: 0 4px 15px rgba(66, 186, 37, 0.3);
}

.sell-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(66, 186, 37, .4);
  color: #fff;
  text-decoration: none;
}

.sell-btn.login-required {
  background: linear-gradient(135deg, #FFC107 0%, #FFD54F 100%);
  color: #333;
  box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3);
}

.sell-btn.login-required:hover {
  box-shadow: 0 8px 25px rgba(255, 193, 7, .4);
  color: #333;
}

/* === GRID DE PRODUCTOS === */
.products-section {
  padding: 80px 0;
  background: #ffffff;
}

.products-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
  gap: 30px;
  margin-bottom: 40px;
}

/* === TARJETAS DE PRODUCTO === */
.product-card {
  background: #fff;
  border-radius: 20px;
  overflow: hidden;
  box-shadow: 0 8px 25px rgba(0,0,0,.08);
  transition: all .4s ease;
  position: relative;
  border: 2px solid #42ba25;
  cursor: pointer;
}

.product-card:hover {
  transform: translateY(-8px);
  box-shadow: 0 20px 50px rgba(0,0,0,.15);
  border-color: #37a01f;
}

.product-image {
  position: relative;
  height: 240px;
  overflow: hidden;
  background: linear-gradient(45deg, #f8f9fa, #e9ecef);
}

.product-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform .4s ease;
}

.product-card:hover .product-image img {
  transform: scale(1.05);
}

.trip-badge {
  position: absolute;
  top: 15px;
  left: 15px;
  background: rgba(26, 26, 26, 0.9);
  color: #fff;
  padding: 8px 16px;
  border-radius: 25px;
  font-size: .8rem;
  font-weight: 600;
  backdrop-filter: blur(15px);
  border: 1px solid rgba(255,255,255,.2);
}

.currency-badge {
  position: absolute;
  top: 15px;
  right: 15px;
  background: rgba(66, 186, 37, 0.9);
  color: #fff;
  padding: 8px 12px;
  border-radius: 20px;
  font-size: .8rem;
  font-weight: 600;
  backdrop-filter: blur(15px);
  border: 1px solid rgba(255,255,255,.2);
}

.product-info {
  padding: 25px;
}

.product-title {
  font-size: 1.3rem;
  font-weight: 700;
  margin-bottom: 10px;
  color: #333;
  line-height: 1.3;
}

.product-description {
  color: #666;
  font-size: .95rem;
  margin-bottom: 18px;
  line-height: 1.5;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.product-route {
  font-size: 0.85rem;
  color: #666;
  margin-bottom: 15px;
  padding: 10px;
  background: #f8f9fa;
  border-radius: 8px;
  border-left: 3px solid #42ba25;
  display: flex;
  align-items: center;
  gap: 8px;
}

/* === INFORMACIÓN DEL VENDEDOR === */
.product-seller {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 18px;
  padding: 12px;
  background: #f8f9fa;
  border-radius: 12px;
}

.seller-avatar {
  position: relative;
  flex-shrink: 0;
}

.seller-avatar .profile-image-laurel img {
  width: 50px;
  height: 50px;
}

.seller-avatar .laurel-svg {
  width: 70px;
  height: 70px;
}

.seller-avatar .verified-badge {
  width: 18px;
  height: 18px;
  bottom: 2px;
  right: 2px;
}

.seller-avatar .verified-badge i {
  font-size: 10px;
}

.seller-info {
  flex: 1;
  min-width: 0;
}

.seller-name {
  font-weight: 600;
  font-size: .95rem;
  color: #333;
  margin-bottom: 6px;
  display: flex;
  align-items: center;
  gap: 6px;
  cursor: pointer;
}

.seller-name:hover {
  color: #42ba25;
}

.seller-name .verified-icon {
  color: #42ba25;
  font-size: 14px;
}

.seller-rating {
  font-size: .85rem;
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.seller-rating .rating-stars {
  color: #ffd700;
  font-size: 14px;
}

.seller-rating .rating-value {
  font-weight: 600;
}

.seller-rating .rating-count {
  color: #666;
  font-size: 12px;
}

.seller-rating .no-rating {
  color: #999;
  font-style: italic;
  font-size: 12px;
}

.product-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 20px;
  gap: 10px;
  flex-wrap: wrap;
}

.product-price {
  font-size: 1.5rem;
  font-weight: 700;
  color: #42ba25;
}

.add-to-cart-btn {
  background: #42ba25;
  color: #fff;
  border: none;
  padding: 12px 24px;
  border-radius: 25px;
  font-weight: 600;
  cursor: pointer;
  transition: all .3s ease;
  display: flex;
  align-items: center;
  gap: 8px;
  font-family: 'Inter', sans-serif;
}

.add-to-cart-btn:hover {
  background: #37a01f;
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(66, 186, 37, .3);
}

.add-to-cart-btn:disabled {
  background: #ccc;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}

.stock-info {
  font-size: .85rem;
  color: #28a745;
  margin-bottom: 12px;
  display: flex;
  align-items: center;
  gap: 6px;
  font-weight: 500;
}

.stock-low {
  color: #ffc107;
}

.stock-out {
  color: #dc3545;
}

/* === CARRITO FLOTANTE === */
.cart-floating {
  position: fixed;
  bottom: 30px;
  right: 30px;
  background: #42ba25;
  color: #fff;
  width: 70px;
  height: 70px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.6rem;
  cursor: pointer;
  box-shadow: 0 10px 30px rgba(66, 186, 37, .4);
  z-index: 1000;
  transition: all .3s ease;
}

.cart-floating:hover {
  transform: scale(1.1);
  box-shadow: 0 15px 40px rgba(66, 186, 37, .5);
}

.cart-count {
  position: absolute;
  top: -8px;
  right: -8px;
  background: #e74c3c;
  color: #fff;
  font-size: .85rem;
  min-width: 24px;
  height: 24px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  border: 3px solid #fff;
}

/* === ESTADOS DE LOADING Y VACÍO === */
.loading {
  text-align: center;
  padding: 80px;
  color: #666;
}

.loading i {
  animation: spin 1s linear infinite;
  font-size: 2.5rem;
  margin-bottom: 25px;
  color: #42ba25;
}

.empty-state {
  text-align: center;
  padding: 100px 20px;
  color: #666;
}

.empty-state i {
  font-size: 4.5rem;
  margin-bottom: 25px;
  color: #ddd;
}

.empty-state h3 {
  font-size: 1.5rem;
  margin-bottom: 15px;
  color: #333;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

/* === MODAL DEL CARRITO === */
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
  background: #f8f9fa;
  border-radius: 20px 20px 0 0;
}

.cart-title {
  font-size: 1.5rem;
  font-weight: 700;
  margin: 0;
  color: #333;
}

.close-cart {
  background: none;
  border: none;
  font-size: 1.8rem;
  cursor: pointer;
  color: #666;
  width: 35px;
  height: 35px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  transition: all .3s ease;
}

.close-cart:hover {
  background: #e9ecef;
  color: #333;
}

.cart-items {
  padding: 25px 30px;
}

.cart-item {
  display: flex;
  gap: 18px;
  padding: 18px 0;
  border-bottom: 1px solid #f0f0f0;
}

.cart-item:last-child {
  border-bottom: none;
}

.cart-item-image {
  width: 80px;
  height: 80px;
  border-radius: 12px;
  object-fit: cover;
}

.cart-item-info {
  flex: 1;
}

.cart-item-name {
  font-weight: 600;
  margin-bottom: 6px;
  font-size: .95rem;
  color: #333;
}

.cart-item-price {
  color: #42ba25;
  font-weight: 600;
  margin-bottom: 10px;
  font-size: 1.05rem;
}

.quantity-controls {
  display: flex;
  align-items: center;
  gap: 12px;
}

.qty-btn {
  background: #f0f0f0;
  border: none;
  width: 35px;
  height: 35px;
  border-radius: 8px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  transition: all .3s ease;
}

.qty-btn:hover {
  background: #e0e0e0;
}

.cart-total {
  padding: 30px;
  border-top: 2px solid #eee;
  text-align: center;
  background: #f8f9fa;
  border-radius: 0 0 20px 20px;
}

.total-amount {
  font-size: 1.8rem;
  font-weight: 700;
  color: #333;
  margin-bottom: 25px;
}

.checkout-btn {
  background: linear-gradient(135deg, #42ba25 0%, #37a01f 100%);
  color: #fff;
  border: none;
  padding: 18px 35px;
  border-radius: 12px;
  font-weight: 600;
  font-size: 1.1rem;
  cursor: pointer;
  width: 100%;
  transition: all .3s ease;
  font-family: 'Inter', sans-serif;
}

.checkout-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 30px rgba(66, 186, 37, .3);
}

/* === RESPONSIVE === */
@media (max-width: 768px) {
  .shop-header {
    padding: 70px 0;
    min-height: 450px;
  }
  
  .shop-header h1 {
    font-size: 2.5rem;
  }
  
  .logo-container img {
    max-width: 280px;
  }
  
  .shop-subtitle {
    font-size: 1.15rem;
  }
  
  .shop-description {
    font-size: 1rem;
  }
  
  .btn-primary, .btn-secondary {
    width: 100%;
    justify-content: center;
  }
  
  /* === FILTROS COLAPSABLES EN MÓVIL === */
  .filters-toggle {
    display: flex !important;
  }
  
  .filters-container {
    padding: 0;
    border: none;
    box-shadow: none;
    background: transparent;
  }
  
  .filters-content {
    max-height: 0;
    overflow: hidden;
    opacity: 0;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    background: #fff;
    border-radius: 15px;
    border: 2px solid #42ba25;
  }
  
  .filters-content.active {
    max-height: 2000px;
    opacity: 1;
    padding: 25px;
    margin-top: 0;
  }
  
  .filter-group {
    width: 100%;
    min-width: 100%;
  }
  
  .sell-btn {
    width: 100%;
    justify-content: center;
  }
  
  .products-grid {
    grid-template-columns: 1fr;
  }
  
  .product-footer {
    flex-direction: column;
    align-items: stretch;
  }
  
  .product-footer > div:last-child {
    width: 100%;
  }
  
  .add-to-cart-btn,
  .offer-btn {
    flex: 1;
    justify-content: center;
  }
  
  .cart-floating {
    width: 60px;
    height: 60px;
    bottom: 20px;
    right: 20px;
    font-size: 1.4rem;
  }
}

/* === LOGO MARCA DE AGUA === */
.logo-watermark {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  z-index: 1;
  pointer-events: none;
}

.logo-watermark img {
  max-width: 1000px;
  height: auto;
  opacity: 0.08;
}

@media (max-width: 768px) {
  .logo-watermark img {
    max-width: 350px;
  }
}
  </style>
</head>
<body data-user-logged="<?= $user_logged_in ? 'true' : 'false' ?>" 
      <?php if ($user_logged_in): ?>
      data-user-id="<?= $user_id ?>" 
      data-user-name="<?= htmlspecialchars($user_name) ?>"
      <?php endif; ?>>
      
  <?php include 'header2.php'; ?>

  <!-- HEADER PREMIUM SOFISTICADO -->
  <div class="shop-header">
    <div class="logo-watermark">
      <!--<img src="../Imagenes/logp_sendvialo_shop.png" alt="SendVialo Shop">-->
    </div>
    <div class="shop-container">
      <div class="header-content">
        <h1>
          <span class="accent">¿Quién quiere?</span><br>
          Productos de <span class="accent">Viajeros</span>
        </h1>

        <p class="shop-subtitle">
          Viajeros ofrecen productos exclusivos de sus destinos
        </p>

        <p class="shop-description">
          Descubre productos únicos que viajeros traen de todo el mundo.
          Compra directamente, negocia el precio y recibe artículos exclusivos en tu ciudad.
        </p>
        
        <div class="cta-section">
          <a href="#productos" class="btn-primary">
            <i class="fas fa-shopping-bag"></i>
            Ver Productos
          </a>
          <?php if ($user_logged_in): ?>
            <a href="shop-manage-products.php" class="btn-secondary">
              <i class="fas fa-plus-circle"></i>
              ¿Quién quiere…?
            </a>
          <?php else: ?>
            <a href="../login.php" class="btn-secondary login-required" onclick="return confirmLogin()">
              <i class="fas fa-sign-in-alt"></i>
              Iniciar Sesión para Ofrecer
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- SECCIÓN DE FILTROS CON COLAPSABLE MÓVIL -->
  <div class="filters-section" id="productos">
    <div class="shop-container">
      <div class="filters-wrapper">
        <!-- Botón Toggle (solo visible en móvil) -->
        <button class="filters-toggle" id="filters-toggle" onclick="toggleFilters()">
          <span class="filters-toggle-text">
            <i class="fas fa-filter"></i>
            <span>Filtros de Búsqueda</span>
          </span>
          <i class="fas fa-chevron-down"></i>
        </button>

        <!-- Contenedor de Filtros -->
        <div class="filters-container">
          <div class="filters-content" id="filters-content">
            <div class="filter-group">
              <label for="search">Buscar producto</label>
              <input type="text" id="search" placeholder="¿Qué buscas?">
            </div>

            <div class="filter-group">
              <label for="origin">Origen</label>
              <select id="origin">
                <option value="">Todos los países</option>
              </select>
            </div>

            <div class="filter-group">
              <label for="destination">Destino</label>
              <select id="destination">
                <option value="">Todos los destinos</option>
              </select>
            </div>

            <div class="filter-group">
              <label for="currency">Moneda</label>
              <select id="shop-currency-selector">
                <option value="EUR">€ Euros</option>
                <option value="USD">$ Dólares</option>
                <option value="BOB">Bs Bolivianos</option>
              </select>
              <input type="hidden" id="shop-currency-input" value="EUR">
            </div>

            <div class="filter-group">
              <label for="category">Categoría</label>
              <select id="category">
                <option value="">Todas las categorías</option>
                <option value="food">Comida y bebidas</option>
                <option value="crafts">Artesanías</option>
                <option value="fashion">Moda y accesorios</option>
                <option value="electronics">Electrónicos</option>
                <option value="books">Libros</option>
                <option value="cosmetics">Cosméticos</option>
              </select>
            </div>

            <?php if (!$user_logged_in): ?>
              <a href="../login.php" class="sell-btn login-required" onclick="return confirmLogin()">
                <i class="fas fa-sign-in-alt"></i> Iniciar sesión para vender
              </a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- TÍTULO PREVIO A LOS PRODUCTOS -->
  <div class="prefilters-title" aria-labelledby="titulo-prefiltros">
    <div class="shop-container">
      <h2 id="titulo-prefiltros">Ofertas de viajeros disponibles ahora</h2>
      <p class="prefilters-subtitle">¿Quién quiere? Encuentra productos exclusivos.</p>
    </div>
  </div>

  <!-- SECCIÓN DE PRODUCTOS -->
  <div class="products-section">
    <div class="shop-container">
      <!-- Grid de productos -->
      <div class="products-grid" id="products-grid"></div>

      <!-- Estado de carga -->
      <div class="loading" id="loading">
        <i class="fas fa-spinner"></i>
        <p>Cargando productos exclusivos...</p>
      </div>

      <!-- Estado vacío -->
      <div class="empty-state" id="empty-state" style="display:none;">
        <i class="fas fa-store"></i>
        <h3>No hay ofertas disponibles</h3>
        <p>Sé el primero en ofrecer productos de tus viajes</p>
        <?php if ($user_logged_in): ?>
          <a href="shop-manage-products.php" class="btn-primary" style="margin-top: 25px;">
            <i class="fas fa-plus-circle"></i> ¿Quién quiere…?
          </a>
        <?php else: ?>
          <a href="../login.php" class="btn-primary" style="margin-top: 25px;">
            <i class="fas fa-sign-in-alt"></i> Iniciar sesión para ofrecer
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Carrito flotante -->
  <div class="cart-floating" onclick="openCart()">
    <i class="fas fa-shopping-cart"></i>
    <span class="cart-count" id="cart-count">0</span>
  </div>

  <!-- Modal del carrito -->
  <div class="cart-modal" id="cart-modal">
    <div class="cart-content">
      <div class="cart-header">
        <h3 class="cart-title">Mi Carrito</h3>
        <button class="close-cart" onclick="closeCart()">×</button>
      </div>
      <div class="cart-items" id="cart-items"></div>
      <div class="cart-total" id="cart-total">
        <div class="total-amount" id="cart-total-amount">€0.00</div>
        <button class="checkout-btn" onclick="checkout()">
          <i class="fas fa-credit-card"></i> Proceder al pago
        </button>
      </div>
    </div>
  </div>

  <!-- Modal de Hacer Oferta -->
  <div class="cart-modal" id="offer-modal" style="display: none;">
    <div class="cart-content" style="max-width: 600px;">
      <div class="cart-header" style="background: linear-gradient(135deg, #FF9800, #FB8C00);">
        <h3 class="cart-title" style="color: white;">
          <i class="fas fa-hand-holding-usd"></i> Hacer Oferta
        </h3>
        <button class="close-cart" onclick="closeOfferModal()" style="color: white;">×</button>
      </div>
      <form id="offer-form">
        <div style="padding: 30px;">
          <input type="hidden" id="offer-product-id" name="product_id">

          <div style="background: #f8f9fa; padding: 20px; border-radius: 12px; margin-bottom: 25px; border: 2px solid #42ba25;">
            <div style="display: flex; align-items: center; gap: 20px;">
              <img id="offer-product-image" src="" style="width: 80px; height: 80px; border-radius: 12px; object-fit: cover; border: 3px solid #42ba25;">
              <div style="flex: 1;">
                <h4 id="offer-product-name" style="margin: 0 0 8px 0; font-size: 1.1rem; color: #333;"></h4>
                <div style="font-size: 1.3rem; font-weight: 700; color: #42ba25;">
                  <span id="offer-product-price"></span>
                </div>
              </div>
            </div>
          </div>

          <div style="margin-bottom: 20px;">
            <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #333;">
              Tu Oferta *
            </label>
            <input type="number" id="offer-price" name="proposed_price" step="0.01" min="0.01" required
                   style="width: 100%; padding: 14px; border: 2px solid #e1e5e9; border-radius: 12px; font-size: 15px; font-family: 'Inter', sans-serif;"
                   placeholder="Ingresa tu precio">
          </div>

          <div style="margin-bottom: 20px;">
            <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #333;">
              Moneda *
            </label>
            <select id="offer-currency" name="proposed_currency" required
                    style="width: 100%; padding: 14px; border: 2px solid #e1e5e9; border-radius: 12px; font-size: 15px; font-family: 'Inter', sans-serif;">
              <option value="EUR">€ Euros</option>
              <option value="USD">$ Dólares</option>
              <option value="BOB">Bs Bolivianos</option>
            </select>
          </div>

          <div style="margin-bottom: 20px;">
            <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #333;">
              Cantidad *
            </label>
            <input type="number" id="offer-quantity" name="quantity" min="1" value="1" required
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

  <!-- Scripts -->
  <script src="shop-currency-integration.js"></script>
  <script src="js/shop-unified-ratings.js"></script>

  <script>
    // ============================================
    // FUNCIÓN PARA TOGGLE DE FILTROS EN MÓVIL
    // ============================================
    function toggleFilters() {
      const filtersContent = document.getElementById('filters-content');
      const toggleBtn = document.getElementById('filters-toggle');
      
      filtersContent.classList.toggle('active');
      toggleBtn.classList.toggle('active');
    }

    // Auto-abrir filtros en desktop
    window.addEventListener('DOMContentLoaded', () => {
      if (window.innerWidth > 768) {
        document.getElementById('filters-content').classList.add('active');
      }
    });

    // Ajustar al redimensionar ventana
    window.addEventListener('resize', () => {
      const filtersContent = document.getElementById('filters-content');
      if (window.innerWidth > 768) {
        filtersContent.classList.add('active');
      }
    });

    // Variables globales
    const $grid = document.getElementById('products-grid');
    const $loading = document.getElementById('loading');
    const $empty = document.getElementById('empty-state');

    let products = [];
    let allOrigins = new Set();
    let allDestinations = new Set();

    // Variables de usuario desde PHP
    const userLoggedIn = document.body.dataset.userLogged === 'true';
    const currentUserId = userLoggedIn ? document.body.dataset.userId : null;
    const currentUserName = userLoggedIn ? document.body.dataset.userName : null;

    console.log('🛍️ SendVialo Shop con filtros colapsables móvil');

    document.addEventListener('DOMContentLoaded', () => {
      console.log('🛍️ SendVialo Shop cargando...');
      cargarProductos();
      setupEventListeners();
    });

    function confirmLogin() {
      Swal.fire({
        icon: 'info',
        title: 'Iniciar sesión requerido',
        text: 'Necesitas iniciar sesión para vender productos en SendVialo Shop',
        showCancelButton: true,
        confirmButtonText: 'Ir al login',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#4CAF50',
        cancelButtonColor: '#6c757d'
      }).then((result) => {
        if (result.isConfirmed) {
          window.location.href = '../login.php';
        }
      });
      return false;
    }

    function setupEventListeners() {
      document.getElementById('search').addEventListener('input', aplicarFiltros);
      document.getElementById('origin').addEventListener('change', aplicarFiltros);
      document.getElementById('destination').addEventListener('change', aplicarFiltros);
      document.getElementById('category').addEventListener('change', aplicarFiltros);
      document.getElementById('shop-currency-selector').addEventListener('change', function() {
        document.getElementById('shop-currency-input').value = this.value;
        renderProductos(products);
      });
      
      window.addEventListener('shopCurrencyChanged', () => renderProductos(products));
      
      // Smooth scroll para el botón de explorar productos
      document.querySelector('a[href="#productos"]')?.addEventListener('click', function(e) {
        e.preventDefault();
        document.getElementById('productos').scrollIntoView({
          behavior: 'smooth'
        });
      });
    }

    async function cargarProductos() {
      try {
        const res = await fetch('shop-actions.php?action=get_products');
        const data = await res.json();
        
        console.log('📊 Datos de productos recibidos:', data);
        
        if (data.success) {
          products = data.products || [];
          procesarFiltrosDisponibles();
          renderProductos(products);
          console.log(`✅ ${products.length} productos cargados`);
        } else {
          console.error('❌ Error del servidor:', data.error);
          products = [];
          renderProductos(products);
        }
      } catch (e) {
        console.error('❌ Error cargando productos:', e);
        products = [];
        renderProductos(products);
      }
    }

    function procesarFiltrosDisponibles() {
      products.forEach(p => {
        if (p.origin_country) allOrigins.add(extractCityFromLocation(p.origin_country));
        if (p.destination_city) allDestinations.add(extractCityFromLocation(p.destination_city));
      });

      const originSelect = document.getElementById('origin');
      const destSelect = document.getElementById('destination');

      originSelect.innerHTML = '<option value="">Todos los países</option>';
      Array.from(allOrigins).sort().forEach(origin => {
        originSelect.innerHTML += `<option value="${origin}">${origin}</option>`;
      });

      destSelect.innerHTML = '<option value="">Todos los destinos</option>';
      Array.from(allDestinations).sort().forEach(dest => {
        destSelect.innerHTML += `<option value="${dest}">${dest}</option>`;
      });
    }

    function extractCityFromLocation(location) {
      if (!location) return '';
      return location.split(',')[0].trim();
    }

    function aplicarFiltros() {
      const q = document.getElementById('search').value.toLowerCase().trim();
      const origin = document.getElementById('origin').value;
      const dest = document.getElementById('destination').value;
      const cat = document.getElementById('category').value;
      
      const result = products.filter(p => {
        const okQ = !q || (p.name?.toLowerCase().includes(q) || p.description?.toLowerCase().includes(q));
        const okO = !origin || extractCityFromLocation(p.origin_country) === origin;
        const okD = !dest || extractCityFromLocation(p.destination_city) === dest;
        const okC = !cat || p.category === cat;
        return okQ && okO && okD && okC;
      });
      
      renderProductos(result);
    }

function renderProductos(list) {
    $grid.innerHTML = '';
    
    if (!list || list.length === 0) {
        $loading.style.display = 'none';
        $empty.style.display = 'block';
        return;
    }
    
    $empty.style.display = 'none';
    $loading.style.display = 'none';

    list.forEach(p => {
        const currentCurrency = document.getElementById('shop-currency-input').value || 'EUR';
        
        let displayPrice = p.price;
        let displayCurrency = p.currency || 'EUR';
        
        if (window.ShopCurrency && window.ShopCurrency.convertPrice) {
            displayPrice = window.ShopCurrency.convertPrice(p.price, p.currency || 'EUR', currentCurrency);
            displayCurrency = currentCurrency;
        }
        
        const priceFormatted = formatPrice(displayPrice, displayCurrency);
        
        let tripBadge = '';
        if (p.trip_code) {
            tripBadge = `<div class="trip-badge">${p.trip_code}</div>`;
        } else if (p.origin_country && p.destination_city) {
            const originShort = extractCityFromLocation(p.origin_country).substring(0, 3).toUpperCase();
            const destShort = extractCityFromLocation(p.destination_city).substring(0, 3).toUpperCase();
            tripBadge = `<div class="trip-badge">${originShort}→${destShort}</div>`;
        }

        const productImage = (p.images && p.images[0]) ? 
            p.images[0] : 
            `https://images.unsplash.com/photo-${getRandomUnsplashId(p.category)}?w=400&h=300&fit=crop&q=80`;

        let stockClass = 'stock-info';
        let stockText = `${p.stock} disponibles`;
        if (p.stock <= 5) {
            stockClass += ' stock-low';
            stockText = `¡Solo ${p.stock} disponibles!`;
        }
        if (p.stock === 0) {
            stockClass += ' stock-out';
            stockText = 'Agotado';
        }

        let routeInfo = '';
        if (p.route_display) {
            routeInfo = `<div class="product-route">🗺 Ruta: ${p.route_display}</div>`;
        } else if (p.origin_country && p.destination_city) {
            const origin = extractCityFromLocation(p.origin_country);
            const dest = extractCityFromLocation(p.destination_city);
            routeInfo = `<div class="product-route">🗺 Ruta: ${origin} → ${dest}</div>`;
        }

        const rating = parseFloat(p.seller_rating) || 0;
        const totalRatings = parseInt(p.total_ratings) || 0;
        const isVerified = p.seller_verified || false;
        const badgeType = getBadgeType(rating);

        let sellerAvatar = p.seller_avatar;
        if (!sellerAvatar || sellerAvatar.includes('user-default.jpg')) {
            const name = p.seller_name || 'Usuario';
            sellerAvatar = `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=667eea&color=fff&size=50`;
        }

        const card = document.createElement('div');
        card.className = 'product-card';
        
        card.onclick = function(e) {
            if (e.target.classList.contains('add-to-cart-btn') || 
                e.target.closest('.add-to-cart-btn') ||
                e.target.classList.contains('seller-name') ||
                e.target.closest('.seller-name') ||
                e.target.classList.contains('offer-btn') ||
                e.target.closest('.offer-btn')) {
                return;
            }
            
            window.location.href = `shop-product-detail.php?id=${p.id}`;
        };
        
        card.innerHTML = `
            <div class="product-image">
                <img src="${productImage}" alt="${p.name}" loading="lazy">
                ${tripBadge}
                <div class="currency-badge">${getCurrencySymbol(displayCurrency)}</div>
            </div>
            <div class="product-info">
                <div class="product-title">${p.name}</div>
                <div class="product-description">${p.description || ''}</div>
                
                ${routeInfo}
                
                <div class="product-seller">
                    <div class="seller-avatar">
                        ${createProfileWithLaurel(sellerAvatar, rating, isVerified, 50)}
                    </div>
                    <div class="seller-info">
                        <div class="seller-name" onclick="event.stopPropagation(); window.location.href='shop-seller-profile.php?id=${p.seller_id}'">
                            ${p.seller_name || 'Viajero'}
                            ${isVerified ? '<i class="fas fa-check-circle verified-icon" title="Usuario verificado"></i>' : ''}
                        </div>
                        <div class="seller-rating">
                            ${rating > 0 ? `
                                <span class="rating-stars">${generateStarRating(rating)}</span>
                                <span class="rating-value ${badgeType}-text">${rating}</span>
                                <span class="rating-count">(${totalRatings})</span>
                            ` : '<span class="no-rating">Sin valoraciones</span>'}
                        </div>
                    </div>
                </div>

                <div class="${stockClass}">
                    <i class="fas fa-box"></i>
                    ${stockText}
                </div>

                <div class="product-footer">
                    <div class="product-price" data-original-price="${p.price}" data-original-currency="${p.currency}">
                        ${priceFormatted}
                    </div>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap; width: 100%;">
                        <button class="add-to-cart-btn" onclick='event.stopPropagation(); addToCart(${JSON.stringify(p).replace(/'/g, "&apos;")})' ${p.stock === 0 ? 'disabled' : ''} style="flex: 1;">
                            <i class="fas fa-cart-plus"></i>
                            ${p.stock === 0 ? 'Agotado' : 'Añadir'}
                        </button>
                        ${userLoggedIn && currentUserId != p.seller_id ? `
                            <button class="offer-btn" onclick='event.stopPropagation(); openOfferModal(${JSON.stringify(p).replace(/'/g, "&apos;")})' style="
                                background: linear-gradient(135deg, #FF9800, #FB8C00);
                                color: white;
                                border: none;
                                padding: 12px 20px;
                                border-radius: 25px;
                                font-weight: 600;
                                cursor: pointer;
                                transition: all .3s ease;
                                display: inline-flex;
                                align-items: center;
                                justify-content: center;
                                gap: 6px;
                                font-family: 'Inter', sans-serif;
                                font-size: 0.9rem;
                                flex: 1;
                            " onmouseover="this.style.background='linear-gradient(135deg, #FB8C00, #F57C00)'; this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 15px rgba(255, 152, 0, .3)';" onmouseout="this.style.background='linear-gradient(135deg, #FF9800, #FB8C00)'; this.style.transform=''; this.style.boxShadow='';">
                                <i class="fas fa-hand-holding-usd"></i>
                                Oferta
                            </button>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
        $grid.appendChild(card);
    });
}

    // FUNCIONES DEL SISTEMA DE VALORACIONES
    function getBadgeType(rating) {
      if (rating >= 4.8) return 'diamond';
      if (rating >= 4.5) return 'gold';
      if (rating >= 4.0) return 'silver';
      if (rating >= 3.5) return 'bronze';
      return 'basic';
    }

    function generateStarRating(rating) {
      const fullStars = Math.floor(rating);
      const hasHalfStar = rating % 1 >= 0.5;
      let stars = '';
      
      for (let i = 0; i < 5; i++) {
        if (i < fullStars) {
          stars += '★';
        } else if (i === fullStars && hasHalfStar) {
          stars += '⭐';
        } else {
          stars += '☆';
        }
      }
      
      return stars;
    }

    function createProfileWithLaurel(imageUrl, rating, isVerified = false, size = 50) {
      const tipo = getBadgeType(rating);
      const laurelSize = Math.round(size * 1.4);
      
      return `
        <div class="profile-image-laurel">
          <img src="${imageUrl}" alt="Perfil" width="${size}" height="${size}" onerror="this.onerror=null; this.src='user-default.jpg';">
          ${generateLaurelSVG(tipo, laurelSize)}
          ${isVerified ? '<div class="verified-badge"><i class="fas fa-check"></i></div>' : ''}
        </div>
      `;
    }

    function generateLaurelSVG(tipo, size = 70) {
      const colors = {
        diamond: { primary: '#e1f5fe', secondary: '#81d4fa', glow: '#4fc3f7' },
        gold: { primary: '#ffd700', secondary: '#ffb300', glow: '#ff8f00' },
        silver: { primary: '#e0e0e0', secondary: '#bdbdbd', glow: '#9e9e9e' },
        bronze: { primary: '#ffab91', secondary: '#ff8a65', glow: '#ff7043' },
        basic: { primary: '#f5f5f5', secondary: '#e0e0e0', glow: '#bdbdbd' }
      };
      
      const color = colors[tipo] || colors.basic;
      const uniqueId = Math.random().toString(36).substr(2, 9);
      
      return `
        <svg width="${size}" height="${size}" viewBox="0 0 120 120" class="laurel-svg laurel-${tipo}">
          <defs>
            <radialGradient id="laurelGradient${uniqueId}" cx="50%" cy="30%">
              <stop offset="0%" stop-color="${color.primary}" />
              <stop offset="100%" stop-color="${color.secondary}" />
            </radialGradient>
            <filter id="glow${uniqueId}">
              <feGaussianBlur stdDeviation="2" result="coloredBlur"/>
              <feMerge> 
                <feMergeNode in="coloredBlur"/>
                <feMergeNode in="SourceGraphic"/>
              </feMerge>
            </filter>
          </defs>
          <g fill="url(#laurelGradient${uniqueId})" stroke="${color.glow}" stroke-width="0.5" filter="url(#glow${uniqueId})">
            <path d="M30 45 Q20 35 25 25 Q35 20 45 30 Q50 35 45 45 Q35 50 30 45" />
            <path d="M90 45 Q100 35 95 25 Q85 20 75 30 Q70 35 75 45 Q85 50 90 45" />
            <path d="M35 55 Q25 45 30 35 Q40 30 50 40 Q55 45 50 55 Q40 60 35 55" />
            <path d="M85 55 Q95 45 90 35 Q80 30 70 40 Q65 45 70 55 Q80 60 85 55" />
            <path d="M40 65 Q30 55 35 45 Q45 40 55 50 Q60 55 55 65 Q45 70 40 65" />
            <path d="M80 65 Q90 55 85 45 Q75 40 65 50 Q60 55 65 65 Q75 70 80 65" />
            <path d="M45 75 Q35 65 40 55 Q50 50 60 60 Q65 65 60 75 Q50 80 45 75" />
            <path d="M75 75 Q85 65 80 55 Q70 50 60 60 Q55 65 60 75 Q70 80 75 75" />
          </g>
        </svg>
      `;
    }

    // Funciones auxiliares
    function formatPrice(amount, currency) {
      const symbol = getCurrencySymbol(currency);
      const value = Number(amount).toFixed(2);
      return currency === 'USD' ? `${symbol}${value}` : `${value}${symbol}`;
    }

    function getCurrencySymbol(currency) {
      const symbols = {
        'EUR': '€',
        'USD': '$',
        'BOB': 'Bs',
        'BRL': 'R$',
        'ARS': '$',
        'COP': '$',
        'MXN': '$',
        'PEN': 'S/'
      };
      return symbols[currency] || '€';
    }

    function getRandomUnsplashId(category) {
      const ids = {
        food: ['1565299624946-3dc8b66b0e83', '1546549032-b9c7a6ac5ac9', '1555939594-f7405dc1e4a0'],
        crafts: ['1578662015441-ce7ecf7fa773', '1513475382585-d06e58bcb0e0', '1571115764594-a9ff6ffa2ddb'],
        fashion: ['1542291026-7eec264c27ff', '1556905055-8f358a7a47b2', '1515886657613-9f3515693f4f'],
        electronics: ['1581092335878-6e6f13b9a4f6', '1593642532842-98bf7f65da4f', '1535223289827-42f1e9919769'],
        books: ['1481627834876-b7833e8f5570', '1507003211169-0a1dd7a6468f', '1495640388908-d46ede6edefe'],
        cosmetics: ['1596462502858-b3b5fe9a5c7a', '1522338242992-e1a54906a8ba', '1559056199-f4c15a42eb22']
      };
      const categoryIds = ids[category] || ids.crafts;
      return categoryIds[Math.floor(Math.random() * categoryIds.length)];
    }

    // Funciones del carrito
    function getCart() { return JSON.parse(localStorage.getItem('sendvialo_cart') || '[]') }
    function setCart(c) { localStorage.setItem('sendvialo_cart', JSON.stringify(c)); updateCartBadge() }
    function updateCartBadge() { 
      const count = getCart().reduce((n, i) => n + i.quantity, 0);
      document.getElementById('cart-count').textContent = count;
      document.getElementById('cart-count').style.display = count > 0 ? 'flex' : 'none';
    }

    function addToCart(p) {
      const cart = getCart();
      const idx = cart.findIndex(i => i.product_id === p.id);
      
      if (idx > -1) {
        cart[idx].quantity += 1;
      } else {
        cart.push({
          product_id: p.id,
          name: p.name,
          price: Number(p.price),
          currency: p.currency || 'EUR',
          quantity: 1,
          image: (p.images && p.images[0]) ? p.images[0] : null
        });
      }
      
      setCart(cart);
      
      Swal.fire({
        icon: 'success',
        title: '¡Añadido al carrito!',
        text: p.name,
        timer: 1500,
        showConfirmButton: false,
        toast: true,
        position: 'top-end',
        background: '#4CAF50',
        color: '#fff'
      });
    }

    function openCart() {
      document.getElementById('cart-modal').style.display = 'block';
      renderCart();
    }

    function closeCart() {
      document.getElementById('cart-modal').style.display = 'none';
    }

    function renderCart() {
      const cart = getCart();
      const itemsContainer = document.getElementById('cart-items');
      
      if (cart.length === 0) {
        itemsContainer.innerHTML = '<div style="text-align:center;padding:50px;color:#666;"><i class="fas fa-shopping-cart" style="font-size:3.5rem;margin-bottom:25px;color:#ddd;"></i><h3>Tu carrito está vacío</h3><p>¡Descubre productos únicos y añádelos aquí!</p></div>';
        document.getElementById('cart-total-amount').textContent = '€0.00';
        return;
      }

      let total = 0;
      const currentCurrency = document.getElementById('shop-currency-input').value || 'EUR';
      
      itemsContainer.innerHTML = cart.map((item, index) => {
        const convertedPrice = window.ShopCurrency ? 
          window.ShopCurrency.convertPrice(item.price, item.currency, currentCurrency) : 
          item.price;
        
        const itemTotal = convertedPrice * item.quantity;
        total += itemTotal;
        
        return `
          <div class="cart-item">
            <img src="${item.image || 'https://via.placeholder.com/80x80'}" alt="${item.name}" class="cart-item-image">
            <div class="cart-item-info">
              <div class="cart-item-name">${item.name}</div>
              <div class="cart-item-price">${formatPrice(convertedPrice, currentCurrency)} x ${item.quantity}</div>
              <div class="quantity-controls">
                <button class="qty-btn" onclick="changeQuantity(${index}, -1)">-</button>
                <span style="margin: 0 15px; font-weight: 600;">${item.quantity}</span>
                <button class="qty-btn" onclick="changeQuantity(${index}, 1)">+</button>
                <button class="qty-btn" onclick="removeFromCart(${index})" style="margin-left: 15px; color: #dc3545;">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
            </div>
          </div>
        `;
      }).join('');
      
      document.getElementById('cart-total-amount').textContent = formatPrice(total, currentCurrency);
    }

    function changeQuantity(index, delta) {
      const cart = getCart();
      cart[index].quantity += delta;
      
      if (cart[index].quantity <= 0) {
        cart.splice(index, 1);
      }
      
      setCart(cart);
      renderCart();
    }

    function removeFromCart(index) {
      const cart = getCart();
      cart.splice(index, 1);
      setCart(cart);
      renderCart();
    }

    async function checkout() {
      const cart = getCart();
      if (cart.length === 0) {
        Swal.fire({
          icon: 'warning',
          title: 'Carrito vacío',
          text: 'No hay productos en el carrito',
          confirmButtonColor: '#4CAF50'
        });
        return;
      }

      <?php if (!$user_logged_in): ?>
        Swal.fire({
          icon: 'warning',
          title: 'Iniciar sesión requerido',
          html: 'Necesitas iniciar sesión para realizar una compra.<br><br>¿Deseas ir a la página de inicio de sesión?',
          showCancelButton: true,
          confirmButtonText: 'Ir a login',
          cancelButtonText: 'Cancelar',
          confirmButtonColor: '#4CAF50',
          cancelButtonColor: '#6c757d'
        }).then((result) => {
          if (result.isConfirmed) {
            window.location.href = '../login.php?redirect=shop';
          }
        });
        return;
      <?php endif; ?>

      Swal.fire({
        title: 'Preparando checkout...',
        html: '<div class="spinner-border text-success" role="status"><span class="visually-hidden">Cargando...</span></div>',
        allowOutsideClick: false,
        showConfirmButton: false
      });

      const form = document.createElement('form');
      form.method = 'POST';
      form.action = 'payment_shop.php';
      
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'cart';
      input.value = JSON.stringify(cart);
      
      form.appendChild(input);
      document.body.appendChild(form);
      form.submit();
    }

    updateCartBadge();

    // SISTEMA DE OFERTAS
    let currentOfferProduct = null;

    function openOfferModal(product) {
        if (!userLoggedIn) {
            Swal.fire({
                icon: 'warning',
                title: 'Iniciar sesión requerido',
                text: 'Necesitas iniciar sesión para hacer ofertas',
                showCancelButton: true,
                confirmButtonText: 'Ir a login',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#42ba25',
                cancelButtonColor: '#6c757d'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../login.php?redirect=shop';
                }
            });
            return;
        }

        currentOfferProduct = product;

        document.getElementById('offer-product-id').value = product.id;
        document.getElementById('offer-product-name').textContent = product.name;
        document.getElementById('offer-product-price').textContent = formatPrice(product.price, product.currency);

        const productImage = (product.images && product.images[0]) ? product.images[0] :
                             (product.primary_image) ||
                             `https://images.unsplash.com/photo-${getRandomUnsplashId(product.category)}?w=400&h=300&fit=crop&q=80`;
        document.getElementById('offer-product-image').src = productImage;

        document.getElementById('offer-currency').value = product.currency || 'EUR';

        const maxQuantity = product.stock || 1;
        document.getElementById('offer-quantity').max = maxQuantity;
        document.getElementById('offer-quantity').value = Math.min(1, maxQuantity);

        document.getElementById('offer-price').value = '';
        document.getElementById('offer-message').value = '';

        document.getElementById('offer-modal').style.display = 'block';
    }

    function closeOfferModal() {
        document.getElementById('offer-modal').style.display = 'none';
        currentOfferProduct = null;
    }

    document.getElementById('offer-form').addEventListener('submit', async function(e) {
        e.preventDefault();

        if (!userLoggedIn) {
            return;
        }

        const formData = new FormData(this);
        formData.append('action', 'submit_product_proposal');

        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
        submitBtn.disabled = true;

        try {
            const response = await fetch('shop-actions.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                closeOfferModal();

                Swal.fire({
                    icon: 'success',
                    title: '¡Oferta enviada!',
                    text: 'El vendedor revisará tu oferta y podrás negociar el precio',
                    confirmButtonColor: '#42ba25',
                    timer: 3000
                });

                document.getElementById('offer-form').reset();

            } else {
                throw new Error(data.error || 'Error al enviar oferta');
            }
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message,
                confirmButtonColor: '#42ba25'
            });
        } finally {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    });

    document.getElementById('offer-modal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeOfferModal();
        }
    });

    console.log('🎯 Sistema completo inicializado');
  </script>
</body>
</html>