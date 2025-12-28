<?php
// ============================================
// 🔍 ERROR DEBUGGING
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Cargar config desde public_html
require_once('../config.php');

// Cargar vendor desde public_html
require_once('../vendor/autoload.php');

// Variables básicas
$redirect_after_login = $_POST['return'] ?? $_GET['return'] ?? 'shop-requests-index.php';

// ============================================
// 🔐 PROCESAR LOGIN CON GOOGLE
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['google_token'])) {
    
    try {
        // Verificar token de Google
        $client = new Google_Client(['client_id' => $_ENV['GOOGLE_CLIENT_ID']]);
        $payload = $client->verifyIdToken($_POST['google_token']);
        
        if ($payload) {
            $email = $payload['email'] ?? '';
            $full_name = $payload['name'] ?? '';
            $google_id = $payload['sub'] ?? '';
            $picture_url = $payload['picture'] ?? '';
            
            // Obtener imagen de perfil
            $picture_data = @file_get_contents($picture_url);
            
            // Buscar usuario existente
            $stmt = $conexion->prepare("SELECT * FROM accounts WHERE email = ? OR google_id = ?");
            $stmt->execute([$email, $google_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // ✅ USUARIO EXISTENTE
                $_SESSION['usuario_id'] = $user['id'];
                $_SESSION['usuario_nombre'] = $user['full_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['ruta_imagen'] = "../mostrar_imagen.php?id=" . $user['id'];
                $_SESSION['loggedin'] = true;
                
                // Redirigir
                header("Location: " . $redirect_after_login);
                exit();
                
            } else {
                // ✅ NUEVO USUARIO - Generar username único
                $username_base = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', explode(' ', $full_name)[0]));
                $username = $username_base . rand(1000, 9999);
                
                // Verificar que sea único
                $counter = 1;
                while (true) {
                    $stmt = $conexion->prepare("SELECT COUNT(*) FROM accounts WHERE username = ?");
                    $stmt->execute([$username]);
                    if ($stmt->fetchColumn() == 0) break;
                    $username = $username_base . rand(1000, 9999) . $counter++;
                }
                
                // Insertar nuevo usuario
                $stmt = $conexion->prepare("
                    INSERT INTO accounts 
                    (full_name, email, google_id, ruta_imagen, username, phone_verified, created_at) 
                    VALUES (?, ?, ?, ?, ?, 0, NOW())
                ");
                $stmt->execute([$full_name, $email, $google_id, $picture_data, $username]);
                
                $new_user_id = $conexion->lastInsertId();
                
                // Establecer sesión
                $_SESSION['usuario_id'] = $new_user_id;
                $_SESSION['usuario_nombre'] = $full_name;
                $_SESSION['email'] = $email;
                $_SESSION['username'] = $username;
                $_SESSION['ruta_imagen'] = "../mostrar_imagen.php?id=" . $new_user_id;
                $_SESSION['loggedin'] = true;
                $_SESSION['is_new_user'] = true;
                
                // Redirigir al index principal para completar perfil
                header("Location: shop-requests-index.php
");
                exit();
            }
        } else {
            $error = "Token de Google inválido";
        }
        
    } catch (Exception $e) {
        $error = "Error de autenticación: " . $e->getMessage();
        error_log("ERROR SHOP LOGIN: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Iniciar Sesión - SendVialo Shop</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <link rel="icon" href="../Imagenes/globo5.png" type="image/png">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            height: 100vh; overflow: hidden; }
        .main-container { display: flex; height: 100vh; }
        .left-panel { flex: 1; background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%);
            display: flex; flex-direction: column; justify-content: center; align-items: center;
            padding: 3rem; position: relative; overflow: hidden; }
        .left-panel::before { content: ''; position: absolute; width: 400px; height: 400px;
            background: rgba(255,255,255,0.1); border-radius: 50%; top: -200px; left: -200px;
            animation: float 20s infinite ease-in-out; }
        .brand-logo { font-size: 5rem; color: white; margin-bottom: 2rem; z-index: 1;
            animation: bounce 2s infinite ease-in-out; }
        .brand-title { font-size: 3.5rem; font-weight: 800; color: white; margin-bottom: 1rem;
            z-index: 1; text-shadow: 2px 2px 4px rgba(0,0,0,0.1); }
        .brand-subtitle { font-size: 1.25rem; color: rgba(255,255,255,0.9); max-width: 400px;
            text-align: center; line-height: 1.6; z-index: 1; }
        .right-panel { flex: 1; background: #2b3544; display: flex; justify-content: center;
            align-items: center; padding: 3rem; position: relative; }
        .login-form-container { width: 100%; max-width: 500px; background: #3c4859;
            padding: 3rem; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
        .form-logo { text-align: center; margin-bottom: 2.5rem; }
        .form-logo h2 { font-size: 2.5rem; color: #ffffff; font-weight: 700; }
        .error-message { background: #fee; color: #c33; padding: 15px; border-radius: 8px;
            margin-bottom: 20px; border: 1px solid #fcc; }
        .g_id_signin { width: 100%; margin: 3rem 0 2rem 0; }
        .terms-notice { text-align: center; font-size: 0.85rem; color: #a0aec0;
            line-height: 1.6; margin-top: 2rem; padding-top: 2rem;
            border-top: 1px solid rgba(160,174,192,0.2); }
        .terms-notice a { color: #68d391; text-decoration: none; }
        @keyframes float { 0%, 100% { transform: translate(0,0); }
            50% { transform: translate(30px,-30px); } }
        @keyframes bounce { 0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); } }
        #splash-screen { position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: #ffffff; display: none; justify-content: center; align-items: center;
            z-index: 9999; flex-direction: column; }
        #splash-screen img { width: 300px; }
        .spinner { border: 3px solid #f3f3f3; border-top: 3px solid #56ab2f;
            border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite;
            margin-top: 20px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        @media (max-width: 1024px) {
            .left-panel { display: none; }
            .right-panel { background: linear-gradient(135deg, #2b3544 0%, #3c4859 100%); }
        }
    </style>
</head>
<body>
    <div id="splash-screen">
        <img src="../Imagenes/logo_sendvialo_shop.png" alt="SendVialo">
        <div class="spinner"></div>
    </div>

    <div class="main-container">
        <div class="left-panel">
            <div class="brand-logo"></div>
            <h1 class="brand-title">SendVialo Shop</h1>
            <p class="brand-subtitle">
                Pide algo al cualquier lugar del mundo, un viajero te lo traerá.
            </p>
        </div>

        <div class="right-panel">
            <div class="login-form-container">
                <div class="form-logo">
                    <h2>SendVialo Shop</h2>
                </div>

                <?php if (isset($error)): ?>
                    <div class="error-message">❌ <?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form name="loginForm" method="post">
                    <input type="hidden" name="google_token" id="google_token">
                    <input type="hidden" name="return" value="<?php echo htmlspecialchars($redirect_after_login); ?>">
                    
                    <div id="g_id_onload"
                         data-client_id="<?php echo $_ENV['GOOGLE_CLIENT_ID']; ?>"
                         data-callback="handleCredentialResponse">
                    </div>
                    <div class="g_id_signin" data-type="standard" data-size="large"></div>

                    <p class="terms-notice">
                        Al continuar, aceptas nuestros 
                        <a href="../terminos.html" target="_blank">Términos</a> y 
                        <a href="../privacidad.html" target="_blank">Política de Privacidad</a>.
                    </p>
                </form>
            </div>
        </div>
    </div>

    <script>
    function handleCredentialResponse(response) {
        document.getElementById('splash-screen').style.display = 'flex';
        document.getElementById('google_token').value = response.credential;
        
        setTimeout(() => {
            document.loginForm.submit();
        }, 1000);
    }

    window.onload = function() {
        google.accounts.id.initialize({
            client_id: '<?php echo $_ENV['GOOGLE_CLIENT_ID']; ?>',
            callback: handleCredentialResponse
        });
        google.accounts.id.renderButton(
            document.querySelector('.g_id_signin'),
            { theme: "filled_blue", size: "large", text: "continue_with", locale: "es" }
        );
    };
    </script>
</body>
</html>