<?php
// shop-chat-actions.php - VERSION CON AUTO-DETECCIÓN DE TABLA USUARIOS
session_start();
require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');

$conn = $conexion;

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'No autenticado',
        'redirect' => '../login.php'
    ]);
    exit;
}

$user_id = $_SESSION['usuario_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if (empty($action)) {
    echo json_encode(['success' => false, 'error' => 'Acción no especificada']);
    exit;
}

// Detectar tabla de usuarios automáticamente
function getUsersTable($conn) {
    static $usersTable = null;
    
    if ($usersTable !== null) {
        return $usersTable;
    }
    
    $tables = ['accounts', 'usuarios', 'users'];
    foreach ($tables as $table) {
        try {
            $stmt = $conn->query("SELECT 1 FROM `{$table}` LIMIT 1");
            if ($stmt) {
                $usersTable = $table;
                return $table;
            }
        } catch (Exception $e) {
            continue;
        }
    }
    
    return 'accounts'; // Default
}

// Detectar columna de nombre
function getNameColumn($conn, $table) {
    static $nameColumn = null;
    
    if ($nameColumn !== null) {
        return $nameColumn;
    }
    
    try {
        $stmt = $conn->query("DESCRIBE `{$table}`");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (in_array('full_name', $columns)) return 'full_name';
        if (in_array('nombre', $columns)) return 'nombre';
        if (in_array('name', $columns)) return 'name';
        if (in_array('username', $columns)) return 'username';
    } catch (Exception $e) {}
    
    return 'full_name'; // Default
}

// Detectar columna de verificación
function getVerifiedColumn($conn, $table) {
    static $verifiedColumn = null;
    
    if ($verifiedColumn !== null) {
        return $verifiedColumn;
    }
    
    try {
        $stmt = $conn->query("DESCRIBE `{$table}`");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (in_array('verified', $columns)) return 'verified';
        if (in_array('verificado', $columns)) return 'verificado';
        if (in_array('is_verified', $columns)) return 'is_verified';
    } catch (Exception $e) {}
    
    return 'verified'; // Default
}

try {
    switch ($action) {
        case 'get_conversations':
            echo json_encode(getConversations($conn, $user_id));
            break;
            
        case 'get_or_create_conversation':
            echo json_encode(getOrCreateConversation($conn, $user_id));
            break;
            
        case 'get_messages':
            echo json_encode(getMessages($conn, $user_id));
            break;
            
        case 'send_message':
            echo json_encode(sendMessage($conn, $user_id));
            break;
            
        case 'send_price_offer':
            echo json_encode(sendPriceOffer($conn, $user_id));
            break;
            
        case 'accept_offer':
            echo json_encode(acceptOffer($conn, $user_id));
            break;
            
        case 'reject_offer':
            echo json_encode(rejectOffer($conn, $user_id));
            break;
            
        case 'counter_offer':
            echo json_encode(counterOffer($conn, $user_id));
            break;
            
        case 'mark_as_read':
            echo json_encode(markAsRead($conn, $user_id));
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit;

// =====================================================
// FUNCIONES
// =====================================================

function getConversations($conn, $user_id) {
    try {
        $sql = "
            SELECT * FROM v_shop_conversations_full
            WHERE (buyer_id = ? OR seller_id = ?)
            AND status = 'active'
            ORDER BY last_message_at DESC
            LIMIT 50
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$user_id, $user_id]);
        
        $conversations = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $user_role = ($row['buyer_id'] == $user_id) ? 'buyer' : 'seller';
            
            $unread_count = ($user_role == 'buyer') 
                ? (int)$row['unread_count_buyer'] 
                : (int)$row['unread_count_seller'];
            
            $other_user_name = ($user_role == 'buyer') 
                ? $row['seller_username'] 
                : $row['buyer_username'];
            
            $conversations[] = [
                'id' => (int)$row['id'],
                'status' => $row['status'],
                'user_role' => $user_role,
                
                'product' => [
                    'id' => (int)$row['product_id'],
                    'name' => $row['product_name'],
                    'price' => (float)$row['product_price'],
                    'currency' => $row['product_currency'] ?? 'EUR',
                    'image' => $row['product_image'] ? "../uploads/{$row['product_image']}" : 'https://via.placeholder.com/60',
                    'stock' => (int)$row['product_stock'],
                    'active' => (bool)$row['product_active']
                ],
                
                'other_user' => [
                    'id' => $user_role == 'buyer' ? (int)$row['seller_id'] : (int)$row['buyer_id'],
                    'name' => $other_user_name,
                    'verified' => $user_role == 'buyer' ? (bool)$row['seller_verified'] : (bool)$row['buyer_verified'],
                    'avatar' => null
                ],
                
                'last_message_preview' => substr($row['last_message_preview'] ?? 'Sin mensajes', 0, 50),
                'last_message_at' => $row['last_message_at'],
                'last_message_at_formatted' => formatRelativeTime($row['last_message_at']),
                'unread_count' => $unread_count,
                'created_at' => $row['created_at']
            ];
        }
        
        return [
            'success' => true,
            'conversations' => $conversations,
            'total' => count($conversations)
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Error al cargar conversaciones: ' . $e->getMessage()];
    }
}

function getOrCreateConversation($conn, $user_id) {
    $product_id = (int)($_POST['product_id'] ?? 0);
    
    if ($product_id <= 0) {
        return ['success' => false, 'error' => 'ID de producto no válido'];
    }
    
    try {
        $stmt = $conn->prepare("
            SELECT id, name, price, currency, stock, active, seller_id
            FROM shop_products 
            WHERE id = ?
        ");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            return ['success' => false, 'error' => 'Producto no encontrado'];
        }
        
        $seller_id = $product['seller_id'];
        
        if ($seller_id == $user_id) {
            return ['success' => false, 'error' => 'No puedes chatear con tu propio producto'];
        }
        
        $stmt = $conn->prepare("
            SELECT id FROM shop_conversations 
            WHERE product_id = ? AND buyer_id = ? AND seller_id = ?
        ");
        $stmt->execute([$product_id, $user_id, $seller_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            $conversation_id = $existing['id'];
        } else {
            $welcome_message = "👋 ¡Hola! Has iniciado una conversación sobre el producto \"{$product['name']}\". Puedes hacer preguntas o enviar una oferta de precio.";
            
            $stmt = $conn->prepare("
                INSERT INTO shop_conversations 
                (product_id, buyer_id, seller_id, status, created_at, last_message_at, last_message_preview, unread_count_buyer, unread_count_seller)
                VALUES (?, ?, ?, 'active', NOW(), NOW(), ?, 0, 1)
            ");
            $stmt->execute([$product_id, $user_id, $seller_id, $welcome_message]);
            $conversation_id = $conn->lastInsertId();
            
            $stmt = $conn->prepare("
                INSERT INTO shop_messages 
                (conversation_id, sender_id, receiver_id, product_id, message, message_type, is_read, created_at, is_price_offer)
                VALUES (?, ?, ?, ?, ?, 'system', 1, NOW(), 0)
            ");
            $stmt->execute([$conversation_id, $seller_id, $user_id, $product_id, $welcome_message]);
        }
        
        // Obtener vendedor con auto-detección
        $usersTable = getUsersTable($conn);
        $nameColumn = getNameColumn($conn, $usersTable);
        $verifiedColumn = getVerifiedColumn($conn, $usersTable);
        
        $stmt = $conn->prepare("SELECT {$nameColumn} as nombre, {$verifiedColumn} as verificado FROM {$usersTable} WHERE id = ?");
        $stmt->execute([$seller_id]);
        $seller = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $conn->prepare("SELECT image_path FROM shop_product_images WHERE product_id = ? AND is_primary = 1 LIMIT 1");
        $stmt->execute([$product_id]);
        $image = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'conversation' => [
                'id' => $conversation_id,
                'user_role' => 'buyer',
                'product' => [
                    'id' => (int)$product['id'],
                    'name' => $product['name'],
                    'price' => (float)$product['price'],
                    'currency' => $product['currency'] ?? 'EUR',
                    'image' => $image ? "../uploads/{$image['image_path']}" : 'https://via.placeholder.com/60',
                    'stock' => (int)$product['stock'],
                    'active' => (bool)$product['active']
                ],
                'other_user' => [
                    'id' => $seller_id,
                    'name' => $seller['nombre'] ?? 'Vendedor',
                    'verified' => (bool)($seller['verificado'] ?? 0),
                    'avatar' => null
                ]
            ]
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Error al crear conversación: ' . $e->getMessage()];
    }
}

function getMessages($conn, $user_id) {
    $conversation_id = (int)($_GET['conversation_id'] ?? 0);
    
    if ($conversation_id <= 0) {
        return ['success' => false, 'error' => 'ID de conversación no válido'];
    }
    
    try {
        $stmt = $conn->prepare("
            SELECT buyer_id, seller_id FROM shop_conversations WHERE id = ?
        ");
        $stmt->execute([$conversation_id]);
        $conv = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$conv || ($conv['buyer_id'] != $user_id && $conv['seller_id'] != $user_id)) {
            return ['success' => false, 'error' => 'No tienes acceso a esta conversación'];
        }
        
        $sql = "
            SELECT * FROM v_shop_messages_full
            WHERE conversation_id = ?
            ORDER BY created_at ASC
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$conversation_id]);
        
        $messages = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $msg = [
                'id' => (int)$row['id'],
                'conversation_id' => (int)$row['conversation_id'],
                'sender_id' => (int)$row['sender_id'],
                'message' => $row['message'],
                'message_type' => $row['message_type'],
                'is_mine' => ($row['sender_id'] == $user_id),
                'is_read' => (bool)$row['is_read'],
                'created_at' => $row['created_at'],
                'created_at_formatted' => date('H:i', strtotime($row['created_at']))
            ];
            
            if ($row['is_price_offer'] == 1 || $row['message_type'] == 'price_offer') {
                $offer_amount = $row['offer_amount'] ?? $row['price_offer_amount'];
                $original_price = $row['original_price'];
                $status = $row['offer_status'] ?? $row['price_offer_status'] ?? 'pending';
                
                if ($offer_amount && $original_price) {
                    $discount = (($original_price - $offer_amount) / $original_price) * 100;
                    $msg['price_offer'] = [
                        'amount' => (float)$offer_amount,
                        'currency' => $row['price_offer_currency'] ?? 'EUR',
                        'status' => $status,
                        'original_price' => (float)$original_price,
                        'discount_percentage' => $discount,
                        'expires_at' => $row['offer_expires_at'] ?? $row['price_offer_expires_at']
                    ];
                }
            }
            
            $messages[] = $msg;
        }
        
        return [
            'success' => true,
            'messages' => $messages
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Error al cargar mensajes: ' . $e->getMessage()];
    }
}

function sendMessage($conn, $user_id) {
    $conversation_id = (int)($_POST['conversation_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    
    if ($conversation_id <= 0 || empty($message)) {
        return ['success' => false, 'error' => 'Datos incompletos'];
    }
    
    try {
        $stmt = $conn->prepare("
            SELECT buyer_id, seller_id, product_id FROM shop_conversations WHERE id = ?
        ");
        $stmt->execute([$conversation_id]);
        $conv = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$conv) {
            return ['success' => false, 'error' => 'Conversación no encontrada'];
        }
        
        $receiver_id = ($conv['buyer_id'] == $user_id) ? $conv['seller_id'] : $conv['buyer_id'];
        
        $stmt = $conn->prepare("
            INSERT INTO shop_messages 
            (conversation_id, sender_id, receiver_id, product_id, message, message_type, is_read, created_at, is_price_offer)
            VALUES (?, ?, ?, ?, ?, 'text', 0, NOW(), 0)
        ");
        $stmt->execute([$conversation_id, $user_id, $receiver_id, $conv['product_id'], $message]);
        
        $unread_field = ($conv['buyer_id'] == $user_id) ? 'unread_count_seller' : 'unread_count_buyer';
        $stmt = $conn->prepare("
            UPDATE shop_conversations 
            SET last_message_at = NOW(), 
                last_message_preview = ?,
                {$unread_field} = {$unread_field} + 1
            WHERE id = ?
        ");
        $preview = substr($message, 0, 100);
        $stmt->execute([$preview, $conversation_id]);
        
        return ['success' => true, 'message' => 'Mensaje enviado'];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Error al enviar mensaje: ' . $e->getMessage()];
    }
}

function sendPriceOffer($conn, $user_id) {
    $conversation_id = (int)($_POST['conversation_id'] ?? 0);
    $offered_price = (float)($_POST['offered_price'] ?? 0);
    $currency = $_POST['currency'] ?? 'EUR';
    $message = trim($_POST['message'] ?? '');
    
    if ($conversation_id <= 0 || $offered_price <= 0) {
        return ['success' => false, 'error' => 'Datos incompletos'];
    }
    
    try {
        $stmt = $conn->prepare("
            SELECT c.buyer_id, c.seller_id, c.product_id, p.price, p.currency
            FROM shop_conversations c
            INNER JOIN shop_products p ON c.product_id = p.id
            WHERE c.id = ?
        ");
        $stmt->execute([$conversation_id]);
        $conv = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$conv) {
            return ['success' => false, 'error' => 'Conversación no encontrada'];
        }
        
        $original_price = $conv['price'];
        $receiver_id = ($conv['buyer_id'] == $user_id) ? $conv['seller_id'] : $conv['buyer_id'];
        
        if ($offered_price >= $original_price) {
            return ['success' => false, 'error' => 'La oferta debe ser menor al precio actual'];
        }
        
        $offer_message = $message ?: "Te ofrezco {$offered_price}{$currency}";
        $expires_at = date('Y-m-d H:i:s', strtotime('+7 days'));
        
        $stmt = $conn->prepare("
            INSERT INTO shop_messages 
            (conversation_id, sender_id, receiver_id, product_id, message, message_type, is_read, created_at, is_price_offer, price_offer_amount, price_offer_currency, price_offer_status, price_offer_expires_at)
            VALUES (?, ?, ?, ?, ?, 'price_offer', 0, NOW(), 1, ?, ?, 'pending', ?)
        ");
        $stmt->execute([$conversation_id, $user_id, $receiver_id, $conv['product_id'], $offer_message, $offered_price, $currency, $expires_at]);
        $message_id = $conn->lastInsertId();
        
        $stmt = $conn->prepare("
            INSERT INTO shop_price_offer_history 
            (message_id, conversation_id, product_id, buyer_id, seller_id, offered_price, original_price, status, expires_at, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
        ");
        $stmt->execute([$message_id, $conversation_id, $conv['product_id'], $conv['buyer_id'], $conv['seller_id'], $offered_price, $original_price, $expires_at]);
        
        $unread_field = ($conv['buyer_id'] == $user_id) ? 'unread_count_seller' : 'unread_count_buyer';
        $stmt = $conn->prepare("
            UPDATE shop_conversations 
            SET last_message_at = NOW(), 
                last_message_preview = ?,
                {$unread_field} = {$unread_field} + 1
            WHERE id = ?
        ");
        $preview = "💰 Oferta: {$offered_price}{$currency}";
        $stmt->execute([$preview, $conversation_id]);
        
        return ['success' => true, 'message' => 'Oferta enviada'];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Error al enviar oferta: ' . $e->getMessage()];
    }
}

function acceptOffer($conn, $user_id) {
    $message_id = (int)($_POST['message_id'] ?? 0);
    
    try {
        $stmt = $conn->prepare("
            SELECT poh.*, c.seller_id, c.product_id
            FROM shop_price_offer_history poh
            INNER JOIN shop_conversations c ON poh.conversation_id = c.id
            WHERE poh.message_id = ?
        ");
        $stmt->execute([$message_id]);
        $offer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$offer || $offer['seller_id'] != $user_id || $offer['status'] != 'pending') {
            return ['success' => false, 'error' => 'No se puede aceptar esta oferta'];
        }
        
        $stmt = $conn->prepare("UPDATE shop_price_offer_history SET status = 'accepted', accepted_at = NOW() WHERE message_id = ?");
        $stmt->execute([$message_id]);
        
        $stmt = $conn->prepare("UPDATE shop_messages SET price_offer_status = 'accepted' WHERE id = ?");
        $stmt->execute([$message_id]);
        
        $stmt = $conn->prepare("UPDATE shop_products SET price = ? WHERE id = ?");
        $stmt->execute([$offer['offered_price'], $offer['product_id']]);
        
        return ['success' => true, 'message' => 'Oferta aceptada', 'new_price' => $offer['offered_price'], 'currency' => 'EUR'];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function rejectOffer($conn, $user_id) {
    $message_id = (int)($_POST['message_id'] ?? 0);
    
    try {
        $stmt = $conn->prepare("UPDATE shop_price_offer_history SET status = 'rejected', rejected_at = NOW() WHERE message_id = ?");
        $stmt->execute([$message_id]);
        
        $stmt = $conn->prepare("UPDATE shop_messages SET price_offer_status = 'rejected' WHERE id = ?");
        $stmt->execute([$message_id]);
        
        return ['success' => true, 'message' => 'Oferta rechazada'];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function counterOffer($conn, $user_id) {
    return sendPriceOffer($conn, $user_id);
}

function markAsRead($conn, $user_id) {
    $conversation_id = (int)($_POST['conversation_id'] ?? 0);
    
    try {
        $stmt = $conn->prepare("UPDATE shop_messages SET is_read = 1 WHERE conversation_id = ? AND receiver_id = ? AND is_read = 0");
        $stmt->execute([$conversation_id, $user_id]);
        
        $stmt = $conn->prepare("SELECT buyer_id FROM shop_conversations WHERE id = ?");
        $stmt->execute([$conversation_id]);
        $conv = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $unread_field = ($conv['buyer_id'] == $user_id) ? 'unread_count_buyer' : 'unread_count_seller';
        
        $stmt = $conn->prepare("UPDATE shop_conversations SET {$unread_field} = 0 WHERE id = ?");
        $stmt->execute([$conversation_id]);
        
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function formatRelativeTime($datetime) {
    if (!$datetime) return '';
    $now = time();
    $time = strtotime($datetime);
    $diff = $now - $time;
    
    if ($diff < 60) return 'Ahora';
    if ($diff < 3600) return floor($diff / 60) . 'min';
    if ($diff < 86400) return floor($diff / 3600) . 'h';
    if ($diff < 604800) return floor($diff / 86400) . 'd';
    
    return date('d/m', $time);
}
?>