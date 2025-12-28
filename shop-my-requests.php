<?php
/**
 * shop-my-requests-premium-final.php - Versión Mejorada
 * ✅ Estados traducidos al castellano
 * ✅ Cantidad integrada en sección de presupuesto
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
  <title>Mis Pedidos | SendVialo Premium</title>
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
      background: var(--slate-900);
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
    .requests-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
      gap: 25px;
      justify-items: center;
    }

    /* TARJETA */
    .request-card {
      background: white; 
      border-radius: 24px; 
      border: 1px solid #e2e8f0;
      overflow: hidden; 
      display: flex; 
      flex-direction: column;
      transition: all 0.3s ease; 
      box-shadow: 0 4px 10px rgba(0,0,0,0.03);
      position: relative;
      width: 100%;
      max-width: 450px;
    }
    .request-card:hover { box-shadow: 0 20px 25px -5px rgba(0,0,0,0.08); transform: translateY(-4px); }

    .card-img-zone { position: relative; height: 180px; background: #f3f4f6; }
    .card-img-zone img { width: 100%; height: 100%; object-fit: cover; }

    .interaction-pills { position: absolute; top: 12px; right: 12px; display: flex; gap: 6px; z-index: 10; }
    .pill { 
      background: white; 
      padding: 5px 10px; 
      border-radius: 10px; 
      font-size: 11px; 
      font-weight: 800; 
      display: flex; 
      align-items: center; 
      gap: 5px; 
      box-shadow: 0 2px 5px rgba(0,0,0,0.1); 
      text-decoration: none; 
      color: inherit; 
    }
    .pill i.fa-heart { color: var(--danger); }
    .pill i.fa-comments { color: var(--primary); }

    .card-body { padding: 20px; flex-grow: 1; }
    .product-name { font-size: 18px; font-weight: 800; color: var(--slate-900); margin: 0 0 8px 0; }
    
    .product-description {
      font-size: 13px;
      color: var(--slate-600);
      line-height: 1.5;
      margin-bottom: 15px;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
      text-overflow: ellipsis;
      font-style: italic;
      font-weight: 400;
    }

    .info-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px; }
    .info-tile { background: #f8fafc; border-radius: 14px; padding: 10px 12px; display: flex; align-items: center; gap: 10px; border: 1px solid #f1f5f9; }
    .info-tile i { color: var(--slate-400); font-size: 14px; background: white; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    .tile-content { display: flex; flex-direction: column; }
    .tile-label { font-size: 9px; font-weight: 800; color: var(--slate-400); text-transform: uppercase; }
    .tile-value { font-size: 13px; font-weight: 800; color: var(--slate-900); text-transform: uppercase; }

    /* SECCIÓN QR */
    .qr-card-section { background: var(--primary-soft); border: 1.5px solid #dcfce7; border-radius: 18px; padding: 12px; display: flex; align-items: center; gap: 15px; cursor: pointer; margin-bottom: 15px; transition: 0.3s; }
    .qr-card-section:hover { background: #e8fae5; transform: scale(1.02); }
    .qr-thumb-box { width: 60px; height: 60px; background: white; border: 2px solid var(--primary); border-radius: 12px; padding: 4px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; position: relative; }
    .qr-thumb-box img { width: 100%; height: 100%; object-fit: contain; border-radius: 4px; }
    .qr-expand-icon {
      position: absolute;
      top: -5px;
      right: -5px;
      background: var(--primary);
      color: white;
      border-radius: 50%;
      width: 20px;
      height: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 10px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.2);
    }
    .qr-text-box { display: flex; flex-direction: column; }
    .qr-title { font-size: 12px; font-weight: 900; text-transform: uppercase; color: var(--slate-900); }
    .qr-subtitle { font-size: 10px; color: var(--primary); font-weight: 700; }

    /* PRESUPUESTO CON CANTIDAD INTEGRADA */
    .price-section {
      background: #f8fafc;
      border-radius: 16px;
      padding: 16px;
      margin-bottom: 15px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      border: 1px solid #e2e8f0;
    }

    .price-box {
      display: flex;
      flex-direction: column;
    }

    .price { 
      font-size: 28px; 
      font-weight: 900; 
      color: var(--slate-900);
      line-height: 1;
      margin-bottom: 4px;
    }
    .price span { 
      font-size: 15px; 
      color: var(--primary); 
      margin-left: 4px; 
      font-weight: 700;
    }
    .price-label { 
      font-size: 10px; 
      font-weight: 800; 
      color: var(--slate-400); 
      text-transform: uppercase; 
      letter-spacing: 0.5px;
    }

    .quantity-badge-inline {
      background: var(--slate-900);
      color: white;
      padding: 8px 16px;
      border-radius: 12px;
      font-size: 13px;
      font-weight: 800;
      display: flex;
      align-items: center;
      gap: 8px;
      white-space: nowrap;
    }

    .btn-manage { 
      width: 100%; 
      padding: 14px; 
      border-radius: 16px; 
      background: var(--slate-900); 
      color: white; 
      font-weight: 800; 
      font-size: 14px; 
      text-align: center; 
      text-decoration: none; 
      display: block; 
      margin-bottom: 12px; 
      transition: 0.2s; 
      border: none; 
      cursor: pointer; 
    }
    .btn-manage:hover { background: var(--primary); transform: translateY(-2px); }

    /* BOTONES DE ACCIÓN */
    .action-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .btn-action { 
      padding: 14px 18px; 
      border-radius: 14px; 
      font-weight: 800; 
      font-size: 13px; 
      border: 2px solid #e2e8f0; 
      background: white; 
      color: var(--slate-900); 
      text-align: center; 
      text-decoration: none; 
      cursor: pointer; 
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }
    .btn-action:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      border-color: var(--slate-900);
    }
    .btn-del { 
      color: var(--danger); 
      border-color: #fee2e2;
      background: #fef2f2;
    }
    .btn-del:hover {
      background: var(--danger);
      color: white;
      border-color: var(--danger);
    }

    /* MODAL QR */
    .modal-overlay { 
      position: fixed; 
      inset: 0; 
      background: rgba(15, 23, 42, 0.9); 
      backdrop-filter: blur(8px); 
      z-index: 3000; 
      display: none; 
      align-items: center; 
      justify-content: center; 
      padding: 20px; 
    }
    .modal-qr-box { 
      background: white; 
      padding: 40px; 
      border-radius: 40px; 
      text-align: center; 
      max-width: 400px; 
      width: 100%; 
    }
    .modal-qr-box img { 
      width: 100%; 
      border-radius: 20px; 
      border: 8px solid #f8fafc; 
      margin-bottom: 20px; 
    }
    .qr-download-btn {
      margin-top: 20px;
      padding: 12px 24px;
      background: var(--primary);
      color: white;
      border: none;
      border-radius: 12px;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.3s ease;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }
    .qr-download-btn:hover {
      background: #2d8518;
      transform: translateY(-2px);
    }
    .qr-close-btn {
      margin-top: 10px;
      padding: 10px 20px;
      background: #f3f4f6;
      color: #6b7280;
      border: none;
      border-radius: 10px;
      font-weight: 600;
      cursor: pointer;
      margin-left: 10px;
    }

    /* RESPONSIVE MOBILE */
    @media (max-width: 768px) {
      .container { padding: 60px 8px 30px; }
      .header-section h1 { font-size: 32px; }
      .header-section p { font-size: 14px; }
      .tabs-wrapper {
        width: calc(100% + 30px);
        margin-left: -15px;
        margin-right: -15px;
        border-radius: 0;
        border-left: none;
        border-right: none;
      }
      .requests-grid {
        grid-template-columns: 1fr;
        gap: 20px;
      }
      .request-card { max-width: 100%; }
      .price-section {
        flex-direction: column;
        gap: 12px;
        align-items: flex-start;
      }
      .quantity-badge-inline {
        width: 100%;
        justify-content: center;
      }
    }

    @media (max-width: 480px) {
      .container { padding: 50px 8px 20px; }
      .header-section { margin-bottom: 30px; }
      .header-section h1 { font-size: 28px; }
      .tabs-wrapper {
        padding: 6px;
        width: 100vw;
        margin-left: -10px;
      }
      .tab { padding: 8px 16px; font-size: 12px; }
      .requests-grid { gap: 15px; }
      .card-body { padding: 16px; }
      .product-name { font-size: 16px; }
      .product-description { font-size: 12px; }
      .price { font-size: 24px; }
      .btn-manage { padding: 12px; font-size: 13px; }
      .action-grid { grid-template-columns: 1fr; gap: 8px; }
      .btn-action { font-size: 13px; padding: 12px 16px; }
    }
  </style>
</head>
<body>

  <?php if (file_exists('header1.php')) include 'header1.php'; ?>

  <div class="container">
<header class="header-section" style="margin-top: 30px;">
  <h1>Mis Solicitudes</h1>
  <p>Administra tus pedidos, revisa propuestas y realiza el seguimiento de tus entregas.</p>
</header>

    <div class="tabs-wrapper">
      <button class="tab active" data-status="all"><i class="fas fa-list"></i> Todas <span class="tab-badge" id="badge-all">0</span></button>
      <button class="tab" data-status="open"><i class="fas fa-door-open"></i> Abiertas <span class="tab-badge" id="badge-open">0</span></button>
      <button class="tab" data-status="negotiating"><i class="fas fa-comments"></i> Negociando <span class="tab-badge" id="badge-negotiating">0</span></button>
      <button class="tab" data-status="accepted"><i class="fas fa-check-circle"></i> Aceptadas <span class="tab-badge" id="badge-accepted">0</span></button>
      
       <button class="tab" data-status="cancelled"><i class="fas fa-times-circle"></i> Rechazadas <span class="tab-badge" id="badge-cancelled">0</span></button>
      
      <button class="tab" data-status="completed"><i class="fas fa-flag-checkered"></i> Finalizadas <span class="tab-badge" id="badge-completed">0</span></button>
     
    </div>

    <div class="requests-grid" id="requests-container"></div>
  </div>

  <div id="modal-qr" class="modal-overlay" onclick="closeQRModal()">
    <div class="modal-qr-box" onclick="event.stopPropagation()">
      <div style="font-size: 10px; font-weight: 800; color: var(--primary); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">Seguridad SendVialo</div>
      <h2 style="font-weight:900; margin: 0 0 20px 0; letter-spacing: -1px;">Código de Entrega</h2>
      <img id="qr-zoom" src="" alt="QR">
      <p style="font-size:13px; color:var(--slate-600); margin-top: 20px;">Muestra este código al viajero al momento de recibir tu producto para validar la transacción.</p>
      <div>
        <button class="qr-download-btn" onclick="downloadQR()">
          <i class="fas fa-download"></i> Descargar
        </button>
        <button class="qr-close-btn" onclick="closeQRModal()">Cerrar</button>
      </div>
    </div>
  </div>

  <script>
    const userId = <?php echo $user_id; ?>;
    let myRequests = [];
    let currentFilter = 'all';
    let currentQRPath = '';
    let currentRequestId = 0;

    // Diccionario de traducción de estados
    const statusTranslations = {
      'open': 'Abierta',
      'negotiating': 'Negociando',
      'accepted': 'Aceptada',
      'completed': 'Finalizada',
      'cancelled': 'Rechazada'
    };

    // Función para traducir estados
    function translateStatus(status) {
      return statusTranslations[status] || status;
    }

    // Función para obtener color del estado
    function getStatusColor(status) {
      const colors = {
        'open': '#3b82f6',
        'negotiating': '#f59e0b',
        'accepted': '#10b981',
        'completed': 'var(--primary)',
        'cancelled': 'var(--danger)'
      };
      return colors[status] || '#64748b';
    }

    async function loadRequests() {
      try {
        const res = await fetch(`shop-requests-actions.php?action=get_my_requests&user_id=${userId}`);
        const data = await res.json();
        if (data.success) {
          myRequests = data.requests || [];
          updateBadges();
          render();
        }
      } catch (e) { console.error(e); }
    }

    function updateBadges() {
      const counts = {
        all: myRequests.length,
        open: myRequests.filter(r => r.status === 'open').length,
        negotiating: myRequests.filter(r => r.status === 'negotiating').length,
        accepted: myRequests.filter(r => r.status === 'accepted').length,
        completed: myRequests.filter(r => r.status === 'completed').length,
        cancelled: myRequests.filter(r => r.status === 'cancelled').length
      };
      Object.keys(counts).forEach(key => {
        const b = document.getElementById(`badge-${key}`);
        if (b) b.textContent = counts[key];
      });
    }

    function isAceptada(req) {
      return req.status === 'accepted' || req.status === 'completed';
    }

    function escapeHtml(text) {
      if (!text) return '';
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    function render() {
      const container = document.getElementById('requests-container');
      let filtered = (currentFilter === 'all') ? myRequests : myRequests.filter(r => r.status === currentFilter);

      if (filtered.length === 0) {
        container.innerHTML = `<div style="grid-column: 1/-1; text-align:center; padding:50px; color:var(--slate-400);">No se encontraron pedidos.</div>`;
        return;
      }

      container.innerHTML = filtered.map(req => {
        const isEditable = (req.status === 'open' || req.status === 'negotiating');
        const isAccepted = isAceptada(req);
        const statusColor = getStatusColor(req.status);
        const statusText = translateStatus(req.status);
        const quantity = parseInt(req.quantity) || 1;

        return `
          <div class="request-card" data-request-id="${req.id}">
            <div class="card-img-zone">
              <img src="${req.reference_images?.[0] || 'https://via.placeholder.com/600x400'}" alt="">
              <div class="interaction-pills">
                <div class="pill"><i class="fas fa-heart"></i> ${req.favorite_count || 0}</div>
                <div class="pill"><i class="fas fa-file-invoice"></i> ${req.proposal_count || 0}</div>
                ${isAccepted ? `<div id="chat-pill-${req.id}"></div>` : ''}
              </div>
            </div>
            <div class="card-body">
              <h2 class="product-name">${escapeHtml(req.title)}</h2>
              
              ${req.description ? `
                <p class="product-description">${escapeHtml(req.description)}</p>
              ` : ''}
              
              <div class="info-row">
                <div class="info-tile">
                  <i class="fas fa-map-marker-alt"></i>
                  <div class="tile-content">
                    <span class="tile-label">Origen</span>
                    <span class="tile-value">${escapeHtml(req.origin_country) || 'Global'}</span>
                  </div>
                </div>
                <div class="info-tile">
                  <i class="fas fa-info-circle"></i>
                  <div class="tile-content">
                    <span class="tile-label">Estado</span>
                    <span class="tile-value" style="color:${statusColor}">${statusText}</span>
                  </div>
                </div>
              </div>
              
              ${isAccepted ? `
                <div class="qr-card-section" onclick="openQR(${req.id})">
                  <div class="qr-thumb-box" id="qr-container-${req.id}">
                     <i class="fas fa-spinner fa-spin" style="color: var(--primary); font-size: 24px;"></i>
                     <div class="qr-expand-icon">
                       <i class="fas fa-expand-alt"></i>
                     </div>
                  </div>
                  <div class="qr-text-box">
                    <span class="qr-title">Validación de Entrega</span>
                    <span class="qr-subtitle">Toca para ver el código QR</span>
                  </div>
                </div>
              ` : ''}
              
              <div class="price-section">
                <div class="price-box">
                  <div class="price">${Math.floor(req.budget_amount)} <span>${req.budget_currency}</span></div>
                  <div class="price-label">Presupuesto</div>
                </div>
                ${quantity > 1 ? `
                  <div class="quantity-badge-inline">
                    ${quantity} unidades

                  </div>
                ` : ''}
              </div>
              
              <a href="shop-request-detail.php?id=${req.id}" class="btn-manage">Gestionar Solicitud</a>
              <div class="action-grid">
                ${isEditable ? `
                  <a href="shop-edit-request.php?id=${req.id}" class="btn-action">
                    <i class="fas fa-edit"></i> Editar
                  </a>
                  <button class="btn-action btn-del" onclick="deleteReq(${req.id})">
                    <i class="fas fa-trash-alt"></i> Eliminar
                  </button>
                ` : `
                  <span class="btn-action" style="opacity:0.4; grid-column: span 2; border-style:dashed;">No editable</span>
                `}
              </div>
            </div>
          </div>
        `;
      }).join('');

      filtered.forEach(req => { 
        if (isAceptada(req)) fetchDeliveryData(req.id); 
      });
    }

    async function fetchDeliveryData(requestId) {
      try {
        const resDetail = await fetch(`shop-requests-actions.php?action=get_request_detail&id=${requestId}`);
        const dataD = await resDetail.json();
        if (dataD.success && dataD.request.proposals) {
          const acc = dataD.request.proposals.find(p => p.status === 'accepted');
          if (acc) {
            const cp = document.getElementById(`chat-pill-${requestId}`);
            if(cp) cp.innerHTML = `<a href="shop-chat.php?proposal_id=${acc.id}" class="pill"><i class="fas fa-comments"></i> Chat</a>`;
          }
        }

        const resQr = await fetch(`shop-requests-actions.php?action=get_request_qr&request_id=${requestId}`);
        const dataQr = await resQr.json();
        const container = document.getElementById(`qr-container-${requestId}`);
        
        if (dataQr.success && dataQr.qr_path && container) {
          let path = dataQr.qr_path;
          path = path.replace(/^\.\.\//, '');
          path = '../' + path;
          
          const testImg = new Image();
          testImg.onload = function() {
            container.innerHTML = `
              <img src="${path}" alt="QR">
              <div class="qr-expand-icon">
                <i class="fas fa-expand-alt"></i>
              </div>
            `;
            container.dataset.fullqr = path;
          };
          testImg.onerror = function() {
            const altPath = dataQr.qr_path;
            const testImg2 = new Image();
            testImg2.onload = function() {
              container.innerHTML = `
                <img src="${altPath}" alt="QR">
                <div class="qr-expand-icon">
                  <i class="fas fa-expand-alt"></i>
                </div>
              `;
              container.dataset.fullqr = altPath;
            };
            testImg2.onerror = function() {
              container.innerHTML = `<i class="fas fa-qrcode" style="color: #ccc; font-size: 30px;"></i>`;
            };
            testImg2.src = altPath;
          };
          testImg.src = path;
        } else {
          if(container) {
            container.innerHTML = `<i class="fas fa-qrcode" style="color: #ddd; font-size: 30px;"></i>`;
          }
        }
      } catch (e) { 
        console.error('Error fetchDeliveryData:', e); 
      }
    }

    function openQR(id) {
      const c = document.getElementById(`qr-container-${id}`);
      if (c && c.dataset.fullqr) {
        currentQRPath = c.dataset.fullqr;
        currentRequestId = id;
        document.getElementById('qr-zoom').src = currentQRPath;
        document.getElementById('modal-qr').style.display = 'flex';
      } else {
        Swal.fire({
          icon: 'warning',
          title: 'QR no disponible',
          text: 'El código QR aún no ha sido generado',
          confirmButtonColor: 'var(--primary)'
        });
      }
    }

    function closeQRModal() { 
      document.getElementById('modal-qr').style.display = 'none'; 
    }

    function downloadQR() {
      if (!currentQRPath) return;
      
      const link = document.createElement('a');
      link.href = currentQRPath;
      link.download = `sendvialo-qr-${currentRequestId}.png`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      
      Swal.fire({
        icon: 'success',
        title: '¡Descargado!',
        text: 'El código QR se ha descargado correctamente',
        timer: 2000,
        showConfirmButton: false
      });
    }

    async function deleteReq(id) {
      const c = await Swal.fire({ 
        title: '¿Eliminar solicitud?',
        text: 'Esta acción no se puede deshacer',
        icon: 'warning', 
        showCancelButton: true, 
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
      });
      if(c.isConfirmed) {
        const fd = new FormData(); 
        fd.append('action', 'delete_request'); 
        fd.append('request_id', id);
        const res = await fetch('shop-requests-actions.php', { method: 'POST', body: fd });
        const d = await res.json();
        if(d.success) {
          Swal.fire('Eliminado', '', 'success');
          loadRequests();
        }
      }
    }

    $('.tab').on('click', function() {
      $('.tab').removeClass('active');
      $(this).addClass('active');
      currentFilter = $(this).data('status');
      render();
    });

    // ===== NAVEGACIÓN DESDE CHAT =====
    function handleNavigationParams() {
      const urlParams = new URLSearchParams(window.location.search);
      const targetTab = urlParams.get('tab');
      const cardId = urlParams.get('card_id');
      const shouldScroll = urlParams.get('scroll');

      if (targetTab && cardId && shouldScroll === 'true') {
        // Cambiar al tab indicado
        $('.tab').removeClass('active');
        $(`.tab[data-status="${targetTab}"]`).addClass('active');
        currentFilter = targetTab;
        
        // Esperar a que se renderice, luego hacer scroll
        setTimeout(() => {
          const targetCard = document.querySelector(`.request-card[data-request-id="${cardId}"]`);
          if (targetCard) {
            targetCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            // Highlight temporal
            targetCard.style.boxShadow = '0 0 0 4px var(--primary)';
            setTimeout(() => {
              targetCard.style.boxShadow = '';
            }, 2000);
          }
        }, 300);
      }
    }

    document.addEventListener('DOMContentLoaded', () => {
      loadRequests();
      handleNavigationParams();
    });
  </script>
</body>
</html>