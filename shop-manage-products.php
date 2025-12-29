<?php
// shop-manage-products.php – Panel del vendedor PREMIUM SOFISTICADO con valoraciones unificadas
session_start();

// Verificar autenticación mejorada
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

// Incluir sistema de insignias unificado (corregido)
require_once 'insignias1.php';
require_once '../config.php';

// Variables de usuario para JavaScript
$user_id = $_SESSION['usuario_id'];
$user_name = $_SESSION['usuario_nombre'] ?? $_SESSION['full_name'] ?? 'Usuario';

// Obtener información completa del usuario con valoraciones
$user_profile = obtenerPerfilCompletoUsuario($user_id, $conexion);
$rating_data = $user_profile['rating_data'] ?? ['average_rating' => 0, 'total_ratings' => 0, 'rating_display' => 'N/A', 'badge_type' => 'basic'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Productos - SendVialo Shop</title>
    <link rel="stylesheet" href="../css/estilos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.3.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="icon" href="../Imagenes/globo5.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <?php incluirEstilosInsignias(); ?>
    
    <style>
/* ============================================
   PROPUESTA 2: BORDES GRUESOS Y DISEÑO BOLD
   Colores: Negro (#000000), Blanco (#FFFFFF) y Verde (#42BA25)
   
   INSTRUCCIONES:
   1. Abre tu archivo shop-manage-products.html
   2. Busca la línea 16 donde dice <style>
   3. BORRA todo el contenido entre <style> y </style>
   4. COPIA todo este archivo y pégalo dentro del <style>
   5. ¡Listo!
   ============================================ */

/* Reset y configuración base */
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: 'Inter', sans-serif;
  background: #f8f9fa;
  color: #333;
}

.shop-dashboard {
  max-width: 1200px;
  margin: 0 auto;
  padding: 20px;
}

/* ============================================
   HEADER ESTILO SHOP-INDEX (VERDE CLARO FRESCO)
   ============================================ */
.dashboard-header {
  background: linear-gradient(135deg, #e8f8e4 0%, #d4f1cd 100%);
  color: #1a1a1a;
  padding: 90px 40px;
  position: relative;
  overflow: hidden;
  min-height: 400px;
  display: flex;
  align-items: center;
  margin-bottom: 40px;
}

.dashboard-header::before {
  content: '';
  position: absolute;
  top: -100px;
  right: -100px;
  width: 400px;
  height: 400px;
  background: #42ba25;
  opacity: 0.05;
  border-radius: 50%;
}

.dashboard-header::after {
  content: '';
  position: absolute;
  bottom: -80px;
  left: -80px;
  width: 350px;
  height: 350px;
  background: #5cd63e;
  opacity: 0.05;
  border-radius: 50%;
}

.dashboard-header .header-content {
  position: relative;
  z-index: 2;
  text-align: center;
  width: 100%;
}

.dashboard-header .premium-badge {
  display: inline-block;
  background: white;
  color: #42ba25;
  padding: 10px 26px;
  border-radius: 50px;
  font-weight: 700;
  font-size: 0.85rem;
  text-transform: uppercase;
  letter-spacing: 1px;
  margin-bottom: 25px;
  box-shadow: 0 4px 15px rgba(66, 186, 37, 0.15);
}

.dashboard-header h1 {
  font-size: 3rem;
  font-weight: 800;
  color: #1a1a1a;
  margin-bottom: 20px;
  line-height: 1.2;
}

.dashboard-header h1 i {
  color: #42ba25;
  margin-right: 15px;
}

.dashboard-header p {
  color: #4a5568;
  font-size: 1.2rem;
  font-weight: 400;
  max-width: 700px;
  margin: 0 auto;
  line-height: 1.6;
}

/* ============================================
   CARDS DE ESTADÍSTICAS
   ============================================ */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 20px;
  margin-bottom: 30px;
}

.stat-card {
  background: #FFFFFF;
  padding: 35px 30px;
  border: 4px solid #000000;
  border-radius: 20px;
  text-align: center;
  transition: all 0.3s ease;
  position: relative;
}

.stat-card::before {
  content: '';
  position: absolute;
  top: -4px;
  left: -4px;
  right: -4px;
  bottom: -4px;
  background: #42BA25;
  z-index: -1;
  opacity: 0;
  transition: opacity 0.3s ease;
}

.stat-card:hover::before {
  opacity: 1;
}

.stat-card:hover {
  transform: translate(-4px, -4px);
  border-color: #000000;
}

.stat-value {
  font-size: 3rem;
  font-weight: 900;
  color: #000000;
  margin-bottom: 8px;
  display: block;
}

.stat-label {
  color: #666;
  font-size: 0.85rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1px;
}

/* ============================================
   BARRA DE ACCIONES
   ============================================ */
.actions-bar {
  background: #FFFFFF;
  padding: 25px;
  margin-bottom: 30px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 20px;
  border: 3px solid #000000;
  border-radius: 20px;
}

.search-filters {
  display: flex;
  gap: 15px;
  flex-wrap: wrap;
}

.search-input, .filter-select {
  padding: 12px 18px;
  border: 3px solid #000000;
  border-radius: 12px;
  font-size: 0.95rem;
  background: #FFFFFF;
  color: #000000;
  font-weight: 600;
  transition: all 0.3s ease;
}

.search-input:focus, .filter-select:focus {
  outline: none;
  border-color: #42BA25;
  box-shadow: 4px 4px 0 #42BA25;
}

.btn {
  padding: 14px 28px;
  border: 3px solid #000000;
  border-radius: 12px;
  font-weight: 800;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 10px;
  font-size: 0.95rem;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.btn-primary {
  background: #42BA25;
  color: #FFFFFF;
}

.btn-primary:hover {
  background: #FFFFFF;
  color: #42BA25;
  transform: translate(-3px, -3px);
  box-shadow: 3px 3px 0 #000000;
}

.btn-secondary {
  background: #f8f9fa;
  color: #333;
  border: 1px solid #dee2e6;
}

.btn-secondary:hover {
  background: #e9ecef;
}

/* ============================================
   TABLA DE PRODUCTOS
   ============================================ */
.products-table {
  background: #FFFFFF;
  border: 4px solid #000000;
  border-radius: 20px;
  overflow: hidden;
}

.table-header {
  padding: 30px;
  background: #000000;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 4px solid #42BA25;
}

.table-header h3 {
  font-size: 1.5rem;
  font-weight: 900;
  color: #FFFFFF;
  text-transform: uppercase;
  letter-spacing: 1px;
  margin: 0;
}

.table-header span {
  color: #42BA25;
  font-weight: 700;
}

.products-grid {
  padding: 30px;
  display: grid;
  gap: 25px;
}



/* ============================================
   CARDS DE PRODUCTOS
   ============================================ */
.product-row {
  background: #FFFFFF;
  padding: 25px;
  border: 3px solid #000000;
  border-radius: 16px;
  display: grid;
  grid-template-columns: 90px 1fr auto;
  gap: 25px;
  align-items: center;
  transition: all 0.3s ease;
  position: relative;
}

.product-row::after {
  content: '';
  position: absolute;
  top: 0;
  right: 0;
  width: 0;
  height: 100%;
  background: #42BA25;
  transition: width 0.3s ease;
  z-index: 0;
}

.product-row:hover::after {
  width: 6px;
}

.product-row:hover {
  transform: translate(-4px, -4px);
  box-shadow: 4px 4px 0 #42BA25;
}

.product-image {
  width: 90px;
  height: 90px;
  border-radius: 12px;
  object-fit: cover;
  border: 3px solid #000000;
  position: relative;
  z-index: 1;
}

.product-info {
  flex: 1;
  position: relative;
  z-index: 1;
}

.product-name {
  font-size: 1.15rem;
  font-weight: 900;
  color: #000000;
  margin-bottom: 10px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.product-details {
  display: flex;
  gap: 15px;
  flex-wrap: wrap;
  align-items: center;
}

.product-price {
  font-size: 1.5rem;
  font-weight: 900;
  color: #42BA25;
}

.product-stock, .product-trip {
  font-size: 0.8rem;
  color: #000000;
  background: #FFFFFF;
  padding: 5px 12px;
  border: 2px solid #000000;
  border-radius: 8px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.product-status {
  padding: 6px 14px;
  border: 2px solid #000000;
  border-radius: 8px;
  font-size: 0.75rem;
  font-weight: 900;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.status-active {
  background: #42BA25;
  color: #FFFFFF;
}

.status-inactive {
  background: #DC3545;
  color: #FFFFFF;
}

.status-low-stock {
  background: #FFC107;
  color: #000000;
}

/* ============================================
   BOTONES DE ACCIÓN
   ============================================ */
.product-actions {
  display: flex;
  gap: 10px;
  position: relative;
  z-index: 1;
}

.btn-sm {
  padding: 10px 14px;
  border: 3px solid #000000;
  border-radius: 10px;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 0.3s ease;
  font-weight: 800;
}

.btn-edit {
  background: #42BA25;
  color: #FFFFFF;
}

.btn-edit:hover {
  background: #FFFFFF;
  color: #42BA25;
  transform: translate(-2px, -2px);
  box-shadow: 2px 2px 0 #000000;
}

.btn-delete {
  background: #DC3545;
  color: #FFFFFF;
}

.btn-delete:hover {
  background: #FFFFFF;
  color: #DC3545;
  transform: translate(-2px, -2px);
  box-shadow: 2px 2px 0 #000000;
}

.btn-toggle {
  background: #000000;
  color: #FFFFFF;
}

.btn-toggle:hover {
  background: #FFFFFF;
  color: #000000;
  transform: translate(-2px, -2px);
  box-shadow: 2px 2px 0 #000000;
}

/* ============================================
   RESPONSIVE - MÓVILES
   ============================================ */
@media (max-width: 768px) {
  .dashboard-header h1 {
    font-size: 1.8rem;
  }
  
  .product-row {
    grid-template-columns: 1fr;
    text-align: center;
  }
  
  .product-image {
    margin: 0 auto;
  }
  
  .product-actions {
    justify-content: center;
  }
  
  .actions-bar {
    flex-direction: column;
  }
  
  .search-filters {
    width: 100%;
    flex-direction: column;
  }
}

/* Otros estilos necesarios que pueda tener tu HTML */
.modal {
  display: none;
  position: fixed;
  z-index: 10000;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.5);
}

.modal-content {
  background: #fff;
  margin: 5% auto;
  padding: 0;
  border-radius: 15px;
  width: 90%;
  max-width: 600px;
  max-height: 90vh;
  overflow-y: auto;
}

.modal-header {
  padding: 20px;
  border-bottom: 1px solid #dee2e6;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-title {
  font-size: 1.25rem;
  font-weight: 600;
  margin: 0;
}

.close {
  background: none;
  border: none;
  font-size: 1.5rem;
  cursor: pointer;
  color: #666;
}

.modal-body {
  padding: 20px;
}

.form-group {
  margin-bottom: 20px;
}

.form-label {
  display: block;
  margin-bottom: 5px;
  font-weight: 600;
  color: #333;
}

.form-control {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #dee2e6;
  border-radius: 6px;
  font-size: 14px;
}

.form-control:focus {
  outline: none;
  border-color: #42ba25;
  box-shadow: 0 0 0 0.2rem rgba(66, 186, 37, 0.25);
}

/* Estilos para inputs de Google Places */
.google-places-input {
  transition: all 0.3s ease;
  border: 2px solid #e2e8f0;
}

.google-places-input:focus {
  border-color: #42ba25;
  box-shadow: 0 0 0 3px rgba(66, 186, 37, 0.1);
  background: #f7fef5;
}

.google-places-input.place-selected {
  border-color: #42ba25;
  background: #f0fdf4;
  animation: placeSelected 0.5s ease;
}

@keyframes placeSelected {
  0% { transform: scale(1); }
  50% { transform: scale(1.02); }
  100% { transform: scale(1); }
}

/* Mejora del dropdown de autocompletado de Google */
.pac-container {
  border-radius: 12px;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
  margin-top: 5px;
  border: 2px solid #42ba25;
  font-family: 'Inter', sans-serif;
}

.pac-item {
  padding: 12px 15px;
  border-top: 1px solid #e8e8e8;
  cursor: pointer;
  transition: background 0.2s ease;
}

.pac-item:hover {
  background: #f0fdf4;
}

.pac-item-selected {
  background: #e8f8e4;
}

.pac-icon {
  margin-right: 10px;
}

.pac-item-query {
  color: #1a1a1a;
  font-weight: 600;
  font-size: 14px;
}

.textarea {
  resize: vertical;
  min-height: 100px;
}

.file-upload {
  border: 2px dashed #dee2e6;
  border-radius: 8px;
  padding: 20px;
  text-align: center;
  cursor: pointer;
  transition: all 0.3s ease;
}

.file-upload:hover {
  border-color: #667eea;
  background: #f8f9ff;
}

.file-upload.dragover {
  border-color: #667eea;
  background: #f0f2ff;
}

.image-preview {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
  gap: 10px;
  margin-top: 15px;
}

.preview-item {
  position: relative;
  width: 100px;
  height: 100px;
}

.preview-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 6px;
}

.remove-image {
  position: absolute;
  top: -8px;
  right: -8px;
  background: #dc3545;
  color: #fff;
  border: none;
  border-radius: 50%;
  width: 20px;
  height: 20px;
  font-size: 12px;
  cursor: pointer;
}

.currency-selector {
  display: flex;
  gap: 10px;
}

.currency-option {
  flex: 1;
  padding: 10px;
  border: 2px solid #dee2e6;
  border-radius: 6px;
  text-align: center;
  cursor: pointer;
  transition: all 0.3s ease;
}

.currency-option.selected {
  border-color: #667eea;
  background: #f0f2ff;
}

.empty-state {
  text-align: center;
  padding: 60px 20px;
  color: #666;
}

.empty-state i {
  font-size: 4rem;
  margin-bottom: 20px;
  color: #ddd;
}

.loading {
  text-align: center;
  padding: 40px;
  color: #666;
}

.loading i {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% {
    transform: rotate(0);
  }
  100% {
    transform: rotate(360deg);
  }
}

/* Botón más pequeño y con más espacio arriba */
.empty-state .btn {
  padding: 10px 20px;
  font-size: 0.85rem;
  margin-top: 30px;
}
    </style>
</head>
<body data-user-id="<?= $user_id ?>" data-user-name="<?= htmlspecialchars($user_name) ?>">
    <?php if (file_exists('shop-header.php')) include 'shop-header.php'; ?>

    <div class="shop-dashboard">
        <div class="dashboard-header">
            <div class="header-content">
                <span class="premium-badge">
                    <?php if ($user_profile['verificado'] ?? false): ?>
                        <i class="fas fa-check-circle"></i> Vendedor Verificado
                    <?php else: ?>
                        <i class="fas fa-store"></i> Panel de Vendedor
                    <?php endif; ?>
                </span>

                <h1><i class="fas fa-box-open"></i> Mi Tienda SendVialo</h1>
                <p>Gestiona tus productos, crea nuevas rutas y aprovecha tus viajes para vender</p>
            </div>
        </div>

        <!-- Debug info (temporal) -->
        <div class="debug-info" id="debug-info">
            <strong>Debug Info:</strong><br>
            <div id="debug-content"></div>
        </div>

        <!-- Estadísticas con valoraciones -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value" id="total-products">0</div>
                <div class="stat-label">Productos Activos</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="total-sales">0</div>
                <div class="stat-label">Ventas Totales</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="total-revenue">€0</div>
                <div class="stat-label">Ingresos</div>
            </div>
            <div class="stat-card rating-card">
                <div class="stat-value" id="avg-rating">
                    <?php if ($rating_data['average_rating'] > 0): ?>
                        <span class="rating-stars">★</span>
                        <span class="<?= $rating_data['badge_type'] ?>-text"><?= $rating_data['average_rating'] ?></span>
                    <?php else: ?>
                        <span>N/A</span>
                    <?php endif; ?>
                </div>
                <div class="stat-label">Valoración</div>
            </div>
        </div>

        <!-- Barra de acciones -->
        <div class="actions-bar">
            <div class="search-filters">
                <input type="text" class="search-input" id="search-products" placeholder="Buscar productos...">
                <select class="filter-select" id="filter-status">
                    <option value="">Todos los estados</option>
                    <option value="active">Activos</option>
                    <option value="inactive">Inactivos</option>
                    <option value="low-stock">Stock bajo</option>
                </select>
                <select class="filter-select" id="filter-category">
                    <option value="">Todas las categorías</option>
                    <option value="food">Comida y bebidas</option>
                    <option value="crafts">Artesanías</option>
                    <option value="fashion">Moda y accesorios</option>
                    <option value="electronics">Electrónicos</option>
                    <option value="books">Libros</option>
                    <option value="cosmetics">Cosméticos</option>
                </select>
            </div>
            <div>
                <button class="btn btn-primary" onclick="openAddProductModal()">
                    <i class="fas fa-plus"></i>
                    Añadir Producto
                </button>
            </div>
        </div>

        <!-- Tabla de productos -->
        <div class="products-table">
            <div class="table-header">
                <h3>Mis Productos</h3>
                <span id="products-count">0 productos</span>
            </div>
            
            <div class="products-grid" id="products-grid">
                <!-- Los productos se cargarán aquí -->
            </div>

            <!-- Estado de carga -->
            <div class="loading" id="loading">
                <i class="fas fa-spinner"></i>
                <p>Cargando productos...</p>
            </div>

            <!-- Estado vacío -->
            <div class="empty-state" id="empty-state" style="display: none;">
                <i class="fas fa-box-open"></i>
                <h3>No tienes productos</h3>
                <p>¡Comienza a vender añadiendo tu primer producto!</p>
                <button class="btn btn-primary" onclick="openAddProductModal()">
                    <i class="fas fa-plus"></i>
                    Añadir mi primer producto
                </button>
            </div>
        </div>
    </div>

    <!-- Modal para añadir/editar producto -->
    <div id="product-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modal-title">Añadir Producto</h3>
                <button class="close" onclick="closeProductModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="product-form" enctype="multipart/form-data">
                    <input type="hidden" id="product-id" name="product_id">
                    
                    <div class="form-group">
                        <label class="form-label" for="product-name">Nombre del Producto *</label>
                        <input type="text" class="form-control" id="product-name" name="name" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="product-description">Descripción *</label>
                        <textarea class="form-control textarea" id="product-description" name="description" required placeholder="Describe tu producto detalladamente..."></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Precio *</label>
                        <div style="display: flex; gap: 15px; align-items: flex-end;">
                            <input type="number" class="form-control" id="product-price" name="price" step="0.01" min="0" required style="flex: 1;">
                            <div class="currency-selector">
                                <div class="currency-option selected" data-currency="EUR">
                                    <strong>EUR</strong><br>
                                    <small>Euro €</small>
                                </div>
                                <div class="currency-option" data-currency="USD">
                                    <strong>USD</strong><br>
                                    <small>Dólar $</small>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" id="product-currency" name="currency" value="EUR">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="product-category">Categoría *</label>
                        <select class="form-control" id="product-category" name="category" required>
                            <option value="">Seleccionar categoría</option>
                            <option value="food">Comida y bebidas</option>
                            <option value="crafts">Artesanías</option>
                            <option value="fashion">Moda y accesorios</option>
                            <option value="electronics">Electrónicos</option>
                            <option value="books">Libros</option>
                            <option value="cosmetics">Cosméticos</option>
                            <option value="others">Otros</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="product-stock">Cantidad disponible *</label>
                        <input type="number" class="form-control" id="product-stock" name="stock" min="1" value="1" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="product-weight">Peso aproximado (kg)</label>
                        <input type="number" class="form-control" id="product-weight" name="weight" step="0.1" min="0" placeholder="0.5">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="product-dimensions">Dimensiones (cm)</label>
                        <input type="text" class="form-control" id="product-dimensions" name="dimensions" placeholder="20x15x10">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="trip-select">Viaje asociado *</label>
                        <div style="display: flex; gap: 10px;">
                            <select class="form-control" id="trip-select" name="trip_id" style="flex: 1;" required>
                                <option value="">Selecciona un viaje...</option>
                                <!-- Los viajes se cargarán dinámicamente -->
                            </select>
                            <button type="button" class="btn btn-secondary" onclick="toggleNewTripForm()" style="white-space: nowrap;">
                                <i class="fas fa-plus"></i> Nueva Ruta
                            </button>
                        </div>

                        <!-- Formulario para crear nueva ruta (oculto por defecto) -->
                        <div id="new-trip-form" style="display: none; margin-top: 15px; padding: 15px; background: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6;">
                            <h4 style="margin: 0 0 15px 0; font-size: 1rem; color: #333;">
                                <i class="fas fa-route"></i> Crear Nueva Ruta
                            </h4>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                                <div>
                                    <label class="form-label" for="new-trip-origin">
                                        <i class="fas fa-map-marker-alt" style="color: #42ba25;"></i> Ciudad de origen *
                                        <span style="font-size: 0.75rem; color: #666; font-weight: normal; margin-left: 5px;">
                                            <i class="fas fa-magic" style="color: #42ba25;"></i> Autocompletado
                                        </span>
                                    </label>
                                    <div style="position: relative;">
                                        <input type="text" class="form-control google-places-input" id="new-trip-origin" name="new_trip_origin"
                                               placeholder="Escribe para buscar ciudad..."
                                               style="padding-left: 40px;">
                                        <i class="fas fa-search" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #42ba25; pointer-events: none;"></i>
                                    </div>
                                </div>
                                <div>
                                    <label class="form-label" for="new-trip-destination">
                                        <i class="fas fa-map-pin" style="color: #42ba25;"></i> Ciudad de destino *
                                        <span style="font-size: 0.75rem; color: #666; font-weight: normal; margin-left: 5px;">
                                            <i class="fas fa-magic" style="color: #42ba25;"></i> Autocompletado
                                        </span>
                                    </label>
                                    <div style="position: relative;">
                                        <input type="text" class="form-control google-places-input" id="new-trip-destination" name="new_trip_destination"
                                               placeholder="Escribe para buscar ciudad..."
                                               style="padding-left: 40px;">
                                        <i class="fas fa-search" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #42ba25; pointer-events: none;"></i>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="form-label" for="new-trip-date">Fecha estimada del viaje *</label>
                                <input type="date" class="form-control" id="new-trip-date" name="new_trip_date">
                            </div>

                            <div style="margin-top: 10px; padding: 10px; background: #e7f3ff; border-radius: 4px;">
                                <small><i class="fas fa-info-circle"></i> Esta ruta se creará cuando guardes el producto y quedará asociada a tu cuenta.</small>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Imágenes del Producto</label>
                        <div class="file-upload" id="file-upload">
                            <i class="fas fa-cloud-upload-alt fa-3x"></i>
                            <p style="margin: 15px 0 10px; font-weight: 600;">Arrastra las imágenes aquí o haz clic para seleccionar</p>
                            <p style="margin: 0;"><small>Máximo 5 imágenes, 5MB cada una</small></p>
                            <input type="file" id="product-images" name="images[]" multiple accept="image/*" style="display: none;">
                        </div>
                        <div class="image-preview" id="image-preview"></div>
                    </div>

                    <div style="display: flex; gap: 15px; justify-content: flex-end; margin-top: 40px;">
                        <button type="button" class="btn btn-secondary" onclick="closeProductModal()">
                            <i class="fas fa-times"></i>
                            Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Guardar Producto
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if (file_exists('footer1.php')) include 'footer1.php'; ?>

    <script>
        // VARIABLES GLOBALES MEJORADAS
        let products = [];
        let filteredProducts = [];
        let isEditing = false;
        let selectedImages = [];
        let debugMode = false;

        // OBTENER DATOS DEL USUARIO DESDE PHP
        const currentUserId = <?= $user_id ?>;
        const currentUserName = '<?= addslashes($user_name) ?>';
        const currentUserRating = <?= $rating_data['average_rating'] ?>;
        const currentUserRatingCount = <?= $rating_data['total_ratings'] ?>;
        const currentUserBadgeType = '<?= $rating_data['badge_type'] ?>';

        // FUNCIÓN DE DEBUG MEJORADA
        function debugLog(message, data = null) {
            const timestamp = new Date().toLocaleTimeString();
            const debugContent = document.getElementById('debug-content');
            const logEntry = `[${timestamp}] ${message}${data ? '\n' + JSON.stringify(data, null, 2) : ''}\n\n`;
            if (debugContent && debugMode) {
                debugContent.innerHTML += logEntry;
                debugContent.scrollTop = debugContent.scrollHeight;
            }
            console.log(`[SHOP DEBUG] ${message}`, data);
        }

        function toggleDebugInfo() {
            debugMode = !debugMode;
            const debugInfo = document.getElementById('debug-info');
            debugInfo.style.display = debugMode ? 'block' : 'none';
            if (debugMode) {
                debugLog('Debug mode activado');
                debugLog('Usuario actual:', {
                    id: currentUserId, 
                    name: currentUserName,
                    rating: currentUserRating,
                    ratingCount: currentUserRatingCount,
                    badgeType: currentUserBadgeType
                });
            }
        }

        // INICIALIZACIÓN
        document.addEventListener('DOMContentLoaded', function() {
            debugLog('🎯 Inicializando shop-manage-products con valoraciones unificadas...');
            debugLog('Usuario logueado:', {
                id: currentUserId,
                name: currentUserName,
                rating: currentUserRating,
                ratingCount: currentUserRatingCount,
                badgeType: currentUserBadgeType
            });

            loadProducts();
            loadStats();
            loadUserTrips();
            setupEventListeners();
            // initGoogleMapsAutocomplete() se llama ahora cuando se muestra el formulario
        });

        // EVENT LISTENERS
        function setupEventListeners() {
            debugLog('⚙️ Configurando event listeners...');
            
            document.getElementById('search-products').addEventListener('input', applyFilters);
            document.getElementById('filter-status').addEventListener('change', applyFilters);
            document.getElementById('filter-category').addEventListener('change', applyFilters);

            // Selector de moneda
            document.querySelectorAll('.currency-option').forEach(option => {
                option.addEventListener('click', function() {
                    document.querySelectorAll('.currency-option').forEach(opt => opt.classList.remove('selected'));
                    this.classList.add('selected');
                    document.getElementById('product-currency').value = this.dataset.currency;
                    debugLog('💱 Moneda seleccionada:', this.dataset.currency);
                });
            });

            // File upload
            const fileUpload = document.getElementById('file-upload');
            const fileInput = document.getElementById('product-images');

            fileUpload.addEventListener('click', () => fileInput.click());
            fileInput.addEventListener('change', handleImageSelect);

            // Drag & Drop
            fileUpload.addEventListener('dragover', e => {
                e.preventDefault(); 
                fileUpload.classList.add('dragover');
            });
            
            fileUpload.addEventListener('dragleave', () => {
                fileUpload.classList.remove('dragover');
            });
            
            fileUpload.addEventListener('drop', e => {
                e.preventDefault(); 
                fileUpload.classList.remove('dragover'); 
                handleImageDrop(e.dataTransfer.files);
            });

            // Modal
            document.getElementById('product-modal').addEventListener('click', e => {
                if (e.target === e.currentTarget) closeProductModal();
            });

            // Form submit
            document.getElementById('product-form').addEventListener('submit', handleProductSubmit);
        }

        // CARGAR DATOS
        async function loadProducts() {
            debugLog('📦 Cargando productos del usuario...');
            try {
                const res = await fetch('shop-actions.php?action=get_user_products');
                const text = await res.text();
                debugLog('📡 Response raw (primeros 300 chars):', text.substring(0, 300));
                
                const data = JSON.parse(text);
                debugLog('📊 Data parseada:', data);
                
                if (data.success) {
                    products = data.products || [];
                    filteredProducts = [...products];
                    debugLog(`✅ ${products.length} productos cargados`);
                    renderProducts();
                } else {
                    debugLog('❌ Error en loadProducts:', data.error);
                    products = []; 
                    filteredProducts = [];
                    renderProducts();
                }
            } catch (e) {
                debugLog('❌ Exception en loadProducts:', e.message);
                products = []; 
                filteredProducts = [];
                renderProducts();
            }
        }

        async function loadStats() {
            debugLog('📊 Cargando estadísticas...');
            try {
                const res = await fetch('shop-actions.php?action=get_seller_stats');
                const text = await res.text();
                debugLog('📡 Stats response (primeros 200 chars):', text.substring(0, 200));
                
                const d = JSON.parse(text);
                debugLog('📊 Stats parseadas:', d);
                
                if (d.success) {
                    document.getElementById('total-products').textContent = d.stats.active_products || 0;
                    document.getElementById('total-sales').textContent = d.stats.total_sales || 0;
                    document.getElementById('total-revenue').textContent = '€' + (d.stats.total_revenue || 0).toFixed(2);
                    
                    // Actualizar valoración con el sistema unificado
                    const avgRatingElement = document.getElementById('avg-rating');
                    if (d.stats.average_rating > 0) {
                        avgRatingElement.innerHTML = `
                            <span class="rating-stars">★</span>
                            <span class="${d.stats.badge_type || 'basic'}-text">${d.stats.average_rating}</span>
                        `;
                    } else {
                        avgRatingElement.innerHTML = '<span>N/A</span>';
                    }
                    
                    debugLog('✅ Stats actualizadas con valoraciones unificadas');
                }
            } catch (e) {
                debugLog('❌ Exception en loadStats:', e.message);
            }
        }

        async function loadUserTrips() {
            debugLog('🗺️ Cargando viajes del usuario...');
            try {
                const res = await fetch('get-user-trips.php');
                const text = await res.text();
                debugLog('📡 Trips response (primeros 200 chars):', text.substring(0, 200));

                const d = JSON.parse(text);
                debugLog('🗺️ Trips parseados:', d);

                const sel = document.getElementById('trip-select');
                if (d.success && Array.isArray(d.trips)) {
                    d.trips.forEach(t => {
                        const opt = document.createElement('option');
                        opt.value = t.id;
                        opt.textContent = `${t.origen_ciudad} → ${t.destino_ciudad} (${t.date})`;
                        sel.appendChild(opt);
                    });
                    debugLog(`✅ ${d.trips.length} viajes cargados`);
                }
            } catch (e) {
                debugLog('❌ Exception en loadUserTrips:', e.message);
            }
        }

        // TOGGLE FORMULARIO DE NUEVA RUTA
        function toggleNewTripForm() {
            const form = document.getElementById('new-trip-form');
            const tripSelect = document.getElementById('trip-select');
            const isVisible = form.style.display !== 'none';

            if (isVisible) {
                // Ocultar formulario
                form.style.display = 'none';
                tripSelect.required = true;
                // Limpiar campos de nueva ruta
                document.getElementById('new-trip-origin').value = '';
                document.getElementById('new-trip-destination').value = '';
                document.getElementById('new-trip-date').value = '';
                // Remover clases visuales
                document.getElementById('new-trip-origin').classList.remove('place-selected');
                document.getElementById('new-trip-destination').classList.remove('place-selected');
                // Hacer campos no requeridos
                document.getElementById('new-trip-origin').required = false;
                document.getElementById('new-trip-destination').required = false;
                document.getElementById('new-trip-date').required = false;
            } else {
                // Mostrar formulario
                form.style.display = 'block';
                tripSelect.value = '';  // Limpiar selección existente
                tripSelect.required = false;  // No requerir viaje existente
                // Hacer campos de nueva ruta requeridos
                document.getElementById('new-trip-origin').required = true;
                document.getElementById('new-trip-destination').required = true;
                document.getElementById('new-trip-date').required = true;
                // Establecer fecha mínima a hoy
                const today = new Date().toISOString().split('T')[0];
                document.getElementById('new-trip-date').min = today;

                // Inicializar Google Places Autocomplete cuando se muestra el formulario
                // Intentar múltiples veces si Google Maps no está listo
                let attempts = 0;
                const maxAttempts = 10;
                const tryInit = () => {
                    if (typeof google !== 'undefined' && typeof google.maps !== 'undefined' && typeof google.maps.places !== 'undefined') {
                        initGoogleMapsAutocomplete();
                    } else if (attempts < maxAttempts) {
                        attempts++;
                        debugLog(`⏳ Esperando Google Maps API... intento ${attempts}/${maxAttempts}`);
                        setTimeout(tryInit, 500);
                    } else {
                        debugLog('❌ Google Maps API no disponible después de múltiples intentos');
                        Swal.fire({
                            icon: 'warning',
                            title: 'Google Maps no disponible',
                            text: 'Por favor, recarga la página e intenta de nuevo',
                            confirmButtonColor: '#42ba25'
                        });
                    }
                };
                setTimeout(tryInit, 300);
            }

            debugLog('🔄 Formulario de nueva ruta:', isVisible ? 'Ocultado' : 'Mostrado');
        }

        // RENDERIZAR Y FILTRAR
        function applyFilters() {
            const q = document.getElementById('search-products').value.toLowerCase().trim();
            const st = document.getElementById('filter-status').value;
            const cat = document.getElementById('filter-category').value;
            
            filteredProducts = products.filter(p => {
                const okQ = !q || (p.name && p.name.toLowerCase().includes(q));
                const okS = !st || (st === 'active' && p.active == 1) || (st === 'inactive' && p.active == 0) || (st === 'low-stock' && Number(p.stock || 0) <= 5);
                const okC = !cat || p.category === cat;
                return okQ && okS && okC;
            });
            
            debugLog(`🔍 Filtros aplicados: ${filteredProducts.length}/${products.length} productos`);
            renderProducts();
        }

        function renderProducts() {
            const grid = document.getElementById('products-grid');
            const loading = document.getElementById('loading');
            const empty = document.getElementById('empty-state');
            
            grid.innerHTML = '';
            loading.style.display = 'none';
            
            if (!filteredProducts.length) {
                empty.style.display = products.length === 0 ? 'block' : 'none';
                if (products.length > 0) {
                    grid.innerHTML = '<div style="padding:60px;text-align:center;color:#666;"><i class="fas fa-filter" style="font-size:3rem;margin-bottom:20px;color:#ddd;"></i><h3>No hay productos que coincidan con los filtros</h3><p>Intenta ajustar los criterios de búsqueda</p></div>';
                }
                document.getElementById('products-count').textContent = '0 productos';
                return;
            }
            
            empty.style.display = 'none';
            document.getElementById('products-count').textContent = `${filteredProducts.length} producto${filteredProducts.length !== 1 ? 's' : ''}`;
            
            filteredProducts.forEach(p => {
                // Usar imagen por defecto si no hay
                const imageUrl = (p.images && p.images[0]) || 
                                (p.primary_image) || 
                                'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=300&h=300&fit=crop';
                
                const priceText = `${p.currency === 'EUR' ? '€' : p.currency === 'USD' ? '$' : 'Bs'}${parseFloat(p.price || 0).toFixed(2)}`;
                
                const row = document.createElement('div');
                row.className = 'product-row';
                row.innerHTML = `
                    <img class="product-image" src="${imageUrl}" alt="${p.name}" onerror="this.src='https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=300&h=300&fit=crop';">
                    <div class="product-info">
                        <div class="product-name">${p.name || 'Sin nombre'}</div>
                        <div class="product-details">
                            <div class="product-price">${priceText}</div>
                            <div class="product-stock">Stock: ${p.stock ?? 0}</div>
                            <div class="product-trip">${p.trip_info || 'Sin viaje'}</div>
                            <div class="product-status ${p.active ? 'status-active':'status-inactive'}">${p.active ? 'Activo':'Inactivo'}</div>
                        </div>
                    </div>
                    <div class="product-actions">
                        <button class="btn-sm btn-edit" onclick="editProduct(${p.id})" title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-sm btn-toggle" onclick="toggleProduct(${p.id})" title="${p.active ? 'Desactivar' : 'Activar'}">
                            <i class="fas fa-${p.active ? 'eye-slash' : 'eye'}"></i>
                        </button>
                        <button class="btn-sm btn-delete" onclick="deleteProduct(${p.id})" title="Eliminar">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                `;
                grid.appendChild(row);
            });
            
            debugLog(`✅ ${filteredProducts.length} productos renderizados`);
        }

        // MODAL Y FORMULARIO
        function openAddProductModal() {
            debugLog('➕ Abriendo modal para añadir producto');
            isEditing = false;
            document.getElementById('modal-title').textContent = 'Añadir Producto';
            document.getElementById('product-form').reset();
            document.getElementById('product-id').value = '';
            selectedImages = [];
            document.getElementById('image-preview').innerHTML = '';

            // Reset currency
            document.querySelectorAll('.currency-option').forEach(opt => opt.classList.remove('selected'));
            document.querySelector('[data-currency="EUR"]').classList.add('selected');
            document.getElementById('product-currency').value = 'EUR';

            // REHABILITAR TODOS LOS CAMPOS
            enableAllFormFields();

            document.getElementById('product-modal').style.display = 'block';
        }

        function closeProductModal() {
            debugLog('❌ Cerrando modal de producto');
            document.getElementById('product-modal').style.display = 'none';

            // REHABILITAR TODOS LOS CAMPOS al cerrar
            enableAllFormFields();
        }

        function enableAllFormFields() {
            // Habilitar todos los inputs, textareas y selects
            const allInputs = document.querySelectorAll('#product-modal input, #product-modal textarea, #product-modal select');
            allInputs.forEach(input => {
                input.disabled = false;
                input.style.backgroundColor = '';
                input.style.cursor = '';
                input.style.opacity = '';
            });

            // Habilitar currency selector
            document.querySelectorAll('.currency-option').forEach(opt => {
                opt.style.pointerEvents = '';
                opt.style.opacity = '';
            });

            // Habilitar trip select y botón
            const tripSelect = document.getElementById('trip-select');
            const newTripBtn = tripSelect.parentElement.querySelector('button');
            tripSelect.disabled = false;
            tripSelect.style.opacity = '';
            tripSelect.style.cursor = '';
            if (newTripBtn) {
                newTripBtn.disabled = false;
                newTripBtn.style.opacity = '';
                newTripBtn.style.cursor = '';
            }
        }

        function editProduct(productId) {
            debugLog('✏️ Editando producto ID:', productId);
            const product = products.find(p => p.id === productId);
            if (!product) {
                debugLog('❌ Producto no encontrado para editar');
                return;
            }

            debugLog('📋 Datos del producto a editar:', product);

            isEditing = true;
            const hasOrders = product.has_orders || false;

            document.getElementById('modal-title').textContent = hasOrders ? 'Editar Producto (con pedidos)' : 'Editar Producto';

            // Llenar formulario
            document.getElementById('product-id').value = product.id;
            document.getElementById('product-name').value = product.name || '';
            document.getElementById('product-description').value = product.description || '';
            document.getElementById('product-price').value = product.price || 0;
            document.getElementById('product-category').value = product.category || '';
            document.getElementById('product-stock').value = product.stock || 1;
            document.getElementById('product-weight').value = product.weight || '';
            document.getElementById('product-dimensions').value = product.dimensions || '';
            document.getElementById('trip-select').value = product.trip_id || '';

            // Currency
            const currency = product.currency || 'EUR';
            document.querySelectorAll('.currency-option').forEach(opt => opt.classList.remove('selected'));
            const currencyOption = document.querySelector(`[data-currency="${currency}"]`);
            if (currencyOption) {
                currencyOption.classList.add('selected');
            }
            document.getElementById('product-currency').value = currency;

            // Limpiar preview de imágenes
            selectedImages = [];
            document.getElementById('image-preview').innerHTML = '';

            // DESHABILITAR CAMBIO DE RUTA AL EDITAR
            const tripSelect = document.getElementById('trip-select');
            const newTripBtn = tripSelect.parentElement.querySelector('button');
            tripSelect.disabled = true;
            tripSelect.style.opacity = '0.6';
            tripSelect.style.cursor = 'not-allowed';
            if (newTripBtn) {
                newTripBtn.disabled = true;
                newTripBtn.style.opacity = '0.6';
                newTripBtn.style.cursor = 'not-allowed';
            }

            // SI TIENE PEDIDOS, DESHABILITAR TODO EXCEPTO IMÁGENES
            if (hasOrders) {
                // Mostrar advertencia
                Swal.fire({
                    icon: 'info',
                    title: 'Producto con pedidos',
                    html: '<strong>Este producto tiene pedidos activos.</strong><br>Solo puedes modificar las imágenes.',
                    confirmButtonColor: '#42ba25',
                    timer: 4000
                });

                // Deshabilitar todos los campos excepto imágenes
                document.getElementById('product-name').disabled = true;
                document.getElementById('product-description').disabled = true;
                document.getElementById('product-price').disabled = true;
                document.getElementById('product-category').disabled = true;
                document.getElementById('product-stock').disabled = true;
                document.getElementById('product-weight').disabled = true;
                document.getElementById('product-dimensions').disabled = true;

                // Deshabilitar currency selector
                document.querySelectorAll('.currency-option').forEach(opt => {
                    opt.style.pointerEvents = 'none';
                    opt.style.opacity = '0.6';
                });

                // Agregar estilo visual a campos deshabilitados
                const disabledInputs = document.querySelectorAll('#product-modal input:disabled, #product-modal textarea:disabled, #product-modal select:disabled');
                disabledInputs.forEach(input => {
                    input.style.backgroundColor = '#f0f0f0';
                    input.style.cursor = 'not-allowed';
                });
            }

            document.getElementById('product-modal').style.display = 'block';
        }

        // MANEJO DE IMÁGENES
        function handleImageSelect(event) {
            debugLog('📸 Archivos seleccionados desde input:', event.target.files.length);
            handleImageFiles(Array.from(event.target.files));
        }

        function handleImageDrop(files) {
            debugLog('📸 Archivos arrastrados:', files.length);
            handleImageFiles(Array.from(files));
        }

        function handleImageFiles(files) {
            debugLog('📄 Procesando archivos:', files.length);
            const preview = document.getElementById('image-preview');
            
            for (const f of files) {
                if (!f.type.startsWith('image/')) {
                    showError(`${f.name} no es una imagen válida`);
                    continue;
                }
                if (f.size > 5 * 1024 * 1024) { 
                    showError(`${f.name} es muy grande (máx 5MB)`);
                    continue; 
                }
                if (selectedImages.length >= 5) { 
                    showError('Máximo 5 imágenes permitidas'); 
                    break;
                }
                
                selectedImages.push(f);
                const url = URL.createObjectURL(f);
                const item = document.createElement('div');
                item.className = 'preview-item';
                item.innerHTML = `
                    <img class="preview-image" src="${url}" alt="Preview">
                    <button class="remove-image" onclick="removePreview(this)">×</button>
                    <div class="image-info">${f.name}<br>${(f.size/1024).toFixed(1)} KB</div>
                `;
                preview.appendChild(item);
                
                debugLog(`✅ Imagen añadida: ${f.name} (${(f.size/1024).toFixed(1)} KB)`);
            }
            
            debugLog(`📊 Total imágenes seleccionadas: ${selectedImages.length}`);
        }

        function removePreview(button) {
            const previewItem = button.parentElement;
            const index = Array.from(previewItem.parentElement.children).indexOf(previewItem);
            
            if (index >= 0 && index < selectedImages.length) {
                selectedImages.splice(index, 1);
                previewItem.remove();
                debugLog(`🗑️ Imagen removida. Total restante: ${selectedImages.length}`);
            }
        }

        // ENVÍO DE FORMULARIO
        async function handleProductSubmit(event) {
            event.preventDefault();
            debugLog('🚀 Enviando formulario de producto...');
            
            if (!currentUserId) {
                showError('Error: Usuario no identificado');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', isEditing ? 'update_product' : 'add_product');
            
            // Recopilar datos del formulario
            const formElements = document.getElementById('product-form').elements;
            for (let element of formElements) {
                if (element.name && element.type !== 'file') {
                    formData.append(element.name, element.value);
                    debugLog(`📝 Campo ${element.name}: ${element.value}`);
                }
            }
            
            // Validaciones básicas
            const name = document.getElementById('product-name').value.trim();
            const description = document.getElementById('product-description').value.trim();
            const price = parseFloat(document.getElementById('product-price').value);
            const category = document.getElementById('product-category').value;
            const stock = parseInt(document.getElementById('product-stock').value);
            
            if (!name || !description) {
                showError('Nombre y descripción son obligatorios');
                return;
            }
            
            if (isNaN(price) || price <= 0) {
                showError('El precio debe ser mayor a 0');
                return;
            }
            
            if (!category) {
                showError('Debes seleccionar una categoría');
                return;
            }
            
            if (isNaN(stock) || stock < 0) {
                showError('El stock no puede ser negativo');
                return;
            }
            
            // Añadir imágenes
            debugLog(`📸 Añadiendo ${selectedImages.length} imágenes al FormData...`);
            selectedImages.forEach((file, index) => {
                formData.append('images[]', file);
                debugLog(`📎 Imagen ${index + 1}: ${file.name} (${file.size} bytes)`);
            });
            
            // Debug final del FormData
            debugLog('📦 Contenido final del FormData:');
            for (let [key, value] of formData.entries()) {
                if (value instanceof File) {
                    debugLog(`  ${key}: [FILE] ${value.name} (${value.size} bytes)`);
                } else {
                    debugLog(`  ${key}: ${value}`);
                }
            }
            
            try {
                debugLog('🌐 Enviando request a shop-actions.php...');
                
                const response = await fetch('shop-actions.php', {
                    method: 'POST',
                    body: formData
                });
                
                debugLog('📡 Response recibida:', {
                    status: response.status,
                    ok: response.ok,
                    statusText: response.statusText
                });
                
                const text = await response.text();
                debugLog('📋 Response text (primeros 500 chars):', text.substring(0, 500));
                
                let data;
                try {
                    data = JSON.parse(text);
                } catch (parseError) {
                    debugLog('❌ Error parseando JSON:', parseError.message);
                    throw new Error(`Error parseando respuesta: ${parseError.message}\n\nRespuesta:\n${text.substring(0, 500)}`);
                }
                
                debugLog('✅ Data parseada correctamente:', data);
                
                if (data.success) {
                    const message = isEditing ? 'Producto actualizado' : 'Producto creado';
                    const imageInfo = data.uploaded_images ? ` (${data.uploaded_images} imágenes subidas)` : '';
                    
                    showSuccess(message + ' exitosamente' + imageInfo);
                    
                    closeProductModal();
                    await loadProducts();
                    await loadStats();
                    
                } else {
                    throw new Error(data.error || 'Error desconocido');
                }
                
            } catch (error) {
                debugLog('❌ Error en handleProductSubmit:', error);
                showError('Error guardando producto: ' + error.message);
            }
        }

        // ACCIONES DE PRODUCTOS
        async function toggleProduct(productId) {
            debugLog('🔄 Cambiando estado del producto:', productId);
            const product = products.find(p => p.id === productId);
            if (!product) return;
            
            try {
                const formData = new FormData();
                formData.append('action', 'update_product');
                formData.append('product_id', productId);
                formData.append('active', product.active ? 0 : 1);
                
                const response = await fetch('shop-actions.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showSuccess(`Producto ${product.active ? 'desactivado' : 'activado'} exitosamente`);
                    await loadProducts();
                } else {
                    throw new Error(data.error);
                }
            } catch (error) {
                debugLog('❌ Error toggleProduct:', error);
                showError('Error cambiando estado: ' + error.message);
            }
        }

        async function deleteProduct(productId) {
            const result = await Swal.fire({
                title: '¿Eliminar producto?',
                text: 'Esta acción no se puede deshacer',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d'
            });
            
            if (!result.isConfirmed) return;
            
            debugLog('🗑️ Eliminando producto:', productId);
            
            try {
                const formData = new FormData();
                formData.append('action', 'delete_product');
                formData.append('product_id', productId);
                
                const response = await fetch('shop-actions.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showSuccess('Producto eliminado exitosamente');
                    await loadProducts();
                    await loadStats();
                } else {
                    throw new Error(data.error);
                }
            } catch (error) {
                debugLog('❌ Error deleteProduct:', error);
                showError('Error eliminando producto: ' + error.message);
            }
        }

        // MENSAJES
        function showSuccess(message) {
            debugLog('✅ Éxito:', message);
            Swal.fire({
                icon: 'success',
                title: '¡Éxito!',
                text: message,
                timer: 2000,
                showConfirmButton: false,
                background: '#fff',
                confirmButtonColor: '#41ba0d'
            });
        }

        function showError(message) {
            debugLog('❌ Error:', message);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: message,
                confirmButtonColor: '#dc3545'
            });
        }

        // =====================================================
        // GOOGLE MAPS AUTOCOMPLETE
        // =====================================================
        let originAutocomplete = null;
        let destinationAutocomplete = null;
        let autocompleteInitialized = false;

        function initGoogleMapsAutocomplete() {
            debugLog('🗺️ Inicializando Google Maps Autocomplete...');

            // Verificar que Google Maps esté cargado
            if (typeof google === 'undefined' || typeof google.maps === 'undefined' || typeof google.maps.places === 'undefined') {
                debugLog('⚠️ Google Maps API no disponible, reintentando en 500ms...');
                setTimeout(initGoogleMapsAutocomplete, 500);
                return;
            }

            // Si ya está inicializado, solo reiniciar los valores
            if (autocompleteInitialized) {
                debugLog('ℹ️ Google Maps ya inicializado, reutilizando instancias');
                return;
            }

            try {
                // Configuración de autocompletado para origen
                const originInput = document.getElementById('new-trip-origin');
                const destinationInput = document.getElementById('new-trip-destination');

                if (!originInput || !destinationInput) {
                    debugLog('❌ Inputs no encontrados, reintentando...');
                    setTimeout(initGoogleMapsAutocomplete, 300);
                    return;
                }

                debugLog('📍 Configurando autocomplete para origen...');
                originAutocomplete = new google.maps.places.Autocomplete(originInput, {
                    types: ['(cities)'],
                    fields: ['formatted_address', 'address_components', 'geometry', 'name', 'place_id']
                });

                originAutocomplete.addListener('place_changed', function() {
                    const place = originAutocomplete.getPlace();
                    debugLog('📍 Lugar seleccionado para origen:', place);

                    if (place && (place.formatted_address || place.name)) {
                        const selectedLocation = place.formatted_address || place.name;
                        originInput.value = selectedLocation;
                        originInput.classList.add('place-selected');

                        Swal.fire({
                            icon: 'success',
                            title: '📍 Origen seleccionado',
                            text: selectedLocation,
                            timer: 2000,
                            showConfirmButton: false,
                            toast: true,
                            position: 'top-end',
                            background: '#42ba25',
                            color: '#fff'
                        });

                        debugLog('✅ Origen seleccionado:', selectedLocation);

                        setTimeout(() => {
                            originInput.classList.remove('place-selected');
                        }, 600);
                    }
                });

                // Listener para cuando el usuario escribe
                originInput.addEventListener('input', function() {
                    this.classList.remove('place-selected');
                });

                debugLog('📍 Configurando autocomplete para destino...');
                destinationAutocomplete = new google.maps.places.Autocomplete(destinationInput, {
                    types: ['(cities)'],
                    fields: ['formatted_address', 'address_components', 'geometry', 'name', 'place_id']
                });

                destinationAutocomplete.addListener('place_changed', function() {
                    const place = destinationAutocomplete.getPlace();
                    debugLog('📍 Lugar seleccionado para destino:', place);

                    if (place && (place.formatted_address || place.name)) {
                        const selectedLocation = place.formatted_address || place.name;
                        destinationInput.value = selectedLocation;
                        destinationInput.classList.add('place-selected');

                        Swal.fire({
                            icon: 'success',
                            title: '📍 Destino seleccionado',
                            text: selectedLocation,
                            timer: 2000,
                            showConfirmButton: false,
                            toast: true,
                            position: 'top-end',
                            background: '#42ba25',
                            color: '#fff'
                        });

                        debugLog('✅ Destino seleccionado:', selectedLocation);

                        setTimeout(() => {
                            destinationInput.classList.remove('place-selected');
                        }, 600);
                    }
                });

                // Listener para cuando el usuario escribe
                destinationInput.addEventListener('input', function() {
                    this.classList.remove('place-selected');
                });

                autocompleteInitialized = true;
                debugLog('✅ Google Maps Autocomplete inicializado correctamente');
            } catch (error) {
                console.error('❌ Error inicializando Google Maps Autocomplete:', error);
                debugLog('❌ Error:', error.message);
            }
        }

        // =====================================================
        // CALLBACK PARA GOOGLE MAPS
        // =====================================================
        window.initGoogleMaps = function() {
            debugLog('✅ Google Maps API cargada correctamente desde callback');
            // Intentar inicializar el autocomplete si el modal está abierto
            if (document.getElementById('new-trip-form').style.display === 'block') {
                setTimeout(initGoogleMapsAutocomplete, 200);
            }
        };

        // Intentar cargar Google Maps si ya está disponible
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof google !== 'undefined' && typeof google.maps !== 'undefined' && typeof google.maps.places !== 'undefined') {
                debugLog('✅ Google Maps ya está disponible en DOMContentLoaded');
            }
        });
    </script>

    <!-- Google Maps API con carga asíncrona -->
    <script async defer
            src="https://maps.googleapis.com/maps/api/js?key=AIzaSyA_5woNomNe9OV4x2Dj9RUSY_wh_t5n8Xc&libraries=places&callback=initGoogleMaps">
    </script>
</body>
</html>