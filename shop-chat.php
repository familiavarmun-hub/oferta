<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

$proposal_id = (int)($_GET['proposal_id'] ?? 0);
$user_id = $_SESSION['usuario_id'];

if ($proposal_id <= 0) {
    die('<div style="padding:100px;text-align:center;font-family:Inter, sans-serif; background:#f8fafc; height:100vh;">
            <div style="background:white; padding:40px; border-radius:32px; display:inline-block; box-shadow:0 20px 50px rgba(0,0,0,0.05);">
                <h2 style="color:#ef4444; font-weight:900;">❌ ID de propuesta inválido</h2>
                <p style="color:#64748b;">No se proporcionó un ID de propuesta válido para iniciar el chat.</p>
                <a href="shop-chat-list.php" style="display:inline-block; margin-top:20px; padding:12px 24px; background:#41ba0d; color:white; border-radius:14px; text-decoration:none; font-weight:700;">← Volver a Chats</a>
            </div>
         </div>');
}

// Cargar datos de la propuesta
$sql = "SELECT p.*, r.title, r.user_id as requester_id,
               p.traveler_id,
               COALESCE(req.full_name, req.username) as requester_name,
               COALESCE(trav.full_name, trav.username) as traveler_name
        FROM shop_request_proposals p
        JOIN shop_requests r ON r.id = p.request_id
        LEFT JOIN accounts req ON req.id = r.user_id
        LEFT JOIN accounts trav ON trav.id = p.traveler_id
        WHERE p.id = ? AND (p.traveler_id = ? OR r.user_id = ?)";
$stmt = $conexion->prepare($sql);
$stmt->execute([$proposal_id, $user_id, $user_id]);
$proposal = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$proposal) {
    die('<div style="padding:100px;text-align:center;font-family:Inter, sans-serif; background:#f8fafc; height:100vh;">
            <div style="background:white; padding:40px; border-radius:32px; display:inline-block; box-shadow:0 20px 50px rgba(0,0,0,0.05);">
                <h2 style="color:#ef4444; font-weight:900;">❌ Propuesta no encontrada</h2>
                <p style="color:#64748b;">No tienes acceso a esta propuesta o ha sido eliminada.</p>
                <a href="shop-chat-list.php" style="display:inline-block; margin-top:20px; padding:12px 24px; background:#41ba0d; color:white; border-radius:14px; text-decoration:none; font-weight:700;">← Volver a Chats</a>
            </div>
         </div>');
}

$is_requester = ($user_id == $proposal['requester_id']);
$other_user_id = $is_requester ? $proposal['traveler_id'] : $proposal['requester_id'];
$other_user_name = $is_requester ? $proposal['traveler_name'] : $proposal['requester_name'];

$in_iframe = true;

// Verificar si la propuesta está aceptada y obtener datos de entrega/QR
$delivery = null;
$isAccepted = ($proposal['status'] === 'accepted');

if ($isAccepted) {
    $sql_delivery = "SELECT d.*
                     FROM shop_deliveries d
                     WHERE d.proposal_id = ?";
    $stmt_delivery = $conexion->prepare($sql_delivery);
    $stmt_delivery->execute([$proposal_id]);
    $delivery = $stmt_delivery->fetch(PDO::FETCH_ASSOC);
}

$userRole = $is_requester ? 'solicitante' : 'viajero';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Chat | <?php echo htmlspecialchars($other_user_name); ?></title>
    <link rel="icon" href="../Imagenes/globo5.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/emoji-picker-element@1.18.4/picker.css">
    
    <style>
        :root {
            --primary: #41ba0d;
            --primary-dark: #2d8518;
            --primary-gradient: linear-gradient(135deg, #41ba0d 0%, #5dcb2a 100%);
            --bg-chat: #f8fafc;
            --slate-900: #0f172a;
            --slate-600: #475569;
            --slate-200: #e2e8f0;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: var(--bg-chat);
            font-family: 'Inter', sans-serif;
            overflow: hidden;
            height: 100vh;
            height: 100dvh;
        }

        .chat-container {
            display: flex;
            flex-direction: column;
            height: 100vh;
            height: 100dvh;
            background: white;
            position: relative;
        }

        /* --- HEADER --- */
        .chat-header {
            background: white;
            padding: 15px 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            border-bottom: 1px solid var(--slate-200);
            flex-shrink: 0;
            z-index: 100;
        }

        .back-btn {
            background: var(--zinc-100);
            border: none;
            color: var(--slate-900);
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: 0.3s;
        }
        .back-btn:hover { background: var(--slate-200); }

        .chat-user-info { flex: 1; }
        .chat-user-name { font-size: 18px; font-weight: 800; color: var(--slate-900); margin: 0; }
        .chat-proposal-title { font-size: 12px; color: var(--primary); font-weight: 700; text-transform: uppercase; margin: 0; }

        /* --- MENSAJES --- */
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 25px;
            background: var(--bg-chat);
            display: flex;
            flex-direction: column;
            gap: 16px;
            scroll-behavior: smooth;
        }

        .message { display: flex; width: 100%; }
        .message.sent { justify-content: flex-end; }
        .message.received { justify-content: flex-start; }

        .message-bubble {
            max-width: 75%;
            padding: 12px 18px;
            border-radius: 22px;
            position: relative;
            box-shadow: 0 4px 10px rgba(0,0,0,0.02);
            font-size: 15px;
        }

        .message.sent .message-bubble {
            background: var(--primary-gradient);
            color: white;
            border-bottom-right-radius: 4px;
        }

        .message.received .message-bubble {
            background: white;
            color: var(--slate-900);
            border-bottom-left-radius: 4px;
            border: 1px solid var(--slate-200);
        }

        .message-text { margin: 0 0 4px 0; line-height: 1.5; font-weight: 500; }
        .message-time { font-size: 10px; opacity: 0.7; display: block; text-align: right; }

        .message-image {
            max-width: 100%;
            border-radius: 14px;
            margin-bottom: 8px;
            cursor: pointer;
            border: 2px solid rgba(255,255,255,0.2);
        }

        .message-location {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            background: rgba(255,255,255,0.15);
            border-radius: 14px;
            text-decoration: none;
            color: inherit;
            font-weight: 700;
            font-size: 13px;
        }

        /* --- BANNERS QR / TRACKING (ESTILO PREMIUM) --- */
        .banners-container {
            padding: 10px 20px;
            background: var(--bg-chat);
            display: flex;
            gap: 10px;
            flex-shrink: 0;
        }

        .qr-nav-card {
            flex: 1;
            background: white;
            padding: 10px 15px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            border: 1px solid var(--slate-200);
            transition: 0.3s;
        }

        .qr-nav-card:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(0,0,0,0.05); }

        .qr-icon-box {
            width: 40px; height: 40px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center; font-size: 20px;
        }

        .qr-nav-card.solicitante .qr-icon-box { background: #f0fdf4; color: var(--primary); }
        .qr-nav-card.viajero .qr-icon-box { background: #eff6ff; color: #3b82f6; }

        .qr-card-text { display: flex; flex-direction: column; }
        .qr-card-text b { font-size: 13px; color: var(--slate-900); }
        .qr-card-text span { font-size: 10px; color: var(--slate-600); font-weight: 600; text-transform: uppercase; }

        /* --- INPUT AREA --- */
        .chat-input-area {
            background: white;
            padding: 15px 25px;
            border-top: 1px solid var(--slate-200);
            flex-shrink: 0;
        }

        .input-group-custom {
            display: flex;
            align-items: flex-end;
            gap: 12px;
            background: #f1f5f9;
            padding: 8px 12px;
            border-radius: 28px;
            border: 1px solid var(--slate-200);
        }

        .chat-input {
            flex: 1;
            border: none;
            background: transparent;
            padding: 8px 5px;
            font-size: 15px;
            max-height: 120px;
            resize: none;
            outline: none;
            color: var(--slate-900);
        }

        .input-actions { display: flex; gap: 5px; }
        .action-btn {
            background: transparent;
            border: none;
            color: var(--slate-600);
            width: 36px; height: 36px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; transition: 0.2s;
        }
        .action-btn:hover { color: var(--primary); background: white; }

        .btn-send {
            background: var(--primary-gradient);
            color: white;
            border: none;
            width: 44px; height: 44px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px;
            box-shadow: 0 4px 12px rgba(65, 186, 13, 0.3);
            transition: 0.3s;
            flex-shrink: 0;
        }
        .btn-send:hover:not(:disabled) { transform: scale(1.05); }

        /* Emoji Picker */
        .emoji-picker-container {
            position: absolute; bottom: 90px; left: 25px; z-index: 1000;
            display: none; box-shadow: 0 10px 40px rgba(0,0,0,0.1); border-radius: 15px; overflow: hidden;
        }
        .emoji-picker-container.show { display: block; }

        .photo-preview {
            position: absolute; bottom: 90px; left: 25px; background: white;
            padding: 10px; border-radius: 18px; border: 1px solid var(--slate-200);
            display: none; z-index: 1000;
        }
        .photo-preview.show { display: block; }

        @media (max-width: 768px) {
            .back-btn { display: flex; }
            .chat-messages { padding: 15px; }
            .message-bubble { max-width: 85%; }
            .banners-container { padding: 10px 12px; }
            .chat-input-area { padding: 10px 15px; }
        }

        /* Scrollbar refined */
        .chat-messages::-webkit-scrollbar { width: 4px; }
        .chat-messages::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    </style>
</head>
<body>
    <div class="chat-container">
        <!-- HEADER -->
        <header class="chat-header">
            <button class="back-btn" onclick="goBack()">
                <i class="bi bi-arrow-left"></i>
            </button>
            <div class="chat-user-info">
                <p class="chat-proposal-title"><?php echo htmlspecialchars($proposal['title']); ?></p>
                <h1 class="chat-user-name"><?php echo htmlspecialchars($other_user_name); ?></h1>
            </div>
            <div class="header-status">
                <span style="width: 10px; height: 10px; background: #10b981; border-radius: 50%; display: inline-block;"></span>
            </div>
        </header>

        <!-- MENSAJES -->
        <div class="chat-messages" id="messages-container">
            <!-- Cargados por JS -->
        </div>

        <!-- BANNERS CONTEXTUALES -->
        <?php if ($isAccepted): ?>
        <div class="banners-container">
            <?php if ($userRole === 'solicitante'): ?>
                <a href="#" 
                   class="qr-nav-card solicitante banner-nav-btn" 
                   data-type="request" 
                   data-request-id="<?php echo $proposal['request_id']; ?>"
                   data-proposal-id="<?php echo $proposal_id; ?>">
                    <div class="qr-icon-box"><i class="bi bi-qr-code"></i></div>
                    <div class="qr-card-text"><b>Mi Código QR</b><span>Ver para entrega</span></div>
                </a>
                <a href="shop-verificacion-qr.php" class="qr-nav-card solicitante" target="_parent">
                    <div class="qr-icon-box"><i class="bi bi-geo-alt"></i></div>
                    <div class="qr-card-text"><b>Seguimiento</b><span>Estado del viaje</span></div>
                </a>
            <?php else: ?>
                <a href="#" 
                   class="qr-nav-card viajero banner-nav-btn" 
                   data-type="proposal" 
                   data-proposal-id="<?php echo $proposal_id; ?>">
                    <div class="qr-icon-box"><i class="bi bi-qr-code-scan"></i></div>
                    <div class="qr-card-text"><b>Escanear QR</b><span>Confirmar entrega</span></div>
                </a>
                <a href="shop-verificacion-qr.php" class="qr-nav-card viajero" target="_parent">
                    <div class="qr-icon-box"><i class="bi bi-truck"></i></div>
                    <div class="qr-card-text"><b>Seguimiento</b><span>Actualizar estado</span></div>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- INPUT AREA -->
        <div class="chat-input-area">
            <div class="photo-preview" id="photoPreview">
                <img src="" alt="Preview" style="max-width: 150px; border-radius: 12px;" id="photoPreviewImg">
                <button onclick="removePhoto()" style="position: absolute; top: -10px; right: -10px; background: #ef4444; color: white; border: none; border-radius: 50%; width: 25px; height: 25px;"><i class="bi bi-x"></i></button>
            </div>

            <div class="emoji-picker-container" id="emojiPickerContainer"></div>

            <form id="message-form" class="d-flex align-items-end gap-2">
                <div class="input-group-custom">
                    <div class="input-actions">
                        <button type="button" class="action-btn" id="emojiBtn"><i class="bi bi-emoji-smile"></i></button>
                        <button type="button" class="action-btn" onclick="document.getElementById('photoInput').click()"><i class="bi bi-image"></i></button>
                        <button type="button" class="action-btn" id="locationBtn"><i class="bi bi-geo-alt"></i></button>
                    </div>
                    
                    <input type="file" id="photoInput" accept="image/*" style="display:none;" onchange="handlePhotoSelect(event)">
                    
                    <textarea class="chat-input" id="message-input" placeholder="Escribe un mensaje..." rows="1" maxlength="1000"></textarea>
                </div>

                <button type="submit" class="btn-send" id="send-button">
                    <i class="bi bi-send-fill"></i>
                </button>
            </form>
        </div>
    </div>

    <!-- Scripts (Mantenidos) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script type="module">
        import 'https://cdn.jsdelivr.net/npm/emoji-picker-element@1.18.4/index.js';
        const picker = document.createElement('emoji-picker');
        document.getElementById('emojiPickerContainer').appendChild(picker);
        picker.addEventListener('emoji-click', event => {
            const input = document.getElementById('message-input');
            input.value += event.detail.unicode;
            input.focus();
            document.getElementById('emojiPickerContainer').classList.remove('show');
        });
    </script>

    <script>
        const proposalId = <?php echo $proposal_id; ?>;
        const userId = <?php echo $user_id; ?>;
        const receiverId = <?php echo $other_user_id; ?>;
        const messageInput = document.getElementById('message-input');
        const messagesContainer = document.getElementById('messages-container');
        let lastMessageId = 0;
        let selectedPhoto = null;

        // Emoji Toggle
        document.getElementById('emojiBtn').onclick = () => document.getElementById('emojiPickerContainer').classList.toggle('show');

        function handlePhotoSelect(event) {
            const file = event.target.files[0];
            if (file) {
                selectedPhoto = file;
                const reader = new FileReader();
                reader.onload = e => {
                    document.getElementById('photoPreviewImg').src = e.target.result;
                    document.getElementById('photoPreview').classList.add('show');
                };
                reader.readAsDataURL(file);
            }
        }

        function removePhoto() {
            selectedPhoto = null;
            document.getElementById('photoPreview').classList.remove('show');
        }

        // Logic for sending/loading (Idéntica a la tuya)
        async function loadMessages() {
            try {
                const response = await fetch(`shop-chat-api.php?action=get_messages&proposal_id=${proposalId}&last_id=${lastMessageId}`);
                const data = await response.json();
                if (data.success && data.messages.length > 0) {
                    data.messages.forEach(msg => {
                        appendMessage(msg);
                        lastMessageId = Math.max(lastMessageId, parseInt(msg.id));
                    });
                    scrollToBottom();
                }
            } catch (error) { console.error(error); }
        }

        function appendMessage(msg) {
            const isSent = parseInt(msg.sender_id) === userId;
            const div = document.createElement('div');
            div.className = `message ${isSent ? 'sent' : 'received'}`;
            
            let content = '';
            if (msg.photo_path) content += `<img src="${msg.photo_path}" class="message-image" onclick="window.open('${msg.photo_path}')">`;
            if (msg.latitude) content += `<a href="https://maps.google.com/?q=${msg.latitude},${msg.longitude}" target="_blank" class="message-location"><i class="bi bi-geo-alt-fill"></i> Ver ubicación</a>`;
            if (msg.message) content += `<p class="message-text">${msg.message}</p>`;
            content += `<span class="message-time">${formatTime(msg.created_at)}</span>`;
            
            div.innerHTML = `<div class="message-bubble">${content}</div>`;
            messagesContainer.appendChild(div);
        }

        async function sendMessage(e) {
            if(e) e.preventDefault();
            const text = messageInput.value.trim();
            if (!text && !selectedPhoto) return;

            const formData = new FormData();
            formData.append('action', 'send_message');
            formData.append('proposal_id', proposalId);
            formData.append('receiver_id', receiverId);
            if(text) formData.append('message', text);
            if(selectedPhoto) formData.append('photo', selectedPhoto);

            try {
                const response = await fetch('shop-chat-api.php', { method: 'POST', body: formData });
                const data = await response.json();
                if (data.success) {
                    messageInput.value = '';
                    removePhoto();
                    loadMessages();
                }
            } catch (error) { console.error(error); }
        }

        document.getElementById('message-form').onsubmit = sendMessage;

        messageInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        function scrollToBottom() { messagesContainer.scrollTop = messagesContainer.scrollHeight; }
        function formatTime(dt) { return new Date(dt).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}); }
        function goBack() { window.location.href = 'shop-chat-list.php'; }

        setInterval(loadMessages, 3000);
        loadMessages();

        // ===== NAVEGACIÓN A TARJETAS =====
        document.querySelectorAll('.banner-nav-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const type = this.dataset.type;
                const proposalId = this.dataset.proposalId;
                const requestId = this.dataset.requestId;

                if (type === 'request' && requestId) {
                    window.top.location.href = `shop-my-requests.php?tab=accepted&card_id=${requestId}&scroll=true`;
                } else if (type === 'proposal' && proposalId) {
                    window.top.location.href = `shop-my-proposals.php?tab=accepted&card_id=${proposalId}&scroll=true`;
                }
            });
        });
    </script>
</body>
</html>