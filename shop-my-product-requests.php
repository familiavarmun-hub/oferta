<?php
// shop-my-product-requests.php - Solicitudes de Oferta Recibidas (para vendedores/viajeros)
session_start();
require_once 'insignias1.php';
require_once __DIR__ . '/config.php';

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
  <title>Solicitudes de Oferta - SendVialo Shop</title>
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

    .header-section {
      background: linear-gradient(135deg, #42ba25 0%, #37a01f 100%);
      color: white;
      padding: 60px 20px;
      text-align: center;
    }

    .header-section h1 {
      font-size: 2.5rem;
      margin-bottom: 10px;
    }

    .header-section p {
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

    .requests-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
      gap: 25px;
      margin-bottom: 40px;
    }

    .request-card {
      background: white;
      border-radius: 15px;
      padding: 25px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.08);
      transition: all 0.3s ease;
      position: relative;
      cursor: pointer;
    }

    .request-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }

    .request-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 20px;
    }

    .product-info {
      flex: 1;
    }

    .product-name {
      font-size: 1.3rem;
      font-weight: 700;
      color: #1a1a1a;
      margin-bottom: 8px;
    }

    .product-original-price {
      font-size: 0.9rem;
      color: #999;
      text-decoration: line-through;
    }

    .status-badge {
      padding: 6px 14px;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 600;
      white-space: nowrap;
    }

    .status-pending {
      background: rgba(255, 193, 7, 0.1);
      color: #FFC107;
    }

    .status-negotiating {
      background: rgba(33, 150, 243, 0.1);
      color: #2196F3;
    }

    .status-accepted {
      background: rgba(76, 175, 80, 0.1);
      color: #4CAF50;
    }

    .status-rejected {
      background: rgba(244, 67, 54, 0.1);
      color: #f44336;
    }

    .status-expired {
      background: rgba(158, 158, 158, 0.1);
      color: #9e9e9e;
    }

    .buyer-section {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 15px;
      background: #f8f9fa;
      border-radius: 10px;
      margin-bottom: 15px;
    }

    .buyer-avatar {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid #42ba25;
    }

    .buyer-details {
      flex: 1;
    }

    .buyer-name {
      font-weight: 600;
      color: #1a1a1a;
      margin-bottom: 3px;
    }

    .buyer-date {
      font-size: 0.85rem;
      color: #999;
    }

    .offer-price-section {
      text-align: center;
      padding: 20px;
      background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
      border-radius: 12px;
      margin: 15px 0;
    }

    .offer-label {
      font-size: 0.9rem;
      color: #666;
      margin-bottom: 5px;
    }

    .offer-price {
      font-size: 2rem;
      font-weight: 700;
      color: #42ba25;
    }

    .offer-quantity {
      font-size: 0.95rem;
      color: #666;
      margin-top: 5px;
    }

    .negotiation-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 15px;
      background: rgba(33, 150, 243, 0.1);
      color: #2196F3;
      border-radius: 20px;
      font-size: 0.9rem;
      font-weight: 600;
    }

    .offer-message {
      background: #f8f9fa;
      padding: 15px;
      border-radius: 10px;
      margin: 15px 0;
      font-size: 0.95rem;
      color: #666;
      border-left: 4px solid #42ba25;
    }

    .action-buttons {
      display: flex;
      gap: 10px;
      margin-top: 15px;
    }

    .btn {
      flex: 1;
      padding: 12px;
      border: none;
      border-radius: 10px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }

    .btn-accept {
      background: #4CAF50;
      color: white;
    }

    .btn-accept:hover {
      background: #45a049;
    }

    .btn-reject {
      background: #f44336;
      color: white;
    }

    .btn-reject:hover {
      background: #da190b;
    }

    .btn-counter {
      background: #2196F3;
      color: white;
    }

    .btn-counter:hover {
      background: #0b7dda;
    }

    .btn-view {
      background: #FF9800;
      color: white;
    }

    .btn-view:hover {
      background: #FB8C00;
    }

    .empty-state {
      text-align: center;
      padding: 60px 20px;
    }

    .empty-state i {
      font-size: 5rem;
      color: #ddd;
      margin-bottom: 20px;
    }

    .empty-state h2 {
      color: #999;
      margin-bottom: 10px;
    }

    .loading-state {
      text-align: center;
      padding: 60px 20px;
    }

    .loading-state i {
      font-size: 3rem;
      color: #42ba25;
      animation: spin 1s linear infinite;
    }

    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }

    /* Modal de historial */
    .modal {
      display: none;
      position: fixed;
      z-index: 10000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.6);
      backdrop-filter: blur(5px);
    }

    .modal-content {
      background: white;
      margin: 50px auto;
      padding: 30px;
      border-radius: 20px;
      max-width: 800px;
      max-height: 80vh;
      overflow-y: auto;
      animation: slideIn 0.3s ease;
    }

    @keyframes slideIn {
      from {
        transform: translateY(-50px);
        opacity: 0;
      }
      to {
        transform: translateY(0);
        opacity: 1;
      }
    }

    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 25px;
      padding-bottom: 15px;
      border-bottom: 2px solid #eee;
    }

    .modal-header h2 {
      color: #1a1a1a;
      font-size: 1.8rem;
    }

    .close-modal {
      font-size: 2rem;
      color: #999;
      cursor: pointer;
      transition: color 0.3s;
    }

    .close-modal:hover {
      color: #333;
    }

    .negotiation-timeline {
      position: relative;
      padding: 20px 0;
    }

    .negotiation-item {
      display: flex;
      gap: 20px;
      margin-bottom: 25px;
      position: relative;
    }

    .negotiation-item::before {
      content: '';
      position: absolute;
      left: 22px;
      top: 45px;
      bottom: -25px;
      width: 2px;
      background: #e0e0e0;
    }

    .negotiation-item:last-child::before {
      display: none;
    }

    .negotiation-avatar {
      width: 45px;
      height: 45px;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid #42ba25;
      z-index: 1;
    }

    .negotiation-content {
      flex: 1;
      background: #f8f9fa;
      padding: 20px;
      border-radius: 12px;
    }

    .negotiation-header {
      display: flex;
      justify-content: space-between;
      margin-bottom: 10px;
    }

    .negotiation-user {
      font-weight: 600;
      color: #1a1a1a;
    }

    .negotiation-date {
      font-size: 0.85rem;
      color: #999;
    }

    .negotiation-price {
      font-size: 1.5rem;
      font-weight: 700;
      color: #42ba25;
      margin: 10px 0;
    }

    .negotiation-message {
      color: #666;
      font-size: 0.95rem;
      line-height: 1.6;
    }

    @media (max-width: 768px) {
      .requests-grid {
        grid-template-columns: 1fr;
      }

      .action-buttons {
        flex-direction: column;
      }

      .modal-content {
        margin: 20px;
        max-height: 90vh;
      }
    }
  </style>
</head>
<body>
<?php include 'shop-header.php'; ?>

<div class="header-section">
  <h1><i class="fas fa-inbox"></i> Solicitudes de Oferta Recibidas</h1>
  <p>Gestiona las ofertas que reciben tus productos</p>
</div>

<div class="container">
  <!-- Tabs de filtrado -->
  <div class="tabs">
    <button class="tab active" data-filter="all">
      <i class="fas fa-list"></i> Todas
    </button>
    <button class="tab" data-filter="pending">
      <i class="fas fa-clock"></i> Pendientes
    </button>
    <button class="tab" data-filter="negotiating">
      <i class="fas fa-handshake"></i> En Negociación
    </button>
    <button class="tab" data-filter="accepted">
      <i class="fas fa-check-circle"></i> Aceptadas
    </button>
    <button class="tab" data-filter="rejected">
      <i class="fas fa-times-circle"></i> Rechazadas
    </button>
  </div>

  <!-- Loading state -->
  <div id="loading-state" class="loading-state">
    <i class="fas fa-spinner"></i>
    <p>Cargando solicitudes...</p>
  </div>

  <!-- Grid de solicitudes -->
  <div id="requests-grid" class="requests-grid" style="display: none;"></div>

  <!-- Empty state -->
  <div id="empty-state" class="empty-state" style="display: none;">
    <i class="fas fa-inbox"></i>
    <h2>No hay solicitudes</h2>
    <p>Cuando recibas ofertas en tus productos aparecerán aquí</p>
  </div>
</div>

<!-- Modal de Historial de Negociación -->
<div id="historyModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2><i class="fas fa-history"></i> Historial de Negociación</h2>
      <span class="close-modal" onclick="closeHistoryModal()">&times;</span>
    </div>
    <div id="history-content" class="negotiation-timeline"></div>
  </div>
</div>

<script>
  const userId = <?= $user_id ?>;
  let allRequests = [];
  let currentFilter = 'all';

  document.addEventListener('DOMContentLoaded', () => {
    loadRequests();
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
        renderRequests();
      });
    });
  }

  // Cargar solicitudes del vendedor
  async function loadRequests() {
    try {
      const response = await fetch(`shop-negotiations-backend.php?action=get_seller_product_offers&user_id=${userId}`);
      const data = await response.json();

      if (data.success) {
        allRequests = data.offers || [];
        renderRequests();
      } else {
        showError(data.error || 'Error al cargar solicitudes');
      }
    } catch (error) {
      console.error('Error:', error);
      showError('Error al conectar con el servidor');
    }
  }

  // Renderizar solicitudes
  function renderRequests() {
    const grid = document.getElementById('requests-grid');
    const loadingState = document.getElementById('loading-state');
    const emptyState = document.getElementById('empty-state');

    loadingState.style.display = 'none';

    // Filtrar según tab activo
    let filtered = allRequests;
    if (currentFilter !== 'all') {
      if (currentFilter === 'negotiating') {
        filtered = allRequests.filter(r =>
          r.status === 'pending' && parseInt(r.negotiation_count || 0) > 0
        );
      } else {
        filtered = allRequests.filter(r => r.status === currentFilter);
      }
    }

    if (filtered.length === 0) {
      grid.style.display = 'none';
      emptyState.style.display = 'block';
      return;
    }

    emptyState.style.display = 'none';
    grid.style.display = 'grid';
    grid.innerHTML = filtered.map(request => createRequestCard(request)).join('');
  }

  // Crear tarjeta de solicitud
  function createRequestCard(request) {
    const statusClass = getStatusClass(request.status, request.negotiation_count);
    const statusLabel = getStatusLabel(request.status, request.negotiation_count);
    const negotiationCount = parseInt(request.negotiation_count || 0);
    const canRespond = request.status === 'pending';

    return `
      <div class="request-card">
        <div class="request-header">
          <div class="product-info">
            <div class="product-name">${escapeHtml(request.product_name)}</div>
            <div class="product-original-price">Precio original: ${formatPrice(request.product_price, request.product_currency)}</div>
          </div>
          <span class="status-badge ${statusClass}">${statusLabel}</span>
        </div>

        <div class="buyer-section">
          ${request.buyer_avatar ? `
            <img src="${request.buyer_avatar}" alt="${escapeHtml(request.buyer_name)}" class="buyer-avatar">
          ` : `
            <div class="buyer-avatar" style="background: #ddd; display: flex; align-items: center; justify-content: center;">
              <i class="fas fa-user" style="color: #999;"></i>
            </div>
          `}
          <div class="buyer-details">
            <div class="buyer-name">${escapeHtml(request.buyer_name)}</div>
            <div class="buyer-date">
              <i class="fas fa-clock"></i> ${formatDate(request.created_at)}
            </div>
          </div>
        </div>

        <div class="offer-price-section">
          <div class="offer-label">Oferta del comprador</div>
          <div class="offer-price">${formatPrice(request.offered_price, request.offered_currency)}</div>
          <div class="offer-quantity">Cantidad: ${request.quantity}</div>
        </div>

        ${negotiationCount > 0 ? `
          <div style="text-align: center; margin: 15px 0;">
            <span class="negotiation-badge">
              <i class="fas fa-comments"></i> ${negotiationCount} ${negotiationCount === 1 ? 'contraoferta' : 'contraofertas'}
            </span>
          </div>
        ` : ''}

        ${request.message ? `
          <div class="offer-message">
            <strong><i class="fas fa-comment"></i> Mensaje del comprador:</strong><br>
            ${escapeHtml(request.message)}
          </div>
        ` : ''}

        <div class="action-buttons">
          <button class="btn btn-view" onclick="viewHistory(${request.id})">
            <i class="fas fa-eye"></i> Ver Detalle
          </button>
          ${canRespond ? `
            <button class="btn btn-accept" onclick="acceptOffer(${request.id})">
              <i class="fas fa-check"></i> Aceptar
            </button>
            <button class="btn btn-counter" onclick="counterOffer(${request.id})">
              <i class="fas fa-exchange-alt"></i> Contraofertear
            </button>
            <button class="btn btn-reject" onclick="rejectOffer(${request.id})">
              <i class="fas fa-times"></i> Rechazar
            </button>
          ` : ''}
        </div>
      </div>
    `;
  }

  // Ver historial de negociación
  async function viewHistory(offerId) {
    try {
      const response = await fetch(`shop-negotiations-backend.php?action=get_product_negotiation_history&offer_id=${offerId}`);
      const data = await response.json();

      if (data.success) {
        showHistoryModal(data.history || []);
      } else {
        showError(data.error || 'Error al cargar historial');
      }
    } catch (error) {
      console.error('Error:', error);
      showError('Error al conectar con el servidor');
    }
  }

  // Mostrar modal de historial
  function showHistoryModal(history) {
    const modal = document.getElementById('historyModal');
    const content = document.getElementById('history-content');

    if (history.length === 0) {
      content.innerHTML = '<p style="text-align:center;color:#999;">No hay historial de negociación aún</p>';
    } else {
      content.innerHTML = history.map(item => `
        <div class="negotiation-item">
          ${item.user_avatar ? `
            <img src="${item.user_avatar}" alt="${escapeHtml(item.user_name)}" class="negotiation-avatar">
          ` : `
            <div class="negotiation-avatar" style="background: #ddd; display: flex; align-items: center; justify-content: center;">
              <i class="fas fa-user" style="color: #999;"></i>
            </div>
          `}
          <div class="negotiation-content">
            <div class="negotiation-header">
              <span class="negotiation-user">${escapeHtml(item.user_name)}</span>
              <span class="negotiation-date">${formatDate(item.created_at)}</span>
            </div>
            <div class="negotiation-price">${formatPrice(item.proposed_price, item.proposed_currency)}</div>
            ${item.message ? `
              <div class="negotiation-message">${escapeHtml(item.message)}</div>
            ` : ''}
          </div>
        </div>
      `).join('');
    }

    modal.style.display = 'block';
  }

  // Cerrar modal
  function closeHistoryModal() {
    document.getElementById('historyModal').style.display = 'none';
  }

  // Cerrar modal al hacer click fuera
  window.onclick = function(event) {
    const modal = document.getElementById('historyModal');
    if (event.target === modal) {
      modal.style.display = 'none';
    }
  };

  // Aceptar oferta
  async function acceptOffer(offerId) {
    const result = await Swal.fire({
      title: '¿Aceptar oferta?',
      text: 'El comprador será notificado y podrá proceder al pago',
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#4CAF50',
      cancelButtonColor: '#999',
      confirmButtonText: 'Sí, aceptar',
      cancelButtonText: 'Cancelar'
    });

    if (result.isConfirmed) {
      try {
        const formData = new FormData();
        formData.append('action', 'accept_product_offer');
        formData.append('offer_id', offerId);

        const response = await fetch('shop-negotiations-backend.php', {
          method: 'POST',
          body: formData
        });

        const data = await response.json();

        if (data.success) {
          Swal.fire('¡Aceptada!', data.message, 'success');
          loadRequests();
        } else {
          showError(data.error);
        }
      } catch (error) {
        console.error('Error:', error);
        showError('Error al aceptar oferta');
      }
    }
  }

  // Rechazar oferta
  async function rejectOffer(offerId) {
    const result = await Swal.fire({
      title: '¿Rechazar oferta?',
      text: 'Esta acción no se puede deshacer',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#f44336',
      cancelButtonColor: '#999',
      confirmButtonText: 'Sí, rechazar',
      cancelButtonText: 'Cancelar'
    });

    if (result.isConfirmed) {
      try {
        const formData = new FormData();
        formData.append('action', 'reject_product_offer');
        formData.append('offer_id', offerId);

        const response = await fetch('shop-negotiations-backend.php', {
          method: 'POST',
          body: formData
        });

        const data = await response.json();

        if (data.success) {
          Swal.fire('Rechazada', data.message, 'success');
          loadRequests();
        } else {
          showError(data.error);
        }
      } catch (error) {
        console.error('Error:', error);
        showError('Error al rechazar oferta');
      }
    }
  }

  // Contraofertear
  async function counterOffer(offerId) {
    const { value: formValues } = await Swal.fire({
      title: 'Hacer Contraoferta',
      html:
        '<input id="counterPrice" class="swal2-input" placeholder="Precio propuesto" type="number" step="0.01" required>' +
        '<textarea id="counterMessage" class="swal2-textarea" placeholder="Mensaje opcional"></textarea>',
      focusConfirm: false,
      showCancelButton: true,
      confirmButtonText: 'Enviar',
      cancelButtonText: 'Cancelar',
      preConfirm: () => {
        const price = document.getElementById('counterPrice').value;
        const message = document.getElementById('counterMessage').value;

        if (!price || parseFloat(price) <= 0) {
          Swal.showValidationMessage('Ingresa un precio válido');
          return false;
        }

        return { price, message };
      }
    });

    if (formValues) {
      try {
        const formData = new FormData();
        formData.append('action', 'product_counteroffer_seller');
        formData.append('offer_id', offerId);
        formData.append('proposed_price', formValues.price);
        formData.append('message', formValues.message);

        const response = await fetch('shop-negotiations-backend.php', {
          method: 'POST',
          body: formData
        });

        const data = await response.json();

        if (data.success) {
          Swal.fire('¡Enviada!', data.message, 'success');
          loadRequests();
        } else {
          showError(data.error);
        }
      } catch (error) {
        console.error('Error:', error);
        showError('Error al enviar contraoferta');
      }
    }
  }

  // Utilidades
  function getStatusClass(status, negotiationCount) {
    if (status === 'pending' && parseInt(negotiationCount || 0) > 0) return 'status-negotiating';
    return `status-${status}`;
  }

  function getStatusLabel(status, negotiationCount) {
    if (status === 'pending' && parseInt(negotiationCount || 0) > 0) return 'En Negociación';
    const labels = {
      'pending': 'Pendiente',
      'accepted': 'Aceptada',
      'rejected': 'Rechazada',
      'expired': 'Expirada'
    };
    return labels[status] || status;
  }

  function formatPrice(price, currency) {
    return new Intl.NumberFormat('es-ES', {
      style: 'currency',
      currency: currency || 'EUR'
    }).format(price);
  }

  function formatDate(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffTime = Math.abs(now - date);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

    if (diffDays === 0) return 'Hoy';
    if (diffDays === 1) return 'Ayer';
    if (diffDays < 7) return `Hace ${diffDays} días`;

    return date.toLocaleDateString('es-ES', { day: 'numeric', month: 'short', year: 'numeric' });
  }

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function showError(message) {
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: message
    });
  }
</script>

</body>
</html>
