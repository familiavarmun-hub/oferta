<?php
// auth_check.php - Sistema de autenticación para el shop
// Crear en la carpeta shop/

session_start();

// Función para verificar autenticación
function isLoggedIn() {
    return isset($_SESSION['usuario_id']) && !empty($_SESSION['usuario_id']);
}

// Función para obtener información del usuario
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['usuario_id'],
        'nombre' => $_SESSION['usuario_nombre'] ?? $_SESSION['full_name'] ?? 'Usuario',
        'username' => $_SESSION['username'] ?? 'user',
        'email' => $_SESSION['email'] ?? ''
    ];
}

// Función para redirigir al login
function redirectToLogin() {
    $login_url = '../login.php'; // Ajustar según tu estructura
    
    // Si es una petición AJAX, devolver JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'No autenticado',
            'redirect' => $login_url,
            'message' => 'Debes iniciar sesión para acceder al shop'
        ]);
        exit;
    }
    
    // Si es petición normal, redirigir
    header("Location: $login_url");
    exit;
}

// Función para mostrar página de login del shop
function showShopLogin() {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Acceso SendVialo Shop</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.3.0/css/all.min.css">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #333;
            }
            
            .login-container {
                background: white;
                border-radius: 20px;
                box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                overflow: hidden;
                width: 100%;
                max-width: 400px;
                margin: 20px;
            }
            
            .login-header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 40px 30px;
                text-align: center;
            }
            
            .login-header i {
                font-size: 3rem;
                margin-bottom: 20px;
            }
            
            .login-header h1 {
                font-size: 1.8rem;
                margin-bottom: 10px;
            }
            
            .login-header p {
                opacity: 0.9;
                font-size: 0.95rem;
            }
            
            .login-content {
                padding: 40px 30px;
                text-align: center;
            }
            
            .login-message {
                margin-bottom: 30px;
                color: #666;
                line-height: 1.6;
            }
            
            .login-buttons {
                display: flex;
                flex-direction: column;
                gap: 15px;
            }
            
            .btn {
                padding: 15px 25px;
                border: none;
                border-radius: 10px;
                font-weight: 600;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
                cursor: pointer;
                transition: all 0.3s ease;
                font-size: 1rem;
            }
            
            .btn-primary {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
            }
            
            .btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
                color: white;
                text-decoration: none;
            }
            
            .btn-secondary {
                background: #f8f9fa;
                color: #333;
                border: 2px solid #e9ecef;
            }
            
            .btn-secondary:hover {
                background: #e9ecef;
                color: #333;
                text-decoration: none;
            }
            
            .features {
                margin-top: 30px;
                padding-top: 30px;
                border-top: 1px solid #eee;
            }
            
            .features h3 {
                font-size: 1.1rem;
                margin-bottom: 15px;
                color: #333;
            }
            
            .features ul {
                list-style: none;
                text-align: left;
            }
            
            .features li {
                padding: 5px 0;
                color: #666;
                font-size: 0.9rem;
            }
            
            .features li i {
                color: #667eea;
                margin-right: 10px;
                width: 16px;
            }
            
            @media (max-width: 480px) {
                .login-container {
                    margin: 10px;
                }
                
                .login-header {
                    padding: 30px 20px;
                }
                
                .login-content {
                    padding: 30px 20px;
                }
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="login-header">
                <i class="fas fa-shopping-bag"></i>
                <h1>SendVialo Shop</h1>
                <p>Tu marketplace de productos únicos de viajeros</p>
            </div>
            
            <div class="login-content">
                <div class="login-message">
                    <p><strong>¡Bienvenido al SendVialo Shop!</strong></p>
                    <p>Para vender productos o gestionar tu tienda necesitas iniciar sesión primero.</p>
                </div>
                
                <div class="login-buttons">
                    <a href="../login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i>
                        Iniciar Sesión
                    </a>
                    
                    <a href="../register.php" class="btn btn-secondary">
                        <i class="fas fa-user-plus"></i>
                        Crear Cuenta
                    </a>
                    
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-eye"></i>
                        Ver Productos (sin cuenta)
                    </a>
                </div>
                
                <div class="features">
                    <h3>¿Qué puedes hacer con tu cuenta?</h3>
                    <ul>
                        <li><i class="fas fa-plus-circle"></i> Vender productos únicos de tus viajes</li>
                        <li><i class="fas fa-route"></i> Vincular productos a tus rutas de viaje</li>
                        <li><i class="fas fa-images"></i> Subir hasta 5 imágenes por producto</li>
                        <li><i class="fas fa-chart-line"></i> Ver estadísticas de ventas</li>
                        <li><i class="fas fa-shopping-cart"></i> Comprar productos de otros viajeros</li>
                        <li><i class="fas fa-star"></i> Sistema de valoraciones y reseñas</li>
                    </ul>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Si se llama directamente, mostrar información
if (basename($_SERVER['PHP_SELF']) == 'auth_check.php') {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Estado de Autenticación - SendVialo Shop</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            .info { padding: 15px; margin: 15px 0; border-radius: 5px; }
            .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
            .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; }
            .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        </style>
    </head>
    <body>
        <h1>🔐 Estado de Autenticación - SendVialo Shop</h1>
        
        <?php
        if (isLoggedIn()) {
            $user = getCurrentUser();
            echo '<div class="info success">';
            echo '<h3>✅ Usuario Autenticado</h3>';
            echo '<p><strong>ID:</strong> ' . $user['id'] . '</p>';
            echo '<p><strong>Nombre:</strong> ' . $user['nombre'] . '</p>';
            echo '<p><strong>Username:</strong> ' . $user['username'] . '</p>';
            echo '<p><a href="shop-manage-products.php">📱 Ir a Gestionar Productos</a></p>';
            echo '<p><a href="index.php">🛍️ Ver Catálogo</a></p>';
            echo '</div>';
        } else {
            echo '<div class="info error">';
            echo '<h3>❌ Usuario No Autenticado</h3>';
            echo '<p>No has iniciado sesión. Las funciones del shop requieren autenticación.</p>';
            echo '<p><a href="../login.php">🔑 Iniciar Sesión</a></p>';
            echo '<p><a href="index.php">👁️ Ver Catálogo (sin cuenta)</a></p>';
            echo '</div>';
        }
        ?>
        
        <div class="info warning">
            <h3>📋 Información de Sesión</h3>
            <p><strong>Session ID:</strong> <?php echo session_id(); ?></p>
            <p><strong>Session Status:</strong> <?php echo session_status(); ?></p>
            <p><strong>Session Data:</strong></p>
            <pre><?php print_r($_SESSION); ?></pre>
        </div>
    </body>
    </html>
    <?php
}
?>