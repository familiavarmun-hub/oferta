<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['usuario_id'];

// Obtener todas las propuestas del usuario con mensajes
$sql = "SELECT DISTINCT 
               p.id as proposal_id,
               p.status as proposal_status,
               r.title as product_title,
               r.user_id as requester_id,
               p.traveler_id,
               COALESCE(req.full_name, req.username) as requester_name,
               COALESCE(trav.full_name, trav.username) as traveler_name,
               (SELECT message 
                FROM shop_chat_messages 
                WHERE proposal_id = p.id 
                ORDER BY created_at DESC LIMIT 1) as last_message,
               (SELECT sender_id 
                FROM shop_chat_messages 
                WHERE proposal_id = p.id 
                ORDER BY created_at DESC LIMIT 1) as last_sender_id,
               (SELECT created_at 
                FROM shop_chat_messages 
                WHERE proposal_id = p.id 
                ORDER BY created_at DESC LIMIT 1) as last_message_time,
               (SELECT COUNT(*) 
                FROM shop_chat_messages 
                WHERE proposal_id = p.id 
                  AND receiver_id = :user_id 
                  AND is_read = 0) as unread_count
        FROM shop_request_proposals p
        JOIN shop_requests r ON r.id = p.request_id
        LEFT JOIN accounts req ON req.id = r.user_id
        LEFT JOIN accounts trav ON trav.id = p.traveler_id
        WHERE (p.traveler_id = :user_id OR r.user_id = :user_id)
          AND EXISTS (
              SELECT 1 FROM shop_chat_messages 
              WHERE proposal_id = p.id
          )
        ORDER BY 
            CASE WHEN last_message_time IS NULL THEN 1 ELSE 0 END,
            last_message_time DESC, 
            p.created_at DESC";

$stmt = $conexion->prepare($sql);
$stmt->execute([':user_id' => $user_id]);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Mis Chats - SendVialo Shop</title>
    <link rel="icon" href="../Imagenes/globo5.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --color1: #41ba0d;
            --color2: #5dcb2a;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            overflow: hidden;
            position: fixed;
            width: 100%;
        }

        body {
            background: #f5f7fa;
            font-family: 'Inter', sans-serif;
        }

        /* Ocultar header en modo móvil cuando hay chat abierto */
        @media (max-width: 992px) {
            body.chat-open #header,
            body.chat-open header,
            body.chat-open nav,
            body.chat-open .header {
                display: none !important;
            }

            body.chat-open {
                position: fixed;
                width: 100%;
                height: 100vh;
                overflow: hidden;
            }

            body.chat-open .chat-layout {
                height: 100vh !important;
                margin-top: 0 !important;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
            }
        }

        .chat-layout {
            display: grid;
            grid-template-columns: 380px 1fr;
            height: 100vh;
            max-width: 100%;
            position: relative;
        }

        /* LISTA DE CONVERSACIONES */
        .conversation-sidebar {
            background: white;
            border-right: 1px solid #e5e7eb;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }

        .sidebar-header {
            padding: 20px;
            background: linear-gradient(135deg, var(--color1), var(--color2));
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }

        .sidebar-header h4 {
            margin: 0;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .back-btn {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }

        .back-btn:hover {
            background: rgba(255,255,255,0.3);
            color: white;
        }

        .search-box {
            padding: 15px;
            border-bottom: 1px solid #e5e7eb;
            flex-shrink: 0;
        }

        .search-input {
            width: 100%;
            padding: 10px 15px 10px 40px;
            border: 2px solid #e5e7eb;
            border-radius: 25px;
            font-size: 14px;
            transition: all 0.3s;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%236b7280' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: 12px center;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--color1);
            box-shadow: 0 0 0 3px rgba(65, 186, 13, 0.1);
        }

        .conversations-list {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            -webkit-overflow-scrolling: touch;
            height: 100%;
        }

        .conversation-item {
            display: flex;
            align-items: center;
            padding: 15px;
            gap: 12px;
            cursor: pointer;
            transition: all 0.2s;
            border-bottom: 1px solid #f3f4f6;
        }

        .conversation-item:hover {
            background: #f9fafb;
        }

        .conversation-item.active {
            background: #e9f8f3;
            border-left: 3px solid var(--color1);
        }

        .conversation-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
            border: 2px solid #e5e7eb;
        }

        .conversation-content {
            flex: 1;
            min-width: 0;
        }

        .conversation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 4px;
        }

        .conversation-name {
            font-weight: 600;
            font-size: 15px;
            color: #111827;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 180px;
        }

        .conversation-time {
            font-size: 12px;
            color: #9ca3af;
            flex-shrink: 0;
            margin-left: 8px;
        }

        .conversation-preview {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            min-width: 0;
        }

        .last-message {
            font-size: 13px;
            color: #6b7280;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            flex: 1;
            max-width: 200px;
        }

        .unread-badge {
            background: var(--color1);
            color: white;
            border-radius: 12px;
            padding: 2px 8px;
            font-size: 11px;
            font-weight: 600;
            min-width: 20px;
            text-align: center;
            flex-shrink: 0;
        }

        .product-tag {
            font-size: 11px;
            color: #6b7280;
            margin-top: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }

        /* CHAT WINDOW */
        .chat-window {
            background: white;
            position: relative;
            height: 100vh;
            overflow: hidden;
        }

        .chat-iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        .no-chat-selected {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #9ca3af;
        }

        .no-chat-selected i {
            font-size: 80px;
            margin-bottom: 20px;
            color: var(--color1);
            opacity: 0.3;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #9ca3af;
        }

        .empty-state i {
            font-size: 60px;
            margin-bottom: 20px;
            opacity: 0.3;
            color: var(--color1);
        }

        /* Scrollbar para desktop */
        .conversations-list::-webkit-scrollbar {
            width: 6px;
        }

        .conversations-list::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }

        .conversations-list::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* RESPONSIVE */
        @media (max-width: 1200px) {
            .last-message {
                max-width: 180px;
            }
            
            .conversation-name {
                max-width: 150px;
            }
        }

        @media (max-width: 992px) {
            html, body {
                overflow: auto;
                position: relative;
            }

            .chat-layout {
                grid-template-columns: 1fr;
                height: calc(100vh - 60px); /* Ajustar por el header */
            }

            body.chat-open html,
            body.chat-open body {
                overflow: hidden;
                position: fixed;
            }

            .conversation-sidebar {
                display: flex;
                height: calc(100vh - 60px);
            }

            .chat-window {
                display: none;
            }

            .chat-layout.chat-open {
                height: 100vh !important;
            }

            .chat-layout.chat-open .conversation-sidebar {
                display: none;
            }

            .chat-layout.chat-open .chat-window {
                display: block;
                height: 100vh;
                height: 100dvh;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 9999;
            }

            .chat-iframe {
                width: 100% !important;
                height: 100vh !important;
                height: 100dvh !important;
                border: none !important;
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                right: 0 !important;
                bottom: 0 !important;
            }

            .conversations-list {
                height: auto;
                flex: 1;
            }

            .last-message {
                max-width: calc(100vw - 200px);
            }

            .conversation-name {
                max-width: calc(100vw - 180px);
            }
        }

        @media (max-width: 768px) {
            .conversation-item {
                padding: 12px;
            }

            .conversation-avatar {
                width: 45px;
                height: 45px;
            }

            .conversation-name {
                font-size: 14px;
                max-width: calc(100vw - 160px);
            }

            .last-message {
                font-size: 12px;
                max-width: calc(100vw - 180px);
            }

            .product-tag {
                font-size: 10px;
            }
        }

        @media (max-width: 480px) {
            .sidebar-header {
                padding: 15px;
            }

            .sidebar-header h4 {
                font-size: 1.1rem;
            }

            .search-box {
                padding: 12px;
            }

            .conversation-item {
                padding: 10px;
                gap: 10px;
            }

            .conversation-avatar {
                width: 40px;
                height: 40px;
            }

            .last-message {
                max-width: calc(100vw - 150px);
            }

            .conversation-name {
                max-width: calc(100vw - 130px);
            }
        }

        @media (max-width: 360px) {
            .last-message {
                max-width: calc(100vw - 130px);
            }

            .conversation-name {
                max-width: calc(100vw - 110px);
            }
        }

        .back-btn-mobile {
            display: none;
        }

        /* Prevenir zoom en iOS */
        @media (max-width: 992px) {
            input, select, textarea {
                font-size: 16px !important;
            }
        }
    </style>
</head>
<body>
    <?php include 'header1.php'; ?>

    <div class="chat-layout" id="chatLayout">
        <!-- SIDEBAR -->
        <div class="conversation-sidebar">
            <div class="sidebar-header">
                <h4>
                    <i class="bi bi-chat-dots-fill"></i>
                    Mis Chats
                </h4>
                <a href="shop-requests-index.php" class="back-btn">
                    <i class="bi bi-arrow-left"></i>
                </a>
            </div>

            <div class="search-box">
                <input type="text" 
                       class="search-input" 
                       id="searchInput" 
                       placeholder="Buscar conversaciones...">
            </div>

            <div class="conversations-list" id="conversationsList">
                <?php if (empty($conversations)): ?>
                    <div class="empty-state">
                        <i class="bi bi-chat-square-text"></i>
                        <p><strong>No tienes conversaciones</strong></p>
                        <p style="font-size: 14px;">Inicia un chat desde tus propuestas</p>
                        <a href="shop-my-requests.php" class="btn btn-success btn-sm mt-3">
                            <i class="bi bi-box-seam"></i> Ver Mis Solicitudes
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($conversations as $conv): 
                        $is_requester = ($conv['requester_id'] == $user_id);
                        $other_user_id = $is_requester ? $conv['traveler_id'] : $conv['requester_id'];
                        $other_user_name = $is_requester ? $conv['traveler_name'] : $conv['requester_name'];
                        $avatar_url = "../mostrar_imagen.php?id={$other_user_id}";
                        
                        $time_ago = '';
                        if ($conv['last_message_time']) {
                            $time = strtotime($conv['last_message_time']);
                            $diff = time() - $time;
                            if ($diff < 60) $time_ago = 'Ahora';
                            elseif ($diff < 3600) $time_ago = floor($diff / 60) . 'm';
                            elseif ($diff < 86400) $time_ago = date('H:i', $time);
                            elseif ($diff < 172800) $time_ago = 'Ayer';
                            else $time_ago = date('d/m', $time);
                        }
                        
                        // Procesar el último mensaje
                        $last_message_display = '';
                        if ($conv['last_message']) {
                            $prefix = ($conv['last_sender_id'] == $user_id) ? 'Tú: ' : '';
                            $message_text = $conv['last_message'];
                            
                            // Limitar a 50 caracteres
                            if (mb_strlen($message_text) > 50) {
                                $message_text = mb_substr($message_text, 0, 50) . '...';
                            }
                            
                            $last_message_display = $prefix . $message_text;
                        } else {
                            $last_message_display = 'Sin mensajes';
                        }
                    ?>
                        <div class="conversation-item" 
                             data-proposal-id="<?php echo $conv['proposal_id']; ?>"
                             onclick="openChat(<?php echo $conv['proposal_id']; ?>)">
                            
                            <img src="<?php echo htmlspecialchars($avatar_url); ?>" 
                                 alt="Avatar"
                                 class="conversation-avatar"
                                 onerror="this.src='../Imagenes/user-default.jpg'">
                            
                            <div class="conversation-content">
                                <div class="conversation-header">
                                    <span class="conversation-name">
                                        <?php echo htmlspecialchars($other_user_name); ?>
                                    </span>
                                    <?php if ($time_ago): ?>
                                        <span class="conversation-time"><?php echo $time_ago; ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="conversation-preview">
                                    <span class="last-message">
                                        <?php echo htmlspecialchars($last_message_display); ?>
                                    </span>
                                    <?php if ($conv['unread_count'] > 0): ?>
                                        <span class="unread-badge"><?php echo $conv['unread_count']; ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="product-tag">
                                    <i class="bi bi-box-seam"></i> 
                                    <?php 
                                    $product_title = $conv['product_title'];
                                    echo htmlspecialchars(mb_strlen($product_title) > 40 ? mb_substr($product_title, 0, 40) . '...' : $product_title); 
                                    ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- CHAT WINDOW -->
        <div class="chat-window">
            <div class="no-chat-selected">
                <i class="bi bi-chat-dots"></i>
                <h4>SendVialo Shop</h4>
                <p>Selecciona una conversación para empezar a chatear</p>
            </div>
        </div>
    </div>

    <script>
        let currentProposalId = null;

        function openChat(proposalId) {
            currentProposalId = proposalId;
            
            // Marcar como activa
            document.querySelectorAll('.conversation-item').forEach(item => {
                item.classList.remove('active');
            });
            const activeItem = document.querySelector(`[data-proposal-id="${proposalId}"]`);
            if (activeItem) activeItem.classList.add('active');
            
            // Cargar chat en iframe
            const chatWindow = document.querySelector('.chat-window');
            chatWindow.innerHTML = `<iframe class="chat-iframe" src="shop-chat.php?proposal_id=${proposalId}"></iframe>`;
            
            // Responsive - OCULTAR HEADER EN MÓVIL
            const chatLayout = document.getElementById('chatLayout');
            chatLayout.classList.add('chat-open');
            
            // Añadir clase al body para ocultar el header en móvil
            if (window.innerWidth <= 992) {
                document.body.classList.add('chat-open');
                // Prevenir scroll del body
                document.documentElement.style.overflow = 'hidden';
            }
            
            // Actualizar contador
            setTimeout(updateUnreadCounts, 1000);
        }

        function closeChat() {
            document.getElementById('chatLayout').classList.remove('chat-open');
            document.body.classList.remove('chat-open');
            document.documentElement.style.overflow = '';
            currentProposalId = null;
        }

        function updateUnreadCounts() {
            fetch('shop-chat-api.php?action=get_all_unread')
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.unread_by_proposal) {
                        Object.entries(data.unread_by_proposal).forEach(([proposalId, count]) => {
                            const item = document.querySelector(`[data-proposal-id="${proposalId}"]`);
                            if (!item) return;
                            
                            let badge = item.querySelector('.unread-badge');
                            
                            if (count > 0 && proposalId != currentProposalId) {
                                if (badge) {
                                    badge.textContent = count;
                                } else {
                                    const preview = item.querySelector('.conversation-preview');
                                    if (preview) {
                                        const newBadge = document.createElement('span');
                                        newBadge.className = 'unread-badge';
                                        newBadge.textContent = count;
                                        preview.appendChild(newBadge);
                                    }
                                }
                            } else if (badge) {
                                badge.remove();
                            }
                        });
                        
                        // Actualizar título
                        const totalUnread = Object.values(data.unread_by_proposal).reduce((a, b) => a + b, 0);
                        document.title = totalUnread > 0 
                            ? `(${totalUnread}) Mis Chats - SendVialo Shop` 
                            : 'Mis Chats - SendVialo Shop';
                    }
                })
                .catch(err => console.error('Error:', err));
        }

        // Búsqueda
        document.getElementById('searchInput')?.addEventListener('input', function() {
            const term = this.value.toLowerCase();
            document.querySelectorAll('.conversation-item').forEach(item => {
                const name = item.querySelector('.conversation-name')?.textContent.toLowerCase() || '';
                const product = item.querySelector('.product-tag')?.textContent.toLowerCase() || '';
                const matches = name.includes(term) || product.includes(term);
                item.style.display = matches ? 'flex' : 'none';
            });
        });

        // Polling cada 5 segundos
        setInterval(updateUnreadCounts, 5000);
        
        // Carga inicial
        setTimeout(updateUnreadCounts, 500);

        // Escuchar mensajes del iframe
        window.addEventListener('message', function(event) {
            if (event.data.type === 'message_sent') {
                updateUnreadCounts();
            }
            
            // Cerrar chat cuando se presiona volver en el iframe
            if (event.data.type === 'close_chat') {
                closeChat();
            }
        });

        // AUTO-ABRIR CHAT AL CARGAR
        window.addEventListener('load', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const proposalId = urlParams.get('proposal_id');
            
            if (proposalId) {
                setTimeout(function() {
                    const chatItem = document.querySelector(`[data-proposal-id="${proposalId}"]`);
                    
                    if (chatItem) {
                        openChat(parseInt(proposalId));
                    }
                }, 300);
            }
        });

        // Detectar cambio de tamaño de ventana
        window.addEventListener('resize', function() {
            if (window.innerWidth > 992) {
                // En desktop, remover la clase del body
                document.body.classList.remove('chat-open');
                document.documentElement.style.overflow = '';
            } else if (currentProposalId) {
                // En móvil con chat abierto, añadir la clase
                document.body.classList.add('chat-open');
                document.documentElement.style.overflow = 'hidden';
            }
        });

        // Remover clase al volver atrás
        window.addEventListener('popstate', function() {
            closeChat();
        });

        // Prevenir zoom en inputs en iOS
        if (/iPad|iPhone|iPod/.test(navigator.userAgent)) {
            const viewport = document.querySelector('meta[name=viewport]');
            if (viewport) {
                viewport.setAttribute('content', 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no');
            }
        }
    </script>
</body>
</html>