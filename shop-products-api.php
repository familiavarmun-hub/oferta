<?php
/**
 * shop-products-api.php - API Backend para SendVialo Shop Products (OFERTA)
 * Maneja productos publicados por viajeros/vendedores
 */

// =====================================================
// CONFIGURACIÓN INICIAL
// =====================================================

$DEBUG = isset($_GET['debug']) || isset($_POST['debug']);
if ($DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

header('Content-Type: application/json; charset=utf-8');

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Requerir configuración principal
$cfg = __DIR__ . '/config.php';
if (!file_exists($cfg)) {
    echo json_encode(['success' => false, 'error' => "No se encontró config.php"]);
    exit;
}
require_once $cfg;

// Verificar conexión PDO
if (!isset($conexion) || !($conexion instanceof PDO)) {
    echo json_encode(['success' => false, 'error' => '$conexion (PDO) no existe. Revisa config.php']);
    exit;
}

try {
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
    // Ya está configurado
}

// =====================================================
// FUNCIONES AUXILIARES
// =====================================================

function getCurrentUserId() {
    return $_SESSION['usuario_id'] ?? $_SESSION['id'] ?? null;
}

function isUserLoggedIn() {
    return isset($_SESSION['usuario_id']) || isset($_SESSION['id']);
}

// =====================================================
// ROUTER - Manejo de acciones
// =====================================================

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'ping':
            echo json_encode(['success' => true, 'pong' => true, 'timestamp' => time()]);
            break;

        case 'get_products':
            getProducts($conexion);
            break;

        case 'get_my_products':
            getMyProducts($conexion);
            break;

        case 'get_product_detail':
            getProductDetail($conexion);
            break;

        case 'get_favorites':
            getFavorites($conexion);
            break;

        case 'add_favorite':
            addFavorite($conexion);
            break;

        case 'remove_favorite':
            removeFavorite($conexion);
            break;

        case 'get_my_purchases':
            getMyPurchases($conexion);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Acción no reconocida: ' . $action]);
    }
} catch (Throwable $e) {
    error_log("Error en shop-products-api: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// =====================================================
// FUNCIONES PRINCIPALES
// =====================================================

/**
 * Obtener todos los productos activos
 */
function getProducts($conexion) {
    $userId = getCurrentUserId();
    $status = $_GET['status'] ?? 'active';
    $category = $_GET['category'] ?? null;
    $origin = $_GET['origin'] ?? null;
    $destination = $_GET['destination'] ?? null;
    $sort = $_GET['sort'] ?? 'recent';

    try {
        // Subquery para favoritos
        $isFavoritedSubquery = '';
        if ($userId) {
            $isFavoritedSubquery = ", (SELECT COUNT(*) > 0 FROM product_favorites pf
                                        WHERE pf.product_id = p.id
                                        AND pf.user_id = :current_user_id) as is_favorited";
        }

        $query = "SELECT p.*,
          COALESCE(u.full_name, u.username) as seller_name,
          u.username as seller_username,
          COALESCE(u.verificado, 0) as seller_verified,
          u.id as seller_avatar_id,
          COALESCE((SELECT AVG(c.valoracion) FROM comentarios c WHERE c.usuario_id = p.seller_id AND c.bloqueado = 0), 0) as seller_rating,
          (SELECT COUNT(*) FROM comentarios c WHERE c.usuario_id = p.seller_id AND c.bloqueado = 0) as seller_total_ratings,
          (SELECT image_path FROM shop_product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image,
          (SELECT COUNT(*) FROM shop_product_orders WHERE product_id = p.id AND status != 'cancelled') as sales_count
          {$isFavoritedSubquery}
          FROM shop_products p
          LEFT JOIN accounts u ON p.seller_id = u.id
          WHERE p.active = 1";

        $params = [];

        // Filtro por estado
        if ($status === 'active') {
            $query .= " AND p.stock > 0";
        }

        // Filtro por categoría
        if (!empty($category)) {
            $query .= " AND p.category = :category";
            $params[':category'] = $category;
        }

        // Filtro por origen
        if (!empty($origin)) {
            $query .= " AND p.origin_city LIKE :origin";
            $params[':origin'] = '%' . $origin . '%';
        }

        // Filtro por destino
        if (!empty($destination)) {
            $query .= " AND p.destination_city LIKE :destination";
            $params[':destination'] = '%' . $destination . '%';
        }

        // Ordenamiento
        switch ($sort) {
            case 'popular':
                $query .= " ORDER BY sales_count DESC, p.created_at DESC";
                break;
            case 'price_low':
                $query .= " ORDER BY p.price ASC";
                break;
            case 'price_high':
                $query .= " ORDER BY p.price DESC";
                break;
            case 'recent':
            default:
                $query .= " ORDER BY p.created_at DESC";
                break;
        }

        if ($userId) {
            $params[':current_user_id'] = $userId;
        }

        $stmt = $conexion->prepare($query);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Procesar datos
        foreach ($products as &$product) {
            // Imagen por defecto si no hay
            if (empty($product['primary_image'])) {
                $product['primary_image'] = 'uploads/products/default.png';
            }

            // Favorito por defecto
            if (!isset($product['is_favorited'])) {
                $product['is_favorited'] = false;
            } else {
                $product['is_favorited'] = (bool)$product['is_favorited'];
            }

            // Rating formateado
            $product['seller_rating'] = round((float)$product['seller_rating'], 1);

            // Convertir tipos
            $product['price'] = (float)$product['price'];
            $product['stock'] = (int)$product['stock'];
            $product['sales_count'] = (int)$product['sales_count'];
        }

        echo json_encode(['success' => true, 'products' => $products], JSON_UNESCAPED_UNICODE);

    } catch (PDOException $e) {
        error_log("Error en getProducts: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Error al obtener productos', 'details' => $e->getMessage()]);
    }
}

/**
 * Obtener productos del vendedor actual
 */
function getMyProducts($conexion) {
    $userId = getCurrentUserId();

    if (!$userId) {
        echo json_encode(['success' => false, 'error' => 'No autenticado']);
        return;
    }

    $status = $_GET['status'] ?? null; // all, active, paused, sold

    try {
        $query = "SELECT p.*,
          (SELECT image_path FROM shop_product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image,
          (SELECT COUNT(*) FROM shop_product_orders WHERE product_id = p.id AND status != 'cancelled') as sales_count,
          (SELECT SUM(quantity) FROM shop_product_orders WHERE product_id = p.id AND status != 'cancelled') as total_sold,
          (SELECT COUNT(*) FROM product_views WHERE product_id = p.id) as view_count
          FROM shop_products p
          WHERE p.seller_id = :seller_id";

        $params = [':seller_id' => $userId];

        // Filtro por estado
        if ($status === 'active') {
            $query .= " AND p.active = 1 AND p.stock > 0";
        } elseif ($status === 'paused') {
            $query .= " AND p.active = 0";
        } elseif ($status === 'sold') {
            $query .= " AND p.stock = 0";
        }

        $query .= " ORDER BY p.created_at DESC";

        $stmt = $conexion->prepare($query);
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Procesar datos
        foreach ($products as &$product) {
            if (empty($product['primary_image'])) {
                $product['primary_image'] = 'uploads/products/default.png';
            }
            $product['price'] = (float)$product['price'];
            $product['stock'] = (int)$product['stock'];
            $product['sales_count'] = (int)$product['sales_count'];
            $product['total_sold'] = (int)($product['total_sold'] ?? 0);
            $product['view_count'] = (int)($product['view_count'] ?? 0);
        }

        echo json_encode(['success' => true, 'products' => $products], JSON_UNESCAPED_UNICODE);

    } catch (PDOException $e) {
        error_log("Error en getMyProducts: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Error al obtener productos']);
    }
}

/**
 * Obtener detalle de un producto
 */
function getProductDetail($conexion) {
    $productId = (int)($_GET['id'] ?? 0);
    $userId = getCurrentUserId();

    if ($productId <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID de producto inválido']);
        return;
    }

    try {
        // Registrar vista
        if ($userId) {
            $stmtView = $conexion->prepare("INSERT IGNORE INTO product_views (product_id, user_id) VALUES (:product_id, :user_id)");
            $stmtView->execute([':product_id' => $productId, ':user_id' => $userId]);
        }

        $isFavoritedSubquery = '';
        if ($userId) {
            $isFavoritedSubquery = ", (SELECT COUNT(*) > 0 FROM product_favorites pf
                                        WHERE pf.product_id = p.id
                                        AND pf.user_id = :current_user_id) as is_favorited";
        }

        $query = "SELECT p.*,
          COALESCE(u.full_name, u.username) as seller_name,
          u.username as seller_username,
          COALESCE(u.verificado, 0) as seller_verified,
          u.id as seller_avatar_id,
          COALESCE((SELECT AVG(c.valoracion) FROM comentarios c WHERE c.usuario_id = p.seller_id AND c.bloqueado = 0), 0) as seller_rating,
          (SELECT COUNT(*) FROM comentarios c WHERE c.usuario_id = p.seller_id AND c.bloqueado = 0) as seller_total_ratings,
          (SELECT COUNT(*) FROM shop_product_orders WHERE product_id = p.id AND status != 'cancelled') as sales_count,
          (SELECT COUNT(*) FROM product_views WHERE product_id = p.id) as view_count
          {$isFavoritedSubquery}
          FROM shop_products p
          LEFT JOIN accounts u ON p.seller_id = u.id
          WHERE p.id = :product_id";

        $params = [':product_id' => $productId];
        if ($userId) {
            $params[':current_user_id'] = $userId;
        }

        $stmt = $conexion->prepare($query);
        $stmt->execute($params);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            echo json_encode(['success' => false, 'error' => 'Producto no encontrado']);
            return;
        }

        // Obtener todas las imágenes
        $stmtImages = $conexion->prepare("SELECT image_path, is_primary FROM shop_product_images WHERE product_id = :product_id ORDER BY is_primary DESC");
        $stmtImages->execute([':product_id' => $productId]);
        $product['images'] = $stmtImages->fetchAll(PDO::FETCH_ASSOC);

        // Imagen principal
        $product['primary_image'] = !empty($product['images']) ? $product['images'][0]['image_path'] : 'uploads/products/default.png';

        // Favorito
        if (!isset($product['is_favorited'])) {
            $product['is_favorited'] = false;
        } else {
            $product['is_favorited'] = (bool)$product['is_favorited'];
        }

        // Tipos
        $product['price'] = (float)$product['price'];
        $product['stock'] = (int)$product['stock'];
        $product['seller_rating'] = round((float)$product['seller_rating'], 1);

        echo json_encode(['success' => true, 'product' => $product], JSON_UNESCAPED_UNICODE);

    } catch (PDOException $e) {
        error_log("Error en getProductDetail: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Error al obtener producto']);
    }
}

/**
 * Obtener productos favoritos del usuario
 */
function getFavorites($conexion) {
    $userId = getCurrentUserId();

    if (!$userId) {
        echo json_encode(['success' => false, 'error' => 'No autenticado']);
        return;
    }

    try {
        // Crear tabla si no existe
        $conexion->exec("CREATE TABLE IF NOT EXISTS product_favorites (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            user_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_favorite (product_id, user_id),
            INDEX idx_user (user_id),
            INDEX idx_product (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $query = "SELECT p.*,
          COALESCE(u.full_name, u.username) as seller_name,
          u.username as seller_username,
          COALESCE(u.verificado, 0) as seller_verified,
          u.id as seller_avatar_id,
          COALESCE((SELECT AVG(c.valoracion) FROM comentarios c WHERE c.usuario_id = p.seller_id AND c.bloqueado = 0), 0) as seller_rating,
          (SELECT image_path FROM shop_product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image,
          (SELECT COUNT(*) FROM shop_product_orders WHERE product_id = p.id AND status != 'cancelled') as sales_count,
          1 as is_favorited
          FROM shop_products p
          INNER JOIN product_favorites pf ON p.id = pf.product_id
          LEFT JOIN accounts u ON p.seller_id = u.id
          WHERE pf.user_id = :user_id AND p.active = 1
          ORDER BY pf.created_at DESC";

        $stmt = $conexion->prepare($query);
        $stmt->execute([':user_id' => $userId]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Procesar datos
        foreach ($products as &$product) {
            if (empty($product['primary_image'])) {
                $product['primary_image'] = 'uploads/products/default.png';
            }
            $product['is_favorited'] = true;
            $product['price'] = (float)$product['price'];
            $product['stock'] = (int)$product['stock'];
            $product['seller_rating'] = round((float)$product['seller_rating'], 1);
        }

        echo json_encode(['success' => true, 'products' => $products], JSON_UNESCAPED_UNICODE);

    } catch (PDOException $e) {
        error_log("Error en getFavorites: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Error al obtener favoritos']);
    }
}

/**
 * Añadir producto a favoritos
 */
function addFavorite($conexion) {
    $userId = getCurrentUserId();
    $productId = (int)($_POST['product_id'] ?? $_GET['product_id'] ?? 0);

    if (!$userId) {
        echo json_encode(['success' => false, 'error' => 'No autenticado']);
        return;
    }

    if ($productId <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID de producto inválido']);
        return;
    }

    try {
        // Crear tabla si no existe
        $conexion->exec("CREATE TABLE IF NOT EXISTS product_favorites (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            user_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_favorite (product_id, user_id),
            INDEX idx_user (user_id),
            INDEX idx_product (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $stmt = $conexion->prepare("INSERT IGNORE INTO product_favorites (product_id, user_id, created_at) VALUES (:product_id, :user_id, NOW())");
        $stmt->execute([':product_id' => $productId, ':user_id' => $userId]);

        echo json_encode(['success' => true, 'message' => 'Producto añadido a favoritos']);

    } catch (PDOException $e) {
        error_log("Error en addFavorite: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Error al añadir favorito: ' . $e->getMessage()]);
    }
}

/**
 * Eliminar producto de favoritos
 */
function removeFavorite($conexion) {
    $userId = getCurrentUserId();
    $productId = (int)($_POST['product_id'] ?? $_GET['product_id'] ?? 0);

    if (!$userId) {
        echo json_encode(['success' => false, 'error' => 'No autenticado']);
        return;
    }

    if ($productId <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID de producto inválido']);
        return;
    }

    try {
        $stmt = $conexion->prepare("DELETE FROM product_favorites WHERE product_id = :product_id AND user_id = :user_id");
        $stmt->execute([':product_id' => $productId, ':user_id' => $userId]);

        echo json_encode(['success' => true, 'message' => 'Producto eliminado de favoritos']);

    } catch (PDOException $e) {
        error_log("Error en removeFavorite: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Error al eliminar favorito']);
    }
}

/**
 * Obtener compras del usuario
 */
function getMyPurchases($conexion) {
    $userId = getCurrentUserId();

    if (!$userId) {
        echo json_encode(['success' => false, 'error' => 'No autenticado']);
        return;
    }

    $status = $_GET['status'] ?? null; // all, pending, shipped, delivered

    try {
        $query = "SELECT o.*,
          p.name as product_name,
          p.description as product_description,
          (SELECT image_path FROM shop_product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as product_image,
          COALESCE(seller.full_name, seller.username) as seller_name,
          seller.id as seller_id,
          seller.id as seller_avatar_id
          FROM shop_product_orders o
          JOIN shop_products p ON o.product_id = p.id
          LEFT JOIN accounts seller ON p.seller_id = seller.id
          WHERE o.buyer_id = :buyer_id";

        $params = [':buyer_id' => $userId];

        // Filtro por estado
        if ($status === 'pending') {
            $query .= " AND o.status IN ('pending', 'confirmed')";
        } elseif ($status === 'shipped') {
            $query .= " AND o.status = 'shipped'";
        } elseif ($status === 'delivered') {
            $query .= " AND o.status = 'delivered'";
        }

        $query .= " ORDER BY o.created_at DESC";

        $stmt = $conexion->prepare($query);
        $stmt->execute($params);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Procesar datos
        foreach ($orders as &$order) {
            if (empty($order['product_image'])) {
                $order['product_image'] = 'uploads/products/default.png';
            }
            $order['total_price'] = (float)$order['total_price'];
            $order['quantity'] = (int)$order['quantity'];
        }

        echo json_encode(['success' => true, 'orders' => $orders], JSON_UNESCAPED_UNICODE);

    } catch (PDOException $e) {
        error_log("Error en getMyPurchases: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Error al obtener compras']);
    }
}
?>
