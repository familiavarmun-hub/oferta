<?php
// shop-orders.php - Página de pedidos del usuario en SendVialo Shop
session_start();

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

// Incluir sistema de insignias unificado
require_once 'insignias1.php';
require_once 'config.php';

// Variables de usuario para JavaScript
$user_id = $_SESSION['usuario_id'];
$user_name = $_SESSION['usuario_nombre'] ?? $_SESSION['full_name'] ?? 'Usuario';

// Obtener información completa del usuario con valoraciones
$user_profile = obtenerPerfilCompletoUsuario($user_id, $conexion);
$rating_data = $user_profile['rating_data'] ?? ['average_rating' => 0, 'total_ratings' => 0, 'rating_display' => 'N/A', 'badge_type' => 'basic'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Pedidos - SendVialo Shop</title>
    <link rel="stylesheet" href="../css/estilos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.3.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="icon" href="../Imagenes/globo5.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <?php incluirEstilosInsignias(); ?>

    <style>
/* Reset y configuración base */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    background: #f8f9fa;
    color: #2d3748;
    line-height: 1.6;
}

.orders-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

/* Header de la página */
.page-header {
    background: linear-gradient(135deg, #e8f8e4 0%, #d4f1cd 100%);
    color: #1a1a1a;
    padding: 60px 40px;
    margin-bottom: 40px;
    border-radius: 20px;
    position: relative;
    overflow: hidden;
}

.page-header::before {
    content: '';
    position: absolute;
    top: -50px;
    right: -50px;
    width: 200px;
    height: 200px;
    background: #42ba25;
    opacity: 0.05;
    border-radius: 50%;
}

.page-header h1 {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 10px;
    position: relative;
    z-index: 1;
}

.page-header p {
    font-size: 1.1rem;
    opacity: 0.8;
    position: relative;
    z-index: 1;
}

/* Filtros de estado */
.status-filters {
    display: flex;
    gap: 15px;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.filter-btn {
    padding: 12px 24px;
    border: 2px solid #42ba25;
    background: white;
    color: #42ba25;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.95rem;
}

.filter-btn:hover {
    background: #f0fdf4;
    transform: translateY(-2px);
}

.filter-btn.active {
    background: #42ba25;
    color: white;
}

/* Sección de pedidos */
.orders-section {
    background: white;
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.orders-section h2 {
    font-size: 1.8rem;
    margin-bottom: 25px;
    color: #1a1a1a;
    display: flex;
    align-items: center;
    gap: 10px;
}

.orders-section h2 i {
    color: #42ba25;
}

/* Lista de pedidos */
.orders-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.order-card {
    border: 2px solid #e2e8f0;
    border-radius: 16px;
    padding: 25px;
    transition: all 0.3s ease;
    background: white;
}

.order-card:hover {
    border-color: #42ba25;
    box-shadow: 0 4px 15px rgba(66, 186, 37, 0.1);
    transform: translateY(-2px);
}

.order-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.order-number {
    font-size: 1.2rem;
    font-weight: 700;
    color: #1a1a1a;
}

.order-date {
    font-size: 0.9rem;
    color: #64748b;
}

.order-status {
    padding: 8px 16px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
}

.status-pending {
    background: #fef3c7;
    color: #92400e;
}

.status-confirmed {
    background: #dbeafe;
    color: #1e40af;
}

.status-paid {
    background: #ccfbf1;
    color: #065f46;
}

.status-shipped {
    background: #e0e7ff;
    color: #3730a3;
}

.status-delivered {
    background: #dcfce7;
    color: #15803d;
}

.status-cancelled {
    background: #fee2e2;
    color: #991b1b;
}

.status-refunded {
    background: #fce7f3;
    color: #831843;
}

.order-items {
    margin-bottom: 20px;
}

.order-item {
    display: flex;
    gap: 15px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 12px;
    margin-bottom: 10px;
}

.item-image {
    width: 80px;
    height: 80px;
    border-radius: 10px;
    object-fit: cover;
    background: #e2e8f0;
}

.item-info {
    flex: 1;
}

.item-name {
    font-weight: 600;
    font-size: 1rem;
    color: #1a1a1a;
    margin-bottom: 5px;
}

.item-details {
    font-size: 0.9rem;
    color: #64748b;
}

.item-price {
    font-weight: 700;
    color: #42ba25;
    font-size: 1.1rem;
}

.order-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 20px;
    border-top: 2px solid #e2e8f0;
    flex-wrap: wrap;
    gap: 15px;
}

.order-total {
    font-size: 1.3rem;
    font-weight: 700;
    color: #1a1a1a;
}

.order-total span {
    color: #42ba25;
}

.order-actions {
    display: flex;
    gap: 10px;
}

.action-btn {
    padding: 10px 20px;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.9rem;
}

.btn-primary {
    background: #42ba25;
    color: white;
}

.btn-primary:hover {
    background: #37a01f;
    transform: translateY(-2px);
}

.btn-secondary {
    background: #e2e8f0;
    color: #475569;
}

.btn-secondary:hover {
    background: #cbd5e1;
}

.btn-danger {
    background: #fee2e2;
    color: #991b1b;
}

.btn-danger:hover {
    background: #fecaca;
}

/* Estado vacío */
.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-state i {
    font-size: 4rem;
    color: #cbd5e1;
    margin-bottom: 20px;
}

.empty-state h3 {
    font-size: 1.5rem;
    color: #475569;
    margin-bottom: 10px;
}

.empty-state p {
    color: #64748b;
    margin-bottom: 25px;
}

.btn-shop {
    display: inline-block;
    padding: 12px 30px;
    background: #42ba25;
    color: white;
    text-decoration: none;
    border-radius: 12px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-shop:hover {
    background: #37a01f;
    transform: translateY(-2px);
}

/* Loading state */
.loading-state {
    text-align: center;
    padding: 60px 20px;
}

.loading-spinner {
    width: 50px;
    height: 50px;
    border: 4px solid #e2e8f0;
    border-top: 4px solid #42ba25;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 20px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Responsive */
@media (max-width: 768px) {
    .page-header h1 {
        font-size: 2rem;
    }

    .status-filters {
        overflow-x: auto;
        flex-wrap: nowrap;
        -webkit-overflow-scrolling: touch;
    }

    .filter-btn {
        white-space: nowrap;
    }

    .order-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .order-item {
        flex-direction: column;
        text-align: center;
    }

    .item-image {
        margin: 0 auto;
    }

    .order-footer {
        flex-direction: column;
        align-items: stretch;
    }

    .order-actions {
        width: 100%;
        flex-direction: column;
    }

    .action-btn {
        width: 100%;
    }
}
    </style>
</head>
<body>

<?php if (file_exists('shop-header.php')) include 'shop-header.php'; ?>

<div class="orders-container">
    <!-- Header de la página -->
    <div class="page-header">
        <h1><i class="fas fa-shopping-bag"></i> Mis Pedidos</h1>
        <p>Gestiona y realiza seguimiento de todos tus pedidos en SendVialo Shop</p>
    </div>

    <!-- Filtros de estado -->
    <div class="status-filters">
        <button class="filter-btn active" data-status="all">
            <i class="fas fa-list"></i> Todos
        </button>
        <button class="filter-btn" data-status="pending">
            <i class="fas fa-clock"></i> Pendientes
        </button>
        <button class="filter-btn" data-status="confirmed">
            <i class="fas fa-check-circle"></i> Confirmados
        </button>
        <button class="filter-btn" data-status="paid">
            <i class="fas fa-credit-card"></i> Pagados
        </button>
        <button class="filter-btn" data-status="shipped">
            <i class="fas fa-shipping-fast"></i> Enviados
        </button>
        <button class="filter-btn" data-status="delivered">
            <i class="fas fa-box-check"></i> Entregados
        </button>
    </div>

    <!-- Sección de pedidos -->
    <div class="orders-section">
        <h2>
            <i class="fas fa-receipt"></i>
            <span id="section-title">Todos los pedidos</span>
        </h2>

        <!-- Loading state -->
        <div id="loading-state" class="loading-state">
            <div class="loading-spinner"></div>
            <p>Cargando tus pedidos...</p>
        </div>

        <!-- Lista de pedidos -->
        <div id="orders-list" class="orders-list" style="display: none;">
            <!-- Los pedidos se cargarán dinámicamente aquí -->
        </div>

        <!-- Estado vacío -->
        <div id="empty-state" class="empty-state" style="display: none;">
            <i class="fas fa-shopping-bag"></i>
            <h3>No tienes pedidos</h3>
            <p>Aún no has realizado ninguna compra en SendVialo Shop</p>
            <a href="index.php" class="btn-shop">
                <i class="fas fa-store"></i> Ir a la Tienda
            </a>
        </div>
    </div>
</div>

<script>
// Variables globales
let allOrders = [];
let currentFilter = 'all';

// Cargar pedidos al iniciar
$(document).ready(function() {
    loadOrders();

    // Event listeners para filtros
    $('.filter-btn').click(function() {
        $('.filter-btn').removeClass('active');
        $(this).addClass('active');
        currentFilter = $(this).data('status');
        filterOrders();
    });
});

// Función para cargar pedidos del usuario
function loadOrders() {
    $('#loading-state').show();
    $('#orders-list').hide();
    $('#empty-state').hide();

    $.ajax({
        url: 'shop-actions.php',
        method: 'POST',
        data: {
            action: 'get_user_orders'
        },
        dataType: 'json',
        success: function(response) {
            console.log('Orders response:', response);

            if (response.success) {
                allOrders = response.orders || [];
                displayOrders();
            } else {
                console.error('Error al cargar pedidos:', response.error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: response.error || 'No se pudieron cargar los pedidos'
                });
                showEmptyState();
            }
        },
        error: function(xhr, status, error) {
            console.error('Error AJAX:', error);
            console.error('Response:', xhr.responseText);
            Swal.fire({
                icon: 'error',
                title: 'Error de conexión',
                text: 'No se pudo conectar con el servidor'
            });
            showEmptyState();
        },
        complete: function() {
            $('#loading-state').hide();
        }
    });
}

// Función para filtrar pedidos
function filterOrders() {
    displayOrders();
}

// Función para mostrar pedidos
function displayOrders() {
    let filteredOrders = allOrders;

    // Aplicar filtro
    if (currentFilter !== 'all') {
        filteredOrders = allOrders.filter(order => order.status === currentFilter);
    }

    // Actualizar título
    const statusTitles = {
        'all': 'Todos los pedidos',
        'pending': 'Pedidos Pendientes',
        'confirmed': 'Pedidos Confirmados',
        'paid': 'Pedidos Pagados',
        'shipped': 'Pedidos Enviados',
        'delivered': 'Pedidos Entregados',
        'cancelled': 'Pedidos Cancelados',
        'refunded': 'Pedidos Reembolsados'
    };
    $('#section-title').text(statusTitles[currentFilter] || 'Pedidos');

    // Mostrar u ocultar estados
    if (filteredOrders.length === 0) {
        showEmptyState();
    } else {
        showOrders(filteredOrders);
    }
}

// Función para mostrar estado vacío
function showEmptyState() {
    $('#orders-list').hide();
    $('#empty-state').show();
}

// Función para mostrar lista de pedidos
function showOrders(orders) {
    $('#empty-state').hide();
    $('#orders-list').show().html('');

    orders.forEach(order => {
        const orderHtml = createOrderCard(order);
        $('#orders-list').append(orderHtml);
    });
}

// Función para crear tarjeta de pedido
function createOrderCard(order) {
    const statusClass = `status-${order.status}`;
    const statusLabels = {
        'pending': 'Pendiente',
        'confirmed': 'Confirmado',
        'paid': 'Pagado',
        'shipped': 'Enviado',
        'delivered': 'Entregado',
        'cancelled': 'Cancelado',
        'refunded': 'Reembolsado'
    };

    // Crear HTML de items
    let itemsHtml = '';
    if (order.items && order.items.length > 0) {
        order.items.forEach(item => {
            const imageUrl = item.image_url || '../Imagenes/product-placeholder.jpg';
            itemsHtml += `
                <div class="order-item">
                    <img src="${imageUrl}" alt="${item.product_name}" class="item-image" onerror="this.src='../Imagenes/product-placeholder.jpg'">
                    <div class="item-info">
                        <div class="item-name">${item.product_name}</div>
                        <div class="item-details">Cantidad: ${item.quantity} | Precio unitario: ${item.unit_price} ${item.currency}</div>
                    </div>
                    <div class="item-price">${item.subtotal} ${item.currency}</div>
                </div>
            `;
        });
    }

    // Crear HTML de acciones
    let actionsHtml = '';
    if (order.status === 'pending') {
        actionsHtml = `
            <button class="action-btn btn-primary" onclick="viewOrderDetails('${order.order_number}')">
                <i class="fas fa-eye"></i> Ver Detalles
            </button>
            <button class="action-btn btn-danger" onclick="cancelOrder('${order.order_number}')">
                <i class="fas fa-times"></i> Cancelar
            </button>
        `;
    } else if (order.status === 'delivered') {
        actionsHtml = `
            <button class="action-btn btn-primary" onclick="viewOrderDetails('${order.order_number}')">
                <i class="fas fa-eye"></i> Ver Detalles
            </button>
            <button class="action-btn btn-secondary" onclick="rateOrder('${order.order_number}')">
                <i class="fas fa-star"></i> Valorar
            </button>
        `;
    } else {
        actionsHtml = `
            <button class="action-btn btn-primary" onclick="viewOrderDetails('${order.order_number}')">
                <i class="fas fa-eye"></i> Ver Detalles
            </button>
        `;
    }

    const orderDate = new Date(order.created_at).toLocaleDateString('es-ES', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });

    return `
        <div class="order-card" data-status="${order.status}">
            <div class="order-header">
                <div>
                    <div class="order-number">Pedido #${order.order_number}</div>
                    <div class="order-date">${orderDate}</div>
                </div>
                <div class="order-status ${statusClass}">
                    ${statusLabels[order.status] || order.status}
                </div>
            </div>

            <div class="order-items">
                ${itemsHtml}
            </div>

            <div class="order-footer">
                <div class="order-total">
                    Total: <span>${order.total_amount} ${order.currency}</span>
                </div>
                <div class="order-actions">
                    ${actionsHtml}
                </div>
            </div>
        </div>
    `;
}

// Función para ver detalles de un pedido
function viewOrderDetails(orderNumber) {
    const order = allOrders.find(o => o.order_number === orderNumber);
    if (!order) return;

    let itemsListHtml = '';
    if (order.items && order.items.length > 0) {
        order.items.forEach(item => {
            itemsListHtml += `
                <li style="margin-bottom: 10px;">
                    <strong>${item.product_name}</strong><br>
                    Cantidad: ${item.quantity} × ${item.unit_price} ${item.currency} = ${item.subtotal} ${item.currency}
                </li>
            `;
        });
    }

    Swal.fire({
        title: `Pedido #${order.order_number}`,
        html: `
            <div style="text-align: left;">
                <p><strong>Estado:</strong> ${order.status}</p>
                <p><strong>Fecha:</strong> ${new Date(order.created_at).toLocaleString('es-ES')}</p>
                <p><strong>Total:</strong> ${order.total_amount} ${order.currency}</p>
                ${order.tracking_number ? `<p><strong>Número de seguimiento:</strong> ${order.tracking_number}</p>` : ''}
                ${order.notes ? `<p><strong>Notas:</strong> ${order.notes}</p>` : ''}
                <br>
                <strong>Productos:</strong>
                <ul style="margin-top: 10px;">
                    ${itemsListHtml}
                </ul>
            </div>
        `,
        icon: 'info',
        confirmButtonText: 'Cerrar',
        confirmButtonColor: '#42ba25'
    });
}

// Función para cancelar un pedido
function cancelOrder(orderNumber) {
    Swal.fire({
        title: '¿Cancelar pedido?',
        text: 'Esta acción no se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, cancelar',
        cancelButtonText: 'No, mantener'
    }).then((result) => {
        if (result.isConfirmed) {
            // TODO: Implementar lógica de cancelación
            Swal.fire({
                icon: 'info',
                title: 'Función en desarrollo',
                text: 'La cancelación de pedidos estará disponible pronto'
            });
        }
    });
}

// Función para valorar un pedido
function rateOrder(orderNumber) {
    // TODO: Implementar sistema de valoraciones
    Swal.fire({
        icon: 'info',
        title: 'Función en desarrollo',
        text: 'El sistema de valoraciones estará disponible pronto'
    });
}
</script>

</body>
</html>