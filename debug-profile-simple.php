<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "INICIANDO DEBUG...<br><br>";

session_start();
echo "✓ Session iniciada<br>";

require_once 'insignias1.php';
echo "✓ insignias1.php cargado<br>";

require_once '../config.php';
echo "✓ config.php cargado<br>";

$requester_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
echo "✓ ID recibido: " . $requester_id . "<br><br>";

if ($requester_id <= 0) {
    die("ERROR: ID inválido");
}

$user_logged_in = isset($_SESSION['usuario_id']);
$current_user_id = $user_logged_in ? $_SESSION['usuario_id'] : null;
echo "✓ Usuario logueado: " . ($user_logged_in ? 'SI (ID: '.$current_user_id.')' : 'NO') . "<br><br>";

try {
    echo "Ejecutando consulta de usuario...<br>";
    $requester_sql = "SELECT a.id, a.full_name, a.username, a.verificado, a.provincia, a.pais, a.created_at, a.ruta_imagen FROM accounts a WHERE a.id = ?";
    $requester_stmt = $conexion->prepare($requester_sql);
    $requester_stmt->execute([$requester_id]);
    $requester = $requester_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$requester) {
        die("ERROR: Usuario no encontrado");
    }
    
    echo "✓ Usuario encontrado: " . $requester['full_name'] . "<br><br>";
    
    echo "Ejecutando consulta de rating...<br>";
    $rating_sql = "SELECT AVG(valoracion) as promedio_valoracion, COUNT(*) as total_valoraciones FROM comentarios WHERE usuario_id = ? AND bloqueado = 0";
    $rating_stmt = $conexion->prepare($rating_sql);
    $rating_stmt->execute([$requester_id]);
    $rating_data = $rating_stmt->fetch(PDO::FETCH_ASSOC);
    $requester_rating = $rating_data['promedio_valoracion'] ? round($rating_data['promedio_valoracion'], 1) : 0;
    $total_ratings = $rating_data['total_valoraciones'] ?: 0;
    
    echo "✓ Rating: " . $requester_rating . " (" . $total_ratings . " valoraciones)<br><br>";
    
    echo "Ejecutando consulta de solicitudes...<br>";
    $requests_sql = "SELECT sr.*, 
        (SELECT COUNT(*) FROM shop_request_proposals WHERE request_id = sr.id) as proposal_count,
        (SELECT image_path FROM shop_request_images sri WHERE sri.request_id = sr.id LIMIT 1) as primary_image 
        FROM shop_requests sr 
        WHERE sr.user_id = ? AND sr.status NOT IN ('completed', 'cancelled') 
        ORDER BY sr.created_at DESC 
        LIMIT 10";
    $requests_stmt = $conexion->prepare($requests_sql);
    $requests_stmt->execute([$requester_id]);
    $requests = $requests_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "✓ Solicitudes encontradas: " . count($requests) . "<br><br>";
    
    if (count($requests) > 0) {
        echo "<h3>Solicitudes:</h3>";
        foreach ($requests as $req) {
            echo "- ID: " . $req['id'] . " | Titulo: " . $req['title'] . " | Status: " . $req['status'] . "<br>";
        }
    }
    
    echo "<br><br>✅ TODO FUNCIONA CORRECTAMENTE - NO DEBERÍA HABER REDIRECT";
    
} catch (PDOException $e) {
    echo "<br><br>❌ ERROR SQL: " . $e->getMessage();
}
?>
