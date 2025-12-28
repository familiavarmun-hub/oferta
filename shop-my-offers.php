<?php
/**
 * shop-my-offers.php - Mis Ofertas (productos que estoy vendiendo)
 * Sección OFERTA - ¿Quién quiere?
 */
session_start();
require_once 'insignias1.php';
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}
$user_id = $_SESSION['usuario_id'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
  <title>Mis Ofertas | SendVialo Shop</title>
  <link rel="stylesheet" href="../css/estilos.css"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.3.0/css/all.min.css"/>
  <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

  <style>
    :root {
      --primary: #42ba25;
      --primary-dark: #37a01f;
      --primary-soft: #eef9e7;
      --danger: #ef4444;
      --warning: #f59e0b;
      --slate-900: #0f172a;
      --slate-600: #475569;
      --slate-400: #94a3b8;
      --zinc-100: #f1f5f9;
    }

    body {
      font-family: 'Inter', sans-serif;
      background-color: #f8fafc;
      margin: 0;
      -webkit-font-smoothing: antialiased;
    }

    .container {
      max-width: 1400px;
      margin: 0 auto;
      padding: 80px 24px 40px;
    }

    /* HEADER SECCIÓN */
    .header-section {
      margin-bottom: 48px;
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      position: relative;
    }

    .header-section::after {
      content: '';
      position: absolute;
      bottom: -15px;
      left: 0;
      width: 60px;
      height: 4px;
      background: var(--primary);
      border-radius: 10px;
    }

    .header-section h1 {
      font-size: 42px;
      font-weight: 900;
      color: var(--slate-900);
      margin: 0;
      letter-spacing: -1.5px;
    }

    .header-section p {
      font-size: 16px;
      color: var(--slate-600);
      margin-top: 12px;
      font-weight: 500;
      max-width: 650px;
    }

    .header-actions {
      display: flex;
      gap: 15px;
      margin-top: 25px;
    }

    .btn-create {
      background: var(--primary);
      color: white;
      padding: 14px 28px;
      border-radius: 14px;
      font-weight: 800;
      font-size: 14px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 10px;
      transition: all 0.2s ease;
    }
    .btn-create:hover {
      background: var(--primary-dark);
      transform: translateY(-2px);
      box-shadow: 0 8px 15px rgba(66, 186, 37, 0.3);
    }

    /* TABS */
    .tabs-wrapper {
      background: white;
      padding: 8px;
      border-radius: 20px;
      display: inline-flex;
      gap: 5px;
      box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
      margin-bottom: 40px;
      border: 1px solid #e2e8f0;
      overflow-x: auto;
      max-width: 100%;
    }

    .tab {
      padding: 10px 20px;
      border: none;
      background: transparent;
      border-radius: 14px;
      font-weight: 700;
      font-size: 13px;
      color: var(--slate-600);
      cursor: pointer;
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      gap: 10px;
      white-space: nowrap;
    }

    .tab.active {
      background: var(--primary);
      color: white;
    }

    .tab-badge {
      font-size: 10px;
      background: #f1f5f9;
      color: var(--slate-600);
      padding: 2px 8px;
      border-radius: 8px;
    }
    .tab.active .tab-badge { background: rgba(255,255,255,0.2); color: white; }

    /* GRID */
    .offers-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
      gap: 25px;
    }

    /* TARJETA */
    .offer-card {
      background: white;
      border-radius: 24px;
      border: 1px solid #e2e8f0;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      transition: all 0.3s ease;
      position: relative;
      width: 100%;
    }
    .offer-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 25px 50px -12px rgba(0,0,0,0.12);
      border-color: var(--primary);
    }

    .card-img-zone {
      position: relative;
      height: 180px;
      background: #f3f4f6;
      overflow: hidden;
      cursor: pointer;
    }
    .card-img-zone img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform 0.8s ease;
    }
    .offer-card:hover .card-img-zone img { transform: scale(1.08); }

    .status-badge {
      position: absolute;
      top: 15px;
      left: 15px;
      padding: 6px 14px;
      border-radius: 10px;
      font-size: 11px;
      font-weight: 800;
      text-transform: uppercase;
    }
    .status-badge.active { background: var(--primary); color: white; }
    .status-badge.paused { background: var(--warning); color: white; }
    .status-badge.sold { background: var(--slate-900); color: white; }

    .stock-badge {
      position: absolute;
      top: 15px;
      right: 15px;
      background: white;
      padding: 6px 12px;
      border-radius: 10px;
      font-size: 11px;
      font-weight: 700;
      color: var(--slate-600);
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    .card-body {
      padding: 24px;
      flex-grow: 1;
      display: flex;
      flex-direction: column;
    }

    .offer-question {
      font-size: 11px;
      font-weight: 800;
      color: var(--primary);
      margin-bottom: 8px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .product-title {
      font-size: 20px;
      font-weight: 800;
      margin-bottom: 10px;
      line-height: 1.3;
      color: var(--slate-900);
    }

    .product-description {
      font-size: 13px;
      line-height: 1.6;
      color: var(--slate-600);
      margin-bottom: 15px;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .route-box {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
      background: #f8fafc;
      padding: 15px;
      border-radius: 16px;
      margin-bottom: 20px;
      border: 1px solid #f1f5f9;
    }
    .route-label {
      font-size: 9px;
      font-weight: 800;
      color: var(--primary);
      text-transform: uppercase;
      margin-bottom: 4px;
    }
    .route-val {
      font-size: 13px;
      font-weight: 700;
      color: var(--slate-900);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    /* STATS */
    .stats-row {
      display: flex;
      gap: 20px;
      margin-bottom: 20px;
      padding: 15px;
      background: var(--primary-soft);
      border-radius: 14px;
    }
    .stat-item {
      display: flex;
      flex-direction: column;
      align-items: center;
      flex: 1;
    }
    .stat-value {
      font-size: 24px;
      font-weight: 900;
      color: var(--primary);
    }
    .stat-label {
      font-size: 10px;
      font-weight: 700;
      color: var(--slate-600);
      text-transform: uppercase;
    }

    /* PRICE */
    .price-section {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: auto;
      padding-top: 15px;
      border-top: 1px solid #f1f5f9;
    }
    .price-label {
      font-size: 10px;
      font-weight: 800;
      color: var(--slate-400);
      text-transform: uppercase;
    }
    .price-amt {
      font-size: 32px;
      font-weight: 900;
      color: var(--slate-900);
    }
    .price-amt span {
      font-size: 14px;
      color: var(--primary);
    }

    .card-actions {
      display: flex;
      gap: 10px;
    }
    .btn-action {
      padding: 12px 20px;
      border-radius: 12px;
      font-weight: 700;
      font-size: 13px;
      cursor: pointer;
      transition: all 0.2s ease;
      border: none;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .btn-edit {
      background: var(--zinc-100);
      color: var(--slate-600);
    }
    .btn-edit:hover {
      background: #e2e8f0;
    }
    .btn-view {
      background: var(--primary);
      color: white;
    }
    .btn-view:hover {
      background: var(--primary-dark);
    }

    /* EMPTY STATE */
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

    /* LOADING */
    #loading {
      text-align: center;
      padding: 60px;
    }

    /* RESPONSIVE */
    @media (max-width: 768px) {
      .container { padding: 70px 16px 30px; }
      .header-section h1 { font-size: 32px; }
      .offers-grid { grid-template-columns: 1fr; }
      .tabs-wrapper { width: 100%; }
      .header-actions { flex-direction: column; }
      .btn-create { width: 100%; justify-content: center; }
    }
  </style>
</head>
<body>

<?php include 'header2.php'; ?>

<div class="container">
  <div class="header-section">
    <h1>Mis Ofertas</h1>
    <p>Gestiona los productos que estás ofreciendo como viajero. Revisa el estado, edita precios y responde a compradores interesados.</p>
    <div class="header-actions">
      <a href="shop-manage-products.php" class="btn-create">
        <i class="fas fa-plus-circle"></i>
        Nueva Oferta
      </a>
    </div>
  </div>

  <div class="tabs-wrapper">
    <button class="tab active" onclick="changeTab('all', this)">
      Todas <span class="tab-badge" id="count-all">0</span>
    </button>
    <button class="tab" onclick="changeTab('active', this)">
      Activas <span class="tab-badge" id="count-active">0</span>
    </button>
    <button class="tab" onclick="changeTab('paused', this)">
      Pausadas <span class="tab-badge" id="count-paused">0</span>
    </button>
    <button class="tab" onclick="changeTab('sold', this)">
      Vendidas <span class="tab-badge" id="count-sold">0</span>
    </button>
  </div>

  <div class="offers-grid" id="offers-grid"></div>

  <div id="loading">
    <i class="fas fa-circle-notch fa-spin fa-2x" style="color: var(--primary);"></i>
  </div>

  <div class="empty-state" id="empty-state" style="display:none;">
    <i class="fas fa-store"></i>
    <h3>No tienes ofertas</h3>
    <p>Comienza a ofrecer productos de tus viajes</p>
    <a href="shop-manage-products.php" class="btn-create" style="margin-top: 20px;">
      <i class="fas fa-plus-circle"></i> Crear mi primera oferta
    </a>
  </div>
</div>

<script>
let offers = [];
let currentFilter = 'all';

async function cargarOfertas() {
  try {
    const res = await fetch('shop-products-api.php?action=get_my_products&user_id=<?= $user_id ?>');
    const data = await res.json();
    if (data.success) {
      offers = data.products || [];
      updateCounts();
      renderOffers();
    }
  } catch (e) {
    console.error(e);
  }
  document.getElementById('loading').style.display = 'none';
}

function updateCounts() {
  document.getElementById('count-all').textContent = offers.length;
  document.getElementById('count-active').textContent = offers.filter(o => o.status === 'active').length;
  document.getElementById('count-paused').textContent = offers.filter(o => o.status === 'paused').length;
  document.getElementById('count-sold').textContent = offers.filter(o => o.status === 'sold' || parseInt(o.stock) === 0).length;
}

function changeTab(filter, btn) {
  document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
  currentFilter = filter;
  renderOffers();
}

function renderOffers() {
  let list = offers;

  if (currentFilter === 'active') {
    list = offers.filter(o => o.status === 'active' && parseInt(o.stock) > 0);
  } else if (currentFilter === 'paused') {
    list = offers.filter(o => o.status === 'paused');
  } else if (currentFilter === 'sold') {
    list = offers.filter(o => o.status === 'sold' || parseInt(o.stock) === 0);
  }

  const grid = document.getElementById('offers-grid');
  const emptyState = document.getElementById('empty-state');

  if (list.length === 0) {
    grid.innerHTML = '';
    emptyState.style.display = 'block';
    return;
  }

  emptyState.style.display = 'none';

  grid.innerHTML = list.map(o => {
    const stock = parseInt(o.stock) || 0;
    const sales = parseInt(o.sales_count) || 0;
    const views = parseInt(o.views_count) || 0;

    let statusClass = 'active';
    let statusText = 'Activa';
    if (o.status === 'paused') {
      statusClass = 'paused';
      statusText = 'Pausada';
    } else if (stock === 0 || o.status === 'sold') {
      statusClass = 'sold';
      statusText = 'Vendida';
    }

    return `
      <div class="offer-card">
        <div class="card-img-zone" onclick="verDetalle(${o.id})">
          <img src="${o.primary_image || 'https://via.placeholder.com/400x200?text=Producto'}" alt="${o.name}">
          <div class="status-badge ${statusClass}">${statusText}</div>
          <div class="stock-badge">${stock} en stock</div>
        </div>
        <div class="card-body">
          <div class="offer-question">¿QUIÉN QUIERE?</div>
          <h3 class="product-title">${o.name}</h3>
          <p class="product-description">${o.description || 'Sin descripción'}</p>

          <div class="route-box">
            <div>
              <div class="route-label">Viene de</div>
              <div class="route-val">${o.origin_country || 'Internacional'}</div>
            </div>
            <div style="border-left:1px solid #eee; padding-left:10px;">
              <div class="route-label">Entrega en</div>
              <div class="route-val">${o.destination_city || 'A coordinar'}</div>
            </div>
          </div>

          <div class="stats-row">
            <div class="stat-item">
              <div class="stat-value">${views}</div>
              <div class="stat-label">Vistas</div>
            </div>
            <div class="stat-item">
              <div class="stat-value">${sales}</div>
              <div class="stat-label">Ventas</div>
            </div>
            <div class="stat-item">
              <div class="stat-value">${stock}</div>
              <div class="stat-label">Stock</div>
            </div>
          </div>

          <div class="price-section">
            <div>
              <div class="price-label">Precio</div>
              <div class="price-amt">${Math.floor(o.price)}<span>${o.currency}</span></div>
            </div>
            <div class="card-actions">
              <button class="btn-action btn-edit" onclick="editarOferta(${o.id})">
                <i class="fas fa-edit"></i>
              </button>
              <button class="btn-action btn-view" onclick="verDetalle(${o.id})">
                <i class="fas fa-eye"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    `;
  }).join('');
}

function verDetalle(id) {
  window.location.href = `shop-product-detail.php?id=${id}`;
}

function editarOferta(id) {
  window.location.href = `shop-manage-products.php?edit=${id}`;
}

document.addEventListener('DOMContentLoaded', cargarOfertas);
</script>
</body>
</html>
