<?php
// shop-my-product-offers.php - Mis Ofertas en Productos (para compradores)
session_start();
require_once 'insignias1.php';
require_once '../config.php';

// Verificar que el usuario esté logueado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['usuario_id'];
$user_name = $_SESSION['usuario_nombre'] ?? $_SESSION['full_name'] ?? 'Usuario';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Mis Ofertas - SendVialo Shop</title>
  <link rel="stylesheet" href="../css/estilos.css"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.3.0/css/all.min.css"/>
  <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link rel="icon" href="../Imagenes/globo5.png" type="image/png"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <?php incluirEstilosInsignias(); ?>

  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', sans-serif;
      background: #f8f9fa;
      color: #333;
    }

    .my-offers-header {
      background: linear-gradient(135deg, #42ba25 0%, #2d8519 50%, #42ba25 100%);
      color: white;
      padding: 60px 20px;
      text-align: center;
    }

    .my-offers-header h1 {
      font-size: 2.5rem;
      margin-bottom: 10px;
    }

    .my-offers-header p {
      font-size: 1.1rem;
      opacity: 0.9;
    }

    .container {
      max-width: 1200px;
      margin: 40px auto;
      padding: 0 20px;
    }

    .tabs {
      display: flex;
      gap: 10px;
      margin-bottom: 30px;
      border-bottom: 2px solid #e1e5e9;
      flex-wrap: wrap;
    }

    .tab {
      padding: 15px 30px;
      background: transparent;
      border: none;
      border-bottom: 3px solid transparent;
      cursor: pointer;
      font-weight: 600;
      color: #666;
      transition: all 0.3s ease;
    }

    .tab.active {
      color: #42ba25;
      border-bottom-color: #42ba25;
    }

    .tab:hover {
      color: #42ba25;
    }

    .offers-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
      gap: 25px;
      margin-bottom: 40px;
    }

    .offer-card {
      background: white;
      border-radius: 15px;
      padding: 25px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.08);
      border: 2px solid #e1e5e9;
      transition: all 0.3s ease;
      cursor: pointer;
    }

    .offer-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 30px rgba(66, 186, 37, 0.15);
      border-color: #42ba25;
    }

    .offer-header {
      display: flex;
      justify-content: space-between;
      align-items: start;
      margin-bottom: 15px;
    }

    .offer-title {
      font-size: 1.3rem;
      font-weight: 700;
      color: #333;
      margin-bottom: 8px;
      flex: 1;
    }

    .status-badge {
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 0.8rem;
      font-weight: 600;
      white-space: nowrap;
    }

    .status-pending {
      background: rgba(255, 193, 7, 0.1);
      color: #FFC107;
    }

    .status-negotiating {
      background: rgba(255, 152, 0, 0.1);
      color: #FF9800;
    }

    .status-accepted {
      background: rgba(76, 175, 80, 0.1);
      color: #4CAF50;
    }

    .status-rejected {
      background: rgba(244, 67, 54, 0.1);
      color: #F44336;
    }

    .status-expired {
      background: rgba(158, 158, 158, 0.1);
      color: #9E9E9E;
    }

    .offer-product-image {
      width: 100%;
      height: 180px;
      object-fit: cover;
      border-radius: 10px;
      margin-bottom: 15px;
    }

    .offer-description {
      color: #666;
      font-size: 0.95rem;
      margin-bottom: 15px;
      line-height: 1.5;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .offer-meta {
      display: flex;
      flex-direction: column;
      gap: 10px;
      margin-bottom: 15px;
      font-size: 0.9rem;
      color: #666;
    }

    .meta-row {
      display: flex;
      gap: 15px;
    }

    .meta-item {
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .price-display {
      background: linear-gradient(135deg, #42ba25 0%, #2d8519 100%);
      color: white;
      padding: 12px 20px;
      border-radius: 10px;
      text-align: center;
      margin: 15px 0;
    }

    .price-display .label {
      font-size: 0.75rem;
      opacity: 0.9;
      margin-bottom: 5px;
    }

    .price-display .amount {
      font-size: 1.5rem;
      font-weight: 700;
    }

    .offer-footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding-top: 15px;
      border-top: 1px solid #eee;
    }

    .seller-info {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .seller-avatar {
      width: 35px;
      height: 35px;
      border-radius: 50%;
      object-fit: cover;
    }

    .seller-name {
      font-weight: 600;
      color: #333;
      font-size: 0.9rem;
    }

    .negotiation-badge {
      background: #FF9800;
      color: white;
      padding: 6px 12px;
      border-radius: 15px;
      font-size: 0.75rem;
      font-weight: 600;
    }

    .action-buttons {
      display: flex;
      gap: 10px;
      margin-top: 15px;
      flex-wrap: wrap;
    }

    .btn-view {
      flex: 1;
      background: #42ba25;
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      min-width: 120px;
    }

    .btn-view:hover {
      background: #2d8519;
      transform: translateY(-1px);
    }

    .btn-cancel {
      flex: 1;
      background: transparent;
      color: #f44336;
      border: 2px solid #f44336;
      padding: 10px 20px;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      min-width: 120px;
    }

    .btn-cancel:hover {
      background: #f44336;
      color: white;
    }

    .btn-pay {
      flex: 1;
      background: linear-gradient(135deg, #FF9800 0%, #F57C00 100%);
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      min-width: 120px;
      box-shadow: 0 4px 12px rgba(255, 152, 0, 0.3);
    }

    .btn-pay:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(255, 152, 0, 0.4);
    }

    .empty-state {
      text-align: center;
      padding: 80px 20px;
      color: #999;
    }

    .empty-state i {
      font-size: 4rem;
      margin-bottom: 20px;
      opacity: 0.5;
    }

    .empty-state h3 {
      font-size: 1.5rem;
      margin-bottom: 10px;
      color: #666;
    }

    .btn-browse {
      background: #42ba25;
      color: white;
      padding: 12px 30px;
      border-radius: 25px;
      text-decoration: none;
      display: inline-block;
      margin-top: 20px;
      font-weight: 600;
      transition: all 0.3s ease;
    }

    .btn-browse:hover {
      background: #2d8519;
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(66, 186, 37, 0.3);
    }

    @media (max-width: 768px) {
      .my-offers-header h1 {
        font-size: 1.8rem;
      }

      .offers-grid {
        grid-template-columns: 1fr;
      }

      .tabs {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
      }

      .tab {
        padding: 12px 20px;
        font-size: 0.9rem;
      }
    }
  </style>
</head>
<body>
  <?php include 'shop-header.php'; ?>

  <div class="my-offers-header">
    <h1><i class="fas fa-tags"></i> Mis Ofertas en Productos</h1>
    <p>Gestiona las ofertas que has hecho para comprar productos</p>
  </div>

  <div class="container">
    <div class="tabs">
      <button class="tab active" data-filter="all">
        <i class="fas fa-th"></i> Todas
      </button>
      <button class="tab" data-filter="pending">
        <i class="fas fa-clock"></i> Pendientes
      </button>
      <button class="tab" data-filter="negotiating">
        <i class="fas fa-handshake"></i> En negociación
      </button>
      <button class="tab" data-filter="accepted">
        <i class="fas fa-check-circle"></i> Aceptadas
      </button>
      <button class="tab" data-filter="rejected">
        <i class="fas fa-times-circle"></i> Rechazadas
      </button>
      <button class="tab" data-filter="expired">
        <i class="fas fa-hourglass-end"></i> Expiradas
      </button>
    </div>

    <div class="offers-grid" id="offersGrid">
      <!-- Las ofertas se cargarán aquí dinámicamente -->
    </div>
  </div>

  <?php if (file_exists('footer1.php')) include 'footer1.php'; ?>

  <script>
    const userId = <?= $user_id ?>;
    let allOffers = [];
    let currentFilter = 'all';

    // Cargar ofertas al iniciar
    document.addEventListener('DOMContentLoaded', () => {
      loadMyOffers();
      setupTabs();
    });

    // Configurar tabs
    function setupTabs() {
      const tabs = document.querySelectorAll('.tab');
      tabs.forEach(tab => {
        tab.addEventListener('click', () => {
          tabs.forEach(t => t.classList.remove('active'));
          tab.classList.add('active');
          currentFilter = tab.dataset.filter;
          renderOffers();
        });
      });
    }

    // Cargar ofertas del usuario
    async function loadMyOffers() {
      try {
        const response = await fetch(`shop-negotiations-backend.php?action=get_my_product_offers&user_id=${userId}`);
        const data = await response.json();

        if (data.success) {
          allOffers = data.offers || [];
          renderOffers();
        } else {
          showError(data.error || 'Error al cargar ofertas');
        }
      } catch (error) {
        console.error('Error:', error);
        showError('Error de conexión al cargar ofertas');
      }
    }

    // Renderizar ofertas según filtro
    function renderOffers() {
      const grid = document.getElementById('offersGrid');
      let filtered = allOffers;

      // Filtrar según status
      if (currentFilter !== 'all') {
        if (currentFilter === 'negotiating') {
          // En negociación = pending con contraofertas
          filtered = allOffers.filter(o =>
            o.status === 'pending' && parseInt(o.negotiation_count || 0) > 0
          );
        } else {
          filtered = allOffers.filter(o => o.status === currentFilter);
        }
      }

      if (filtered.length === 0) {
        grid.innerHTML = `
          <div class="empty-state" style="grid-column: 1/-1;">
            <i class="fas fa-inbox"></i>
            <h3>No hay ofertas ${currentFilter !== 'all' ? getFilterLabel(currentFilter) : ''}</h3>
            <p>Explora productos y haz ofertas a los vendedores</p>
            <a href="index.php" class="btn-browse">
              <i class="fas fa-search"></i> Explorar Productos
            </a>
          </div>
        `;
        return;
      }

      grid.innerHTML = filtered.map(offer => createOfferCard(offer)).join('');
    }

    // Crear tarjeta de oferta
    function createOfferCard(offer) {
      const statusLabel = getStatusLabel(offer.status, offer.negotiation_count);
      const statusClass = getStatusClass(offer.status, offer.negotiation_count);
      const productImage = offer.product_image || '../Imagenes/default-product.png';

      return `
        <div class="offer-card" onclick="viewProductDetail(${offer.product_id})">
          <div class="offer-header">
            <div class="offer-title">${escapeHtml(offer.product_name)}</div>
            <span class="status-badge ${statusClass}">${statusLabel}</span>
          </div>

          ${productImage ? `
            <img src="${productImage}" alt="${escapeHtml(offer.product_name)}" class="offer-product-image" onerror="this.src='../Imagenes/default-product.png'">
          ` : ''}

          <div class="offer-meta">
            <div class="meta-row">
              <div class="meta-item">
                <i class="fas fa-box"></i>
                <span>${offer.quantity} ${offer.quantity === 1 ? 'unidad' : 'unidades'}</span>
              </div>
              <div class="meta-item">
                <i class="fas fa-calendar"></i>
                <span>${formatDate(offer.created_at)}</span>
              </div>
            </div>
            ${offer.delivery_preference ? `
              <div class="meta-item">
                <i class="fas fa-truck"></i>
                <span>${escapeHtml(offer.delivery_preference)}</span>
              </div>
            ` : ''}
          </div>

          <div class="price-display">
            <div class="label">Tu oferta</div>
            <div class="amount">${formatPrice(offer.offered_price, offer.offered_currency)}</div>
          </div>

          ${parseInt(offer.negotiation_count || 0) > 0 ? `
            <div style="text-align: center; margin-bottom: 10px;">
              <span class="negotiation-badge">
                <i class="fas fa-comments"></i> ${offer.negotiation_count} ${parseInt(offer.negotiation_count) === 1 ? 'contraoferta' : 'contraofertas'}
              </span>
            </div>
          ` : ''}

          ${offer.message ? `
            <div style="background:#f8f9fa;padding:12px;border-radius:8px;margin-bottom:15px;font-size:0.9rem;color:#666;">
              <strong>Tu mensaje:</strong><br>
              ${escapeHtml(offer.message).substring(0, 100)}${offer.message.length > 100 ? '...' : ''}
            </div>
          ` : ''}

          <div class="offer-footer">
            <div class="seller-info">
              ${offer.seller_avatar ? `
                <img src="${offer.seller_avatar}" alt="${escapeHtml(offer.seller_name)}" class="seller-avatar">
              ` : `
                <div class="seller-avatar" style="background: #ddd; display: flex; align-items: center; justify-content: center;">
                  <i class="fas fa-user" style="color: #999;"></i>
                </div>
              `}
              <div class="seller-name">${escapeHtml(offer.seller_name)}</div>
            </div>
          </div>

          <div class="action-buttons">
            <button class="btn-view" onclick="event.stopPropagation(); viewOfferDetail(${offer.id})">
              <i class="fas fa-eye"></i> Ver Detalle
            </button>
            ${offer.status === 'accepted' ? `
              <button class="btn-pay" onclick="event.stopPropagation(); proceedToPayment(${offer.id})">
                <i class="fas fa-credit-card"></i> Pagar Ahora
              </button>
            ` : ''}
            ${offer.status === 'pending' ? `
              <button class="btn-cancel" onclick="event.stopPropagation(); cancelOffer(${offer.id})">
                <i class="fas fa-times"></i> Cancelar
              </button>
            ` : ''}
          </div>
        </div>
      `;
    }

    // Ver detalle del producto
    function viewProductDetail(productId) {
      window.location.href = `shop-product-detail.php?id=${productId}`;
    }

    // Ver detalle de la oferta (modal con historial de negociación)
    async function viewOfferDetail(offerId) {
      try {
        const response = await fetch(`shop-negotiations-backend.php?action=get_product_negotiation_history&offer_id=${offerId}`);
        const data = await response.json();

        if (data.success) {
          showNegotiationHistoryModal(data.history, offerId);
        } else {
          showError(data.error);
        }
      } catch (error) {
        showError('Error al cargar historial');
      }
    }

    // Mostrar modal con historial de negociación
    function showNegotiationHistoryModal(history, offerId) {
      const historyHtml = history.map((item, index) => {
        const isLatest = index === history.length - 1;
        const userClass = item.user_type === 'buyer' ? 'from-buyer' : 'from-seller';
        const avatar = item.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(item.nombre_usuario)}&background=random&size=40`;

        return `
          <div style="background:${isLatest ? '#FFF3E0' : 'white'};padding:15px;border-radius:10px;margin-bottom:12px;border-left:4px solid ${item.user_type === 'buyer' ? '#2196F3' : '#4CAF50'}">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
              <div style="display:flex;align-items:center;gap:10px;">
                <img src="${avatar}" style="width:40px;height:40px;border-radius:50%;" />
                <div>
                  <div style="font-weight:600;">${item.nombre_usuario}</div>
                  <div style="font-size:0.8rem;color:#999;">${item.role_name}</div>
                </div>
              </div>
              <div style="font-size:1.3rem;font-weight:700;color:#42ba25;">
                ${formatPrice(item.offered_price, item.offered_currency)}
              </div>
            </div>
            ${item.message ? `
              <div style="color:#555;margin:10px 0;">
                <strong>Mensaje:</strong><br>${item.message}
              </div>
            ` : ''}
            <div style="font-size:0.8rem;color:#999;">
              <i class="fas fa-clock"></i> ${formatDateTime(item.created_at)}
              ${isLatest ? '<span style="margin-left:10px;background:#FF9800;color:white;padding:4px 12px;border-radius:15px;font-weight:700;font-size:0.75rem;">OFERTA ACTUAL</span>' : ''}
            </div>
          </div>
        `;
      }).join('');

      Swal.fire({
        title: '<i class="fas fa-history"></i> Historial de Negociación',
        html: `
          <div style="max-height:400px;overflow-y:auto;text-align:left;">
            ${historyHtml}
          </div>
        `,
        width: 600,
        showCloseButton: true,
        showConfirmButton: false
      });
    }

    // Cancelar oferta
    async function cancelOffer(offerId) {
      const result = await Swal.fire({
        icon: 'warning',
        title: '¿Cancelar esta oferta?',
        text: 'Esta acción no se puede deshacer',
        showCancelButton: true,
        confirmButtonText: 'Sí, cancelar',
        cancelButtonText: 'No',
        confirmButtonColor: '#f44336'
      });

      if (result.isConfirmed) {
        try {
          const formData = new FormData();
          formData.append('action', 'cancel_product_offer');
          formData.append('offer_id', offerId);

          const response = await fetch('shop-negotiations-backend.php', {
            method: 'POST',
            body: formData
          });

          const data = await response.json();

          if (data.success) {
            Swal.fire({
              icon: 'success',
              title: 'Oferta cancelada',
              confirmButtonColor: '#42ba25'
            }).then(() => loadMyOffers());
          } else {
            throw new Error(data.error);
          }
        } catch (error) {
          showError(error.message);
        }
      }
    }

    // Proceder al pago
    function proceedToPayment(offerId) {
      // Redirigir a la página de pago con Stripe
      window.location.href = `shop-pagar-producto.php?offer_id=${offerId}`;
    }

    // Obtener etiqueta de estado
    function getStatusLabel(status, negotiationCount) {
      negotiationCount = parseInt(negotiationCount || 0);

      if (status === 'pending' && negotiationCount > 0) {
        return 'En negociación';
      }

      const labels = {
        'pending': 'Pendiente',
        'accepted': 'Aceptada',
        'rejected': 'Rechazada',
        'expired': 'Expirada'
      };
      return labels[status] || status;
    }

    // Obtener clase CSS de estado
    function getStatusClass(status, negotiationCount) {
      negotiationCount = parseInt(negotiationCount || 0);

      if (status === 'pending' && negotiationCount > 0) {
        return 'status-negotiating';
      }

      return `status-${status}`;
    }

    // Obtener etiqueta de filtro
    function getFilterLabel(filter) {
      const labels = {
        'pending': 'pendientes',
        'negotiating': 'en negociación',
        'accepted': 'aceptadas',
        'rejected': 'rechazadas',
        'expired': 'expiradas'
      };
      return labels[filter] || '';
    }

    // Formatear precio
    function formatPrice(amount, currency = 'EUR') {
      const symbols = {
        'EUR': '€',
        'USD': '$',
        'BOB': 'Bs',
        'GBP': '£'
      };
      return `${parseFloat(amount).toFixed(2)} ${symbols[currency] || currency}`;
    }

    // Formatear fecha
    function formatDate(dateStr) {
      if (!dateStr) return 'Sin fecha';
      const date = new Date(dateStr);
      return date.toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    // Formatear fecha y hora
    function formatDateTime(dateStr) {
      if (!dateStr) return 'Sin fecha';
      const date = new Date(dateStr);
      return date.toLocaleString('es-ES', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
    }

    // Escapar HTML
    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    // Mostrar error
    function showError(message) {
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: message,
        confirmButtonColor: '#42ba25'
      });
    }
  </script>
</body>
</html>