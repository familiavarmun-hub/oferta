<?php
// index.php - Página principal OFERTA (¿Quién quiere?)
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
  <title>¿Quién Quiere? | SendVialo Shop</title>

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
  --warning: #f59e0b;
}

* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Inter', sans-serif; background-color: var(--bg-body); color: var(--slate-900); -webkit-font-smoothing: antialiased; }

/* ========== HERO & FULL-WIDTH SEARCH ========== */
.hero-header {
  background: white;
  padding: 85px 20px 20px;
  border-bottom: 1px solid #eee;
}
.hero-header h1 {
  font-size: 36px;
  font-weight: 900;
  letter-spacing: -1.5px;
  margin-bottom: 10px;
  text-align: center;
  color: var(--slate-900);
}
.hero-header h1 .accent { color: var(--primary); }
.hero-subtitle {
  text-align: center;
  font-size: 16px;
  color: var(--slate-600);
  margin-bottom: 20px;
}

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

.modal-body { padding: 30px; }

.search-section { margin-bottom: 30px; }

.search-section-title {
  font-size: 13px; font-weight: 900; text-transform: uppercase;
  color: var(--slate-900); margin-bottom: 15px; letter-spacing: 0.5px;
  display: flex; align-items: center; gap: 8px;
}

.search-section-title i { color: var(--primary); font-size: 14px; }

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
.location-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

.input-wrapper { position: relative; }

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
.category-pills { display: flex; flex-wrap: wrap; gap: 10px; }

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
.currency-selector { display: flex; gap: 12px; }

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
.budget-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

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
.btn-secondary:hover { background: var(--zinc-100); }

.btn-primary {
  flex: 2; padding: 16px; border-radius: 14px; border: none;
  background: var(--slate-900); color: white; font-weight: 800;
  cursor: pointer; transition: 0.3s; font-size: 14px;
  display: flex; align-items: center; justify-content: center; gap: 8px;
}
.btn-primary:hover { background: var(--primary); }
.btn-primary i { font-size: 16px; }

/* ========== FILTROS BAR STICKY ========== */
.filter-bar { background: white; padding: 12px 20px; border-bottom: 1px solid #eee; position: sticky; top: 0; z-index: 900; }
.filter-bar .container { max-width: 1400px; margin: 0 auto; display: flex; align-items: center; }
.tabs { display: flex; gap: 8px; overflow-x: auto; scrollbar-width: none; width: 100%; }
.tab { padding: 8px 18px; border-radius: 12px; border: none; background: transparent; font-weight: 700; font-size: 12px; color: var(--slate-600); cursor: pointer; transition: 0.3s; white-space: nowrap; }
.tab.active { background: var(--slate-900); color: white; }

/* ========== GRID & CARDS ========== */
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

/* CORAZÓN ELITE - FAVORITOS */
.favorite-btn {
  position: absolute; top: 15px; right: 15px; background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(8px);
  border: 1px solid rgba(0,0,0,0.05); width: 38px; height: 38px; border-radius: 50%; color: #94a3b8;
  cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 20; transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.favorite-btn.active { color: var(--danger) !important; transform: scale(1.1); background: white; }
.favorite-btn i { font-size: 18px; pointer-events: none; }
.favorite-btn:active { transform: scale(0.9); }

/* Badge de stock */
.stock-tag {
  position: absolute; top: 15px; left: 15px; background: var(--primary); color: white;
  padding: 5px 12px; border-radius: 10px; font-size: 10px; font-weight: 900; text-transform: uppercase;
  box-shadow: 0 4px 10px rgba(66, 186, 37, 0.3);
}
.stock-tag.low { background: var(--warning); }
.stock-tag.out { background: var(--danger); }

.card-body { padding: 24px; flex-grow: 1; cursor: pointer; display: flex; flex-direction: column; }
.offer-question { font-size: 11px; font-weight: 800; color: var(--primary); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
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

/* ROUTE BOX */
.route-box { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; background: #f8fafc; padding: 15px; border-radius: 18px; margin-bottom: 20px; border: 1px solid #f1f5f9; }
.route-label { font-size: 9px; font-weight: 800; color: var(--primary); text-transform: uppercase; margin-bottom: 4px; }
.route-val { font-size: 13px; font-weight: 700; color: var(--slate-900); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* USER INFO */
.user-info { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; border-top: 1px solid #f1f5f9; padding-top: 15px; }
.user-info > div:nth-child(2) { flex: 1; }
.avatar-img { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; border: 2px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }

.sales-badge {
  display: inline-flex; align-items: center; gap: 6px; background: #f1f5f9;
  padding: 5px 12px; border-radius: 10px; font-size: 11px; font-weight: 700; margin: 0; color: var(--slate-600);
}
.sales-badge i { color: var(--primary); }

/* PRICE & ACTION */
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

.quantity-badge {
  background: var(--primary-soft);
  color: var(--primary);
  padding: 6px 14px;
  border-radius: 100px;
  font-size: 13px;
  font-weight: 800;
  display: inline-block;
  margin-top: 8px;
  width: fit-content;
}

.btn-cart {
  background: var(--primary);
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
  display: flex;
  align-items: center;
  gap: 8px;
}
.btn-cart:hover {
  background: var(--primary-dark);
  transform: translateY(-2px);
  box-shadow: 0 8px 15px rgba(66, 186, 37, 0.3);
}
.btn-cart:active { transform: translateY(0); }
.btn-cart:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-cart i { font-size: 16px; }

/* ========== CÓMO FUNCIONA ========== */
.how-it-works {
  background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
  padding: 80px 20px;
  margin-top: 60px;
}
.how-it-works .container { max-width: 1200px; margin: 0 auto; }
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
  display: inline-flex;
  background: var(--primary);
  color: white;
  width: 50px;
  height: 50px;
  border-radius: 50%;
  font-size: 24px;
  font-weight: 900;
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

/* ========== EMPTY STATE ========== */
.empty-state {
  text-align: center;
  padding: 80px 20px;
  color: var(--slate-600);
}
.empty-state i {
  font-size: 80px;
  color: #ddd;
  margin-bottom: 30px;
}
.empty-state h3 {
  font-size: 24px;
  font-weight: 800;
  color: var(--slate-900);
  margin-bottom: 15px;
}
.empty-state p {
  font-size: 16px;
  margin-bottom: 30px;
}
.empty-state .btn-primary {
  display: inline-flex;
  padding: 16px 32px;
}

/* ========== RESPONSIVE ========== */
@media (max-width: 768px) {
  .hero-header { padding: 70px 15px 20px; }
  .hero-header h1 { font-size: 28px; }

  .search-wrapper { margin-top: 10px; padding: 0; }

  .search-pill {
    padding: 12px 20px;
    border-radius: 50px;
    margin: 0 16px;
  }

  .search-pill i { font-size: 16px; margin-right: 12px; }
  .search-pill span { font-size: 14px; font-weight: 500; }

  .filter-bar { padding: 10px 0; }
  .filter-bar .container { padding: 0 16px; }
  .tabs { gap: 8px; }
  .tab { padding: 8px 16px; font-size: 12px; border-radius: 12px; }

  .main-container { padding: 20px 16px; }
  .products-grid { grid-template-columns: 1fr; }

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

  .price-amt { font-size: 32px; }
  .quantity-badge { margin-top: 0; }
  .btn-cart { width: 100%; padding: 16px; justify-content: center; }

  .how-it-works h2 { font-size: 32px; }
  .steps-grid { gap: 30px; }

  .search-modal-overlay { padding: 0; }

  .search-modal-content {
    margin: 0;
    max-width: 100%;
    max-height: 100vh;
    height: 100vh;
    border-radius: 0;
    display: flex;
    flex-direction: column;
  }

  .modal-header { padding: 16px 20px; border-radius: 0; }
  .modal-header h2 { font-size: 20px; }
  .modal-body { padding: 20px; flex: 1; overflow-y: auto; padding-bottom: 90px; }

  .modal-footer {
    padding: 12px 16px;
    position: fixed !important;
    bottom: 0; left: 0; right: 0;
    border-radius: 0;
    box-shadow: 0 -2px 15px rgba(0,0,0,0.1);
    z-index: 20;
    background: white;
  }

  .btn-secondary, .btn-primary {
    padding: 12px 16px;
    font-size: 13px;
    border-radius: 10px;
  }

  .location-grid { grid-template-columns: 1fr; }
  .budget-grid { grid-template-columns: 1fr; }

  .category-pills { flex-wrap: wrap; }
  .category-pill { flex: 0 0 calc(50% - 5px); justify-content: center; }
}

@media (max-width: 400px) {
  .price-amt { font-size: 28px; }
  .quantity-badge { font-size: 11px; padding: 5px 12px; }
}

/* ========== CART MODAL ========== */
.cart-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.6);
  z-index: 3000;
  display: none;
  backdrop-filter: blur(8px);
  align-items: center;
  justify-content: center;
  padding: 20px;
}
.cart-modal-overlay.active {
  display: flex;
}

.cart-modal {
  background: white;
  border-radius: 24px;
  width: 100%;
  max-width: 550px;
  max-height: 90vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  animation: modalSlideIn 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.cart-modal-header {
  padding: 20px 24px;
  border-bottom: 1px solid #eee;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: white;
}
.cart-modal-header h2 {
  font-size: 20px;
  font-weight: 800;
  color: var(--slate-900);
  display: flex;
  align-items: center;
  gap: 10px;
}
.cart-modal-header h2 i {
  color: var(--primary);
}
.cart-count-badge {
  background: var(--primary);
  color: white;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 700;
}

.modal-close-btn {
  background: var(--zinc-100);
  border: none;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  cursor: pointer;
  color: var(--slate-600);
  transition: 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}
.modal-close-btn:hover {
  background: #e2e8f0;
}

.cart-modal-body {
  flex: 1;
  overflow-y: auto;
  padding: 20px 24px;
}

.cart-empty {
  text-align: center;
  padding: 40px 20px;
}
.cart-empty i {
  font-size: 60px;
  color: #ddd;
  margin-bottom: 20px;
}
.cart-empty h3 {
  font-size: 18px;
  font-weight: 700;
  color: var(--slate-900);
  margin-bottom: 10px;
}
.cart-empty p {
  color: var(--slate-600);
  margin-bottom: 20px;
}
.btn-continue-shopping {
  background: var(--primary);
  color: white;
  border: none;
  padding: 12px 24px;
  border-radius: 12px;
  font-weight: 700;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.cart-items-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.cart-item {
  display: flex;
  gap: 16px;
  padding: 16px;
  background: #f8fafc;
  border-radius: 16px;
  align-items: center;
}
.cart-item-image {
  width: 70px;
  height: 70px;
  border-radius: 12px;
  overflow: hidden;
  flex-shrink: 0;
}
.cart-item-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.cart-item-details {
  flex: 1;
  min-width: 0;
}
.cart-item-name {
  font-size: 14px;
  font-weight: 700;
  color: var(--slate-900);
  margin-bottom: 4px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.cart-item-seller {
  font-size: 12px;
  color: var(--slate-600);
  margin-bottom: 4px;
}
.cart-item-price {
  font-size: 14px;
  font-weight: 800;
  color: var(--primary);
}

.cart-item-actions {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 8px;
}
.quantity-controls {
  display: flex;
  align-items: center;
  gap: 8px;
  background: white;
  border-radius: 10px;
  padding: 4px;
  border: 1px solid #e2e8f0;
}
.qty-btn {
  width: 28px;
  height: 28px;
  border: none;
  background: transparent;
  border-radius: 8px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--slate-600);
  transition: 0.2s;
}
.qty-btn:hover {
  background: var(--primary-soft);
  color: var(--primary);
}
.qty-input {
  width: 40px;
  text-align: center;
  border: none;
  font-weight: 700;
  font-size: 14px;
  background: transparent;
}
.qty-input::-webkit-outer-spin-button,
.qty-input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}

.btn-remove-item {
  background: transparent;
  border: none;
  color: var(--danger);
  cursor: pointer;
  padding: 6px;
  border-radius: 8px;
  transition: 0.2s;
}
.btn-remove-item:hover {
  background: #fef2f2;
}

.cart-modal-footer {
  padding: 20px 24px;
  border-top: 1px solid #eee;
  background: white;
}
.cart-summary {
  margin-bottom: 16px;
}
.summary-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 0;
  font-size: 14px;
  color: var(--slate-600);
}
.summary-row.total {
  border-top: 2px solid var(--primary);
  padding-top: 12px;
  margin-top: 8px;
}
.total-amount {
  font-size: 20px;
  color: var(--primary);
}

.cart-actions {
  display: flex;
  gap: 12px;
}
.btn-clear-cart {
  flex: 1;
  padding: 14px;
  border-radius: 12px;
  border: 2px solid #e2e8f0;
  background: white;
  font-weight: 700;
  cursor: pointer;
  color: var(--slate-600);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: 0.2s;
}
.btn-clear-cart:hover {
  border-color: var(--danger);
  color: var(--danger);
}
.btn-checkout {
  flex: 2;
  padding: 14px;
  border-radius: 12px;
  border: none;
  background: var(--slate-900);
  color: white;
  font-weight: 800;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: 0.3s;
}
.btn-checkout:hover {
  background: var(--primary);
}

@media (max-width: 768px) {
  .cart-modal {
    max-width: 100%;
    max-height: 100vh;
    height: 100vh;
    border-radius: 0;
  }
  .cart-item {
    flex-wrap: wrap;
  }
  .cart-item-actions {
    width: 100%;
    flex-direction: row;
    justify-content: space-between;
    margin-top: 10px;
  }
}
</style>
</head>
<body>

<?php include 'header2.php'; ?>

<header class="hero-header">
  <div class="container">
    <h1><span class="accent">¿QUIÉN QUIERE?</span></h1>
    <p class="hero-subtitle">Productos exclusivos que viajeros traen de todo el mundo</p>

    <div class="search-wrapper" id="search-wrapper">
      <div class="search-pill" onclick="openSearchModal()">
        <i class="fas fa-search"></i>
        <span>Buscar productos, viajeros, ciudades...</span>
      </div>
    </div>
  </div>
</header>

<div class="filter-bar">
  <div class="container">
    <div class="tabs">
      <button class="tab active" onclick="changeTab('recent', this)">RECIENTES</button>
      <button class="tab" onclick="changeTab('popular', this)">POPULARES</button>
      <button class="tab" onclick="changeTab('best_price', this)">MEJOR PRECIO</button>
    </div>
  </div>
</div>

<main class="main-container">
  <div class="products-grid" id="products-grid"></div>
  <div id="loading" style="text-align:center; padding:50px;">
    <i class="fas fa-circle-notch fa-spin fa-2x" style="color: var(--primary);"></i>
  </div>

  <div class="empty-state" id="empty-state" style="display:none;">
    <i class="fas fa-store"></i>
    <h3>No hay ofertas disponibles</h3>
    <p>Sé el primero en ofrecer productos de tus viajes</p>
    <?php if ($user_logged_in): ?>
      <a href="shop-manage-products.php" class="btn-primary">
        <i class="fas fa-plus-circle"></i> ¿Quién quiere…?
      </a>
    <?php else: ?>
      <a href="../login.php" class="btn-primary">
        <i class="fas fa-sign-in-alt"></i> Iniciar sesión para ofrecer
      </a>
    <?php endif; ?>
  </div>
</main>

<!-- Cómo funciona -->
<section class="how-it-works">
  <div class="container">
    <h2>Cómo funciona ¿Quién Quiere?</h2>
    <div class="steps-grid">
      <div class="step-card">
        <div class="step-number">01</div>
        <h3 class="step-title">Explora productos</h3>
        <p class="step-description">Descubre productos únicos que viajeros traen de diferentes países. Filtra por origen, categoría o precio.</p>
      </div>
      <div class="step-card">
        <div class="step-number">02</div>
        <h3 class="step-title">Añade al carrito</h3>
        <p class="step-description">Selecciona los productos que te interesan. Puedes comprar de varios viajeros en una sola sesión.</p>
      </div>
      <div class="step-card">
        <div class="step-number">03</div>
        <h3 class="step-title">Recibe en tu ciudad</h3>
        <p class="step-description">Coordina con el viajero la entrega. Pago seguro con código QR de confirmación.</p>
      </div>
    </div>
  </div>
</section>

<!-- MODAL DE BÚSQUEDA -->
<div class="search-modal-overlay" id="searchModal" onclick="closeSearchModal(event)">
  <div class="search-modal-content" onclick="event.stopPropagation()">

    <div class="modal-header">
      <h2>Buscar Ofertas</h2>
      <button class="modal-close" onclick="closeSearchModal()">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <div class="modal-body">

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

      <div class="search-section">
        <div class="search-section-title">
          <i class="fas fa-route"></i>
          Rutas
        </div>
        <div class="location-grid">
          <div class="input-wrapper">
            <i class="fas fa-plane-departure input-icon"></i>
            <input type="text" class="input-field" id="originInput" placeholder="Viene de (país)" autocomplete="off">
          </div>
          <div class="input-wrapper">
            <i class="fas fa-plane-arrival input-icon"></i>
            <input type="text" class="input-field" id="destinationInput" placeholder="Entrega en (ciudad)" autocomplete="off">
          </div>
        </div>
      </div>

      <div class="search-section">
        <div class="search-section-title">
          <i class="fas fa-tags"></i>
          Categorías
        </div>
        <div class="category-pills">
          <button class="category-pill" data-category="food" onclick="toggleCategoryPill(this)">🍕 Comida</button>
          <button class="category-pill" data-category="fashion" onclick="toggleCategoryPill(this)">👗 Moda</button>
          <button class="category-pill" data-category="electronics" onclick="toggleCategoryPill(this)">💻 Tecnología</button>
          <button class="category-pill" data-category="crafts" onclick="toggleCategoryPill(this)">🎨 Artesanía</button>
          <button class="category-pill" data-category="cosmetics" onclick="toggleCategoryPill(this)">💄 Cosméticos</button>
        </div>
      </div>

      <div class="search-section">
        <div class="search-section-title">
          <i class="fas fa-coins"></i>
          Moneda
        </div>
        <div class="currency-selector">
          <button class="currency-btn active" data-currency="EUR" onclick="selectCurrency(this)">EUR (€)</button>
          <button class="currency-btn" data-currency="USD" onclick="selectCurrency(this)">USD ($)</button>
          <button class="currency-btn" data-currency="BOB" onclick="selectCurrency(this)">BOB (Bs)</button>
        </div>
      </div>

      <div class="search-section">
        <div class="search-section-title">
          <i class="fas fa-wallet"></i>
          Rango de precio
        </div>
        <div class="budget-grid">
          <div class="input-wrapper">
            <i class="fas fa-arrow-down input-icon"></i>
            <input type="number" class="input-field" id="minPrice" placeholder="Mínimo" min="0">
          </div>
          <div class="input-wrapper">
            <i class="fas fa-arrow-up input-icon"></i>
            <input type="number" class="input-field" id="maxPrice" placeholder="Máximo" min="0">
          </div>
        </div>
      </div>

    </div>

    <div class="modal-footer">
      <button class="btn-secondary" onclick="clearAllFilters()">Limpiar filtros</button>
      <button class="btn-primary" onclick="executeSearch()">
        <i class="fas fa-search"></i>
        Buscar
      </button>
    </div>

  </div>
</div>

<!-- MODAL DEL CARRITO -->
<div class="cart-modal-overlay" id="cart-modal-overlay" onclick="if(event.target === this) closeCart()"></div>

<script>
let products = [];
let currentFilter = 'recent';
let selectedCats = new Set();
let currentCurr = 'EUR';
const userFavorites = new Set();

// Cargar productos desde la API
async function cargarProductos() {
  try {
    const res = await fetch('shop-actions.php?action=get_products');
    const data = await res.json();
    if (data.success) {
      products = data.products || [];
      // Convertir IDs a números
      products.forEach(p => {
        p.id = parseInt(p.id);
        if (p.is_favorited) userFavorites.add(p.id);
      });

      // Exponer products como allProducts para cart.js
      window.allProducts = products;

      // Cargar favoritos del usuario
      <?php if ($user_logged_in): ?>
      await cargarFavoritos();
      <?php endif; ?>

      aplicarFiltros();
    } else {
      console.error('Error:', data.error);
    }
  } catch (e) {
    console.error(e);
  }
  document.getElementById('loading').style.display = 'none';
}

<?php if ($user_logged_in): ?>
async function cargarFavoritos() {
  try {
    console.log('Cargando favoritos...');
    const res = await fetch('shop-products-api.php?action=get_favorites');
    const data = await res.json();
    console.log('Respuesta favoritos:', data);
    if (data.success && data.products) {
      data.products.forEach(p => {
        const id = parseInt(p.id);
        userFavorites.add(id);
        console.log('Favorito añadido:', id);
      });
      console.log('Total favoritos cargados:', userFavorites.size);
    } else if (data.error) {
      console.error('Error API favoritos:', data.error);
    }
  } catch (e) {
    console.error('Error cargando favoritos:', e);
  }
}
<?php endif; ?>

// Modal de búsqueda
function openSearchModal() {
  document.getElementById('searchModal').classList.add('show');
  document.body.style.overflow = 'hidden';
  setTimeout(() => document.getElementById('searchQuery').focus(), 100);
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
  document.getElementById('searchQuery').value = '';
  document.getElementById('originInput').value = '';
  document.getElementById('destinationInput').value = '';
  document.getElementById('minPrice').value = '';
  document.getElementById('maxPrice').value = '';

  document.querySelectorAll('.category-pill').forEach(btn => btn.classList.remove('active'));
  selectedCats.clear();

  currentCurr = 'EUR';
  document.querySelectorAll('.currency-btn').forEach(btn => {
    btn.classList.remove('active');
    if (btn.dataset.currency === 'EUR') btn.classList.add('active');
  });
}

function executeSearch() {
  aplicarFiltros();
  closeSearchModal();

  const grid = document.getElementById('products-grid');
  if (grid) {
    setTimeout(() => grid.scrollIntoView({ behavior: 'smooth', block: 'start' }), 300);
  }
}

function aplicarFiltros() {
  const searchQuery = document.getElementById('searchQuery')?.value.toLowerCase() || '';
  const ori = document.getElementById('originInput')?.value.toLowerCase() || '';
  const des = document.getElementById('destinationInput')?.value.toLowerCase() || '';
  const min = parseFloat(document.getElementById('minPrice')?.value) || 0;
  const max = parseFloat(document.getElementById('maxPrice')?.value) || 999999;

  let list = products.filter(p => {
    const mSearch = !searchQuery ||
      p.name?.toLowerCase().includes(searchQuery) ||
      p.description?.toLowerCase().includes(searchQuery);

    const mOri = !ori || p.origin_country?.toLowerCase().includes(ori);
    const mDes = !des || p.destination_city?.toLowerCase().includes(des);
    const mCat = selectedCats.size === 0 || selectedCats.has(p.category);
    const mCur = p.currency === currentCurr;
    const mPri = p.price >= min && p.price <= max;

    return mSearch && mOri && mDes && mCat && mCur && mPri;
  });

  // Ordenar según filtro
  if (currentFilter === 'popular') {
    list.sort((a,b) => (b.sales_count || 0) - (a.sales_count || 0));
  } else if (currentFilter === 'best_price') {
    list.sort((a,b) => a.price - b.price);
  } else {
    list.sort((a,b) => new Date(b.created_at) - new Date(a.created_at));
  }

  const grid = document.getElementById('products-grid');
  const emptyState = document.getElementById('empty-state');

  if (list.length === 0) {
    grid.innerHTML = '';
    emptyState.style.display = 'block';
    return;
  }

  emptyState.style.display = 'none';

  grid.innerHTML = list.map(p => {
    const rating = parseFloat(p.seller_rating) || 0;
    const isFav = userFavorites.has(p.id);
    const avatar = p.seller_avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(p.seller_name || 'Usuario')}&background=42ba25&color=fff`;
    const description = p.description || 'Producto exclusivo de viajero';
    const stock = parseInt(p.stock) || 0;
    const origin = p.origin || p.origin_country || 'Internacional';
    const destination = p.destination || p.destination_city || 'A coordinar';
    const totalRatings = parseInt(p.total_ratings) || 0;

    let stockTag = '';
    if (stock === 0) {
      stockTag = '<div class="stock-tag out">Agotado</div>';
    } else if (stock <= 3) {
      stockTag = `<div class="stock-tag low">Últimas ${stock}</div>`;
    } else {
      stockTag = `<div class="stock-tag">${stock} disponibles</div>`;
    }

    return `
      <div class="product-card">
        <div class="card-img-zone" onclick="verDetalle(${p.id})">
          <img src="${p.primary_image || 'https://via.placeholder.com/400x250?text=Producto'}" alt="${p.name}">
          ${stockTag}
          <button class="favorite-btn ${isFav ? 'active' : ''}" onclick="event.stopPropagation(); toggleFav(this, ${p.id})">
            <i class="${isFav ? 'fas' : 'far'} fa-heart"></i>
          </button>
        </div>
        <div class="card-body" onclick="verDetalle(${p.id})">
          <div class="offer-question">¿QUIÉN QUIERE?</div>
          <h3 class="product-title">${p.name}</h3>

          <p class="product-description">${description}</p>

          <div class="route-box">
            <div>
              <div class="route-label">Viene de</div>
              <div class="route-val">${origin}</div>
            </div>
            <div style="border-left:1px solid #eee; padding-left:10px;">
              <div class="route-label">Entrega en</div>
              <div class="route-val">${destination}</div>
            </div>
          </div>

          <div class="user-info">
            <img src="${avatar}" class="avatar-img" alt="${p.seller_name || 'Vendedor'}">
            <div>
              <div style="font-weight:600; font-size:13px;">@${p.seller_username || p.seller_name || 'vendedor'}</div>
              <div style="font-size:11px; color:#f59e0b;">★ ${rating.toFixed(1)} ${totalRatings > 0 ? `(${totalRatings})` : ''}</div>
            </div>
            ${p.seller_verified ? '<div class="verified-badge"><i class="fas fa-check-circle"></i></div>' : ''}
          </div>

          <div class="price-action">
            <div class="price-section">
              <div class="price-label">Precio</div>
              <div class="price-container">
                <div class="price-amt">${Math.floor(p.price)}<span>${p.currency}</span></div>
              </div>
              ${stock > 1 ? `<div class="quantity-badge">${stock} disponibles</div>` : ''}
            </div>
            <button class="btn-cart" ${stock === 0 ? 'disabled' : ''} onclick="event.stopPropagation(); addToCart(${p.id})">
              <i class="fas fa-cart-plus"></i>
              ${stock === 0 ? 'Agotado' : 'Añadir'}
            </button>
          </div>

        </div>
      </div>
    `;
  }).join('');
}

function verDetalle(id) {
  window.location.href = `shop-product-detail.php?id=${id}`;
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

  id = parseInt(id);
  const icon = btn.querySelector('i');
  const wasActive = btn.classList.contains('active');

  try {
    const res = await fetch('shop-products-api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `action=${wasActive ? 'remove_favorite' : 'add_favorite'}&product_id=${id}`
    });
    const data = await res.json();

    console.log('Favorito response:', data);

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
    } else {
      console.error('Error favorito:', data.error);
    }
  } catch (e) {
    console.error('Error al guardar favorito:', e);
  }
}

function addToCart(id) {
  <?php if (!$user_logged_in): ?>
  Swal.fire({ icon: 'warning', title: 'Inicia sesión', text: 'Debes iniciar sesión para comprar' });
  return;
  <?php endif; ?>

  id = parseInt(id);
  const product = products.find(p => parseInt(p.id) === id);
  if (!product) {
    console.error('Producto no encontrado:', id);
    return;
  }

  let cart = JSON.parse(localStorage.getItem('sendvialo_cart') || '[]');
  const existingIndex = cart.findIndex(item => parseInt(item.product_id) === id);

  if (existingIndex >= 0) {
    if (cart[existingIndex].quantity < product.stock) {
      cart[existingIndex].quantity += 1;
    } else {
      Swal.fire({
        icon: 'warning',
        title: 'Stock limitado',
        text: `Solo hay ${product.stock} unidades disponibles`,
        confirmButtonColor: '#41ba0d'
      });
      return;
    }
  } else {
    cart.push({
      product_id: id,
      name: product.name,
      price: product.price,
      currency: product.currency,
      image: product.primary_image,
      seller_id: product.seller_id,
      seller_name: product.seller_name,
      quantity: 1,
      stock: product.stock
    });
  }

  localStorage.setItem('sendvialo_cart', JSON.stringify(cart));

  // Actualizar badge del carrito
  updateCartBadge();

  Swal.fire({
    toast: true,
    position: 'top-end',
    icon: 'success',
    title: 'Añadido al carrito',
    showConfirmButton: false,
    timer: 1500
  });
}

function updateCartBadge() {
  const cart = JSON.parse(localStorage.getItem('sendvialo_cart') || '[]');
  const count = cart.reduce((n, i) => n + i.quantity, 0);

  const badges = document.querySelectorAll('#header-cart-count, #mobile-cart-count');
  badges.forEach(badge => {
    if (badge) {
      badge.textContent = count;
      badge.style.display = count > 0 ? 'flex' : 'none';
    }
  });
}

// Cerrar modal con ESC
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    closeSearchModal();
    closeCart();
  }
});

document.addEventListener('DOMContentLoaded', () => {
  cargarProductos();
  updateCartBadge();
});

// ========================================
// FUNCIONES DEL CARRITO
// ========================================

function openCart() {
  renderCartModal();
  const overlay = document.getElementById('cart-modal-overlay');
  if (overlay) {
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
  }
}

function closeCart() {
  const overlay = document.getElementById('cart-modal-overlay');
  if (overlay) {
    overlay.classList.remove('active');
    document.body.style.overflow = '';
  }
}

function getCart() {
  return JSON.parse(localStorage.getItem('sendvialo_cart') || '[]');
}

function saveCartData(cart) {
  localStorage.setItem('sendvialo_cart', JSON.stringify(cart));
  updateCartBadge();
}

function formatCartPrice(amount, currency) {
  const symbols = {
    'EUR': '€', 'USD': '$', 'BOB': 'Bs.', 'BRL': 'R$',
    'ARS': '$', 'VES': 'Bs.', 'COP': '$', 'MXN': '$',
    'NIO': 'C$', 'CUP': '$MN', 'PEN': 'S/'
  };
  const symbol = symbols[currency] || currency;
  return `${symbol}${parseFloat(amount).toFixed(2)}`;
}

function renderCartModal() {
  const cart = getCart();
  const overlay = document.getElementById('cart-modal-overlay');

  if (cart.length === 0) {
    overlay.innerHTML = `
      <div class="cart-modal" onclick="event.stopPropagation()">
        <div class="cart-modal-header">
          <h2><i class="fas fa-shopping-cart"></i> Tu Carrito</h2>
          <button class="modal-close-btn" onclick="closeCart()">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="cart-modal-body">
          <div class="cart-empty">
            <i class="fas fa-shopping-cart"></i>
            <h3>Tu carrito está vacío</h3>
            <p>Añade productos para empezar a comprar</p>
            <button class="btn-continue-shopping" onclick="closeCart()">
              <i class="fas fa-arrow-left"></i> Seguir comprando
            </button>
          </div>
        </div>
      </div>
    `;
    return;
  }

  const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
  const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
  const currency = cart[0]?.currency || 'EUR';

  overlay.innerHTML = `
    <div class="cart-modal" onclick="event.stopPropagation()">
      <div class="cart-modal-header">
        <h2>
          <i class="fas fa-shopping-cart"></i> Tu Carrito
          <span class="cart-count-badge">${totalItems}</span>
        </h2>
        <button class="modal-close-btn" onclick="closeCart()">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="cart-modal-body">
        <div class="cart-items-list">
          ${cart.map((item, index) => `
            <div class="cart-item">
              <div class="cart-item-image">
                <img src="${item.image || 'https://via.placeholder.com/100'}" alt="${item.name}">
              </div>
              <div class="cart-item-details">
                <h4 class="cart-item-name">${item.name}</h4>
                <p class="cart-item-seller"><i class="fas fa-user"></i> ${item.seller_name || 'Vendedor'}</p>
                <p class="cart-item-price">${formatCartPrice(item.price * item.quantity, item.currency)}</p>
              </div>
              <div class="cart-item-actions">
                <div class="quantity-controls">
                  <button class="qty-btn" onclick="updateCartQuantity(${index}, ${item.quantity - 1})">
                    <i class="fas fa-minus"></i>
                  </button>
                  <input type="number" class="qty-input" value="${item.quantity}" min="1" max="${item.stock || 99}"
                         onchange="updateCartQuantity(${index}, parseInt(this.value))">
                  <button class="qty-btn" onclick="updateCartQuantity(${index}, ${item.quantity + 1})">
                    <i class="fas fa-plus"></i>
                  </button>
                </div>
                <button class="btn-remove-item" onclick="removeFromCartByIndex(${index})">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
            </div>
          `).join('')}
        </div>
      </div>

      <div class="cart-modal-footer">
        <div class="cart-summary">
          <div class="summary-row">
            <span>Subtotal (${totalItems} productos):</span>
            <strong>${formatCartPrice(subtotal, currency)}</strong>
          </div>
          <div class="summary-row total">
            <span>Total:</span>
            <strong class="total-amount">${formatCartPrice(subtotal, currency)}</strong>
          </div>
        </div>
        <div class="cart-actions">
          <button class="btn-clear-cart" onclick="clearEntireCart()">
            <i class="fas fa-trash-alt"></i> Vaciar
          </button>
          <button class="btn-checkout" onclick="proceedToCheckout()">
            <i class="fas fa-credit-card"></i> Proceder al pago
          </button>
        </div>
      </div>
    </div>
  `;
}

function updateCartQuantity(index, newQuantity) {
  const cart = getCart();
  if (index < 0 || index >= cart.length) return;

  if (newQuantity <= 0) {
    removeFromCartByIndex(index);
    return;
  }

  const maxStock = cart[index].stock || 99;
  if (newQuantity > maxStock) {
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'warning',
      title: `Solo hay ${maxStock} unidades disponibles`,
      timer: 2000,
      showConfirmButton: false
    });
    return;
  }

  cart[index].quantity = newQuantity;
  saveCartData(cart);
  renderCartModal();
}

function removeFromCartByIndex(index) {
  Swal.fire({
    title: '¿Eliminar producto?',
    text: "Se quitará del carrito",
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#41ba0d',
    cancelButtonColor: '#ef4444',
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      const cart = getCart();
      cart.splice(index, 1);
      saveCartData(cart);
      renderCartModal();

      Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: 'Producto eliminado',
        timer: 1500,
        showConfirmButton: false
      });
    }
  });
}

function clearEntireCart() {
  const cart = getCart();
  if (cart.length === 0) return;

  Swal.fire({
    title: '¿Vaciar carrito?',
    text: "Se eliminarán todos los productos",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#ef4444',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Sí, vaciar',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      localStorage.removeItem('sendvialo_cart');
      updateCartBadge();
      renderCartModal();

      Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: 'Carrito vaciado',
        timer: 1500,
        showConfirmButton: false
      });
    }
  });
}

function proceedToCheckout() {
  const cart = getCart();
  if (cart.length === 0) {
    Swal.fire({
      icon: 'warning',
      title: 'Carrito vacío',
      text: 'Añade productos antes de proceder al pago',
      confirmButtonColor: '#41ba0d'
    });
    return;
  }

  const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
  const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
  const currency = cart[0]?.currency || 'EUR';

  Swal.fire({
    title: '<i class="fas fa-receipt"></i> Resumen de compra',
    html: `
      <div style="text-align: left; padding: 1rem;">
        <h4 style="margin-bottom: 1rem; color: #374151;">Productos (${totalItems}):</h4>
        ${cart.map(item => `
          <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid #e5e7eb;">
            <span>${item.name} x${item.quantity}</span>
            <strong>${formatCartPrice(item.price * item.quantity, item.currency)}</strong>
          </div>
        `).join('')}
        <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 2px solid #41ba0d;">
          <div style="display: flex; justify-content: space-between; font-size: 1.2rem;">
            <strong>Total:</strong>
            <strong style="color: #41ba0d;">${formatCartPrice(subtotal, currency)}</strong>
          </div>
        </div>
        <div style="margin-top: 1.5rem; padding: 1rem; background: #f9fafb; border-radius: 8px;">
          <p style="margin: 0; color: #6b7280; font-size: 0.9rem;">
            <i class="fas fa-info-circle"></i>
            El pago se procesará de forma segura
          </p>
        </div>
      </div>
    `,
    width: '600px',
    showCancelButton: true,
    confirmButtonColor: '#41ba0d',
    cancelButtonColor: '#6b7280',
    confirmButtonText: '<i class="fas fa-check"></i> Continuar al pago',
    cancelButtonText: '<i class="fas fa-arrow-left"></i> Volver'
  }).then((result) => {
    if (result.isConfirmed) {
      submitToCheckout();
    }
  });
}

function submitToCheckout() {
  const cart = getCart();
  if (cart.length === 0) return;

  // Mostrar loading
  Swal.fire({
    title: 'Procesando...',
    html: 'Preparando tu pedido',
    allowOutsideClick: false,
    allowEscapeKey: false,
    didOpen: () => {
      Swal.showLoading();
    }
  });

  // Crear formulario oculto para enviar a shop-checkout.php
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = 'shop-checkout.php';
  form.style.display = 'none';

  const input = document.createElement('input');
  input.type = 'hidden';
  input.name = 'cart';
  input.value = JSON.stringify(cart);
  form.appendChild(input);

  document.body.appendChild(form);
  form.submit();
}
</script>

</body>
</html>
