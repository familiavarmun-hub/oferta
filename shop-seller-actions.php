<?php
/**
 * shop-seller-actions.php - VERSIÓN ADAPTADA
 * Compatible con tu estructura existente de shop_messages
 */

session_start();
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Debes iniciar sesión'
    ]);
    exit;
}

$user_id = $_SESSION['usuario_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'follow':
            followSeller($conexion, $user_id);
            break;
        case 'unfollow':
            unfollowSeller($conexion, $user_id);
            break;
        case 'check_follow':
            checkFollowStatus($conexion, $user_id);
            break;
        case 'send_message':
            sendMessage($conexion, $user_id);
            break;
        case 'get_followers_count':
            getFollowersCount($conexion);
            break;
        case 'get_products':
            getSellerProducts($conexion);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function followSeller($conexion, $user_id) {
    $seller_id = (int)($_POST['seller_id'] ?? 0);
    
    if ($seller_id <= 0 || $user_id == $seller_id) {
        echo json_encode(['success' => false, 'message' => 'Vendedor no válido']);
        return;
    }
    
    $check = $conexion->prepare("SELECT id FROM shop_followers WHERE follower_id = ? AND seller_id = ?");
    $check->execute([$user_id, $seller_id]);
    
    if ($check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Ya sigues a este vendedor', 'following' => true]);
        return;
    }
    
    $insert = $conexion->prepare("INSERT INTO shop_followers (follower_id, seller_id, created_at) VALUES (?, ?, NOW())");
    
    if ($insert->execute([$user_id, $seller_id])) {
        $seller = $conexion->prepare("SELECT full_name FROM accounts WHERE id = ?");
        $seller->execute([$seller_id]);
        $name = $seller->fetch(PDO::FETCH_ASSOC);
        
        $count = $conexion->prepare("SELECT COUNT(*) as total FROM shop_followers WHERE seller_id = ?");
        $count->execute([$seller_id]);
        $total = $count->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => '¡Ahora sigues a ' . htmlspecialchars($name['full_name']) . '!',
            'following' => true,
            'followers_count' => (int)$total['total']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al seguir']);
    }
}

function unfollowSeller($conexion, $user_id) {
    $seller_id = (int)($_POST['seller_id'] ?? 0);
    
    $delete = $conexion->prepare("DELETE FROM shop_followers WHERE follower_id = ? AND seller_id = ?");
    
    if ($delete->execute([$user_id, $seller_id])) {
        $count = $conexion->prepare("SELECT COUNT(*) as total FROM shop_followers WHERE seller_id = ?");
        $count->execute([$seller_id]);
        $total = $count->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Has dejado de seguir',
            'following' => false,
            'followers_count' => (int)$total['total']
        ]);
    }
}

function checkFollowStatus($conexion, $user_id) {
    $seller_id = (int)($_GET['seller_id'] ?? 0);
    
    $check = $conexion->prepare("SELECT id FROM shop_followers WHERE follower_id = ? AND seller_id = ?");
    $check->execute([$user_id, $seller_id]);
    $following = $check->fetch() ? true : false;
    
    $count = $conexion->prepare("SELECT COUNT(*) as total FROM shop_followers WHERE seller_id = ?");
    $count->execute([$seller_id]);
    $total = $count->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'following' => $following,
        'followers_count' => (int)$total['total']
    ]);
}

/**
 * ✅ ADAPTADO para tu tabla shop_messages existente
 * Campos: sender_id, receiver_id, product_id, order_id, message, is_read, created_at
 */
function sendMessage($conexion, $user_id) {
    $seller_id = (int)($_POST['seller_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    
    if ($seller_id <= 0 || $user_id == $seller_id) {
        echo json_encode(['success' => false, 'message' => 'Destinatario no válido']);
        return;
    }
    
    if (empty($message) || strlen($message) < 10) {
        echo json_encode(['success' => false, 'message' => 'Mensaje muy corto (mínimo 10 caracteres)']);
        return;
    }
    
    // Si hay asunto, agregarlo al mensaje
    $full_message = !empty($subject) ? "[{$subject}] {$message}" : $message;
    
    // ✅ INSERT adaptado a tu estructura (con product_id y order_id en NULL)
    $insert = $conexion->prepare("INSERT INTO shop_messages 
        (sender_id, receiver_id, message, is_read, created_at, product_id, order_id) 
        VALUES (?, ?, ?, 0, NOW(), NULL, NULL)");
    
    if ($insert->execute([$user_id, $seller_id, $full_message])) {
        echo json_encode([
            'success' => true,
            'message' => '¡Mensaje enviado! El vendedor lo verá pronto.'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al enviar']);
    }
}

function getFollowersCount($conexion) {
    $seller_id = (int)($_GET['seller_id'] ?? 0);

    $count = $conexion->prepare("SELECT COUNT(*) as total FROM shop_followers WHERE seller_id = ?");
    $count->execute([$seller_id]);
    $total = $count->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'followers_count' => (int)$total['total']
    ]);
}

function getSellerProducts($conexion) {
    $seller_id = (int)($_GET['seller_id'] ?? 0);

    if ($seller_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Vendedor no válido']);
        return;
    }

    try {
        $query = $conexion->prepare("
            SELECT
                p.id,
                p.name,
                p.description,
                p.price,
                p.currency,
                p.stock,
                p.active,
                (SELECT image_path FROM shop_product_images
                 WHERE product_id = p.id AND is_primary = 1
                 LIMIT 1) AS image
            FROM shop_products p
            WHERE p.seller_id = ? AND p.active = 1 AND p.stock > 0
            ORDER BY p.created_at DESC
            LIMIT 50
        ");

        $query->execute([$seller_id]);
        $products = $query->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'products' => $products,
            'count' => count($products)
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error al cargar productos: ' . $e->getMessage()
        ]);
    }
}
?>