<?php
/**
 * shop-edit-request.php - Edición Elite Premium
 * ✅ Diseño Profesional | ✅ Dictado por Voz | ✅ Google Maps | ✅ AJAX Saving
 */
session_start();
require_once 'insignias1.php';
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['usuario_id'];
$request_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($request_id <= 0) {
    header('Location: shop-my-requests.php');
    exit;
}

try {
    $stmt = $conexion->prepare("SELECT * FROM shop_requests WHERE id = :id AND user_id = :user_id");
    $stmt->execute([':id' => $request_id, ':user_id' => $user_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        header('Location: shop-my-requests.php');
        exit;
    }

    $editable_statuses = ['open', 'negotiating'];
    if (!in_array($request['status'], $editable_statuses)) {
        header('Location: shop-my-requests.php');
        exit;
    }

    $request['reference_images'] = !empty($request['reference_images']) ? json_decode($request['reference_images'], true) : [];
    $request['reference_links'] = !empty($request['reference_links']) ? json_decode($request['reference_links'], true) : [];

} catch (PDOException $e) {
    header('Location: shop-my-requests.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Editar Solicitud | SendVialo Premium</title>
    <link rel="stylesheet" href="../css/estilos.css"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="icon" href="../Imagenes/globo5.png" type="image/png"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Google Maps API -->
    <script src="https://maps.googleapis.com/maps/api/js?v=3.exp&libraries=places&key=AIzaSyA_5woNomNe9OV4x2Dj9RUSY_wh_t5n8Xc&language=es"></script>

    <style>
        :root {
            --primary: #41ba0d;
            --primary-dark: #2d8518;
            --primary-soft: #f1fcf0;
            --danger: #ef4444;
            --slate-900: #0f172a;
            --slate-800: #1e293b;
            --slate-600: #475569;
            --slate-400: #94a3b8;
            --slate-200: #e2e8f0;
            --bg-body: #f8fafc;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-body); color: var(--slate-900); -webkit-font-smoothing: antialiased; }
        
        .container-elite { max-width: 1000px; margin: 0 auto; padding: 100px 24px 60px; }

        /* --- HEADER --- */
        .page-header { margin-bottom: 40px; position: relative; }
        .page-header::after { content: ''; position: absolute; bottom: -12px; left: 0; width: 60px; height: 4px; background: var(--primary); border-radius: 10px; }
        .page-header h1 { font-size: 38px; font-weight: 900; letter-spacing: -1.5px; margin: 0; }
        .page-header p { font-size: 16px; color: var(--slate-600); margin-top: 10px; font-weight: 500; }

        /* --- FORM CARD --- */
        .edit-card {
            background: white; border-radius: 32px; border: 1px solid var(--slate-200);
            padding: 40px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
        }

        .form-section-title {
            display: flex; align-items: center; gap: 12px; font-size: 18px; 
            font-weight: 800; color: var(--slate-900); margin-bottom: 25px;
            padding-bottom: 10px; border-bottom: 1px solid var(--zinc-100);
        }
        .form-section-title i { color: var(--primary); }

        /* --- INPUTS & MIC --- */
        .form-group { margin-bottom: 25px; position: relative; }
        .form-group label { display: block; font-weight: 700; margin-bottom: 10px; font-size: 13px; color: var(--slate-800); text-transform: uppercase; }
        
        .input-elite {
            width: 100%; padding: 15px 18px; border-radius: 14px;
            border: 1.5px solid var(--slate-200); background: #fcfdfe;
            font-size: 15px; font-weight: 500; transition: 0.3s;
        }
        .input-elite:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px var(--primary-soft); background: white; }

        .btn-mic {
            position: absolute; right: 15px; top: 40px; border: none; background: transparent;
            color: var(--slate-400); font-size: 18px; cursor: pointer; transition: 0.3s; z-index: 10;
        }
        .btn-mic.listening { color: var(--danger); animation: pulse-mic 1s infinite; }
        @keyframes pulse-mic { 0% { transform: scale(1); } 50% { transform: scale(1.2); } 100% { transform: scale(1); } }

        /* --- GRID LAYOUT --- */
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

        /* --- MEDIA MANAGEMENT --- */
        .preview-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 15px; margin-top: 15px; }
        .preview-item { position: relative; aspect-ratio: 1; border-radius: 16px; overflow: hidden; border: 1px solid var(--slate-200); }
        .preview-item img { width: 100%; height: 100%; object-fit: cover; }
        .btn-remove { position: absolute; top: 8px; right: 8px; background: var(--danger); color: white; border: none; border-radius: 50%; width: 24px; height: 24px; font-size: 12px; cursor: pointer; display: flex; align-items: center; justify-content: center; }

        .link-pill { display: flex; align-items: center; justify-content: space-between; background: var(--zinc-100); padding: 10px 15px; border-radius: 12px; margin-bottom: 8px; font-size: 13px; font-weight: 600; }

        /* --- BUTTONS --- */
        .action-footer { margin-top: 40px; padding-top: 30px; border-top: 1px solid var(--slate-200); display: flex; gap: 15px; }
        .btn-elite { flex: 1; padding: 18px; border-radius: 16px; font-weight: 800; font-size: 14px; text-transform: uppercase; border: none; cursor: pointer; transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px; text-decoration: none; }
        .btn-save { background: var(--primary); color: white; box-shadow: 0 10px 20px rgba(65, 186, 13, 0.2); }
        .btn-cancel { background: var(--slate-900); color: white; }
        .btn-elite:hover { transform: translateY(-3px); filter: brightness(1.1); }

        @media (max-width: 768px) {
            .container-elite { padding-top: 85px; }
            .edit-card { padding: 30px 20px; border-radius: 0; border: none; background: transparent; }
            .form-row { grid-template-columns: 1fr; }
            .action-footer { flex-direction: column-reverse; }
        }
    </style>
</head>
<body>

    <?php if (file_exists('header1.php')) include 'header1.php'; ?>

    <div class="container-elite">
        <header class="page-header">
            <nav class="breadcrumb-nav" style="margin-bottom:15px; display:flex; gap:10px; font-size:12px; font-weight:800; text-transform:uppercase; color:var(--slate-400);">
                <a href="shop-my-requests.php" style="color:var(--primary); text-decoration:none;">Mis Pedidos</a>
                <span>/</span>
                <span style="color:var(--slate-900);">Editar</span>
            </nav>
            <h1>Editar Solicitud</h1>
            <p>Ajusta los detalles de tu pedido para recibir mejores propuestas.</p>
        </header>

        <div class="edit-card">
            <?php if ($request['status'] === 'negotiating'): ?>
            <div style="background: #fffbeb; border: 1px solid #fef3c7; padding: 15px; border-radius: 16px; color: #92400e; font-size: 13px; font-weight: 600; margin-bottom: 30px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-info-circle fa-lg"></i>
                Esta solicitud tiene negociaciones activas. Tus cambios notificarán a los viajeros.
            </div>
            <?php endif; ?>

            <form id="editForm">
                <input type="hidden" name="request_id" value="<?php echo $request_id; ?>">

                <!-- SECCIÓN 1: PRODUCTO -->
                <div class="form-section-title"><i class="fas fa-box"></i> Detalle del Producto</div>
                
                <div class="form-group">
                    <label>Título de la solicitud *</label>
                    <input type="text" id="title" name="title" class="input-elite" value="<?php echo htmlspecialchars($request['title']); ?>" required>
                    <button type="button" class="btn-mic" onclick="runVoice('title', this)"><i class="fas fa-microphone"></i></button>
                </div>

                <div class="form-group">
                    <label>Descripción detallada *</label>
                    <textarea id="description" name="description" class="input-elite" style="height:120px;" required><?php echo htmlspecialchars($request['description']); ?></textarea>
                    <button type="button" class="btn-mic" style="top:40px;" onclick="runVoice('description', this)"><i class="fas fa-microphone"></i></button>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Categoría</label>
                        <select name="category" class="input-elite">
                            <?php 
                            $cats = ['electronics'=>'Tecnología','fashion'=>'Moda','beauty'=>'Belleza','food'=>'Comida','books'=>'Libros','other'=>'Otro'];
                            foreach($cats as $val => $label): ?>
                                <option value="<?=$val?>" <?=$request['category']==$val?'selected':''?>><?=$label?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Cantidad</label>
                        <input type="number" name="quantity" class="input-elite" value="<?php echo $request['quantity']; ?>" min="1">
                    </div>
                </div>

                <!-- SECCIÓN 2: LOGÍSTICA -->
                <div class="form-section-title" style="margin-top:20px;"><i class="fas fa-map-marker-alt"></i> Ubicación y Entrega</div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>País de Origen</label>
                        <input type="text" id="origin_country" name="origin_country" class="input-elite" value="<?php echo htmlspecialchars($request['origin_country']); ?>" <?php echo $request['origin_flexible'] ? 'disabled' : ''; ?>>
                        <label style="display:flex; align-items:center; gap:8px; margin-top:10px; cursor:pointer;">
                            <input type="checkbox" name="origin_flexible" id="origin_flexible" <?php echo $request['origin_flexible'] ? 'checked' : ''; ?> style="width:18px; height:18px; accent-color:var(--primary)">
                            <span style="font-size:13px; font-weight:600; color:var(--slate-600);">Cualquier país (Flexible)</span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label>Ciudad de Destino *</label>
                        <input type="text" id="destination_city" name="destination_city" class="input-elite" value="<?php echo htmlspecialchars($request['destination_city']); ?>" required>
                    </div>
                </div>

                <!-- SECCIÓN 3: PRESUPUESTO -->
                <div class="form-section-title" style="margin-top:20px;"><i class="fas fa-wallet"></i> Presupuesto</div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Monto ofrecido *</label>
                        <input type="number" name="budget_amount" class="input-elite" value="<?php echo $request['budget_amount']; ?>" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label>Moneda</label>
                        <select name="budget_currency" class="input-elite">
                            <option value="EUR" <?php echo $request['budget_currency']=='EUR'?'selected':''; ?>>EUR (€)</option>
                            <option value="USD" <?php echo $request['budget_currency']=='USD'?'selected':''; ?>>USD ($)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label style="display:flex; align-items:center; gap:10px; cursor:pointer; background:var(--zinc-100); padding:15px; border-radius:14px;">
                        <input type="checkbox" name="includes_product_cost" <?php echo $request['includes_product_cost'] ? 'checked' : ''; ?> style="width:20px; height:20px; accent-color:var(--primary)">
                        <span style="font-size:13px; font-weight:600; color:var(--slate-600);">El presupuesto incluye el costo del producto</span>
                    </label>
                </div>

                <!-- SECCIÓN 4: IMÁGENES -->
                <div class="form-section-title" style="margin-top:20px;"><i class="fas fa-images"></i> Galería Actual</div>
                <div class="image-preview-grid" id="currentImages">
                    <?php foreach ($request['reference_images'] as $index => $img): ?>
                        <div class="preview-item" data-idx="<?=$index?>">
                            <img src="<?=$img?>" alt="Foto">
                            <button type="button" class="btn-remove" onclick="removeStoredImg(<?=$index?>)"><i class="fas fa-times"></i></button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="form-group" style="margin-top:20px;">
                    <label>Subir nuevas fotos (Máx 5 en total)</label>
                    <input type="file" id="new_images_input" accept="image/*" multiple class="input-elite">
                    <div class="preview-grid" id="newPreviews"></div>
                </div>

                <div class="action-footer">
                    <button type="button" class="btn-elite btn-cancel" onclick="window.location.href='shop-my-requests.php'">Cancelar</button>
                    <button type="submit" class="btn-elite btn-save"><i class="fas fa-save"></i> Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>

    <?php if (file_exists('footer1.php')) include 'footer1.php'; ?>

    <script>
        let removedStoredImages = [];
        let newImagesData = [];

        // --- GOOGLE AUTOCOMPLETE ---
        function initAutocomplete() {
            const options = { types: ['(cities)'] };
            new google.maps.places.Autocomplete(document.getElementById('origin_country'), options);
            new google.maps.places.Autocomplete(document.getElementById('destination_city'), options);
        }
        google.maps.event.addDomListener(window, 'load', initAutocomplete);

        // --- DICTADO POR VOZ ---
        function runVoice(targetId, btn) {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!SpeechRecognition) return Swal.fire('No compatible', 'Usa Chrome o Safari', 'error');

            const recognition = new SpeechRecognition();
            recognition.lang = 'es-ES';
            
            recognition.onstart = () => $(btn).addClass('listening');
            recognition.onresult = (e) => {
                const text = e.results[0][0].transcript;
                const input = document.getElementById(targetId);
                input.value = input.value ? input.value + " " + text : text;
            };
            recognition.onend = () => $(btn).removeClass('listening');
            recognition.start();
        }

        // --- IMÁGENES ---
        function removeStoredImg(idx) {
            removedStoredImages.push(idx);
            $(`.preview-item[data-idx="${idx}"]`).fadeOut();
        }

        $('#new_images_input').on('change', function(e) {
            const files = Array.from(e.target.files);
            files.forEach(f => {
                const reader = new FileReader();
                reader.onload = (e) => {
                    newImagesData.push({ data: e.target.result, name: f.name });
                    renderNewPreviews();
                };
                reader.readAsDataURL(f);
            });
            $(this).val('');
        });

        function renderNewPreviews() {
            $('#newPreviews').html(newImagesData.map((img, i) => `
                <div class="preview-item">
                    <img src="${img.data}">
                    <button type="button" class="btn-remove" onclick="removeNewImg(${i})">×</button>
                </div>
            `).join(''));
        }
        window.removeNewImg = (i) => { newImagesData.splice(i, 1); renderNewPreviews(); };

        // --- ENVÍO AJAX ---
        $('#editForm').on('submit', async function(e) {
            e.preventDefault();
            const btn = $(this).find('.btn-save');
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Guardando...');

            const fd = new FormData(this);
            fd.append('action', 'update_request');
            fd.append('removed_images', JSON.stringify(removedStoredImages));
            fd.append('new_images', JSON.stringify(newImagesData));
            
            // Fix para booleanos
            fd.set('origin_flexible', $('#origin_flexible').is(':checked') ? '1' : '0');
            fd.set('includes_product_cost', $('input[name="includes_product_cost"]').is(':checked') ? '1' : '0');

            try {
                const res = await fetch('shop-requests-actions.php', { method: 'POST', body: fd });
                const d = await res.json();
                if(d.success) {
                    Swal.fire('¡Éxito!', 'Cambios guardados', 'success').then(() => window.location.href='shop-my-requests.php');
                } else throw new Error(d.error);
            } catch(err) {
                Swal.fire('Error', err.message, 'error');
                btn.prop('disabled', false).html('<i class="fas fa-save"></i> Guardar Cambios');
            }
        });

        // Toggle origen
        $('#origin_flexible').on('change', function() {
            $('#origin_country').prop('disabled', this.checked).css('opacity', this.checked ? '0.5' : '1');
        });
    </script>
</body>
</html>