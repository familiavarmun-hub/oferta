<?php
// perfil_shop.php (dentro de /shop)

// ✅ Debug temporal (si ya te funciona, puedes quitar estas 3 líneas)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['usuario_id'])) {
    $current_url = 'shop/perfil_shop.php';
    echo "<script>
        localStorage.setItem('redirect_after_login', '$current_url');
        window.location.href = '../login.php';
    </script>";
    exit;
}

$usuario_id = (int)$_SESSION['usuario_id'];

/**
 * ✅ Header único reutilizable
 * - Trae config.php, insignias, notificaciones, etc.
 * - Renderiza el header y la barra móvil inferior (si corresponde)
 */
include __DIR__ . '/header1.php';

// --- OBTENER DATOS DEL USUARIO ---
$defaultImageURL = '../Imagenes/user-default.jpg';
$imageUrl   = $defaultImageURL;
$email      = $full_name = $username = $phone = $residence = '';
$verificado = 0;

$sql = "SELECT ruta_imagen, email, full_name, username, phone, residence, verificado
        FROM accounts
        WHERE id = :id";
$stmt = $conexion->prepare($sql);

if ($stmt) {
    $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    if ($stmt->execute()) {
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($row['ruta_imagen'])) {
                $imageUrl = "../mostrar_imagen.php?id=" . $usuario_id;
            }
            $email      = $row['email']      ?? '';
            $full_name  = $row['full_name']  ?? '';
            $username   = $row['username']   ?? '';
            $phone      = $row['phone']      ?? '';
            $residence  = $row['residence']  ?? '';
            $verificado = (int)($row['verificado'] ?? 0);
        }
    }
}

// --- OBTENER PROMEDIO DE VALORACIÓN ---
$promedio_valoracion = 0.0;
try {
    $sql_promedio = "SELECT AVG(valoracion) as promedio_valoracion
                     FROM comentarios
                     WHERE usuario_id = :usuario_id";
    $stmt_prom = $conexion->prepare($sql_promedio);
    $stmt_prom->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_prom->execute();
    $resultado_prom = $stmt_prom->fetch(PDO::FETCH_ASSOC);
    if ($resultado_prom && $resultado_prom['promedio_valoracion'] !== null) {
        $promedio_valoracion = round((float)$resultado_prom['promedio_valoracion'], 1);
    }
} catch (PDOException $e) {
    // En producción: log, no echo
}

// --- Generar HTML de la imagen de perfil con laurel e insignia ---
$imageHtmlWithLaurel = mostrarImagenConLaurel($imageUrl, $promedio_valoracion, $verificado);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Perfil de Usuario</title>

    <link rel="stylesheet" href="../css/perfil.css">
    <link rel="icon" href="../Imagenes/globo5.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

    <style>
        /* ✅ Como header1 es fijo, dejamos espacio arriba */
        body.loggedin { padding-top: 100px; }
        @media (max-width: 768px) { body.loggedin { padding-top: 80px; } }

        /* ✅ Contenedor de la foto - CENTRADO */
        .profile-container .perfil-image-wrapper {
            position: relative !important;
            width: 200px !important;
            height: 200px !important;
            margin: 0 auto !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        .profile-container .perfil-img-container {
            position: relative !important;
            width: 200px !important;
            height: 200px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        /* ✅ Foto principal - CENTRADA */
        .profile-container .perfil-foto-principal {
            width: 150px !important;
            height: 150px !important;
            border-radius: 50% !important;
            object-fit: cover !important;
            border: 3px solid #fff !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
            position: absolute !important;
            top: 50% !important;
            left: 50% !important;
            transform: translate(-50%, -50%) !important;
            z-index: 2 !important;
            display: block !important;
        }

        /* ✅ Laurel - CENTRADO */
        .profile-container .perfil-laurel-corona {
            position: absolute !important;
            top: 50% !important;
            left: 50% !important;
            width: 200px !important;
            height: 200px !important;
            transform: translate(-50%, -50%) !important;
            pointer-events: none !important;
            z-index: 1 !important;
        }

        /* ✅ Overlay - CENTRADO */
        .profile-container .profile-overlay {
            position: absolute !important;
            top: 50% !important;
            left: 50% !important;
            width: 200px !important;
            height: 200px !important;
            transform: translate(-50%, -50%) !important;
            border-radius: 50% !important;
            background: rgba(0,0,0,0.5) !important;
            display: flex !important;
            flex-direction: column !important;
            justify-content: center !important;
            align-items: center !important;
            opacity: 0 !important;
            transition: opacity 0.3s ease !important;
            color: #fff !important;
            cursor: pointer !important;
            z-index: 5 !important;
        }

        .profile-container .perfil-image-wrapper:hover .profile-overlay { opacity: 1 !important; }

        /* ✅ Insignia de verificación - CENTRADA */
        .profile-container .perfil-verificacion-wrapper {
            position: absolute !important;
            bottom: 10px !important;
            right: 10px !important;
            z-index: 10 !important;
        }

        .profile-container .perfil-verificacion-insignia { 
            width: 55px !important; 
            height: 55px !important; 
        }

        /* ✅ Evitar conflicto con el CSS del header (header1 tiene .profile-menu global) */
        .profile-container .profile-menu {
            position: absolute;
            top: 210px;
            left: 50%;
            transform: translateX(-50%);
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            padding: 10px;
            z-index: 3000;
            width: 220px;
        }
        .profile-container .profile-menu ul { list-style: none; margin: 0; padding: 0; }
        .profile-container .profile-menu li { padding: 10px; cursor: pointer; border-radius: 8px; }
        .profile-container .profile-menu li:hover { background: rgba(76, 175, 80, 0.10); }

        /* Estilo especial para el bloque de cerrar sesión */
        .grid-item-logout {
            background-color: #fff5f5 !important;
            border: 1px solid #ffdddd !important;
        }
        .grid-item-logout:hover {
            background-color: #ffe0e0 !important;
            border-color: #ff9999 !important;
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(255, 0, 0, 0.1);
        }
        .grid-item-logout h3 { color: #d32f2f; }

        #camera-modal {
            display: none;
            position: fixed;
            left: 0; top: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.7);
            justify-content: center;
            align-items: center;
            z-index: 5000;
        }
        #camera-modal .modal-content {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            position: relative;
            text-align: center;
        }
        #camera-modal video { max-width: 100%; border-radius: 8px; }
        #camera-modal button {
            margin-top: 10px;
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        #camera-modal .close-modal {
            position: absolute;
            top: 10px; right: 10px;
            cursor: pointer;
            font-size: 20px;
            color: #333;
        }
    </style>
</head>

<body class="loggedin">

    <div class="profile-container">
        <div class="profile-content">
            <div class="perfil-image-wrapper">
                <div class="perfil-img-container">
                    <?php
                    // ✅ Adaptamos SOLO las clases de la imagen para esta página (sin tocar header1)
                    $tmp = $imageHtmlWithLaurel;
                    $tmp = str_replace('profile-img', 'perfil-foto-principal', $tmp);
                    $tmp = str_replace('laurel-crown', 'perfil-laurel-corona', $tmp);
                    $tmp = str_replace('profile-img-container', 'perfil-img-container', $tmp);
                    $tmp = str_replace('verificacion-wrapper', 'perfil-verificacion-wrapper', $tmp);
                    $tmp = str_replace('verificacion-insignia', 'perfil-verificacion-insignia', $tmp);
                    echo $tmp;
                    ?>
                </div>

                <div class="profile-overlay" id="profile-overlay">
                    <div class="camera-icon"><i class="fas fa-camera"></i></div>
                    <span>CAMBIAR FOTO DEL PERFIL</span>
                </div>

                <!-- ✅ IMPORTANTE: id y clase NO deben chocar con header1 -->
                <div class="profile-menu" id="profile-photo-menu" style="display: none;">
                    <ul>
                        <li id="view-photo-option">Ver foto</li>
                        <li id="take-photo-option">Tomar foto</li>
                        <li id="changePhoto">Cargar foto</li>
                        <li id="delete-photo-option">Eliminar foto</li>
                    </ul>
                </div>

                <input type="file" id="file-input" accept="image/*" style="display: none;" onchange="changeProfileImage(this)">
            </div>
        </div>
    </div>

    <div class="residence-container">
        <b><?php echo htmlspecialchars($username); ?></b>
    </div>

    <div class="grid-container">

        <a href="shop_mi_perfil.php" class="grid-item">
            <img src="../Imagenes/profile.svg" alt="Mi perfil" class="grid-icon" />
            <div class="grid-text">
                <h3>Mi perfil</h3>
                <p>Datos personales y valoraciones de usuario.</p>
            </div>
        </a>

        <a href="shop_datos_personales_html.php" class="grid-item">
            <img src="../Imagenes/pencil.svg" alt="Datos personales" class="grid-icon" />
            <div class="grid-text">
                <h3>Datos personales</h3>
                <p>Registra o modifica tus datos.</p>
            </div>
        </a>

        <a href="shop_verificacion_identidad.php" class="grid-item">
            <img src="../Imagenes/veri.svg" alt="Verificación" class="grid-icon" />
            <div class="grid-text">
                <h3>Verificación</h3>
                <p>Proceso de verificación de identidad.</p>
            </div>
        </a>

        <a href="shop-verificacion-qr.php" class="grid-item">
            <img src="../Imagenes/qr3.svg" alt="Verificación y seguimiento" class="grid-icon" />
            <div class="grid-text">
                <h3>Verificación y seguimiento</h3>
                <p>Si eres el viajero, debes escanear el código QR.</p>
            </div>
        </a>

        <a href="shop-my-requests.php" class="grid-item">
            <img src="../shop/Imagenes/requests.svg" alt="Mis solicitudes" class="grid-icon" />
            <div class="grid-text">
                <h3>Mis solicitudes</h3>
                <p>Si eres el que compra al mundo debes mostrar el QR a la entrega.</p>
            </div>
        </a>

        <a href="shop-my-proposals.php" class="grid-item">
            <img src="../shop/Imagenes/proposal.svg" alt="Mis propuestas" class="grid-icon" />
            <div class="grid-text">
                <h3>Mis propuestas</h3>
                <p>Si eres el viajero, debes escanear el código QR.</p>
            </div>
        </a>

        <a href="shop-my-favorites.php" class="grid-item">
            <img src="Imagenes/favoritos.svg" alt="Mis favoritos" class="grid-icon" />
            <div class="grid-text">
                <h3>Mis favoritos</h3>
                <p>Productos/solicitudes guardadas.</p>
            </div>
        </a>

        <a href="shop_pagos_cobros.php" class="grid-item">
            <img src="/Imagenes/pagos.svg" alt="Pagos & Cobros" class="grid-icon" />
            <div class="grid-text">
                <h3>Pagos & Cobros</h3>
                <p>Administra pagos y cobros.</p>
            </div>
        </a>

        <a href="shop_registrar_metodo_pago.php" class="grid-item">
            <img src="../Imagenes/credit_card.svg" alt="Método de cobro" class="grid-icon" style="width: 100px; height: 100px;" />
            <div class="grid-text">
                <h3>Método de cobro</h3>
                <p>Configura tu cuenta bancaria.</p>
            </div>
        </a>

        <a href="../soporte_usuario.php" class="grid-item">
            <img src="../Imagenes/atencion.svg" alt="Atención al usuario" class="grid-icon" />
            <div class="grid-text">
                <h3>Atención al cliente</h3>
                <p>Soporte al usuario.</p>
            </div>
        </a>

        <a href="shop-logout.php" class="grid-item grid-item-logout">
            <img src="../Imagenes/logout.png" alt="Cerrar sesión" class="grid-icon" />
            <div class="grid-text">
                <h3>Cerrar sesión</h3>
            </div>
        </a>

    </div>

    <!-- Modal para ver la foto en grande -->
    <div id="profile-photo-modal" style="display: none;">
        <div style="position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); display: flex; justify-content: center; align-items: center;">
            <div style="background: white; padding: 20px; border-radius: 10px; position: relative;">
                <img id="modal-profile-image" src="" alt="Imagen de Perfil Ampliada" style="max-width: 500px; max-height: 80vh;">
                <span onclick="closeProfilePhotoModal()" style="position: absolute; right: 10px; top: 10px; cursor: pointer; font-size: 24px; color: #333;">
                    <i class="fas fa-times"></i>
                </span>
            </div>
        </div>
    </div>

    <!-- Modal para tomar foto -->
    <div id="camera-modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeCameraModal()">×</span>
            <video id="video" autoplay playsinline></video>
            <br>
            <button onclick="capturePhoto()">Capturar</button>
            <button onclick="closeCameraModal()">Cancelar</button>
        </div>
    </div>

    <script>
    let videoStream = null;

    document.addEventListener('DOMContentLoaded', function() {
        // ✅ Menu acciones foto
        const overlay = document.getElementById('profile-overlay');
        const menu = document.getElementById('profile-photo-menu');

        if (overlay && menu) {
            overlay.addEventListener('click', function(e) {
                e.stopPropagation();
                menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
            });

            menu.addEventListener('click', function(e) {
                e.stopPropagation();
            });

            document.addEventListener('click', function() {
                menu.style.display = 'none';
            });
        }

        const btnChange = document.getElementById('changePhoto');
        if (btnChange) {
            btnChange.addEventListener('click', function(e) {
                e.stopPropagation();
                document.getElementById('file-input').click();
            });
        }

        const btnView = document.getElementById('view-photo-option');
        if (btnView) {
            btnView.addEventListener('click', function() {
                const img = document.querySelector('.perfil-foto-principal');
                if (!img) return;
                document.getElementById('modal-profile-image').src = img.src;
                document.getElementById('profile-photo-modal').style.display = 'flex';
            });
        }

        const btnDelete = document.getElementById('delete-photo-option');
        if (btnDelete) {
            btnDelete.addEventListener('click', function() {
                deleteProfileImage();
            });
        }

        const btnTake = document.getElementById('take-photo-option');
        if (btnTake) {
            btnTake.addEventListener('click', function() {
                openCameraModal();
            });
        }
    });

    function changeProfileImage(input) {
        const file = input.files[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('imagen', file);

        const xhr = new XMLHttpRequest();
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) location.reload();
                else alert("Error al subir la imagen: " + xhr.responseText);
            }
        };
        xhr.open('POST', '../subir_imagenes.php', true);
        xhr.send(formData);
    }

    function deleteProfileImage() {
        const userId = <?php echo (int)$_SESSION['usuario_id']; ?>;
        const xhr = new XMLHttpRequest();
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) location.reload();
        };
        xhr.open('POST', '../eliminar_imagen.php', true);
        xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
        xhr.send('user_id=' + userId);
    }

    function closeProfilePhotoModal() {
        document.getElementById('profile-photo-modal').style.display = 'none';
    }

    function openCameraModal() {
        const cameraModal = document.getElementById('camera-modal');
        cameraModal.style.display = 'flex';

        navigator.mediaDevices.getUserMedia({ video: true })
            .then(function(stream) {
                videoStream = stream;
                document.getElementById('video').srcObject = stream;
            })
            .catch(function(err) {
                alert("No se pudo acceder a la cámara: " + err);
                closeCameraModal();
            });
    }

    function closeCameraModal() {
        document.getElementById('camera-modal').style.display = 'none';
        if (videoStream) {
            videoStream.getTracks().forEach(track => track.stop());
            videoStream = null;
        }
    }

    function capturePhoto() {
        const video = document.getElementById('video');
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;

        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

        canvas.toBlob(function(blob) {
            const formData = new FormData();
            formData.append('imagen', blob, 'captura.png');

            const xhr = new XMLHttpRequest();
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) location.reload();
                    else alert("Error al subir la imagen: " + xhr.responseText);
                }
            };
            xhr.open('POST', '../subir_imagenes.php', true);
            xhr.send(formData);
        }, 'image/png');

        closeCameraModal();
    }
    </script>

</body>
</html>