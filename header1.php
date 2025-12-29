<?php
// header1.php (INCLUDE LIMPIO) ✅
// - NO tiene <!DOCTYPE>, <html>, <head>, <body> ni </body></html>
// - Mantiene tu lógica de sesión, BD, insignias, notificaciones y badges
// - ✅ ÚNICO cambio: evitar choque con otras páginas (perfil_shop.php) cambiando .profile-menu -> .sv-profile-menu

$base_path = dirname(__FILE__);
$parent_path = dirname($base_path);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once($parent_path . '/config.php');
require_once($base_path . '/insignias1.php');
require_once($base_path . '/shop-notifications-helper.php');

// ====== Estado de sesión ======
$isLoggedIn = isset($_SESSION['username']) && $_SESSION['username'] !== "Invitado";
$username   = $isLoggedIn ? $_SESSION['username'] : '';
$usuario_actual_id = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : 0;

// ====== Variables por defecto ======
$total_unread = 0;
$total_shop_unread = 0;
$total_notifications_unread = 0;
$verificado = 0;
$promedio_rating = 0;

// ====== Consultas si hay usuario ======
if ($usuario_actual_id > 0) {
    // Mensajes generales no leídos
    $query_unread = "SELECT COUNT(*) AS total_unread 
                     FROM mensajes 
                     WHERE receptor_id = :usuario_id 
                       AND leido = 0";
    $stmt_unread = $conexion->prepare($query_unread);
    $stmt_unread->bindParam(':usuario_id', $usuario_actual_id, PDO::PARAM_INT);
    $stmt_unread->execute();
    $resultado_unread = $stmt_unread->fetch(PDO::FETCH_ASSOC);
    $total_unread = (int)($resultado_unread['total_unread'] ?? 0);

    // Mensajes no leídos SHOP CHAT
    $query_shop_unread = "SELECT COUNT(*) AS total_shop_unread 
                          FROM shop_chat_messages 
                          WHERE receiver_id = :usuario_id 
                            AND is_read = 0";
    $stmt_shop_unread = $conexion->prepare($query_shop_unread);
    $stmt_shop_unread->bindParam(':usuario_id', $usuario_actual_id, PDO::PARAM_INT);
    $stmt_shop_unread->execute();
    $resultado_shop_unread = $stmt_shop_unread->fetch(PDO::FETCH_ASSOC);
    $total_shop_unread = (int)($resultado_shop_unread['total_shop_unread'] ?? 0);

    // Notificaciones no leídas
    $total_notifications_unread = (int) getUnreadNotificationsCount($usuario_actual_id);

    // Estado de verificación
    $sql_user = "SELECT verificado FROM accounts WHERE id = :usuario_id LIMIT 1";
    $stmt_user = $conexion->prepare($sql_user);
    $stmt_user->bindParam(':usuario_id', $usuario_actual_id, PDO::PARAM_INT);
    $stmt_user->execute();
    $userData = $stmt_user->fetch(PDO::FETCH_ASSOC);
    $verificado = (int)($userData['verificado'] ?? 0);

    // Promedio valoraciones
    $sql_rating = "SELECT AVG(valoracion) AS promedio FROM comentarios WHERE usuario_id = :usuario_id";
    $stmt_rating = $conexion->prepare($sql_rating);
    $stmt_rating->bindParam(':usuario_id', $usuario_actual_id, PDO::PARAM_INT);
    $stmt_rating->execute();
    $ratingData = $stmt_rating->fetch(PDO::FETCH_ASSOC);
    $promedio_rating = !is_null($ratingData['promedio']) ? round((float)$ratingData['promedio'], 1) : 0;
}

// ====== Imagen de perfil segura ======
function getProfileImageUrl($userId) {
    if (!empty($userId) && (int)$userId > 0) {
        return '../mostrar_imagen.php?id=' . (int)$userId;
    }
    return '../Imagenes/user-default.jpg';
}

$profileImageUrl = getProfileImageUrl($usuario_actual_id);

// HTML imagen con laurel + verificación (si logueado)
if ($usuario_actual_id > 0) {
    $profileImageHtml = mostrarImagenConLaurel($profileImageUrl, $promedio_rating, $verificado);
} else {
    $profileImageHtml = '<img src="../Imagenes/user-default.jpg" alt="Perfil" class="hamburger-profile-img">';
}
?>

<!-- ✅ Dependencias del header (cárgalas aquí o en tu <head> principal) -->
<link rel="stylesheet" href="../css/header.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* ==========================================
   ESTILOS BASE DEL HEADER
   ========================================== */
.profile-img {
  width: 50px; height: 50px; border-radius: 50%;
  object-fit: cover; position: relative;
}
.profile-img-container {
  position: relative; display: inline-block;
  width: 50px; height: 50px; text-align: center;
}
.verificacion-wrapper { position: absolute; bottom: -17px; right: -16px; z-index: 6; }
.verificacion-insignia { position: absolute; bottom: 0; right: 0; width: 50px; height: 50px; z-index: 5; }
.laurel-crown {
  position: absolute; top: 45%; left: 50%;
  transform: translate(-50%, -50%);
  width: 70px; height: 70px; z-index: 1111;
}

/* ==========================================
   BOTÓN PUBLICAR SOLICITUD (MÓVIL/TABLET)
   ========================================== */
.mobile-publish-btn{
  display:none;
  background: linear-gradient(135deg,#41ba0d,#5dcb2a);
  color:#fff; border:none;
  padding:10px 20px;
  border-radius:25px;
  font-weight:700; font-size:14px;
  cursor:pointer; align-items:center; gap:8px;
  transition: all .3s;
  text-decoration:none;
  box-shadow: 0 4px 16px rgba(65,186,13,.4);
  position:relative; overflow:hidden;
}
.mobile-publish-btn::before{
  content:'';
  position:absolute; top:0; left:-100%;
  width:100%; height:100%;
  background: linear-gradient(90deg,transparent,rgba(255,255,255,.3),transparent);
  transition:left .5s;
}
.mobile-publish-btn:hover::before{ left:100%; }
.mobile-publish-btn:hover{
  background: linear-gradient(135deg,#379909,#4fb822);
  transform: translateY(-2px) scale(1.02);
  box-shadow: 0 6px 20px rgba(65,186,13,.5);
  color:#fff;
}
.mobile-publish-btn:active{ transform: translateY(0) scale(.98); }
.mobile-publish-btn i{ font-size:18px; animation:pulse-icon 2s infinite; }
@keyframes pulse-icon{ 0%,100%{transform:scale(1)} 50%{transform:scale(1.15)} }

/* ==========================================
   NOTIFICACIONES
   ========================================== */
.notifications-wrapper{ position:relative; display:inline-block; }
.notifications-icon{
  position:relative; font-size:20px; color:#333;
  cursor:pointer; transition: color .3s ease;
  text-decoration:none; display:inline-flex;
  align-items:center; padding:8px 12px; border-radius:8px;
}
.notifications-icon:hover{ background: rgba(76,175,80,.1); color:#4CAF50; }
.notifications-badge{
  position:absolute; top:2px; right:2px;
  background:#f44336; color:#fff; border-radius:10px;
  padding:2px 6px; font-size:11px; font-weight:700;
  min-width:18px; height:18px;
  display:flex; align-items:center; justify-content:center;
  animation: notificationPulse 2s infinite;
  box-shadow: 0 2px 5px rgba(244,67,54,.4);
}
@keyframes notificationPulse{ 0%,100%{transform:scale(1)} 50%{transform:scale(1.15)} }

.badge{
  background:#dc3545; color:#fff; border-radius:10px;
  padding:2px 6px; font-size:11px; font-weight:600;
  margin-left:5px; display:inline-block;
  min-width:18px; text-align:center;
}
.mobile-badge{
  position:absolute; top:-5px; right:-5px;
  background:#dc3545; color:#fff; border-radius:10px;
  padding:2px 6px; font-size:10px; font-weight:600;
  min-width:16px; text-align:center;
}
.chat-link{ position:relative; }
.chat-link .badge{ animation:pulse 2s infinite; }
@keyframes pulse{ 0%,100%{transform:scale(1)} 50%{transform:scale(1.1)} }

/* ==========================================
   ESCRITORIO (min-width: 769px)
   ========================================== */
@media screen and (min-width: 769px){
  .mobile-only{ display:none !important; visibility:hidden !important; height:0 !important; overflow:hidden !important; margin:0 !important; padding:0 !important; }
  .mobile-login-btn{ display:none !important; }
  .mobile-bottom-nav{ display:none !important; }
  .mobile-publish-btn{ display:none !important; }
  .desktop-only{ display:flex !important; }
  .hamburger-icon{ display:flex !important; align-items:center !important; cursor:pointer !important; z-index:1001 !important; }

  /* ✅ Cambiado: .profile-menu -> .sv-profile-menu */
  .sv-profile-menu{ position:relative !important; display:flex !important; }
  .sv-profile-menu .profile-trigger{ display:flex !important; align-items:center !important; gap:8px !important; cursor:pointer !important; }
  .sv-profile-menu .sub-menus{
    display:none !important; position:absolute !important;
    top:100% !important; right:0 !important;
    background:#fff !important; border-radius:8px !important;
    box-shadow:0 4px 15px rgba(0,0,0,.15) !important;
    min-width:200px !important; z-index:1000 !important;
    padding:8px 0 !important; list-style:none !important;
  }
  .sv-profile-menu:hover .sub-menus{ display:block !important; }
  .sv-profile-menu .sub-menus li{ display:block !important; margin:0 !important; padding:0 !important; }
  .sv-profile-menu .sub-menus li a{
    display:flex !important; align-items:center !important; gap:10px !important;
    padding:10px 16px !important; color:#333 !important;
    transition: background .2s !important; white-space:nowrap !important;
    text-decoration:none !important;
  }
  .sv-profile-menu .sub-menus li a:hover{ background: rgba(76,175,80,.1) !important; }
}

/* ==========================================
   MÓVIL (max-width: 768px)
   ========================================== */
@media screen and (max-width: 768px){
  .desktop-only{ display:none !important; visibility:hidden !important; height:0 !important; overflow:hidden !important; margin:0 !important; padding:0 !important; }

  /* ✅ Cambiado: .profile-menu -> .sv-profile-menu */
  .sv-profile-menu{ display:none !important; visibility:hidden !important; height:0 !important; overflow:hidden !important; }

  .hamburger-icon{ display:none !important; visibility:hidden !important; height:0 !important; overflow:hidden !important; }
  .mobile-publish-btn{ display:flex !important; }
  .mobile-only{ display:block !important; visibility:visible !important; height:auto !important; }

  .profile-img{ width:40px !important; height:40px !important; }
  .laurel-crown{ width:60px !important; height:60px !important; top:60% !important; }
  .notifications-icon{ font-size:18px !important; padding:6px 10px !important; }

  .hamburger-profile-img{
    width:40px !important; height:40px !important;
    border-radius:50% !important; object-fit:cover !important;
    border:2px solid #fff !important;
  }

  #links{
    display:none !important;
    position:fixed !important;
    top:60px !important; left:0 !important; right:0 !important;
    background:#4CAF50 !important;
    padding:15px !important;
    max-height: calc(100vh - 60px - 80px) !important;
    overflow-y:auto !important;
    z-index:999 !important;
    box-shadow:0 4px 10px rgba(0,0,0,.2) !important;
  }
  #links.menu-visible{ display:block !important; }

  #links .main-menu{
    display:flex !important; flex-direction:column !important;
    gap:5px !important; list-style:none !important;
    margin:0 !important; padding:0 !important;
  }
  #links .main-menu > li{ width:100% !important; margin:0 !important; }
  #links .main-menu > li > a{
    display:flex !important; align-items:center !important; gap:12px !important;
    padding:12px 15px !important;
    color:#fff !important; font-size:16px !important;
    border-radius:8px !important;
    transition: background .2s !important;
    text-decoration:none !important;
  }
  #links .main-menu > li > a:hover{ background: rgba(255,255,255,.15) !important; }

  .profile-mobile{
    margin-top:15px !important; padding-top:15px !important;
    border-top:1px solid rgba(255,255,255,.3) !important;
  }
  .logout-mobile a{ color:#ffcdd2 !important; }

  .mobile-bottom-nav{
    display:flex !important;
    position:fixed !important;
    bottom:0 !important; left:0 !important; right:0 !important;
    background:#fff !important;
    box-shadow:0 -2px 10px rgba(0,0,0,.1) !important;
    z-index:1000 !important;
    padding:8px 0 !important;
    padding-bottom: max(8px, env(safe-area-inset-bottom)) !important;
  }
  .mobile-bottom-nav ul{
    display:flex !important; justify-content:space-around !important;
    width:100% !important; margin:0 !important; padding:0 !important;
    list-style:none !important;
  }
  .mobile-bottom-nav li{ flex:1 !important; text-align:center !important; }
  .mobile-bottom-nav a{
    display:flex !important; flex-direction:column !important;
    align-items:center !important; gap:4px !important;
    color:#666 !important; font-size:11px !important;
    text-decoration:none !important; padding:5px !important;
  }
  .mobile-bottom-nav a.active{ color:#4CAF50 !important; }
  .mobile-bottom-nav i{ font-size:20px !important; }
  .mobile-nav-label{ font-size:10px !important; }

  body.has-bottom-nav{ padding-bottom:70px !important; }

  .nav-profile-wrapper{ width:28px !important; height:28px !important; }
  .nav-profile-wrapper img,
  .nav-profile-wrapper .profile-img-container{ width:28px !important; height:28px !important; }
  .nav-profile-wrapper .laurel-crown{ width:40px !important; height:40px !important; }
}

/* Extra pequeño */
@media screen and (max-width:480px){
  .mobile-publish-btn{ padding:9px 16px !important; font-size:13px !important; gap:6px !important; border-radius:20px !important; }
  .mobile-publish-btn i{ font-size:16px !important; }
  .mobile-publish-btn span{ display:inline !important; }
}
@media screen and (max-width:360px){
  .mobile-publish-btn{ padding:8px 14px !important; font-size:12px !important; }
  .mobile-publish-btn i{ font-size:15px !important; }
}
</style>

<header>
  <nav id="menu">
    <div id="logo">
      <a href="shop-requests-index.php">
        <img src="../Imagenes/logo_sendvialo_shop.png" alt="SendVialo Shop" class="logo">
      </a>
    </div>

    <?php if ($isLoggedIn): ?>
      <!-- Botón Publicar Solicitud - Solo Móvil/Tablet -->
      <a href="shop-request-create.php" class="mobile-publish-btn">
        <i class="fas fa-plus-circle"></i>
        <span>¿Quién me lo trae…?</span>
      </a>
    <?php else: ?>
      <div class="mobile-login-btn">
        <a href="shop-login.php">Iniciar Sesión</a>
      </div>
    <?php endif; ?>

    <div id="links" class="<?php echo $isLoggedIn ? '' : 'guest-menu'; ?>">
      <ul class="main-menu">
        <?php if ($isLoggedIn): ?>

          <!-- ===== SOLO ESCRITORIO ===== -->
          <li class="nav-item desktop-only">
            <a href="shop-request-create.php"><i class="fas fa-plus-circle"></i> ¿Quién me lo trae…?</a>
          </li>
          <li class="nav-item desktop-only">
            <a href="shop-my-requests.php"><i class="fas fa-clipboard-list"></i> Mis Solicitudes</a>
          </li>
          <li class="nav-item desktop-only">
            <a href="shop-my-proposals.php"><i class="fas fa-handshake"></i> Mis Propuestas</a>
          </li>
          <li class="nav-item desktop-only">
            <a href="shop-chat-list.php" class="chat-link">
              <i class="fas fa-comments"></i> Chats Shop
              <?php if ($total_shop_unread > 0): ?>
                <span class="badge"><?php echo $total_shop_unread; ?></span>
              <?php endif; ?>
            </a>
          </li>

          <!-- Notificaciones escritorio -->
          <li class="nav-item notifications-wrapper desktop-only">
            <a href="shop-notifications.php" class="notifications-icon" title="Notificaciones">
              <i class="fas fa-bell"></i>
              <?php if ($total_notifications_unread > 0): ?>
                <span class="notifications-badge" id="desktop-notifications-badge">
                  <?php echo $total_notifications_unread > 99 ? '99+' : $total_notifications_unread; ?>
                </span>
              <?php endif; ?>
            </a>
          </li>

       <li class="nav-item sv-profile-menu desktop-only">
  <a href="shop_mi_perfil.php" class="profile-trigger" style="text-decoration: none; display: flex; align-items: center; gap: 8px;">
    <?php echo $profileImageHtml; ?>
    <i class="fas fa-chevron-down profile-arrow"></i>
  </a>
  <ul class="sub-menus">
                
                
              <li><a href="shop_mi_perfil.php"><i class="fas fa-user-circle"></i> Perfil</a></li>
              <li>
                <a href="shop-chat.php?username=<?php echo urlencode($username); ?>">
                  <i class="fa-regular fa-message"></i> Mensajes
                  <?php if ($total_unread > 0): ?>
                    <span class="badge"><?php echo $total_unread; ?></span>
                  <?php endif; ?>
                </a>
              </li>
              <li><a href="shop-verificacion-qr.php"><i class="fas fa-qrcode"></i> Mis Entregas</a></li>
              <li><a href="shop-logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a></li>
            </ul>
          </li>

          <!-- ===== SOLO MÓVIL (menú desplegable) ===== -->
          <li class="nav-item mobile-only"><a href="shop_mi_perfil.php"><i class="fas fa-user-circle"></i> Perfil</a></li>
          <li class="nav-item mobile-only"><a href="shop-my-requests.php"><i class="fas fa-clipboard-list"></i> Mis Solicitudes</a></li>

          <li class="nav-item mobile-only">
            <a href="shop-chat-list.php" class="chat-link">
              <i class="fas fa-comments"></i> Chats
              <?php if ($total_shop_unread > 0): ?>
                <span class="mobile-badge"><?php echo $total_shop_unread; ?></span>
              <?php endif; ?>
            </a>
          </li>

          <li class="nav-item mobile-only">
            <a href="shop-notifications.php" class="chat-link">
              <i class="fas fa-bell"></i> Notificaciones
              <?php if ($total_notifications_unread > 0): ?>
                <span class="mobile-badge" id="mobile-notifications-badge">
                  <?php echo $total_notifications_unread > 99 ? '99+' : $total_notifications_unread; ?>
                </span>
              <?php endif; ?>
            </a>
          </li>

          <li class="nav-item mobile-only"><a href="shop-verificacion-qr.php"><i class="fas fa-qrcode"></i> Mis Entregas</a></li>

        <?php else: ?>
          <li class="nav-item login-btn"><a href="shop-login.php">Iniciar Sesión</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </nav>
</header>

<div class="header-spacer"></div>

<?php if ($isLoggedIn): ?>
<nav class="mobile-bottom-nav">
  <ul>
    <li>
      <a href="shop-my-proposals.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'shop-my-proposals.php' ? 'active' : ''; ?>">
        <i class="fas fa-handshake"></i>
        <span class="mobile-nav-label">Propuestas</span>
      </a>
    </li>
    <li>
      <a href="shop-my-requests.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'shop-my-requests.php' ? 'active' : ''; ?>">
        <i class="fas fa-clipboard-list"></i>
        <span class="mobile-nav-label">Solicitudes</span>
      </a>
    </li>
    <li>
      <a href="shop-chat-list.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'shop-chat-list.php' || basename($_SERVER['PHP_SELF']) == 'shop-chat.php') ? 'active' : ''; ?>">
        <div style="position: relative; display: inline-block;">
          <i class="fas fa-comments"></i>
          <?php if ($total_shop_unread > 0): ?>
            <span class="mobile-badge" style="top:-8px; right:-8px;"><?php echo $total_shop_unread; ?></span>
          <?php endif; ?>
        </div>
        <span class="mobile-nav-label">Chats</span>
      </a>
    </li>
    <li>
      <a href="shop-notifications.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'shop-notifications.php' ? 'active' : ''; ?>">
        <div style="position: relative; display: inline-block;">
          <i class="fas fa-bell"></i>
          <?php if ($total_notifications_unread > 0): ?>
            <span class="mobile-badge" style="top:-8px; right:-8px;" id="bottom-nav-notifications-badge">
              <?php echo $total_notifications_unread > 99 ? '99+' : $total_notifications_unread; ?>
            </span>
          <?php endif; ?>
        </div>
        <span class="mobile-nav-label">Alertas</span>
      </a>
    </li>
    <li>
      <a href="shop_mi_perfil.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'shop_mi_perfil.php' ? 'active' : ''; ?>">
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
    if (!menu) return;
    if (window.scrollY > 10) menu.classList.add('scrolled');
    else menu.classList.remove('scrolled');
  });

  if (document.querySelector('.mobile-bottom-nav')) {
    document.body.classList.add('has-bottom-nav');
  }

  // Mensajes no leídos chat (cada 10s)
  setInterval(function() {
    fetch('shop-chat-api.php?action=get_all_unread')
      .then(r => r.json())
      .then(data => {
        if (data.success && data.unread_by_proposal) {
          const total = Object.values(data.unread_by_proposal).reduce((a,b)=>a+b,0);

          document.querySelectorAll('.chat-link .badge, .chat-link .mobile-badge').forEach(badge => {
            if (!badge) return;
            if (total > 0) {
              badge.textContent = total;
              badge.style.display = 'inline-block';
            } else {
              badge.style.display = 'none';
            }
          });
        }
      })
      .catch(err => console.error('Error actualizando contador de chat:', err));
  }, 10000);

  // Notificaciones (cada 30s)
  function updateNotificationsBadge() {
    fetch('shop-notifications-api.php?action=get_notifications&user_id=<?php echo $usuario_actual_id; ?>')
      .then(r => r.json())
      .then(data => {
        if (data.success && data.stats) {
          const unreadCount = data.stats.unread || 0;
          const badges = [
            document.getElementById('desktop-notifications-badge'),
            document.getElementById('mobile-notifications-badge'),
            document.getElementById('bottom-nav-notifications-badge')
          ];

          badges.forEach(badge => {
            if (!badge) return;
            if (unreadCount > 0) {
              badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
              badge.style.display = 'flex';
            } else {
              badge.style.display = 'none';
            }
          });

          const currentTitle = document.title;
          if (unreadCount > 0 && !currentTitle.startsWith('(')) {
            document.title = `(${unreadCount}) ${currentTitle}`;
          } else if (unreadCount === 0 && currentTitle.startsWith('(')) {
            document.title = currentTitle.replace(/^\(\d+\)\s*/, '');
          }
        }
      })
      .catch(err => console.error('Error actualizando notificaciones:', err));
  }

  updateNotificationsBadge();
  setInterval(updateNotificationsBadge, 30000);
});
</script>
