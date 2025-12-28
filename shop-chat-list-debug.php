<?php
// Activar reporte de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body>";
echo "<h2>🔍 Debug de Shop Chat List</h2>";

try {
    echo "<p>✓ Iniciando sesión...</p>";
    session_start();
    
    if (!isset($_SESSION['usuario_id'])) {
        die('<p style="color:red;">❌ No hay sesión activa. <a href="../login.php">Ir a login</a></p>');
    }
    
    echo "<p>✓ Sesión activa: Usuario ID = " . $_SESSION['usuario_id'] . "</p>";
    
    echo "<p>✓ Cargando config...</p>";
    require_once '../config.php';
    
    echo "<p>✓ Conexión a BD establecida</p>";
    
    $user_id = $_SESSION['usuario_id'];
    
    echo "<p>✓ Verificando tabla shop_chat_messages...</p>";
    
    // Verificar si existe la tabla
    $stmt = $conexion->query("SHOW TABLES LIKE 'shop_chat_messages'");
    if ($stmt->rowCount() === 0) {
        die('<p style="color:red;">❌ La tabla shop_chat_messages NO EXISTE. Debes ejecutar el SQL primero.</p>
             <pre>CREATE TABLE IF NOT EXISTS shop_chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proposal_id INT NOT NULL,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proposal_id) REFERENCES shop_request_proposals(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES accounts(id) ON DELETE CASCADE,
    INDEX idx_proposal (proposal_id),
    INDEX idx_users (sender_id, receiver_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;</pre>');
    }
    
    echo "<p>✓ Tabla shop_chat_messages existe</p>";
    
    // Verificar tablas relacionadas
    echo "<p>✓ Verificando tablas relacionadas...</p>";
    
    $tables = ['shop_request_proposals', 'shop_requests', 'accounts'];
    foreach ($tables as $table) {
        $stmt = $conexion->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() === 0) {
            die("<p style='color:red;'>❌ La tabla $table NO EXISTE</p>");
        }
        echo "<p>✓ Tabla $table existe</p>";
    }
    
    // Intentar ejecutar la consulta principal
    echo "<p>✓ Ejecutando consulta de conversaciones...</p>";
    
    $sql = "SELECT DISTINCT 
                   p.id as proposal_id,
                   p.status as proposal_status,
                   r.title as product_title,
                   r.user_id as requester_id,
                   p.traveler_id,
                   COALESCE(req.full_name, req.username) as requester_name,
                   COALESCE(trav.full_name, trav.username) as traveler_name,
                   (SELECT message 
                    FROM shop_chat_messages 
                    WHERE proposal_id = p.id 
                    ORDER BY created_at DESC LIMIT 1) as last_message,
                   (SELECT sender_id 
                    FROM shop_chat_messages 
                    WHERE proposal_id = p.id 
                    ORDER BY created_at DESC LIMIT 1) as last_sender_id,
                   (SELECT created_at 
                    FROM shop_chat_messages 
                    WHERE proposal_id = p.id 
                    ORDER BY created_at DESC LIMIT 1) as last_message_time,
                   (SELECT COUNT(*) 
                    FROM shop_chat_messages 
                    WHERE proposal_id = p.id 
                      AND receiver_id = :user_id 
                      AND is_read = 0) as unread_count
            FROM shop_request_proposals p
            JOIN shop_requests r ON r.id = p.request_id
            LEFT JOIN accounts req ON req.id = r.user_id
            LEFT JOIN accounts trav ON trav.id = p.traveler_id
            WHERE (p.traveler_id = :user_id OR r.user_id = :user_id)
              AND EXISTS (
                  SELECT 1 FROM shop_chat_messages 
                  WHERE proposal_id = p.id
              )
            ORDER BY last_message_time DESC NULLS LAST, p.created_at DESC";
    
    $stmt = $conexion->prepare($sql);
    $stmt->execute([':user_id' => $user_id]);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>✓ Consulta ejecutada correctamente</p>";
    echo "<p>✓ Conversaciones encontradas: " . count($conversations) . "</p>";
    
    if (count($conversations) > 0) {
        echo "<h3>Conversaciones:</h3>";
        echo "<pre>" . print_r($conversations, true) . "</pre>";
    } else {
        echo "<p>ℹ️ No tienes conversaciones con mensajes todavía.</p>";
    }
    
    echo "<hr>";
    echo "<h3>✅ TODO OK - No hay errores</h3>";
    echo "<p><a href='shop-chat-list.php'>Intentar cargar shop-chat-list.php</a></p>";
    
} catch (PDOException $e) {
    echo "<h3 style='color:red;'>❌ ERROR DE BASE DE DATOS:</h3>";
    echo "<pre style='background:#ffebee; padding:15px; border-radius:5px;'>";
    echo "Mensaje: " . $e->getMessage() . "\n";
    echo "Código: " . $e->getCode() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString();
    echo "</pre>";
} catch (Exception $e) {
    echo "<h3 style='color:red;'>❌ ERROR GENERAL:</h3>";
    echo "<pre style='background:#ffebee; padding:15px; border-radius:5px;'>";
    echo "Mensaje: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString();
    echo "</pre>";
}

echo "</body></html>";