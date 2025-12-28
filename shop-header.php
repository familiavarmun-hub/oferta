<?php
// Header para la carpeta SHOP
// Todas las rutas están ajustadas para funcionar desde /shop/

// Iniciar la sesión si no está ya iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Incluir conexión a base de datos si no está incluida
if (!isset($conexion)) {
    require_once __DIR__ . '/../conexion.php';
}

// Incluir funciones de laurel si existe
if (file_exists(__DIR__ . '/../funciones_laurel.php')) {
    require_once __DIR__ . '/../funciones_laurel.php';
}

// Verificamos si hay un usuario en sesión
if (isset($_SESSION['usuario_id'])) {
    $usuario_actual_id = $_SESSION['usuario_id'];

    // Consulta para contar mensajes no leídos (mensajes generales)
    $query_unread = "SELECT COUNT(*) AS total_unread 
                     FROM mensajes 
                     WHERE receptor_id = :usuario_id 
                       AND leido = 0";
    $stmt_unread = $conexion->prepare($query_unread);
    $stmt_unread->bindParam(':usuario_id', $usuario_actual_id, PDO::PARAM_INT);
    $stmt_unread->execute();
    $resultado_unread = $stmt_unread->fetch(PDO::FETCH_ASSOC);
    $total_unread = $resultado_unread['total_unread'] ?? 0;

    // Consulta para contar mensajes no leídos del SHOP CHAT
    $query_shop_unread = "SELECT COUNT(*) AS total_shop_unread 
                          FROM shop_chat_messages 
                          WHERE receiver_id = :usuario_id 
                            AND is_read = 0";
    $stmt_shop_unread = $conexion->prepare($query_shop_unread);
    $stmt_shop_unread->bindParam(':usuario_id', $usuario_actual_id, PDO::PARAM_INT);
    $stmt_shop_unread->execute();
    $resultado_shop_unread = $stmt_shop_unread->fetch(PDO::FETCH_ASSOC);
    $total_shop_unread = $resultado_shop_unread['total_shop_unread'] ?? 0;

    // Consulta para obtener el estado de verificación desde accounts
    $sql_user = "SELECT verificado FROM accounts WHERE id = :usuario_id LIMIT 1";
    $stmt_user = $conexion->prepare($sql_user);
    $stmt_user->bindParam(':usuario_id', $usuario_actual_id, PDO::PARAM_INT);
    $stmt_user->execute();
    $userData = $stmt_user->fetch(PDO::FETCH_ASSOC);
    $verificado = $userData['verificado'] ?? 0;

    // Consulta para obtener el promedio de valoraciones del usuario
    $sql_rating = "SELECT AVG(valoracion) AS promedio FROM comentarios WHERE usuario_id = :usuario_id";
    $stmt_rating = $conexion->prepare($sql_rating);
    $stmt_rating->bindParam(':usuario_id', $usuario_actual_id, PDO::PARAM_INT);
    $stmt_rating->execute();
    $ratingData = $stmt_rating->fetch(PDO::FETCH_ASSOC);
    $promedio_rating = !is_null($ratingData['promedio']) ? round($ratingData['promedio'], 1) : 0;
} else {
    $total_unread = 0;
    $total_shop_unread = 0;
    $verificado = 0;
    $promedio_rating = 0;
}

// Función para obtener URL de imagen de perfil de forma segura
function getProfileImageUrl($userId) {
    if (isset($userId) && $userId > 0) {
        return '../mostrar_imagen.php?id=' . (int)$userId;
    }
    return '../Imagenes/user-default.jpg';
}

// Obtener la URL de la imagen de perfil
$profileImageUrl = getProfileImageUrl($_SESSION['usuario_id'] ?? null);

// Generar HTML de imagen de perfil
if (isset($_SESSION['usuario_id']) && function_exists('mostrarImagenConLaurel')) {
    $profileImageHtml = mostrarImagenConLaurel($profileImageUrl, $promedio_rating, $verificado);
} else if (isset($_SESSION['usuario_id'])) {
    // Usuario logueado pero sin función de laurel
    $profileImageHtml = '<img src="' . htmlspecialchars($profileImageUrl) . '" alt="Perfil" class="profile-img hamburger-profile-img">';
} else {
    // Usuario no logueado
    $profileImageHtml = '<img src="../Imagenes/user-default.jpg" alt="Perfil" class="profile-img hamburger-profile-img">';
}

$isLoggedIn = isset($_SESSION['username']) && $_SESSION['username'] !== "Invitado";
$username = $isLoggedIn ? $_SESSION['username'] : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SendVialo Requests</title>
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .profile-img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            position: relative;
        }
        .hamburger-profile-img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            cursor: pointer;
            border: 2px solid #ddd;
            transition: border-color 0.3s ease;
        }
        .hamburger-profile-img:hover {
            border-color: #007bff;
        }
        .profile-img-container {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 50px;
            text-align: center;
        }
        .verificacion-wrapper {
            position: absolute;
            bottom: -17px;
            right: -16px;
            z-index: 6;
        }
        .verificacion-insignia {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 50px;
            height: 50px;
            z-index: 5;
        }
        .laurel-crown {
            position: absolute;
            top: 45%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 70px;
            height: 70px;
            z-index: 1111;
        }
        
        /* Estilos para el badge de chat */
        .badge {
            background: #dc3545;
            color: white;
            border-radius: 10px;
            padding: 2px 6px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 5px;
            display: inline-block;
            min-width: 18px;
            text-align: center;
        }
        
        .mobile-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            border-radius: 10px;
            padding: 2px 6px;
            font-size: 10px;
            font-weight: 600;
            min-width: 16px;
            text-align: center;
        }
        
        .chat-link {
            position: relative;
        }
        
        .chat-link .badge {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.1);
            }
        }
        
        @media screen and (max-width: 768px) {
            .profile-img {
                width: 40px;
                height: 40px;
            }
            .laurel-crown {
                width: 60px;
                height: 60px;
                top: 60%;
            }
            .hamburger-profile-img {
                width: 35px;
                height: 35px;
            }
        }
        
        /* Estilos para imagen de perfil en barra inferior móvil */
        .nav-profile-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .nav-profile-wrapper .profile-img,
        .nav-profile-wrapper .hamburger-profile-img {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #ddd;
        }
        .nav-profile-wrapper .profile-img-container {
            width: 28px;
            height: 28px;
        }
        .nav-profile-wrapper .laurel-crown {
            width: 40px;
            height: 40px;
        }
        .nav-profile-wrapper .verificacion-insignia {
            width: 28px;
            height: 28px;
        }
    </style>
</head>
<body>
<header>
    <nav id="menu">
        <div id="logo">
            <a href="shop-requests-index.php">
                <img src="../Imagenes/logo_sendvialo_shop.png" alt="SendVialo Shop" class="logo">
            </a>
        </div>
        
        <?php if ($isLoggedIn): ?>
            <div class="hamburger-icon" aria-label="Menú">
                <?php echo $profileImageHtml; ?>
            </div>
        <?php else: ?>
            <div class="mobile-login-btn">
                <a href="shop-login.php">Iniciar Sesión</a>
            </div>
        <?php endif; ?>
        
        <div id="links" class="<?php echo $isLoggedIn ? '' : 'guest-menu'; ?>">
            <ul class="main-menu">
                <?php if ($isLoggedIn): ?>
                    <li class="nav-item">
                        <a href="shop-request-create.php">
                            <i class="fas fa-plus-circle"></i> Publicar Solicitud
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="shop-my-requests.php">
                            <i class="fas fa-clipboard-list"></i> Mis Solicitudes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="shop-my-proposals.php">
                            <i class="fas fa-handshake"></i> Mis Propuestas
                        </a>
                    </li>
                    
                    <!-- Botón de Chat Shop -->
                    <li class="nav-item">
                        <a href="shop-chat-list.php" class="chat-link">
                            <i class="fas fa-comments"></i> Chats Shop
                            <?php if ($total_shop_unread > 0): ?>
                                <span class="badge"><?php echo $total_shop_unread; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    
                    <li class="nav-item profile-menu">
                        <div class="profile-trigger">
                            <?php echo $profileImageHtml; ?>
                            <i class="fas fa-chevron-down profile-arrow"></i>
                        </div>
                        <ul class="sub-menus">
                            <li><a href="perfil_shop.php"><i class="fas fa-user-circle"></i> Perfil</a></li>
                            <li class="messages-submenu">
                                <a href="shop-chat.php?username=<?php echo urlencode($username); ?>">
                                    <i class="fa-regular fa-message"></i> Mensajes
                                    <?php if ($total_unread > 0): ?>
                                        <span class="badge"><?php echo $total_unread; ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                            <li>
                                <a href="shop-logout.php">
                                    <i class="fas fa-sign-out-alt"></i> Cerrar sesión
                                </a>
                            </li>
                        </ul>
                    </li>
                    
                    <!-- Móvil -->
                    <li class="nav-item mobile-only profile-mobile">
                        <a href="perfil_shop.php">
                            <i class="fas fa-user-circle"></i> Perfil
                        </a>
                    </li>
                    
                    <!-- Chat Shop en móvil -->
                    <li class="nav-item mobile-only">
                        <a href="shop-chat-list.php" class="chat-link">
                            <i class="fas fa-comments"></i> Chats Shop
                            <?php if ($total_shop_unread > 0): ?>
                                <span class="mobile-badge"><?php echo $total_shop_unread; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    
                    <li class="nav-item mobile-only messages-mobile">
                        <a href="shop-chat.php?username=<?php echo urlencode($username); ?>">
                            <i class="fa-regular fa-message"></i> Mensajes
                            <?php if ($total_unread > 0): ?>
                                <span class="mobile-badge"><?php echo $total_unread; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item mobile-only logout-mobile">
                        <a href="shop-logout.php">
                            <i class="fas fa-sign-out-alt"></i> Cerrar sesión
                        </a>
                    </li>
                <?php else: ?>
                    <li class="nav-item login-btn">
                        <a href="shop-login.php">Iniciar Sesión</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>
</header>

<div class="header-spacer"></div>
<style>
    /* Padding inferior para dispositivos móviles con barra de navegación */
    body.has-bottom-nav {
        padding-bottom: 80px;
    }
    
    @media screen and (min-width: 769px) {
        body.has-bottom-nav {
            padding-bottom: 0;
        }
    }
</style>

<?php if ($isLoggedIn): ?>
<nav class="mobile-bottom-nav">
    <ul>
        <li>
            <a href="shop-requests-index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'shop-requests-index.php' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i>
                <span class="mobile-nav-label">Inicio</span>
            </a>
        </li>
        <li>
            <a href="shop-my-requests.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'shop-my-requests.php' ? 'active' : ''; ?>">
                <i class="fas fa-clipboard-list"></i>
                <span class="mobile-nav-label">Solicitudes</span>
            </a>
        </li>
        
        <!-- Botón de Chat en barra inferior móvil -->
        <li>
            <a href="shop-chat-list.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'shop-chat-list.php' || basename($_SERVER['PHP_SELF']) == 'shop-chat.php' ? 'active' : ''; ?>">
                <div style="position: relative;">
                    <i class="fas fa-comments"></i>
                    <?php if ($total_shop_unread > 0): ?>
                        <span class="mobile-badge" style="top: -8px; right: -8px;"><?php echo $total_shop_unread; ?></span>
                    <?php endif; ?>
                </div>
                <span class="mobile-nav-label">Chats</span>
            </a>
        </li>
        
        <li>
            <a href="shop-my-proposals.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'shop-my-proposals.php' ? 'active' : ''; ?>">
                <i class="fas fa-handshake"></i>
                <span class="mobile-nav-label">Propuestas</span>
            </a>
        </li>
        <li>
            <a href="perfil_shop.php">
                <div class="nav-profile-wrapper">
                    <?php echo $profileImageHtml; ?>
                </div>
                <span class="mobile-nav-label">Perfil</span>
            </a>
        </li>
    </ul>
</nav>
<?php endif; ?>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const hamburger = document.querySelector('.hamburger-icon');
    const linksSection = document.getElementById('links');
    
    if (hamburger) {
        hamburger.addEventListener('click', function() {
            linksSection.classList.toggle('menu-visible');
        });
    }

    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 768 && 
            linksSection && 
            !linksSection.contains(e.target) && 
            hamburger && 
            !hamburger.contains(e.target)) {
            linksSection.classList.remove('menu-visible');
        }
    });
    
    window.addEventListener('scroll', function() {
        const menu = document.getElementById('menu');
        if (window.scrollY > 10) {
            menu.classList.add('scrolled');
        } else {
            menu.classList.remove('scrolled');
        }
    });
    
    if (document.querySelector('.mobile-bottom-nav')) {
        document.body.classList.add('has-bottom-nav');
    }
    
    // Actualizar contador de mensajes no leídos cada 10 segundos
    setInterval(function() {
        fetch('shop-chat-api.php?action=get_all_unread')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.unread_by_proposal) {
                    const total = Object.values(data.unread_by_proposal).reduce((a, b) => a + b, 0);
                    
                    // Actualizar todos los badges de chat
                    document.querySelectorAll('.chat-link .badge, .mobile-badge').forEach(badge => {
                        if (total > 0) {
                            badge.textContent = total;
                            badge.style.display = 'inline-block';
                        } else {
                            badge.style.display = 'none';
                        }
                    });
                    
                    // Actualizar título si hay mensajes nuevos
                    if (total > 0 && !document.title.startsWith('(')) {
                        document.title = `(${total}) ${document.title}`;
                    } else if (total === 0 && document.title.startsWith('(')) {
                        document.title = document.title.replace(/^\(\d+\)\s*/, '');
                    }
                }
            })
            .catch(err => console.error('Error actualizando contador:', err));
    }, 10000);
});
</script>
</body>
</html>