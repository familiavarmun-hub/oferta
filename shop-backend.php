<?php
/**
 * shop-negotiations-backend.php
 * Backend para sistema de negociación de precios (requests y products)
 * Fecha: 2025-11-19
 */

session_start();
require_once '../config.php';

header('Content-Type: application/json');

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$user_id = $_SESSION['usuario_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {

        // ================================================================
        // REQUESTS (DEMANDAS) - NEGOCIACIÓN
        // ================================================================

        case 'submit_counteroffer':
            $proposal_id = (int)($_POST['proposal_id'] ?? 0);
            $proposed_price = (float)($_POST['proposed_price'] ?? 0);
            $proposed_currency = $_POST['proposed_currency'] ?? 'EUR';
            $estimated_delivery = $_POST['estimated_delivery'] ?? null;
            $message = trim($_POST['message'] ?? '');

            if ($proposal_id <= 0 || $proposed_price <= 0) {
                throw new Exception('Datos inválidos');
            }

            // Obtener información de la propuesta
            $sql = "SELECT p.*, r.user_id as requester_id
                    FROM shop_request_proposals p
                    JOIN shop_requests r ON r.id = p.request_id
                    WHERE p.id = :id AND p.status = 'pending'";
            $stmt = $conexion->prepare($sql);
            $stmt->execute([':id' => $proposal_id]);
            $proposal = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$proposal) {
                throw new Exception('Propuesta no encontrada o ya procesada');
            }

            // Determinar quién hace la contraoferta
            $is_requester = ($user_id == $proposal['requester_id']);
            $is_traveler = ($user_id == $proposal['traveler_id']);

            if (!$is_requester && !$is_traveler) {
                throw new Exception('No tienes permiso para hacer contraofertas en esta propuesta');
            }

            $user_type = $is_requester ? 'requester' : 'traveler';

            $conexion->beginTransaction();

            // Insertar contraoferta en historial
            $sql_neg = "INSERT INTO shop_request_price_negotiation
                        (proposal_id, request_id, user_id, user_type, offer_type,
                         proposed_price, proposed_currency, estimated_delivery, message)
                        VALUES (:proposal_id, :request_id, :user_id, :user_type, 'counteroffer',
                                :price, :currency, :delivery, :message)";

            $stmt_neg = $conexion->prepare($sql_neg);
            $stmt_neg->execute([
                ':proposal_id' => $proposal_id,
                ':request_id' => $proposal['request_id'],
                ':user_id' => $user_id,
                ':user_type' => $user_type,
                ':price' => $proposed_price,
                ':currency' => $proposed_currency,
                ':delivery' => $estimated_delivery,
                ':message' => $message
            ]);

            // Actualizar la propuesta con los nuevos valores
            $sql_update = "UPDATE shop_request_proposals
                           SET proposed_price = :price,
                               proposed_currency = :currency,
                               estimated_delivery = :delivery,
                               updated_at = NOW()
                           WHERE id = :id";

            $stmt_update = $conexion->prepare($sql_update);
            $stmt_update->execute([
                ':price' => $proposed_price,
                ':currency' => $proposed_currency,
                ':delivery' => $estimated_delivery,
                ':id' => $proposal_id
            ]);

            $conexion->commit();

            // TODO: Enviar notificación por email a la otra parte

            echo json_encode([
                'success' => true,
                'message' => 'Contraoferta enviada exitosamente',
                'negotiation_id' => $conexion->lastInsertId()
            ]);
            break;

        case 'get_negotiation_history':
            $proposal_id = (int)($_GET['proposal_id'] ?? 0);

            if ($proposal_id <= 0) {
                throw new Exception('ID inválido');
            }

            // Obtener historial completo de negociación
            $sql = "SELECT * FROM v_shop_request_negotiations
                    WHERE proposal_id = :id
                    ORDER BY created_at ASC";

            $stmt = $conexion->prepare($sql);
            $stmt->execute([':id' => $proposal_id]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Procesar avatares
            foreach ($history as &$item) {
                // Intentar obtener avatar LONGBLOB
                $sql_avatar = "SELECT profile_picture FROM accounts WHERE id = :id";
                $stmt_avatar = $conexion->prepare($sql_avatar);
                $stmt_avatar->execute([':id' => $item['user_id']]);
                $avatar_data = $stmt_avatar->fetchColumn();

                if ($avatar_data) {
                    $item['avatar'] = 'data:image/jpeg;base64,' . base64_encode($avatar_data);
                } else {
                    $item['avatar'] = null;
                }
            }

            echo json_encode([
                'success' => true,
                'history' => $history
            ]);
            break;

        // ================================================================
        // PRODUCTS (OFERTAS) - NEGOCIACIÓN
        // ================================================================

        case 'submit_product_offer':
            $product_id = (int)($_POST['product_id'] ?? 0);
            $offered_price = (float)($_POST['offered_price'] ?? 0);
            $offered_currency = $_POST['offered_currency'] ?? 'EUR';
            $quantity = (int)($_POST['quantity'] ?? 1);
            $message = trim($_POST['message'] ?? '');
            $delivery_preference = trim($_POST['delivery_preference'] ?? '');

            if ($product_id <= 0 || $offered_price <= 0 || $quantity <= 0) {
                throw new Exception('Datos inválidos');
            }

            // Obtener información del producto
            $sql = "SELECT * FROM shop_products WHERE id = :id AND active = 1";
            $stmt = $conexion->prepare($sql);
            $stmt->execute([':id' => $product_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                throw new Exception('Producto no encontrado');
            }

            if ($user_id == $product['seller_id']) {
                throw new Exception('No puedes hacer ofertas en tus propios productos');
            }

            // Verificar stock
            if ($quantity > $product['stock']) {
                throw new Exception('Cantidad solicitada no disponible');
            }

            $conexion->beginTransaction();

            // Crear oferta
            $expires_at = date('Y-m-d H:i:s', strtotime('+7 days'));

            $sql_offer = "INSERT INTO shop_product_offers
                          (product_id, buyer_id, seller_id, offered_price, offered_currency,
                           quantity, message, delivery_preference, expires_at)
                          VALUES (:product_id, :buyer_id, :seller_id, :price, :currency,
                                  :quantity, :message, :delivery, :expires)";

            $stmt_offer = $conexion->prepare($sql_offer);
            $stmt_offer->execute([
                ':product_id' => $product_id,
                ':buyer_id' => $user_id,
                ':seller_id' => $product['seller_id'],
                ':price' => $offered_price,
                ':currency' => $offered_currency,
                ':quantity' => $quantity,
                ':message' => $message,
                ':delivery' => $delivery_preference,
                ':expires' => $expires_at
            ]);

            $offer_id = $conexion->lastInsertId();

            // Registrar en historial de negociación como oferta inicial
            $sql_neg = "INSERT INTO shop_product_price_negotiation
                        (offer_id, product_id, user_id, user_type, offer_type,
                         offered_price, offered_currency, quantity, message)
                        VALUES (:offer_id, :product_id, :user_id, 'buyer', 'initial',
                                :price, :currency, :quantity, :message)";

            $stmt_neg = $conexion->prepare($sql_neg);
            $stmt_neg->execute([
                ':offer_id' => $offer_id,
                ':product_id' => $product_id,
                ':user_id' => $user_id,
                ':price' => $offered_price,
                ':currency' => $offered_currency,
                ':quantity' => $quantity,
                ':message' => $message
            ]);

            $conexion->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Oferta enviada exitosamente',
                'offer_id' => $offer_id
            ]);
            break;

        case 'submit_product_counteroffer':
            $offer_id = (int)($_POST['offer_id'] ?? 0);
            $offered_price = (float)($_POST['offered_price'] ?? 0);
            $offered_currency = $_POST['offered_currency'] ?? 'EUR';
            $quantity = (int)($_POST['quantity'] ?? 1);
            $message = trim($_POST['message'] ?? '');

            if ($offer_id <= 0 || $offered_price <= 0) {
                throw new Exception('Datos inválidos');
            }

            // Obtener información de la oferta
            $sql = "SELECT * FROM shop_product_offers WHERE id = :id AND status = 'pending'";
            $stmt = $conexion->prepare($sql);
            $stmt->execute([':id' => $offer_id]);
            $offer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$offer) {
                throw new Exception('Oferta no encontrada o ya procesada');
            }

            // Determinar quién hace la contraoferta
            $is_buyer = ($user_id == $offer['buyer_id']);
            $is_seller = ($user_id == $offer['seller_id']);

            if (!$is_buyer && !$is_seller) {
                throw new Exception('No tienes permiso para hacer contraofertas');
            }

            $user_type = $is_buyer ? 'buyer' : 'seller';

            $conexion->beginTransaction();

            // Insertar contraoferta en historial
            $sql_neg = "INSERT INTO shop_product_price_negotiation
                        (offer_id, product_id, user_id, user_type, offer_type,
                         offered_price, offered_currency, quantity, message)
                        VALUES (:offer_id, :product_id, :user_id, :user_type, 'counteroffer',
                                :price, :currency, :quantity, :message)";

            $stmt_neg = $conexion->prepare($sql_neg);
            $stmt_neg->execute([
                ':offer_id' => $offer_id,
                ':product_id' => $offer['product_id'],
                ':user_id' => $user_id,
                ':user_type' => $user_type,
                ':price' => $offered_price,
                ':currency' => $offered_currency,
                ':quantity' => $quantity,
                ':message' => $message
            ]);

            // Actualizar la oferta con los nuevos valores
            $sql_update = "UPDATE shop_product_offers
                           SET offered_price = :price,
                               offered_currency = :currency,
                               quantity = :quantity,
                               updated_at = NOW()
                           WHERE id = :id";

            $stmt_update = $conexion->prepare($sql_update);
            $stmt_update->execute([
                ':price' => $offered_price,
                ':currency' => $offered_currency,
                ':quantity' => $quantity,
                ':id' => $offer_id
            ]);

            $conexion->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Contraoferta enviada exitosamente',
                'negotiation_id' => $conexion->lastInsertId()
            ]);
            break;

        case 'get_product_negotiation_history':
            $offer_id = (int)($_GET['offer_id'] ?? 0);

            if ($offer_id <= 0) {
                throw new Exception('ID inválido');
            }

            // Obtener historial completo
            $sql = "SELECT * FROM v_shop_product_negotiations
                    WHERE offer_id = :id
                    ORDER BY created_at ASC";

            $stmt = $conexion->prepare($sql);
            $stmt->execute([':id' => $offer_id]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Procesar avatares
            foreach ($history as &$item) {
                $sql_avatar = "SELECT profile_picture FROM accounts WHERE id = :id";
                $stmt_avatar = $conexion->prepare($sql_avatar);
                $stmt_avatar->execute([':id' => $item['user_id']]);
                $avatar_data = $stmt_avatar->fetchColumn();

                if ($avatar_data) {
                    $item['avatar'] = 'data:image/jpeg;base64,' . base64_encode($avatar_data);
                } else {
                    $item['avatar'] = null;
                }
            }

            echo json_encode([
                'success' => true,
                'history' => $history
            ]);
            break;

        case 'accept_product_offer':
            $offer_id = (int)($_POST['offer_id'] ?? 0);

            if ($offer_id <= 0) {
                throw new Exception('ID inválido');
            }

            // Obtener oferta
            $sql = "SELECT o.*, p.seller_id, p.stock
                    FROM shop_product_offers o
                    JOIN shop_products p ON o.product_id = p.id
                    WHERE o.id = :id AND o.status = 'pending'";

            $stmt = $conexion->prepare($sql);
            $stmt->execute([':id' => $offer_id]);
            $offer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$offer) {
                throw new Exception('Oferta no encontrada');
            }

            // Verificar que el usuario es el vendedor
            if ($user_id != $offer['seller_id']) {
                throw new Exception('Solo el vendedor puede aceptar ofertas');
            }

            // Verificar stock
            if ($offer['quantity'] > $offer['stock']) {
                throw new Exception('Stock insuficiente');
            }

            $conexion->beginTransaction();

            // Actualizar oferta
            $sql_accept = "UPDATE shop_product_offers
                           SET status = 'accepted', updated_at = NOW()
                           WHERE id = :id";
            $stmt_accept = $conexion->prepare($sql_accept);
            $stmt_accept->execute([':id' => $offer_id]);

            $conexion->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Oferta aceptada. Redirige al comprador al pago.',
                'offer_id' => $offer_id
            ]);
            break;

        case 'reject_product_offer':
            $offer_id = (int)($_POST['offer_id'] ?? 0);

            if ($offer_id <= 0) {
                throw new Exception('ID inválido');
            }

            // Obtener oferta
            $sql = "SELECT o.*, p.seller_id
                    FROM shop_product_offers o
                    JOIN shop_products p ON o.product_id = p.id
                    WHERE o.id = :id AND o.status = 'pending'";

            $stmt = $conexion->prepare($sql);
            $stmt->execute([':id' => $offer_id]);
            $offer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$offer) {
                throw new Exception('Oferta no encontrada');
            }

            // Verificar que el usuario es el vendedor
            if ($user_id != $offer['seller_id']) {
                throw new Exception('Solo el vendedor puede rechazar ofertas');
            }

            // Actualizar oferta
            $sql_reject = "UPDATE shop_product_offers
                           SET status = 'rejected', updated_at = NOW()
                           WHERE id = :id";
            $stmt_reject = $conexion->prepare($sql_reject);
            $stmt_reject->execute([':id' => $offer_id]);

            echo json_encode([
                'success' => true,
                'message' => 'Oferta rechazada'
            ]);
            break;

        case 'get_my_product_offers':
            // Obtener todas las ofertas del usuario como comprador
            $sql = "SELECT o.*,
                           p.name as product_name,
                           p.description as product_description,
                           p.seller_id,
                           COALESCE(seller.full_name, seller.username, 'Vendedor') as seller_name,
                           (SELECT image_path FROM shop_product_images
                            WHERE product_id = p.id AND is_primary = 1
                            LIMIT 1) as product_image,
                           (SELECT COUNT(*) FROM shop_product_price_negotiation
                            WHERE offer_id = o.id) as negotiation_count
                    FROM shop_product_offers o
                    JOIN shop_products p ON o.product_id = p.id
                    LEFT JOIN accounts seller ON p.seller_id = seller.id
                    WHERE o.buyer_id = :user_id
                    ORDER BY o.created_at DESC";

            $stmt = $conexion->prepare($sql);
            $stmt->execute([':user_id' => $user_id]);
            $offers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Procesar avatares de vendedores
            foreach ($offers as &$offer) {
                $sql_avatar = "SELECT profile_picture FROM accounts WHERE id = :id";
                $stmt_avatar = $conexion->prepare($sql_avatar);
                $stmt_avatar->execute([':id' => $offer['seller_id']]);
                $avatar_data = $stmt_avatar->fetchColumn();

                if ($avatar_data) {
                    $offer['seller_avatar'] = 'data:image/jpeg;base64,' . base64_encode($avatar_data);
                } else {
                    $offer['seller_avatar'] = "https://ui-avatars.com/api/?name=" . urlencode($offer['seller_name']) . "&background=42ba25&color=fff&size=100";
                }
            }

            echo json_encode([
                'success' => true,
                'offers' => $offers
            ]);
            break;

        case 'cancel_product_offer':
            $offer_id = (int)($_POST['offer_id'] ?? 0);

            if ($offer_id <= 0) {
                throw new Exception('ID inválido');
            }

            // Obtener oferta y verificar que pertenece al usuario
            $sql = "SELECT * FROM shop_product_offers WHERE id = :id AND buyer_id = :user_id";
            $stmt = $conexion->prepare($sql);
            $stmt->execute([':id' => $offer_id, ':user_id' => $user_id]);
            $offer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$offer) {
                throw new Exception('Oferta no encontrada o no tienes permiso');
            }

            if ($offer['status'] !== 'pending') {
                throw new Exception('Solo puedes cancelar ofertas pendientes');
            }

            // Actualizar oferta a rejected
            $sql_update = "UPDATE shop_product_offers
                           SET status = 'rejected', updated_at = NOW()
                           WHERE id = :id";
            $stmt_update = $conexion->prepare($sql_update);
            $stmt_update->execute([':id' => $offer_id]);

            echo json_encode([
                'success' => true,
                'message' => 'Oferta cancelada'
            ]);
            break;

        case 'get_seller_product_offers':
            // Obtener todas las ofertas recibidas en productos del vendedor
            $sql = "SELECT o.*,
                           p.name as product_name,
                           p.price as product_price,
                           p.currency as product_currency,
                           p.description as product_description,
                           COALESCE(buyer.full_name, buyer.username, 'Comprador') as buyer_name,
                           (SELECT image_path FROM shop_product_images
                            WHERE product_id = p.id AND is_primary = 1
                            LIMIT 1) as product_image,
                           (SELECT COUNT(*) FROM shop_product_price_negotiation
                            WHERE offer_id = o.id) as negotiation_count
                    FROM shop_product_offers o
                    JOIN shop_products p ON o.product_id = p.id
                    LEFT JOIN accounts buyer ON o.buyer_id = buyer.id
                    WHERE p.seller_id = :user_id
                    ORDER BY o.created_at DESC";

            $stmt = $conexion->prepare($sql);
            $stmt->execute([':user_id' => $user_id]);
            $offers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Procesar avatares de compradores
            foreach ($offers as &$offer) {
                $sql_avatar = "SELECT profile_picture FROM accounts WHERE id = :id";
                $stmt_avatar = $conexion->prepare($sql_avatar);
                $stmt_avatar->execute([':id' => $offer['buyer_id']]);
                $avatar_data = $stmt_avatar->fetchColumn();

                if ($avatar_data) {
                    $offer['buyer_avatar'] = 'data:image/jpeg;base64,' . base64_encode($avatar_data);
                } else {
                    $offer['buyer_avatar'] = "https://ui-avatars.com/api/?name=" . urlencode($offer['buyer_name']) . "&background=42ba25&color=fff&size=100";
                }
            }

            echo json_encode([
                'success' => true,
                'offers' => $offers
            ]);
            break;

        case 'accept_product_offer':
            $offer_id = (int)($_POST['offer_id'] ?? 0);

            if ($offer_id <= 0) {
                throw new Exception('ID inválido');
            }

            // Obtener oferta y verificar que el usuario es el vendedor
            $sql = "SELECT o.*, p.seller_id
                    FROM shop_product_offers o
                    JOIN shop_products p ON o.product_id = p.id
                    WHERE o.id = :id AND o.status = 'pending'";

            $stmt = $conexion->prepare($sql);
            $stmt->execute([':id' => $offer_id]);
            $offer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$offer) {
                throw new Exception('Oferta no encontrada');
            }

            // Verificar que el usuario es el vendedor
            if ($user_id != $offer['seller_id']) {
                throw new Exception('Solo el vendedor puede aceptar ofertas');
            }

            // Actualizar oferta
            $sql_accept = "UPDATE shop_product_offers
                           SET status = 'accepted', updated_at = NOW()
                           WHERE id = :id";
            $stmt_accept = $conexion->prepare($sql_accept);
            $stmt_accept->execute([':id' => $offer_id]);

            echo json_encode([
                'success' => true,
                'message' => 'Oferta aceptada. El comprador será notificado.'
            ]);
            break;

        case 'product_counteroffer_seller':
            $offer_id = (int)($_POST['offer_id'] ?? 0);
            $proposed_price = (float)($_POST['proposed_price'] ?? 0);
            $message = trim($_POST['message'] ?? '');

            if ($offer_id <= 0 || $proposed_price <= 0) {
                throw new Exception('Datos inválidos');
            }

            // Obtener oferta y verificar permisos
            $sql = "SELECT o.*, p.seller_id, p.currency as product_currency
                    FROM shop_product_offers o
                    JOIN shop_products p ON o.product_id = p.id
                    WHERE o.id = :id";

            $stmt = $conexion->prepare($sql);
            $stmt->execute([':id' => $offer_id]);
            $offer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$offer) {
                throw new Exception('Oferta no encontrada');
            }

            if ($user_id != $offer['seller_id']) {
                throw new Exception('Solo el vendedor puede contraofertear');
            }

            $conexion->beginTransaction();

            // Insertar contraoferta en historial
            $sql_nego = "INSERT INTO shop_product_price_negotiation
                         (offer_id, product_id, user_id, user_type, offer_type,
                          proposed_price, proposed_currency, message, created_at)
                         VALUES
                         (:offer_id, :product_id, :user_id, 'seller', 'counteroffer',
                          :price, :currency, :message, NOW())";

            $stmt_nego = $conexion->prepare($sql_nego);
            $stmt_nego->execute([
                ':offer_id' => $offer_id,
                ':product_id' => $offer['product_id'],
                ':user_id' => $user_id,
                ':price' => $proposed_price,
                ':currency' => $offer['product_currency'],
                ':message' => $message
            ]);

            // Actualizar contador de negociaciones
            $sql_update = "UPDATE shop_product_offers
                           SET negotiation_count = negotiation_count + 1,
                               updated_at = NOW()
                           WHERE id = :id";
            $stmt_update = $conexion->prepare($sql_update);
            $stmt_update->execute([':id' => $offer_id]);

            $conexion->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Contraoferta enviada'
            ]);
            break;

        case 'get_product_negotiation_history':
            $offer_id = (int)($_GET['offer_id'] ?? 0);

            if ($offer_id <= 0) {
                throw new Exception('ID inválido');
            }

            // Obtener historial completo de negociación
            $sql = "SELECT n.*,
                           COALESCE(u.full_name, u.username, 'Usuario') as user_name,
                           u.profile_picture
                    FROM shop_product_price_negotiation n
                    LEFT JOIN accounts u ON n.user_id = u.id
                    WHERE n.offer_id = :offer_id
                    ORDER BY n.created_at ASC";

            $stmt = $conexion->prepare($sql);
            $stmt->execute([':offer_id' => $offer_id]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Procesar avatares
            foreach ($history as &$item) {
                if ($item['profile_picture']) {
                    $item['user_avatar'] = 'data:image/jpeg;base64,' . base64_encode($item['profile_picture']);
                } else {
                    $item['user_avatar'] = "https://ui-avatars.com/api/?name=" . urlencode($item['user_name']) . "&background=42ba25&color=fff&size=100";
                }
                unset($item['profile_picture']);
            }

            echo json_encode([
                'success' => true,
                'history' => $history
            ]);
            break;

        default:
            throw new Exception('Acción no reconocida');
    }

} catch (Exception $e) {
    if (isset($conexion) && $conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log("Error en shop-negotiations-backend: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
