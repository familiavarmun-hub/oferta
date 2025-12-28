<?php
/**
 * shop-notifications-api.php
 * API con sistema híbrido de limpieza automática
 * - Limpieza automática de notificaciones leídas antiguas
 * - Límite de notificaciones por usuario
 * - Eliminación manual de notificaciones leídas
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

// ⚙️ CONFIGURACIÓN DEL SISTEMA
define('NOTIFICATIONS_AUTO_DELETE_DAYS', 7);  // Auto-eliminar leídas después de 7 días
define('NOTIFICATIONS_MAX_PER_USER', 50);      // Máximo de notificaciones por usuario

try {
    switch ($action) {
        case 'get_notifications':
            getNotifications($conexion, $user_id);
            break;
            
        case 'mark_as_read':
            markAsRead($conexion, $_POST['notification_id'] ?? 0, $user_id);
            break;
            
        case 'mark_all_as_read':
            markAllAsRead($conexion, $user_id);
            break;
            
        case 'delete_notification':
            deleteNotification($conexion, $_POST['notification_id'] ?? 0, $user_id);
            break;
        
        case 'delete_read_notifications':
            deleteReadNotifications($conexion, $user_id);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * 🔄 Obtener notificaciones con limpieza automática
 * - Limpia notificaciones leídas antiguas
 * - Limita el número máximo de notificaciones
 * - Procesa datos JSON y estadísticas
 */
function getNotifications($conexion, $user_id) {
    // 🧹 PASO 1: Limpiar notificaciones leídas antiguas (automático)
    $cleanup_sql = "DELETE FROM shop_notifications 
                    WHERE user_id = ? 
                    AND is_read = 1 
                    AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
    
    $cleanup_stmt = $conexion->prepare($cleanup_sql);
    $cleanup_stmt->execute([$user_id, NOTIFICATIONS_AUTO_DELETE_DAYS]);
    
    // 📊 PASO 2: Obtener las últimas notificaciones (con límite)
    $sql = "SELECT * FROM shop_notifications 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?";
    
    $stmt = $conexion->prepare($sql);
    $stmt->bindValue(1, $user_id, PDO::PARAM_INT);
    $stmt->bindValue(2, NOTIFICATIONS_MAX_PER_USER, PDO::PARAM_INT);
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 🔍 PASO 3: Procesar data JSON y extraer información adicional
    foreach ($notifications as &$n) {
        if (!empty($n['data'])) {
            $data = json_decode($n['data'], true);
            $n['action_url'] = $data['action_url'] ?? null;
            $n['reference_id'] = $data['reference_id'] ?? null;
        } else {
            $n['action_url'] = null;
            $n['reference_id'] = null;
        }
    }
    
    // 📈 PASO 4: Calcular estadísticas
    $stats = [
        'total' => count($notifications),
        'unread' => 0,
        'important' => 0
    ];
    
    foreach ($notifications as $n) {
        if ($n['is_read'] == 0) {
            $stats['unread']++;
        }
        if (in_array($n['type'], ['new_proposal', 'payment_received', 'delivery_completed'])) {
            $stats['important']++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'stats' => $stats
    ]);
}

/**
 * ✅ Marcar una notificación como leída
 */
function markAsRead($conexion, $notification_id, $user_id) {
    if (empty($notification_id)) {
        echo json_encode(['success' => false, 'error' => 'ID de notificación no válido']);
        return;
    }
    
    $sql = "UPDATE shop_notifications 
            SET is_read = 1 
            WHERE id = ? AND user_id = ?";
    
    $stmt = $conexion->prepare($sql);
    $result = $stmt->execute([$notification_id, $user_id]);
    
    echo json_encode([
        'success' => $result,
        'message' => $result ? 'Notificación marcada como leída' : 'Error al actualizar'
    ]);
}

/**
 * ✅✅ Marcar todas las notificaciones como leídas
 */
function markAllAsRead($conexion, $user_id) {
    $sql = "UPDATE shop_notifications 
            SET is_read = 1 
            WHERE user_id = ? AND is_read = 0";
    
    $stmt = $conexion->prepare($sql);
    $result = $stmt->execute([$user_id]);
    $affected = $stmt->rowCount();
    
    echo json_encode([
        'success' => $result,
        'affected' => $affected,
        'message' => $affected > 0 
            ? "Se marcaron $affected notificaciones como leídas" 
            : 'No hay notificaciones sin leer'
    ]);
}

/**
 * 🗑️ Eliminar una notificación específica
 */
function deleteNotification($conexion, $notification_id, $user_id) {
    if (empty($notification_id)) {
        echo json_encode(['success' => false, 'error' => 'ID de notificación no válido']);
        return;
    }
    
    $sql = "DELETE FROM shop_notifications 
            WHERE id = ? AND user_id = ?";
    
    $stmt = $conexion->prepare($sql);
    $result = $stmt->execute([$notification_id, $user_id]);
    
    echo json_encode([
        'success' => $result,
        'message' => $result ? 'Notificación eliminada' : 'Error al eliminar'
    ]);
}

/**
 * 🧹 Eliminar TODAS las notificaciones leídas (manual)
 * Solo elimina las que el usuario ya marcó como leídas
 */
function deleteReadNotifications($conexion, $user_id) {
    $sql = "DELETE FROM shop_notifications 
            WHERE user_id = ? AND is_read = 1";
    
    $stmt = $conexion->prepare($sql);
    $result = $stmt->execute([$user_id]);
    $deleted_count = $stmt->rowCount();
    
    echo json_encode([
        'success' => $result,
        'deleted' => $deleted_count,
        'message' => $deleted_count > 0 
            ? "Se eliminaron $deleted_count notificaciones" 
            : 'No hay notificaciones leídas para eliminar'
    ]);
}