<?php
/**
 * shop-my-sales.php - Mis Ventas (órdenes que compradores han hecho de mis productos)
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
  <title>Mis Ventas | SendVialo Shop</title>
  <link rel="stylesheet" href="../css/estilos.css"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.3.0/css/all.min.css"/>
  <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

  <style>
    :root {
      --primary: #41ba0d;
      --primary-dark: #2d8518;
      --primary-soft: #f1fcf0;
      --danger: #ef4444;
      --warning: #f59e0b;
      --info: #3b82f6;
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
    .sales-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
      gap: 25px;
    }

    /* TARJETA */
    .sale-card {
      background: white;
      border-radius: 24px;
      border: 1px solid #e2e8f0;
      overflow: hidden;
      transition: all 0.3s ease;
    }
    .sale-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 20px 40px -12px rgba(0,0,0,0.1);
      border-color: var(--primary);
    }

    .card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 20px;
      border-bottom: 1px solid #f1f5f9;
      background: var(--primary-soft);
    }

    .order-number {
      font-size: 13px;
      font-weight: 800;
      color: var(--primary);
    }

    .order-date {
      font-size: 12px;
      color: var(--slate-400);
    }

    .status-badge {
      padding: 6px 14px;
      border-radius: 10px;
      font-size: 11px;
      font-weight: 800;
      text-transform: uppercase;
    }
    .status-badge.pending { background: var(--warning); color: white; }
    .status-badge.confirmed { background: var(--info); color: white; }
    .status-badge.paid { background: #10b981; color: white; }
    .status-badge.shipped { background: #8b5cf6; color: white; }
    .status-badge.delivered { background: var(--primary); color: white; }
    .status-badge.cancelled { background: var(--danger); color: white; }

    .card-body {
      padding: 20px;
    }

    /* BUYER INFO */
    .buyer-info {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 15px;
      background: #f8fafc;
      border-radius: 14px;
      margin-bottom: 15px;
    }
    .buyer-avatar {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid var(--primary);
    }
    .buyer-details {
      flex: 1;
    }
    .buyer-label {
      font-size: 10px;
      font-weight: 800;
      color: var(--slate-400);
      text-transform: uppercase;
      margin-bottom: 4px;
    }
    .buyer-name {
      font-size: 15px;
      font-weight: 700;
      color: var(--slate-900);
    }
    .buyer-username {
      font-size: 12px;
      color: var(--slate-600);
    }

    .product-row {
      display: flex;
      gap: 15px;
      padding: 15px 0;
      border-bottom: 1px solid #f1f5f9;
    }
    .product-row:last-child { border-bottom: none; }

    .product-img {
      width: 70px;
      height: 70px;
      border-radius: 12px;
      object-fit: cover;
      background: #f3f4f6;
    }

    .product-info {
      flex: 1;
    }

    .product-name {
      font-size: 14px;
      font-weight: 700;
      color: var(--slate-900);
      margin-bottom: 5px;
    }

    .product-qty {
      font-size: 12px;
      color: var(--slate-400);
    }

    .product-price {
      font-size: 16px;
      font-weight: 800;
      color: var(--primary);
      text-align: right;
    }
    .product-price span {
      font-size: 11px;
    }

    .card-footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 20px;
      background: var(--primary-soft);
      border-top: 1px solid #e2e8f0;
    }

    .total-label {
      font-size: 12px;
      font-weight: 700;
      color: var(--slate-600);
      text-transform: uppercase;
    }
    .total-amount {
      font-size: 28px;
      font-weight: 900;
      color: var(--primary);
    }
    .total-amount span {
      font-size: 14px;
    }

    .card-actions {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }
    .btn-action {
      padding: 12px 18px;
      border-radius: 12px;
      font-weight: 700;
      font-size: 13px;
      cursor: pointer;
      transition: all 0.2s ease;
      border: none;
      display: flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
    }
    .btn-chat {
      background: white;
      color: var(--slate-600);
      border: 1px solid #e2e8f0;
    }
    .btn-chat:hover { background: #f1f5f9; }

    .btn-qr {
      background: var(--primary);
      color: white;
    }
    .btn-qr:hover { background: var(--primary-dark); }

    .btn-ship {
      background: #8b5cf6;
      color: white;
    }
    .btn-ship:hover { background: #7c3aed; }

    /* EMPTY */
    .empty-state {
      text-align: center;
      padding: 80px 20px;
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
      .sales-grid { grid-template-columns: 1fr; }
      .card-footer { flex-direction: column; gap: 15px; text-align: center; }
      .card-actions { width: 100%; justify-content: center; }
    }
  </style>
</head>
<body>

<?php include 'header2.php'; ?>

<div class="container">
  <div class="header-section">
    <h1>Mis Ventas</h1>
    <p>Pedidos de compradores en tus productos. Gestiona entregas, comunícate con compradores y confirma con código QR.</p>
  </div>

  <div class="tabs-wrapper">
    <button class="tab active" onclick="changeTab('all', this)">
      Todas <span class="tab-badge" id="count-all">0</span>
    </button>
    <button class="tab" onclick="changeTab('pending', this)">
      Pendientes <span class="tab-badge" id="count-pending">0</span>
    </button>
    <button class="tab" onclick="changeTab('paid', this)">
      Pagadas <span class="tab-badge" id="count-paid">0</span>
    </button>
    <button class="tab" onclick="changeTab('shipped', this)">
      Enviadas <span class="tab-badge" id="count-shipped">0</span>
    </button>
    <button class="tab" onclick="changeTab('delivered', this)">
      Entregadas <span class="tab-badge" id="count-delivered">0</span>
    </button>
  </div>

  <div class="sales-grid" id="sales-grid"></div>

  <div id="loading">
    <i class="fas fa-circle-notch fa-spin fa-2x" style="color: var(--primary);"></i>
  </div>

  <div class="empty-state" id="empty-state" style="display:none;">
    <i class="fas fa-store"></i>
    <h3>No tienes ventas</h3>
    <p>Cuando alguien compre tus productos, aparecerán aquí</p>
    <a href="shop-manage-products.php">
      <i class="fas fa-plus-circle"></i> Publicar productos
    </a>
  </div>
</div>

<script>
let sales = [];
let currentFilter = 'all';

async function cargarVentas() {
  try {
    const res = await fetch('shop-actions.php?action=get_seller_orders');
    const data = await res.json();
    console.log('Ventas:', data);
    if (data.success) {
      sales = data.orders || [];
      updateCounts();
      renderSales();
    } else {
      console.error('Error:', data.error);
    }
  } catch (e) {
    console.error(e);
  }
  document.getElementById('loading').style.display = 'none';
}

function updateCounts() {
  document.getElementById('count-all').textContent = sales.length;
  document.getElementById('count-pending').textContent = sales.filter(s => s.status === 'pending' || s.status === 'confirmed').length;
  document.getElementById('count-paid').textContent = sales.filter(s => s.status === 'paid').length;
  document.getElementById('count-shipped').textContent = sales.filter(s => s.status === 'shipped').length;
  document.getElementById('count-delivered').textContent = sales.filter(s => s.status === 'delivered').length;
}

function changeTab(filter, btn) {
  document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
  currentFilter = filter;
  renderSales();
}

function getStatusClass(status) {
  const map = { pending: 'pending', confirmed: 'confirmed', paid: 'paid', shipped: 'shipped', delivered: 'delivered', cancelled: 'cancelled' };
  return map[status] || 'pending';
}

function getStatusText(status) {
  const map = { pending: 'Pendiente', confirmed: 'Confirmado', paid: 'Pagado', shipped: 'Enviado', delivered: 'Entregado', cancelled: 'Cancelado' };
  return map[status] || 'Pendiente';
}

function formatDate(dateStr) {
  const date = new Date(dateStr);
  return date.toLocaleDateString('es-ES', { day: 'numeric', month: 'short', year: 'numeric' });
}

function renderSales() {
  let list = sales;

  if (currentFilter === 'pending') {
    list = sales.filter(s => s.status === 'pending' || s.status === 'confirmed');
  } else if (currentFilter === 'paid') {
    list = sales.filter(s => s.status === 'paid');
  } else if (currentFilter === 'shipped') {
    list = sales.filter(s => s.status === 'shipped');
  } else if (currentFilter === 'delivered') {
    list = sales.filter(s => s.status === 'delivered');
  }

  const grid = document.getElementById('sales-grid');
  const emptyState = document.getElementById('empty-state');

  if (list.length === 0) {
    grid.innerHTML = '';
    emptyState.style.display = 'block';
    return;
  }

  emptyState.style.display = 'none';

  grid.innerHTML = list.map(order => {
    const buyerAvatar = order.buyer_avatar_id > 0
      ? `../mostrar_imagen.php?id=${order.buyer_avatar_id}`
      : `https://ui-avatars.com/api/?name=${encodeURIComponent(order.buyer_name || 'Comprador')}&background=41ba0d&color=fff`;

    // Determinar qué botones mostrar según el estado
    let actionButtons = `
      <a href="shop-chat.php?order=${order.id}" class="btn-action btn-chat">
        <i class="fas fa-comments"></i> Chat
      </a>
    `;

    // Si está pagado, mostrar botón para marcar como enviado
    if (order.status === 'paid') {
      actionButtons += `
        <button class="btn-action btn-ship" onclick="marcarEnviado(${order.id})">
          <i class="fas fa-shipping-fast"></i> Marcar enviado
        </button>
      `;
    }

    // Si está enviado o pagado, mostrar QR para que el comprador escanee
    if (order.status === 'shipped' || order.status === 'paid') {
      actionButtons += `
        <a href="shop-verificacion-qr-producto.php?order=${order.id}&mode=seller" class="btn-action btn-qr">
          <i class="fas fa-qrcode"></i> Mostrar QR
        </a>
      `;
    }

    return `
      <div class="sale-card">
        <div class="card-header">
          <div>
            <div class="order-number">Pedido #${order.id}</div>
            <div class="order-date">${formatDate(order.created_at)}</div>
          </div>
          <div class="status-badge ${getStatusClass(order.status)}">${getStatusText(order.status)}</div>
        </div>

        <div class="card-body">
          <div class="buyer-info">
            <img src="${buyerAvatar}" class="buyer-avatar" alt="${order.buyer_name || 'Comprador'}">
            <div class="buyer-details">
              <div class="buyer-label">Comprador</div>
              <div class="buyer-name">${order.buyer_name || 'Usuario'}</div>
              <div class="buyer-username">@${order.buyer_username || 'usuario'}</div>
            </div>
          </div>

          ${(order.items || []).map(item => `
            <div class="product-row">
              <img src="${item.image || 'https://via.placeholder.com/70'}" alt="${item.product_name}" class="product-img">
              <div class="product-info">
                <div class="product-name">${item.product_name}</div>
                <div class="product-qty">Cantidad: ${item.quantity}</div>
              </div>
              <div class="product-price">${Math.floor(item.subtotal)}<span>${item.currency}</span></div>
            </div>
          `).join('')}
        </div>

        <div class="card-footer">
          <div>
            <div class="total-label">Tu ganancia</div>
            <div class="total-amount">${Math.floor(order.seller_subtotal || order.total_amount)}<span>${order.currency}</span></div>
          </div>
          <div class="card-actions">
            ${actionButtons}
          </div>
        </div>
      </div>
    `;
  }).join('');
}

async function marcarEnviado(orderId) {
  const result = await Swal.fire({
    title: '¿Marcar como enviado?',
    text: 'El comprador será notificado y podrá usar el código QR para confirmar la entrega',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#8b5cf6',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Sí, marcar enviado',
    cancelButtonText: 'Cancelar'
  });

  if (result.isConfirmed) {
    try {
      const res = await fetch('shop-actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=update_order_status&order_id=${orderId}&status=shipped`
      });
      const data = await res.json();

      if (data.success) {
        Swal.fire({
          icon: 'success',
          title: '¡Marcado como enviado!',
          text: 'El comprador ha sido notificado',
          confirmButtonColor: '#41ba0d'
        });
        cargarVentas();
      } else {
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: data.error || 'No se pudo actualizar el estado'
        });
      }
    } catch (e) {
      console.error(e);
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'Error de conexión'
      });
    }
  }
}

document.addEventListener('DOMContentLoaded', cargarVentas);
</script>
</body>
</html>
