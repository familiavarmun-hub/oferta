<?php
/**
 * shop-request-create.php - Versión Elite Final con Cantidad
 * ✅ Cantidad añadida | ✅ Descripción en resumen
 * ✅ Google Maps Autocomplete | ✅ Diseño Premium
 */
session_start();
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['usuario_id'];
$user_name = $_SESSION['usuario_nombre'] ?? $_SESSION['full_name'] ?? 'Usuario';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
  <title>Crear Solicitud | SendVialo Premium</title>
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
      --slate-900: #0f172a;
      --slate-600: #475569;
      --slate-400: #94a3b8;
      --slate-200: #e2e8f0;
      --bg-body: #f8fafc;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', sans-serif; background-color: var(--bg-body); color: var(--slate-900); -webkit-font-smoothing: antialiased; }
    
    .container-wizard { max-width: 1000px; margin: 0 auto; padding: 80px 20px 100px; }
    
    /* --- HEADER --- */
    .wizard-header { margin-bottom: 40px; position: relative; }
    .wizard-header::after { content: ''; position: absolute; bottom: -12px; left: 0; width: 50px; height: 4px; background: var(--primary); border-radius: 10px; }
    .wizard-header h1 { font-size: 36px; font-weight: 900; letter-spacing: -1.5px; }

    /* --- STEPPER --- */
    .stepper-container {
      background: white; padding: 25px; border-radius: 24px; border: 1px solid var(--slate-200);
      margin-bottom: 30px; display: flex; justify-content: space-between; position: relative;
    }
    .stepper-line { position: absolute; top: 41px; left: 60px; right: 60px; height: 2px; background: var(--slate-200); z-index: 1; }
    .progress-fill { position: absolute; top: 0; left: 0; height: 100%; background: var(--primary); transition: 0.4s ease; }
    
    .step-item { position: relative; z-index: 2; display: flex; flex-direction: column; align-items: center; gap: 8px; flex: 1; }
    .step-circle { width: 32px; height: 32px; border-radius: 50%; background: white; border: 2px solid var(--slate-200); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 12px; color: var(--slate-400); transition: 0.3s; }
    .step-item.active .step-circle { background: var(--slate-900); border-color: var(--slate-900); color: white; transform: scale(1.1); }
    .step-item.completed .step-circle { background: var(--primary); border-color: var(--primary); color: white; }
    .step-label { font-size: 10px; font-weight: 700; color: var(--slate-400); text-transform: uppercase; letter-spacing: 0.5px; }
    .step-item.active .step-label { color: var(--slate-900); }

    /* --- FORM CARD --- */
    .form-card { background: white; padding: 45px; border-radius: 32px; border: 1px solid var(--slate-200); box-shadow: 0 15px 35px -5px rgba(0, 0, 0, 0.05); min-height: 450px; }
    .panel { display: none; animation: fadeIn 0.4s ease-out; }
    .panel.active { display: block; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

    .panel-title { font-size: 22px; font-weight: 900; margin-bottom: 10px; }
    .panel-subtitle { font-size: 14px; color: var(--slate-600); margin-bottom: 30px; }

    .form-group { margin-bottom: 22px; }
    .form-group label { display: block; font-weight: 700; margin-bottom: 8px; font-size: 13px; color: var(--slate-800); }
    
    .input-elite {
      width: 100%; padding: 14px 18px; border-radius: 14px;
      border: 1.5px solid var(--slate-200); background: #fcfdfe;
      font-size: 15px; font-weight: 500; transition: 0.3s;
    }
    .input-elite:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px var(--primary-soft); background: white; }

    /* --- CATEGORIES --- */
    .cat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 10px; }
    .cat-item { cursor: pointer; border: 1.5px solid var(--slate-200); border-radius: 16px; padding: 15px 10px; text-align: center; transition: 0.2s; }
    .cat-item i { font-size: 22px; color: var(--slate-400); margin-bottom: 8px; display: block; }
    .cat-item span { font-size: 11px; font-weight: 800; text-transform: uppercase; color: var(--slate-600); }
    input[type="radio"]:checked + .cat-item { background: var(--slate-900); border-color: var(--slate-900); }
    input[type="radio"]:checked + .cat-item i, input[type="radio"]:checked + .cat-item span { color: white; }

    /* --- URGENCY --- */
    .urg-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
    .urg-item { cursor: pointer; padding: 18px; border-radius: 16px; border: 1.5px solid var(--slate-200); text-align: center; transition: 0.2s; }
    .urg-item b { font-size: 13px; display: block; color: var(--slate-900); }
    input[type="radio"]:checked + .urg-item { border-color: var(--primary); background: var(--primary-soft); }

    /* --- UPLOAD ZONE --- */
    .drop-zone { border: 2px dashed var(--slate-200); border-radius: 20px; padding: 35px; text-align: center; background: #fafbfc; cursor: pointer; transition: 0.3s; }
    .drop-zone:hover { border-color: var(--primary); background: #f0fdf4; }
    .drop-zone i { font-size: 28px; color: var(--primary); margin-bottom: 10px; }
    .preview-box { display: grid; grid-template-columns: repeat(auto-fill, minmax(90px, 1fr)); gap: 12px; margin-top: 20px; }
    .preview-item { position: relative; width: 100%; aspect-ratio: 1; border-radius: 12px; overflow: hidden; border: 1px solid var(--slate-200); }
    .preview-item img { width: 100%; height: 100%; object-fit: cover; }
    .remove-img { position: absolute; top: 4px; right: 4px; background: #ef4444; color: white; border: none; border-radius: 50%; width: 20px; height: 20px; font-size: 10px; cursor: pointer; }

    /* --- FOOTER --- */
    .actions-footer { margin-top: 35px; padding-top: 25px; border-top: 1px solid var(--slate-200); display: flex; justify-content: space-between; }
    .btn-step { padding: 14px 28px; border-radius: 14px; font-weight: 800; font-size: 13px; text-transform: uppercase; cursor: pointer; border: none; display: flex; align-items: center; gap: 8px; transition: 0.3s; }
    .btn-prev { background: transparent; color: var(--slate-600); border: 1.5px solid var(--slate-200); }
    .btn-next { background: var(--slate-900); color: white; }
    .btn-submit { background: var(--primary); color: white; }
    .btn-step:hover { transform: translateY(-2px); }

    /* --- SUMMARY --- */
    .summary-card { background: #f8f9fa; border-radius: 20px; padding: 20px; margin-bottom: 20px; }
    .sum-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid rgba(0,0,0,0.04); }
    .sum-row:last-child { border-bottom: none; }
    .sum-label { font-size: 11px; font-weight: 700; color: var(--slate-400); text-transform: uppercase; }
    .sum-val { font-size: 13px; font-weight: 800; color: var(--slate-900); text-align: right; max-width: 60%; word-break: break-word; }
    .sum-description { font-size: 12px; color: var(--slate-600); line-height: 1.6; margin-top: 5px; }

    .grid-2 { display: grid; grid-template-columns: 100px 1fr; gap: 10px; align-items: center; }

    @media (max-width: 768px) {
      .form-card { padding: 30px 20px; border-radius: 0; border: none; }
      .container-wizard { padding-top: 20px; }
      .urg-grid { grid-template-columns: 1fr; }
      .stepper-container { padding: 15px; }
      .step-text { display: none; }
      .grid-2 { grid-template-columns: 80px 1fr; }
    }
  </style>
</head>
<body>
  <?php if (file_exists('header1.php')) include 'header1.php'; ?>

  <div class="container-wizard">
    <header class="wizard-header">
      <h1>Crear Solicitud</h1>
      <p>Publica tu pedido en minutos y recibe ofertas de viajeros.</p>
    </header>

    <div class="stepper-container">
      <div class="stepper-line"><div class="progress-fill" id="p-bar"></div></div>
      <div class="step-item active" data-step="1"><div class="step-circle">1</div><div class="step-label">Producto</div></div>
      <div class="step-item" data-step="2"><div class="step-circle">2</div><div class="step-label">Logística</div></div>
      <div class="step-item" data-step="3"><div class="step-circle">3</div><div class="step-label">Precio</div></div>
      <div class="step-item" data-step="4"><div class="step-circle">4</div><div class="step-label">Urgencia</div></div>
      <div class="step-item" data-step="5"><div class="step-circle">5</div><div class="step-label">Revisión</div></div>
    </div>

    <form id="form-solicitud">
      <div class="form-card">
        <!-- PASO 1 -->
        <div class="panel active" id="p-1">
          <h2 class="panel-title">¿Qué necesitas?</h2>
          <p class="panel-subtitle">Describe el artículo para los viajeros.</p>
          <div class="form-group">
            <label>Título del pedido *</label>
            <input type="text" id="title" name="title" required class="input-elite" placeholder="Ej: Zapatillas Nike Air Max 270 talla 42">
          </div>
          <div class="form-group">
            <label>Descripción detallada *</label>
            <textarea id="description" name="description" required class="input-elite" style="height: 100px;" placeholder="Detalles, talla, modelo..."></textarea>
          </div>
          <div class="form-group">
            <label>Cantidad *</label>
            <div class="grid-2">
              <input type="number" id="quantity" name="quantity" required class="input-elite" placeholder="1" min="1" value="1">
              <span style="font-size: 13px; color: var(--slate-600);">Unidades que necesitas</span>
            </div>
          </div>
          <div class="form-group">
            <label>Categoría *</label>
            <div class="cat-grid">
              <?php $c = ['Alimentos'=>'utensils','Ropa'=>'tshirt','Electrónicos'=>'laptop','libros'=>'book','cosmeticos'=>'spray-can','otros'=>'box'];
              foreach($c as $k=>$v): ?>
                <label><input type="radio" name="category" value="<?=$k?>" hidden <?=$k=='others'?'checked':''?>>
                <div class="cat-item"><i class="fas fa-<?=$v?>"></i><span><?=$k?></span></div></label>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="form-group">
            <label>Fotos (Hasta 5)</label>
            <div class="drop-zone" onclick="document.getElementById('img_input').click()">
              <i class="fas fa-camera"></i>
              <p>Subir imágenes</p>
              <input type="file" id="img_input" accept="image/*" multiple hidden>
            </div>
            <div class="preview-box" id="previews"></div>
          </div>
        </div>

        <!-- PASO 2 -->
        <div class="panel" id="p-2">
          <h2 class="panel-title">Ubicación</h2>
          <p class="panel-subtitle">Indica dónde comprar y dónde entregar.</p>
          <div class="form-group">
            <label>País de Origen</label>
            <input type="text" id="origin_country" name="origin_country" class="input-elite" placeholder="¿Dónde se compra?">
            <label style="display:flex; align-items:center; gap:8px; margin-top:10px; cursor:pointer;">
                <input type="checkbox" id="origin_flexible" name="origin_flexible" style="width:18px; height:18px; accent-color:var(--primary)">
                <span style="font-size:13px; font-weight:600;">Origen Flexible (Cualquier país)</span>
            </label>
          </div>
          <div class="form-group" style="margin-top:30px;">
            <label>Ciudad de Destino *</label>
            <input type="text" id="destination_city" name="destination_city" required class="input-elite" placeholder="¿Dónde lo recibes?">
          </div>
        </div>

        <!-- PASO 3 -->
        <div class="panel" id="p-3">
          <h2 class="panel-title">Presupuesto</h2>
          <p class="panel-subtitle">Cuánto pagarás por el envío.</p>
          <div style="display:grid; grid-template-columns:1fr 100px; gap:10px;">
            <input type="number" id="budget_amount" name="budget_amount" required class="input-elite" placeholder="0.00" step="0.01">
            <select id="budget_currency" name="budget_currency" class="input-elite">
                <option value="EUR">EUR</option>
                <option value="USD">USD</option>
            </select>
          </div>
          <label style="background:var(--primary-soft); padding:15px; border-radius:15px; display:flex; align-items:flex-start; gap:10px; margin-top:20px; border:1px solid #dcfce7; cursor:pointer;">
              <input type="checkbox" name="includes_product_cost" checked style="width:20px; height:20px; margin-top:3px; accent-color:var(--primary)">
              <span style="font-size:13px; color:var(--slate-600);"><b>Incluye costo del producto:</b> Si está marcado, el viajero compra el producto con este dinero. Si no, es solo tu comisión.</span>
          </label>
        </div>

        <!-- PASO 4 -->
        <div class="panel" id="p-4">
          <h2 class="panel-title">Urgencia</h2>
          <div class="urg-grid">
            <label><input type="radio" name="urgency" value="flexible" hidden checked><div class="urg-item"><b>Flexible</b></div></label>
            <label><input type="radio" name="urgency" value="moderada" hidden><div class="urg-item"><b>Moderada</b></div></label>
            <label><input type="radio" name="urgency" value="urgente" hidden><div class="urg-item"><b>Urgente</b></div></label>
          </div>
          <div class="form-group" style="margin-top:30px;">
            <label>Fecha Límite (Opcional)</label>
            <input type="date" name="deadline_date" class="input-elite" min="<?=date('Y-m-d')?>">
          </div>
        </div>

        <!-- PASO 5 -->
        <div class="panel" id="p-5">
          <h2 class="panel-title">Revisar y Publicar</h2>
          <div class="summary-card">
            <div class="sum-row">
              <span class="sum-label">Producto</span>
              <span class="sum-val" id="s-title">-</span>
            </div>
            <div class="sum-row">
              <span class="sum-label">Descripción</span>
              <span class="sum-val"><div class="sum-description" id="s-description">-</div></span>
            </div>
            <div class="sum-row">
              <span class="sum-label">Cantidad</span>
              <span class="sum-val" id="s-quantity">-</span>
            </div>
            <div class="sum-row">
              <span class="sum-label">Ruta</span>
              <span class="sum-val" id="s-route">-</span>
            </div>
            <div class="sum-row">
              <span class="sum-label">Presupuesto</span>
              <span class="sum-val" id="s-price">-</span>
            </div>
            <div class="sum-row">
              <span class="sum-label">Urgencia</span>
              <span class="sum-val" id="s-urg">-</span>
            </div>
          </div>
        </div>

        <div class="actions-footer">
          <button type="button" class="btn-step btn-prev" id="b-prev" style="visibility:hidden"><i class="fas fa-chevron-left"></i> Atrás</button>
          <button type="button" class="btn-step btn-next" id="b-next">Siguiente <i class="fas fa-chevron-right"></i></button>
          <button type="submit" class="btn-step btn-submit" id="b-submit" style="display:none">Publicar <i class="fas fa-paper-plane"></i></button>
        </div>
      </div>
    </form>
  </div>

  <?php if (file_exists('footer1.php')) include 'footer1.php'; ?>

  <script>
    let step = 1;
    const images = [];

    // --- GOOGLE AUTOCOMPLETE ---
    function initMap() {
        const options = { types: ['(cities)'] };
        const oriIn = document.getElementById('origin_country');
        const desIn = document.getElementById('destination_city');
        new google.maps.places.Autocomplete(oriIn, options);
        new google.maps.places.Autocomplete(desIn, options);
    }
    google.maps.event.addDomListener(window, 'load', initMap);

    // --- WIZARD NAVIGATION ---
    function updateUI() {
        $('.panel').removeClass('active');
        $(`#p-${step}`).addClass('active');
        $('.step-item').removeClass('active completed');
        $('.step-item').each((i, el) => {
            let s = i + 1;
            if(s < step) $(el).addClass('completed');
            if(s == step) $(el).addClass('active');
        });
        $('#p-bar').css('width', ((step-1)/4 * 100) + '%');
        $('#b-prev').css('visibility', step === 1 ? 'hidden' : 'visible');
        if(step === 5) { $('#b-next').hide(); $('#b-submit').show(); fillSummary(); }
        else { $('#b-next').show(); $('#b-submit').hide(); }
        window.scrollTo({top:0, behavior:'smooth'});
    }

    function fillSummary() {
        $('#s-title').text($('#title').val());
        $('#s-description').text($('#description').val() || 'Sin descripción');
        $('#s-quantity').text($('#quantity').val() + ' unidad(es)');
        const ori = $('#origin_flexible').is(':checked') ? 'Global' : ($('#origin_country').val() || 'No especificado');
        $('#s-route').text(`${ori} → ${$('#destination_city').val()}`);
        $('#s-price').text(`${$('#budget_amount').val()} ${$('#budget_currency').val()}`);
        const urgText = $('input[name="urgency"]:checked').val();
        $('#s-urg').text(urgText.charAt(0).toUpperCase() + urgText.slice(1));
    }

    $('#b-next').on('click', () => {
        let valid = true;
        $(`#p-${step} [required]`).each(function() {
            if(!$(this).val()) { valid = false; $(this).css('border-color','red'); }
            else $(this).css('border-color','');
        });
        if(valid) { step++; updateUI(); }
    });
    $('#b-prev').on('click', () => { step--; updateUI(); });

    // --- IMAGE LOGIC FIX ---
    $('#img_input').on('change', function(e) {
        const files = e.target.files;
        if(images.length + files.length > 5) return Swal.fire('Máximo 5 fotos','','warning');
        for(let f of files) {
            const reader = new FileReader();
            reader.onload = (e) => {
                images.push(e.target.result);
                renderPreviews();
            };
            reader.readAsDataURL(f);
        }
        $(this).val('');
    });

    function renderPreviews() {
        $('#previews').html(images.map((img, i) => `
            <div class="preview-item">
                <img src="${img}">
                <button type="button" class="remove-img" onclick="removeImg(${i})">×</button>
            </div>
        `).join(''));
    }
    window.removeImg = (i) => { images.splice(i, 1); renderPreviews(); };

    // --- SUBMIT ---
    $('#form-solicitud').on('submit', async function(e) {
        e.preventDefault();
        const btn = $('#b-submit');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        const fd = new FormData(this);
        fd.append('action', 'create_request');
        if(images.length) fd.append('reference_images', JSON.stringify(images));
        
        try {
            const res = await fetch('shop-requests-actions.php', { method:'POST', body:fd });
            const d = await res.json();
            if(d.success) {
                Swal.fire('¡Publicado!','','success').then(() => location.href='shop-requests-index.php');
            } else throw new Error(d.error);
        } catch(e) {
            Swal.fire('Error', e.message, 'error');
            btn.prop('disabled', false).html('Publicar <i class="fas fa-paper-plane"></i>');
        }
    });

    updateUI();
  </script>
</body>
</html>