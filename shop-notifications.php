<?php
/**
 * shop-notifications.php
 * Sistema de notificaciones con limpieza automática e híbrida
 * - Auto-elimina notificaciones leídas de más de 7 días
 * - Límite de 50 notificaciones por usuario
 * - Botón manual para limpiar todas las leídas
 */

session_start();
require_once '../config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['usuario_id'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificaciones - SendVialo Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
            padding-bottom: 40px;
        }

        .header {
            background: white;
            padding: 25px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header-title h1 {
            font-size: 28px;
            color: #333;
            font-weight: 700;
        }

        .header-title .icon {
            font-size: 32px;
            color: #4CAF50;
        }

        .header-actions {
            display: flex;
            gap: 15px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 14px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4CAF50, #66BB6A);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.3);
        }

        .btn-secondary {
            background: white;
            color: #666;
            border: 2px solid #e1e5e9;
        }

        .btn-secondary:hover {
            border-color: #4CAF50;
            color: #4CAF50;
        }

        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .notifications-filters {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
            justify-content: space-between;
        }

        .filter-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 10px 20px;
            border: 2px solid #e1e5e9;
            background: white;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            color: #666;
        }

        .filter-btn.active {
            background: #4CAF50;
            color: white;
            border-color: #4CAF50;
        }

        .filter-btn:hover {
            border-color: #4CAF50;
            color: #4CAF50;
        }

        .filter-btn.active:hover {
            color: white;
        }

        .cleanup-btn {
            margin-left: auto;
            display: none;
        }
        
        .cleanup-btn.visible {
            display: flex;
        }

        .notifications-list {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .notification-item {
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            gap: 15px;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }
        
        .notification-item:hover::after {
            content: 'Click para ver y marcar como leída';
            position: absolute;
            bottom: 10px;
            right: 20px;
            background: rgba(76, 175, 80, 0.9);
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
            opacity: 0;
            animation: fadeInTooltip 0.3s forwards;
            pointer-events: none;
        }
        
        .notification-item.unread:hover::after {
            opacity: 1;
        }
        
        @keyframes fadeInTooltip {
            to {
                opacity: 1;
            }
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-item:hover {
            background: #f8f9fa;
        }

        .notification-item.unread {
            background: #e8f5e9;
        }

        .notification-item.unread::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: #4CAF50;
        }

        .notification-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .notification-content {
            flex: 1;
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 8px;
        }

        .notification-title {
            font-weight: 600;
            color: #333;
            font-size: 16px;
        }

        .notification-time {
            color: #999;
            font-size: 13px;
            white-space: nowrap;
        }

        .notification-message {
            color: #666;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 10px;
        }

        .notification-actions {
            display: flex;
            gap: 10px;
            margin-top: 12px;
        }

        .notification-actions .btn {
            padding: 8px 16px;
            font-size: 13px;
        }

        .loading-state {
            text-align: center;
            padding: 80px 20px;
        }

        .loading-state i {
            font-size: 3rem;
            color: #4CAF50;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
        }

        .empty-state i {
            font-size: 80px;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .empty-state i.fa-check-circle {
            color: #4CAF50;
        }

        .empty-state h2 {
            color: #666;
            margin-bottom: 10px;
            font-size: 24px;
        }

        .empty-state p {
            color: #999;
            font-size: 16px;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 8px;
        }

        .badge-success {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .badge-warning {
            background: #fff3e0;
            color: #f57c00;
        }

        .badge-info {
            background: #e3f2fd;
            color: #1976d2;
        }

        .badge-danger {
            background: #ffebee;
            color: #c62828;
        }

        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
                gap: 15px;
            }

            .header-title h1 {
                font-size: 24px;
            }

            .notifications-filters {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group {
                flex-wrap: wrap;
            }

            .cleanup-btn {
                margin-left: 0;
                width: 100%;
            }

            .notification-item {
                flex-direction: column;
            }
            
            .notification-item:hover::after {
                display: none;
            }

            .notification-header {
                flex-direction: column;
                gap: 5px;
            }

            .notification-actions {
                flex-direction: column;
            }

            .notification-actions .btn {
                width: 100%;
            }

            body {
                padding-bottom: 80px;
            }
        }

        @media (min-width: 769px) and (max-width: 1024px) {
            .cleanup-text {
                display: none;
            }
        }
    </style>
</head>
<body>
    <?php if (file_exists('header1.php')) include 'header1.php'; ?>

    <div class="header">
        <div class="header-container">
            <div class="header-title">
                <i class="fas fa-bell icon"></i>
                <h1>Notificaciones</h1>
            </div>
            <div class="header-actions">
                <button class="btn btn-secondary" onclick="markAllAsRead()">
                    <i class="fas fa-check-double"></i> Marcar todas como leídas
                </button>
                <a href="shop-requests-index.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- FILTROS CON BOTÓN LIMPIAR -->
        <div class="notifications-filters">
            <div class="filter-group">
                <button class="filter-btn active" data-filter="unread" onclick="filterNotifications('unread')">
                    <i class="fas fa-envelope"></i> Sin leer
                </button>
                <button class="filter-btn" data-filter="read" onclick="filterNotifications('read')">
                    <i class="fas fa-envelope-open"></i> Leídas
                </button>
                <button class="filter-btn" data-filter="proposals" onclick="filterNotifications('proposals')">
                    <i class="fas fa-file-alt"></i> Propuestas
                </button>
                <button class="filter-btn" data-filter="deliveries" onclick="filterNotifications('deliveries')">
                    <i class="fas fa-truck"></i> Entregas
                </button>
                <button class="filter-btn" data-filter="payments" onclick="filterNotifications('payments')">
                    <i class="fas fa-dollar-sign"></i> Pagos
                </button>
            </div>
            
            <!-- 🆕 BOTÓN LIMPIAR LEÍDAS (solo visible en filtro "leídas") -->
            <button class="btn btn-secondary cleanup-btn" id="cleanup-btn" onclick="deleteReadNotifications()" title="Eliminar notificaciones ya leídas" style="display: none;">
                <i class="fas fa-broom"></i> 
                <span class="cleanup-text">Limpiar leídas</span>
            </button>
        </div>

        <!-- LISTA DE NOTIFICACIONES -->
        <div class="notifications-list" id="notifications-container">
            <div class="loading-state">
                <i class="fas fa-spinner fa-spin"></i>
                <p style="margin-top:20px;color:#666;font-weight:600;">Cargando notificaciones...</p>
            </div>
        </div>
    </div>

    <script>
        const userId = <?php echo $user_id; ?>;
        let allNotifications = [];
        let currentFilter = 'unread'; // Por defecto: solo sin leer

        document.addEventListener('DOMContentLoaded', () => {
            loadNotifications();
            setInterval(loadNotifications, 30000);
        });

        /**
         * 🔄 Cargar notificaciones desde la API
         */
        async function loadNotifications() {
            try {
                const response = await fetch(`shop-notifications-api.php?action=get_notifications&user_id=${userId}`);
                const data = await response.json();
                
                if (data.success) {
                    allNotifications = data.notifications;
                    filterNotifications(currentFilter);
                } else {
                    showError('Error al cargar notificaciones');
                }
            } catch (error) {
                console.error('Error:', error);
                showError('Error de conexión');
            }
        }

        /**
         * 🎨 Renderizar notificaciones en el DOM
         */
        function renderNotifications(notifications) {
            const container = document.getElementById('notifications-container');
            
            if (notifications.length === 0) {
                let emptyMessage = '';
                let emptyIcon = 'fa-bell-slash';
                
                if (currentFilter === 'unread') {
                    emptyMessage = '<h2>¡Todo limpio!</h2><p>No tienes notificaciones sin leer</p>';
                    emptyIcon = 'fa-check-circle';
                } else if (currentFilter === 'read') {
                    emptyMessage = '<h2>No hay notificaciones leídas</h2><p>Las notificaciones que marques como leídas aparecerán aquí</p>';
                    emptyIcon = 'fa-envelope-open';
                } else {
                    emptyMessage = '<h2>No hay notificaciones de este tipo</h2><p>Cuando recibas notificaciones de esta categoría, aparecerán aquí</p>';
                }
                
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas ${emptyIcon}"></i>
                        ${emptyMessage}
                    </div>
                `;
                return;
            }

            container.innerHTML = notifications.map(n => createNotificationHTML(n)).join('');
        }

        /**
         * 🏗️ Crear HTML de una notificación
         */
        function createNotificationHTML(notification) {
            const config = getNotificationConfig(notification.type);
            const timeAgo = formatTimeAgo(notification.created_at);
            const isUnread = notification.is_read == 0;

            return `
                <div class="notification-item ${isUnread ? 'unread' : ''}" 
                     data-id="${notification.id}"
                     data-type="${notification.type}"
                     onclick="handleNotificationClick(${notification.id}, '${notification.type}', ${notification.reference_id})">
                    
                    <div class="notification-icon" style="background: ${config.bgColor}; color: ${config.color};">
                        <i class="${config.icon}"></i>
                    </div>
                    
                    <div class="notification-content">
                        <div class="notification-header">
                            <div class="notification-title">
                                ${escapeHtml(notification.title)}
                                ${config.badge ? `<span class="badge ${config.badge.class}">${config.badge.text}</span>` : ''}
                            </div>
                            <div class="notification-time">
                                <i class="far fa-clock"></i> ${timeAgo}
                            </div>
                        </div>
                        
                        <div class="notification-message">
                            ${escapeHtml(notification.message)}
                        </div>
                        
                        ${notification.action_url ? `
                            <div class="notification-actions">
                                <button class="btn btn-primary" onclick="event.stopPropagation(); navigateTo('${escapeHtml(notification.action_url)}')">
                                    ${config.actionText || 'Ver detalles'}
                                </button>
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
        }

        /**
         * 🎨 Configuración visual por tipo de notificación
         */
        function getNotificationConfig(type) {
            const configs = {
                'new_proposal': {
                    icon: 'fas fa-file-alt',
                    color: '#1976d2',
                    bgColor: '#e3f2fd',
                    actionText: 'Ver propuesta',
                    badge: { class: 'badge-info', text: 'Nueva' }
                },
                'proposal_accepted': {
                    icon: 'fas fa-check-circle',
                    color: '#2e7d32',
                    bgColor: '#e8f5e9',
                    actionText: 'Ver detalles',
                    badge: { class: 'badge-success', text: 'Aceptada' }
                },
                'proposal_rejected': {
                    icon: 'fas fa-times-circle',
                    color: '#c62828',
                    bgColor: '#ffebee',
                    actionText: 'Ver solicitud',
                    badge: { class: 'badge-danger', text: 'Rechazada' }
                },
                'payment_received': {
                    icon: 'fas fa-dollar-sign',
                    color: '#2e7d32',
                    bgColor: '#e8f5e9',
                    actionText: 'Ver pago',
                    badge: { class: 'badge-success', text: 'Recibido' }
                },
                'delivery_update': {
                    icon: 'fas fa-truck',
                    color: '#f57c00',
                    bgColor: '#fff3e0',
                    actionText: 'Ver entrega',
                    badge: { class: 'badge-warning', text: 'Actualización' }
                },
                'delivery_completed': {
                    icon: 'fas fa-check-double',
                    color: '#2e7d32',
                    bgColor: '#e8f5e9',
                    actionText: 'Ver detalles',
                    badge: { class: 'badge-success', text: 'Completada' }
                },
                'payment_released': {
                    icon: 'fas fa-money-bill-wave',
                    color: '#2e7d32',
                    bgColor: '#e8f5e9',
                    actionText: 'Ver detalles',
                    badge: { class: 'badge-success', text: 'Liberado' }
                },
                'system': {
                    icon: 'fas fa-info-circle',
                    color: '#1976d2',
                    bgColor: '#e3f2fd',
                    actionText: 'Ver detalles',
                    badge: { class: 'badge-info', text: 'Sistema' }
                }
            };

            return configs[type] || {
                icon: 'fas fa-bell',
                color: '#666',
                bgColor: '#f5f5f5',
                actionText: 'Ver detalles'
            };
        }

        /**
         * 🔍 Filtrar notificaciones por tipo
         */
        function filterNotifications(filter) {
            currentFilter = filter;
            
            // Actualizar botones activos
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelector(`[data-filter="${filter}"]`).classList.add('active');

            // Mostrar/ocultar botón "Limpiar leídas" solo en el filtro "read"
            const cleanupBtn = document.getElementById('cleanup-btn');
            if (filter === 'read') {
                cleanupBtn.style.display = 'flex';
            } else {
                cleanupBtn.style.display = 'none';
            }

            let filtered = allNotifications;

            if (filter === 'unread') {
                // Solo las NO leídas
                filtered = allNotifications.filter(n => n.is_read == 0);
            } else if (filter === 'read') {
                // Solo las LEÍDAS
                filtered = allNotifications.filter(n => n.is_read == 1);
            } else if (filter === 'proposals') {
                // Solo propuestas NO leídas
                filtered = allNotifications.filter(n => 
                    n.type.includes('proposal') && n.is_read == 0
                );
            } else if (filter === 'deliveries') {
                // Solo entregas NO leídas
                filtered = allNotifications.filter(n => 
                    n.type.includes('delivery') && n.is_read == 0
                );
            } else if (filter === 'payments') {
                // Solo pagos NO leídos
                filtered = allNotifications.filter(n => 
                    n.type.includes('payment') && n.is_read == 0
                );
            }

            renderNotifications(filtered);
        }

        /**
         * 🖱️ Manejar clic en notificación
         */
        async function handleNotificationClick(notificationId, type, referenceId) {
            await markAsRead(notificationId);
            
            const urls = {
                'new_proposal': `shop-request-detail.php?id=${referenceId}#proposals`,
                'proposal_accepted': `shop-request-detail.php?id=${referenceId}`,
                'proposal_rejected': `shop-request-detail.php?id=${referenceId}`,
                'payment_received': `shop-verificacion-qr.php`,
                'delivery_update': `shop-verificacion-qr.php`,
                'delivery_completed': `shop-verificacion-qr.php`,
                'payment_released': `shop-verificacion-qr.php`
            };

            if (urls[type]) {
                window.location.href = urls[type];
            }
        }

        /**
         * ✅ Marcar notificación como leída
         */
        async function markAsRead(notificationId) {
            try {
                const formData = new FormData();
                formData.append('action', 'mark_as_read');
                formData.append('notification_id', notificationId);

                const response = await fetch('shop-notifications-api.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                
                if (data.success) {
                    const item = document.querySelector(`[data-id="${notificationId}"]`);
                    if (item) {
                        item.classList.remove('unread');
                    }
                    await loadNotifications();
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }

        /**
         * ✅✅ Marcar todas como leídas
         */
        async function markAllAsRead() {
            const unreadCount = allNotifications.filter(n => n.is_read == 0).length;
            
            if (unreadCount === 0) {
                Swal.fire({
                    icon: 'info',
                    title: 'No hay notificaciones sin leer',
                    confirmButtonColor: '#4CAF50'
                });
                return;
            }

            const result = await Swal.fire({
                title: '¿Marcar todas como leídas?',
                html: `Se marcarán <strong>${unreadCount}</strong> notificaciones como leídas.`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#4CAF50',
                cancelButtonColor: '#666',
                confirmButtonText: 'Sí, marcar',
                cancelButtonText: 'Cancelar'
            });

            if (result.isConfirmed) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'mark_all_as_read');
                    formData.append('user_id', userId);

                    const response = await fetch('shop-notifications-api.php', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();
                    
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Listo',
                            text: data.message,
                            confirmButtonColor: '#4CAF50'
                        });
                        
                        await loadNotifications();
                    }
                } catch (error) {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'No se pudo completar la acción'
                    });
                }
            }
        }

        /**
         * 🧹 Eliminar todas las notificaciones leídas (manual)
         */
        async function deleteReadNotifications() {
            const readCount = allNotifications.filter(n => n.is_read == 1).length;
            
            if (readCount === 0) {
                Swal.fire({
                    icon: 'info',
                    title: 'No hay notificaciones leídas',
                    text: 'Todas tus notificaciones están sin leer',
                    confirmButtonColor: '#4CAF50'
                });
                return;
            }

            const result = await Swal.fire({
                title: '¿Eliminar notificaciones leídas?',
                html: `Se eliminarán <strong>${readCount}</strong> notificaciones ya leídas.<br><small style="color:#999;">Esta acción no se puede deshacer.</small>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#666',
                confirmButtonText: `Sí, eliminar ${readCount}`,
                cancelButtonText: 'Cancelar'
            });

            if (result.isConfirmed) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'delete_read_notifications');

                    const response = await fetch('shop-notifications-api.php', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();
                    
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Limpieza completada!',
                            text: data.message,
                            confirmButtonColor: '#4CAF50',
                            timer: 3000
                        });
                        
                        await loadNotifications();
                    } else {
                        throw new Error(data.message || 'Error desconocido');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'No se pudo completar la acción. Inténtalo de nuevo.',
                        confirmButtonColor: '#d33'
                    });
                }
            }
        }

        /**
         * 🔗 Navegar a URL de forma segura
         */
        function navigateTo(url) {
            // Validar que sea una URL interna
            if (!url || (!url.startsWith('shop-') && !url.startsWith('/'))) {
                console.error('URL no válida:', url);
                return;
            }
            window.location.href = url;
        }

        /**
         * ⏰ Formatear tiempo relativo
         */
        function formatTimeAgo(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const seconds = Math.floor((now - date) / 1000);

            const intervals = {
                año: 31536000,
                mes: 2592000,
                semana: 604800,
                día: 86400,
                hora: 3600,
                minuto: 60
            };

            for (const [name, value] of Object.entries(intervals)) {
                const interval = Math.floor(seconds / value);
                if (interval >= 1) {
                    return interval === 1 ? `Hace 1 ${name}` : `Hace ${interval} ${name}s`;
                }
            }

            return 'Hace un momento';
        }

        /**
         * 🛡️ Escapar HTML para prevenir XSS
         */
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        /**
         * ⚠️ Mostrar estado de error
         */
        function showError(message) {
            const container = document.getElementById('notifications-container');
            container.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle" style="color: #f57c00;"></i>
                    <h2>${message}</h2>
                    <button class="btn btn-primary" onclick="loadNotifications()" style="margin-top: 20px;">
                        <i class="fas fa-redo"></i> Reintentar
                    </button>
                </div>
            `;
        }
    </script>

</body>
</html>