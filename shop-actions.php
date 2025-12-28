<?php
// shop-actions.php - VERSIÓN CORREGIDA CON SISTEMA DE VALORACIONES UNIFICADO
ini_set('display_errors', 0);
error_reporting(0);
ob_start();

// Incluir sistema de autenticación
require_once 'auth_check.php';

ob_clean();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Buscar config
$config_paths = [__DIR__ . '/config.php', __DIR__ . '/../config.php'];
$config_found = false;

foreach ($config_paths as $path) {
    if (file_exists($path)) {
        try {
            require_once $path;
            $config_found = true;
            break;
        } catch (Exception $e) {
            continue;
        }
    }
}

if (!$config_found || !isset($conexion)) {
    sendResponse(['error' => 'Error de configuración'], 500);
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
if (empty($action)) {
    sendResponse(['error' => 'Acción no especificada'], 400);
}

// Verificar autenticación para acciones que la requieren
$protected_actions = ['add_product', 'get_user_products', 'get_seller_stats', 'update_product', 'delete_product', 'create_order', 'get_user_orders', 'submit_product_proposal', 'submit_product_counteroffer', 'accept_product_proposal', 'reject_product_proposal'];
if (in_array($action, $protected_actions) && !isLoggedIn()) {
    sendResponse([
        'error' => 'Acceso denegado',
        'message' => 'Debes iniciar sesión para realizar esta acción',
        'redirect' => '../login.php'
    ], 401);
}

try {
    switch ($action) {
        case 'add_product':
            sendResponse(addProduct());
            break;
        case 'get_products':
            sendResponse(getProducts());
            break;
        case 'get_product_detail':
            sendResponse(getProductDetail());
            break;
        case 'get_user_products':
            sendResponse(getUserProducts());
            break;
        case 'get_seller_stats':
            sendResponse(getSellerStats());
            break;
        case 'update_product':
            sendResponse(updateProduct());
            break;
        case 'delete_product':
            sendResponse(deleteProduct());
            break;
        case 'create_order':
            sendResponse(createOrder());
            break;
        case 'get_user_orders':
            sendResponse(getUserOrders());
            break;
        case 'submit_product_proposal':
            sendResponse(submitProductProposal());
            break;
        case 'submit_product_counteroffer':
            sendResponse(submitProductCounteroffer());
            break;
        case 'accept_product_proposal':
            sendResponse(acceptProductProposal());
            break;
        case 'reject_product_proposal':
            sendResponse(rejectProductProposal());
            break;
        case 'get_product_negotiation_history':
            sendResponse(getProductNegotiationHistory());
            break;
        default:
            sendResponse(['error' => 'Acción no válida'], 400);
    }
} catch (Exception $e) {
    sendResponse(['error' => $e->getMessage()], 500);
}

// FUNCIÓN NUEVA: Obtener valoración unificada del vendedor
function getUnifiedSellerRating($seller_id) {
    global $conexion;
    
    try {
        // Obtener valoraciones de la tabla comentarios (sistema principal)
        $sql = "SELECT 
                    AVG(valoracion) as promedio_valoracion,
                    COUNT(*) as total_valoraciones
                FROM comentarios 
                WHERE usuario_id = ? AND bloqueado = 0";
        
        $stmt = $conexion->prepare($sql);
        $stmt->execute([$seller_id]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $promedio = 0;
        $total = 0;
        
        if ($resultado && $resultado['promedio_valoracion'] !== null) {
            $promedio = round((float)$resultado['promedio_valoracion'], 1);
            $total = (int)$resultado['total_valoraciones'];
        }
        
        return [
            'average_rating' => $promedio,
            'total_ratings' => $total,
            'rating_display' => $promedio > 0 ? $promedio : 'N/A'
        ];
        
    } catch (PDOException $e) {
        return [
            'average_rating' => 0,
            'total_ratings' => 0,
            'rating_display' => 'N/A'
        ];
    }
}

// FUNCIÓN NUEVA: Determinar tipo de insignia/efecto según valoración
function obtenerTipoEfecto($promedio) {
    if ($promedio >= 4.8) return 'diamond';
    if ($promedio >= 4.5) return 'gold';
    if ($promedio >= 4.0) return 'silver';
    if ($promedio >= 3.5) return 'bronze';
    return 'basic';
}

function addProduct() {
    global $conexion;
    
    // Verificar autenticación usando el sistema auth_check
    if (!isLoggedIn()) {
        return [
            'error' => 'Usuario no autenticado',
            'redirect' => '../login.php'
        ];
    }
    
    $user = getCurrentUser();
    $seller_id = $user['id'];
    
    $required = ['name', 'description', 'price', 'category', 'stock'];
    foreach ($required as $field) {
        if (!isset($_POST[$field]) || $_POST[$field] === '') {
            return ['error' => "Campo '$field' es obligatorio"];
        }
    }
    
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = (float)$_POST['price'];
    $currency = $_POST['currency'] ?? 'EUR';
    $category = $_POST['category'];
    $stock = (int)$_POST['stock'];
    $weight = !empty($_POST['weight']) ? (float)$_POST['weight'] : null;
    $dimensions = !empty($_POST['dimensions']) ? trim($_POST['dimensions']) : null;
    $trip_id = !empty($_POST['trip_id']) ? (int)$_POST['trip_id'] : null;
    
    if ($price <= 0) return ['error' => 'El precio debe ser mayor a 0'];
    if ($stock <= 0) return ['error' => 'El stock debe ser mayor a 0'];
    
    $valid_currencies = ['EUR', 'USD', 'BOB', 'BRL', 'ARS', 'VES', 'COP', 'MXN', 'NIO', 'CUP', 'PEN'];
    if (!in_array($currency, $valid_currencies)) {
        return ['error' => 'Moneda no válida'];
    }
    
    $valid_categories = ['food', 'crafts', 'fashion', 'electronics', 'books', 'cosmetics', 'toys', 'sports', 'home', 'others'];
    if (!in_array($category, $valid_categories)) {
        return ['error' => 'Categoría no válida'];
    }
    
    if ($trip_id) {
        $stmt = $conexion->prepare("SELECT id FROM transporting WHERE id = ? AND id_transporting = ?");
        $stmt->execute([$trip_id, $seller_id]);
        if (!$stmt->fetch()) {
            return ['error' => 'El viaje seleccionado no te pertenece'];
        }
    }
    
    try {
        $sql = "INSERT INTO shop_products (
                    seller_id, trip_id, name, description, price, currency, 
                    category, stock, weight, dimensions, active, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())";
        
        $stmt = $conexion->prepare($sql);
        $result = $stmt->execute([
            $seller_id, $trip_id, $name, $description, $price, 
            $currency, $category, $stock, $weight, $dimensions
        ]);
        
        if (!$result) {
            return ['error' => 'Error al guardar en la base de datos'];
        }
        
        $product_id = $conexion->lastInsertId();
        
        $uploaded_images = 0;
        if (isset($_FILES['images'])) {
            $upload_result = uploadImages($product_id, $_FILES['images']);
            if ($upload_result['success']) {
                $uploaded_images = count($upload_result['files']);
            }
        }
        
        return [
            'success' => true,
            'message' => 'Producto añadido exitosamente',
            'product_id' => $product_id,
            'uploaded_images' => $uploaded_images
        ];
        
    } catch (PDOException $e) {
        return ['error' => 'Error de base de datos'];
    }
}

function uploadImages($product_id, $files) {
    global $conexion;
    
    // RUTA CORREGIDA: Asegurar que el directorio existe
    $upload_dir = __DIR__ . '/uploads/shop_products/';
    if (!file_exists($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            return ['success' => false, 'error' => 'No se pudo crear el directorio de uploads'];
        }
        
        // Crear .htaccess para seguridad
        $htaccess_content = "Options -Indexes\nDeny from all\n<Files ~ \"\\.(jpg|jpeg|png|gif|webp)$\">\n    Allow from all\n</Files>";
        file_put_contents($upload_dir . '.htaccess', $htaccess_content);
    }
    
    if (!is_writable($upload_dir)) {
        return ['success' => false, 'error' => 'Directorio no escribible'];
    }
    
    $uploaded = [];
    $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB
    $max_files = 5;
    
    if (!isset($files['tmp_name'])) {
        return ['success' => true, 'files' => []];
    }
    
    // Normalizar arrays de archivos
    $file_array = [];
    if (is_array($files['tmp_name'])) {
        for ($i = 0; $i < count($files['tmp_name']); $i++) {
            $file_array[] = [
                'tmp_name' => $files['tmp_name'][$i],
                'name' => $files['name'][$i],
                'size' => $files['size'][$i],
                'error' => $files['error'][$i]
            ];
        }
    } else {
        $file_array[] = [
            'tmp_name' => $files['tmp_name'],
            'name' => $files['name'],
            'size' => $files['size'],
            'error' => $files['error']
        ];
    }
    
    // Procesar cada archivo
    for ($i = 0; $i < min(count($file_array), $max_files); $i++) {
        $file = $file_array[$i];
        
        if ($file['error'] !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            continue;
        }
        
        // Verificar tipo MIME
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime, $allowed)) {
            continue;
        }
        
        if ($file['size'] > $max_size) {
            continue;
        }
        
        // Generar nombre único y seguro
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = 'prod_' . $product_id . '_' . time() . '_' . $i . '_' . uniqid() . '.' . $ext;
        $filepath = $upload_dir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            try {
                // RUTA RELATIVA CORREGIDA
                $relative_path = 'uploads/shop_products/' . $filename;
                
                $stmt = $conexion->prepare("INSERT INTO shop_product_images (product_id, image_path, is_primary, created_at) VALUES (?, ?, ?, NOW())");
                $is_primary = empty($uploaded) ? 1 : 0;
                $stmt->execute([$product_id, $relative_path, $is_primary]);
                
                $uploaded[] = $relative_path;
                
                // Crear miniatura si es posible
                createThumbnail($filepath, dirname($filepath) . '/thumb_' . $filename, 300, 300);
                
            } catch (PDOException $e) {
                unlink($filepath);
            }
        }
    }
    
    return ['success' => true, 'files' => $uploaded];
}

function createThumbnail($source, $destination, $width, $height) {
    if (!extension_loaded('gd')) return false;
    
    $info = getimagesize($source);
    if (!$info) return false;
    
    $srcWidth = $info[0];
    $srcHeight = $info[1];
    $mime = $info['mime'];
    
    // Crear imagen fuente
    switch ($mime) {
        case 'image/jpeg':
            $srcImage = imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $srcImage = imagecreatefrompng($source);
            break;
        case 'image/gif':
            $srcImage = imagecreatefromgif($source);
            break;
        case 'image/webp':
            $srcImage = imagecreatefromwebp($source);
            break;
        default:
            return false;
    }
    
    if (!$srcImage) return false;
    
    // Calcular dimensiones manteniendo proporción
    $ratio = min($width / $srcWidth, $height / $srcHeight);
    $newWidth = round($srcWidth * $ratio);
    $newHeight = round($srcHeight * $ratio);
    
    // Crear imagen de destino
    $destImage = imagecreatetruecolor($newWidth, $newHeight);
    
    // Preservar transparencia para PNG
    if ($mime === 'image/png') {
        imagealphablending($destImage, false);
        imagesavealpha($destImage, true);
        $transparent = imagecolorallocatealpha($destImage, 255, 255, 255, 127);
        imagefill($destImage, 0, 0, $transparent);
    }
    
    // Redimensionar
    imagecopyresampled($destImage, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $srcWidth, $srcHeight);
    
    // Guardar
    $saved = false;
    switch ($mime) {
        case 'image/jpeg':
            $saved = imagejpeg($destImage, $destination, 85);
            break;
        case 'image/png':
            $saved = imagepng($destImage, $destination, 8);
            break;
        case 'image/gif':
            $saved = imagegif($destImage, $destination);
            break;
        case 'image/webp':
            $saved = imagewebp($destImage, $destination, 85);
            break;
    }
    
    imagedestroy($srcImage);
    imagedestroy($destImage);
    
    return $saved;
}

function getProducts() {
    global $conexion;
    
    try {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        
        $where = ['sp.active = 1'];
        $params = [];
        
        if (!empty($_GET['search'])) {
            $where[] = '(sp.name LIKE ? OR sp.description LIKE ?)';
            $term = '%' . $_GET['search'] . '%';
            $params[] = $term;
            $params[] = $term;
        }
        
        if (!empty($_GET['currency'])) {
            $where[] = 'sp.currency = ?';
            $params[] = $_GET['currency'];
        }
        
        if (!empty($_GET['category'])) {
            $where[] = 'sp.category = ?';
            $params[] = $_GET['category'];
        }
        
        if (!empty($_GET['origin'])) {
            $where[] = 't.search_input LIKE ?';
            $params[] = '%' . $_GET['origin'] . '%';
        }
        
        if (!empty($_GET['destination'])) {
            $where[] = 't.destination_input LIKE ?';
            $params[] = '%' . $_GET['destination'] . '%';
        }
        
        $where_sql = 'WHERE ' . implode(' AND ', $where);
        
        $sql = "SELECT 
                    sp.*,
                    a.full_name as seller_name,
                    a.username as seller_username,
                    a.verificado as seller_verified,
                    t.search_input as origin_country,
                    t.destination_input as destination_city,
                    t.datepicker as fecha_viaje
                FROM shop_products sp
                LEFT JOIN accounts a ON sp.seller_id = a.id
                LEFT JOIN transporting t ON sp.trip_id = t.id
                $where_sql
                ORDER BY sp.created_at DESC
                LIMIT $limit OFFSET $offset";
        
        $stmt = $conexion->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($products as &$product) {
            // OBTENER VALORACIÓN UNIFICADA DEL VENDEDOR
            $seller_rating_data = getUnifiedSellerRating($product['seller_id']);
            $product['seller_rating'] = $seller_rating_data['average_rating'];
            $product['total_ratings'] = $seller_rating_data['total_ratings'];
            $product['rating_display'] = $seller_rating_data['rating_display'];
            
            // Determinar tipo de insignia/efecto
            $product['seller_badge_type'] = obtenerTipoEfecto($product['seller_rating']);
            
            // CORREGIR: Obtener imágenes con verificación de existencia
            $img_stmt = $conexion->prepare("SELECT image_path FROM shop_product_images WHERE product_id = ? ORDER BY is_primary DESC, id ASC");
            $img_stmt->execute([$product['id']]);
            $db_images = $img_stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $valid_images = [];
            foreach ($db_images as $img_path) {
                // Verificar que el archivo existe físicamente
                $full_path = __DIR__ . '/' . ltrim($img_path, '/');
                if (file_exists($full_path)) {
                    $valid_images[] = $img_path;
                } else {
                    // Limpiar imagen huérfana de la BD (opcional)
                    $clean_stmt = $conexion->prepare("DELETE FROM shop_product_images WHERE product_id = ? AND image_path = ?");
                    $clean_stmt->execute([$product['id'], $img_path]);
                }
            }
            
            $product['images'] = $valid_images;
            $product['primary_image'] = !empty($valid_images) ? $valid_images[0] : null;
            
            // Si no hay imagen válida, usar placeholder
            if (empty($valid_images)) {
                $product['primary_image'] = generatePlaceholderImage($product['category']);
            }
            
            // Info del viaje - MEJORADO para mostrar rutas
            if ($product['origin_country'] && $product['destination_city']) {
                $origin = getShortCity($product['origin_country']);
                $dest = getShortCity($product['destination_city']);
                $product['origin'] = $origin;
                $product['destination'] = $dest;
                
                // Código de ruta más claro
                $origin_code = strtoupper(substr($origin, 0, 3));
                $dest_code = strtoupper(substr($dest, 0, 3));
                $product['trip_code'] = $origin_code . '→' . $dest_code;
                
                // Info completa de la ruta
                $product['route_display'] = $origin . ' → ' . $dest;
                $product['route_full'] = $product['origin_country'] . ' → ' . $product['destination_city'];
            } else {
                $product['trip_code'] = null;
                $product['origin'] = null;
                $product['destination'] = null;
                $product['route_display'] = null;
                $product['route_full'] = null;
            }
            
            // Avatar del vendedor
            if (empty($product['seller_avatar'])) {
                $name = $product['seller_name'] ?: 'Usuario';
                $product['seller_avatar'] = "https://ui-avatars.com/api/?name=" . urlencode($name) . "&background=667eea&color=fff&size=70";
            }
            
            // Formatear números
            $product['price'] = (float)$product['price'];
            $product['stock'] = (int)$product['stock'];
            $product['seller_rating'] = (float)$product['seller_rating'];
            $product['total_ratings'] = (int)$product['total_ratings'];
            $product['seller_verified'] = (bool)$product['seller_verified'];
            
            $product['available'] = $product['stock'] > 0;
            $product['low_stock'] = $product['stock'] <= 5 && $product['stock'] > 0;
        }
        
        // Total
        $count_sql = "SELECT COUNT(*) FROM shop_products sp 
                      LEFT JOIN accounts a ON sp.seller_id = a.id
                      LEFT JOIN transporting t ON sp.trip_id = t.id
                      $where_sql";
        $count_stmt = $conexion->prepare($count_sql);
        $count_stmt->execute($params);
        $total = (int)$count_stmt->fetchColumn();
        
        return [
            'success' => true,
            'products' => $products,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total
            ]
        ];
        
    } catch (PDOException $e) {
        return ['error' => 'Error cargando productos'];
    }
}

function generatePlaceholderImage($category) {
    // URLs de imágenes de placeholder según categoría
    $placeholders = [
        'food' => 'https://images.unsplash.com/photo-1565299624946-3dc8b66b0e83?w=400&h=300&fit=crop&q=80',
        'crafts' => 'https://images.unsplash.com/photo-1578662015441-ce7ecf7fa773?w=400&h=300&fit=crop&q=80',
        'fashion' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=400&h=300&fit=crop&q=80',
        'electronics' => 'https://images.unsplash.com/photo-1581092335878-6e6f13b9a4f6?w=400&h=300&fit=crop&q=80',
        'books' => 'https://images.unsplash.com/photo-1481627834876-b7833e8f5570?w=400&h=300&fit=crop&q=80',
        'cosmetics' => 'https://images.unsplash.com/photo-1596462502858-b3b5fe9a5c7a?w=400&h=300&fit=crop&q=80',
        'toys' => 'https://images.unsplash.com/photo-1558060370-d644479cb6f7?w=400&h=300&fit=crop&q=80',
        'sports' => 'https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?w=400&h=300&fit=crop&q=80',
        'home' => 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?w=400&h=300&fit=crop&q=80',
        'others' => 'https://images.unsplash.com/photo-1560472355-536de3962603?w=400&h=300&fit=crop&q=80'
    ];
    
    return $placeholders[$category] ?? $placeholders['others'];
}

function getUserProducts() {
    global $conexion;
    
    if (!isLoggedIn()) {
        return [
            'error' => 'Usuario no autenticado',
            'redirect' => '../login.php'
        ];
    }
    
    $user = getCurrentUser();
    $user_id = $user['id'];
    
    try {
        $sql = "SELECT 
                    sp.*,
                    t.search_input as origin_country,
                    t.destination_input as destination_city,
                    t.datepicker as fecha_viaje
                FROM shop_products sp
                LEFT JOIN transporting t ON sp.trip_id = t.id
                WHERE sp.seller_id = ?
                ORDER BY sp.created_at DESC";
        
        $stmt = $conexion->prepare($sql);
        $stmt->execute([$user_id]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($products as &$product) {
            // CORREGIR: Verificar existencia de imágenes
            $img_stmt = $conexion->prepare("SELECT image_path FROM shop_product_images WHERE product_id = ? ORDER BY is_primary DESC, id ASC");
            $img_stmt->execute([$product['id']]);
            $db_images = $img_stmt->fetchAll(PDO::FETCH_COLUMN);

            $valid_images = [];
            foreach ($db_images as $img_path) {
                $full_path = __DIR__ . '/' . ltrim($img_path, '/');
                if (file_exists($full_path)) {
                    $valid_images[] = $img_path;
                }
            }

            $product['images'] = $valid_images;
            $product['primary_image'] = !empty($valid_images) ? $valid_images[0] : null;

            // Verificar si el producto tiene pedidos activos
            $order_stmt = $conexion->prepare("
                SELECT COUNT(DISTINCT oi.order_id)
                FROM shop_order_items oi
                JOIN shop_orders o ON oi.order_id = o.id
                WHERE oi.product_id = ?
                AND o.status IN ('pending', 'confirmed', 'in_transit')
            ");
            $order_stmt->execute([$product['id']]);
            $order_count = (int)$order_stmt->fetchColumn();
            $product['has_orders'] = $order_count > 0;
            $product['order_count'] = $order_count;

            // Info del viaje
            if ($product['origin_country'] && $product['destination_city']) {
                $origin = getShortCity($product['origin_country']);
                $dest = getShortCity($product['destination_city']);
                $product['trip_info'] = $origin . ' → ' . $dest;

                if ($product['fecha_viaje']) {
                    $product['trip_info'] .= ' (' . date('d/m/Y', strtotime($product['fecha_viaje'])) . ')';
                }
            } else {
                $product['trip_info'] = null;
            }

            $product['price'] = (float)$product['price'];
            $product['stock'] = (int)$product['stock'];
            $product['active'] = (bool)$product['active'];
        }
        
        return [
            'success' => true,
            'products' => $products
        ];

    } catch (PDOException $e) {
        error_log("Error en getUserProducts: " . $e->getMessage());
        return [
            'error' => 'Error cargando productos: ' . $e->getMessage(),
            'success' => false
        ];
    }
}

function getSellerStats() {
    global $conexion;
    
    if (!isLoggedIn()) {
        return [
            'error' => 'Usuario no autenticado',
            'redirect' => '../login.php'
        ];
    }
    
    $user = getCurrentUser();
    $user_id = $user['id'];
    
    try {
        $sql = "SELECT 
                    COUNT(*) as total_products,
                    SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) as active_products,
                    SUM(CASE WHEN stock <= 5 AND stock > 0 THEN 1 ELSE 0 END) as low_stock_products,
                    SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) as out_of_stock_products
                FROM shop_products 
                WHERE seller_id = ?";
        
        $stmt = $conexion->prepare($sql);
        $stmt->execute([$user_id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$stats) {
            $stats = [
                'total_products' => 0, 
                'active_products' => 0,
                'low_stock_products' => 0,
                'out_of_stock_products' => 0
            ];
        }
        
        // Estadísticas de ventas (simuladas por ahora)
        $sales_sql = "SELECT 
                        COUNT(DISTINCT oi.order_id) as total_orders,
                        COALESCE(SUM(oi.subtotal), 0) as total_revenue
                      FROM shop_order_items oi 
                      WHERE oi.seller_id = ?";
        
        $sales_stmt = $conexion->prepare($sales_sql);
        $sales_stmt->execute([$user_id]);
        $sales_data = $sales_stmt->fetch(PDO::FETCH_ASSOC);
        
        // USAR SISTEMA DE VALORACIÓN UNIFICADO
        $rating_data = getUnifiedSellerRating($user_id);
        
        return [
            'success' => true,
            'stats' => [
                'total_products' => (int)$stats['total_products'],
                'active_products' => (int)$stats['active_products'],
                'low_stock_products' => (int)$stats['low_stock_products'],
                'out_of_stock_products' => (int)$stats['out_of_stock_products'],
                'total_sales' => (int)($sales_data['total_orders'] ?? 0),
                'total_revenue' => (float)($sales_data['total_revenue'] ?? 0),
                'average_rating' => $rating_data['average_rating'],
                'total_reviews' => $rating_data['total_ratings'],
                'rating_display' => $rating_data['rating_display'],
                'badge_type' => obtenerTipoEfecto($rating_data['average_rating'])
            ]
        ];
        
    } catch (PDOException $e) {
        return ['error' => 'Error cargando estadísticas'];
    }
}

function updateProduct() {
    global $conexion;
    
    if (!isLoggedIn()) {
        return [
            'error' => 'Usuario no autenticado',
            'redirect' => '../login.php'
        ];
    }
    
    $user = getCurrentUser();
    $seller_id = $user['id'];
    
    $product_id = (int)($_POST['product_id'] ?? 0);
    if ($product_id <= 0) {
        return ['error' => 'ID de producto no válido'];
    }
    
    // Verificar que el producto pertenece al usuario
    $check_stmt = $conexion->prepare("SELECT COUNT(*) FROM shop_products WHERE id = ? AND seller_id = ?");
    $check_stmt->execute([$product_id, $seller_id]);
    if ($check_stmt->fetchColumn() == 0) {
        return ['error' => 'Producto no encontrado o no tienes permisos'];
    }
    
    try {
        $fields_to_update = [];
        $params = [];
        
        // Solo actualizar campos que se envían
        $updateable_fields = [
            'name' => 'string',
            'description' => 'string', 
            'price' => 'float',
            'currency' => 'string',
            'category' => 'string',
            'stock' => 'int',
            'weight' => 'float',
            'dimensions' => 'string',
            'trip_id' => 'int',
            'active' => 'bool'
        ];
        
        foreach ($updateable_fields as $field => $type) {
            if (isset($_POST[$field])) {
                $fields_to_update[] = "$field = ?";
                
                switch ($type) {
                    case 'int':
                        $params[] = (int)$_POST[$field];
                        break;
                    case 'float':
                        $params[] = (float)$_POST[$field];
                        break;
                    case 'bool':
                        $params[] = (bool)$_POST[$field] ? 1 : 0;
                        break;
                    default:
                        $params[] = trim($_POST[$field]);
                }
            }
        }
        
        if (empty($fields_to_update)) {
            return ['error' => 'No hay campos para actualizar'];
        }
        
        // Validaciones básicas
        if (isset($_POST['price']) && (float)$_POST['price'] <= 0) {
            return ['error' => 'El precio debe ser mayor a 0'];
        }
        
        if (isset($_POST['stock']) && (int)$_POST['stock'] < 0) {
            return ['error' => 'El stock no puede ser negativo'];
        }
        
        $fields_to_update[] = "updated_at = NOW()";
        $params[] = $product_id;
        
        $sql = "UPDATE shop_products SET " . implode(', ', $fields_to_update) . " WHERE id = ?";
        $stmt = $conexion->prepare($sql);
        $result = $stmt->execute($params);
        
        if (!$result) {
            return ['error' => 'Error actualizando producto'];
        }
        
        // Manejar nuevas imágenes si se enviaron
        if (isset($_FILES['images'])) {
            $upload_result = uploadImages($product_id, $_FILES['images']);
            if (!$upload_result['success']) {
                // El producto se actualizó pero las imágenes fallaron
                return [
                    'success' => true,
                    'message' => 'Producto actualizado, pero hubo problemas con las imágenes: ' . $upload_result['error'],
                    'images_uploaded' => 0
                ];
            }
        }
        
        return [
            'success' => true,
            'message' => 'Producto actualizado exitosamente'
        ];
        
    } catch (PDOException $e) {
        return ['error' => 'Error de base de datos'];
    }
}

function deleteProduct() {
    global $conexion;
    
    if (!isLoggedIn()) {
        return [
            'error' => 'Usuario no autenticado',
            'redirect' => '../login.php'
        ];
    }
    
    $user = getCurrentUser();
    $seller_id = $user['id'];
    
    $product_id = (int)($_POST['product_id'] ?? 0);
    if ($product_id <= 0) {
        return ['error' => 'ID de producto no válido'];
    }
    
    // Verificar que el producto pertenece al usuario
    $check_stmt = $conexion->prepare("SELECT COUNT(*) FROM shop_products WHERE id = ? AND seller_id = ?");
    $check_stmt->execute([$product_id, $seller_id]);
    if ($check_stmt->fetchColumn() == 0) {
        return ['error' => 'Producto no encontrado o no tienes permisos'];
    }
    
    try {
        $conexion->beginTransaction();
        
        // Obtener rutas de imágenes para eliminar archivos físicos
        $img_stmt = $conexion->prepare("SELECT image_path FROM shop_product_images WHERE product_id = ?");
        $img_stmt->execute([$product_id]);
        $images = $img_stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Eliminar registros de imágenes de la BD
        $delete_images_stmt = $conexion->prepare("DELETE FROM shop_product_images WHERE product_id = ?");
        $delete_images_stmt->execute([$product_id]);
        
        // Eliminar el producto
        $delete_product_stmt = $conexion->prepare("DELETE FROM shop_products WHERE id = ? AND seller_id = ?");
        $delete_product_stmt->execute([$product_id, $seller_id]);
        
        $conexion->commit();
        
        // Eliminar archivos físicos
        foreach ($images as $img_path) {
            $full_path = __DIR__ . '/' . ltrim($img_path, '/');
            if (file_exists($full_path)) {
                unlink($full_path);
            }
            
            // También eliminar miniatura si existe
            $thumb_path = dirname($full_path) . '/thumb_' . basename($full_path);
            if (file_exists($thumb_path)) {
                unlink($thumb_path);
            }
        }
        
        return [
            'success' => true,
            'message' => 'Producto eliminado exitosamente'
        ];
        
    } catch (PDOException $e) {
        $conexion->rollBack();
        return ['error' => 'Error eliminando producto'];
    }
}

function createOrder() {
    global $conexion;
    
    if (!isLoggedIn()) {
        return [
            'error' => 'Usuario no autenticado',
            'redirect' => '../login.php'
        ];
    }
    
    $user = getCurrentUser();
    $buyer_id = $user['id'];
    
    $items = $_POST['items'] ?? [];
    if (empty($items) || !is_array($items)) {
        return ['error' => 'Carrito vacío'];
    }
    
    try {
        $conexion->beginTransaction();
        
        // Crear orden
        $order_number = 'SV' . date('YmdHis') . sprintf('%04d', mt_rand(1, 9999));
        $order_stmt = $conexion->prepare("
            INSERT INTO shop_orders (order_number, buyer_id, status, total_amount, currency, created_at) 
            VALUES (?, ?, 'pending', 0, 'EUR', NOW())
        ");
        $order_stmt->execute([$order_number, $buyer_id]);
        $order_id = $conexion->lastInsertId();
        
        $total_amount = 0;
        
        // Procesar cada item
        foreach ($items as $item) {
            $product_id = (int)($item['product_id'] ?? 0);
            $quantity = (int)($item['quantity'] ?? 1);
            
            if ($product_id <= 0 || $quantity <= 0) continue;
            
            // Verificar producto y stock
            $product_stmt = $conexion->prepare("
                SELECT id, seller_id, price, currency, stock, name 
                FROM shop_products 
                WHERE id = ? AND active = 1
            ");
            $product_stmt->execute([$product_id]);
            $product = $product_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$product) {
                throw new Exception("Producto no disponible: ID $product_id");
            }
            
            if ($product['stock'] < $quantity) {
                throw new Exception("Stock insuficiente para: {$product['name']}");
            }
            
            // Reducir stock
            $stock_stmt = $conexion->prepare("UPDATE shop_products SET stock = stock - ? WHERE id = ?");
            $stock_stmt->execute([$quantity, $product_id]);
            
            // Agregar item a la orden
            $unit_price = (float)$product['price'];
            $subtotal = $unit_price * $quantity;
            $total_amount += $subtotal;
            
            $item_stmt = $conexion->prepare("
                INSERT INTO shop_order_items (order_id, product_id, seller_id, quantity, unit_price, subtotal, currency, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $item_stmt->execute([
                $order_id, $product_id, $product['seller_id'], 
                $quantity, $unit_price, $subtotal, $product['currency']
            ]);
        }
        
        // Actualizar total de la orden
        $total_stmt = $conexion->prepare("UPDATE shop_orders SET total_amount = ? WHERE id = ?");
        $total_stmt->execute([$total_amount, $order_id]);
        
        $conexion->commit();
        
        return [
            'success' => true,
            'order_id' => $order_id,
            'order_number' => $order_number,
            'total_amount' => $total_amount,
            'message' => 'Orden creada exitosamente'
        ];
        
    } catch (Exception $e) {
        $conexion->rollBack();
        return ['error' => $e->getMessage()];
    }
}

function getShortCity($full_name) {
    if (empty($full_name)) return '';
    $parts = explode(',', $full_name);
    return trim($parts[0]);
}

// =======================================================
// SISTEMA DE PROPUESTAS PARA PRODUCTOS (IGUAL QUE SHOP-REQUESTS)
// =======================================================

/**
 * Obtiene detalle completo de un producto con propuestas
 */
function getProductDetail() {
    global $conexion;

    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        return ['error' => 'ID inválido'];
    }

    try {
        // Obtener producto
        $sql = "SELECT
                    sp.*,
                    a.full_name as seller_name,
                    a.verificado as seller_verified,
                    a.email as seller_email,
                    a.id as seller_user_id,
                    t.search_input as origin_country,
                    t.destination_input as destination_city,
                    t.datepicker as fecha_viaje
                FROM shop_products sp
                LEFT JOIN accounts a ON sp.seller_id = a.id
                LEFT JOIN transporting t ON sp.trip_id = t.id
                WHERE sp.id = ?";

        $st = $conexion->prepare($sql);
        $st->execute([$id]);
        $product = $st->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            return ['error' => 'Producto no encontrado'];
        }

        // Obtener imágenes
        $img_stmt = $conexion->prepare("SELECT image_path FROM shop_product_images WHERE product_id = ? ORDER BY is_primary DESC, id ASC");
        $img_stmt->execute([$id]);
        $product['images'] = $img_stmt->fetchAll(PDO::FETCH_COLUMN);
        $product['primary_image'] = !empty($product['images']) ? $product['images'][0] : null;

        // Rating del vendedor
        $rating_data = getUnifiedSellerRating($product['seller_user_id']);
        $product['seller_rating'] = $rating_data['average_rating'];
        $product['seller_total_ratings'] = $rating_data['total_ratings'];

        // Avatar del vendedor
        $product['seller_avatar'] = "https://ui-avatars.com/api/?name=" . urlencode($product['seller_name'] ?: 'Usuario') . "&background=667eea&color=fff&size=96";

        // Obtener propuestas
        $sqlP = "SELECT
                    pp.*,
                    a.full_name as buyer_name,
                    a.verificado as buyer_verified,
                    a.id as buyer_user_id
                 FROM shop_product_proposals pp
                 LEFT JOIN accounts a ON a.id = pp.buyer_id
                 WHERE pp.product_id = ?
                 ORDER BY
                    CASE pp.status
                        WHEN 'accepted' THEN 1
                        WHEN 'pending' THEN 2
                        WHEN 'rejected' THEN 3
                        ELSE 4
                    END,
                    pp.created_at DESC";

        $sp = $conexion->prepare($sqlP);
        $sp->execute([$id]);
        $proposals = $sp->fetchAll(PDO::FETCH_ASSOC);

        foreach ($proposals as &$p) {
            $buyer_id = (int)($p['buyer_user_id'] ?? 0);
            $p['buyer_avatar'] = "https://ui-avatars.com/api/?name=" . urlencode($p['buyer_name'] ?: 'Usuario') . "&background=667eea&color=fff&size=64";

            // Rating del comprador
            $buyer_rating = getUnifiedSellerRating($buyer_id);
            $p['buyer_rating'] = $buyer_rating['average_rating'];
            $p['buyer_total_ratings'] = $buyer_rating['total_ratings'];

            unset($p['buyer_user_id']);
        }

        $product['proposals'] = $proposals;
        unset($product['seller_user_id']);

        return ['success' => true, 'product' => $product];

    } catch (PDOException $e) {
        return ['error' => 'Error cargando producto: ' . $e->getMessage()];
    }
}

/**
 * Enviar una propuesta a un producto
 */
function submitProductProposal() {
    global $conexion;

    $user = getCurrentUser();
    $buyer_id = $user['id'];

    $product_id = (int)($_POST['product_id'] ?? 0);
    $proposed_price = (float)($_POST['proposed_price'] ?? 0);
    $proposed_currency = trim($_POST['proposed_currency'] ?? 'EUR');
    $quantity = (int)($_POST['quantity'] ?? 1);
    $message = trim($_POST['message'] ?? '');

    if ($product_id <= 0) {
        return ['error' => 'ID de producto inválido'];
    }
    if ($proposed_price <= 0) {
        return ['error' => 'El precio debe ser mayor a 0'];
    }
    if ($quantity <= 0) {
        return ['error' => 'La cantidad debe ser mayor a 0'];
    }

    try {
        // Verificar que el producto existe y no es propio
        $stmt = $conexion->prepare("SELECT seller_id, stock, active FROM shop_products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            return ['error' => 'Producto no encontrado'];
        }
        if ($product['seller_id'] == $buyer_id) {
            return ['error' => 'No puedes hacer ofertas en tu propio producto'];
        }
        if (!$product['active']) {
            return ['error' => 'Este producto ya no está disponible'];
        }

        // Verificar si ya envió una propuesta
        $checkStmt = $conexion->prepare("SELECT id FROM shop_product_proposals WHERE product_id = ? AND buyer_id = ?");
        $checkStmt->execute([$product_id, $buyer_id]);
        if ($checkStmt->fetch()) {
            return ['error' => 'Ya enviaste una propuesta para este producto'];
        }

        // Insertar propuesta
        $sql = "INSERT INTO shop_product_proposals
                (product_id, buyer_id, proposed_price, proposed_currency, quantity, message, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())";

        $stmt = $conexion->prepare($sql);
        $stmt->execute([$product_id, $buyer_id, $proposed_price, $proposed_currency, $quantity, $message]);

        return ['success' => true, 'message' => '¡Propuesta enviada exitosamente!'];

    } catch (PDOException $e) {
        return ['error' => 'Error al enviar propuesta: ' . $e->getMessage()];
    }
}

/**
 * Enviar contraoferta
 */
function submitProductCounteroffer() {
    global $conexion;

    $user = getCurrentUser();
    $user_id = $user['id'];

    $proposal_id = (int)($_POST['proposal_id'] ?? 0);
    $proposed_price = (float)($_POST['proposed_price'] ?? 0);
    $proposed_currency = trim($_POST['proposed_currency'] ?? 'EUR');
    $quantity = (int)($_POST['quantity'] ?? 1);
    $message = trim($_POST['message'] ?? '');

    if ($proposal_id <= 0) {
        return ['error' => 'ID de propuesta inválido'];
    }
    if ($proposed_price <= 0) {
        return ['error' => 'El precio debe ser mayor a 0'];
    }

    try {
        // Verificar que el usuario es parte de la negociación
        $stmt = $conexion->prepare("
            SELECT pp.*, p.seller_id
            FROM shop_product_proposals pp
            JOIN shop_products p ON pp.product_id = p.id
            WHERE pp.id = ?
        ");
        $stmt->execute([$proposal_id]);
        $proposal = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$proposal) {
            return ['error' => 'Propuesta no encontrada'];
        }

        $isSeller = ($user_id == $proposal['seller_id']);
        $isBuyer = ($user_id == $proposal['buyer_id']);

        if (!$isSeller && !$isBuyer) {
            return ['error' => 'No autorizado'];
        }

        if ($proposal['status'] !== 'pending') {
            return ['error' => 'Esta propuesta ya fue procesada'];
        }

        $user_type = $isSeller ? 'seller' : 'buyer';

        // Iniciar transacción
        $conexion->beginTransaction();

        // Guardar en historial de negociación
        $stmt = $conexion->prepare("
            INSERT INTO shop_product_negotiations
            (proposal_id, user_id, user_type, proposed_price, proposed_currency, quantity, message, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$proposal_id, $user_id, $user_type, $proposed_price, $proposed_currency, $quantity, $message]);

        // Actualizar la propuesta principal con la última oferta
        $stmt = $conexion->prepare("
            UPDATE shop_product_proposals
            SET proposed_price = ?,
                proposed_currency = ?,
                quantity = ?,
                counteroffer_count = counteroffer_count + 1,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$proposed_price, $proposed_currency, $quantity, $proposal_id]);

        $conexion->commit();

        return ['success' => true, 'message' => 'Contraoferta enviada exitosamente'];

    } catch (PDOException $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        return ['error' => 'Error al enviar contraoferta: ' . $e->getMessage()];
    }
}

/**
 * Aceptar una propuesta
 */
function acceptProductProposal() {
    global $conexion;

    $user = getCurrentUser();
    $user_id = $user['id'];

    $proposal_id = (int)($_POST['proposal_id'] ?? 0);

    if ($proposal_id <= 0) {
        return ['error' => 'ID de propuesta inválido'];
    }

    try {
        // Verificar que la propuesta existe y pertenece a un producto del usuario
        $stmt = $conexion->prepare("
            SELECT pp.*, p.seller_id as product_owner_id
            FROM shop_product_proposals pp
            INNER JOIN shop_products p ON pp.product_id = p.id
            WHERE pp.id = ?
        ");
        $stmt->execute([$proposal_id]);
        $proposal = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$proposal) {
            return ['error' => 'Propuesta no encontrada'];
        }

        if ($proposal['product_owner_id'] != $user_id) {
            return ['error' => 'No tienes permiso para aceptar esta propuesta'];
        }

        if ($proposal['status'] !== 'pending') {
            return ['error' => 'Esta propuesta ya fue procesada'];
        }

        // Iniciar transacción
        $conexion->beginTransaction();

        // Aceptar la propuesta
        $conexion->prepare("UPDATE shop_product_proposals SET status = 'accepted', updated_at = NOW() WHERE id = ?")
            ->execute([$proposal_id]);

        // Rechazar las demás propuestas del mismo producto
        $conexion->prepare("UPDATE shop_product_proposals SET status = 'rejected', updated_at = NOW()
                          WHERE product_id = ? AND id != ? AND status = 'pending'")
            ->execute([$proposal['product_id'], $proposal_id]);

        $conexion->commit();

        return ['success' => true, 'message' => '¡Propuesta aceptada exitosamente!'];

    } catch (PDOException $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        return ['error' => 'Error al aceptar propuesta: ' . $e->getMessage()];
    }
}

/**
 * Rechazar una propuesta
 */
function rejectProductProposal() {
    global $conexion;

    $user = getCurrentUser();
    $user_id = $user['id'];

    $proposal_id = (int)($_POST['proposal_id'] ?? 0);

    if ($proposal_id <= 0) {
        return ['error' => 'ID de propuesta inválido'];
    }

    try {
        // Verificar que la propuesta existe y pertenece a un producto del usuario
        $stmt = $conexion->prepare("
            SELECT pp.*, p.seller_id as product_owner_id
            FROM shop_product_proposals pp
            INNER JOIN shop_products p ON pp.product_id = p.id
            WHERE pp.id = ?
        ");
        $stmt->execute([$proposal_id]);
        $proposal = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$proposal) {
            return ['error' => 'Propuesta no encontrada'];
        }

        if ($proposal['product_owner_id'] != $user_id) {
            return ['error' => 'No tienes permiso para rechazar esta propuesta'];
        }

        if ($proposal['status'] !== 'pending') {
            return ['error' => 'Esta propuesta ya fue procesada'];
        }

        // Rechazar la propuesta
        $conexion->prepare("UPDATE shop_product_proposals SET status = 'rejected', updated_at = NOW() WHERE id = ?")
            ->execute([$proposal_id]);

        return ['success' => true, 'message' => 'Propuesta rechazada'];

    } catch (PDOException $e) {
        return ['error' => 'Error al rechazar propuesta: ' . $e->getMessage()];
    }
}

/**
 * Obtener historial de negociación de una propuesta
 */
function getProductNegotiationHistory() {
    global $conexion;

    $proposal_id = (int)($_GET['proposal_id'] ?? 0);

    if ($proposal_id <= 0) {
        return ['error' => 'ID de propuesta inválido'];
    }

    try {
        $history = [];

        // 1. Obtener la propuesta INICIAL
        $stmt = $conexion->prepare("
            SELECT pp.id as proposal_id,
                   pp.buyer_id as user_id,
                   'buyer' as user_type,
                   pp.proposed_price,
                   pp.proposed_currency,
                   pp.quantity,
                   pp.message,
                   pp.created_at,
                   a.full_name as nombre_usuario,
                   'Comprador' as role_name,
                   'initial' as offer_type
            FROM shop_product_proposals pp
            JOIN accounts a ON pp.buyer_id = a.id
            WHERE pp.id = ?
        ");
        $stmt->execute([$proposal_id]);
        $initialProposal = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($initialProposal) {
            $initialProposal['avatar'] = "https://ui-avatars.com/api/?name=" . urlencode($initialProposal['nombre_usuario']) . "&background=random&size=40";
            $history[] = $initialProposal;
        }

        // 2. Obtener todas las CONTRAOFERTAS
        $stmt = $conexion->prepare("
            SELECT n.id,
                   n.proposal_id,
                   n.user_id,
                   n.user_type,
                   n.proposed_price,
                   n.proposed_currency,
                   n.quantity,
                   n.message,
                   n.created_at,
                   a.full_name as nombre_usuario,
                   CASE
                       WHEN n.user_type = 'seller' THEN 'Vendedor'
                       ELSE 'Comprador'
                   END as role_name,
                   'counteroffer' as offer_type
            FROM shop_product_negotiations n
            JOIN accounts a ON n.user_id = a.id
            WHERE n.proposal_id = ?
            ORDER BY n.created_at ASC
        ");
        $stmt->execute([$proposal_id]);
        $counteroffers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($counteroffers as &$item) {
            $item['avatar'] = "https://ui-avatars.com/api/?name=" . urlencode($item['nombre_usuario']) . "&background=random&size=40";
        }

        $history = array_merge($history, $counteroffers);

        return ['success' => true, 'history' => $history];

    } catch (PDOException $e) {
        return ['error' => 'Error al obtener historial: ' . $e->getMessage()];
    }
}

/**
 * Obtener todos los pedidos del usuario actual
 */
function getUserOrders() {
    global $conexion;

    if (!isLoggedIn()) {
        return [
            'error' => 'Usuario no autenticado',
            'redirect' => '../login.php'
        ];
    }

    $user = getCurrentUser();
    $user_id = $user['id'];

    try {
        // Obtener todas las órdenes del usuario como comprador
        $stmt = $conexion->prepare("
            SELECT
                o.id,
                o.order_number,
                o.buyer_id,
                o.status,
                o.total_amount,
                o.currency,
                o.shipping_address,
                o.billing_address,
                o.notes,
                o.payment_method,
                o.payment_id,
                o.tracking_number,
                o.created_at,
                o.updated_at,
                o.delivered_at
            FROM shop_orders o
            WHERE o.buyer_id = ?
            ORDER BY o.created_at DESC
        ");
        $stmt->execute([$user_id]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Para cada orden, obtener sus items
        foreach ($orders as &$order) {
            $items_stmt = $conexion->prepare("
                SELECT
                    oi.id,
                    oi.product_id,
                    oi.seller_id,
                    oi.quantity,
                    oi.unit_price,
                    oi.subtotal,
                    oi.currency,
                    oi.status,
                    p.name as product_name,
                    p.description as product_description,
                    a.full_name as seller_name
                FROM shop_order_items oi
                LEFT JOIN shop_products p ON oi.product_id = p.id
                LEFT JOIN accounts a ON oi.seller_id = a.id
                WHERE oi.order_id = ?
            ");
            $items_stmt->execute([$order['id']]);
            $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

            // Para cada item, obtener la primera imagen del producto
            foreach ($items as &$item) {
                $image_stmt = $conexion->prepare("
                    SELECT file_path
                    FROM shop_product_images
                    WHERE product_id = ?
                    ORDER BY display_order ASC
                    LIMIT 1
                ");
                $image_stmt->execute([$item['product_id']]);
                $image = $image_stmt->fetch(PDO::FETCH_ASSOC);

                if ($image && !empty($image['file_path'])) {
                    // Asegurarse de que la ruta sea correcta
                    $item['image_url'] = $image['file_path'];
                } else {
                    $item['image_url'] = null;
                }
            }

            $order['items'] = $items;
        }

        return [
            'success' => true,
            'orders' => $orders,
            'count' => count($orders)
        ];

    } catch (PDOException $e) {
        return [
            'error' => 'Error al obtener pedidos: ' . $e->getMessage()
        ];
    }
}
?>