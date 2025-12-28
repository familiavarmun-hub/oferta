<?php
// chatpage.php – versión completa con integración Shop

require 'config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['username'])) {
    $url = 'chatpage.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
    echo "<script>localStorage.setItem('redirect_after_login','$url');window.location.href='login.php';</script>";
    exit;
}
$current_user = $_SESSION['username'];

require 'chat_manager.php';

// ✅ Wrapper simple para agregar lectura individual
$chatManager = new class($conexion) extends ChatManager {
    
    public function __construct($db) {
        parent::__construct($db);
        $this->db = $db;
    }
    
    // Sobrescribir solo el método markAsRead para usar chat_message_reads
    public function markAsRead($conversation_id, $username) {
        try {
            // Verificar si existe la nueva tabla
            $tableExists = false;
            try {
                $check = $this->db->query("SHOW TABLES LIKE 'chat_message_reads'");
                $tableExists = ($check->rowCount() > 0);
            } catch (Exception $e) {}
            
            if ($tableExists) {
                // Sistema NUEVO: lectura individual
                $sql = "SELECT cm.message_id
                        FROM chat_messages cm
                        WHERE cm.conversation_id = :conversation_id
                          AND cm.sender_username != :username
                          AND cm.message_id NOT IN (
                              SELECT message_id 
                              FROM chat_message_reads 
                              WHERE username = :username
                          )";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    ':conversation_id' => $conversation_id,
                    ':username' => $username
                ]);
                
                $unreadMessages = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                if (!empty($unreadMessages)) {
                    // Insertar lecturas individuales
                    foreach ($unreadMessages as $message_id) {
                        $insertSql = "INSERT IGNORE INTO chat_message_reads (message_id, username) VALUES (?, ?)";
                        $insertStmt = $this->db->prepare($insertSql);
                        $insertStmt->execute([$message_id, $username]);
                    }
                }
            } else {
                // Fallback: usar método padre (sistema antiguo)
                return parent::markAsRead($conversation_id, $username);
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Error marking as read: " . $e->getMessage());
            return false;
        }
    }
};

/* =========================
   HELPERS
   ========================= */
function getUserIdByUsername(PDO $db, string $username): ?int {
    $st = $db->prepare("SELECT id FROM accounts WHERE username = ? LIMIT 1");
    $st->execute([$username]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ? (int)$row['id'] : null;
}
function getProfileImageUrlByUserId(?int $userId): string {
    return ($userId && $userId > 0) ? 'mostrar_imagen.php?id='.(int)$userId : 'Imagenes/user-default.jpg';
}
function getProfileImageUrlByUsername(PDO $db, ?string $username): string {
    if (!$username) return 'Imagenes/user-default.jpg';
    $uid = getUserIdByUsername($db, $username);
    return getProfileImageUrlByUserId($uid);
}
function getInitials($n) {
    $w = preg_split('/\s+/', trim($n));
    $i = '';
    for ($j=0;$j<min(2,count($w));$j++) $i .= strtoupper($w[$j][0] ?? '?');
    return $i ?: '?';
}
function isGroup($c) {
    return !empty($c['is_group']) || stripos($c['title'] ?? '', 'grupo')!==false;
}

/* =========================
   TÍTULO ORIGINAL - VISTA DINÁMICA
   ========================= */
function getOriginalChatTitle($conv, $current_user, $conexion) {
    if (!empty($conv['is_group']) || stripos($conv['title'] ?? '', 'grupo') !== false) {
        return $conv['title'] ?? 'Grupo';
    }

    if (!empty($conv['conversation_id'])) {
        $query = "SELECT cp.username, cp.is_admin
                  FROM chat_participants cp 
                  WHERE cp.conversation_id = :conv_id 
                    AND cp.invited_by_link = 0
                  ORDER BY cp.participant_id ASC 
                  LIMIT 2";
        $stmt = $conexion->prepare($query);
        $stmt->bindValue(':conv_id', $conv['conversation_id'], PDO::PARAM_INT);
        $stmt->execute();
        $original = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($original) === 2) {
            $pagador = $original[0]['username'];
            $viajero = $original[1]['username'];
            
            if ($current_user === $pagador) return $viajero;
            if ($current_user === $viajero) return $pagador;
            return $viajero; // Invitados ven al viajero
        }
    }

    if (!empty($conv['canonical_title'])) return $conv['canonical_title'];
    
    $participants = $conv['participants'] ?? [];
    $others = array_diff($participants, [$current_user]);
    return reset($others) ?: 'Chat';
}

// =========================
// Selección de conversación (SAFE)
// =========================
$conversation_id = null;

if (!empty($_GET['username']) && $_GET['username'] !== $current_user) {
    $other = htmlspecialchars($_GET['username']);

    // 1) Buscar conversación directa existente QUE NO SEA GRUPO
    $st = $conexion->prepare("
        SELECT c.conversation_id
        FROM chat_conversations c
        JOIN chat_participants p1 ON p1.conversation_id = c.conversation_id AND p1.username = :me
        JOIN chat_participants p2 ON p2.conversation_id = c.conversation_id AND p2.username = :other
        WHERE COALESCE(c.is_group,0) = 0
          AND (SELECT COUNT(*) FROM chat_participants cp WHERE cp.conversation_id = c.conversation_id) = 2
        LIMIT 1
    ");
    $st->execute([':me'=>$current_user, ':other'=>$other]);
    $conversation_id = $st->fetchColumn();

    // 2) Crear solo si no existe UN CHAT DIRECTO (no grupo)
    if (!$conversation_id) {
        $conversation_id = $chatManager->createDirectConversation($current_user, $other);
    }
}

if (!empty($_GET['conversation_id'])) {
    $conversation_id = (int)$_GET['conversation_id'];
}

/* =========================
   Usuario actual (ID)
   ========================= */
$stmt_get_user_id = $conexion->prepare("SELECT id FROM accounts WHERE username = ? LIMIT 1");
$stmt_get_user_id->execute([$current_user]);
$user_row = $stmt_get_user_id->fetch(PDO::FETCH_ASSOC);
$usuario_actual_id = $user_row ? (int)$user_row['id'] : null;

/* =======================================================
   ✅ OBTENER CONVERSACIONES - CON INTEGRACIÓN SHOP
   ======================================================= */
$conversations = [];

if ($usuario_actual_id) {
    // 1️⃣ OBTENER CONVERSACIONES EXISTENTES DEL USUARIO
    $query_conversaciones_usuario = "
        SELECT DISTINCT cp.conversation_id, cc.title, cc.is_group, cc.canonical_title, 
               cc.conversation_status, cc.ready_at
        FROM chat_participants cp
        INNER JOIN chat_conversations cc ON cp.conversation_id = cc.conversation_id
        WHERE cp.username = :username
    ";
    $stmt_conv = $conexion->prepare($query_conversaciones_usuario);
    $stmt_conv->bindValue(":username", $current_user);
    $stmt_conv->execute();
    $conversaciones_usuario = $stmt_conv->fetchAll(PDO::FETCH_ASSOC);

    // 2️⃣ OBTENER USUARIOS CON SOLICITUDES EN listas_compras
    $query_usuarios_listas = "
        SELECT DISTINCT a.id, a.username
        FROM accounts a
        INNER JOIN listas_compras lc ON (
            (lc.id_usuario = :usuario_actual_id AND lc.id_transporting = a.id)
            OR (lc.id_transporting = :usuario_actual_id AND lc.id_usuario = a.id)
        )
        WHERE a.id != :usuario_actual_id
    ";
    $stmt_listas = $conexion->prepare($query_usuarios_listas);
    $stmt_listas->bindValue(":usuario_actual_id", $usuario_actual_id, PDO::PARAM_INT);
    $stmt_listas->execute();
    $usuarios_con_listas = $stmt_listas->fetchAll(PDO::FETCH_ASSOC);
    $usuarios_permitidos_listas = array_column($usuarios_con_listas, 'username');

    // 🔥 3️⃣ OBTENER USUARIOS CON PAGOS EN SHOP
    $query_usuarios_shop = "
        SELECT DISTINCT a.id, a.username
        FROM accounts a
        INNER JOIN shop_payments sp ON (
            (sp.payer_id = :usuario_actual_id AND sp.receiver_id = a.id)
            OR (sp.receiver_id = :usuario_actual_id AND sp.payer_id = a.id)
        )
        WHERE a.id != :usuario_actual_id
    ";
    $stmt_shop = $conexion->prepare($query_usuarios_shop);
    $stmt_shop->bindValue(":usuario_actual_id", $usuario_actual_id, PDO::PARAM_INT);
    $stmt_shop->execute();
    $usuarios_con_shop = $stmt_shop->fetchAll(PDO::FETCH_ASSOC);
    $usuarios_permitidos_shop = array_column($usuarios_con_shop, 'username');

    // 🔥 COMBINAR AMBAS LISTAS
    $usuarios_permitidos = array_unique(array_merge($usuarios_permitidos_listas, $usuarios_permitidos_shop));

    // 4️⃣ PROCESAR CONVERSACIONES EXISTENTES
    $all_conversations = $chatManager->getUserConversations($current_user);
    $conversation_ids_validos = array_column($conversaciones_usuario, 'conversation_id');

    foreach ($all_conversations as $conv) {
        $es_grupo = isGroup($conv);
        
        if ($es_grupo) {
            if (in_array($conv['conversation_id'], $conversation_ids_validos)) {
                $conversations[] = $conv;
            }
        } else {
            $otros_participantes = array_diff($conv['participants'] ?? [], [$current_user]);
            if (!empty($otros_participantes)) {
                $otro_usuario = reset($otros_participantes);
                if (in_array($otro_usuario, $usuarios_permitidos)) {
                    $conversations[] = $conv;
                }
            }
        }
    }

    // 5️⃣ CREAR CONVERSACIONES FALTANTES
    if (!empty($usuarios_permitidos)) {
        foreach ($usuarios_permitidos as $username_permitido) {
            $existe = false;
            foreach ($conversations as $conv_existente) {
                if (!isGroup($conv_existente)) {
                    $otros = array_diff($conv_existente['participants'] ?? [], [$current_user]);
                    if (in_array($username_permitido, $otros)) {
                        $existe = true;
                        break;
                    }
                }
            }
            
            if (!$existe) {
                $new_conv_id = $chatManager->createDirectConversation($current_user, $username_permitido);
                
                if ($new_conv_id) {
                    $all_updated = $chatManager->getUserConversations($current_user);
                    foreach ($all_updated as $conv_check) {
                        if ($conv_check['conversation_id'] == $new_conv_id) {
                            $conversations[] = $conv_check;
                            break;
                        }
                    }
                }
            }
        }
    }
}

// Ordenar conversaciones por última actividad
usort($conversations, function($a, $b) {
    $tA = strtotime($a['last_message_time'] ?? '1970-01-01');
    $tB = strtotime($b['last_message_time'] ?? '1970-01-01');
    return $tB - $tA;
});

/* =========================
   Invitaciones pendientes
   ========================= */
$invitations = $chatManager->getPendingInvitations($current_user);

/* =========================
   Conversación actual + estado READY/LOCKED
   ========================= */
$current_conv = null;
$messages = [];
$convStatus = 'LOCKED';
$convReadyAt = null;

if ($conversation_id) {
    $puede_acceder = false;
    $stmt_check = $conexion->prepare("
        SELECT 1 FROM chat_participants 
        WHERE conversation_id = :conversation_id AND username = :username LIMIT 1
    ");
    $stmt_check->bindValue(':conversation_id', $conversation_id, PDO::PARAM_INT);
    $stmt_check->bindValue(':username', $current_user);
    $stmt_check->execute();
    if ($stmt_check->fetch()) {
        $puede_acceder = true;
    } else {
        foreach ($conversations as $c) {
            if ($c['conversation_id'] == $conversation_id) {
                $puede_acceder = true;
                break;
            }
        }
    }

    if ($puede_acceder) {
        $all_convs = $chatManager->getUserConversations($current_user);
        foreach ($all_convs as $c) {
            if ($c['conversation_id'] == $conversation_id) {
                $current_conv = $c;
                break;
            }
        }

        // Traer estado y ready_at
        $st = $conexion->prepare("SELECT conversation_status, ready_at, canonical_title FROM chat_conversations WHERE conversation_id=? LIMIT 1");
        $st->execute([$conversation_id]);
        if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $convStatus = $row['conversation_status'] ?? 'LOCKED';
            $convReadyAt = $row['ready_at'] ?? null;
            $current_conv['conversation_status'] = $convStatus;
            $current_conv['ready_at'] = $convReadyAt;
            $current_conv['canonical_title'] = $row['canonical_title'] ?? ($current_conv['canonical_title'] ?? null);
        }

        if ($current_conv) {
            try {
                $stmt_mark_read = $conexion->prepare("
                    UPDATE chat_messages
                    SET is_read = 1
                    WHERE conversation_id = :conversation_id
                      AND sender_username <> :current_user
                ");
                $stmt_mark_read->bindValue(':conversation_id', $conversation_id, PDO::PARAM_INT);
                $stmt_mark_read->bindValue(':current_user', $current_user);
                $stmt_mark_read->execute();
            } catch (Exception $e) { /* opcional */ }

            $chatManager->markAsRead($conversation_id, $current_user);
        }
    } else {
        $conversation_id = null;
        $current_conv = null;
        echo "<script>window.addEventListener('DOMContentLoaded', function() { showNotification('No tienes acceso a esta conversación', 'error'); });</script>";
    }
}

$isReady = ($convStatus === 'READY');

/* =========================
   🔄 SINCRONIZACIÓN CON LISTAS_COMPRAS
   ========================= */
$codigo_unico_viaje = null;

if (!isGroup($current_conv ?? []) && $usuario_actual_id && $conversation_id) {
    try {
        // 1. Obtener los participantes originales (no invitados)
        $stmt = $conexion->prepare("
            SELECT username FROM chat_participants 
            WHERE conversation_id = ? 
              AND invited_by_link = 0
            ORDER BY participant_id ASC
            LIMIT 2
        ");
        $stmt->execute([$conversation_id]);
        $participantes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Identificar quién es el otro usuario
        $otro_username = null;
        foreach ($participantes as $p) {
            if ($p !== $current_user) {
                $otro_username = $p;
                break;
            }
        }
        
        if ($otro_username) {
            // 2. Obtener ID del otro usuario
            $stmt = $conexion->prepare("SELECT id FROM accounts WHERE username = ? LIMIT 1");
            $stmt->execute([$otro_username]);
            $otro_id = $stmt->fetchColumn();
            
            if ($otro_id) {
                // 3. Buscar la lista más reciente entre estos usuarios
                $stmt = $conexion->prepare("
                    SELECT id, estado, fecha_creacion, codigo_unico
                    FROM listas_compras
                    WHERE ((id_usuario = ? AND id_transporting = ?)
                        OR (id_usuario = ? AND id_transporting = ?))
                    ORDER BY fecha_creacion DESC
                    LIMIT 1
                ");
                $stmt->execute([$usuario_actual_id, $otro_id, $otro_id, $usuario_actual_id]);
                $lista = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($lista) {
                    $codigo_unico_viaje = $lista['codigo_unico'];
                    
                    $estado_lista = $lista['estado'];
                    $nuevo_status = null;
                    
                    // 4. Determinar el estado correcto del chat
                    if ($estado_lista === 'aceptado') {
                        $nuevo_status = 'READY';
                    } elseif ($estado_lista === 'pendiente' || $estado_lista === 'rechazado') {
                        $nuevo_status = 'LOCKED';
                    }
                    
                    // 5. Sincronizar si es diferente
                    if ($nuevo_status && $convStatus !== $nuevo_status) {
                        $stmt = $conexion->prepare("
                            UPDATE chat_conversations 
                            SET conversation_status = ?, 
                                ready_at = CASE WHEN ? = 'READY' THEN NOW() ELSE ready_at END
                            WHERE conversation_id = ?
                        ");
                        $stmt->execute([$nuevo_status, $nuevo_status, $conversation_id]);
                        
                        $convStatus = $nuevo_status;
                        
                        if ($nuevo_status === 'READY') {
                            $stmt = $conexion->prepare("
                                INSERT INTO chat_messages 
                                (conversation_id, sender_username, message_text, sent_at, is_read)
                                VALUES (?, 'Sistema', ?, NOW(), 0)
                            ");
                            $mensaje = "✅ El viajero ha aceptado los artículos. Ya pueden coordinar la entrega.";
                            $stmt->execute([$conversation_id, $mensaje]);
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error en sincronización listas_compras: " . $e->getMessage());
    }
}

/* =========================
   🔄 SINCRONIZACIÓN CON SHOP_PAYMENTS
   ========================= */
if (!isGroup($current_conv ?? []) && $usuario_actual_id && $conversation_id) {
    try {
        // Obtener participantes originales
        $stmt = $conexion->prepare("
            SELECT username FROM chat_participants 
            WHERE conversation_id = ? 
              AND invited_by_link = 0
            ORDER BY participant_id ASC
            LIMIT 2
        ");
        $stmt->execute([$conversation_id]);
        $participantes_shop = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $otro_username_shop = null;
        foreach ($participantes_shop as $p) {
            if ($p !== $current_user) {
                $otro_username_shop = $p;
                break;
            }
        }
        
        if ($otro_username_shop) {
            $stmt = $conexion->prepare("SELECT id FROM accounts WHERE username = ? LIMIT 1");
            $stmt->execute([$otro_username_shop]);
            $otro_id_shop = $stmt->fetchColumn();
            
            if ($otro_id_shop) {
                // Verificar si hay pago completado en shop_payments
                $stmt = $conexion->prepare("
                    SELECT COUNT(*) 
                    FROM shop_payments 
                    WHERE ((payer_id = ? AND receiver_id = ?)
                        OR (payer_id = ? AND receiver_id = ?))
                      AND payment_status = 'completed'
                ");
                $stmt->execute([$usuario_actual_id, $otro_id_shop, $otro_id_shop, $usuario_actual_id]);
                $tiene_pago_shop = (int)$stmt->fetchColumn() > 0;
                
                if ($tiene_pago_shop && $convStatus !== 'READY') {
                    $stmt = $conexion->prepare("
                        UPDATE chat_conversations 
                        SET conversation_status = 'READY', 
                            ready_at = NOW(),
                            updated_at = NOW()
                        WHERE conversation_id = ?
                    ");
                    $stmt->execute([$conversation_id]);
                    
                    $convStatus = 'READY';
                    
                    // Mensaje del sistema
                    $stmt = $conexion->prepare("
                        INSERT INTO chat_messages 
                        (conversation_id, sender_username, message_text, sent_at, is_read)
                        VALUES (?, 'Sistema', ?, NOW(), 0)
                    ");
                    $mensaje_shop = "✅ Pago confirmado. Ya pueden coordinar la entrega del producto.";
                    $stmt->execute([$conversation_id, $mensaje_shop]);
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error en sincronización shop_payments: " . $e->getMessage());
    }
}

// Recalcular $isReady después de sincronizaciones
$isReady = ($convStatus === 'READY');

/* =========================
   🎯 DETERMINAR ROL DEL USUARIO (SOLICITANTE vs ACEPTADOR)
   ========================= */
$canInvite = false;
$userRole = null;

if ($current_conv && !isGroup($current_conv) && $usuario_actual_id && $conversation_id) {
    // Obtener los participantes originales (no invitados)
    $stmt_original = $conexion->prepare("
        SELECT username, is_admin 
        FROM chat_participants 
        WHERE conversation_id = :conv_id 
          AND invited_by_link = 0
        ORDER BY participant_id ASC
        LIMIT 2
    ");
    $stmt_original->execute([':conv_id' => $conversation_id]);
    $original_participants = $stmt_original->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($original_participants) === 2) {
        $primero = $original_participants[0]['username'];
        $segundo = $original_participants[1]['username'];
        
        // 🔍 DETERMINAR ROLES BASÁNDOSE EN listas_compras O shop_payments
        $stmt_quien = $conexion->prepare("
            SELECT 
                a1.username as usuario_username,
                a2.username as transporting_username,
                'listas_compras' as source
            FROM listas_compras lc
            INNER JOIN accounts a1 ON lc.id_usuario = a1.id
            INNER JOIN accounts a2 ON lc.id_transporting = a2.id
            WHERE (a1.username IN (:primero, :segundo) AND a2.username IN (:primero, :segundo))
            ORDER BY lc.fecha_creacion DESC
            LIMIT 1
        ");
        $stmt_quien->execute([':primero' => $primero, ':segundo' => $segundo]);
        $lista_info = $stmt_quien->fetch(PDO::FETCH_ASSOC);
        
        if (!$lista_info) {
            // Si no hay listas, buscar en shop_payments
            $stmt_shop_quien = $conexion->prepare("
                SELECT 
                    a1.username as usuario_username,
                    a2.username as transporting_username,
                    'shop_payments' as source
                FROM shop_payments sp
                INNER JOIN accounts a1 ON sp.payer_id = a1.id
                INNER JOIN accounts a2 ON sp.receiver_id = a2.id
                WHERE (a1.username IN (:primero, :segundo) AND a2.username IN (:primero, :segundo))
                ORDER BY sp.created_at DESC
                LIMIT 1
            ");
            $stmt_shop_quien->execute([':primero' => $primero, ':segundo' => $segundo]);
            $lista_info = $stmt_shop_quien->fetch(PDO::FETCH_ASSOC);
        }
        
        if ($lista_info) {
            $remitente = $lista_info['usuario_username'];
            $viajero = $lista_info['transporting_username'];
            $context_source = $lista_info['source'] ?? 'listas_compras'; // 'listas_compras' o 'shop_payments'

            if ($current_user === $remitente) {
                $userRole = 'solicitante';
                $canInvite = true;
            } elseif ($current_user === $viajero) {
                $userRole = 'aceptador';
                $canInvite = false;
            } else {
                $userRole = 'invitado';
                $canInvite = false;
            }
        } else {
            $context_source = 'listas_compras'; // Default
            // Fallback si no hay registros
            $solicitante = $original_participants[1]['username'];
            $aceptador = $original_participants[0]['username'];
            
            if ($current_user === $solicitante) {
                $userRole = 'solicitante';
                $canInvite = true;
            } elseif ($current_user === $aceptador) {
                $userRole = 'aceptador';
                $canInvite = false;
            } else {
                $userRole = 'invitado';
                $canInvite = false;
            }
        }
    }
}

// 🎯 OBTENER IDs PARA SHOP (si es contexto shop_payments)
$shop_request_id = null;
$shop_delivery_id = null;

if (!empty($context_source) && $context_source === 'shop_payments' && $usuario_actual_id && !empty($primero) && !empty($segundo)) {
    try {
        // Obtener el delivery más reciente entre estos usuarios
        $stmt_delivery = $conexion->prepare("
            SELECT
                sd.id as delivery_id,
                sd.proposal_id,
                srp.request_id
            FROM shop_deliveries sd
            INNER JOIN shop_request_proposals srp ON sd.proposal_id = srp.id
            INNER JOIN accounts a1 ON sd.requester_id = a1.id
            INNER JOIN accounts a2 ON sd.traveler_id = a2.id
            WHERE (a1.username IN (:primero, :segundo) AND a2.username IN (:primero, :segundo))
            ORDER BY sd.created_at DESC
            LIMIT 1
        ");
        $stmt_delivery->execute([':primero' => $primero, ':segundo' => $segundo]);
        $delivery_info = $stmt_delivery->fetch(PDO::FETCH_ASSOC);

        if ($delivery_info) {
            $shop_delivery_id = $delivery_info['delivery_id'];
            $shop_request_id = $delivery_info['request_id'];
        }
    } catch (Exception $e) {
        error_log("Error obteniendo shop delivery: " . $e->getMessage());
    }
}

// Preparar URLs de fotos de perfil para participantes
$participantPhotos = [];
if ($current_conv && !empty($current_conv['participants'])) {
    foreach ($current_conv['participants'] as $pUsername) {
        $participantPhotos[$pUsername] = getProfileImageUrlByUsername($conexion, $pUsername);
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
  <title>Chat - Sendvialo</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="css/chatpage.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
  <link rel="icon" href="Imagenes/globo5.png" type="image/png">

  <script type="module">
    import 'https://cdn.jsdelivr.net/npm/emoji-picker-element@^1/index.js';
  </script>
<style>
/* ====== Light Mode – ajustes globales para este bloque ====== */
:root { --input-bar-height: 64px; } /* altura de la barra inferior */

/* ===== Estados de mensaje ===== */
.message-status.delivered { color: #9ca3af; }
.message-status.partially-read { color: #d97706; }
.message-status.read { color: #2563eb; }
.message-status[title]:hover::after{
  content: attr(title);
  position: absolute; bottom: 100%; right: 0;
  background: rgba(17,24,39,.92); color: #fff;
  padding: 4px 8px; border-radius: 6px; font-size: 11px;
  white-space: nowrap; z-index: 1000; transform: translateY(-2px);
}

/* Insignias / estados extra */
.conversation-item.has-payment{
  border-left: 3px solid #22c55e;
  background: linear-gradient(90deg, rgba(34,197,94,.08) 0%, transparent 100%);
}
.unread-badge{ background:#22c55e; color:#fff; border-radius:12px; padding:1px 6px; font-size:11px; display:inline-block; }
@media (max-width:768px){
  .invite-banner-mobile, .viajero-banner-mobile{ display:block; position: sticky; bottom: 64px; z-index: 100; }
}
/* ===== Avatares ===== */
.conversation-avatar{
  width:48px; height:48px; border-radius:50%; overflow:hidden; flex-shrink:0;
  position:relative; background:#e5e7eb; display:flex; align-items:center; justify-content:center;
}
.conversation-avatar img{ width:100%; height:100%; object-fit:cover; border-radius:50%; }
.conversation-avatar.group{ background: linear-gradient(135deg, #93c5fd 0%, #a78bfa 100%); color:#fff; font-size:18px; }
.header-avatar{ width:40px; height:40px; border-radius:50%; object-fit:cover; flex-shrink:0; cursor:pointer; transition:transform .2s; }
.header-avatar:hover{ transform:scale(1.05); }
.message-avatar{ width:32px; height:32px; border-radius:50%; object-fit:cover; margin-right:8px; flex-shrink:0; cursor:pointer; transition:transform .2s; }
.message-avatar:hover{ transform:scale(1.1); }
.system-avatar{
  width:32px; height:32px; border-radius:50%;
  background: linear-gradient(135deg, #a855f7 0%, #7c3aed 100%);
  display:flex; align-items:center; justify-content:center; color:white; margin-right:8px; flex-shrink:0;
}
.participant-avatar-img{ width:40px; height:40px; border-radius:50%; object-fit:cover; margin-right:12px; }
img[class*="avatar"]{ border-radius:50% !important; object-fit:cover !important; }

/* ===== Emoji picker ===== */
.emoji-picker-container{
  position:absolute; bottom:100%; left:0; margin-bottom:10px; opacity:0; visibility:hidden;
  transform:translateY(10px); transition: all .3s cubic-bezier(.4,0,.2,1); z-index:1000;
  box-shadow: 0 8px 24px rgba(0,0,0,.12); border-radius:12px; overflow:hidden; background:#fff;
}
.emoji-picker-container.show{ opacity:1; visibility:visible; transform:translateY(0); }
emoji-picker{
  --border-radius: 12px; --border-color:#e5e7eb; --button-hover-background: rgba(0,168,132,.1);
  --button-active-background: rgba(0,168,132,.2); --indicator-color:#00a884; --input-border-color:#e5e7eb;
  --input-font-color:#111827; --input-placeholder-color:#9ca3af; --outline-color:#00a884;
  width:350px; height:400px; font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;
}

/* ===== Mensajes (contenedor y burbujas) ===== */
.messages-container{
  background: #f6f8fb;
  background-image:
    radial-gradient(circle at 20% 50%, rgba(0,168,132,.06) 0%, transparent 45%),
    radial-gradient(circle at 80% 80%, rgba(34,197,94,.06) 0%, transparent 45%);
  padding:20px; overflow-y:auto; flex:1;
  /* Espacio para input bar + banner móvil + margen extra */
  padding-bottom: calc(var(--input-bar-height) + 60px + env(safe-area-inset-bottom, 0px));
}

.message{ margin-bottom:20px; max-width:70%; animation: messageSlideIn .3s ease-out; }
@keyframes messageSlideIn{ from{opacity:0; transform:translateY(10px);} to{opacity:1; transform:translateY(0);} }
.message.sent{ margin-left:auto; text-align:right; }
.message.received{ margin-right:auto; text-align:left; }

/* Burbujas */
.message.sent .message-content{
  background: linear-gradient(135deg, #00c2a0 0%, #00a884 100%);
  color:#fff; border-radius:18px 18px 4px 18px; padding:12px 16px;
  box-shadow: 0 6px 16px rgba(0,168,132,.22);
}
.message.received .message-content{
  background:#ffffff; color:#111827; border:1px solid #e5e7eb;
  border-radius:18px 18px 18px 4px; padding:12px 16px;
  box-shadow: 0 4px 12px rgba(15,23,42,.06);
}

/* Tiempos */
.message-time{
  font-size:11px; margin-top:4px; color:#6b7280; display:flex; align-items:center; gap:4px;
}
.message.sent .message-time{ justify-content:flex-end; color:rgba(255,255,255,.85); }
.message.received .message-time{ justify-content:flex-start; color:#6b7280; }

/* ===== Indicadores ===== */
.payment-indicator{
  position:absolute; bottom:-2px; right:-2px; width:18px; height:18px;
  background: linear-gradient(135deg, #22c55e 0%, #10b981 100%);
  border-radius:50%; display:flex; align-items:center; justify-content:center; color:white;
  font-size:10px; border:2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,.12);
}
.guest-indicator{
  display:inline-flex; align-items:center; justify-content:center;
  background: rgba(0,168,132,.12); color:#00a884; font-size:11px; font-weight:600;
  padding:2px 6px; border-radius:10px; margin-left:6px; transition: all .2s;
}
.guest-indicator:hover{ background: rgba(0,168,132,.2); transform: scale(1.05); }

/* ===== Lista de conversaciones ===== */
.conversation-item{
  display:flex; align-items:center; padding:12px; gap:12px; cursor:pointer; transition: all .2s;
  border-bottom:1px solid #e5e7eb; background:#fff;
}
.conversation-item:hover{ background:#f3f6fb; }
.conversation-item.active{ background:#e9f8f3; border-left:3px solid #00a884; }
.conversation-content{ flex:1; min-width:0; overflow:hidden; }
.conversation-title{ display:flex; align-items:center; gap:4px; margin-bottom:4px; }
.conversation-name{ font-weight:600; color:#111827; font-size:14px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.conversation-last{ font-size:13px; color:#6b7280; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.conversation-meta{ display:flex; flex-direction:column; align-items:flex-end; gap:4px; }
.conv-time{ font-size:11px; color:#9ca3af; }
.message-header-info{ display:flex; align-items:center; gap:8px; margin-bottom:4px; }
.message-sender-name{ font-weight:600; font-size:13px; color:#0f172a; }

/* ===== Nombres truncables ===== */
.chat-title, .conversation-name{ display:inline-block; max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

/* ====================================================================== */
/* BARRA DE MENSAJE FIJA (clara) + sin franjas inferiores                 */
/* ====================================================================== */
.message-form{
  padding: 10px 12px !important;
  background: #ffffff !important;
  border-top: 1px solid #e5e7eb !important;
  position: fixed !important;
  bottom: 0 !important; left: 0 !important; right: 0 !important;
  width: 100% !important; height: var(--input-bar-height) !important;
  z-index: 10000 !important; box-sizing: border-box !important;
  transform: none !important; margin: 0 !important;
  box-shadow: 0 -6px 24px rgba(15,23,42,.06) !important;
}

body::after{ content: none !important; }

.message-form form > div{
  display:flex !important; gap:8px !important; align-items:center !important;
  width:100% !important; flex-direction:row !important;
}

/* Campo de texto (claro) */
.message-input-horizontal{
  flex:1 !important; border:none !important; outline:none !important; resize:none !important;
  font-size:15px !important; padding:10px 14px !important; max-height:80px !important; overflow-y:auto !important;
  font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif !important; line-height:1.4 !important;
  background:#eef2f6 !important; color:#0f172a !important; border-radius:22px !important; transition: all .2s !important;
  border:1px solid #e6ebf2 !important;
}
.message-input-horizontal:focus{
  background:#f5f7fb !important; box-shadow: 0 0 0 2px rgba(0,168,132,.28) !important; border-color:#bfe7dd !important;
}
.message-input-horizontal:disabled{ background:#f1f5f9 !important; cursor:not-allowed !important; opacity:.7 !important; }
.message-input-horizontal::placeholder{ color:#6b7280 !important; }

/* Botones del input */
.input-btn{
  background:none !important; border:none !important; color:#64748b !important;
  font-size:20px !important; cursor:pointer !important; padding:8px !important; border-radius:50% !important; transition: all .2s !important;
  display:flex !important; align-items:center !important; justify-content:center !important; width:38px !important; height:38px !important; flex-shrink:0 !important;
}
.input-btn:hover:not(:disabled){ background:#e9eef5 !important; color:#0f172a !important; transform:scale(1.1) !important; }
.input-btn:active:not(:disabled){ transform:scale(.95) !important; }
.input-btn:disabled{ opacity:.4 !important; cursor:not-allowed !important; }

/* Botón enviar */
.send-btn-horizontal,
.send-btn,
#sendBtn{
  background:#00a884 !important; color:#fff !important; border:none !important; border-radius:50% !important;
  width:42px !important; height:42px !important; display:flex !important; align-items:center !important; justify-content:center !important;
  cursor:pointer !important; transition: all .2s !important; box-shadow: 0 6px 16px rgba(0,168,132,.25) !important; flex-shrink:0 !important;
}
.send-btn-horizontal:hover:not(:disabled),
.send-btn:hover:not(:disabled),
#sendBtn:hover:not(:disabled){
  transform:scale(1.08) !important; box-shadow: 0 10px 20px rgba(0,168,132,.32) !important;
}
.send-btn-horizontal:active:not(:disabled),
.send-btn:active:not(:disabled),
#sendBtn:active:not(:disabled){ transform:scale(.95) !important; }
.send-btn-horizontal:disabled,
.send-btn:disabled,
#sendBtn:disabled{ background:#cbd5e1 !important; box-shadow:none !important; cursor:not-allowed !important; }
.send-btn-horizontal i{ font-size:18px !important; }

@media (max-width:768px){
  .message{ max-width:85% !important; }
  .messages-container{
    padding: 16px !important;
    /* CRÍTICO: Espacio para input + banner + margen de seguridad */
    padding-bottom: calc(var(--input-bar-height) + 70px + env(safe-area-inset-bottom, 0px)) !important;
  }
}

/* ===== Mensaje de chat bloqueado (centrado y claro) ===== */
.chat-locked-message {
  text-align: center;
  background: #f1f5f9;
  border: 1px solid #dbe2eb;
  color: #334155;
  padding: 14px 18px;
  font-size: 14px;
  border-radius: 12px;
  margin: 16px auto;
  max-width: 80%;
  box-shadow: 0 4px 12px rgba(0,0,0,.05);
}

.message-sender-name {
  color: #0f172a !important;
  font-weight: 600 !important;
}


/* Ocultar por defecto en desktop */
.mobile-bottom-nav {
    display: none !important;
}

/* Mostrar solo en tablets (hasta 992px) y móviles */
@media (max-width: 992px) {
    .mobile-bottom-nav {
        display: flex !important;
    }
}

/* Asegurar que no se muestre en desktop cuando hay conversation_id */
@media (min-width: 993px) {
    .mobile-bottom-nav {
        display: none !important;
    }
}
</style>

</head>
<body>

<?php include 'header.php'; ?>

<div class="chat-container <?php echo $conversation_id ? 'has-conversation' : ''; ?>">
  <!-- LISTA DE CONVERSACIONES -->
  <div class="conversation-list">
    <div class="conv-header">
      <div style="display:flex; align-items:center; gap:12px;">
        <a href="index.php" style="display:flex; align-items:center; text-decoration:none;">
          <img src="Imagenes/back_arrow.png" alt="Logo" style="width:35px; height:35px; border-radius:50%; transition: transform .2s;">
        </a>
        <h4>Chats</h4>
      </div>
    </div>

    <div class="conv-search">
      <input id="searchConvos" class="form-control" placeholder="Buscar conversaciones...">
    </div>

    <?php if($invitations): ?>
      <div class="invitation-list">
        <h6 class="mb-2">Invitaciones pendientes</h6>
        <?php foreach($invitations as $inv): ?>
          <div class="d-flex justify-content-between align-items-center mb-2">
            <small>
              <strong style="color:#00d4aa;"><?= htmlspecialchars($inv['inviter_username']) ?></strong> te invitó
            </small>
            <div>
              <form class="d-inline" action="chat_actions.php" method="post">
                <input type="hidden" name="action" value="respond_invitation">
                <input type="hidden" name="invitation_id" value="<?=$inv['invitation_id']?>">
                <input type="hidden" name="status" value="accepted">
                <button class="btn btn-xs btn-success">✓</button>
              </form>
              <form class="d-inline" action="chat_actions.php" method="post">
                <input type="hidden" name="action" value="respond_invitation">
                <input type="hidden" name="invitation_id" value="<?=$inv['invitation_id']?>">
                <input type="hidden" name="status" value="rejected">
                <button class="btn btn-xs btn-danger">✗</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="conv-list-scroll">
<?php foreach($conversations as $c):
    $grp = isGroup($c);
    $name = getOriginalChatTitle($c, $current_user, $conexion);
    $avatarUrl = '';
    $otherUserForLink = null;

    if (!$grp) {
        $otherUserForLink = $name;
        $avatarUrl = getProfileImageUrlByUsername($conexion, $otherUserForLink);
    }

    $hasPayment = false;
    if (!$grp && $usuario_actual_id && $otherUserForLink) {
        $check_payment = $conexion->prepare("
            SELECT 1 FROM accounts a WHERE a.username = :username AND (
                EXISTS (
                    SELECT 1 FROM paypal_payments pp 
                    WHERE (pp.id_sender = :usuario_actual_id AND pp.id_transporting = a.id 
                           AND pp.payment_status IN ('COMPLETED', 'EN_CUSTODIA', 'LIBERADO_AUTO', 'LIBERADO_MANUAL'))
                       OR (pp.id_transporting = :usuario_actual_id AND pp.id_sender = a.id 
                           AND pp.payment_status IN ('COMPLETED', 'EN_CUSTODIA', 'LIBERADO_AUTO', 'LIBERADO_MANUAL'))
                )
                OR EXISTS (
                    SELECT 1 FROM google_pay_payments gp 
                    WHERE (gp.id_sender = :usuario_actual_id AND gp.id_transporting = a.id 
                           AND (gp.payment_status IN ('COMPLETED', 'EN_CUSTODIA', 'LIBERADO_AUTO', 'LIBERADO_MANUAL') OR gp.payment_status = ''))
                       OR (gp.id_transporting = :usuario_actual_id AND gp.id_sender = a.id 
                           AND (gp.payment_status IN ('COMPLETED', 'EN_CUSTODIA', 'LIBERADO_AUTO', 'LIBERADO_MANUAL') OR gp.payment_status = ''))
                )
                OR EXISTS (
                    SELECT 1 FROM cards_payment cp 
                    WHERE (cp.id_sender = :usuario_actual_id AND cp.id_transporting = a.id 
                           AND (cp.payment_status IN ('LIBERADO', 'EN_CUSTODIA') OR cp.payment_status = ''))
                       OR (cp.id_transporting = :usuario_actual_id AND cp.id_sender = a.id 
                           AND (cp.payment_status IN ('LIBERADO', 'EN_CUSTODIA') OR cp.payment_status = ''))
                )
                OR EXISTS (
                    SELECT 1 FROM shop_payments sp 
                    WHERE (sp.payer_id = :usuario_actual_id AND sp.receiver_id = a.id 
                           AND sp.payment_status = 'completed')
                       OR (sp.receiver_id = :usuario_actual_id AND sp.payer_id = a.id 
                           AND sp.payment_status = 'completed')
                )
            ) LIMIT 1
        ");
        $check_payment->bindValue(':username', $otherUserForLink);
        $check_payment->bindValue(':usuario_actual_id', $usuario_actual_id, PDO::PARAM_INT);
        $check_payment->execute();
        $hasPayment = $check_payment->fetch() !== false;
    }
  ?>
        <div class="conversation-item <?= $c['conversation_id']==$conversation_id?'active':'' ?> <?= $hasPayment?'has-payment':'' ?>"
             onclick="location.search='?conversation_id=<?=$c['conversation_id']?>'">
          <div class="conversation-avatar <?= $grp?'group':'' ?>" onclick="event.stopPropagation();">
            <?php if($grp): ?>
              <span style="font-size:18px;">👥</span>
            <?php else: ?>
              <a href="perfil_publico.php?username=<?= urlencode($otherUserForLink) ?>" style="text-decoration: none; display: inline-block;">
                <img src="<?= htmlspecialchars($avatarUrl) ?>" 
                     alt="Avatar de <?= htmlspecialchars($name) ?>" 
                     onerror="this.src='Imagenes/user-default.jpg'"
                     style="cursor: pointer; transition: transform 0.3s ease;">
              </a>
            <?php endif; ?>
            <?php if($hasPayment && !$grp): ?>
              <div class="payment-indicator" title="Usuario con pago completado"><i class="bi bi-check"></i></div>
            <?php endif; ?>
          </div>
          
         <div class="conversation-content">
  <div class="conversation-title">
    <span class="conversation-name" style="cursor: default;"><?= htmlspecialchars($name) ?></span>
    <?php if($hasPayment && !$grp): ?>
      <i class="bi bi-shield-fill-check" style="color:#28a745; font-size:12px; margin-left:4px;" title="Pago verificado"></i>
    <?php endif; ?>
    <?php 
    $participantCount = count($c['participants'] ?? []);
    if (!$grp && $participantCount > 2): 
      $extraCount = $participantCount - 2;
    ?>
      <span class="guest-indicator">+<?= $extraCount ?></span>
    <?php endif; ?>
  </div>
  <div class="conversation-last">
    <?= $c['last_message'] ? (($c['last_sender']===$current_user?'Tú: ':'').htmlspecialchars($c['last_message'])) : 'Sin mensajes' ?>
  </div>
</div>
          
          <div class="conversation-meta">
            <?php if(!empty($c['last_message_time'])): ?>
              <div class="conv-time"><?=date('H:i', strtotime($c['last_message_time']))?></div>
            <?php endif; ?>
            <?php if(!empty($c['unread_count'])): ?>
              <div class="unread-badge" id="unread-<?=$c['conversation_id']?>"><?=$c['unread_count']?></div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

<!-- VENTANA DE CHAT -->
<div class="chat-window">
  <?php if($current_conv): ?>
    <?php
    $isGrp = isGroup($current_conv);
    $chatTitle = getOriginalChatTitle($current_conv, $current_user, $conexion);
    $headerAvatarUrl = '';
    $otherUsername = null;
    
    if (!$isGrp) {
      $otherUsername = $chatTitle;
      $headerAvatarUrl = getProfileImageUrlByUsername($conexion, $otherUsername);
    }
    ?>
    
    <!-- HEADER DEL CHAT -->
    <div class="chat-header">
      <?php if($conversation_id): ?>
        <button class="back-btn d-md-none" onclick="location.href='chatpage.php'">
          <i class="bi bi-arrow-left"></i>
        </button>
      <?php endif; ?>
      
      <div style="display:flex; align-items:center; gap:10px; flex:1;">
        <?php if($isGrp): ?>
          <div class="header-avatar" style="display:flex; align-items:center; justify-content:center; background:#eef;">👥</div>
        <?php else: ?>
          <a href="perfil_publico.php?username=<?= urlencode($otherUsername) ?>" style="text-decoration: none; display: inline-block;">
            <img class="header-avatar" src="<?= htmlspecialchars($headerAvatarUrl) ?>" alt="Avatar" onerror="this.src='Imagenes/user-default.jpg'">
          </a>
        <?php endif; ?>
        
        <div style="flex:1;">
          <h5 style="margin:0;">
            <span class="chat-title" style="cursor: default;"><?= htmlspecialchars($chatTitle) ?></span>
            
            <?php 
            $participantCount = count($current_conv['participants'] ?? []);
            if (!$isGrp && $participantCount > 2): 
              $extraCount = $participantCount - 2;
            ?>
              <span style="display:inline-flex; align-items:center; background:rgba(0,212,170,0.15); color:#00d4aa; font-size:13px; font-weight:600; padding:3px 10px; border-radius:12px; margin-left:8px;">
                +<?= $extraCount ?><?= $extraCount > 1 ? 's' : '' ?>
              </span>
            <?php endif; ?>
            
            <?php if(!$isGrp && !$isReady): ?>
              <span class="badge bg-warning text-dark" title="Pendiente de aceptación">Bloqueado</span>
            <?php endif; ?>
          </h5>
          
          <div class="participants-info" onclick="showParticipants()">
            <small><?=count($current_conv['participants'])?> participante<?= count($current_conv['participants']) > 1 ? 's' : '' ?></small>
          </div>
        </div>
      </div>
      
      <!-- BOTÓN INVITAR (solo para solicitante) -->
      <div style="display:flex; align-items:center; gap:12px;">
<?php if ($canInvite && $userRole === 'solicitante' && $isReady): ?>
  <?php
  // Verificar si ya hay invitados
  $stmt_check_guests = $conexion->prepare("
      SELECT COUNT(*) as guest_count 
      FROM chat_participants 
      WHERE conversation_id = :conv_id 
        AND invited_by_link = 1
  ");
  $stmt_check_guests->execute([':conv_id' => $conversation_id]);
  $guest_info = $stmt_check_guests->fetch(PDO::FETCH_ASSOC);
  $hasInvitedGuests = ($guest_info && $guest_info['guest_count'] > 0);
  
  if (!$hasInvitedGuests):
  ?>
  <button class="btn btn-invite-desktop" onclick="toggleInviteSection()" 
          style="display: flex; align-items: center; gap: 6px; padding: 6px 14px; 
                 font-weight: 600; font-size: 13px; background: #42ba25; color: white; 
                 border: none; border-radius: 20px; 
                 box-shadow: 0 2px 6px rgba(66, 186, 37, 0.25); transition: all 0.2s;">
    <i class="bi bi-person-plus-fill" style="font-size: 14px;"></i>
    <span>Invitar</span>
  </button>
  <?php else: ?>
  <div style="display: flex; align-items: center; gap: 6px; padding: 6px 12px; 
              background: rgba(108, 117, 125, 0.1); border-radius: 16px;">
    <i class="bi bi-check-circle-fill" style="color: #28a745; font-size: 14px;"></i>
    <small style="color: #6c757d; font-weight: 500;">
      <?= $guest_info['guest_count'] ?> invitado<?= $guest_info['guest_count'] > 1 ? 's' : '' ?>
    </small>
  </div>
  <?php endif; ?>
<?php endif; ?>
      </div>
    </div>

    <!-- SECCIÓN DE INVITACIÓN (desplegable) -->
    <div id="inviteSection" style="display: none; background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%); border-bottom: 3px solid #42ba25; padding: 20px;">
      <div style="max-width: 800px; margin: 0 auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
          <div style="display: flex; align-items: center; gap: 12px;">
            <div style="background: linear-gradient(135deg, #42ba25 0%, #359a1e 100%); padding: 12px; border-radius: 12px;">
              <i class="bi bi-person-plus-fill" style="font-size: 24px; color: white;"></i>
            </div>
            <div>
              <h5 style="margin: 0; font-weight: 700; color: #2d3748;">Invitar al Destinatario del Envío</h5>
              <small style="color: #6c757d;">Comparte este enlace para coordinar la entrega</small>
            </div>
          </div>
          <button onclick="toggleInviteSection()" class="btn btn-danger btn-sm"><i class="bi bi-x-lg"></i></button>
        </div>

        <div class="mb-4">
          <label class="form-label"><i class="bi bi-link-45deg"></i> Enlace de Invitación</label>
          <div class="input-group">
            <input type="text" class="form-control" id="invitationLinkInline" readonly placeholder="Generando enlace...">
            <button class="btn btn-success" type="button" onclick="copyInviteLink()"><i class="bi bi-clipboard"></i> Copiar</button>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label"><i class="bi bi-share"></i> Compartir</label>
          <div class="d-flex gap-3">
            <button class="btn" onclick="shareOnNetwork('whatsapp')" style="background:#25D366;color:#fff;">WhatsApp</button>
            <button class="btn" onclick="shareOnNetwork('telegram')" style="background:#0088cc;color:#fff;">Telegram</button>
            <button class="btn btn-secondary" onclick="shareOnNetwork('email')">Email</button>
          </div>
        </div>

        <div class="text-center">
          <button type="button" class="btn btn-warning" onclick="openSecurityModal()">
            <i class="bi bi-shield-exclamation"></i> Ver Recomendaciones de Seguridad
          </button>
          <p class="text-muted mt-2" style="font-size:12px;">⚠️ No compartas teléfonos, códigos QR ni información personal</p>
        </div>

        <div class="text-center mt-3">
          <button type="button" class="btn btn-success" onclick="generateNewLinkInline()">
            <i class="bi bi-arrow-repeat"></i> Generar Nuevo Enlace
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL SEGURIDAD -->
    <div id="securityModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.7); z-index:99999; align-items:center; justify-content:center; padding:20px;" onclick="closeSecurityModal()">
      <div style="background:#fff; border-radius:12px; max-width:600px; width:100%; overflow:hidden;" onclick="event.stopPropagation()">
        <div class="p-3 bg-warning d-flex justify-content-between align-items-center">
          <h5 class="m-0"><i class="bi bi-shield-exclamation"></i> Recomendaciones de Seguridad</h5>
          <button class="btn btn-light btn-sm" onclick="closeSecurityModal()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="p-3">
          <div class="alert alert-warning">
            <strong>Importante:</strong> No compartas teléfonos, códigos QR ni información personal. Usa solo este chat para coordinar.
          </div>
          <ul>
            <li>Coordina puntos de encuentro públicos.</li>
            <li>No envíes documentos sensibles.</li>
            <li>Reporta actividad sospechosa.</li>
          </ul>
          <div class="text-center">
            <button class="btn btn-success" onclick="closeSecurityModal()"><i class="bi bi-check2-circle"></i> Entendido</button>
          </div>
        </div>
      </div>
    </div>

    <!-- MENSAJES -->
    <div class="messages-container" id="msgs"></div>

    <!-- ✅ BANNER MÓVIL VERDE - SOLO PARA SOLICITANTE (REMITENTE) -->
    <?php if ($userRole === 'solicitante' && $isReady && !$isGrp): ?>
    <?php
    // Contar invitados reales
    $stmt_guests_mobile = $conexion->prepare("
        SELECT COUNT(*) as total
        FROM chat_participants
        WHERE conversation_id = :cid AND invited_by_link = 1
    ");
    $stmt_guests_mobile->execute([':cid' => $conversation_id]);
    $guest_count_mobile = (int)$stmt_guests_mobile->fetchColumn();
    $hasInvitedMobile = ($guest_count_mobile > 0);

    // 🎯 Construir URL según contexto (listas_compras o shop_payments)
    if (!empty($context_source) && $context_source === 'shop_payments' && $shop_request_id) {
        // SHOP: Ir a mis solicitudes con QR
        $url_destino_verde = 'shop-my-requests.php?tab=accepted&request_id=' . urlencode($shop_request_id) . '&open_qr=1';
        $texto_boton_verde = 'Ver QR';
    } else {
        // LISTAS_COMPRAS: URL original
        $url_destino_verde = 'https://sendvialo.com/mis_listas.php?tab=aceptado';
        if ($codigo_unico_viaje) {
            $url_destino_verde .= '&codigo=' . urlencode($codigo_unico_viaje) . '&open_lugar=1';
        }
        $texto_boton_verde = 'QR';
    }
    ?>
    <div class="invite-banner-mobile"
         style="background: linear-gradient(135deg, #42ba25 0%, #359a1e 100%);
                padding: 12px 15px; border-top: 2px solid #2e8a18;
                position: fixed; bottom: 64px; left: 0; right: 0; z-index: 100;">
      <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px;">
        
        <!-- Botón QR - Lleva directo a la lista con tab de lugar abierto O a shop-my-requests -->
        <a href="<?= htmlspecialchars($url_destino_verde) ?>"
           style="flex: 1; display: flex; align-items: center; justify-content: center; gap: 6px;
                  background: rgba(255, 255, 255, 0.2); padding: 10px 6px; border-radius: 8px;
                  color: #fff; text-decoration: none; font-weight: 600; font-size: 11px;
                  border: 1px solid rgba(255, 255, 255, 0.3);">
          <i class="bi bi-qr-code" style="font-size: 20px;"></i>
          <span><?= $texto_boton_verde ?></span>
        </a>
        
        <!-- Botón Invitar / Estado -->
        <?php if (!$hasInvitedMobile): ?>
          <button onclick="toggleInviteSection()"
                  style="flex: 1; display: flex; align-items: center; justify-content: center; gap: 6px;
                         background: rgba(255, 255, 255, 0.95); padding: 10px 6px; border-radius: 8px;
                         color: #359a1e; font-weight: 600; font-size: 11px; border: none;
                         box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15); cursor: pointer;">
            <i class="bi bi-person-plus-fill" style="font-size: 20px;"></i>
            <span>Invitar</span>
          </button>
        <?php else: ?>
          <div style="flex: 1; display: flex; align-items: center; justify-content: center; 
                      background: rgba(255, 255, 255, 0.3); padding: 10px 6px; border-radius: 8px;">
            <i class="bi bi-check-circle-fill" style="color: #fff; font-size: 14px;"></i>
            <small style="color: #fff; font-weight: 600; font-size: 10px; margin-left: 4px;">
              <?= $guest_count_mobile ?> inv.
            </small>
          </div>
        <?php endif; ?>
        
      </div>
    </div>
    <?php endif; ?>

    <!-- ✅ BANNER MÓVIL AZUL - SOLO PARA ACEPTADOR (VIAJERO) -->
    <?php if ($userRole === 'aceptador' && $isReady && !$isGrp): ?>
    <?php
    // 🎯 Construir URLs según contexto (listas_compras o shop_payments)
    if (!empty($context_source) && $context_source === 'shop_payments') {
        // SHOP: Ir a mis propuestas con botón escanear QR
        $url_seguimiento_azul = 'shop-my-proposals.php#accepted';
        $texto_boton_azul = 'Escanear QR';
    } else {
        // LISTAS_COMPRAS: URL original de verificación de envíos
        $url_seguimiento_azul = 'https://sendvialo.com/verificacion_envios.php';
        if ($codigo_unico_viaje) {
            $url_seguimiento_azul .= '?codigo=' . urlencode($codigo_unico_viaje);
        }
        $texto_boton_azul = '';
    }
    ?>
    <div class="viajero-banner-mobile"
         style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
                padding: 10px 12px; border-top: 2px solid #004085;
                position: fixed; bottom: 64px; left: 0; right: 0; z-index: 100;">
      <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">

        <!-- Botón Seguimiento / Escanear QR -->
        <a href="<?= htmlspecialchars($url_seguimiento_azul) ?>"
           style="flex: 1; display: flex; align-items: center; justify-content: center; gap: 6px;
                  background: rgba(255, 255, 255, 0.2); padding: 10px 6px; border-radius: 8px;
                  color: #fff; text-decoration: none; font-weight: 600; font-size: 11px;
                  border: 1px solid rgba(255, 255, 255, 0.3);">
          <i class="bi bi-qr-code-scan" style="font-size: 20px;"></i>
          <?php if ($texto_boton_azul): ?>
          <span><?= $texto_boton_azul ?></span>
          <?php endif; ?>
        </a>

        <!-- Botón Mi Viaje (solo para listas_compras) -->
        <?php if (empty($context_source) || $context_source !== 'shop_payments'): ?>
        <a href="https://sendvialo.com/pagina_transporting.php#aceptado"
           style="flex: 1; display: flex; align-items: center; justify-content: center; gap: 6px;
                  background: rgba(255, 255, 255, 0.95); padding: 10px 6px; border-radius: 8px;
                  color: #0056b3; text-decoration: none; font-weight: 600; font-size: 11px;
                  border: none; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15); cursor: pointer;">
          <i class="bi bi-list-check" style="font-size: 20px;"></i>
          <span>Mi Viaje</span>
        </a>
        <?php endif; ?>

      </div>
    </div>
    <?php endif; ?>

    <!-- CSS RESPONSIVE PARA IPHONE -->
    <style>
    @media (max-width: 430px) {
      .invite-banner-mobile,
      .viajero-banner-mobile {
        padding: 8px 8px !important;
      }
      
      .invite-banner-mobile a,
      .invite-banner-mobile button,
      .viajero-banner-mobile a {
        padding: 8px 4px !important;
        font-size: 10px !important;
        gap: 4px !important;
      }
    }
    
    @media (max-width: 375px) {
      .invite-banner-mobile,
      .viajero-banner-mobile {
        padding: 8px 6px !important;
      }
      
      .invite-banner-mobile a,
      .invite-banner-mobile button,
      .viajero-banner-mobile a {
        padding: 8px 3px !important;
        font-size: 9px !important;
        gap: 3px !important;
      }
    }
    </style>

    <!-- FORMULARIO - SIEMPRE VISIBLE -->
    <div class="message-form">
      <form id="frmMsg" action="chat_actions.php" method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="send_message">
        <input type="hidden" name="conversation_id" value="<?=$conversation_id?>">
        
        <div style="display: flex; gap: 8px; align-items: center; width: 100%;">
          <button type="button" class="input-btn" onclick="toggleEmojiPicker()" title="Emojis" <?= $isReady ? '' : 'disabled' ?>>
            <i class="bi bi-emoji-smile"></i>
          </button>
          
          <div id="emojiPickerContainer" class="emoji-picker-container"></div>
          
          <textarea 
            class="message-input-horizontal" 
            name="message" 
            id="msgInput" 
            placeholder="<?= $isReady ? 'Mensaje...' : 'Chat bloqueado' ?>" 
            rows="1" 
            onkeydown="handleKeyDown(event)" 
            oninput="adjustTextarea(this)" 
            <?= $isReady ? '' : 'disabled' ?>
          ></textarea>
          
          <input type="file" name="photo" id="photoInput" accept="image/*" style="display:none;" onchange="handlePhotoSelect(this)" <?= $isReady ? '' : 'disabled' ?>>
          
          <button type="button" class="input-btn" onclick="attachPhoto()" title="Adjuntar foto" <?= $isReady ? '' : 'disabled' ?>>
            <i class="bi bi-image"></i>
          </button>
          
          <button type="button" class="input-btn" onclick="shareLocation()" title="Compartir ubicación" <?= $isReady ? '' : 'disabled' ?>>
            <i class="bi bi-geo-alt"></i>
          </button>
          
          <button type="submit" class="send-btn-horizontal" id="sendBtn" title="Enviar mensaje" <?= $isReady ? '' : 'disabled' ?>>
            <i class="bi bi-send-fill"></i>
          </button>
        </div>
      </form>
    </div>

    <?php if(!$isReady): ?>
    <div class="chat-locked-overlay">
      <div class="chat-locked-box">
        <div class="chat-locked-title">⚠️ Chat pendiente</div>
        <div class="chat-locked-text">
          El viajero debe aceptar los artículos desde la página de transporte.<br>
          Cuando lo haga, el chat se habilitará automáticamente.
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php else: ?>
      <div class="no-chat">
        <i class="bi bi-chat-dots"></i>
        <h4>SendVialo</h4>
        <p>Selecciona una conversación para comenzar a chatear</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- MODAL PARTICIPANTES -->
<div class="participants-modal" id="participantsModal" onclick="hideParticipants()">
  <div class="participants-content" onclick="event.stopPropagation()">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5>Participantes</h5>
      <button class="btn-close" onclick="hideParticipants()"></button>
    </div>
    <div id="participantsList"></div>
  </div>
</div>

<!-- MODAL CREAR GRUPO -->
<div class="modal fade" id="createGroupModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" action="chat_actions.php" method="post" onsubmit="return validateGroupCreation(event)">
      <input type="hidden" name="action" value="create_group">
      <div class="modal-header">
        <h5 class="modal-title">Crear grupo</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Nombre del grupo</label>
          <input class="form-control" name="group_name" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Invitar usuarios (separados por comas)</label>
          <input class="form-control" name="initial_members" id="initial_members" placeholder="usuario1, usuario2">
          <small class="text-muted">Solo puedes invitar a usuarios con los que hayas completado pagos</small>
        </div>
        <div id="validationMessage" class="alert alert-warning d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-success">Crear grupo</button>
      </div>
    </form>
  </div>
</div>

<?php if (empty($_GET['conversation_id'])): ?>
  <?php include 'mobile-bottom-nav.php'; ?>
<?php endif; ?>

<?php if (file_exists('dudas/pajaro_dudas.php')) include 'dudas/pajaro_dudas.php'; ?>

<script src="chat_notifications.js"></script>
<script>
document.addEventListener('click', function() {
  document.body.setAttribute('data-user-interacted', 'true');
}, { once: true });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// ===== VARIABLES GLOBALES =====
const me = <?= json_encode($current_user) ?>;
const conv = <?= $conversation_id ?: 'null' ?>;
const currentParticipants = <?= json_encode($current_conv['participants'] ?? []) ?>;
const participantPhotos = <?= json_encode($participantPhotos ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

function isChatReady() {
  const el = document.getElementById('msgInput');
  return !!el && !el.disabled;
}

let isLoadingMsgs = false;
let pollInterval;
let lastMessageCount = 0;
let conversationsCache = {};
let lastTotalUnreadCount = 0;

// ===== CARGA DE MENSAJES =====
function loadMsgs(forceScroll = false) {
  if (!conv || isLoadingMsgs) return;
  isLoadingMsgs = true;
  fetch(`chat_actions.php?action=get_messages&conversation_id=${conv}&_t=${Date.now()}`)
    .then(r => r.json())
    .then(d => {
      if (!d.success) return;

      const container = document.getElementById('msgs');

      if (!forceScroll && d.messages.length === lastMessageCount && d.messages.length > 0) {
        updateReadStatus(d.messages);
        return;
      }
      lastMessageCount = d.messages.length;

      const wasAtBottom = container.scrollTop + container.clientHeight >= container.scrollHeight - 50;
      container.innerHTML = '';

      d.messages.forEach((m, index) => {
        const msgDiv = document.createElement('div');
        const isSystemMessage = (m.sender_username === 'Sistema' || m.sender_username === 'sistema' || m.sender_username === 'SYSTEM');
        msgDiv.className = `message ${isSystemMessage ? 'received' : (m.sender_username === me ? 'sent' : 'received')}`;
        msgDiv.setAttribute('data-message-id', m.message_id || index);

        let html = '';

        if (isSystemMessage) {
          html += `
            <div class="message-header-info">
              <div class="system-avatar"><i class="bi bi-gear-fill"></i></div>
              <div class="message-sender-name" style="color:#9b59b6;">Sistema</div>
            </div>
          `;
        } else {
          const senderPhotoUrl = participantPhotos[m.sender_username] || 'Imagenes/user-default.jpg';
          const senderColor = m.sender_username === me ? 'rgba(255,255,255,.95)' : '#00d4aa';
          html += `
            <div class="message-header-info">
              <a href="perfil_publico.php?username=${encodeURIComponent(m.sender_username)}" style="text-decoration:none; display:inline-block;">
                <img src="${escapeHtml(senderPhotoUrl)}" class="message-avatar" alt="Avatar" onerror="this.src='Imagenes/user-default.jpg'">
              </a>
              <div class="message-sender-name" style="color:${senderColor};">${escapeHtml(m.sender_username)}</div>
            </div>
          `;
        }

        if (m.message_text) {
          if (m.message_text.includes('Ubicación: https://maps.google.com/')) {
            const coords = m.message_text.match(/q=([-\d.]+),([-\d.]+)/);
            if (coords) {
              const lat = coords[1];
              const lon = coords[2];
              html += `
                <div class="message-map" onclick="window.open('https://maps.google.com/?q=${lat},${lon}', '_blank')">
                  <iframe src="https://maps.google.com/maps?q=${lat},${lon}&t=m&z=15&output=embed&iwloc=near"
                          allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
                <div style="margin-top:4px; font-size:11px; color:#666;">
                  📍 <a href="https://maps.google.com/?q=${lat},${lon}" target="_blank" style="color:#007bff; text-decoration:none;">Ver en Google Maps</a>
                </div>
              `;
            } else {
              html += `<div>${escapeHtml(m.message_text)}</div>`;
            }
          } else {
            html += `<div>${escapeHtml(m.message_text)}</div>`;
          }
        }

        if (m.file_type === 'image' && m.file_path) {
          html += `<img src="${escapeHtml(m.file_path)}" class="attached-img" alt="Foto" loading="lazy" onclick="openImageModal('${escapeHtml(m.file_path)}')">`;
        }

        const time = new Date(m.sent_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
        html += `<div class="message-time" data-message-status="${m.message_id || index}">${time}`;

        if (m.sender_username === me && !isSystemMessage) {
          let statusClass = 'delivered';
          let statusIcon = 'bi-check';
          let statusText = '';
          if (m.read_stats) {
            if (m.read_stats.delivery_status === 'read') {
              statusClass = 'read';
              statusIcon = 'bi-check-all';
              if (isCurrentGroup() && m.read_stats.total_participants > 1) {
                statusText = `Leído por ${m.read_stats.read_count}/${m.read_stats.total_participants}`;
              }
            } else if (m.read_stats.read_count > 0) {
              statusClass = 'partially-read';
              statusIcon = 'bi-check-all';
              statusText = `Leído por ${m.read_stats.read_count}/${m.read_stats.total_participants}`;
            }
          } else if (m.is_read) {
            statusClass = 'read';
            statusIcon = 'bi-check-all';
          }
          html += ` <span class="message-status ${statusClass}" title="${statusText}"><i class="bi ${statusIcon}"></i></span>`;
        }

        html += `</div>`;
        msgDiv.innerHTML = html;
        container.appendChild(msgDiv);
        container.appendChild(Object.assign(document.createElement('div'), { className: 'clearfix' }));
      });

      if (forceScroll || wasAtBottom) setTimeout(scrollToBottom, 100);
    })
    .finally(() => { isLoadingMsgs = false; });
}

function updateReadStatus(messages) {
  messages.forEach((m, index) => {
    const wrap = document.querySelector(`[data-message-status="${m.message_id || index}"]`);
    if (!wrap) return;
    const statusElement = wrap.querySelector('.message-status');
    if (statusElement && m.sender_username === me) {
      let statusClass = 'delivered';
      let statusIcon = 'bi-check';
      let statusText = '';
      if (m.read_stats) {
        if (m.read_stats.delivery_status === 'read') {
          statusClass = 'read';
          statusIcon = 'bi-check-all';
          if (isCurrentGroup() && m.read_stats.total_participants > 1) {
            statusText = `Leído por ${m.read_stats.read_count}/${m.read_stats.total_participants}`;
          }
        } else if (m.read_stats.read_count > 0) {
          statusClass = 'partially-read';
          statusIcon = 'bi-check-all';
          statusText = `Leído por ${m.read_stats.read_count}/${m.read_stats.total_participants}`;
        }
      } else if (m.is_read) {
        statusClass = 'read';
        statusIcon = 'bi-check-all';
      }
      statusElement.className = `message-status ${statusClass}`;
      statusElement.innerHTML = `<i class="bi ${statusIcon}"></i>`;
      statusElement.title = statusText;
    }
  });
}

function updateUnreadCounts() {
  if (conv && document.hasFocus()) {
    fetch(`chat_actions.php?action=get_messages&conversation_id=${conv}&limit=1&_t=${Date.now()}`)
      .then(r => r.json())
      .catch(() => {});
  }

  fetch(`chat_actions.php?action=get_conversations&_t=${Date.now()}`)
    .then(r => r.json())
    .then(d => {
      if (!d.success || !Array.isArray(d.conversations)) return;

      let totalUnreadCount = 0;

      d.conversations.forEach(conversation => {
        const convId = conversation.conversation_id;
        const convItem = document.querySelector(`[onclick*="conversation_id=${convId}"]`);
        if (!convItem) return;

        const metaContainer = convItem.querySelector('.conversation-meta') || convItem;
        let badge = convItem.querySelector(`#unread-${convId}`);

        if (convId !== conv) totalUnreadCount += conversation.unread_count || 0;

        if ((conversation.unread_count || 0) > 0 && convId !== conv) {
          if (badge) {
            badge.textContent = conversation.unread_count;
            badge.style.display = 'block';
          } else if (metaContainer) {
            const newBadge = document.createElement('div');
            newBadge.className = 'unread-badge';
            newBadge.id = `unread-${convId}`;
            newBadge.textContent = conversation.unread_count;
            metaContainer.appendChild(newBadge);
          }
        } else if (badge) {
          badge.remove();
        }

        if (conv && convId === conv && conversation.conversation_status === 'READY') {
          const msgInput = document.getElementById('msgInput');
          
          if (msgInput && msgInput.disabled) {
            enableChatUI();
            loadMsgs(true);
            if (typeof refreshParticipantsOnce === 'function') {
              refreshParticipantsOnce();
            }
          }
        }

        const lastMsgElement = convItem.querySelector('.conversation-last');
        if (lastMsgElement && conversation.last_message !== null) {
          const prefix = conversation.last_sender === me ? 'Tú: ' : '';
          lastMsgElement.textContent = prefix + conversation.last_message;
        }

        const timeElement = convItem.querySelector('.conv-time');
        if (conversation.last_message_time) {
          const time = new Date(conversation.last_message_time);
          const now = new Date();
          const diffMs = now - time;
          const diffMins = Math.floor(diffMs / 60000);
          const diffHours = Math.floor(diffMs / 3600000);
          const diffDays = Math.floor(diffMs / 86400000);

          let timeStr;
          if (diffMins < 1) timeStr = 'Ahora';
          else if (diffMins < 60) timeStr = `${diffMins}m`;
          else if (diffHours < 24) timeStr = time.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
          else if (diffDays === 1) timeStr = 'Ayer';
          else if (diffDays < 7) timeStr = time.toLocaleDateString([], { weekday: 'short' });
          else timeStr = time.toLocaleDateString([], { day: '2-digit', month: '2-digit' });

          if (!timeElement) {
            const timeDiv = document.createElement('div');
            timeDiv.className = 'conv-time';
            timeDiv.textContent = timeStr;
            metaContainer.insertBefore(timeDiv, metaContainer.firstChild);
          } else {
            timeElement.textContent = timeStr;
          }
        }
      });

      document.title = totalUnreadCount > 0
        ? `(${totalUnreadCount}) Chat - Sendvialo`
        : 'Chat - Sendvialo';

      if (totalUnreadCount > lastTotalUnreadCount && !document.hasFocus()) {
        playNotificationSound();
      }
      lastTotalUnreadCount = totalUnreadCount;
    })
    .catch(() => {});
}

function enableChatUI() {
  const msgInput = document.getElementById('msgInput');
  if (msgInput) {
    msgInput.disabled = false;
    msgInput.placeholder = 'Escribe un mensaje...';
  }

  ['sendBtn', 'photoInput'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.disabled = false;
  });

  document.querySelectorAll('.input-btn').forEach(btn => {
    btn.disabled = false;
  });

  const overlay = document.querySelector('.chat-locked-overlay');
  if (overlay) overlay.remove();

  showNotification('✅ El viajero ha aceptado los artículos. Chat habilitado', 'success');
  
  window._chatReady = true;
}

function checkConvReadyOnce(){
  if (!conv) return;
  const input = document.getElementById('msgInput');
  if (input && !input.disabled) return;

  fetch(`chat_actions.php?action=get_conv_status&conversation_id=${conv}&_t=${Date.now()}`)
    .then(r => r.json())
    .then(d => {
      if (d && d.success && d.conversation_status === 'READY') {
        if (typeof enableChatUI === 'function') enableChatUI();
      }
    }).catch(()=>{});
}

let lastParticipantHash = '';

function refreshParticipantsOnce(){
  if (!conv) return;
  fetch(`chat_actions.php?action=get_participants&conversation_id=${conv}&_t=${Date.now()}`)
    .then(r=>r.json())
    .then(d=>{
      if(!d.success || !Array.isArray(d.participants)) return;

      const h = d.participants.map(p=>p.username + '|' + (p.photo||'')).join(',');
      if (h === lastParticipantHash) return;
      lastParticipantHash = h;

      d.participants.forEach(p=>{
        participantPhotos[p.username] = p.photo || 'Imagenes/user-default.jpg';
      });

      const title = document.querySelector('.chat-title')?.textContent?.trim();
      const headerImg = document.querySelector('.chat-header .header-avatar');
      if (title && headerImg && participantPhotos[title]) {
        headerImg.src = participantPhotos[title];
      }

      if (document.getElementById('participantsModal')?.classList.contains('show')) {
        showParticipants();
      }
    })
    .catch(()=>{});
}

(function bootParticipantsAutoRefresh(){
  if (!conv) return;
  let ticks = 0;
  const fast = setInterval(()=>{
    refreshParticipantsOnce();
    if (++ticks