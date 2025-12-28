<?php
/**
 * shop-my-proposals-premium.php - Versión Final Corregida
 * ✅ Tarjetas ANCHAS en móvil | ✅ Username CORREGIDO desde API
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
  <title>Mis Propuestas | SendVialo</title>
  <link rel="stylesheet" href="../css/estilos.css"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
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

    body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: var(--slate-900); margin: 0; -webkit-font-smoothing: antialiased; }
    .container { max-width: 1400px; margin: 0 auto; padding: 100px 20px 60px; }

    .header-section { margin-bottom: 40px; }
    .header-section h1 { font-size: 38px; font-weight: 900; letter-spacing: -1.5px; margin: 0; }
    .header-section p { font-size: 16px; color: var(--slate-600); margin-top: 8px; }

    .tabs-wrapper { background: white; padding: 6px; border-radius: 20px; display: inline-flex; gap: 4px; box-shadow: 0 4px 6px rgba(0,0,0,0.04); border: 1px solid #e2e8f0; margin-bottom: 30px; overflow-x: auto; max-width: 100%; }
    .tab { padding: 10px 18px; border-radius: 14px; border: none; background: transparent; font-weight: 700; font-size: 13px; color: var(--slate-600); cursor: pointer; display: flex; align-items: center; gap: 8px; white-space: nowrap; transition: 0.2s; }
    .tab.active { background: var(--slate-900); color: white; }

    /* GRID MÁS ANCHO */
 .proposals-grid { 
  display: grid; 
  grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); 
  gap: 25px; 
}
    /* TARJETA MÁS ANCHA */
.proposal-card { 
  background: white; 
  border-radius: 24px; 
  border: 1px solid #e2e8f0; 
  overflow: hidden; 
  display: flex; 
  flex-direction: column; 
  transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.4s ease; 
  box-shadow: 0 4px 10px rgba(0,0,0,0.02); 
  width: 100%; 
  position: relative;
}
 .proposal-card:hover { 
  transform: translateY(-10px) scale(1.01);
  box-shadow: 0 30px 60px -15px rgba(0,0,0,0.15); 
  border-color: var(--primary);
}

    .card-img-zone { position: relative; height: 200px; background: #f3f4f6; overflow: hidden; }
    .card-img-zone img { width: 100%; height: 100%; object-fit: cover; transition: 0.5s; }
    
    .proposal-card:hover .card-img-zone img { transform: scale(1.05); }

    .interaction-pills { position: absolute; top: 12px; right: 12px; display: flex; gap: 6px; z-index: 10; }
    .pill { background: white; padding: 6px 12px; border-radius: 12px; font-size: 11px; font-weight: 800; display: flex; align-items: center; gap: 5px; box-shadow: 0 4px 10px rgba(0,0,0,0.15); text-decoration: none; color: inherit; }
    .pill i.fa-heart { color: var(--danger); }
    .pill i.fa-comments { color: var(--primary); }

    .status-badge { position: absolute; top: 12px; left: 12px; padding: 6px 14px; border-radius: 10px; font-size: 10px; font-weight: 800; text-transform: uppercase; background: rgba(255,255,255,0.9); box-shadow: 0 2px 5px rgba(0,0,0,0.1); }

    .card-body { padding: 24px; flex-grow: 1; display: flex; flex-direction: column; }
    .proposal-title { font-size: 19px; font-weight: 800; color: var(--slate-900); margin: 0 0 18px 0; line-height: 1.3; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }

    /* INFO TILES - TEXTO COMPLETO */
    .info-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px; }
    .info-tile { 
      background: #f1f5f9; 
      border-radius: 16px; 
      padding: 12px; 
      display: flex; 
      align-items: center; 
      gap: 10px; 
      border: 1px solid #f1f5f9; 
      min-width: 0; 
      overflow: hidden;
    }
    .info-tile i { 
      font-size: 14px; 
      color: var(--slate-400); 
      background: white; 
      width: 32px; 
      height: 32px; 
      flex-shrink: 0; 
      display: flex; 
      align-items: center; 
      justify-content: center; 
      border-radius: 50%; 
      box-shadow: 0 2px 4px rgba(0,0,0,0.05); 
    }
    .tile-data { 
      display: flex; 
      flex-direction: column; 
      min-width: 0;
      overflow: hidden;
      flex: 1;
    }
    .tile-label { 
      font-size: 9px; 
      font-weight: 800; 
      color: var(--slate-400); 
      text-transform: uppercase; 
      white-space: nowrap;
    }
    .tile-value { 
      font-size: 12px; 
      font-weight: 800; 
      color: var(--slate-900); 
      text-transform: uppercase; 
      white-space: nowrap; 
      overflow: hidden; 
      text-overflow: ellipsis;
      width: 100%;
    }

.price-row { 
  display: flex; 
  justify-content: space-between; 
  align-items: center; 
  margin-top: auto; 
  padding: 15px 0; 
  border-top: 1px solid #f1f5f9; 
}

.price-info {
  display: flex;
  align-items: center;
  justify-content: space-between; /* 👈 CAMBIO CLAVE */
  gap: 12px;
  width: 100%; /* 👈 OCUPAR TODO EL ANCHO */
}
    .price-val { font-size: 28px; font-weight: 900; color: var(--slate-900); }
    .price-val span { font-size: 15px; color: var(--primary); margin-left: 4px; }
    .price-label { font-size: 10px; font-weight: 800; color: var(--slate-400); text-transform: uppercase; }
    /* Badge de cantidad */
/* Badge de cantidad */
/* Badge de cantidad */
.quantity-badge-proposal {
  background: #eef9e7;
  color: var(--primary);
  padding: 6px 12px;
  border-radius: 100px;
  font-size: 11px;
  font-weight: 800;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin: 0; /* Quitar margin-top */
  flex-shrink: 0;
}

.quantity-badge-proposal i {
  font-size: 10px;
}

    .btn-details { width: 100%; padding: 15px; border-radius: 16px; background: var(--slate-900); color: white; font-weight: 800; font-size: 14px; text-align: center; text-decoration: none; display: block; transition: 0.2s; border: none; cursor: pointer; }
    .btn-details:hover { background: var(--primary); transform: translateY(-2px); }

    .scan-qr-area { background: var(--primary-soft); border: 1.5px solid #dcfce7; border-radius: 20px; padding: 15px; margin-top: 15px; display: flex; align-items: center; justify-content: space-between; }
    .btn-scan { background: var(--primary); color: white; border: none; padding: 10px 18px; border-radius: 12px; font-weight: 800; font-size: 12px; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.3s; }
    .btn-scan:hover { background: var(--primary-dark); box-shadow: 0 4px 12px rgba(65, 186, 13, 0.3); }

    .requester-footer { display: flex; align-items: center; gap: 12px; margin-top: 15px; padding-top: 15px; border-top: 1px solid #f1f5f9; }
    .req-avatar { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; border: 2px solid white; box-shadow: 0 2px 6px rgba(0,0,0,0.1); flex-shrink: 0; }
    .req-name { font-size: 13px; font-weight: 700; color: var(--slate-600); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }


/* ========================================
   SOLUCIÓN DEFINITIVA - ANCHO DE TARJETAS EN MOBILE
   Reemplaza TODO el bloque @media en shop-my-proposals-premium.php
   ======================================== */

/* RESPONSIVE - TARJETAS MUY ANCHAS EN MÓVIL */
@media (max-width: 768px) {
  .container {
    padding: 85px 8px 30px !important; /* !important para forzar */
  }
  
  .proposals-grid { 
    grid-template-columns: 1fr !important; /* !important para forzar */
    gap: 20px;
    padding: 0 !important; /* Sin padding adicional */
  }
  
  .proposal-card { 
    width: 100%;
    max-width: none !important; /* Quitar cualquier max-width */
  }
}

@media (max-width: 480px) {
  .container { 
    padding: 80px 2px 20px !important; /* !important para forzar */
  }
  
  .proposals-grid { 
    grid-template-columns: 1fr !important;
    gap: 15px; 
    padding: 0 !important;
  }
  
  .proposal-card { 
    border-radius: 20px; /* Más pequeño en mobile */
    width: 100%;
    max-width: none !important;
    margin: 0 !important;
  }
  
  .proposal-title { font-size: 18px; }
  .card-img-zone { height: 180px; }
  .card-body { padding: 18px; }
  
  .tile-value {
    font-size: 11px;
  }
}

/* Si el problema persiste, agrega esto TAMBIÉN */
@media (max-width: 768px) {
  body {
    overflow-x: hidden !important;
  }
  
  .proposals-grid > * {
    max-width: 100% !important;
    width: 100% !important;
  }
}
  </style>
</head>
<body>
  <?php if (file_exists('header1.php')) include 'header1.php'; ?>

  <div class="container">
    <header class="header-section">
      <h1>Mis Propuestas</h1>
      <p>Gestiona tus ofertas como viajero y finaliza tus entregas con seguridad.</p>
    </header>

    <div class="tabs-wrapper">
      <button class="tab active" data-filter="all"><i class="fas fa-list"></i> Todas</button>
      <button class="tab" data-filter="pending"><i class="fas fa-clock"></i> Pendientes</button>
      <button class="tab" data-filter="negotiating"><i class="fas fa-comments"></i> Negociación</button>
      <button class="tab" data-filter="accepted"><i class="fas fa-check-circle"></i> Aceptadas</button>
      <button class="tab" data-filter="completed"><i class="fas fa-flag-checkered"></i> Completadas</button>
    </div>

    <div class="proposals-grid" id="proposals-grid"></div>
  </div>

  <script>
    const userId = <?php echo $user_id; ?>;
    let allProposals = [];
    let currentFilter = 'all';
    
    
    

// 🚀 VERSIÓN OPTIMIZADA - Reemplaza la función loadProposals()

async function loadProposals() {
  try {
    console.log('🔄 Cargando propuestas...');
    
    // PASO 1: Cargar propuestas
    const resProposals = await fetch(`shop-requests-actions.php?action=get_my_proposals&user_id=${userId}`);
    const dataProposals = await resProposals.json();

    if (!dataProposals.success) {
      console.error('Error al cargar propuestas');
      return;
    }

    allProposals = dataProposals.proposals || [];
    console.log('✅ Propuestas cargadas:', allProposals.length);

    if (allProposals.length === 0) {
      renderProposals(currentFilter);
      return;
    }

    // PASO 2: Cargar delivery state EN PARALELO ⚡
    const deliveryPromises = allProposals.map(async (proposal) => {
      try {
        const res = await fetch(`shop-requests-actions.php?action=get_delivery_by_proposal&proposal_id=${proposal.id}`);
        const data = await res.json();
        
        if (data.success && data.delivery) {
          proposal.delivery_state = data.delivery.delivery_state;
          proposal.payment_released = data.delivery.payment_released;
          proposal.payment_released_at = data.delivery.payment_released_at;
        } else {
          proposal.delivery_state = null;
        }
      } catch (e) {
        console.error(`Error delivery ${proposal.id}:`, e);
        proposal.delivery_state = null;
      }
    });

    await Promise.all(deliveryPromises);
    console.log('✅ Delivery states cargados');

    // PASO 3: Cargar request details EN PARALELO ⚡
    const uniqueRequestIds = [...new Set(allProposals.map(p => p.request_id))];
    
    const detailPromises = uniqueRequestIds.map(async (requestId) => {
      try {
        const res = await fetch(`shop-requests-actions.php?action=get_request_detail&id=${requestId}`);
        const data = await res.json();
        
        if (data.success && data.request) {
            
            
          // Actualizar todas las propuestas de este request
   allProposals.forEach(p => {
  if (p.request_id === requestId) {
    p.reference_images = data.request.reference_images;
    p.final_username = data.request.requester_username || 
                       data.request.username || 
                       p.requester_username || 
                       p.requester_name || 
                       'usuario';
    p.requester_avatar_id = data.request.requester_avatar_id || p.requester_avatar_id;
    p.quantity = data.request.quantity || 1; // 👈 AGREGAR ESTA LÍNEA
  }
});
          
          
        }
      } catch (e) {
        console.error(`Error request ${requestId}:`, e);
      }
    });

    await Promise.all(detailPromises);
    console.log('✅ Request details cargados');

    // PASO 4: Procesar avatares
    allProposals.forEach(proposal => {
      const username = proposal.final_username || 'usuario';
      
      if (proposal.requester_avatar_id && proposal.requester_avatar_id > 0) {
        proposal.requester_avatar = `../mostrar_imagen.php?id=${proposal.requester_avatar_id}`;
      } else {
        proposal.requester_avatar = `https://ui-avatars.com/api/?name=${encodeURIComponent(username)}&background=41ba0d&color=fff&size=40`;
      }
    });

    console.log('✅ Todo listo. Renderizando...');
    renderProposals(currentFilter);

  } catch (e) {
    console.error('❌ Error:', e);
  }
}
    
    
    

    function renderProposals(filter) {
      const grid = document.getElementById('proposals-grid');
      
      let filtered = allProposals;
      
      if (filter === 'negotiating') {
        filtered = allProposals.filter(p => p.status === 'pending' && parseInt(p.negotiation_count || 0) > 0);
      } else if (filter === 'completed') {
        filtered = allProposals.filter(p => p.delivery_state === 'delivered');
      } else if (filter === 'accepted') {
        filtered = allProposals.filter(p => p.status === 'accepted' && p.delivery_state !== 'delivered');
      } else if (filter !== 'all') {
        filtered = allProposals.filter(p => p.status === filter);
      }

      if (filtered.length === 0) {
        grid.innerHTML = `<div style="grid-column:1/-1; text-align:center; padding:80px 20px; color:#94a3b8; background:white; border-radius:32px; border:2px dashed #eee;">No se encontraron registros.</div>`;
        return;
      }

      grid.innerHTML = filtered.map(p => {
        const isCompleted = p.delivery_state === 'delivered';
        const isAccepted = p.status === 'accepted' && !isCompleted;
        const statusLabel = isCompleted ? 'Entregado' : (p.status === 'accepted' ? 'Aceptada' : p.status.toUpperCase());
        const statusColor = isCompleted ? 'var(--primary)' : (p.status === 'accepted' ? '#3b82f6' : '#94a3b8');
        
        const price = Math.floor(p.accepted_price || p.current_price || p.proposed_price);
        
        // Procesar imagen
        let firstImage = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300"%3E%3Crect fill="%23f3f4f6" width="400" height="300"/%3E%3Ctext x="50%25" y="50%25" dominant-baseline="middle" text-anchor="middle" fill="%239ca3af" font-size="24" font-family="Arial"%3ESin imagen%3C/text%3E%3C/svg%3E';
        
        if (p.reference_images && p.reference_images.length > 0) {
          firstImage = p.reference_images[0];
        }

        // USAR EL USERNAME FINAL QUE PROCESAMOS
        const username = p.final_username || 'usuario';
        
console.log(`Propuesta ${p.id}: quantity = ${p.quantity}`);
        return `
          <div class="proposal-card" data-proposal-id="${p.id}">
            <div class="card-img-zone">
              <img src="${firstImage}" alt="${p.request_title || 'Solicitud'}" onerror="this.src='https://via.placeholder.com/400x200/f1f5f9/94a3b8?text=SendVialo'">
              <div class="status-badge" style="color:${statusColor}">${statusLabel}</div>
              <div class="interaction-pills">
                <div class="pill"><i class="fas fa-heart"></i> ${p.favorite_count || 0}</div>
                <div class="pill"><i class="fas fa-file-invoice"></i> ${p.proposal_count || 0}</div>
                ${isAccepted ? `<a href="shop-chat.php?proposal_id=${p.id}" class="pill"><i class="fas fa-comments"></i> Chat</a>` : ''}
              </div>
            </div>

            <div class="card-body">
              <h2 class="proposal-title">${p.request_title || 'Sin título'}</h2>
              
              <div class="info-row">
                <div class="info-tile">
                  <i class="fas fa-shopping-bag"></i>
                  <div class="tile-data">
                    <span class="tile-label">Recojo</span>
                    <span class="tile-value" title="${p.pickup_city || 'Varios'}">${p.pickup_city || 'Varios'}</span>
                  </div>
                </div>
                <div class="info-tile">
                  <i class="fas fa-location-dot"></i>
                  <div class="tile-data">
                    <span class="tile-label">Entrega</span>
                    <span class="tile-value" title="${p.destination_city || 'N/A'}">${p.destination_city || 'N/A'}</span>
                  </div>
                </div>
              </div>



<div class="price-row">
  <div class="price-info">
    <div>
      <div class="price-val">${price} <span>${p.proposed_currency}</span></div>
      <div class="price-label">Precio Acordado</div>
    </div>
    ${p.quantity && p.quantity > 1 ? `
      <div class="quantity-badge-proposal">
        <span>${p.quantity} unidades</span>
      </div>
    ` : ''}
  </div>
</div>


              <a href="shop-request-detail.php?id=${p.request_id}" class="btn-details">Ver Detalles de la Solicitud</a>

              ${isAccepted ? `
                <div class="scan-qr-area">
                  <div style="font-size:11px; font-weight:800; color:var(--primary-dark);">LLEGUE A DESTINO</div>
                  <button class="btn-scan" onclick="location.href='shop-verificacion-qr.php?proposal_id=${p.id}'">
                    <i class="fas fa-qrcode"></i> Escanear para Entregar
                  </button>
                </div>
              ` : ''}

              <div class="requester-footer">
                <img src="${p.requester_avatar}" class="req-avatar" alt="${username}" onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(username)}&background=41ba0d&color=fff'">
                <span class="req-name">@${username}</span>
              </div>
            </div>
          </div>
        `;
      }).join('');
    }

    $('.tab').on('click', function() {
      $('.tab').removeClass('active');
      $(this).addClass('active');
      currentFilter = $(this).data('filter');
      renderProposals(currentFilter);
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
        $(`.tab[data-filter="${targetTab}"]`).addClass('active');
        currentFilter = targetTab;
        renderProposals(currentFilter);
        
        // Esperar a que se renderice, luego hacer scroll
        setTimeout(() => {
          const targetCard = document.querySelector(`.proposal-card[data-proposal-id="${cardId}"]`);
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
      loadProposals();
      handleNavigationParams();
    });
  </script>
</body>
</html>