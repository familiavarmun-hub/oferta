<?php
/**
 * shop-favorites.php - Mis Favoritos (productos guardados)
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
  <title>Mis Favoritos | SendVialo Shop</title>
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

    /* HEADER */
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
      background: var(--danger);
      border-radius: 10px;
    }

    .header-section h1 {
      font-size: 42px;
      font-weight: 900;
      color: var(--slate-900);
      margin: 0;
      letter-spacing: -1.5px;
      display: flex;
      align-items: center;
      gap: 15px;
    }
    .header-section h1 i {
      color: var(--danger);
    }

    .header-section p {
      font-size: 16px;
      color: var(--slate-600);
      margin-top: 12px;
      font-weight: 500;
    }

    /* GRID */
    .favorites-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
      gap: 25px;
    }

    /* CARD */
    .product-card {
      background: white;
      border-radius: 24px;
      border: 1px solid #e2e8f0;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.4s ease;
      position: relative;
    }
    .product-card:hover {
      transform: translateY(-10px) scale(1.01);
      box-shadow: 0 30px 60px -15px rgba(0,0,0,0.15);
      border-color: var(--primary);
    }

    .card-img-zone {
      position: relative;
      height: 190px;
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
    .product-card:hover .card-img-zone img { transform: scale(1.1); }

    .favorite-btn {
      position: absolute;
      top: 15px;
      right: 15px;
      background: white;
      border: none;
      width: 42px;
      height: 42px;
      border-radius: 50%;
      color: var(--danger);
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 20;
      transition: all 0.3s ease;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
    .favorite-btn:hover {
      transform: scale(1.1);
      background: var(--danger);
      color: white;
    }
    .favorite-btn i { font-size: 20px; }

    .stock-tag {
      position: absolute;
      top: 15px;
      left: 15px;
      background: var(--primary);
      color: white;
      padding: 5px 12px;
      border-radius: 10px;
      font-size: 10px;
      font-weight: 900;
      text-transform: uppercase;
    }
    .stock-tag.low { background: var(--warning); }
    .stock-tag.out { background: var(--danger); }

    .card-body {
      padding: 24px;
      flex-grow: 1;
      cursor: pointer;
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
      font-size: 19px;
      font-weight: 800;
      margin-bottom: 12px;
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
      border-radius: 18px;
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

    .user-info {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 20px;
      border-top: 1px solid #f1f5f9;
      padding-top: 15px;
    }
    .avatar-img {
      width: 42px;
      height: 42px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid white;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

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
    }
    .price-amt {
      font-size: 40px;
      font-weight: 900;
      color: var(--slate-900);
      line-height: 1;
    }
    .price-amt span {
      font-size: 14px;
      color: var(--primary);
    }

    .btn-cart {
      background: var(--primary);
      color: white;
      border: none;
      padding: 16px 24px;
      border-radius: 14px;
      font-weight: 800;
      font-size: 14px;
      cursor: pointer;
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .btn-cart:hover {
      background: var(--primary-dark);
      transform: translateY(-2px);
    }
    .btn-cart:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    /* EMPTY */
    .empty-state {
      text-align: center;
      padding: 80px 20px;
    }
    .empty-state i {
      font-size: 80px;
      color: #fecaca;
      margin-bottom: 30px;
    }
    .empty-state h3 {
      font-size: 24px;
      font-weight: 800;
      color: var(--slate-900);
      margin-bottom: 15px;
    }
    .empty-state p {
      color: var(--slate-600);
      margin-bottom: 25px;
    }
    .empty-state a {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      background: var(--primary);
      color: white;
      padding: 14px 28px;
      border-radius: 14px;
      font-weight: 700;
      text-decoration: none;
    }

    #loading {
      text-align: center;
      padding: 60px;
    }

    @media (max-width: 768px) {
      .container { padding: 70px 16px 30px; }
      .header-section h1 { font-size: 32px; }
      .favorites-grid { grid-template-columns: 1fr; }
      .price-action { flex-direction: column; align-items: stretch; }
      .btn-cart { width: 100%; justify-content: center; }
    }
  </style>
</head>
<body>

<?php include 'header2.php'; ?>

<div class="container">
  <div class="header-section">
    <h1><i class="fas fa-heart"></i> Mis Favoritos</h1>
    <p>Productos que has guardado para comprar más tarde. Revisa disponibilidad y añádelos al carrito.</p>
  </div>

  <div class="favorites-grid" id="favorites-grid"></div>

  <div id="loading">
    <i class="fas fa-circle-notch fa-spin fa-2x" style="color: var(--primary);"></i>
  </div>

  <div class="empty-state" id="empty-state" style="display:none;">
    <i class="far fa-heart"></i>
    <h3>No tienes favoritos</h3>
    <p>Explora productos y guarda los que te interesen</p>
    <a href="index.php">
      <i class="fas fa-search"></i> Explorar productos
    </a>
  </div>
</div>

<script>
let favorites = [];

async function cargarFavoritos() {
  try {
    const res = await fetch('shop-products-api.php?action=get_favorites&user_id=<?= $user_id ?>');
    const data = await res.json();
    if (data.success) {
      favorites = data.products || [];
      renderFavorites();
    }
  } catch (e) {
    console.error(e);
  }
  document.getElementById('loading').style.display = 'none';
}

function renderFavorites() {
  const grid = document.getElementById('favorites-grid');
  const emptyState = document.getElementById('empty-state');

  if (favorites.length === 0) {
    grid.innerHTML = '';
    emptyState.style.display = 'block';
    return;
  }

  emptyState.style.display = 'none';

  grid.innerHTML = favorites.map(p => {
    const rating = parseFloat(p.seller_rating) || 0;
    const avatar = p.seller_avatar_id > 0
      ? `../mostrar_imagen.php?id=${p.seller_avatar_id}`
      : `https://ui-avatars.com/api/?name=${encodeURIComponent(p.seller_name)}&background=42ba25&color=fff`;
    const stock = parseInt(p.stock) || 0;

    let stockTag = '';
    if (stock === 0) {
      stockTag = '<div class="stock-tag out">Agotado</div>';
    } else if (stock <= 3) {
      stockTag = `<div class="stock-tag low">Últimas ${stock}</div>`;
    } else {
      stockTag = `<div class="stock-tag">${stock} disponibles</div>`;
    }

    return `
      <div class="product-card" data-id="${p.id}">
        <div class="card-img-zone" onclick="verDetalle(${p.id})">
          <img src="${p.primary_image || 'https://via.placeholder.com/400x250?text=Producto'}" alt="${p.name}">
          ${stockTag}
          <button class="favorite-btn" onclick="event.stopPropagation(); removeFavorite(${p.id})" title="Quitar de favoritos">
            <i class="fas fa-heart"></i>
          </button>
        </div>
        <div class="card-body" onclick="verDetalle(${p.id})">
          <div class="offer-question">¿QUIÉN QUIERE?</div>
          <h3 class="product-title">${p.name}</h3>
          <p class="product-description">${p.description || 'Producto exclusivo de viajero'}</p>

          <div class="route-box">
            <div>
              <div class="route-label">Viene de</div>
              <div class="route-val">${p.origin_country || 'Internacional'}</div>
            </div>
            <div style="border-left:1px solid #eee; padding-left:10px;">
              <div class="route-label">Entrega en</div>
              <div class="route-val">${p.destination_city || 'A coordinar'}</div>
            </div>
          </div>

          <div class="user-info">
            <img src="${avatar}" class="avatar-img" alt="${p.seller_name}">
            <div style="flex:1;">
              <div style="font-weight:600; font-size:13px;">@${p.seller_name}</div>
              <div style="font-size:11px; color:#f59e0b;">★ ${rating.toFixed(1)}</div>
            </div>
          </div>

          <div class="price-action">
            <div class="price-section">
              <div class="price-label">Precio</div>
              <div class="price-amt">${Math.floor(p.price)}<span>${p.currency}</span></div>
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

async function removeFavorite(id) {
  const result = await Swal.fire({
    title: '¿Quitar de favoritos?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#ef4444',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Sí, quitar',
    cancelButtonText: 'Cancelar'
  });

  if (!result.isConfirmed) return;

  try {
    const res = await fetch('shop-products-api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `action=remove_favorite&product_id=${id}`
    });
    const data = await res.json();

    if (data.success) {
      favorites = favorites.filter(f => f.id !== id);
      renderFavorites();

      Swal.fire({
        toast: true,
        position: 'bottom-end',
        icon: 'success',
        title: 'Eliminado de favoritos',
        showConfirmButton: false,
        timer: 2000
      });
    }
  } catch (e) {
    console.error(e);
  }
}

function addToCart(id) {
  const product = favorites.find(p => p.id === id);
  if (!product) return;

  let cart = JSON.parse(localStorage.getItem('sendvialo_cart') || '[]');
  const existingIndex = cart.findIndex(item => item.product_id === id);

  if (existingIndex >= 0) {
    if (cart[existingIndex].quantity < product.stock) {
      cart[existingIndex].quantity += 1;
    } else {
      Swal.fire({
        icon: 'warning',
        title: 'Stock limitado',
        text: `Solo hay ${product.stock} unidades disponibles`,
        confirmButtonColor: '#42ba25'
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

  // Actualizar badges
  const badges = document.querySelectorAll('#header-cart-count, #mobile-cart-count');
  const count = cart.reduce((n, i) => n + i.quantity, 0);
  badges.forEach(badge => {
    if (badge) {
      badge.textContent = count;
      badge.style.display = count > 0 ? 'flex' : 'none';
    }
  });

  Swal.fire({
    toast: true,
    position: 'bottom-end',
    icon: 'success',
    title: `${product.name} añadido al carrito`,
    showConfirmButton: false,
    timer: 2000
  });
}

document.addEventListener('DOMContentLoaded', cargarFavoritos);
</script>
</body>
</html>
