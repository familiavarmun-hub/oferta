<?php
/**
 * shop-edit-request-action.php - Procesar actualización de solicitud
 */
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$user_id = $_SESSION['usuario_id'];
$action = $_POST['action'] ?? '';

if ($action !== 'update_request') {
    echo json_encode(['success' => false, 'error' => 'Acción inválida']);
    exit;
}

try {
    $request_id = (int)($_POST['request_id'] ?? 0);

    if ($request_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID de solicitud inválido']);
        exit;
    }

    // Verificar que la solicitud pertenece al usuario y es editable
    $stmt = $conexion->prepare("
        SELECT * FROM shop_requests 
        WHERE id = :id AND user_id = :user_id
    ");
    $stmt->execute([':id' => $request_id, ':user_id' => $user_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        echo json_encode(['success' => false, 'error' => 'Solicitud no encontrada']);
        exit;
    }

    $editable_statuses = ['open', 'negotiating'];
    if (!in_array($request['status'], $editable_statuses)) {
        echo json_encode(['success' => false, 'error' => 'Esta solicitud no puede ser editada']);
        exit;
    }

    // Obtener datos del formulario
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 1);
    $budget_amount = (float)($_POST['budget_amount'] ?? 0);
    $budget_currency = trim($_POST['budget_currency'] ?? 'EUR');
    $origin_country = trim($_POST['origin_country'] ?? '');
    $origin_flexible = isset($_POST['origin_flexible']) ? 1 : 0;
    $destination_city = trim($_POST['destination_city'] ?? '');
    $deadline_date = !empty($_POST['deadline_date']) ? $_POST['deadline_date'] : null;
    $urgency = trim($_POST['urgency'] ?? 'flexible');
    $includes_product_cost = isset($_POST['includes_product_cost']) ? 1 : 0;

    // Validaciones
    if (empty($title) || empty($description) || empty($category) || empty($destination_city)) {
        echo json_encode(['success' => false, 'error' => 'Faltan campos obligatorios']);
        exit;
    }

    if ($budget_amount <= 0) {
        echo json_encode(['success' => false, 'error' => 'El presupuesto debe ser mayor a 0']);
        exit;
    }

    // Procesar imágenes
    $current_images = json_decode($request['reference_images'], true) ?: [];
    $removed_images = json_decode($_POST['removed_images'] ?? '[]', true) ?: [];
    $new_images_data = json_decode($_POST['new_images'] ?? '[]', true) ?: [];

    // Remover imágenes eliminadas
    foreach ($removed_images as $index) {
        if (isset($current_images[$index])) {
            unset($current_images[$index]);
        }
    }
    $current_images = array_values($current_images);

    // Guardar nuevas imágenes
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

    foreach ($new_images_data as $imageObj) {
        if (isset($imageObj['data']) && isset($imageObj['name'])) {
            $savedPath = saveBase64Image($imageObj['data'], $imageObj['name']);
            if ($savedPath) {
                $current_images[] = $savedPath;
            }
        }
    }

    // Procesar enlaces
    $current_links = json_decode($request['reference_links'], true) ?: [];
    $removed_links = json_decode($_POST['removed_links'] ?? '[]', true) ?: [];
    $new_links = json_decode($_POST['new_links'] ?? '[]', true) ?: [];

    // Remover enlaces eliminados
    foreach ($removed_links as $index) {
        if (isset($current_links[$index])) {
            unset($current_links[$index]);
        }
    }
    $current_links = array_values($current_links);

    // Agregar nuevos enlaces
    $current_links = array_merge($current_links, $new_links);

    // Actualizar en base de datos
    $stmt = $conexion->prepare("
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
        ':budget_amount' => $budget_amount,
        ':budget_currency' => $budget_currency,
        ':origin_country' => $origin_country,
        ':origin_flexible' => $origin_flexible,
        ':destination_city' => $destination_city,
        ':deadline_date' => $deadline_date,
        ':urgency' => $urgency,
        ':includes_product_cost' => $includes_product_cost,
        ':reference_images' => json_encode($current_images),
        ':reference_links' => json_encode($current_links),
        ':id' => $request_id,
        ':user_id' => $user_id
    ]);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Solicitud actualizada exitosamente'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'No se pudo actualizar la solicitud'
        ]);
    }

} catch (PDOException $e) {
    error_log("Error en update_request: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al actualizar: ' . $e->getMessage()
    ]);
}<?php
/**
 * shop-edit-request-action.php - Procesar actualización de solicitud
 */
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$user_id = $_SESSION['usuario_id'];
$action = $_POST['action'] ?? '';

if ($action !== 'update_request') {
    echo json_encode(['success' => false, 'error' => 'Acción inválida']);
    exit;
}

try {
    $request_id = (int)($_POST['request_id'] ?? 0);

    if ($request_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID de solicitud inválido']);
        exit;
    }

    // Verificar que la solicitud pertenece al usuario y es editable
    $stmt = $conexion->prepare("
        SELECT * FROM shop_requests 
        WHERE id = :id AND user_id = :user_id
    ");
    $stmt->execute([':id' => $request_id, ':user_id' => $user_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        echo json_encode(['success' => false, 'error' => 'Solicitud no encontrada']);
        exit;
    }

    $editable_statuses = ['open', 'negotiating'];
    if (!in_array($request['status'], $editable_statuses)) {
        echo json_encode(['success' => false, 'error' => 'Esta solicitud no puede ser editada']);
        exit;
    }

    // Obtener datos del formulario
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 1);
    $budget_amount = (float)($_POST['budget_amount'] ?? 0);
    $budget_currency = trim($_POST['budget_currency'] ?? 'EUR');
    $origin_country = trim($_POST['origin_country'] ?? '');
    $origin_flexible = isset($_POST['origin_flexible']) ? 1 : 0;
    $destination_city = trim($_POST['destination_city'] ?? '');
    $deadline_date = !empty($_POST['deadline_date']) ? $_POST['deadline_date'] : null;
    $urgency = trim($_POST['urgency'] ?? 'flexible');
    $includes_product_cost = isset($_POST['includes_product_cost']) ? 1 : 0;

    // Validaciones
    if (empty($title) || empty($description) || empty($category) || empty($destination_city)) {
        echo json_encode(['success' => false, 'error' => 'Faltan campos obligatorios']);
        exit;
    }

    if ($budget_amount <= 0) {
        echo json_encode(['success' => false, 'error' => 'El presupuesto debe ser mayor a 0']);
        exit;
    }

    // Procesar imágenes
    $current_images = json_decode($request['reference_images'], true) ?: [];
    $removed_images = json_decode($_POST['removed_images'] ?? '[]', true) ?: [];
    $new_images_data = json_decode($_POST['new_images'] ?? '[]', true) ?: [];

    // Remover imágenes eliminadas
    foreach ($removed_images as $index) {
        if (isset($current_images[$index])) {
            unset($current_images[$index]);
        }
    }
    $current_images = array_values($current_images);

    // Guardar nuevas imágenes
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

    foreach ($new_images_data as $imageObj) {
        if (isset($imageObj['data']) && isset($imageObj['name'])) {
            $savedPath = saveBase64Image($imageObj['data'], $imageObj['name']);
            if ($savedPath) {
                $current_images[] = $savedPath;
            }
        }
    }

    // Procesar enlaces
    $current_links = json_decode($request['reference_links'], true) ?: [];
    $removed_links = json_decode($_POST['removed_links'] ?? '[]', true) ?: [];
    $new_links = json_decode($_POST['new_links'] ?? '[]', true) ?: [];

    // Remover enlaces eliminados
    foreach ($removed_links as $index) {
        if (isset($current_links[$index])) {
            unset($current_links[$index]);
        }
    }
    $current_links = array_values($current_links);

    // Agregar nuevos enlaces
    $current_links = array_merge($current_links, $new_links);

    // Actualizar en base de datos
    $stmt = $conexion->prepare("
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
        ':budget_amount' => $budget_amount,
        ':budget_currency' => $budget_currency,
        ':origin_country' => $origin_country,
        ':origin_flexible' => $origin_flexible,
        ':destination_city' => $destination_city,
        ':deadline_date' => $deadline_date,
        ':urgency' => $urgency,
        ':includes_product_cost' => $includes_product_cost,
        ':reference_images' => json_encode($current_images),
        ':reference_links' => json_encode($current_links),
        ':id' => $request_id,
        ':user_id' => $user_id
    ]);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Solicitud actualizada exitosamente'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'No se pudo actualizar la solicitud'
        ]);
    }

} catch (PDOException $e) {
    error_log("Error en update_request: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al actualizar: ' . $e->getMessage()
    ]);
}