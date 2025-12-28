<?php
// shop-requester-actions.php - Manejo de acciones del perfil de solicitante
session_start();
require_once '../config.php';

header('Content-Type: application/json');

// Verificar que el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión']);
    exit;
}

$current_user_id = $_SESSION['usuario_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        
        // SEGUIR/DEJAR DE SEGUIR SOLICITANTE
        case 'follow':
        case 'unfollow':
            $requester_id = $_POST['requester_id'] ?? 0;
            
            if (!$requester_id) {
                throw new Exception('ID de solicitante inválido');
            }
            
            // Verificar que no sea el mismo usuario
            if ($current_user_id == $requester_id) {
                throw new Exception('No puedes seguirte a ti mismo');
            }
            
            if ($action === 'follow') {
                // Seguir solicitante
                $sql = "INSERT IGNORE INTO requester_followers (follower_id, requester_id, created_at) 
                        VALUES (?, ?, NOW())";
                $stmt = $conexion->prepare($sql);
                $stmt->execute([$current_user_id, $requester_id]);
                
                $following = true;
                $message = '¡Ahora sigues a este solicitante!';
                
            } else {
                // Dejar de seguir
                $sql = "DELETE FROM requester_followers 
                        WHERE follower_id = ? AND requester_id = ?";
                $stmt = $conexion->prepare($sql);
                $stmt->execute([$current_user_id, $requester_id]);
                
                $following = false;
                $message = 'Dejaste de seguir a este solicitante';
            }
            
            // Obtener conteo actualizado
            $count_sql = "SELECT COUNT(*) as count FROM requester_followers WHERE requester_id = ?";
            $count_stmt = $conexion->prepare($count_sql);
            $count_stmt->execute([$requester_id]);
            $followers_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            echo json_encode([
                'success' => true,
                'following' => $following,
                'followers_count' => $followers_count,
                'message' => $message
            ]);
            break;
        
        // VERIFICAR ESTADO DE SEGUIMIENTO
        case 'check_follow':
            $requester_id = $_GET['requester_id'] ?? 0;
            
            if (!$requester_id) {
                throw new Exception('ID de solicitante inválido');
            }
            
            // Verificar si ya sigue
            $check_sql = "SELECT COUNT(*) as is_following FROM requester_followers 
                         WHERE follower_id = ? AND requester_id = ?";
            $check_stmt = $conexion->prepare($check_sql);
            $check_stmt->execute([$current_user_id, $requester_id]);
            $is_following = $check_stmt->fetch(PDO::FETCH_ASSOC)['is_following'] > 0;
            
            // Obtener conteo de seguidores
            $count_sql = "SELECT COUNT(*) as count FROM requester_followers WHERE requester_id = ?";
            $count_stmt = $conexion->prepare($count_sql);
            $count_stmt->execute([$requester_id]);
            $followers_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            echo json_encode([
                'success' => true,
                'following' => $is_following,
                'followers_count' => $followers_count
            ]);
            break;
        
        // ENVIAR MENSAJE AL SOLICITANTE
        case 'send_message':
            $requester_id = $_POST['requester_id'] ?? 0;
            $subject = $_POST['subject'] ?? '';
            $message = $_POST['message'] ?? '';
            
            if (!$requester_id || !$subject || !$message) {
                throw new Exception('Completa todos los campos');
            }
            
            // Verificar que no sea el mismo usuario
            if ($current_user_id == $requester_id) {
                throw new Exception('No puedes enviarte mensajes a ti mismo');
            }
            
            // Insertar mensaje
            $sql = "INSERT INTO messages (sender_id, receiver_id, subject, message, created_at) 
                    VALUES (?, ?, ?, ?, NOW())";
            $stmt = $conexion->prepare($sql);
            $stmt->execute([$current_user_id, $requester_id, $subject, $message]);
            
            // Crear notificación
            $notif_sql = "INSERT INTO notifications (user_id, type, title, message, link, created_at) 
                         VALUES (?, 'message', 'Nuevo mensaje', ?, '/messages', NOW())";
            $notif_stmt = $conexion->prepare($notif_sql);
            $notif_message = 'Has recibido un nuevo mensaje sobre: ' . $subject;
            $notif_stmt->execute([$requester_id, $notif_message]);
            
            echo json_encode([
                'success' => true,
                'message' => '¡Mensaje enviado correctamente!'
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
?>
