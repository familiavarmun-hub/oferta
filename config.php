<?php
// config.php
// Protección contra inclusiones múltiples
if (defined('CONFIG_LOADED')) {
    return;
}
define('CONFIG_LOADED', true);

// Protección contra acceso directo
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    header('HTTP/1.1 403 Forbidden');
    exit("Acceso denegado");
}

// Cargar manualmente variables desde .env
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0 || !strpos($line, '=')) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        if (!isset($_ENV[$name])) {
            $_ENV[$name] = $value;
        }
    }
} else {
    exit('Error: No se encontró el archivo .env');
}

// Datos de conexión
$DATABASE_HOST = $_ENV['DATABASE_HOST'];
$DATABASE_USER = $_ENV['DATABASE_USER'];
$DATABASE_PASS = $_ENV['DATABASE_PASS'];
$DATABASE_NAME = $_ENV['DATABASE_NAME'];

// Configuraciones API
if (!defined('PIXABAY_API_KEY')) {
    define('PIXABAY_API_KEY', $_ENV['PIXABAY_API'] ?? '');
}

// Email
if (!defined('MAIL_FROM_ADDRESS')) {
    define('MAIL_FROM_ADDRESS', $_ENV['MAIL_FROM_ADDRESS'] ?? 'no-reply@sendvialo.com');
}
if (!defined('MAIL_FROM_NAME')) {
    define('MAIL_FROM_NAME', $_ENV['MAIL_FROM_NAME'] ?? 'Liberación de Pagos');
}

// PayPal
if (!defined('PAYPAL_MODE')) {
    define('PAYPAL_MODE', 'live');
}
if (!defined('PAYPAL_CLIENT_ID')) {
    define('PAYPAL_CLIENT_ID', $_ENV['PAYPAL_CLIENT_ID'] ?? '');
}
if (!defined('PAYPAL_SECRET')) {
    define('PAYPAL_SECRET', $_ENV['PAYPAL_SECRET'] ?? '');
}
$_ENV['PAYPAL_MODE'] = PAYPAL_MODE;

// Stripe
if (!defined('STRIPE_SECRET')) {
    define('STRIPE_SECRET', $_ENV['STRIPE_SECRET'] ?? '');
}
if (!defined('STRIPE_PUBLISHABLE_KEY')) {
    define('STRIPE_PUBLISHABLE_KEY', $_ENV['STRIPE_PUBLISHABLE_KEY'] ?? '');
}

// Google
if (!defined('GOOGLE_CLIENT_ID')) {
    define('GOOGLE_CLIENT_ID', $_ENV['GOOGLE_CLIENT_ID'] ?? '');
}
if (!defined('GOOGLE_MAPS_API_KEY')) {
    define('GOOGLE_MAPS_API_KEY', $_ENV['GOOGLE_MAPS_API_KEY'] ?? '');
}

// Twilio
if (!defined('TWILIO_SID')) {
    define('TWILIO_SID', $_ENV['TWILIO_SID'] ?? '');
}
if (!defined('TWILIO_AUTH_TOKEN')) {
    define('TWILIO_AUTH_TOKEN', $_ENV['TWILIO_AUTH_TOKEN'] ?? '');
}
if (!defined('TWILIO_PHONE_NUMBER')) {
    define('TWILIO_PHONE_NUMBER', $_ENV['TWILIO_PHONE_NUMBER'] ?? '');
}
if (!defined('TWILIO_VERIFY_SERVICE_SID')) {
    define('TWILIO_VERIFY_SERVICE_SID', $_ENV['TWILIO_VERIFY_SERVICE_SID'] ?? '');
}

// Apple Pay
define('APPLE_PAY_MERCHANT_ID', 'merchant.com.sendvialo');
define('APPLE_PAY_DOMAIN', 'sendvialo.com');

// COMENTADO EN PRODUCCIÓN - Descomenta solo para debug
/*
error_log("🔍 CONFIG DEBUG:");
error_log("PAYPAL_MODE: " . PAYPAL_MODE);
error_log("PAYPAL_CLIENT_ID: " . (PAYPAL_CLIENT_ID ? substr(PAYPAL_CLIENT_ID, 0, 10) . '...' : 'NO CONFIGURADO'));
error_log("PAYPAL_SECRET: " . (PAYPAL_SECRET ? substr(PAYPAL_SECRET, 0, 10) . '...' : 'NO CONFIGURADO'));
error_log("STRIPE_PUBLISHABLE_KEY: " . (STRIPE_PUBLISHABLE_KEY ? substr(STRIPE_PUBLISHABLE_KEY, 0, 10) . '...' : 'NO CONFIGURADO'));
*/

// Conectar a BD
try {
    $conexion = new PDO("mysql:host=$DATABASE_HOST;dbname=$DATABASE_NAME", $DATABASE_USER, $DATABASE_PASS);
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conexion->exec("set names utf8mb4");
} catch(PDOException $e) {
    exit('Error al conectar con la base de datos: ' . $e->getMessage());
}
?>