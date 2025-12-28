<?php
// Activar reporte de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== DEBUG SHOP-CHAT ===<br>\n";

// 1. Verificar sesión
echo "1. Verificando sesión...<br>\n";
session_start();
if (!isset($_SESSION['usuario_id'])) {
    echo "❌ No hay sesión activa<br>\n";
    echo "Redirigiendo a login...<br>\n";
    exit;
}
echo "✅ Sesión activa: usuario_id = " . $_SESSION['usuario_id'] . "<br>\n";

// 2. Verificar config.php
echo "<br>2. Cargando config.php...<br>\n";
try {
    require_once 'config.php';
    echo "✅ config.php cargado correctamente<br>\n";
} catch (Exception $e) {
    echo "❌ Error al cargar config.php: " . $e->getMessage() . "<br>\n";
    exit;
}

// 3. Verificar conexión BD
echo "<br>3. Verificando conexión a BD...<br>\n";
if (isset($conexion)) {
    echo "✅ Conexión a BD establecida<br>\n";
} else {
    echo "❌ No se pudo conectar a la BD<br>\n";
    exit;
}

// 4. Verificar proposal_id
echo "<br>4. Verificando proposal_id...<br>\n";
$proposal_id = (int)($_GET['proposal_id'] ?? 0);
echo "proposal_id recibido: $proposal_id<br>\n";

if ($proposal_id <= 0) {
    echo "❌ proposal_id inválido<br>\n";
    exit;
}
echo "✅ proposal_id válido<br>\n";

// 5. Consultar propuesta
echo "<br>5. Consultando propuesta en BD...<br>\n";
try {
    $sql = "SELECT p.*, r.title, r.user_id as requester_id,
                   p.traveler_id,
                   COALESCE(req.full_name, req.username) as requester_name,
                   COALESCE(trav.full_name, trav.username) as traveler_name
            FROM shop_request_proposals p
            JOIN shop_requests r ON r.id = p.request_id
            LEFT JOIN accounts req ON req.id = r.user_id
            LEFT JOIN accounts trav ON trav.id = p.traveler_id
            WHERE p.id = ? AND (p.traveler_id = ? OR r.user_id = ?)";

    $stmt = $conexion->prepare($sql);
    $user_id = $_SESSION['usuario_id'];
    $stmt->execute([$proposal_id, $user_id, $user_id]);
    $proposal = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$proposal) {
        echo "❌ Propuesta no encontrada o sin acceso<br>\n";
        echo "Query ejecutada con: proposal_id=$proposal_id, user_id=$user_id<br>\n";
        exit;
    }

    echo "✅ Propuesta encontrada:<br>\n";
    echo "- ID: " . $proposal['id'] . "<br>\n";
    echo "- Título: " . htmlspecialchars($proposal['title']) . "<br>\n";
    echo "- Estado: " . $proposal['status'] . "<br>\n";
    echo "- Solicitante ID: " . $proposal['requester_id'] . "<br>\n";
    echo "- Viajero ID: " . $proposal['traveler_id'] . "<br>\n";

} catch (PDOException $e) {
    echo "❌ Error en consulta SQL: " . $e->getMessage() . "<br>\n";
    exit;
}

// 6. Verificar delivery
echo "<br>6. Verificando delivery...<br>\n";
$isAccepted = ($proposal['status'] === 'accepted');
echo "Propuesta aceptada: " . ($isAccepted ? 'SÍ' : 'NO') . "<br>\n";

if ($isAccepted) {
    try {
        $sql_delivery = "SELECT d.*
                         FROM shop_deliveries d
                         WHERE d.proposal_id = ?";
        $stmt_delivery = $conexion->prepare($sql_delivery);
        $stmt_delivery->execute([$proposal_id]);
        $delivery = $stmt_delivery->fetch(PDO::FETCH_ASSOC);

        if ($delivery) {
            echo "✅ Delivery encontrado<br>\n";
            echo "- ID: " . $delivery['id'] . "<br>\n";
        } else {
            echo "⚠️ No hay delivery creado aún<br>\n";
        }
    } catch (PDOException $e) {
        echo "❌ Error consultando delivery: " . $e->getMessage() . "<br>\n";
    }
}

echo "<br>=== FIN DEBUG ===<br>\n";
echo "<br>Si llegaste hasta aquí, el problema NO está en las consultas básicas.<br>\n";
echo "El error 500 podría estar en el HTML/CSS/JavaScript del archivo original.<br>\n";
?>