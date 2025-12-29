<?php
/**
 * shop-manage-products.php - Panel de Vendedor Premium
 * Diseño Elite igual a shop-request-create.php
 */
session_start();
require_once 'insignias1.php';
require_once '../config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['usuario_id'];
$user_name = $_SESSION['usuario_nombre'] ?? $_SESSION['full_name'] ?? 'Usuario';
$user_profile = obtenerPerfilCompletoUsuario($user_id, $conexion);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
  <title>Mis Productos | SendVialo Premium</title>
  <link rel="stylesheet" href="../css/estilos.css"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link rel="icon" href="../Imagenes/globo5.png" type="image/png"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

  <?php incluirEstilosInsignias(); ?>

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

    .container-wizard { max-width: 1000px; margin: 0 auto; padding: 100px 20px 100px; }

    /* --- HEADER --- */
    .wizard-header { margin-bottom: 40px; position: relative; }
    .wizard-header::after { content: ''; position: absolute; bottom: -12px; left: 0; width: 50px; height: 4px; background: var(--primary); border-radius: 10px; }
    .wizard-header h1 { font-size: 36px; font-weight: 900; letter-spacing: -1.5px; }
    .wizard-header p { color: var(--slate-600); margin-top: 8px; }

    /* --- TABS --- */
    .tabs-container { display: flex; gap: 10px; margin-bottom: 30px; }
    .tab-btn { padding: 14px 28px; border-radius: 14px; font-weight: 700; font-size: 14px; cursor: pointer; border: 2px solid var(--slate-200); background: white; color: var(--slate-600); transition: 0.3s; display: flex; align-items: center; gap: 8px; }
    .tab-btn:hover { border-color: var(--primary); }
    .tab-btn.active { background: var(--slate-900); border-color: var(--slate-900); color: white; }
    .tab-btn i { font-size: 16px; }

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
    .form-card { background: white; padding: 45px; border-radius: 32px; border: 1px solid var(--slate-200); box-shadow: 0 15px 35px -5px rgba(0, 0, 0, 0.05); min-height: 400px; }
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

    /* --- CURRENCY --- */
    .currency-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
    .currency-item { cursor: pointer; padding: 18px; border-radius: 16px; border: 1.5px solid var(--slate-200); text-align: center; transition: 0.2s; }
    .currency-item b { font-size: 18px; display: block; color: var(--slate-900); }
    .currency-item span { font-size: 11px; color: var(--slate-400); }
    input[type="radio"]:checked + .currency-item { border-color: var(--primary); background: var(--primary-soft); }

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

    .grid-2 { display: grid; grid-template-columns: 1fr 120px; gap: 15px; align-items: end; }
    .grid-3 { display: grid; grid-template-columns: 1fr 1fr 100px; gap: 15px; align-items: end; }

    /* --- PRODUCTS LIST --- */
    .products-section { display: none; }
    .products-section.active { display: block; }

    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 30px; }
    .stat-card { background: white; padding: 25px; border-radius: 20px; border: 1px solid var(--slate-200); text-align: center; }
    .stat-value { font-size: 2.5rem; font-weight: 900; color: var(--slate-900); }
    .stat-value.green { color: var(--primary); }
    .stat-label { font-size: 12px; font-weight: 600; color: var(--slate-400); text-transform: uppercase; margin-top: 5px; }

    .products-grid { display: flex; flex-direction: column; gap: 12px; }
    .product-row { background: white; border-radius: 16px; padding: 20px; border: 1px solid var(--slate-200); display: flex; align-items: center; gap: 20px; transition: 0.3s; }
    .product-row:hover { border-color: var(--primary); box-shadow: 0 5px 20px rgba(65,186,13,0.1); }
    .product-image { width: 80px; height: 80px; border-radius: 12px; object-fit: cover; }
    .product-info { flex: 1; }
    .product-name { font-weight: 800; font-size: 16px; margin-bottom: 5px; }
    .product-meta { display: flex; gap: 15px; flex-wrap: wrap; }
    .product-meta span { font-size: 13px; color: var(--slate-600); display: flex; align-items: center; gap: 5px; }
    .product-meta span i { color: var(--primary); }
    .product-actions { display: flex; gap: 8px; }
    .btn-icon { width: 40px; height: 40px; border-radius: 10px; border: 1.5px solid var(--slate-200); background: white; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s; }
    .btn-icon:hover { border-color: var(--primary); color: var(--primary); }
    .btn-icon.danger:hover { border-color: #ef4444; color: #ef4444; }

    .empty-state { text-align: center; padding: 60px 20px; }
    .empty-state i { font-size: 4rem; color: var(--slate-200); margin-bottom: 20px; }
    .empty-state h3 { font-weight: 800; margin-bottom: 10px; }
    .empty-state p { color: var(--slate-600); margin-bottom: 20px; }

    .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
    .status-badge.active { background: var(--primary-soft); color: var(--primary); }
    .status-badge.inactive { background: #fef2f2; color: #ef4444; }

    @media (max-width: 768px) {
      .container-wizard { padding-top: 80px; }
      .form-card { padding: 30px 20px; border-radius: 24px; }
      .stepper-container { padding: 15px; }
      .step-label { display: none; }
      .grid-2, .grid-3 { grid-template-columns: 1fr; }
      .tabs-container { flex-wrap: wrap; }
      .product-row { flex-direction: column; text-align: center; }
      .product-actions { justify-content: center; }
      .wizard-header h1 { font-size: 28px; }
    }
  </style>
</head>
<body>
  <?php include 'header2.php'; ?>

  <div class="container-wizard">
    <header class="wizard-header">
      <h1><i class="fas fa-store" style="color: var(--primary);"></i> Mis Productos</h1>
      <p>Gestiona tu inventario o añade nuevos productos para vender.</p>
    </header>

    <!-- TABS -->
    <div class="tabs-container">
      <button class="tab-btn active" onclick="showTab('products')">
        <i class="fas fa-boxes"></i> Mis Productos
      </button>
      <button class="tab-btn" onclick="showTab('add')">
        <i class="fas fa-plus-circle"></i> Añadir Producto
      </button>
    </div>

    <!-- ========== SECCION: MIS PRODUCTOS ========== -->
    <div id="tab-products" class="products-section active">
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-value" id="total-products">0</div>
          <div class="stat-label">Productos</div>
        </div>
        <div class="stat-card">
          <div class="stat-value green" id="total-active">0</div>
          <div class="stat-label">Activos</div>
        </div>
        <div class="stat-card">
          <div class="stat-value" id="total-sales">0</div>
          <div class="stat-label">Ventas</div>
        </div>
        <div class="stat-card">
          <div class="stat-value green" id="total-revenue">0</div>
          <div class="stat-label">Ingresos</div>
        </div>
      </div>

      <div class="form-card">
        <div class="products-grid" id="products-grid"></div>
        <div class="empty-state" id="empty-state" style="display:none;">
          <i class="fas fa-box-open"></i>
          <h3>No tienes productos</h3>
          <p>Comienza añadiendo tu primer producto</p>
          <button class="btn-step btn-submit" onclick="showTab('add')">
            <i class="fas fa-plus"></i> Añadir Producto
          </button>
        </div>
        <div class="loading" id="loading" style="text-align:center; padding:40px;">
          <i class="fas fa-spinner fa-spin" style="font-size:2rem; color:var(--primary);"></i>
          <p style="margin-top:15px; color:var(--slate-600);">Cargando productos...</p>
        </div>
      </div>
    </div>

    <!-- ========== SECCION: AÑADIR PRODUCTO ========== -->
    <div id="tab-add" class="products-section">
      <div class="stepper-container">
        <div class="stepper-line"><div class="progress-fill" id="p-bar"></div></div>
        <div class="step-item active" data-step="1"><div class="step-circle">1</div><div class="step-label">Producto</div></div>
        <div class="step-item" data-step="2"><div class="step-circle">2</div><div class="step-label">Precio</div></div>
        <div class="step-item" data-step="3"><div class="step-circle">3</div><div class="step-label">Imagenes</div></div>
        <div class="step-item" data-step="4"><div class="step-circle">4</div><div class="step-label">Envio</div></div>
        <div class="step-item" data-step="5"><div class="step-circle">5</div><div class="step-label">Revision</div></div>
      </div>

      <form id="product-form">
        <div class="form-card">
          <!-- PASO 1: Producto -->
          <div class="panel active" id="p-1">
            <h2 class="panel-title">¿Que vendes?</h2>
            <p class="panel-subtitle">Describe tu producto para los compradores.</p>

            <div class="form-group">
              <label>Nombre del producto *</label>
              <input type="text" id="name" name="name" required class="input-elite" placeholder="Ej: Zapatillas Nike Air Max 270">
            </div>

            <div class="form-group">
              <label>Descripcion detallada *</label>
              <textarea id="description" name="description" required class="input-elite" style="height: 100px;" placeholder="Detalles, caracteristicas, talla, color..."></textarea>
            </div>

            <div class="form-group">
              <label>Categoria *</label>
              <div class="cat-grid">
                <?php
                $cats = [
                  'food' => ['Alimentos', 'utensils'],
                  'fashion' => ['Moda', 'tshirt'],
                  'electronics' => ['Electronica', 'laptop'],
                  'books' => ['Libros', 'book'],
                  'cosmetics' => ['Cosmeticos', 'spray-can'],
                  'crafts' => ['Artesanias', 'palette'],
                  'other' => ['Otros', 'box']
                ];
                foreach($cats as $key => $cat): ?>
                  <label><input type="radio" name="category" value="<?=$key?>" hidden <?=$key=='other'?'checked':''?>>
                  <div class="cat-item"><i class="fas fa-<?=$cat[1]?>"></i><span><?=$cat[0]?></span></div></label>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <!-- PASO 2: Precio -->
          <div class="panel" id="p-2">
            <h2 class="panel-title">Precio y Stock</h2>
            <p class="panel-subtitle">Define el precio y cantidad disponible.</p>

            <div class="form-group">
              <label>Precio *</label>
              <div class="grid-2">
                <input type="number" id="price" name="price" required class="input-elite" placeholder="0.00" step="0.01" min="0">
                <select id="currency" name="currency" class="input-elite">
                  <option value="EUR">EUR</option>
                  <option value="USD">USD</option>
                  <option value="BOB">BOB</option>
                </select>
              </div>
            </div>

            <div class="form-group">
              <label>Stock disponible *</label>
              <input type="number" id="stock" name="stock" required class="input-elite" placeholder="1" min="1" value="1">
            </div>

            <div class="form-group">
              <label>Condicion del producto</label>
              <div class="currency-grid">
                <label><input type="radio" name="condition" value="new" hidden checked>
                <div class="currency-item"><b>Nuevo</b><span>Sin usar</span></div></label>
                <label><input type="radio" name="condition" value="like_new" hidden>
                <div class="currency-item"><b>Como nuevo</b><span>Casi sin uso</span></div></label>
                <label><input type="radio" name="condition" value="used" hidden>
                <div class="currency-item"><b>Usado</b><span>Buen estado</span></div></label>
              </div>
            </div>
          </div>

          <!-- PASO 3: Imagenes -->
          <div class="panel" id="p-3">
            <h2 class="panel-title">Fotos del producto</h2>
            <p class="panel-subtitle">Añade hasta 5 imagenes de tu producto.</p>

            <div class="form-group">
              <div class="drop-zone" onclick="document.getElementById('img_input').click()">
                <i class="fas fa-camera"></i>
                <p><b>Haz clic para subir</b> o arrastra las imagenes</p>
                <span style="font-size:12px; color:var(--slate-400);">PNG, JPG hasta 5MB</span>
                <input type="file" id="img_input" accept="image/*" multiple hidden>
              </div>
              <div class="preview-box" id="previews"></div>
            </div>
          </div>

          <!-- PASO 4: Envio -->
          <div class="panel" id="p-4">
            <h2 class="panel-title">Informacion de envio</h2>
            <p class="panel-subtitle">¿Desde donde envias y a donde?</p>

            <div class="form-group">
              <label>Ciudad de origen *</label>
              <input type="text" id="origin_city" name="origin_city" required class="input-elite" placeholder="¿Desde donde envias?">
            </div>

            <div class="form-group">
              <label>Ciudad de destino *</label>
              <input type="text" id="destination_city" name="destination_city" required class="input-elite" placeholder="¿A donde puede llegar?">
            </div>

            <div class="form-group">
              <label>Fecha de disponibilidad</label>
              <input type="date" id="available_date" name="available_date" class="input-elite" min="<?=date('Y-m-d')?>">
            </div>
          </div>

          <!-- PASO 5: Revision -->
          <div class="panel" id="p-5">
            <h2 class="panel-title">Revisar y Publicar</h2>
            <p class="panel-subtitle">Verifica la informacion antes de publicar.</p>

            <div class="summary-card">
              <div class="sum-row">
                <span class="sum-label">Producto</span>
                <span class="sum-val" id="s-name">-</span>
              </div>
              <div class="sum-row">
                <span class="sum-label">Categoria</span>
                <span class="sum-val" id="s-category">-</span>
              </div>
              <div class="sum-row">
                <span class="sum-label">Precio</span>
                <span class="sum-val" id="s-price">-</span>
              </div>
              <div class="sum-row">
                <span class="sum-label">Stock</span>
                <span class="sum-val" id="s-stock">-</span>
              </div>
              <div class="sum-row">
                <span class="sum-label">Condicion</span>
                <span class="sum-val" id="s-condition">-</span>
              </div>
              <div class="sum-row">
                <span class="sum-label">Ruta</span>
                <span class="sum-val" id="s-route">-</span>
              </div>
              <div class="sum-row">
                <span class="sum-label">Imagenes</span>
                <span class="sum-val" id="s-images">-</span>
              </div>
            </div>
          </div>

          <div class="actions-footer">
            <button type="button" class="btn-step btn-prev" id="b-prev" style="visibility:hidden"><i class="fas fa-chevron-left"></i> Atras</button>
            <button type="button" class="btn-step btn-next" id="b-next">Siguiente <i class="fas fa-chevron-right"></i></button>
            <button type="submit" class="btn-step btn-submit" id="b-submit" style="display:none">Publicar <i class="fas fa-paper-plane"></i></button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <script>
    const userId = <?= $user_id ?>;
    let step = 1;
    const totalSteps = 5;
    const images = [];
    let products = [];

    // ===== TABS =====
    function showTab(tab) {
      document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
      document.querySelectorAll('.products-section').forEach(s => s.classList.remove('active'));

      if (tab === 'products') {
        document.querySelector('.tab-btn:nth-child(1)').classList.add('active');
        document.getElementById('tab-products').classList.add('active');
        loadProducts();
      } else {
        document.querySelector('.tab-btn:nth-child(2)').classList.add('active');
        document.getElementById('tab-add').classList.add('active');
        resetForm();
      }
    }

    // ===== WIZARD NAVIGATION =====
    function goTo(s) {
      step = s;
      document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
      document.getElementById('p-' + s).classList.add('active');

      document.querySelectorAll('.step-item').forEach((el, i) => {
        el.classList.remove('active', 'completed');
        if (i + 1 < s) el.classList.add('completed');
        if (i + 1 === s) el.classList.add('active');
      });

      const pct = ((s - 1) / (totalSteps - 1)) * 100;
      document.getElementById('p-bar').style.width = pct + '%';

      document.getElementById('b-prev').style.visibility = s === 1 ? 'hidden' : 'visible';
      document.getElementById('b-next').style.display = s === totalSteps ? 'none' : 'flex';
      document.getElementById('b-submit').style.display = s === totalSteps ? 'flex' : 'none';

      if (s === totalSteps) updateSummary();
    }

    document.getElementById('b-next').onclick = () => {
      if (validateStep(step)) goTo(step + 1);
    };
    document.getElementById('b-prev').onclick = () => goTo(step - 1);

    function validateStep(s) {
      if (s === 1) {
        const name = document.getElementById('name').value.trim();
        const desc = document.getElementById('description').value.trim();
        const cat = document.querySelector('input[name="category"]:checked');
        if (!name) { Swal.fire({icon:'warning',title:'Nombre requerido',confirmButtonColor:'#41ba0d'}); return false; }
        if (!desc) { Swal.fire({icon:'warning',title:'Descripcion requerida',confirmButtonColor:'#41ba0d'}); return false; }
        if (!cat) { Swal.fire({icon:'warning',title:'Selecciona una categoria',confirmButtonColor:'#41ba0d'}); return false; }
      }
      if (s === 2) {
        const price = document.getElementById('price').value;
        const stock = document.getElementById('stock').value;
        if (!price || price <= 0) { Swal.fire({icon:'warning',title:'Precio invalido',confirmButtonColor:'#41ba0d'}); return false; }
        if (!stock || stock < 1) { Swal.fire({icon:'warning',title:'Stock invalido',confirmButtonColor:'#41ba0d'}); return false; }
      }
      if (s === 4) {
        const origin = document.getElementById('origin_city').value.trim();
        const dest = document.getElementById('destination_city').value.trim();
        if (!origin) { Swal.fire({icon:'warning',title:'Ciudad de origen requerida',confirmButtonColor:'#41ba0d'}); return false; }
        if (!dest) { Swal.fire({icon:'warning',title:'Ciudad de destino requerida',confirmButtonColor:'#41ba0d'}); return false; }
      }
      return true;
    }

    function updateSummary() {
      const categoryLabels = {food:'Alimentos',fashion:'Moda',electronics:'Electronica',books:'Libros',cosmetics:'Cosmeticos',crafts:'Artesanias',other:'Otros'};
      const conditionLabels = {new:'Nuevo',like_new:'Como nuevo',used:'Usado'};
      const currencySymbols = {EUR:'€',USD:'$',BOB:'Bs'};

      document.getElementById('s-name').textContent = document.getElementById('name').value || '-';
      document.getElementById('s-category').textContent = categoryLabels[document.querySelector('input[name="category"]:checked')?.value] || '-';
      document.getElementById('s-price').textContent = currencySymbols[document.getElementById('currency').value] + document.getElementById('price').value;
      document.getElementById('s-stock').textContent = document.getElementById('stock').value + ' unidades';
      document.getElementById('s-condition').textContent = conditionLabels[document.querySelector('input[name="condition"]:checked')?.value] || '-';
      document.getElementById('s-route').textContent = document.getElementById('origin_city').value + ' -> ' + document.getElementById('destination_city').value;
      document.getElementById('s-images').textContent = images.length + ' imagen(es)';
    }

    // ===== IMAGE UPLOAD =====
    document.getElementById('img_input').onchange = function(e) {
      const files = Array.from(e.target.files);
      files.forEach(file => {
        if (images.length >= 5) return;
        const reader = new FileReader();
        reader.onload = ev => {
          images.push({ file, data: ev.target.result });
          renderPreviews();
        };
        reader.readAsDataURL(file);
      });
    };

    function renderPreviews() {
      const box = document.getElementById('previews');
      box.innerHTML = images.map((img, i) => `
        <div class="preview-item">
          <img src="${img.data}" alt="Preview">
          <button type="button" class="remove-img" onclick="removeImage(${i})">x</button>
        </div>
      `).join('');
    }

    function removeImage(i) {
      images.splice(i, 1);
      renderPreviews();
    }

    // ===== FORM SUBMIT =====
    document.getElementById('product-form').onsubmit = async function(e) {
      e.preventDefault();

      Swal.fire({title:'Publicando...',allowOutsideClick:false,didOpen:()=>Swal.showLoading()});

      const formData = new FormData();
      formData.append('action', 'add');
      formData.append('name', document.getElementById('name').value);
      formData.append('description', document.getElementById('description').value);
      formData.append('category', document.querySelector('input[name="category"]:checked').value);
      formData.append('price', document.getElementById('price').value);
      formData.append('currency', document.getElementById('currency').value);
      formData.append('stock', document.getElementById('stock').value);
      formData.append('condition', document.querySelector('input[name="condition"]:checked').value);
      formData.append('origin_city', document.getElementById('origin_city').value);
      formData.append('destination_city', document.getElementById('destination_city').value);
      formData.append('available_date', document.getElementById('available_date').value);

      images.forEach((img, i) => formData.append('images[]', img.file));

      try {
        const res = await fetch('shop-actions.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
          Swal.fire({icon:'success',title:'Producto publicado!',text:'Tu producto ya esta visible',confirmButtonColor:'#41ba0d'})
          .then(() => {
            resetForm();
            showTab('products');
          });
        } else {
          Swal.fire({icon:'error',title:'Error',text:data.error||'No se pudo publicar',confirmButtonColor:'#41ba0d'});
        }
      } catch (err) {
        Swal.fire({icon:'error',title:'Error',text:'Error de conexion',confirmButtonColor:'#41ba0d'});
      }
    };

    function resetForm() {
      document.getElementById('product-form').reset();
      images.length = 0;
      renderPreviews();
      goTo(1);
    }

    // ===== LOAD PRODUCTS =====
    async function loadProducts() {
      const grid = document.getElementById('products-grid');
      const loading = document.getElementById('loading');
      const empty = document.getElementById('empty-state');

      loading.style.display = 'block';
      grid.innerHTML = '';
      empty.style.display = 'none';

      try {
        const res = await fetch(`shop-actions.php?action=get_products&seller_id=${userId}`);
        const data = await res.json();

        loading.style.display = 'none';

        if (data.success && data.products && data.products.length > 0) {
          products = data.products;
          renderProducts();
          updateStats(data);
        } else {
          empty.style.display = 'block';
        }
      } catch (err) {
        loading.style.display = 'none';
        empty.style.display = 'block';
      }
    }

    function renderProducts() {
      const grid = document.getElementById('products-grid');
      const currencySymbols = {EUR:'€',USD:'$',BOB:'Bs'};

      grid.innerHTML = products.map(p => {
        const img = p.primary_image || (p.images && p.images[0]) || '../Imagenes/product-default.jpg';
        const price = (currencySymbols[p.currency] || '€') + parseFloat(p.price).toFixed(2);
        return `
          <div class="product-row">
            <img class="product-image" src="${img}" alt="${p.name}" onerror="this.src='../Imagenes/product-default.jpg'">
            <div class="product-info">
              <div class="product-name">${p.name}</div>
              <div class="product-meta">
                <span><i class="fas fa-tag"></i> ${price}</span>
                <span><i class="fas fa-box"></i> Stock: ${p.stock}</span>
                <span><i class="fas fa-map-marker-alt"></i> ${p.origin_city || 'N/A'} -> ${p.destination_city || 'N/A'}</span>
                <span class="status-badge ${p.active ? 'active' : 'inactive'}">${p.active ? 'Activo' : 'Inactivo'}</span>
              </div>
            </div>
            <div class="product-actions">
              <button class="btn-icon" onclick="editProduct(${p.id})" title="Editar"><i class="fas fa-edit"></i></button>
              <button class="btn-icon" onclick="toggleProduct(${p.id}, ${p.active})" title="${p.active ? 'Desactivar' : 'Activar'}">
                <i class="fas fa-${p.active ? 'eye-slash' : 'eye'}"></i>
              </button>
              <button class="btn-icon danger" onclick="deleteProduct(${p.id})" title="Eliminar"><i class="fas fa-trash"></i></button>
            </div>
          </div>
        `;
      }).join('');
    }

    function updateStats(data) {
      document.getElementById('total-products').textContent = data.stats?.total_products || products.length;
      document.getElementById('total-active').textContent = data.stats?.active_products || products.filter(p => p.active).length;
      document.getElementById('total-sales').textContent = data.stats?.total_sales || 0;
      const revenue = data.stats?.total_revenue || 0;
      document.getElementById('total-revenue').textContent = '€' + parseFloat(revenue).toFixed(2);
    }

    // ===== PRODUCT ACTIONS =====
    async function toggleProduct(id, currentState) {
      const res = await fetch('shop-actions.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=toggle&product_id=${id}`
      });
      const data = await res.json();
      if (data.success) {
        Swal.fire({toast:true,position:'top-end',icon:'success',title:currentState?'Producto desactivado':'Producto activado',showConfirmButton:false,timer:1500});
        loadProducts();
      }
    }

    async function deleteProduct(id) {
      const result = await Swal.fire({
        title: '¿Eliminar producto?',
        text: 'Esta accion no se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Si, eliminar',
        cancelButtonText: 'Cancelar'
      });

      if (result.isConfirmed) {
        const res = await fetch('shop-actions.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: `action=delete&product_id=${id}`
        });
        const data = await res.json();
        if (data.success) {
          Swal.fire({toast:true,position:'top-end',icon:'success',title:'Producto eliminado',showConfirmButton:false,timer:1500});
          loadProducts();
        }
      }
    }

    function editProduct(id) {
      Swal.fire({icon:'info',title:'Proximamente',text:'La edicion de productos estara disponible pronto',confirmButtonColor:'#41ba0d'});
    }

    // ===== INIT =====
    document.addEventListener('DOMContentLoaded', () => {
      loadProducts();
    });
  </script>
</body>
</html>
