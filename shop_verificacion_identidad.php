<?php
// verificacion_identidad.php - Página principal de verificación para usuarios - VERSIÓN MEJORADA
session_start();
require_once 'config.php';
require_once '../verificacion_functions.php';

// Verificar que el usuario esté logueado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['usuario_id'];
$message = '';
$message_type = '';

// Obtener datos actuales del usuario
try {
    $stmt = $conexion->prepare("
        SELECT 
            full_name, email, phone, dni, 
            documento_identidad_path, selfie_documento_path, 
            estado_verificacion, fecha_solicitud_verificacion, 
            fecha_verificacion, notas_verificacion, verificacion_identidad
        FROM accounts 
        WHERE id = ?
    ");
    $stmt->execute([$user_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        die('Usuario no encontrado');
    }
} catch (PDOException $e) {
    die('Error de base de datos: ' . $e->getMessage());
}

// VALIDACIÓN CRÍTICA: Prevenir procesamiento en estados que no deben ser modificados
$estados_protegidos = ['aprobado'];
$form_blocked = false;
$block_reason = '';

if (in_array($usuario['estado_verificacion'], $estados_protegidos)) {
    $form_blocked = true;
    $block_reason = 'Tu verificación ya ha sido completada';
}

// Log de intentos sospechosos
function logSuspiciousActivity($user_id, $action, $current_state) {
    error_log("[VERIFICACIÓN] Usuario $user_id intentó '$action' en estado '$current_state' - " . date('Y-m-d H:i:s'));
}

// Procesar formulario SOLO si está permitido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$form_blocked) {
    $action = $_POST['action'] ?? '';
    $estado_actual = $usuario['estado_verificacion'];
    
    // Validaciones de seguridad por acción
    $action_allowed = false;
    $validation_error = '';
    
switch ($action) {
    case 'solicitar_verificacion':
        if ($estado_actual === 'no_solicitado') {
            $action_allowed = true;
        } else {
            $validation_error = 'Tu verificación no ha sido aprobada. Sube nuevamente tus documentos siguiendo las instrucciones.';
        }
        break;
        
    case 'actualizar_documentos':
        if ($estado_actual === 'rechazado') {
            $action_allowed = true;
        } else {
            $validation_error = 'Solo puedes solicitar verificación si no la has solicitado antes.';
        }
        break;
        
    default:
        $validation_error = 'Acción no válida.';
}
    
    // Si la acción no está permitida, registrar y bloquear
    if (!$action_allowed) {
        logSuspiciousActivity($user_id, $action, $estado_actual);
        $message = $validation_error;
        $message_type = 'error';
    } else {
        // Procesar la acción válida
        try {
            $conexion->beginTransaction();
            
            if ($action === 'solicitar_verificacion') {
                // Validar archivos obligatorios para nueva solicitud
                if (empty($_FILES['documento_identidad']['tmp_name']) || empty($_FILES['selfie_documento']['tmp_name'])) {
                    throw new Exception('Debe subir tanto el documento de identidad como la selfie');
                }
                
                // Procesar documento de identidad
                $resultado_documento = procesarArchivoVerificacion($_FILES['documento_identidad'], $user_id, 'documento');
                if (isset($resultado_documento['error'])) {
                    throw new Exception('Error en documento: ' . $resultado_documento['error']);
                }
                
                // Procesar selfie
                $resultado_selfie = procesarArchivoVerificacion($_FILES['selfie_documento'], $user_id, 'selfie');
                if (isset($resultado_selfie['error'])) {
                    // Limpiar archivo de documento si la selfie falló
                    if (file_exists($resultado_documento['ruta_archivo'])) {
                        unlink($resultado_documento['ruta_archivo']);
                    }
                    throw new Exception('Error en selfie: ' . $resultado_selfie['error']);
                }
                
                // CAMBIO CRÍTICO: Solo eliminar archivos anteriores DESPUÉS de confirmar que los nuevos están OK
                if (!empty($usuario['documento_identidad_path']) || !empty($usuario['selfie_documento_path'])) {
                    try {
                        eliminarArchivosVerificacion($user_id);
                    } catch (Exception $e) {
                        // Log del error pero no fallar la operación
                        error_log("Error eliminando archivos anteriores para usuario $user_id: " . $e->getMessage());
                    }
                }
                
                // Actualizar base de datos
                $stmt = $conexion->prepare("
                    UPDATE accounts SET 
                        documento_identidad_path = ?,
                        selfie_documento_path = ?,
                        estado_verificacion = 'pendiente',
                        fecha_solicitud_verificacion = NOW(),
                        fecha_verificacion = NULL,
                        admin_verificador_id = NULL,
                        notas_verificacion = NULL
                    WHERE id = ? AND estado_verificacion = 'no_solicitado'
                ");
                
                $result = $stmt->execute([
                    $resultado_documento['ruta_relativa'],
                    $resultado_selfie['ruta_relativa'],
                    $user_id
                ]);
                
                // Verificar que la actualización fue exitosa
                if ($stmt->rowCount() === 0) {
                    throw new Exception('No se pudo actualizar el registro. El estado puede haber cambiado.');
                }
                
                // Registrar en historial
                registrarHistorialVerificacion(
                    $conexion, 
                    $user_id, 
                    $user_id,
                    'solicitado',
                    $usuario['estado_verificacion'],
                    'pendiente',
                    'Solicitud de verificación de identidad'
                );
                
                $conexion->commit();
                
                $message = 'Documentos subidos correctamente. Tu solicitud está siendo revisada.';
                $message_type = 'success';
                
                // Actualizar datos del usuario en memoria
                $usuario['documento_identidad_path'] = $resultado_documento['ruta_relativa'];
                $usuario['selfie_documento_path'] = $resultado_selfie['ruta_relativa'];
                $usuario['estado_verificacion'] = 'pendiente';
                $usuario['fecha_solicitud_verificacion'] = date('Y-m-d H:i:s');
                
            } elseif ($action === 'actualizar_documentos') {
                // Validar que se suba al menos un archivo
                if (empty($_FILES['documento_identidad']['tmp_name']) && empty($_FILES['selfie_documento']['tmp_name'])) {
                    throw new Exception('Debe subir al menos un documento');
                }
                
                $rutas_actualizadas = [];
                $archivos_procesados = [];
                
                // Procesar documento si se subió
                if (!empty($_FILES['documento_identidad']['tmp_name'])) {
                    $resultado_documento = procesarArchivoVerificacion($_FILES['documento_identidad'], $user_id, 'documento');
                    if (isset($resultado_documento['error'])) {
                        throw new Exception('Error en documento: ' . $resultado_documento['error']);
                    }
                    $rutas_actualizadas['documento_identidad_path'] = $resultado_documento['ruta_relativa'];
                    $archivos_procesados[] = $resultado_documento['ruta_archivo'];
                }
                
                // Procesar selfie si se subió
                if (!empty($_FILES['selfie_documento']['tmp_name'])) {
                    $resultado_selfie = procesarArchivoVerificacion($_FILES['selfie_documento'], $user_id, 'selfie');
                    if (isset($resultado_selfie['error'])) {
                        // Limpiar archivos procesados si hay error
                        foreach ($archivos_procesados as $archivo) {
                            if (file_exists($archivo)) {
                                unlink($archivo);
                            }
                        }
                        throw new Exception('Error en selfie: ' . $resultado_selfie['error']);
                    }
                    $rutas_actualizadas['selfie_documento_path'] = $resultado_selfie['ruta_relativa'];
                }
                
                // Construir query dinámicamente
                $campos_update = [];
                $valores_update = [];
                
                foreach ($rutas_actualizadas as $campo => $valor) {
                    $campos_update[] = "$campo = ?";
                    $valores_update[] = $valor;
                }
                
                $campos_update[] = "estado_verificacion = 'pendiente'";
                $campos_update[] = "fecha_solicitud_verificacion = NOW()";
                $campos_update[] = "notas_verificacion = NULL";
                $valores_update[] = $user_id;
                
                $sql = "UPDATE accounts SET " . implode(', ', $campos_update) . " WHERE id = ? AND estado_verificacion = 'rechazado'";
                $stmt = $conexion->prepare($sql);
                $stmt->execute($valores_update);
                
                // Verificar que la actualización fue exitosa
                if ($stmt->rowCount() === 0) {
                    throw new Exception('No se pudo actualizar el registro. El estado puede haber cambiado.');
                }
                
                // Registrar en historial
                registrarHistorialVerificacion(
                    $conexion,
                    $user_id,
                    $user_id,
                    'documentos_actualizados', 
                    'rechazado',
                    'pendiente',
                    'Documentos actualizados tras rechazo'
                );
                
                $conexion->commit();
                
                $message = 'Documentos actualizados correctamente. Tu solicitud está siendo revisada nuevamente.';
                $message_type = 'success';
                
                // Actualizar datos del usuario en memoria
                foreach ($rutas_actualizadas as $campo => $valor) {
                    $usuario[$campo] = $valor;
                }
                $usuario['estado_verificacion'] = 'pendiente';
                $usuario['notas_verificacion'] = null;
            }
            
        } catch (Exception $e) {
            $conexion->rollback();
            $message = $e->getMessage();
            $message_type = 'error';
            
            // Log del error
            error_log("Error en verificación usuario $user_id: " . $e->getMessage());
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $form_blocked) {
    // Log de intento bloqueado
    logSuspiciousActivity($user_id, $_POST['action'] ?? 'unknown', $usuario['estado_verificacion']);
    $message = $block_reason;
    $message_type = 'info';
}

// Función para obtener el badge del estado
function getEstadoBadge($estado) {
    switch ($estado) {
        case 'no_solicitado':
            return '<span class="estado-badge estado-no-solicitado">No solicitado</span>';
        case 'pendiente':
            return '<span class="estado-badge estado-pendiente">Pendiente de revisión</span>';
        case 'aprobado':
            return '<span class="estado-badge estado-aprobado">✓ Verificado</span>';
        case 'rechazado':
            return '<span class="estado-badge estado-rechazado">Rechazado</span>';
        default:
            return '<span class="estado-badge estado-desconocido">Desconocido</span>';
    }
}

function formatearFecha($fecha) {
    if (!$fecha) return 'No disponible';
    return date('d/m/Y H:i', strtotime($fecha));
}

// Determinar qué vista mostrar basado en el estado
$mostrar_formulario = false;
$mostrar_vista_pendiente = false;
$mostrar_vista_aprobado = false;

switch ($usuario['estado_verificacion']) {
    case 'no_solicitado':
        $mostrar_formulario = true;
        break;
    case 'rechazado':
        $mostrar_formulario = !empty($usuario['notas_verificacion']); // Solo si hay notas del admin
        break;
    case 'pendiente':
        $mostrar_vista_pendiente = true;
        break;
    case 'aprobado':
        $mostrar_vista_aprobado = true;
        break;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de Identidad - SendVialo</title>
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/footer.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="icon" href="Imagenes/globo5.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
    /* ====== NUEVO ESTILO MINIMALISTA ====== */
.verification-card {
  background: #fff;
  border-left: 5px solid #42ba25;
  border-radius: 12px;
  max-width: 700px;
  width: 100%;
  padding: 30px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
  margin: 40px auto;
}
.verification-card h2 {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 1.8rem;
  color: #2c3e50;
}
.progress-bar {
  height: 8px;
  background: linear-gradient(90deg, #42ba25, #358a1f);
  border-radius: 4px;
  width: 100%;
  margin: 15px 0;
}
.status-pendiente { color: #ffc107; }
.status-aprobado { color: #42ba25; }
.status-rechazado { color: #dc3545; }

.documents-gallery {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 24px;
  margin-top: 40px;
}
.document {
  position: relative;
  background: #fff;
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid #e0e0e0;
  transition: transform 0.2s ease;
}
.document:hover { transform: translateY(-3px); }
.document img {
  width: 100%;
  height: 200px;
  object-fit: cover;
}
.document .check {
  position: absolute;
  top: 10px;
  right: 10px;
  background: #42ba25;
  color: #fff;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
}
.document span {
  display: block;
  text-align: center;
  padding: 10px;
  color: #2c3e50;
  font-weight: 500;
}

        /* Reset y configuración base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #ffffff;
            color: #2c3e50;
            line-height: 1.6;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* Estilos para header y footer */
        header, footer {
            width: 100%;
        }

        /* Contenedor principal centrado */
        .main-wrapper {
            width: 100%;
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
        }

        .container {
            width: 100%;
            max-width: 900px;
            margin: 30px auto;
            padding: 40px;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* Header section profesional */
        .header-section {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 3px solid #42ba25;
            width: 100%;
        }

        .header-section h1 {
            color: #6c757d;
            font-size: 2.8rem;
            font-weight: 300;
            margin-bottom: 12px;
            letter-spacing: -0.5px;
        }

        .header-section p {
            color: #6c757d;
            font-size: 1.1rem;
            font-weight: 400;
            margin: 0;
        }

        /* Mensajes de estado */
        .status-message {
            border-radius: 10px;
            padding: 16px 24px;
            margin: 0 0 30px 0;
            font-weight: 500;
            text-align: center;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            width: 100%;
            max-width: 700px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
        }

        .info-message {
            background: #d1ecf1;
            color: #0c5460;
            border: 2px solid #bee5eb;
        }

        /* Estado de verificación */
        .status-section {
            background: #ffffff;
            border: 2px solid #42ba25;
            border-radius: 12px;
            padding: 32px;
            margin: 0 0 30px 0;
            box-shadow: 0 4px 16px rgba(66, 186, 37, 0.1);
            width: 100%;
            max-width: 800px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .status-section:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(66, 186, 37, 0.15);
        }

        .status-section h5 {
            color: #42ba25;
            font-weight: 700;
            margin-bottom: 20px;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Badges de estado */
        .estado-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .estado-no-solicitado {
            background: #6c757d;
            color: #ffffff;
        }

        .estado-pendiente {
            background: #ffc107;
            color: #000000;
            animation: pulseGlow 2s infinite;
        }

        .estado-aprobado {
            background: #28a745;
            color: #ffffff;
        }

        .estado-rechazado {
            background: #dc3545;
            color: #ffffff;
        }

        .estado-desconocido {
            background: #e9ecef;
            color: #6c757d;
        }

        @keyframes pulseGlow {
            0%, 100% {
                box-shadow: 0 2px 8px rgba(255, 193, 7, 0.3);
            }
            50% {
                box-shadow: 0 4px 16px rgba(255, 193, 7, 0.6);
            }
        }

        /* Barra de progreso */
        .progress-container {
            margin-top: 20px;
        }

        .progress {
            height: 8px;
            border-radius: 4px;
            background: #e9ecef;
            overflow: hidden;
        }

        .progress-bar {
            background: linear-gradient(90deg, #42ba25, #358a1f);
            transition: width 0.3s ease;
        }

        /* Notas del administrador */
        .admin-notes {
            background: #fff3cd;
            border: 2px solid #ffeaa7;
            border-radius: 10px;
            padding: 16px;
            margin-top: 20px;
            border-left: 4px solid #ffc107;
        }

        /* Contenedor del formulario */
        .form-container {
            background: #ffffff;
            border: 2px solid #42ba25;
            border-radius: 12px;
            padding: 32px;
            margin: 0 0 30px 0;
            box-shadow: 0 4px 16px rgba(66, 186, 37, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            width: 100%;
            max-width: 800px;
        }

        .form-container:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(66, 186, 37, 0.15);
        }

        /* Zona de subida de archivos MEJORADA */
        .upload-zone {
            border: 3px dashed #dee2e6;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            background: #f8f9fa;
            margin-bottom: 20px;
        }

        .upload-zone:hover {
            border-color: #42ba25;
            background: rgba(66, 186, 37, 0.05);
        }

        .upload-zone.dragover {
            border-color: #42ba25;
            background: rgba(66, 186, 37, 0.1);
            transform: scale(1.02);
        }

        .upload-zone input[type="file"] {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .upload-zone i {
            font-size: 3rem;
            color: #6c757d;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        .upload-zone:hover i {
            color: #42ba25;
            transform: scale(1.1);
        }

        .upload-zone h6 {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 1.1rem;
        }

        .upload-zone p {
            color: #6c757d;
            margin: 0;
            font-size: 0.9rem;
        }

        /* Preview de imágenes MEJORADO */
        .preview-container {
            margin-top: 15px;
            display: none;
            text-align: center;
        }

        .preview-image {
            max-width: 100%;
            max-height: 200px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border: 2px solid #42ba25;
            object-fit: cover;
        }

        .existing-image {
            max-width: 200px;
            max-height: 150px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #42ba25;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .existing-image:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
        }

        /* Botón para quitar preview */
        .remove-preview {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.8rem;
            margin-top: 10px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .remove-preview:hover {
            background: #c82333;
            transform: translateY(-1px);
        }

        /* Requisitos */
        .requirements {
            background: rgba(66, 186, 37, 0.05);
            border: 1px solid rgba(66, 186, 37, 0.2);
            border-left: 4px solid #42ba25;
            padding: 16px;
            border-radius: 8px;
            margin-top: 15px;
        }

        .requirements h6 {
            color: #42ba25;
            font-weight: 600;
            margin-bottom: 10px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .requirements ul {
            margin: 0;
            padding-left: 20px;
        }

        .requirements li {
            font-size: 0.85rem;
            color: #2c3e50;
            margin-bottom: 4px;
        }

        /* Información del usuario */
        .user-info-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            border: 1px solid #dee2e6;
        }

        .user-info-section h6 {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 15px;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .user-info-item {
            font-size: 0.9rem;
        }

        .user-info-item strong {
            color: #2c3e50;
            display: block;
            margin-bottom: 2px;
        }

        /* Botones profesionales */
        .btn {
            background: #42ba25;
            border: none;
            color: #ffffff;
            padding: 14px 28px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(66, 186, 37, 0.25);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            justify-content: center;
            border: 2px solid #42ba25;
        }

        .btn:hover {
            background: #358a1f;
            border-color: #358a1f;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(66, 186, 37, 0.3);
            color: #ffffff;
        }

        .btn:disabled {
            opacity: 0.6;
            transform: none;
            cursor: not-allowed;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .btn-lg {
            padding: 16px 32px;
            font-size: 1.1rem;
        }

        /* Estados de contenido */
        .content-state {
            text-align: center;
            padding: 60px 20px;
            width: 100%;
            max-width: 600px;
        }

        .content-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            display: block;
        }

        .content-state h4 {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 15px;
            font-size: 1.5rem;
        }

        .content-state p {
            color: #6c757d;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        /* Checkbox personalizado */
        .form-check {
            margin: 20px 0;
        }

        .form-check-input {
            width: 1.2em;
            height: 1.2em;
            margin-top: 0.1em;
            border: 2px solid #42ba25;
        }

        .form-check-input:checked {
            background-color: #42ba25;
            border-color: #42ba25;
        }

        .form-check-label {
            font-size: 0.9rem;
            color: #2c3e50;
            margin-left: 8px;
        }

        /* Alertas informativas */
        .info-alert {
            background: rgba(66, 186, 37, 0.1);
            border: 1px solid rgba(66, 186, 37, 0.3);
            border-radius: 8px;
            padding: 16px;
            margin: 15px 0;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .info-alert i {
            color: #42ba25;
            font-size: 1.2rem;
            flex-shrink: 0;
            margin-top: 2px;
        }

        /* Verificación aprobada - Vista simplificada pero elegante */
        .verified-identity-section {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
        }

        .verification-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            border-radius: 16px;
            padding: 32px;
            margin-bottom: 32px;
            color: white;
            text-align: center;
        }

        .verification-status h3 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .verification-status p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 12px;
        }

        .verified-badge {
            background: #4CAF50;
            color: white;
            padding: 12px 20px;
            border-radius: 25px;
            font-weight: 700;
            font-size: 0.9rem;
            letter-spacing: 1px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        /* Documentos verificados con vista previa mejorada */
        .documents-preview-section {
            background: white;
            border-radius: 16px;
            margin-bottom: 32px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
            border: 1px solid #e9ecef;
            overflow: hidden;
        }

        .documents-preview-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 24px 32px;
            border-bottom: 1px solid #dee2e6;
        }

        .documents-preview-header h4 {
            color: #2c3e50;
            font-weight: 600;
            font-size: 1.3rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .documents-grid {
            padding: 32px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
        }

        .document-preview-card {
            border: 1px solid #e9ecef;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .document-preview-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.15);
        }

        .document-preview-header {
            background: #f8f9fa;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            border-bottom: 1px solid #e9ecef;
        }

        .doc-icon {
            background: #42ba25;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }

        .doc-info h6 {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 4px;
            font-size: 1rem;
        }

        .doc-info span {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .document-image-container {
            position: relative;
            height: 200px;
            overflow: hidden;
        }

        .document-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .document-preview-card:hover .document-image {
            transform: scale(1.05);
        }

        .document-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(76, 175, 80, 0.9), rgba(66, 186, 37, 0.9));
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .document-preview-card:hover .document-overlay {
            opacity: 1;
        }

        .overlay-content {
            color: white;
            text-align: center;
            font-weight: 700;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }

        .overlay-content i {
            font-size: 2rem;
        }

        /* Modal para vista completa de imagen */
        .image-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
        }

        .modal-content {
            position: relative;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            max-width: 90%;
            max-height: 90%;
        }

        .modal-image {
            width: 100%;
            height: auto;
            border-radius: 8px;
        }

        .close-modal {
            position: absolute;
            top: 15px;
            right: 35px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
        }

        .close-modal:hover {
            color: #bbb;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-wrapper {
                padding: 0 15px;
            }
            
            @media (max-width: 768px) {
  .verification-card h2 {
    font-size: 1.2rem; /* más pequeño y proporcionado */
    text-align: center; /* centrado en pantallas pequeñas */
  }
}


            .container {
                margin: 20px auto;
                padding: 24px;
            }

            .header-section h1 {
                font-size: 2.2rem;
            }

            .form-container,
            .status-section {
                padding: 20px;
                margin-bottom: 20px;
            }

            .upload-zone {
                padding: 20px;
            }

            .upload-zone i {
                font-size: 2.5rem;
            }

            .user-info-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .content-state {
                padding: 40px 15px;
            }

            .content-state i {
                font-size: 3rem;
            }

            .btn-lg {
                width: 100%;
                padding: 14px;
            }

            .documents-grid {
                grid-template-columns: 1fr;
                padding: 20px;
            }

            .verification-header {
                padding: 24px;
            }
        }

        @media (max-width: 480px) {
            .header-section h1 {
                font-size: 1.9rem;
            }

            .container {
                padding: 20px;
            }

            .upload-zone {
                padding: 15px;
            }
        }

        /* Animaciones de entrada */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in-up {
            animation: fadeInUp 0.6s ease forwards;
        }

        /* Animación de spinner */
        .spin {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        /* ✅ Ajuste visual para móviles */
@media (max-width: 768px) {
  .documents-gallery {
    grid-template-columns: repeat(2, 1fr); /* 2 columnas incluso en móviles */
    gap: 12px; /* menos espacio entre tarjetas */
  }

  .document {
    border-radius: 10px;
  }

  .document img {
    height: 120px; /* más pequeño y compacto */
    object-fit: cover;
  }

  .document span {
    font-size: 0.85rem;
    padding: 6px;
  }

  .document .check {
    width: 22px;
    height: 22px;
    font-size: 0.8rem;
  }
}

    </style>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

</head>
<body>
    <?php include 'shop-header.php'; ?>

    <div class="main-wrapper">
        <div class="container">
            <div class="header-section">
                <h1>Verificación de Identidad</h1>
                <p>Verifica tu identidad para obtener mayor confianza en la plataforma</p>
            </div>

            <!-- Mensajes -->
            <?php if ($message): ?>
            <div class="status-message <?= $message_type ?>-message fade-in-up">
                <i class="bi bi-<?= $message_type === 'success' ? 'check-circle' : ($message_type === 'info' ? 'info-circle' : 'exclamation-triangle') ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
            <?php endif; ?>

<!-- NUEVA TARJETA DE ESTADO CONTEXTUAL -->
<div class="verification-card fade-in-up">
  <?php
    // Determinar progreso y mensaje según el estado
    switch($usuario['estado_verificacion']) {
      case 'aprobado':
        $progreso = 100;
        $titulo = '¡Tienes un Perfil Verificado!';
        $colorBarra = '#42ba25'; // Verde
        $mensaje = 'Tu identidad ha sido verificada exitosamente';
        $icono = 'bi-check-circle-fill';
        $colorIcono = '#42ba25';
        break;
        
      case 'pendiente':
        $progreso = 50;
        $titulo = 'Pendiente';
        $colorBarra = '#ffc107'; // Amarillo
        $mensaje = 'Estamos revisando tus documentos. Te notificaremos pronto';
        $icono = 'bi-hourglass-split';
        $colorIcono = '#ffc107';
        break;
        
      case 'rechazado':
        $progreso = 25;
        $titulo = 'Verificación Rechazada';
        $colorBarra = '#dc3545'; // Rojo
        $mensaje = 'Revisa las notas y sube nuevamente tus documentos';
        $icono = 'bi-x-circle-fill';
        $colorIcono = '#dc3545';
        break;
        
      default: // no_solicitado
        $progreso = 0;
        $titulo = 'Tu Perfil No Está Verificado';
        $colorBarra = '#ff8c00'; // Naranja
        $mensaje = 'Sube tus documentos para obtener mayor confianza en la plataforma';
        $icono = 'bi-shield-exclamation';
        $colorIcono = '#ff8c00';
    }
  ?>
  
  <h2>
    <i class="bi <?= $icono ?>" style="color: <?= $colorIcono ?>"></i>
    <?= $titulo ?>
  </h2>
  
  <div class="progress-bar" style="width: <?= $progreso ?>%; background: <?= $colorBarra ?>;"></div>
  
  <p style="margin-top: 15px; color: #6c757d; font-size: 1rem;">
    <?= $mensaje ?>
  </p>

  <?php if ($usuario['estado_verificacion'] !== 'no_solicitado'): ?>
    <p style="margin-top: 10px;">
      <strong>Estado:</strong> 
      <span class="status-<?= htmlspecialchars($usuario['estado_verificacion']) ?>">
        <?= ucfirst($usuario['estado_verificacion']) ?>
      </span>
    </p>
  <?php endif; ?>

  <?php if ($usuario['fecha_solicitud_verificacion']): ?>
    <p><strong>Solicitado:</strong> <?= formatearFecha($usuario['fecha_solicitud_verificacion']) ?></p>
  <?php endif; ?>
  
  <?php if ($usuario['fecha_verificacion']): ?>
    <p><strong>Verificado:</strong> <?= formatearFecha($usuario['fecha_verificacion']) ?></p>
  <?php endif; ?>

  <?php if (!empty($usuario['notas_verificacion'])): ?>
    <div class="admin-notes">
      <strong><i class="bi bi-sticky-note me-2"></i>Notas del administrador:</strong><br>
      <?= nl2br(htmlspecialchars($usuario['notas_verificacion'])) ?>
    </div>
  <?php endif; ?>
</div>

            <!-- Formulario de verificación (solo en estados permitidos) -->
            <?php if ($mostrar_formulario): ?>
            
            <div class="form-container fade-in-up">
                <form method="POST" enctype="multipart/form-data" id="verificacionForm">
                    <input type="hidden" name="action" value="<?= $usuario['estado_verificacion'] === 'rechazado' ? 'actualizar_documentos' : 'solicitar_verificacion' ?>">
                    
                    <div class="row">
                        <!-- Documento de identidad -->
                        <div class="col-md-6 mb-4">
                            <h6>
                                <i class="bi bi-file-earmark-person text-success me-2"></i>
                                Documento de Identidad
                            </h6>
                            
                            <?php if (!empty($usuario['documento_identidad_path']) && $usuario['estado_verificacion'] === 'rechazado'): ?>
                            <div class="mb-3 text-center">
                                <p class="small text-muted mb-2">Documento actual:</p>
                                <img src="<?= htmlspecialchars($usuario['documento_identidad_path']) ?>" 
                                     class="existing-image" 
                                     alt="Documento actual"
                                     onclick="openImageModal(this.src)"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                <div style="display: none;" class="text-muted">
                                    <i class="bi bi-file-image" style="font-size: 2rem;"></i>
                                    <p class="small">Imagen no disponible</p>
                                </div>
                            </div>
                            <div class="info-alert">
                                <i class="bi bi-info-circle"></i>
                                <span><strong>Opcional:</strong> Solo sube si quieres cambiar el documento</span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="upload-zone" data-target="documento_identidad">
                                <i class="bi bi-cloud-upload"></i>
                                <h6>Sube tu documento</h6>
                                <p>Arrastra tu archivo o haz clic para seleccionar</p>
                                <input type="file" name="documento_identidad" accept="image/*" 
                                       <?= ($usuario['estado_verificacion'] === 'no_solicitado') ? 'required' : '' ?>>
                                
                                <div class="preview-container">
                                    <img src="" alt="Vista previa" class="preview-image" onclick="openImageModal(this.src)">
                                    <button type="button" class="remove-preview">
                                        <i class="bi bi-x"></i> Quitar
                                    </button>
                                </div>
                            </div>
                            
                            <div class="requirements">
                                <h6><i class="bi bi-list-check"></i>Requisitos:</h6>
                                <ul>
                                    <li>DNI, Pasaporte o Cédula de Identidad</li>
                                    <li>Imagen clara y legible</li>
                                    <li>Formato: JPG, PNG o WEBP</li>
                                    <li>Tamaño máximo: 5MB</li>
                                    <li>Resolución mínima: 800x600px</li>
                                </ul>
                            </div>
                        </div>
                        
                        <!-- Selfie con documento -->
                        <div class="col-md-6 mb-4">
                            <h6>
                                <i class="bi bi-camera text-success me-2"></i>
                                Selfie con Documento
                            </h6>
                            
                            <?php if (!empty($usuario['selfie_documento_path']) && $usuario['estado_verificacion'] === 'rechazado'): ?>
                            <div class="mb-3 text-center">
                                <p class="small text-muted mb-2">Selfie actual:</p>
                                <img src="<?= htmlspecialchars($usuario['selfie_documento_path']) ?>" 
                                     class="existing-image" 
                                     alt="Selfie actual"
                                     onclick="openImageModal(this.src)"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                <div style="display: none;" class="text-muted">
                                    <i class="bi bi-file-image" style="font-size: 2rem;"></i>
                                    <p class="small">Imagen no disponible</p>
                                </div>
                            </div>
                            <div class="info-alert">
                                <i class="bi bi-info-circle"></i>
                                <span><strong>Opcional:</strong> Solo sube si quieres cambiar la selfie</span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="upload-zone" data-target="selfie_documento">
                                <i class="bi bi-person-circle"></i>
                                <h6>Sube tu selfie</h6>
                                <p>Tómate una foto sosteniendo tu documento</p>
                                <input type="file" name="selfie_documento" accept="image/*" 
                                       <?= ($usuario['estado_verificacion'] === 'no_solicitado') ? 'required' : '' ?>>
                                
                                <div class="preview-container">
                                    <img src="" alt="Vista previa" class="preview-image" onclick="openImageModal(this.src)">
                                    <button type="button" class="remove-preview">
                                        <i class="bi bi-x"></i> Quitar
                                    </button>
                                </div>
                            </div>
                            
                            <div class="requirements">
                                <h6><i class="bi bi-list-check"></i>Requisitos:</h6>
                                <ul>
                                    <li>Tu rostro debe ser claramente visible</li>
                                    <li>Sostén tu documento junto a tu cara</li>
                                    <li>Buena iluminación, sin sombras</li>
                                    <li>Formato: JPG, PNG o WEBP</li>
                                    <li>Tamaño máximo: 5MB</li>
                                    <li>Resolución mínima: 400x400px</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Información del usuario -->
                    <div class="user-info-section">
                        <h6>
                            <i class="bi bi-person-check"></i>
                            Información a verificar
                        </h6>
                        <div class="user-info-grid">
                            <div class="user-info-item">
                                <strong>Nombre:</strong>
                                <?= htmlspecialchars($usuario['full_name']) ?>
                            </div>
                            <div class="user-info-item">
                                <strong>Email:</strong>
                                <?= htmlspecialchars($usuario['email']) ?>
                            </div>
                            <div class="user-info-item">
                                <strong>Teléfono:</strong>
                                <?= htmlspecialchars($usuario['phone']) ?>
                            </div>
                            <div class="user-info-item">
                                <strong>DNI:</strong>
                                <?= htmlspecialchars($usuario['dni']) ?>
                            </div>
                        </div>
                        <div class="info-alert">
                            <i class="bi bi-info-circle"></i>
                            <span><strong>Importante:</strong> Asegúrate de que los datos de tu documento coincidan con la información de tu perfil.</span>
                        </div>
                    </div>
                    
                    <!-- Términos y envío -->
                    <div class="text-center">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="terminos" required>
                            <label class="form-check-label" for="terminos">
                                Acepto que mis documentos sean revisados para verificar mi identidad y entiendo que serán tratados de forma confidencial.
                            </label>
                        </div>
                        
                        <button type="submit" class="btn btn-lg" id="submitBtn">
                            <i class="bi bi-send"></i>
                            <?= $usuario['estado_verificacion'] === 'rechazado' ? 'Actualizar Documentos' : 'Enviar para Verificación' ?>
                        </button>
                    </div>
                </form>
            </div>
            
            <?php elseif ($mostrar_vista_pendiente): ?>
            
            <!-- Estado pendiente CON VISTA PREVIA MEJORADA -->
            <div class="content-state state-pending fade-in-up">
                <i class="bi bi-hourglass-half" style="color: #ffc107;"></i>
                <h4>Verificación en proceso</h4>
                <p>
                    Tus documentos están siendo revisados por nuestro equipo.<br>
                    Recibirás una notificación cuando el proceso esté completo.
                </p>
                
                <div class="info-alert">
                    <i class="bi bi-clock"></i>
                    <span><strong>Tiempo estimado:</strong> 24-48 horas laborables</span>
                </div>
            </div>

            <!-- Vista previa de documentos enviados -->
<section class="documents-gallery fade-in-up">
  <?php if (!empty($usuario['documento_identidad_path'])): ?>
    <div class="document">
      <img src="<?= htmlspecialchars($usuario['documento_identidad_path']) ?>" alt="Documento de identidad" />
      <div class="check">✓</div>
      <span>Documento de Identidad</span>
    </div>
  <?php endif; ?>

  <?php if (!empty($usuario['selfie_documento_path'])): ?>
    <div class="document">
      <img src="<?= htmlspecialchars($usuario['selfie_documento_path']) ?>" alt="Selfie con documento" />
      <div class="check">✓</div>
      <span>Selfie Verificada</span>
    </div>
  <?php endif; ?>
</section>

            
            <?php elseif ($mostrar_vista_aprobado): ?>
            
            <!-- Estado aprobado - Vista profesional -->
            <div class="verified-identity-section fade-in-up">
                

                <!-- Documentos verificados con vista previa -->
<section class="documents-gallery fade-in-up">
  <?php if (!empty($usuario['documento_identidad_path'])): ?>
    <div class="document">
      <img src="<?= htmlspecialchars($usuario['documento_identidad_path']) ?>" alt="Documento de identidad" />
      <div class="check">✓</div>
      <span>Documento de Identidad</span>
    </div>
  <?php endif; ?>

  <?php if (!empty($usuario['selfie_documento_path'])): ?>
    <div class="document">
      <img src="<?= htmlspecialchars($usuario['selfie_documento_path']) ?>" alt="Selfie con documento" />
      <div class="check">✓</div>
      <span>Selfie Verificada</span>
    </div>
  <?php endif; ?>
</section>


                <!-- Beneficios de verificación -->
                <div class="info-alert">
                   
                    <div>
                        <strong> 🔒 Identidad validada</strong> <br>Tu documentación ha sido revisada y aprobada. Con esta verificación, garantizamos un entorno más seguro y transparente para todos los usuarios
                    </div>
                </div>
            </div>
            
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal para vista completa de imágenes -->
    <div id="imageModal" class="image-modal">
        <span class="close-modal">&times;</span>
        <div class="modal-content">
            <img id="modalImage" class="modal-image" src="" alt="Vista completa">
        </div>
    </div>

    <?php include 'mobile-bottom-nav.php'; ?>


    <?php include 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Sistema de verificación cargado con protecciones de seguridad');
            
            // Aplicar animaciones de entrada escalonadas
            const elements = document.querySelectorAll('.fade-in-up');
            elements.forEach((element, index) => {
                element.style.opacity = '0';
                element.style.transform = 'translateY(30px)';
                setTimeout(() => {
                    element.style.transition = 'all 0.6s ease';
                    element.style.opacity = '1';
                    element.style.transform = 'translateY(0)';
                }, index * 200);
            });
            
            // Prevenir envío múltiple
            const form = document.getElementById('verificacionForm');
            if (form) {
                let formSubmitted = false;
                
                form.addEventListener('submit', function(e) {
                    if (formSubmitted) {
                        e.preventDefault();
                        return false;
                    }
                    
                    const terminos = document.getElementById('terminos');
                    if (!terminos.checked) {
                        e.preventDefault();
                        alert('Debes aceptar los términos para continuar.');
                        return false;
                    }
                    
                    formSubmitted = true;
                    const submitBtn = document.getElementById('submitBtn');
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Procesando...';
                    
                    // Timeout de seguridad
                    setTimeout(() => {
                        formSubmitted = false;
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="bi bi-send"></i> Enviar para Verificación';
                    }, 30000);
                });
            }
            
            // Manejo de drag & drop y preview
            document.querySelectorAll('.upload-zone').forEach(zone => {
                const input = zone.querySelector('input[type="file"]');
                const previewContainer = zone.querySelector('.preview-container');
                const previewImage = zone.querySelector('.preview-image');
                const removeBtn = zone.querySelector('.remove-preview');
                
                // Eventos de drag & drop
                zone.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    zone.classList.add('dragover');
                });
                
                zone.addEventListener('dragleave', () => {
                    zone.classList.remove('dragover');
                });
                
                zone.addEventListener('drop', (e) => {
                    e.preventDefault();
                    zone.classList.remove('dragover');
                    
                    const files = e.dataTransfer.files;
                    if (files.length > 0) {
                        input.files = files;
                        handleFileSelect(input, previewContainer, previewImage);
                    }
                });
                
                // Evento de selección de archivo
                input.addEventListener('change', () => {
                    handleFileSelect(input, previewContainer, previewImage);
                });
                
                // Botón para quitar preview
                if (removeBtn) {
                    removeBtn.addEventListener('click', () => {
                        input.value = '';
                        previewContainer.style.display = 'none';
                        zone.querySelector('i').style.display = 'block';
                        zone.querySelector('h6').style.display = 'block';
                        zone.querySelector('p').style.display = 'block';
                    });
                }
            });
            
            function handleFileSelect(input, previewContainer, previewImage) {
                const file = input.files[0];
                if (!file) return;
                
                // Validaciones básicas
                if (!file.type.startsWith('image/')) {
                    alert('Por favor selecciona solo archivos de imagen.');
                    input.value = '';
                    return;
                }
                
                if (file.size > 5 * 1024 * 1024) { // 5MB
                    alert('El archivo es demasiado grande. Máximo 5MB.');
                    input.value = '';
                    return;
                }
                
                // Mostrar preview
                const reader = new FileReader();
                reader.onload = (e) => {
                    previewImage.src = e.target.result;
                    previewContainer.style.display = 'block';
                    
                    // Ocultar elementos de upload
                    const zone = input.closest('.upload-zone');
                    zone.querySelector('i').style.display = 'none';
                    zone.querySelector('h6').style.display = 'none';
                    zone.querySelector('p').style.display = 'none';
                };
                reader.readAsDataURL(file);
            }
            
            console.log('Sistema de verificación protegido cargado correctamente');
        });

        // Función para abrir modal de imagen
        function openImageModal(imageSrc) {
            const modal = document.getElementById('imageModal');
            const modalImage = document.getElementById('modalImage');
            
            modal.style.display = 'block';
            modalImage.src = imageSrc;
            
            // Prevenir scroll del body
            document.body.style.overflow = 'hidden';
        }

        // Cerrar modal
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('imageModal');
            const closeBtn = document.querySelector('.close-modal');
            
            // Cerrar con X
            closeBtn.onclick = function() {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
            
            // Cerrar al hacer clic fuera de la imagen
            modal.onclick = function(event) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
            }
            
            // Cerrar con ESC
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && modal.style.display === 'block') {
                    modal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
            });
        });
    </script>
</body>
</html>