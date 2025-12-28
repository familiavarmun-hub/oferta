<?php
/**
 * shop-update-delivery-state-producto.php
 * Actualizar estado de entrega de productos (para vendedores)
 */

session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$user_id = $_SESSION['usuario_id'];

// Leer datos del POST
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

$delivery_id = (int)($data['delivery_id'] ?? 0);
$new_state = $data['new_state'] ?? '';

// Validar estado
$valid_states = ['pending', 'in_transit', 'at_destination', 'delivered'];
if (!in_array($new_state, $valid_states)) {
    echo json_encode(['success' => false, 'message' => 'Estado inválido']);
    exit;
}

if ($delivery_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de entrega no válido']);
    exit;
}

try {
    $conexion->beginTransaction();

    // Verificar que el usuario es el vendedor de esta entrega
    $sql = "SELECT d.*, d.delivery_state as current_state
            FROM shop_product_deliveries d
            WHERE d.id = :delivery_id AND d.seller_id = :user_id";

    $stmt = $conexion->prepare($sql);
    $stmt->execute([
        ':delivery_id' => $delivery_id,
        ':user_id' => $user_id
    ]);

    $delivery = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$delivery) {
        throw new Exception('Entrega no encontrada o no autorizada');
    }

    // Validar que la entrega no esté ya entregada
    if ($delivery['current_state'] === 'delivered') {
        throw new Exception('Esta entrega ya ha sido completada');
    }

    // Validar transición de estado
    $validTransitions = [
        'pending' => ['in_transit'],
        'in_transit' => ['at_destination'],
        'at_destination' => ['delivered'] // Solo vía QR scan
    ];

    if (!isset($validTransitions[$delivery['current_state']]) ||
        !in_array($new_state, $validTransitions[$delivery['current_state']])) {
        throw new Exception('Transición de estado no permitida');
    }

    // No permitir marcar como "delivered" sin QR (solo para vendedores)
    if ($new_state === 'delivered') {
        throw new Exception('La entrega debe ser confirmada escaneando el código QR');
    }

    // Actualizar estado
    $sql_update = "UPDATE shop_product_deliveries
                   SET delivery_state = :new_state
                   WHERE id = :id";

    $stmt_update = $conexion->prepare($sql_update);
    $stmt_update->execute([
        ':new_state' => $new_state,
        ':id' => $delivery_id
    ]);

    // Registrar en historial
    $sql_history = "INSERT INTO shop_product_delivery_state_history
                    (delivery_id, previous_state, new_state, changed_by, change_method, notes)
                    VALUES (:delivery_id, :previous_state, :new_state, :changed_by, 'manual', 'Actualizado por vendedor')";

    $stmt_history = $conexion->prepare($sql_history);
    $stmt_history->execute([
        ':delivery_id' => $delivery_id,
        ':previous_state' => $delivery['current_state'],
        ':new_state' => $new_state,
        ':changed_by' => $user_id
    ]);

    $conexion->commit();

    error_log("✅ ESTADO PRODUCTO ACTUALIZADO - Delivery #{$delivery_id}");
    error_log("   {$delivery['current_state']} → {$new_state}");
    error_log("   Por vendedor: {$user_id}");

    echo json_encode([
        'success' => true,
        'message' => 'Estado actualizado exitosamente',
        'delivery_id' => $delivery_id,
        'previous_state' => $delivery['current_state'],
        'new_state' => $new_state
    ]);

} catch (Exception $e) {
    if (isset($conexion) && $conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log("❌ Error en shop-update-delivery-state-producto: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
