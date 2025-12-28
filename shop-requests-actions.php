<?php
/**
 * shop-requests-actions.php - API Backend para SendVialo Shop Requests
 * ✅ VERSIÓN MEJORADA CON HISTORIAL COMPLETO Y PRECIO ACORDADO
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

// 🔔 SISTEMA DE NOTIFICACIONES
if (file_exists(__DIR__ . '/shop-notifications-helper.php')) {
    require_once __DIR__ . '/shop-notifications-helper.php';
}

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

function getCurrentUserName() {
    return $_SESSION['usuario_nombre'] ?? $_SESSION['full_name'] ?? 'Usuario';
}

function isUserLoggedIn() {
    return isset($_SESSION['usuario_id']) || isset($_SESSION['id']);
}

function getUserRating(PDO $db, $userId) {
    try {
        $stmt = $db->prepare("
            SELECT AVG(rating) as avg_rating, COUNT(*) as total_ratings
            FROM shop_seller_ratings
            WHERE seller_id = :user_id
        ");
        $stmt->execute([':user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'rating' => $result['avg_rating'] ? round($result['avg_rating'], 1) : 0,
            'total_ratings' => $result['total_ratings'] ?? 0
        ];
    } catch (Throwable $e) {
        return ['rating' => 0, 'total_ratings' => 0];
    }
}

function saveBase64Image($base64Data, $fileName) {
    $uploadDir = __DIR__ . '/uploads/request-images/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileName = preg_replace('/[^a-zA-Z0-9._-]/', '', $fileName);
    $fileName = time() . '_' . $fileName;

    if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $matches)) {
        $imageData = substr($base64Data, strpos($base64Data, ',') + 1);
        $imageData = base64_decode($imageData);
        
        if ($imageData === false) {
            return null;
        }

        $extension = $matches[1];
        $fileName = pathinfo($fileName, PATHINFO_FILENAME) . '.' . $extension;
        $filePath = $uploadDir . $fileName;

        if (file_put_contents($filePath, $imageData)) {
            return 'uploads/request-images/' . $fileName;
        }
    }

    return null;
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

        case 'selfcheck':
            selfCheck($conexion);
            break;

        case 'test_requests':
            testRequests($conexion);
            break;

        case 'get_requests':
            $status = $_GET['status'] ?? null;
            $userId = getCurrentUserId();
            $filters = [
                'category' => $_GET['category'] ?? null,
                'destination_city' => $_GET['destination_city'] ?? null,
                'max_budget' => $_GET['max_budget'] ?? null
            ];
            $result = getRequests($conexion, $status, $userId, $filters);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

        case 'get_my_requests':
            getMyRequests($conexion);
            break;

        case 'get_my_proposals':
            getMyProposals($conexion);
            break;

        case 'get_request_detail':
            getRequestDetail($conexion);
            break;

        case 'create_request':
            createRequest($conexion);
            break;

        case 'submit_proposal':
            submitProposal($conexion);
            break;

        case 'accept_proposal':
            acceptProposal($conexion);
            break;

        case 'reject_proposal':
            rejectProposal($conexion);
            break;

        case 'submit_counteroffer':
            submitCounteroffer($conexion);
            break;

        case 'get_negotiation_history':
            getNegotiationHistory($conexion);
            break;

        case 'get_request_qr':
            getRequestQR($conexion);
            break;

        case 'add_favorite':
            addFavorite($conexion);
            break;

        case 'remove_favorite':
            removeFavorite($conexion);
            break;

        case 'get_delivery_by_proposal':
            getDeliveryByProposal($conexion);
            break;

        case 'get_users_data':
            getUsersData($conexion);
            break;

        case 'get_my_favorites':
            getMyFavorites($conexion);
            break;

case 'update_request':
    updateRequest($conexion);
    break;


case 'delete_request':
    deleteRequest($conexion);
    break;


        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida: ' . $action]);
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit;

// =====================================================
// FUNCIONES DE ACCIONES
// =====================================================

function selfCheck(PDO $db) {
    $out = ['ok' => true, 'checks' => []];

    try {
        $v = $db->query('SELECT VERSION() v')->fetch(PDO::FETCH_ASSOC)['v'] ?? '¿?';
        $out['checks'][] = "MySQL: $v";
    } catch (Throwable $e) {
        $out['ok'] = false;
        $out['checks'][] = 'VERSION(): ' . $e->getMessage();
    }

    $tables = ['shop_requests', 'shop_request_proposals', 'shop_request_proposal_history', 'shop_proposal_negotiations', 'accounts', 'shop_notifications', 'shop_deliveries'];
    foreach ($tables as $t) {
        try {
            $c = (int)$db->query("SELECT COUNT(*) c FROM $t")->fetch(PDO::FETCH_ASSOC)['c'];
            $out['checks'][] = "$t: $c filas";
        } catch (Throwable $e) {
            $out['ok'] = false;
            $out['checks'][] = "❌ Tabla $t: " . $e->getMessage();
        }
    }

    echo json_encode(['success' => $out['ok'], 'checks' => $out['checks']], JSON_UNESCAPED_UNICODE);
}

function testRequests(PDO $db) {
    $status = $_GET['status'] ?? 'open';
    try {
        $stmt = $db->prepare("
            SELECT id, user_id, title, category, destination_city, status, created_at
            FROM shop_requests
            WHERE status = :s
            ORDER BY created_at DESC
            LIMIT 20
        ");
        $stmt->execute([':s' => $status]);
        echo json_encode(['success' => true, 'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => 'SQL testRequests: ' . $e->getMessage()]);
    }
}

function getRequests($conexion, $status = null, $userId = null, $filters = []) {
    try {
        $statusArray = [];
        if ($status) {
            $statusArray = array_map('trim', explode(',', $status));
        } else {
            $statusArray = ['open', 'negotiating'];
        }
        
        $statusPlaceholders = [];
        $statusParams = [];
        foreach ($statusArray as $index => $statusValue) {
            $paramName = ":status_{$index}";
            $statusPlaceholders[] = $paramName;
            $statusParams[$paramName] = $statusValue;
        }
        $statusPlaceholdersStr = implode(',', $statusPlaceholders);
        
        $isFavoritedSubquery = '';
        if ($userId) {
            $isFavoritedSubquery = ", (SELECT COUNT(*) > 0 FROM request_favorites rf 
                                        WHERE rf.request_id = r.id 
                                        AND rf.user_id = :current_user_id) as is_favorited";
        }
        
        $query = "SELECT r.*, 
          COALESCE(u.full_name, u.username) as requester_name,
          u.username as requester_username,
          u.full_name as requester_fullname,
          COALESCE(u.verificado, 0) as requester_verified,
          (SELECT COUNT(*) FROM shop_request_proposals WHERE request_id = r.id) as proposal_count,
          u.id as requester_avatar_id,
          EXISTS(SELECT 1 FROM shop_request_proposals WHERE request_id = r.id AND traveler_id = :user_id_check) as user_has_applied,
          COALESCE((SELECT AVG(c.valoracion) FROM comentarios c WHERE c.usuario_id = r.user_id AND c.bloqueado = 0), 0) as requester_rating,
          (SELECT COUNT(*) FROM comentarios c WHERE c.usuario_id = r.user_id AND c.bloqueado = 0) as requester_total_ratings
          {$isFavoritedSubquery}
          FROM shop_requests r
          LEFT JOIN accounts u ON r.user_id = u.id
          WHERE r.status IN ({$statusPlaceholdersStr})";
        
        $params = $statusParams;
        
        if (!empty($filters['category'])) {
            $query .= " AND r.category = :category";
            $params[':category'] = $filters['category'];
        }
        
        if (!empty($filters['destination_city'])) {
            $query .= " AND r.destination_city LIKE :destination_city";
            $params[':destination_city'] = '%' . $filters['destination_city'] . '%';
        }
        
        if (!empty($filters['max_budget'])) {
            $query .= " AND r.budget_amount <= :max_budget";
            $params[':max_budget'] = $filters['max_budget'];
        }
        
        $query .= " ORDER BY r.created_at DESC";
        
        $params[':user_id_check'] = $userId ?: 0;
        
        if ($userId) {
            $params[':current_user_id'] = $userId;
        }
        
        $stmt = $conexion->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($requests as &$request) {
            $request['reference_images'] = !empty($request['reference_images']) 
                ? json_decode($request['reference_images'], true) 
                : [];
            $request['reference_links'] = !empty($request['reference_links']) 
                ? json_decode($request['reference_links'], true) 
                : [];
                
            if (!isset($request['is_favorited'])) {
                $request['is_favorited'] = false;
            } else {
                $request['is_favorited'] = (bool)$request['is_favorited'];
            }
            
            $request['requester_rating'] = round((float)$request['requester_rating'], 1);
        }
        
        return ['success' => true, 'requests' => $requests];
        
    } catch (PDOException $e) {
        error_log("Error en getRequests: " . $e->getMessage());
        return ['success' => false, 'error' => 'Error al obtener solicitudes', 'details' => $e->getMessage()];
    }
}

function getUsersData(PDO $db) {
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $user_ids = $input['user_ids'] ?? [];
    
    if (empty($user_ids) || !is_array($user_ids)) {
        echo json_encode(['success' => false, 'message' => 'IDs de usuario inválidos']);
        exit;
    }
    
    try {
        $user_ids = array_map('intval', $user_ids);
        $user_ids = array_filter($user_ids, function($id) { return $id > 0; });
        
        if (empty($user_ids)) {
            echo json_encode(['success' => false, 'message' => 'No hay IDs válidos']);
            exit;
        }
        
        $placeholders = str_repeat('?,', count($user_ids) - 1) . '?';
        
        $sql = "SELECT 
                    a.id,
                    a.username,
                    a.full_name,
                    a.verificado,
                    a.ruta_imagen,
                    COALESCE(AVG(c.valoracion), 0) as rating,
                    COUNT(DISTINCT c.id) as total_ratings
                FROM accounts a
                LEFT JOIN comentarios c ON c.usuario_id = a.id AND c.bloqueado = 0
                WHERE a.id IN ($placeholders)
                GROUP BY a.id, a.username, a.full_name, a.verificado, a.ruta_imagen";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($user_ids);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $formatted_users = array_map(function($user) {
            return [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'full_name' => $user['full_name'],
                'verificado' => (bool)$user['verificado'],
                'ruta_imagen' => $user['ruta_imagen'],
                'rating' => round((float)$user['rating'], 1),
                'total_ratings' => (int)$user['total_ratings']
            ];
        }, $users);
        
        echo json_encode([
            'success' => true,
            'users' => $formatted_users
        ]);
        
    } catch (PDOException $e) {
        error_log("Error en getUsersData: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error al obtener datos de usuarios'
        ]);
    }
    exit;
}

function getMyRequests(PDO $db) {
    $userId = $_GET['user_id'] ?? getCurrentUserId();

    if (!$userId) {
        echo json_encode(['success' => false, 'error' => 'No se especificó usuario']);
        return;
    }

    $sql = "SELECT
            r.*,
            (SELECT COUNT(*) FROM shop_request_proposals p
             WHERE p.request_id = r.id) AS proposal_count,
            (SELECT COUNT(*) FROM shop_request_proposals p
             WHERE p.request_id = r.id AND p.status = 'pending') AS pending_proposals,
            (SELECT COUNT(*) FROM request_favorites rf
             WHERE rf.request_id = r.id) AS favorite_count
          FROM shop_requests r
          WHERE r.user_id = :user_id
          ORDER BY r.created_at DESC";

    try {
        $st = $db->prepare($sql);
        $st->execute([':user_id' => $userId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $r['reference_images'] = !empty($r['reference_images']) ? json_decode($r['reference_images'], true) : [];
            $r['reference_links'] = !empty($r['reference_links']) ? json_decode($r['reference_links'], true) : [];
        }

        echo json_encode(['success' => true, 'requests' => $rows], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => 'SQL getMyRequests: ' . $e->getMessage()]);
    }
}






function getMyProposals(PDO $db) {
    $userId = $_GET['user_id'] ?? getCurrentUserId();

    if (!$userId) {
        echo json_encode(['success' => false, 'error' => 'No se especificó usuario']);
        return;
    }

    $sql = "SELECT
            p.*,
            r.id AS request_id,
            r.title AS request_title,
            r.description AS request_description,
            r.origin_country AS pickup_location,
            r.destination_city AS delivery_location,
            r.destination_city,
            r.origin_country,
            r.quantity AS max_weight,
            r.deadline_date AS requested_delivery_date,
            r.category,
            r.status AS request_status,
            COALESCE(u.full_name, 'Usuario') AS requester_name,
            COALESCE(u.verificado, 0) AS requester_verified,
            u.id AS requester_user_id,
            (SELECT COUNT(*) FROM shop_proposal_negotiations n
             WHERE n.proposal_id = p.id) AS negotiation_count,
            -- ✅ OBTENER ÚLTIMA OFERTA DE HISTORIAL
            (SELECT h.offered_price FROM shop_request_proposal_history h
             WHERE h.proposal_id = p.id
             ORDER BY h.created_at DESC
             LIMIT 1) AS current_price,
            (SELECT h.offered_currency FROM shop_request_proposal_history h
             WHERE h.proposal_id = p.id
             ORDER BY h.created_at DESC
             LIMIT 1) AS current_currency
          FROM shop_request_proposals p
          JOIN shop_requests r ON r.id = p.request_id
          LEFT JOIN accounts u ON u.id = r.user_id
          WHERE p.traveler_id = :user_id
          ORDER BY p.created_at DESC";

    try {
        $st = $db->prepare($sql);
        $st->execute([':user_id' => $userId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $uid = (int)($row['requester_user_id'] ?? 0);
            $row['requester_avatar_id'] = $uid;

            $ratingData = getUserRating($db, $uid);
            $row['requester_rating'] = $ratingData['rating'];
            $row['requester_total_ratings'] = $ratingData['total_ratings'];

            unset($row['requester_user_id']);
        }

        echo json_encode(['success' => true, 'proposals' => $rows], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => 'SQL getMyProposals: ' . $e->getMessage()]);
    }
}




function getRequestDetail(PDO $db) {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID inválido']);
        return;
    }

    try {
        $sql = "SELECT r.*,
                   COALESCE(u.full_name, 'Usuario') AS requester_name,
                   COALESCE(u.verificado, 0) AS requester_verified,
                   u.email AS requester_email,
                   u.id AS requester_id
            FROM shop_requests r
            LEFT JOIN accounts u ON u.id = r.user_id
            WHERE r.id = :id";

        $st = $db->prepare($sql);
        $st->execute([':id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            echo json_encode(['success' => false, 'error' => 'Solicitud no encontrada']);
            return;
        }

        $row['reference_images'] = !empty($row['reference_images']) ? json_decode($row['reference_images'], true) : [];
        $row['reference_links'] = !empty($row['reference_links']) ? json_decode($row['reference_links'], true) : [];

        $uid = (int)($row['requester_id'] ?? 0);
        $row['requester_avatar_id'] = $uid;

        $ratingData = getUserRating($db, $uid);
        $row['requester_rating'] = $ratingData['rating'];
        $row['requester_total_ratings'] = $ratingData['total_ratings'];

        $sqlP = "SELECT p.*,
                    COALESCE(u.full_name, 'Viajero') AS traveler_name,
                    COALESCE(u.verificado, 0) AS traveler_verified,
                    u.id AS traveler_id
             FROM shop_request_proposals p
             LEFT JOIN accounts u ON u.id = p.traveler_id
             WHERE p.request_id = :rid
             ORDER BY
                CASE p.status
                    WHEN 'accepted' THEN 1
                    WHEN 'pending' THEN 2
                    WHEN 'rejected' THEN 3
                    ELSE 4
                END,
                p.created_at DESC";

        $sp = $db->prepare($sqlP);
        $sp->execute([':rid' => $id]);
        $props = $sp->fetchAll(PDO::FETCH_ASSOC);

        foreach ($props as &$p) {
            $tid = (int)($p['traveler_id'] ?? 0);
            $p['traveler_avatar_id'] = $tid;

            $travelerRating = getUserRating($db, $tid);
            $p['traveler_rating'] = $travelerRating['rating'];
            $p['traveler_total_ratings'] = $travelerRating['total_ratings'];

            $lastOfferStmt = $db->prepare("
                SELECT offered_by as user_type, offered_price as proposed_price, 
                       offered_currency as proposed_currency, offered_delivery as estimated_delivery
                FROM shop_request_proposal_history
                WHERE proposal_id = :pid
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $lastOfferStmt->execute([':pid' => $p['id']]);
            $lastOffer = $lastOfferStmt->fetch(PDO::FETCH_ASSOC);

            $p['last_offer_by'] = $lastOffer ? $lastOffer['user_type'] : 'traveler';
            $p['current_price'] = $lastOffer ? $lastOffer['proposed_price'] : $p['proposed_price'];
            $p['current_currency'] = $lastOffer ? $lastOffer['proposed_currency'] : $p['proposed_currency'];
            $p['current_estimated_delivery'] = $lastOffer ? $lastOffer['estimated_delivery'] : $p['estimated_delivery'];
        }

        $row['proposals'] = $props;
        unset($row['requester_id']);

        echo json_encode(['success' => true, 'request' => $row], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => 'SQL getRequestDetail: ' . $e->getMessage()]);
    }
}

function createRequest(PDO $db) {
    $userId = getCurrentUserId();

    if (!$userId) {
        echo json_encode(['success' => false, 'error' => 'Debes iniciar sesión']);
        return;
    }

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 1);
    $budgetAmount = (float)($_POST['budget_amount'] ?? 0);
    $budgetCurrency = trim($_POST['budget_currency'] ?? 'EUR');
    $originCountry = trim($_POST['origin_country'] ?? '');
    $originFlexible = isset($_POST['origin_flexible']) ? 1 : 0;
    $destinationCity = trim($_POST['destination_city'] ?? '');
    $deadlineDate = !empty($_POST['deadline_date']) ? $_POST['deadline_date'] : null;
    $urgency = trim($_POST['urgency'] ?? 'flexible');
    $paymentMethod = trim($_POST['payment_method'] ?? 'negotiable');
    $includesProductCost = isset($_POST['includes_product_cost']) ? 1 : 0;

    if (empty($title)) {
        echo json_encode(['success' => false, 'error' => 'El título es obligatorio']);
        return;
    }
    if (empty($description)) {
        echo json_encode(['success' => false, 'error' => 'La descripción es obligatoria']);
        return;
    }
    if (empty($category)) {
        echo json_encode(['success' => false, 'error' => 'La categoría es obligatoria']);
        return;
    }
    if (empty($destinationCity)) {
        echo json_encode(['success' => false, 'error' => 'El destino es obligatorio']);
        return;
    }
    if ($budgetAmount <= 0) {
        echo json_encode(['success' => false, 'error' => 'El presupuesto debe ser mayor a 0']);
        return;
    }

    $referenceLinks = '[]';
    if (!empty($_POST['reference_links'])) {
        $links = array_map('trim', explode(',', $_POST['reference_links']));
        $links = array_filter($links);
        $referenceLinks = json_encode(array_values($links));
    }

    $savedImages = [];
    if (!empty($_POST['reference_images'])) {
        $imagesData = json_decode($_POST['reference_images'], true);
        
        if (is_array($imagesData)) {
            foreach ($imagesData as $imageObj) {
                if (isset($imageObj['data']) && isset($imageObj['name'])) {
                    $savedPath = saveBase64Image($imageObj['data'], $imageObj['name']);
                    if ($savedPath) {
                        $savedImages[] = $savedPath;
                    }
                }
            }
        }
    }

    $referenceImages = json_encode($savedImages);

    try {
        $sql = "INSERT INTO shop_requests
            (user_id, title, description, category, quantity, budget_amount, budget_currency,
             origin_country, origin_flexible, destination_city, deadline_date, urgency,
             reference_images, reference_links, payment_method, includes_product_cost, status)
            VALUES (:user_id, :title, :description, :category, :quantity, :budget_amount, :budget_currency,
                    :origin_country, :origin_flexible, :destination_city, :deadline_date, :urgency,
                    :reference_images, :reference_links, :payment_method, :includes_product_cost, 'open')";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':title' => $title,
            ':description' => $description,
            ':category' => $category,
            ':quantity' => $quantity,
            ':budget_amount' => $budgetAmount,
            ':budget_currency' => $budgetCurrency,
            ':origin_country' => $originCountry,
            ':origin_flexible' => $originFlexible,
            ':destination_city' => $destinationCity,
            ':deadline_date' => $deadlineDate,
            ':urgency' => $urgency,
            ':reference_images' => $referenceImages,
            ':reference_links' => $referenceLinks,
            ':payment_method' => $paymentMethod,
            ':includes_product_cost' => $includesProductCost
        ]);

        echo json_encode([
            'success' => true,
            'message' => '¡Solicitud creada exitosamente!',
            'request_id' => $db->lastInsertId()
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => 'Error al crear: ' . $e->getMessage()]);
    }
}

function submitProposal(PDO $db) {
    $travelerId = getCurrentUserId();

    if (!$travelerId) {
        echo json_encode(['success' => false, 'error' => 'Debes iniciar sesión']);
        return;
    }

    $requestId = (int)($_POST['request_id'] ?? 0);
    $proposedPrice = (float)($_POST['proposed_price'] ?? 0);
    $proposedCurrency = trim($_POST['proposed_currency'] ?? 'EUR');
    $estimatedDelivery = !empty($_POST['estimated_delivery']) ? $_POST['estimated_delivery'] : null;
    $message = trim($_POST['message'] ?? '');

    if ($requestId <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID de solicitud inválido']);
        return;
    }
    if ($proposedPrice <= 0) {
        echo json_encode(['success' => false, 'error' => 'El precio debe ser mayor a 0']);
        return;
    }

    try {
        $stmt = $db->prepare("
            SELECT r.*, 
                   COALESCE(u.full_name, u.username) as requester_name
            FROM shop_requests r
            LEFT JOIN accounts u ON r.user_id = u.id
            WHERE r.id = :id
        ");
        $stmt->execute([':id' => $requestId]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            echo json_encode(['success' => false, 'error' => 'Solicitud no encontrada']);
            return;
        }
        if ($request['user_id'] == $travelerId) {
            echo json_encode(['success' => false, 'error' => 'No puedes aplicar a tu propia solicitud']);
            return;
        }
        
        $allowedStatuses = ['open', 'negotiating'];
        if (!in_array($request['status'], $allowedStatuses)) {
            echo json_encode(['success' => false, 'error' => 'Esta solicitud ya no acepta propuestas']);
            return;
        }

        $checkStmt = $db->prepare("SELECT id FROM shop_request_proposals WHERE request_id = :rid AND traveler_id = :tid");
        $checkStmt->execute([':rid' => $requestId, ':tid' => $travelerId]);
        if ($checkStmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Ya enviaste una propuesta para esta solicitud']);
            return;
        }

        $stmt = $db->prepare("SELECT COALESCE(full_name, username) as name FROM accounts WHERE id = :id");
        $stmt->execute([':id' => $travelerId]);
        $traveler = $stmt->fetch(PDO::FETCH_ASSOC);
        $travelerName = $traveler['name'] ?? 'Un viajero';

        $db->beginTransaction();

        $sql = "INSERT INTO shop_request_proposals
            (request_id, traveler_id, proposed_price, proposed_currency, 
             estimated_delivery, message, status, is_counteroffer, counteroffer_count)
            VALUES (:request_id, :traveler_id, :proposed_price, :proposed_currency, 
                    :estimated_delivery, :message, 'pending', 0, 0)";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':request_id' => $requestId,
            ':traveler_id' => $travelerId,
            ':proposed_price' => $proposedPrice,
            ':proposed_currency' => $proposedCurrency,
            ':estimated_delivery' => $estimatedDelivery,
            ':message' => $message
        ]);

        $proposalId = $db->lastInsertId();

        $historyStmt = $db->prepare("
            INSERT INTO shop_request_proposal_history 
            (proposal_id, offered_by, user_id, offered_price, offered_currency, 
             offered_delivery, message)
            VALUES (:proposal_id, 'traveler', :user_id, :price, :currency, :delivery, :message)
        ");
        $historyStmt->execute([
            ':proposal_id' => $proposalId,
            ':user_id' => $travelerId,
            ':price' => $proposedPrice,
            ':currency' => $proposedCurrency,
            ':delivery' => $estimatedDelivery,
            ':message' => $message
        ]);

        $db->commit();

        try {
            if (function_exists('notifyNewProposal')) {
                notifyNewProposal(
                    $request['user_id'],
                    $requestId,
                    $travelerName,
                    $request['title'],
                    $proposedPrice,
                    $proposedCurrency
                );
            }
        } catch (Throwable $e) {
            error_log("Error creando notificación: " . $e->getMessage());
        }

        echo json_encode([
            'success' => true, 
            'message' => '¡Propuesta enviada exitosamente!',
            'proposal_id' => $proposalId
        ], JSON_UNESCAPED_UNICODE);

    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        echo json_encode(['success' => false, 'error' => 'Error al enviar propuesta: ' . $e->getMessage()]);
    }
}

function acceptProposal(PDO $db) {
    $userId = getCurrentUserId();

    if (!$userId) {
        echo json_encode(['success' => false, 'error' => 'Debes iniciar sesión']);
        return;
    }

    $proposalId = (int)($_POST['proposal_id'] ?? 0);

    if ($proposalId <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID de propuesta inválido']);
        return;
    }

    try {
        $stmt = $db->prepare("
            SELECT p.*, r.user_id as request_owner_id, r.title,
                   COALESCE(req.full_name, req.username) as requester_name,
                   COALESCE(trav.full_name, trav.username) as traveler_name
            FROM shop_request_proposals p
            INNER JOIN shop_requests r ON p.request_id = r.id
            LEFT JOIN accounts req ON r.user_id = req.id
            LEFT JOIN accounts trav ON p.traveler_id = trav.id
            WHERE p.id = :id
        ");
        $stmt->execute([':id' => $proposalId]);
        $proposal = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$proposal) {
            echo json_encode(['success' => false, 'error' => 'Propuesta no encontrada']);
            return;
        }

        if ((int)$proposal['request_owner_id'] !== (int)$userId) {
            echo json_encode(['success' => false, 'error' => 'No tienes permiso para aceptar esta propuesta']);
            return;
        }

        if (($proposal['status'] ?? '') !== 'pending') {
            echo json_encode(['success' => false, 'error' => 'Esta propuesta ya fue procesada']);
            return;
        }

        $finalPrice = (float)$proposal['proposed_price'];
        $finalCurrency = $proposal['proposed_currency'];
        $finalEstimatedDelivery = $proposal['estimated_delivery'];
        $finalLastOfferBy = 'traveler';

        $lastOfferStmt = $db->prepare("
            SELECT offered_by as user_type, offered_price as proposed_price, 
                   offered_currency as proposed_currency, offered_delivery as estimated_delivery
            FROM shop_request_proposal_history
            WHERE proposal_id = :pid
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $lastOfferStmt->execute([':pid' => $proposalId]);
        $lastOffer = $lastOfferStmt->fetch(PDO::FETCH_ASSOC);

        if ($lastOffer) {
            $finalPrice = (float)$lastOffer['proposed_price'];
            $finalCurrency = $lastOffer['proposed_currency'];
            $finalEstimatedDelivery = $lastOffer['estimated_delivery'];
            $finalLastOfferBy = $lastOffer['user_type'] ?: $finalLastOfferBy;
        }

        $db->beginTransaction();

        $db->prepare("
            UPDATE shop_request_proposals
            SET
                status = 'accepted',
                accepted_price = :accepted_price,
                accepted_currency = :accepted_currency,
                accepted_delivery = :accepted_delivery,
                accepted_at = NOW(),
                accepted_by_user_id = :accepted_by_user_id,
                current_price = :current_price,
                current_currency = :current_currency,
                current_estimated_delivery = :current_estimated_delivery,
                last_offer_by = :last_offer_by,
                last_offer_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
        ")->execute([
            ':accepted_price' => $finalPrice,
            ':accepted_currency' => $finalCurrency,
            ':accepted_delivery' => $finalEstimatedDelivery,
            ':accepted_by_user_id' => $userId,
            ':current_price' => $finalPrice,
            ':current_currency' => $finalCurrency,
            ':current_estimated_delivery' => $finalEstimatedDelivery,
            ':last_offer_by' => $finalLastOfferBy,
            ':id' => $proposalId
        ]);

        $db->prepare("
            UPDATE shop_request_proposals 
            SET status = 'rejected', updated_at = NOW()
            WHERE request_id = :rid AND id != :id AND status = 'pending'
        ")->execute([
            ':rid' => $proposal['request_id'], 
            ':id' => $proposalId
        ]);

        $db->prepare("
            UPDATE shop_requests 
            SET status = 'accepted', updated_at = NOW() 
            WHERE id = :id
        ")->execute([':id' => $proposal['request_id']]);

        $db->commit();

        try {
            if (function_exists('notifyProposalAccepted')) {
                notifyProposalAccepted(
                    $proposal['traveler_id'],
                    $proposal['request_id'],
                    $proposal['requester_name'] ?? 'El solicitante',
                    $proposal['title']
                );
            }
        } catch (Throwable $e) {
            error_log("Error creando notificación: " . $e->getMessage());
        }

        echo json_encode([
            'success' => true,
            'message' => '¡Propuesta aceptada exitosamente!',
            'agreed_price' => $finalPrice,
            'agreed_currency' => $finalCurrency,
            'agreed_delivery' => $finalEstimatedDelivery
        ], JSON_UNESCAPED_UNICODE);

    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        echo json_encode(['success' => false, 'error' => 'Error al aceptar propuesta: ' . $e->getMessage()]);
    }
}

function rejectProposal(PDO $db) {
    $userId = getCurrentUserId();

    if (!$userId) {
        echo json_encode(['success' => false, 'error' => 'Debes iniciar sesión']);
        return;
    }

    $proposalId = (int)($_POST['proposal_id'] ?? 0);

    if ($proposalId <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID de propuesta inválido']);
        return;
    }

    try {
        $stmt = $db->prepare("
            SELECT p.*, r.user_id as request_owner_id, r.title
            FROM shop_request_proposals p
            INNER JOIN shop_requests r ON p.request_id = r.id
            WHERE p.id = :id
        ");
        $stmt->execute([':id' => $proposalId]);
        $proposal = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$proposal) {
            echo json_encode(['success' => false, 'error' => 'Propuesta no encontrada']);
            return;
        }

        if ($proposal['request_owner_id'] != $userId) {
            echo json_encode(['success' => false, 'error' => 'No tienes permiso para rechazar esta propuesta']);
            return;
        }

        if ($proposal['status'] !== 'pending') {
            echo json_encode(['success' => false, 'error' => 'Esta propuesta ya fue procesada']);
            return;
        }

        $db->prepare("UPDATE shop_request_proposals SET status = 'rejected', updated_at = NOW() WHERE id = :id")
            ->execute([':id' => $proposalId]);

        try {
            if (function_exists('notifyProposalRejected')) {
                notifyProposalRejected(
                    $proposal['traveler_id'],
                    $proposal['request_id'],
                    $proposal['title']
                );
            }
        } catch (Throwable $e) {
            error_log("Error creando notificación: " . $e->getMessage());
        }

        echo json_encode(['success' => true, 'message' => 'Propuesta rechazada'], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => 'Error al rechazar propuesta: ' . $e->getMessage()]);
    }
}

function submitCounteroffer(PDO $db) {
    $userId = getCurrentUserId();

    if (!$userId) {
        echo json_encode(['success' => false, 'error' => 'Debes iniciar sesión']);
        return;
    }

    $proposalId = (int)($_POST['proposal_id'] ?? 0);
    $proposedPrice = (float)($_POST['proposed_price'] ?? 0);
    $proposedCurrency = trim($_POST['proposed_currency'] ?? 'EUR');
    $estimatedDelivery = !empty($_POST['estimated_delivery']) ? $_POST['estimated_delivery'] : null;
    $message = trim($_POST['message'] ?? '');

    if ($proposalId <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID de propuesta inválido']);
        return;
    }

    if ($proposedPrice <= 0) {
        echo json_encode(['success' => false, 'error' => 'El precio debe ser mayor a 0']);
        return;
    }

    try {
        $stmt = $db->prepare("
            SELECT p.*, r.user_id as requester_id 
            FROM shop_request_proposals p
            JOIN shop_requests r ON p.request_id = r.id
            WHERE p.id = :id
        ");
        $stmt->execute([':id' => $proposalId]);
        $proposal = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$proposal) {
            echo json_encode(['success' => false, 'error' => 'Propuesta no encontrada']);
            return;
        }

        $isRequester = ($userId == $proposal['requester_id']);
        $isTraveler = ($userId == $proposal['traveler_id']);

        if (!$isRequester && !$isTraveler) {
            echo json_encode(['success' => false, 'error' => 'No autorizado']);
            return;
        }

        if ($proposal['status'] !== 'pending') {
            echo json_encode(['success' => false, 'error' => 'Esta propuesta ya fue procesada']);
            return;
        }

        $userType = $isRequester ? 'requester' : 'traveler';

        $db->beginTransaction();

        $stmt = $db->prepare("
            INSERT INTO shop_proposal_negotiations 
            (proposal_id, user_id, user_type, proposed_price, proposed_currency, 
             estimated_delivery, message) 
            VALUES (:proposal_id, :user_id, :user_type, :proposed_price, :proposed_currency, 
                    :estimated_delivery, :message)
        ");
        $stmt->execute([
            ':proposal_id' => $proposalId,
            ':user_id' => $userId,
            ':user_type' => $userType,
            ':proposed_price' => $proposedPrice,
            ':proposed_currency' => $proposedCurrency,
            ':estimated_delivery' => $estimatedDelivery,
            ':message' => $message
        ]);

        $historyStmt = $db->prepare("
            INSERT INTO shop_request_proposal_history 
            (proposal_id, offered_by, user_id, offered_price, offered_currency, 
             offered_delivery, message)
            VALUES (:proposal_id, :offered_by, :user_id, :price, :currency, :delivery, :message)
        ");
        $historyStmt->execute([
            ':proposal_id' => $proposalId,
            ':offered_by' => $userType,
            ':user_id' => $userId,
            ':price' => $proposedPrice,
            ':currency' => $proposedCurrency,
            ':delivery' => $estimatedDelivery,
            ':message' => $message
        ]);

        $stmt = $db->prepare("
            UPDATE shop_request_proposals
            SET counteroffer_count = counteroffer_count + 1,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([':id' => $proposalId]);

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Contraoferta enviada exitosamente'
        ], JSON_UNESCAPED_UNICODE);

    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        echo json_encode(['success' => false, 'error' => 'Error al enviar contraoferta: ' . $e->getMessage()]);
    }
}

function getNegotiationHistory(PDO $db) {
    $proposalId = (int)($_GET['proposal_id'] ?? 0);

    if ($proposalId <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID de propuesta inválido']);
        return;
    }

    try {
        $stmt = $db->prepare("
            SELECT 
                h.id,
                h.proposal_id,
                h.user_id,
                h.offered_by as user_type,
                h.offered_price as proposed_price,
                h.offered_currency as proposed_currency,
                h.offered_delivery as estimated_delivery,
                h.message,
                h.created_at,
                COALESCE(a.full_name, a.username, 'Usuario') as nombre_usuario,
                a.id as avatar_id,
                CASE
                    WHEN h.offered_by = 'requester' THEN 'Solicitante'
                    ELSE 'Viajero'
                END as role_name
            FROM shop_request_proposal_history h
            JOIN accounts a ON h.user_id = a.id
            WHERE h.proposal_id = :proposal_id
            ORDER BY h.created_at ASC
        ");
        $stmt->execute([':proposal_id' => $proposalId]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'history' => $history,
            'total' => count($history)
        ], JSON_UNESCAPED_UNICODE);

    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => 'Error al obtener historial: ' . $e->getMessage()]);
    }
}

function getRequestQR($conexion) {
    try {
        $requestId = $_GET['request_id'] ?? null;

        if (!$requestId) {
            echo json_encode(['success' => false, 'error' => 'ID de solicitud requerido']);
            return;
        }

        $stmt = $conexion->prepare("
            SELECT
                sd.id,
                sd.qr_code_unique_id,
                sd.qr_code_path,
                sd.qr_data_json,
                sd.delivery_state,
                sd.created_at,
                sr.title as request_title
            FROM shop_deliveries sd
            JOIN shop_request_proposals srp ON sd.proposal_id = srp.id
            JOIN shop_requests sr ON srp.request_id = sr.id
            WHERE srp.request_id = :request_id
            ORDER BY sd.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([':request_id' => $requestId]);
        $delivery = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$delivery) {
            echo json_encode([
                'success' => false,
                'error' => 'No se encontró código QR para esta solicitud'
            ]);
            return;
        }

        echo json_encode([
            'success' => true,
            'qr_unique_id' => $delivery['qr_code_unique_id'],
            'qr_path' => $delivery['qr_code_path'],
            'delivery_state' => $delivery['delivery_state'],
            'request_title' => $delivery['request_title'],
            'created_at' => $delivery['created_at']
        ], JSON_UNESCAPED_UNICODE);

    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => 'Error al obtener QR: ' . $e->getMessage()]);
    }
}

function addFavorite(PDO $db) {
    $userId = getCurrentUserId();

    if (!$userId) {
        echo json_encode(['success' => false, 'error' => 'Usuario no autenticado']);
        return;
    }

    $requestId = (int)($_POST['request_id'] ?? 0);

    if ($requestId <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID de solicitud inválido']);
        return;
    }

    try {
        $stmt = $db->prepare("SELECT id FROM shop_requests WHERE id = :id");
        $stmt->execute([':id' => $requestId]);
        
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Solicitud no encontrada']);
            return;
        }

        $stmt = $db->prepare("
            INSERT INTO request_favorites (user_id, request_id) 
            VALUES (:user_id, :request_id)
            ON DUPLICATE KEY UPDATE created_at = created_at
        ");
        $stmt->execute([
            ':user_id' => $userId,
            ':request_id' => $requestId
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Agregado a favoritos'
        ], JSON_UNESCAPED_UNICODE);

    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => 'Error al guardar favorito: ' . $e->getMessage()]);
    }
}

function removeFavorite(PDO $db) {
    $userId = getCurrentUserId();

    if (!$userId) {
        echo json_encode(['success' => false, 'error' => 'Usuario no autenticado']);
        return;
    }

    $requestId = (int)($_POST['request_id'] ?? 0);

    if ($requestId <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID de solicitud inválido']);
        return;
    }

    try {
        $stmt = $db->prepare("
            DELETE FROM request_favorites 
            WHERE user_id = :user_id AND request_id = :request_id
        ");
        $stmt->execute([
            ':user_id' => $userId,
            ':request_id' => $requestId
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Eliminado de favoritos'
        ], JSON_UNESCAPED_UNICODE);

    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => 'Error al eliminar favorito: ' . $e->getMessage()]);
    }
}

function getDeliveryByProposal(PDO $db) {
    $proposalId = (int)($_GET['proposal_id'] ?? 0);

    if ($proposalId <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID de propuesta inválido']);
        return;
    }

    try {
        $stmt = $db->prepare("
            SELECT 
                id,
                delivery_state,
                payment_released,
                payment_released_at,
                qr_code_unique_id,
                created_at,
                updated_at
            FROM shop_deliveries 
            WHERE proposal_id = :proposal_id 
            LIMIT 1
        ");
        $stmt->execute([':proposal_id' => $proposalId]);
        $delivery = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$delivery) {
            echo json_encode([
                'success' => false,
                'error' => 'No se encontró delivery para esta propuesta'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode([
            'success' => true,
            'delivery' => $delivery
        ], JSON_UNESCAPED_UNICODE);

    } catch (Throwable $e) {
        echo json_encode([
            'success' => false, 
            'error' => 'Error al obtener delivery: ' . $e->getMessage()
        ]);
    }
}







function getMyFavorites(PDO $db) {
    $userId = getCurrentUserId();

    if (!$userId) {
        echo json_encode(['success' => false, 'error' => 'Usuario no autenticado']);
        return;
    }

    try {
        $sql = "SELECT r.*, 
                    u.username as requester_username,
                    u.id as requester_avatar_id,
                    COALESCE(u.verificado, 0) as requester_verified,
                    COALESCE((SELECT AVG(c.valoracion) 
                              FROM comentarios c 
                              WHERE c.usuario_id = r.user_id 
                              AND c.bloqueado = 0), 0) as requester_rating,
                    (SELECT COUNT(*) 
                     FROM shop_request_proposals 
                     WHERE request_id = r.id) as proposal_count,
                    rf.created_at as favorited_at
                FROM request_favorites rf
                INNER JOIN shop_requests r ON rf.request_id = r.id
                LEFT JOIN accounts u ON r.user_id = u.id
                WHERE rf.user_id = :user_id
                ORDER BY rf.created_at DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($favorites as &$fav) {
            $fav['reference_images'] = !empty($fav['reference_images']) 
                ? json_decode($fav['reference_images'], true) 
                : [];
            $fav['reference_links'] = !empty($fav['reference_links']) 
                ? json_decode($fav['reference_links'], true) 
                : [];
            $fav['requester_rating'] = round((float)$fav['requester_rating'], 1);
        }

        echo json_encode([
            'success' => true,
            'favorites' => $favorites,
            'total' => count($favorites)
        ], JSON_UNESCAPED_UNICODE);

    } catch (Throwable $e) {
        echo json_encode([
            'success' => false, 
            'error' => 'Error al obtener favoritos: ' . $e->getMessage()
        ]);
    }
}







function updateRequest(PDO $db) {
    $userId = getCurrentUserId();

    if (!$userId) {
        echo json_encode(['success' => false, 'error' => 'No autorizado']);
        return;
    }

    $requestId = (int)($_POST['request_id'] ?? 0);

    if ($requestId <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID de solicitud inválido']);
        return;
    }

    try {
        // Verificar que la solicitud pertenece al usuario y es editable
        $stmt = $db->prepare("
            SELECT * FROM shop_requests 
            WHERE id = :id AND user_id = :user_id
        ");
        $stmt->execute([':id' => $requestId, ':user_id' => $userId]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            echo json_encode(['success' => false, 'error' => 'Solicitud no encontrada']);
            return;
        }

        $editableStatuses = ['open', 'negotiating'];
        if (!in_array($request['status'], $editableStatuses)) {
            echo json_encode(['success' => false, 'error' => 'Esta solicitud no puede ser editada']);
            return;
        }

        // Obtener datos del formulario
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $quantity = (int)($_POST['quantity'] ?? 1);
        $budgetAmount = (float)($_POST['budget_amount'] ?? 0);
        $budgetCurrency = trim($_POST['budget_currency'] ?? 'EUR');
        $originCountry = trim($_POST['origin_country'] ?? '');
        $originFlexible = ($_POST['origin_flexible'] ?? '0') == '1' ? 1 : 0;
        $destinationCity = trim($_POST['destination_city'] ?? '');
        $deadlineDate = !empty($_POST['deadline_date']) ? $_POST['deadline_date'] : null;
        $urgency = trim($_POST['urgency'] ?? 'flexible');
        $includesProductCost = ($_POST['includes_product_cost'] ?? '0') == '1' ? 1 : 0;

        // Validaciones
        if (empty($title) || empty($description) || empty($category) || empty($destinationCity)) {
            echo json_encode(['success' => false, 'error' => 'Faltan campos obligatorios']);
            return;
        }

        if ($budgetAmount <= 0) {
            echo json_encode(['success' => false, 'error' => 'El presupuesto debe ser mayor a 0']);
            return;
        }

        // Procesar imágenes
        $currentImages = json_decode($request['reference_images'], true) ?: [];
        $removedImages = json_decode($_POST['removed_images'] ?? '[]', true) ?: [];
        $newImagesData = json_decode($_POST['new_images'] ?? '[]', true) ?: [];

        // Remover imágenes eliminadas
        foreach ($removedImages as $index) {
            if (isset($currentImages[$index])) {
                unset($currentImages[$index]);
            }
        }
        $currentImages = array_values($currentImages);

        // Guardar nuevas imágenes
        foreach ($newImagesData as $imageObj) {
            if (isset($imageObj['data']) && isset($imageObj['name'])) {
                $savedPath = saveBase64Image($imageObj['data'], $imageObj['name']);
                if ($savedPath) {
                    $currentImages[] = $savedPath;
                }
            }
        }

        // Procesar enlaces
        $currentLinks = json_decode($request['reference_links'], true) ?: [];
        $removedLinks = json_decode($_POST['removed_links'] ?? '[]', true) ?: [];
        $newLinks = json_decode($_POST['new_links'] ?? '[]', true) ?: [];

        // Remover enlaces eliminados
        foreach ($removedLinks as $index) {
            if (isset($currentLinks[$index])) {
                unset($currentLinks[$index]);
            }
        }
        $currentLinks = array_values($currentLinks);

        // Agregar nuevos enlaces
        $currentLinks = array_merge($currentLinks, $newLinks);

        // Actualizar en base de datos
        $stmt = $db->prepare("
            UPDATE shop_requests SET
                title = :title,
                description = :description,
                category = :category,
                quantity = :quantity,
                budget_amount = :budget_amount,
                budget_currency = :budget_currency,
                origin_country = :origin_country,
                origin_flexible = :origin_flexible,
                destination_city = :destination_city,
                deadline_date = :deadline_date,
                urgency = :urgency,
                includes_product_cost = :includes_product_cost,
                reference_images = :reference_images,
                reference_links = :reference_links,
                updated_at = NOW()
            WHERE id = :id AND user_id = :user_id
        ");

        $result = $stmt->execute([
            ':title' => $title,
            ':description' => $description,
            ':category' => $category,
            ':quantity' => $quantity,
            ':budget_amount' => $budgetAmount,
            ':budget_currency' => $budgetCurrency,
            ':origin_country' => $originCountry,
            ':origin_flexible' => $originFlexible,
            ':destination_city' => $destinationCity,
            ':deadline_date' => $deadlineDate,
            ':urgency' => $urgency,
            ':includes_product_cost' => $includesProductCost,
            ':reference_images' => json_encode($currentImages),
            ':reference_links' => json_encode($currentLinks),
            ':id' => $requestId,
            ':user_id' => $userId
        ]);

        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Solicitud actualizada exitosamente'
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'No se pudo actualizar la solicitud'
            ]);
        }

    } catch (Throwable $e) {
        error_log("Error en updateRequest: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => 'Error al actualizar: ' . $e->getMessage()
        ]);
    }
}

function deleteRequest(PDO $db) {
    $userId = getCurrentUserId();

    if (!$userId) {
        echo json_encode(['success' => false, 'error' => 'No autorizado']);
        return;
    }

    $requestId = (int)($_POST['request_id'] ?? 0);

    if ($requestId <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID de solicitud inválido']);
        return;
    }

    try {
        // Verificar que la solicitud pertenece al usuario
        $stmt = $db->prepare("
            SELECT * FROM shop_requests 
            WHERE id = :id AND user_id = :user_id
        ");
        $stmt->execute([':id' => $requestId, ':user_id' => $userId]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            echo json_encode(['success' => false, 'error' => 'Solicitud no encontrada']);
            return;
        }

        // Verificar que sea eliminable (solo open o negotiating)
        $deletableStatuses = ['open', 'negotiating'];
        if (!in_array($request['status'], $deletableStatuses)) {
            echo json_encode(['success' => false, 'error' => 'Esta solicitud no puede ser eliminada']);
            return;
        }

        // Verificar que no tenga propuestas aceptadas
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM shop_request_proposals 
            WHERE request_id = :id AND status = 'accepted'
        ");
        $stmt->execute([':id' => $requestId]);
        $acceptedCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        if ($acceptedCount > 0) {
            echo json_encode(['success' => false, 'error' => 'No se puede eliminar una solicitud con propuestas aceptadas']);
            return;
        }

        $db->beginTransaction();

        // Eliminar historial de negociaciones
        $db->prepare("
            DELETE FROM shop_request_proposal_history 
            WHERE proposal_id IN (
                SELECT id FROM shop_request_proposals WHERE request_id = :id
            )
        ")->execute([':id' => $requestId]);

        // Eliminar negociaciones
        $db->prepare("
            DELETE FROM shop_proposal_negotiations 
            WHERE proposal_id IN (
                SELECT id FROM shop_request_proposals WHERE request_id = :id
            )
        ")->execute([':id' => $requestId]);

        // Eliminar propuestas
        $db->prepare("DELETE FROM shop_request_proposals WHERE request_id = :id")
            ->execute([':id' => $requestId]);

        // Eliminar favoritos
        $db->prepare("DELETE FROM request_favorites WHERE request_id = :id")
            ->execute([':id' => $requestId]);

        // Eliminar la solicitud
        $db->prepare("DELETE FROM shop_requests WHERE id = :id AND user_id = :user_id")
            ->execute([':id' => $requestId, ':user_id' => $userId]);

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Solicitud eliminada exitosamente'
        ], JSON_UNESCAPED_UNICODE);

    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Error en deleteRequest: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => 'Error al eliminar: ' . $e->getMessage()
        ]);
    }
}