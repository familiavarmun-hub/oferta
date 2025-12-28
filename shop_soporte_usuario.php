<?php
ob_start();
session_start();

// Comprobar si el usuario está logueado o asignarle un nombre por defecto
if (!isset($_SESSION['usuario_nombre'])) {
    $_SESSION['usuario_nombre'] = "Invitado";
}

// Intenta utilizar la imagen de perfil si está disponible y existe
$ruta_imagen_por_defecto = "../Imagenes/user-default.jpg";
$ruta_imagen = $_SESSION['ruta_imagen'] ?? $ruta_imagen_por_defecto;
if (!file_exists($ruta_imagen)) {
    $ruta_imagen = $ruta_imagen_por_defecto;
}

// Incluir archivo de configuración para la conexión a la base de datos
require_once __DIR__ . '/../config.php';

// Verificar si el usuario está autenticado para funciones exclusivas
$usuario_autenticado = isset($_SESSION['username']);
$usuario_id = $_SESSION['usuario_id'] ?? null;

// Obtener categorías de soporte
$categorias = [];
try {
    $stmtCat = $conexion->query("SELECT id, nombre FROM soporte_categorias WHERE activo = 1 ORDER BY id ASC");
    $categorias = $stmtCat->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $categorias = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atención al Usuario - SendVialo Shop</title>
    <link rel="stylesheet" href="../css/estilos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.3.0/css/all.min.css"
        integrity="sha512-SzlrxWUlpfuzQ+pcUCosxcglQRNAq/DZjVsC0lE40xsADsfeQoEypE+enwcOiGjk/bSuGGKHEyjSoQ1zVisanQ=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <link rel="icon" href="../Imagenes/globo5.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../css/header.css">

    <style>
        html, body {
            overflow-x: visible !important;
            overflow-y: auto !important;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%) !important;
            font-family: 'Inter', sans-serif !important;
        }
        
        /* Padding para barra inferior móvil */
        body.has-bottom-nav {
            padding-bottom: 80px;
        }
        
        .soporte-header {
            text-align: center !important;
            margin: 20px auto 60px auto !important;
            padding: 30px 20px !important;
            border-bottom: 3px solid #42ba25 !important;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%) !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08) !important;
            width: 100% !important;
            border-radius: 0 0 16px 16px !important;
            animation: slideDown 0.5s ease-out !important;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .soporte-header h1 {
            color: #2c3e50 !important;
            font-size: 2rem !important;
            font-weight: 700 !important;
            margin: 0 auto 10px auto !important;
            line-height: 1.3 !important;
            background: linear-gradient(135deg, #42ba25 0%, #2d8518 100%) !important;
            -webkit-background-clip: text !important;
            -webkit-text-fill-color: transparent !important;
            background-clip: text !important;
        }

        .soporte-header p {
            color: #6c757d !important;
            font-size: 1rem !important;
            font-weight: 400 !important;
            line-height: 1.5 !important;
        }
        
        @media (min-width: 1025px) {
            .soporte-header {
                padding: 50px 20px !important;
            }
            .soporte-header h1 {
                font-size: 3.8rem !important;
            }
            .soporte-header p {
                font-size: 1.6rem !important;
            }
        }
        
        .user-info {
            display: none !important;
        }
        
        .main-wrapper {
            max-width: 1350px !important;
            padding: 0 20px !important;
            margin: 0 auto !important;
        }
        
        .soporte-content {
            display: flex !important;
            gap: 24px !important;
            margin-top: 50px !important;
            animation: fadeIn 0.6s ease-out !important;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .soporte-sidebar {
            flex: 0 0 280px !important;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%) !important;
            border: 2px solid #e3e8ef !important;
            border-radius: 16px !important;
            padding: 24px !important;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08) !important;
            transition: all 0.3s ease !important;
        }
        
        .soporte-sidebar:hover {
            transform: translateY(-4px) !important;
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.12) !important;
        }
        
        .categorias-soporte h3 {
            color: #2c3e50 !important;
            font-size: 0.9rem !important;
            font-weight: 700 !important;
            margin-bottom: 18px !important;
            padding-bottom: 14px !important;
            border-bottom: 2px solid #42ba25 !important;
            text-transform: uppercase !important;
            letter-spacing: 1px !important;
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
        }
        
        .categorias-lista {
            list-style: none !important;
            padding: 0 !important;
            margin: 0 !important;
        }
        
        .categorias-lista li {
            margin-bottom: 8px !important;
        }
        
        .categorias-lista a {
            display: flex !important;
            align-items: center !important;
            gap: 12px !important;
            padding: 12px 16px !important;
            border-radius: 12px !important;
            text-decoration: none !important;
            color: #495057 !important;
            font-weight: 600 !important;
            font-size: 0.95rem !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            border: 2px solid transparent !important;
            position: relative !important;
            overflow: hidden !important;
        }
        
        .categorias-lista a:hover {
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%) !important;
            color: #2d8518 !important;
            border-color: #42ba25 !important;
            transform: translateX(8px) scale(1.02) !important;
            box-shadow: 0 4px 12px rgba(66, 186, 37, 0.2) !important;
        }
        
        .categorias-lista .categoria-activa a {
            background: linear-gradient(135deg, #42ba25 0%, #37a31f 100%) !important;
            color: #ffffff !important;
            border-color: #42ba25 !important;
            font-weight: 700 !important;
            box-shadow: 0 6px 16px rgba(66, 186, 37, 0.35) !important;
            transform: translateX(8px) scale(1.02) !important;
        }
        
        .categorias-lista i {
            font-size: 1.1rem !important;
            transition: transform 0.3s ease !important;
        }
        
        .icono-amarillo {
            color: #ffc107 !important;
            filter: drop-shadow(0 2px 4px rgba(255, 193, 7, 0.3)) !important;
        }
        
        .categorias-lista .categoria-activa a .icono-amarillo {
            color: #ffffff !important;
        }
        
        .soporte-main {
            flex: 1 !important;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%) !important;
            border: 2px solid #e3e8ef !important;
            border-radius: 16px !important;
            min-height: 600px !important;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08) !important;
            overflow: hidden !important;
            transition: all 0.3s ease !important;
        }
        
        .soporte-main:hover {
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.12) !important;
        }
        
        .seccion-contenido {
            padding: 28px !important;
        }
        
        .chat-container {
            display: flex !important;
            flex-direction: column !important;
            height: 600px !important;
            border: 2px solid #e3e8ef !important;
            border-radius: 16px !important;
            overflow: hidden !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08) !important;
        }
        
        .chat-header {
            background: linear-gradient(135deg, #42ba25 0%, #37a31f 100%) !important;
            color: #ffffff !important;
            padding: 18px 24px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            box-shadow: 0 4px 12px rgba(66, 186, 37, 0.2) !important;
        }
        
        .chat-header h3 {
            font-size: 1.2rem !important;
            font-weight: 700 !important;
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            margin: 0 !important;
        }
        
        .chat-header h3 i {
            animation: bounce 2s infinite !important;
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-4px); }
        }
        
        .status {
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            font-size: 0.9rem !important;
            font-weight: 600 !important;
            background: rgba(255, 255, 255, 0.25) !important;
            padding: 6px 14px !important;
            border-radius: 20px !important;
            backdrop-filter: blur(10px) !important;
        }
        
        .status-dot {
            width: 10px !important;
            height: 10px !important;
            background: #00ff88 !important;
            border-radius: 50% !important;
            animation: pulse 2s infinite !important;
            box-shadow: 0 0 10px #00ff88 !important;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(1.2); }
        }
        
        .chat-messages {
            flex: 1 !important;
            padding: 24px !important;
            overflow-y: auto !important;
            background: linear-gradient(to bottom, #f8f9fa 0%, #ffffff 50%, #f8f9fa 100%) !important;
            display: flex !important;
            flex-direction: column !important;
            gap: 14px !important;
            padding-bottom: 100px !important;
        }
        
        .chat-messages::-webkit-scrollbar {
            width: 8px !important;
        }
        
        .chat-messages::-webkit-scrollbar-track {
            background: #f1f3f5 !important;
            border-radius: 10px !important;
        }
        
        .chat-messages::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #42ba25 0%, #37a31f 100%) !important;
            border-radius: 10px !important;
        }
        
        .message {
            max-width: 70% !important;
            padding: 14px 18px !important;
            border-radius: 18px !important;
            font-size: 0.95rem !important;
            line-height: 1.6 !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1) !important;
            animation: messageSlide 0.4s cubic-bezier(0.4, 0, 0.2, 1) !important;
            position: relative !important;
        }
        
        @keyframes messageSlide {
            from { opacity: 0; transform: translateY(20px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        
        .message.from-user, .message.user {
            background: rgba(66, 186, 37, 0.35) !important;
            backdrop-filter: blur(8px) !important;
            color: #2c3e50 !important;
            align-self: flex-end !important;
            border-bottom-right-radius: 4px !important;
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
            box-shadow: 0 4px 12px rgba(66, 186, 37, 0.15) !important;
        }
        
        .message.from-agent, .message.support {
            background: linear-gradient(135deg, #e9ecef 0%, #f8f9fa 100%) !important;
            color: #2c3e50 !important;
            align-self: flex-start !important;
            border-bottom-left-radius: 4px !important;
            border: 1px solid #dee2e6 !important;
        }
        
        .system-message {
            align-self: center !important;
            background: linear-gradient(135deg, #fff9e6 0%, #fff3cd 100%) !important;
            padding: 12px 18px !important;
            border-radius: 12px !important;
            color: #856404 !important;
            font-size: 0.9rem !important;
            border: 2px solid #ffc107 !important;
            box-shadow: 0 2px 8px rgba(255, 193, 7, 0.2) !important;
            font-weight: 500 !important;
        }
        
        .typing-indicator {
            display: none !important;
            align-items: center !important;
            gap: 8px !important;
            padding: 12px 16px !important;
            color: #6c757d !important;
            font-style: italic !important;
            font-size: 0.9rem !important;
        }
        
        .typing-indicator.active {
            display: flex !important;
        }
        
        .typing-indicator span {
            width: 8px !important;
            height: 8px !important;
            background: #42ba25 !important;
            border-radius: 50% !important;
            animation: typing 1.4s infinite !important;
        }
        
        .typing-indicator span:nth-child(2) { animation-delay: 0.2s !important; }
        .typing-indicator span:nth-child(3) { animation-delay: 0.4s !important; }
        
        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); opacity: 0.5; }
            30% { transform: translateY(-12px); opacity: 1; }
        }
        
        .chat-input {
            position: relative !important;
            bottom: 0 !important;
            left: 0 !important;
            width: 100% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            background: #ffffff !important;
            border-top: 1px solid #e3e8ef !important;
            padding: 10px 14px !important;
            gap: 10px !important;
            z-index: 10 !important;
            box-sizing: border-box !important;
        }
        
        .chat-input input {
            flex: 1 !important;
            padding: 12px 16px !important;
            font-size: 1rem !important;
            border: 1px solid #dee2e6 !important;
            border-radius: 22px !important;
            background: #f8f9fa !important;
            height: 46px !important;
        }
        
        .chat-input input:focus {
            outline: none !important;
            border-color: #42ba25 !important;
            box-shadow: 0 0 0 3px rgba(66, 186, 37, 0.15) !important;
        }
        
        .chat-input button {
            width: 46px !important;
            height: 46px !important;
            border-radius: 50% !important;
            background: #42ba25 !important;
            color: #fff !important;
            border: none !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            transition: transform 0.2s ease !important;
            box-shadow: 0 4px 10px rgba(66, 186, 37, 0.3) !important;
            cursor: pointer !important;
        }
        
        .chat-input button:hover {
            transform: scale(1.1) !important;
        }
        
        .chat-back {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 36px !important;
            height: 36px !important;
            border-radius: 50% !important;
            margin-right: 10px !important;
            cursor: pointer !important;
            transition: background 0.3s ease, transform 0.2s ease !important;
        }
        
        .chat-back i {
            color: #ffffff !important;
            font-size: 1.1rem !important;
        }
        
        .chat-back:hover {
            background: rgba(255, 255, 255, 0.15) !important;
            transform: scale(1.05) !important;
        }
        
        /* Formulario de incidencias */
        .faq-section h2 {
            color: #2c3e50 !important;
            font-size: 1.5rem !important;
            font-weight: 700 !important;
            margin-bottom: 20px !important;
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
        }
        
        .faq-section h2 i {
            color: #ffc107 !important;
        }
        
        .contacto-content > p {
            text-align: center !important;
            color: #6c757d !important;
            font-size: 1rem !important;
            margin-bottom: 24px !important;
        }
        
        .formulario-contacto {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%) !important;
            border: 2px solid #e3e8ef !important;
            border-radius: 16px !important;
            padding: 28px !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08) !important;
        }
        
        .formulario-contacto h3 {
            color: #2c3e50 !important;
            font-size: 1.2rem !important;
            font-weight: 700 !important;
            margin-bottom: 20px !important;
            padding-bottom: 14px !important;
            border-bottom: 2px solid #42ba25 !important;
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
        }
        
        .form-group {
            margin-bottom: 20px !important;
        }
        
        .form-group label {
            color: #495057 !important;
            font-weight: 600 !important;
            margin-bottom: 8px !important;
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            font-size: 0.95rem !important;
        }
        
        .form-group label i {
            color: #42ba25 !important;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100% !important;
            padding: 12px 16px !important;
            border: 2px solid #dee2e6 !important;
            border-radius: 10px !important;
            font-size: 0.95rem !important;
            transition: all 0.3s ease !important;
            background: #ffffff !important;
            font-family: 'Inter', sans-serif !important;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none !important;
            border-color: #42ba25 !important;
            box-shadow: 0 0 0 4px rgba(66, 186, 37, 0.15) !important;
        }
        
        .form-group textarea {
            resize: vertical !important;
            min-height: 120px !important;
        }
        
        .btn-enviar {
            background: linear-gradient(135deg, #42ba25 0%, #37a31f 100%) !important;
            color: white !important;
            border: none !important;
            padding: 14px 32px !important;
            border-radius: 12px !important;
            font-weight: 700 !important;
            font-size: 1rem !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            cursor: pointer !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 10px !important;
            box-shadow: 0 4px 12px rgba(66, 186, 37, 0.3) !important;
        }
        
        .btn-enviar:hover {
            background: linear-gradient(135deg, #37a31f 0%, #2d8518 100%) !important;
            transform: translateY(-3px) scale(1.02) !important;
            box-shadow: 0 8px 20px rgba(66, 186, 37, 0.4) !important;
        }
        
        .btn-enviar:disabled {
            opacity: 0.6 !important;
            cursor: not-allowed !important;
        }
        
        /* Responsive móvil */
        @media (max-width: 768px) {
            .soporte-header { display: none !important; }
            .soporte-sidebar { display: none !important; }
            
            .main-wrapper,
            .soporte-main,
            .seccion-contenido {
                margin: 0 !important;
                padding: 0 !important;
                width: 100vw !important;
                max-width: 100vw !important;
                border: none !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                background: #ffffff !important;
            }
            
            .soporte-main {
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                width: 100vw !important;
                height: 100vh !important;
                z-index: 999 !important;
            }
            
            .chat-container {
                width: 100% !important;
                height: 100% !important;
                display: flex !important;
                flex-direction: column !important;
                border: none !important;
                border-radius: 0 !important;
            }
            
            .chat-header {
                flex-shrink: 0 !important;
                padding: 14px 18px !important;
                border-radius: 0 !important;
                width: 100% !important;
            }
            
            .chat-messages {
                flex: 1 !important;
                overflow-y: auto !important;
                background: #f8f9fa !important;
                padding: 16px !important;
                height: calc(100vh - 160px) !important;
            }
            
            .message {
                max-width: 92% !important;
                font-size: 0.95rem !important;
            }
            
            .chat-input {
                position: fixed !important;
                bottom: 60px !important;
                left: 0 !important;
                width: 100% !important;
                border-top: 1px solid #e3e8ef !important;
                padding: 12px 14px !important;
                background: #fff !important;
                min-height: 70px !important;
            }
            
            html, body {
                margin: 0 !important;
                padding: 0 !important;
                overflow: hidden !important;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/shop-header.php'; ?>

    <div class="main-wrapper">
        <div class="container">
            <div class="soporte-header">
                <h1>Centro de Atención al Usuario</h1>
                <p>Estamos aquí para ayudarte con cualquier duda o problema</p>
            </div>

            <?php if ($usuario_autenticado): ?>
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <strong>Usuario:</strong> <?= htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Invitado') ?> 
                <?php if ($usuario_id): ?>
                    (ID: <?= $usuario_id ?>)
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="soporte-main">
                <!-- Sección de Chat en Vivo -->
                <div class="seccion-contenido" id="seccion-chat">
                    <div class="chat-container">
                        <div class="chat-header">
                            <div class="chat-back" id="backFromChat">
                                <i class="fas fa-arrow-left"></i>
                            </div>
                            <h3><i class="fas fa-headset"></i> Chat en Vivo</h3>
                            <div class="status">
                                <div class="status-dot"></div>
                                <span>En línea</span>
                            </div>
                        </div>
                        <div class="chat-messages" id="chatMessages">
                            <!-- Los mensajes se cargarán dinámicamente -->
                        </div>
                        <div class="typing-indicator" id="typingIndicator">
                            <i class="fas fa-comment-dots"></i>
                            Escribiendo
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                        <div class="chat-input">
                            <input type="text" id="chatInput" placeholder="Escribe tu mensaje aquí..." autocomplete="off">
                            <button id="sendMessage"><i class="fas fa-paper-plane"></i></button>
                        </div>
                    </div>
                </div>

                <!-- Sección para reportar incidencias -->
                <div class="seccion-contenido" id="seccion-incidencia" style="display: none;">
                    <div class="faq-section">
                        <h2><i class="fas fa-exclamation-triangle"></i> Reportar una Incidencia</h2>
                        <div class="contacto-content">
                            <p>Proporciona los detalles de tu incidencia o reclamo.</p>
                            <div class="formulario-contacto">
                                <h3><i class="fas fa-file-alt"></i> Detalles de la Incidencia</h3>
                                <form id="incidencia-form" method="post" action="">
                                    <div class="form-group">
                                        <label for="incidencia-categoria"><i class="fas fa-tag"></i> Categoría</label>
                                        <select id="incidencia-categoria" name="categoria_id" required>
                                            <option value="" selected disabled>Selecciona una categoría</option>
                                            <?php if (!empty($categorias)): ?>
                                                <?php foreach ($categorias as $c): ?>
                                                    <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['nombre'], ENT_QUOTES, 'UTF-8') ?></option>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <option value="" disabled>No hay categorías disponibles</option>
                                            <?php endif; ?>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label for="incidencia-referencia"><i class="fas fa-barcode"></i> Referencia</label>
                                        <input type="text" id="incidencia-referencia" name="referencia" placeholder="Ej: ENV-12345" required>
                                    </div>

                                    <div class="form-group">
                                        <label for="incidencia-prioridad"><i class="fas fa-flag"></i> Prioridad</label>
                                        <select id="incidencia-prioridad" name="prioridad" required>
                                            <option value="baja">Baja</option>
                                            <option value="media" selected>Media</option>
                                            <option value="alta">Alta</option>
                                            <option value="urgente">Urgente</option>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label for="incidencia-descripcion"><i class="fas fa-align-left"></i> Descripción</label>
                                        <textarea id="incidencia-descripcion" name="descripcion" placeholder="Describe tu problema en detalle..." required></textarea>
                                    </div>

                                    <button type="submit" class="btn-enviar" <?= empty($categorias) ? 'disabled' : '' ?>>
                                        <i class="fas fa-paper-plane"></i> Enviar Incidencia
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/shop-footer.php'; ?>

    <script>
    document.getElementById('backFromChat')?.addEventListener('click', function() {
        if (document.referrer) {
            window.history.back();
        } else {
            window.location.href = 'shop-requests-index.php';
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        // Navegación entre secciones
        const categoriaLinks = document.querySelectorAll('.categorias-lista a');
        const secciones = document.querySelectorAll('.seccion-contenido');

        categoriaLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                categoriaLinks.forEach(l => l.parentElement.classList.remove('categoria-activa'));
                this.parentElement.classList.add('categoria-activa');
                secciones.forEach(seccion => seccion.style.display = 'none');
                const seccionId = 'seccion-' + this.getAttribute('data-categoria');
                const seccionElement = document.getElementById(seccionId);
                if (seccionElement) {
                    seccionElement.style.display = 'block';
                    if (seccionId === 'seccion-chat' && typeof window.supportChat !== 'undefined') {
                        window.supportChat.loadChat();
                    }
                }
            });
        });

        // Envío del formulario de incidencias
        $('#incidencia-form').on('submit', function(e) {
            e.preventDefault();

            const categoria_id = $('#incidencia-categoria').val();
            const referencia = $('#incidencia-referencia').val();
            const prioridad = $('#incidencia-prioridad').val();
            const descripcion = $('#incidencia-descripcion').val();

            if (!categoria_id || !referencia || !prioridad || !descripcion) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Campos requeridos',
                    text: 'Completa todos los campos.',
                    confirmButtonColor: '#42ba25'
                });
                return;
            }

            const submitBtn = $(this).find('.btn-enviar');
            const originalText = submitBtn.html();
            submitBtn.html('<i class="fas fa-spinner fa-spin"></i> Enviando...').prop('disabled', true);

            $.ajax({
                url: 'soporte/crear_incidencia.php',
                type: 'POST',
                data: {
                    categoria_id: categoria_id,
                    referencia: referencia,
                    prioridad: prioridad,
                    descripcion: descripcion
                },
                dataType: 'json',
                cache: false,
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Incidencia Registrada',
                            text: 'Ticket: #' + response.ticket_id,
                            confirmButtonText: 'Ver en chat',
                            confirmButtonColor: '#42ba25'
                        }).then(() => {
                            $('.categorias-lista a').parent().removeClass('categoria-activa');
                            $('.categorias-lista a[data-categoria="chat"]').parent().addClass('categoria-activa');
                            $('.seccion-contenido').hide();
                            $('#seccion-chat').show();
                            if (window.supportChat) {
                                window.supportChat.chatId = response.chat_id;
                                window.supportChat.loadMessages?.();
                            }
                        });
                        $('#incidencia-form')[0].reset();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message || 'No se pudo registrar.',
                            confirmButtonColor: '#42ba25'
                        });
                    }
                },
                error: function(xhr, status) {
                    let msg = 'No se pudo conectar con el servidor.';
                    if (xhr.status === 404) msg = 'Endpoint no encontrado (404).';
                    else if (xhr.status === 500) msg = 'Error interno (500).';
                    Swal.fire({ icon: 'error', title: 'Error', text: msg, confirmButtonColor: '#42ba25' });
                },
                complete: function() {
                    submitBtn.html(originalText).prop('disabled', false);
                }
            });
        });

        // Enter para enviar mensaje
        document.getElementById('chatInput')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                document.getElementById('sendMessage')?.click();
            }
        });

        // Activar padding para barra inferior
        if (document.querySelector('.mobile-bottom-nav')) {
            document.body.classList.add('has-bottom-nav');
        }
    });
    </script>

    <!-- Chat en tiempo real -->
    <script src="soporte/chat_realtime.js?v=2"></script>
</body>
</html>