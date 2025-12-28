<?php
session_start();
require_once 'insignias1.php';
require_once '../config.php';

$requester_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($requester_id <= 0) {
    die("ID inválido");
}

try {
    $sql = "SELECT * FROM accounts WHERE id = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->execute([$requester_id]);
    $requester = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$requester) {
        die("Usuario no encontrado");
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Perfil de <?= $requester['full_name'] ?></title>
</head>
<body>
    <h1>Perfil de Solicitante</h1>
    <h2><?= htmlspecialchars($requester['full_name']) ?></h2>
    <p>Username: @<?= htmlspecialchars($requester['username']) ?></p>
    <p>ID: <?= $requester['id'] ?></p>
    <p>✅ Si ves esto, el archivo funciona correctamente</p>
    
    <a href="shop-request-detail.php?id=34">Volver al detalle</a>
</body>
</html>
```

Sube este archivo y accede a:
```
https://sendvialo.com/shop/shop-requester-profile-simple.php?id=60