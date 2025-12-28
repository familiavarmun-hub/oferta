<?php
// shop-checkout.php - VERSIÓN MEJORADA CON LOGS DE DEBUGGING
// ==========================================================

session_start();

// Config.php está en public_html/ (carpeta padre)
require_once '../config.php';

// Verificar que el usuario esté logueado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php?redirect=shop');
    exit;
}

// ========== LOGS DETALLADOS PARA DEBUGGING ==========
error_log("🛒 ========== SHOP CHECKOUT INICIADO ==========");
error_log("👤 Usuario ID: " . $_SESSION['usuario_id']);
error_log("📥 Método: " . $_SERVER['REQUEST_METHOD']);
error_log("📦 POST cart existe: " . (isset($_POST['cart']) ? 'SÍ' : 'NO'));

if (isset($_POST['cart'])) {
    error_log("📋 Cart JSON recibido: " . $_POST['cart']);
}

// Obtener datos del carrito desde POST
$cart_data = isset($_POST['cart']) ? json_decode($_POST['cart'], true) : [];

error_log("🔍 Decodificación JSON: " . (json_last_error() === JSON_ERROR_NONE ? 'EXITOSA' : 'ERROR'));
error_log("📊 Cantidad de items: " . count($cart_data));
error_log("📦 Contenido del carrito: " . print_r($cart_data, true));

if (empty($cart_data)) {
    error_log("❌ ERROR: Carrito vacío - Redirigiendo al shop");
    echo '<script>
        alert("El carrito está vacío");
        window.location.href = "index.php";
    </script>';
    exit;
}

error_log("✅ Carrito válido con " . count($cart_data) . " items");

// Validar que todos los productos existen y están disponibles
$product_ids = array_column($cart_data, 'product_id');
$placeholders = implode(',', array_fill(0, count($product_ids), '?'));

error_log("🔍 Product IDs a buscar: " . implode(', ', $product_ids));

// ✅ OBTENER trip_id JUNTO CON LOS DATOS DEL PRODUCTO
$sql_products = "SELECT id, seller_id, trip_id, name, price, currency, weight, stock 
                 FROM shop_products 
                 WHERE id IN ($placeholders) AND active = 1";
                 
try {
    $stmt_products = $conexion->prepare($sql_products);
    $stmt_products->execute($product_ids);
    $products_db = $stmt_products->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("✅ Productos encontrados en DB: " . count($products_db));
    error_log("📊 Productos DB: " . print_r($products_db, true));
    
} catch (Exception $e) {
    error_log("❌ ERROR al consultar productos: " . $e->getMessage());
    echo '<script>
        alert("Error al procesar la solicitud. Por favor, intenta de nuevo.");
        window.location.href = "index.php";
    </script>';
    exit;
}

// Verificar que encontramos todos los productos
if (count($products_db) === 0) {
    error_log("❌ No se encontraron productos activos en la DB");
    echo '<script>
        alert("Los productos seleccionados no están disponibles");
        window.location.href = "index.php";
    </script>';
    exit;
}

// Crear array indexado por product_id para fácil acceso
$products_map = [];
foreach ($products_db as $product) {
    $products_map[$product['id']] = $product;
}

// ✅ OBTENER EL trip_id DEL PRIMER PRODUCTO
$first_product = reset($products_db);
$trip_id = $first_product['trip_id'];

error_log("🚗 Trip ID del producto: " . ($trip_id ?? 'NULL'));

// ✅ VARIABLES POR DEFECTO (en caso de que no haya viaje)
$origen = 'SendVialo Shop';
$destino = 'Envío a domicilio';
$fecha = date('Y-m-d');
$id_viaje = 0;

// ✅ SI HAY UN VIAJE ASOCIADO, OBTENER SUS DATOS REALES
if ($trip_id && $trip_id > 0) {
    error_log("✅ Producto asociado a viaje ID: {$trip_id}");
    
    try {
        // ✅ USAR LOS NOMBRES CORRECTOS DE COLUMNAS
        $sql_viaje = "SELECT id, search_input, destination_input, datepicker 
                      FROM transporting 
                      WHERE id = ? 
                      LIMIT 1";
        
        $stmt_viaje = $conexion->prepare($sql_viaje);
        $stmt_viaje->execute([$trip_id]);
        $viaje_data = $stmt_viaje->fetch(PDO::FETCH_ASSOC);
        
        if ($viaje_data) {
            $id_viaje = $viaje_data['id'];
            $origen = $viaje_data['search_input'];      // ✅ COLUMNA CORRECTA
            $destino = $viaje_data['destination_input']; // ✅ COLUMNA CORRECTA
            $fecha = $viaje_data['datepicker'];          // ✅ COLUMNA CORRECTA
            
            error_log("✅ Datos del viaje obtenidos: {$origen} → {$destino} ({$fecha})");
        } else {
            error_log("⚠️ Viaje ID {$trip_id} no encontrado, usando valores por defecto");
        }
        
    } catch (Exception $e) {
        error_log("❌ ERROR al consultar viaje: " . $e->getMessage());
        // Continuar con valores por defecto
    }
} else {
    error_log("ℹ️ Producto sin trip_id, usando valores genéricos del shop");
}

// Agrupar items por vendedor
$sellers_orders = [];
$total_weight = 0;
$total_amount = 0;

error_log("📦 Procesando items del carrito...");

foreach ($cart_data as $item) {
    $product_id = $item['product_id'];
    $quantity = $item['quantity'];
    
    error_log("  - Producto ID: {$product_id}, Cantidad: {$quantity}");
    
    if (!isset($products_map[$product_id])) {
        error_log("❌ Producto no encontrado en DB: ID={$product_id}");
        echo '<script>
            alert("Uno de los productos ya no está disponible");
            window.location.href = "index.php";
        </script>';
        exit;
    }
    
    $product = $products_map[$product_id];
    $seller_id = $product['seller_id'];
    
    // Verificar stock
    if ($product['stock'] < $quantity) {
        error_log("❌ Stock insuficiente: Producto={$product['name']}, Stock={$product['stock']}, Solicitado={$quantity}");
        echo '<script>
            alert("Stock insuficiente para: ' . htmlspecialchars($product['name']) . '\\nDisponible: ' . $product['stock'] . ', Solicitado: ' . $quantity . '");
            window.location.href = "index.php";
        </script>';
        exit;
    }
    
    // Agrupar por vendedor
    if (!isset($sellers_orders[$seller_id])) {
        $sellers_orders[$seller_id] = [
            'seller_id' => $seller_id,
            'items' => [],
            'subtotal' => 0,
            'currency' => $product['currency']
        ];
    }
    
    $item_total = $product['price'] * $quantity;
    $item_weight = ($product['weight'] ?? 0) * $quantity;
    
    $sellers_orders[$seller_id]['items'][] = [
        'product_id' => $product_id,
        'name' => $product['name'],
        'price' => $product['price'],
        'quantity' => $quantity,
        'weight' => $product['weight'] ?? 0,
        'currency' => $product['currency']
    ];
    
    $sellers_orders[$seller_id]['subtotal'] += $item_total;
    $total_weight += $item_weight;
    $total_amount += $item_total;
}

error_log("💰 Total calculado: {$total_amount}, Peso: {$total_weight} kg");
error_log("👥 Vendedores involucrados: " . count($sellers_orders));

// Por ahora, trabajaremos con un solo vendedor (el primero)
$first_seller = reset($sellers_orders);
$seller_id = $first_seller['seller_id'];

error_log("🏪 Procesando orden del vendedor ID: {$seller_id}");

// Obtener información del vendedor
$sql_seller = "SELECT username, email FROM accounts WHERE id = ? LIMIT 1";
try {
    $stmt_seller = $conexion->prepare($sql_seller);
    $stmt_seller->execute([$seller_id]);
    $seller_data = $stmt_seller->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("❌ ERROR al consultar vendedor: " . $e->getMessage());
    echo '<script>
        alert("Error al procesar la solicitud. Por favor, intenta de nuevo.");
        window.location.href = "index.php";
    </script>';
    exit;
}

if (!$seller_data) {
    error_log("❌ Vendedor no encontrado: ID={$seller_id}");
    echo '<script>
        alert("Error: Vendedor no encontrado");
        window.location.href = "index.php";
    </script>';
    exit;
}

error_log("✅ Vendedor: {$seller_data['username']} (ID: {$seller_id})");

// Preparar datos para payment.php
$params = [
    'username' => $seller_data['username'],
    'origen' => $origen,  // ✅ Ahora viene de search_input
    'destino' => $destino,  // ✅ Ahora viene de destination_input
    'fecha' => $fecha,  // ✅ Ahora viene de datepicker
    'id' => $id_viaje,  // ✅ ID del viaje real (o 0 si no hay)
    'totalWeight' => number_format($total_weight, 2),
    'totalAmount' => number_format($total_amount, 2),
    'items' => json_encode($first_seller['items']),
    'shop_mode' => 'true' // Indicador de que es una compra del shop
];

error_log("📋 Parámetros para payment.php:");
error_log("   - Vendedor: {$params['username']}");
error_log("   - Origen: {$params['origen']}");
error_log("   - Destino: {$params['destino']}");
error_log("   - Fecha: {$params['fecha']}");
error_log("   - ID Viaje: {$params['id']}");
error_log("   - Peso total: {$params['totalWeight']} kg");
error_log("   - Monto total: {$params['totalAmount']}");
error_log("   - Items: {$params['items']}");

// Redirigir a payment.php
$query_string = http_build_query($params);
$redirect_url = '../payment.php?' . $query_string;

error_log("🚀 Redirigiendo a: {$redirect_url}");
error_log("🛒 ========== CHECKOUT COMPLETADO ==========");

header('Location: ' . $redirect_url);
exit;
?>