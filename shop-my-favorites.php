<?php
session_start();
require_once 'insignias1.php';
require_once '../config.php';

$user_logged_in = isset($_SESSION['usuario_id']);
$user_id = $user_logged_in ? $_SESSION['usuario_id'] : null;

if (!$user_logged_in) {
    header('Location: shop-login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Mis Favoritos - SendVialo Shop</title>
  <link rel="stylesheet" href="../css/estilos.css"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.3.0/css/all.min.css"/>
  <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link rel="icon" href="../Imagenes/globo5.png" type="image/png"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <?php incluirEstilosInsignias(); ?>
  
<style>
:root {
  --color1: #41ba0d;
  --color2: #5dcb2a;
  --dark: #111827;
  --gray-500: #6b7280;
  --gray-300: #d1d5db;
  --gray-100: #f3f4f6;
}

* { margin: 0; padding: 0; box-sizing: border-box; }

body {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  background: #f7f7f7;
  color: #222;
  padding-top: 80px;
  padding-bottom: 100px;
}

/* ========== HEADER ========== */
.page-header {
  background: white;
  padding: 40px 24px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
  margin-bottom: 32px;
}

.header-content {
  max-width: 1400px;
  margin: 0 auto;
}

.header-title {
  font-size: 32px;
  font-weight: 800;
  color: var(--dark);
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  gap: 12px;
}

.header-title i {
  color: #ff385c;
  font-size: 28px;
}

.header-subtitle {
  color: var(--gray-500);
  font-size: 16px;
}

.favorites-count {
  background: var(--color1);
  color: white;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 14px;
  font-weight: 700;
  margin-left: 8px;
}

/* ========== TABS ========== */
.tabs-container {
  max-width: 1400px;
  margin: 0 auto 32px;
  padding: 0 24px;
}

.tabs {
  display: flex;
  gap: 12px;
  border-bottom: 2px solid var(--gray-300);
  overflow-x: auto;
}

.tab-btn {
  padding: 12px 24px;
  background: none;
  border: none;
  border-bottom: 3px solid transparent;
  font-size: 15px;
  font-weight: 600;
  color: var(--gray-500);
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}

.tab-btn:hover {
  color: var(--dark);
}

.tab-btn.active {
  color: var(--color1);
  border-bottom-color: var(--color1);
}

/* ========== CONTENIDO ========== */
.main-content {
  max-width: 1400px;
  margin: 0 auto;
  padding: 0 24px;
}

.products-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 32px;
}

/* ========== TARJETAS (reutilizar del index) ========== */
.product-card {
  background: white;
  border-radius: 20px;
  overflow: hidden;
  cursor: pointer;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
  border: 1px solid var(--gray-300);
}

.product-card:hover {
  transform: translateY(-6px);
  box-shadow: 0 16px 40px rgba(0, 0, 0, 0.1);
  border-color: var(--color1);
}

.product-image-container {
  position: relative;
  height: 240px;
  background: var(--gray-100);
  overflow: hidden;
}

.product-image-container img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.5s;
}

.product-card:hover .product-image-container img {
  transform: scale(1.05);
}

.category-icon-placeholder {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  font-size: 48px;
  color: #ddd;
}

.favorite-button {
  position: absolute;
  top: 16px;
  right: 16px;
  width: 40px;
  height: 40px;
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(10px);
  border: none;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
  z-index: 10;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.favorite-button:hover { 
  transform: scale(1.1);
  background: white;
}

.favorite-button i {
  font-size: 17px;
  color: #ff385c;
}

.product-content {
  padding: 24px;
}

.request-question {
  font-size: 13px;
  font-weight: 700;
  color: #e65100;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  margin-bottom: 12px;
}

.product-title {
  font-size: 19px;
  font-weight: 700;
  color: var(--dark);
  margin-bottom: 18px;
  line-height: 1.4;
  letter-spacing: -0.3px;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.route-clean {
  margin-bottom: 18px;
}

.route-steps {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.route-step {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 0;
}

.step-number {
  width: 28px;
  height: 28px;
  background: linear-gradient(135deg, var(--color1), var(--color2));
  color: white;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-weight: 800;
  flex-shrink: 0;
  box-shadow: 0 2px 8px rgba(65, 186, 13, 0.25);
}

.step-content {
  flex: 1;
}

.step-label {
  font-size: 11px;
  color: var(--gray-500);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 3px;
}

.step-location {
  font-size: 15px;
  font-weight: 600;
  color: var(--dark);
  display: flex;
  align-items: center;
  gap: 6px;
}

.price-section {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 0;
  margin-bottom: 18px;
  border-top: 1px solid var(--gray-300);
  border-bottom: 1px solid var(--gray-300);
}

.price-label {
  font-size: 12px;
  color: var(--gray-500);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.price-value {
  font-size: 28px;
  font-weight: 800;
  color: var(--color1);
  letter-spacing: -0.5px;
}

.product-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.user-info-compact {
  display: flex;
  align-items: center;
  gap: 10px;
}

.user-avatar-small {
  position: relative;
  width: 40px;
  height: 40px;
  flex-shrink: 0;
}

.user-avatar-small .profile-img-container {
  position: relative;
  display: inline-block;
  width: 40px;
  height: 40px;
}

.user-avatar-small .profile-img {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  object-fit: cover;
  position: relative;
  z-index: 2;
}

.user-avatar-small .laurel-crown {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 60px;
  height: 60px;
  z-index: 1;
  pointer-events: none;
}

.user-avatar-small .verificacion-wrapper {
  position: absolute;
  bottom: -3px;
  right: -3px;
  z-index: 3;
}

.user-avatar-small .verificacion-insignia {
  width: 18px;
  height: 18px;
  display: block;
}

.user-details {
  flex: 1;
}

.user-name {
  font-size: 14px;
  font-weight: 600;
  color: var(--dark);
  margin-bottom: 2px;
}

.user-rating-small {
  font-size: 12px;
  color: var(--gray-500);
  display: flex;
  align-items: center;
  gap: 4px;
}

.user-rating-small i {
  color: #fbbf24;
  font-size: 12px;
}

.offers-count {
  background: var(--gray-100);
  padding: 8px 12px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 600;
  color: var(--dark);
  display: flex;
  align-items: center;
  gap: 6px;
  border: 1px solid var(--gray-300);
}

.offers-count i {
  color: var(--color1);
  font-size: 13px;
}

.apply-btn {
  width: 100%;
  padding: 16px;
  background: linear-gradient(135deg, var(--color1) 0%, var(--color2) 100%);
  border: none;
  border-radius: 12px;
  color: white;
  font-size: 15px;
  font-weight: 600;
  letter-spacing: 0.3px;
  cursor: pointer;
  transition: all 0.3s;
  box-shadow: 0 4px 14px rgba(65, 186, 13, 0.25);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.apply-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(65, 186, 13, 0.35);
}

/* ========== EMPTY STATE ========== */
.empty-state {
  text-align: center;
  padding: 100px 20px;
}

.empty-state i {
  font-size: 64px;
  color: #ff385c;
  margin-bottom: 20px;
  opacity: 0.5;
}

.empty-state h3 {
  font-size: 24px;
  color: var(--dark);
  margin-bottom: 10px;
}

.empty-state p { 
  color: var(--gray-500); 
  margin-bottom: 24px;
}

.btn-explore {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 14px 28px;
  background: var(--color1);
  color: white;
  border-radius: 12px;
  text-decoration: none;
  font-weight: 600;
  transition: all 0.3s;
}

.btn-explore:hover {
  background: #379909;
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(65, 186, 13, 0.35);
  color: white;
}

/* ========== LOADING ========== */
.loading {
  text-align: center;
  padding: 80px 20px;
}

.loading i {
  font-size: 40px;
  color: var(--color1);
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

/* ========== RESPONSIVE ========== */
@media (max-width: 768px) {
  .page-header {
    padding: 24px 16px;
  }
  
  .header-title {
    font-size: 24px;
  }
  
  .products-grid {
    grid-template-columns: 1fr;
    gap: 20px;
  }
  
  .main-content {
    padding: 0 16px;
  }
}
</style>
</head>
<body>
<?php include 'header2.php'; ?>

<!-- HEADER -->
<div class="page-header">
  <div class="header-content">
    <h1 class="header-title">
      <i class="fas fa-heart"></i>
      Mis Favoritos
      <span class="favorites-count" id="favorites-count">0</span>
    </h1>
    <p class="header-subtitle">Solicitudes que has marcado para seguir</p>
  </div>
</div>

<!-- TABS -->
<div class="tabs-container">
  <div class="tabs">
    <button class="tab-btn active" data-filter="all">
      <i class="fas fa-list"></i> Todos
    </button>
    <button class="tab-btn" data-filter="open">
      <i class="fas fa-folder-open"></i> Abiertas
    </button>
    <button class="tab-btn" data-filter="negotiating">
      <i class="fas fa-handshake"></i> En negociación
    </button>
  </div>
</div>

<!-- CONTENIDO -->
<main class="main-content">
  <div class="products-grid" id="favorites-grid"></div>
  
  <div class="loading" id="loading">
    <i class="fas fa-spinner"></i>
    <p style="margin-top:16px;color:#717171">Cargando favoritos...</p>
  </div>
  
  <div class="empty-state" id="empty-state" style="display:none;">
    <i class="fas fa-heart-broken"></i>
    <h3>No tienes favoritos aún</h3>
    <p>Explora solicitudes y marca las que te interesen con ❤️</p>
    <a href="shop-requests-index.php" class="btn-explore">
      <i class="fas fa-search"></i> Explorar Solicitudes
    </a>
  </div>
</main>

<?php if (file_exists('footer1.php')) include 'footer1.php'; ?>

<script>
const $grid = document.getElementById('favorites-grid');
const $loading = document.getElementById('loading');
const $empty = document.getElementById('empty-state');
const $favoritesCount = document.getElementById('favorites-count');

let allFavorites = [];
let currentFilter = 'all';

// ========== FUNCIÓN PARA GENERAR HTML DE AVATAR CON LAUREL E INSIGNIA ==========
function getAvatarWithLaurel(avatarUrl, rating, isVerified) {
  rating = parseFloat(rating) || 0;
  
  let laurelClass = '';
  if (rating >= 4.8) laurelClass = 'laurel-oro-intenso';
  else if (rating >= 4.5) laurelClass = 'laurel-oro';
  else if (rating >= 4.0) laurelClass = 'laurel-plata';
  else if (rating >= 3.5) laurelClass = 'laurel-bronce';
  
  const laurelHTML = laurelClass ? `<div class="laurel-crown ${laurelClass}"></div>` : '';
  const verificacionHTML = isVerified 
    ? `<div class="verificacion-wrapper"><div class="verificacion-insignia"></div></div>`
    : '';
  
  return `
    <div class="profile-img-container">
      <img src="${avatarUrl}" alt="" class="profile-img">
      ${laurelHTML}
      ${verificacionHTML}
    </div>
  `;
}

// ========== CARGAR FAVORITOS ==========
document.addEventListener('DOMContentLoaded', () => {
  cargarFavoritos();
  
  // Tabs
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      currentFilter = btn.dataset.filter;
      renderFavoritos();
    });
  });
});

async function cargarFavoritos() {
  try {
    const response = await fetch('shop-requests-actions.php?action=get_my_favorites');
    const data = await response.json();
    
    if (data.success) {
      allFavorites = data.favorites || [];
      renderFavoritos();
    }
  } catch (error) {
    console.error('Error:', error);
    $loading.style.display = 'none';
    $empty.style.display = 'block';
  }
}

function renderFavoritos() {
  $loading.style.display = 'none';
  
  let favorites = allFavorites;
  
  // Filtrar
  if (currentFilter !== 'all') {
    favorites = allFavorites.filter(f => f.status === currentFilter);
  }
  
  $favoritesCount.textContent = favorites.length;
  
  if (favorites.length === 0) {
    $grid.innerHTML = '';
    $empty.style.display = 'block';
    return;
  }
  
  $empty.style.display = 'none';
  $grid.innerHTML = '';
  
  favorites.forEach(r => {
    const rating = parseFloat(r.requester_rating) || 0;
    const isVerified = r.requester_verified || false;
    const proposalCount = parseInt(r.proposal_count) || 0;
    
    const requesterUsername = r.username || r.requester_username || 'Usuario';
    const requesterAvatar = r.requester_avatar_id > 0 
      ? `../mostrar_imagen.php?id=${r.requester_avatar_id}`
      : `https://ui-avatars.com/api/?name=${encodeURIComponent(requesterUsername)}&background=41ba0d&color=fff&size=40`;
    
    let imageContent = r.reference_images && r.reference_images[0]
      ? `<img src="${r.reference_images[0]}" alt="" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"><i class="fas fa-box category-icon-placeholder" style="display:none"></i>`
      : `<i class="fas fa-box category-icon-placeholder"></i>`;
    
    let originText = r.origin_flexible 
      ? '🌍 Cualquier país' 
      : `✈️ ${extractCity(r.origin_country)}`;
    
    let destinationText = `📍 ${extractCity(r.destination_city)}`;
    
    const card = document.createElement('div');
    card.className = 'product-card';
    card.onclick = () => window.location.href = `shop-request-detail.php?id=${r.id}`;
    card.innerHTML = `
      <div class="product-image-container">
        ${imageContent}
        <button class="favorite-button active" onclick="toggleFavorite(event, ${r.id})">
          <i class="fas fa-heart"></i>
        </button>
      </div>
      <div class="product-content">
        <div class="request-question">¿Quién me lo trae?</div>
        <h3 class="product-title">${r.title}</h3>
        
        <div class="route-clean">
          <div class="route-steps">
            <div class="route-step">
              <div class="step-content">
                <div class="step-label">Comprar desde</div>
                <div class="step-location">${originText}</div>
              </div>
            </div>
            <div class="route-step">
              <div class="step-content">
                <div class="step-label">Traerlo hasta</div>
                <div class="step-location">${destinationText}</div>
              </div>
            </div>
          </div>
        </div>
        
        <div class="price-section">
          <span class="price-label">Dispuesto a pagar</span>
          <span class="price-value">${formatPrice(r.budget_amount, r.budget_currency)}</span>
        </div>
        
        <div class="product-footer">
          <div class="user-info-compact">
            <div class="user-avatar-small">
              ${getAvatarWithLaurel(requesterAvatar, rating, isVerified)}
            </div>
            <div class="user-details">
              <div class="user-name">@${requesterUsername}</div>
              <div class="user-rating-small">
                ${rating > 0 ? `<i class="fas fa-star"></i> ${rating.toFixed(1)}` : 'Sin valoraciones'}
              </div>
            </div>
          </div>
          ${proposalCount > 0 ? `<div class="offers-count"><i class="fas fa-users"></i> ${proposalCount}</div>` : ''}
        </div>
        
        <button class="apply-btn" onclick="event.stopPropagation(); window.location.href='shop-request-detail.php?id=${r.id}'">
          Ver Detalles
        </button>
      </div>
    `;
    $grid.appendChild(card);
  });
}

function toggleFavorite(event, id) {
  event.stopPropagation();
  
  fetch('shop-requests-actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `action=remove_favorite&request_id=${id}`
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      allFavorites = allFavorites.filter(f => f.id !== id);
      renderFavoritos();
    }
  });
}

function extractCity(location) {
  if (!location) return 'No especificado';
  const parts = location.split(',');
  return parts[0].trim();
}

function formatPrice(amount, currency) {
  const symbols = { EUR: '€', USD: '$', BOB: 'Bs' };
  const symbol = symbols[currency] || '€';
  const value = Number(amount).toFixed(0);
  return ['USD'].includes(currency) ? `${symbol}${value}` : `${value}${symbol}`;
}
</script>
</body>
</html>
