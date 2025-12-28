<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

$user_id = $_SESSION['usuario_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'send_message':
            $proposal_id = (int)($_POST['proposal_id'] ?? 0);
            $message = isset($_POST['message']) ? trim($_POST['message']) : '';
            $receiver_id = (int)($_POST['receiver_id'] ?? 0);
            $message_type = $_POST['message_type'] ?? 'text'; // text, photo, location
            
            if ($proposal_id <= 0 || $receiver_id <= 0) {
                throw new Exception('Datos inválidos');
            }

            // Verificar que al menos haya mensaje, foto o ubicación
            $has_text = !empty($message);
            $has_photo = !empty($_FILES['photo']['name']);
            $has_location = ($message_type === 'location' && isset($_POST['latitude'], $_POST['longitude']));
            
            if (!$has_text && !$has_photo && !$has_location) {
                throw new Exception('Debes enviar un mensaje, foto o ubicación');
            }

            // Verificar que el usuario es parte de la propuesta
            $sql = "SELECT p.id, p.traveler_id, r.user_id as requester_id
                    FROM shop_request_proposals p
                    JOIN shop_requests r ON r.id = p.request_id
                    WHERE p.id = ? AND (p.traveler_id = ? OR r.user_id = ?)";
            $stmt = $conexion->prepare($sql);
            $stmt->execute([$proposal_id, $user_id, $user_id]);
            $proposal = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$proposal) {
                throw new Exception('Propuesta no encontrada');
            }

            // Validar receptor
            $valid_receiver = ($receiver_id == $proposal['traveler_id'] || 
                             $receiver_id == $proposal['requester_id']) && 
                             $receiver_id != $user_id;

            if (!$valid_receiver) {
                throw new Exception('Receptor inválido');
            }

            // Preparar datos del mensaje
            $photo_path = null;
            $latitude = null;
            $longitude = null;

            // Procesar foto si existe
            if (!empty($_FILES['photo']['name'])) {
                // Ruta correcta: /shop/uploads/shop-chat/
                $upload_dir = __DIR__ . '/uploads/shop-chat/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $file = $_FILES['photo'];
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                
                if (!in_array($file['type'], $allowed_types)) {
                    throw new Exception('Tipo de archivo no permitido');
                }

                if ($file['size'] > 5 * 1024 * 1024) { // 5MB max
                    throw new Exception('La imagen es demasiado grande (máx 5MB)');
                }

                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'chat_' . $proposal_id . '_' . time() . '_' . uniqid() . '.' . $extension;
                $filepath = $upload_dir . $filename;

                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    // Guardar ruta relativa desde la raíz de shop
                    $photo_path = 'uploads/shop-chat/' . $filename;
                } else {
                    throw new Exception('Error al subir la foto');
                }
            }

            // Procesar ubicación si existe
            if ($message_type === 'location' && isset($_POST['latitude'], $_POST['longitude'])) {
                $latitude = (float)$_POST['latitude'];
                $longitude = (float)$_POST['longitude'];

                // Validar coordenadas
                if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
                    throw new Exception('Coordenadas inválidas');
                }
            }

            // Insertar mensaje
            $sql = "INSERT INTO shop_chat_messages 
                    (proposal_id, sender_id, receiver_id, message, photo_path, latitude, longitude, message_type) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conexion->prepare($sql);
            $stmt->execute([
                $proposal_id, 
                $user_id, 
                $receiver_id, 
                !empty($message) ? $message : null,  // NULL si está vacío
                $photo_path,
                $latitude,
                $longitude,
                $message_type
            ]);

            echo json_encode([
                'success' => true,
                'message_id' => $conexion->lastInsertId()
            ]);
            break;

        case 'get_messages':
            $proposal_id = (int)($_GET['proposal_id'] ?? 0);
            $last_id = (int)($_GET['last_id'] ?? 0);

            if ($proposal_id <= 0) {
                throw new Exception('ID de propuesta inválido');
            }

            // Verificar acceso
            $sql = "SELECT p.id, p.traveler_id, r.user_id as requester_id
                    FROM shop_request_proposals p
                    JOIN shop_requests r ON r.id = p.request_id
                    WHERE p.id = ? AND (p.traveler_id = ? OR r.user_id = ?)";
            $stmt = $conexion->prepare($sql);
            $stmt->execute([$proposal_id, $user_id, $user_id]);
            
            if (!$stmt->fetch()) {
                throw new Exception('Acceso denegado');
            }

            // Obtener mensajes
            $sql = "SELECT m.*, 
                           COALESCE(s.full_name, s.username) as sender_name,
                           s.id as sender_user_id
                    FROM shop_chat_messages m
                    JOIN accounts s ON s.id = m.sender_id
                    WHERE m.proposal_id = ? AND m.id > ?
                    ORDER BY m.created_at ASC";
            $stmt = $conexion->prepare($sql);
            $stmt->execute([$proposal_id, $last_id]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Marcar como leídos
            if (!empty($messages)) {
                $sql = "UPDATE shop_chat_messages 
                        SET is_read = 1 
                        WHERE proposal_id = ? AND receiver_id = ? AND is_read = 0";
                $stmt = $conexion->prepare($sql);
                $stmt->execute([$proposal_id, $user_id]);
            }

            echo json_encode([
                'success' => true,
                'messages' => $messages
            ]);
            break;

        case 'get_unread_count':
            $proposal_id = (int)($_GET['proposal_id'] ?? 0);

            if ($proposal_id <= 0) {
                throw new Exception('ID inválido');
            }

            $sql = "SELECT COUNT(*) 
                    FROM shop_chat_messages 
                    WHERE proposal_id = ? AND receiver_id = ? AND is_read = 0";
            $stmt = $conexion->prepare($sql);
            $stmt->execute([$proposal_id, $user_id]);
            $count = $stmt->fetchColumn();

            echo json_encode([
                'success' => true,
                'unread_count' => (int)$count
            ]);
            break;

        case 'get_all_unread':
            $sql = "SELECT proposal_id, COUNT(*) as count
                    FROM shop_chat_messages
                    WHERE receiver_id = ? AND is_read = 0
                    GROUP BY proposal_id";
            $stmt = $conexion->prepare($sql);
            $stmt->execute([$user_id]);
            $unread = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            echo json_encode([
                'success' => true,
                'unread_by_proposal' => $unread
            ]);
            break;

        default:
            throw new Exception('Acción no válida');
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}