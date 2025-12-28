<?php
// shop-debug-images.php - Diagnóstico completo de problemas con imágenes
session_start();

// Incluir configuración
$config_paths = [__DIR__ . '/config.php', __DIR__ . '/../config.php'];
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

function checkDirectoryPermissions($dir) {
    if (!file_exists($dir)) {
        return ['exists' => false, 'readable' => false, 'writable' => false];
    }
    return [
        'exists' => true,
        'readable' => is_readable($dir),
        'writable' => is_writable($dir),
        'permissions' => substr(sprintf('%o', fileperms($dir)), -4)
    ];
}

function checkImageFiles($productId, $conexion) {
    $stmt = $conexion->prepare("SELECT * FROM shop_product_images WHERE product_id = ?");
    $stmt->execute([$productId]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $results = [];
    foreach ($images as $img) {
        $fullPath = __DIR__ . '/' . ltrim($img['image_path'], '/');
        $results[] = [
            'db_path' => $img['image_path'],
            'full_path' => $fullPath,
            'file_exists' => file_exists($fullPath),
            'file_size' => file_exists($fullPath) ? filesize($fullPath) : 0,
            'is_primary' => $img['is_primary']
        ];
    }
    return $results;
}

function testImageUpload() {
    $uploadDir = __DIR__ . '/uploads/shop_products/';
    
    // Crear directorio si no existe
    if (!file_exists($uploadDir)) {
        $created = mkdir($uploadDir, 0755, true);
        echo "📁 Creando directorio: " . ($created ? "✅ Éxito" : "❌ Error") . "<br>";
    }
    
    // Crear imagen de prueba
    $testImage = imagecreate(100, 100);
    $background = imagecolorallocate($testImage, 255, 255, 255);
    $textColor = imagecolorallocate($testImage, 0, 0, 0);
    imagestring($testImage, 5, 10, 40, 'TEST', $textColor);
    
    $testFile = $uploadDir . 'test_' . time() . '.png';
    $saved = imagepng($testImage, $testFile);
    imagedestroy($testImage);
    
    if ($saved && file_exists($testFile)) {
        echo "🖼️ Imagen de prueba creada: ✅ " . basename($testFile) . "<br>";
        unlink($testFile); // Limpiar
        return true;
    } else {
        echo "🖼️ Error creando imagen de prueba: ❌<br>";
        return false;
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔍 Diagnóstico de Imágenes - SendVialo Shop</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f8f9fa;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
        }
        h1 {
            color: #667eea;
            text-align: center;
            margin-bottom: 30px;
        }
        .section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
        }
        .section h2 {
            color: #495057;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
            margin-top: 0;
        }
        .status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
            margin: 2px;
        }
        .status.ok { background: #d4edda; color: #155724; }
        .status.error { background: #f8d7da; color: #721c24; }
        .status.warning { background: #fff3cd; color: #856404; }
        .code {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 10px;
            font-family: monospace;
            font-size: 14px;
            overflow-x: auto;
            margin: 10px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            padding: 8px 12px;
            text-align: left;
            border: 1px solid #dee2e6;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
        }
        .btn {
            background: #667eea;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
        }
        .btn:hover {
            background: #5a67d8;
        }
        .fix-btn {
            background: #28a745;
        }
        .fix-btn:hover {
            background: #218838;
        }
        .image-preview {
            max-width: 150px;
            max-height: 150px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Diagnóstico de Imágenes - SendVialo Shop</h1>
        
        <!-- 1. Verificar Directorios -->
        <div class="section">
            <h2>📁 Verificación de Directorios</h2>
            <?php
            $directories = [
                'uploads' => __DIR__ . '/uploads',
                'uploads/shop_products' => __DIR__ . '/uploads/shop_products',
                'Imagenes' => __DIR__ . '/Imagenes',
                'root_uploads' => __DIR__ . '/../uploads'
            ];
            
            echo "<table>";
            echo "<tr><th>Directorio</th><th>Existe</th><th>Lectura</th><th>Escritura</th><th>Permisos</th><th>Acción</th></tr>";
            
            foreach ($directories as $name => $path) {
                $check = checkDirectoryPermissions($path);
                echo "<tr>";
                echo "<td><code>$path</code></td>";
                echo "<td><span class='status " . ($check['exists'] ? 'ok' : 'error') . "'>" . ($check['exists'] ? '✅' : '❌') . "</span></td>";
                echo "<td><span class='status " . ($check['readable'] ? 'ok' : 'error') . "'>" . ($check['readable'] ? '✅' : '❌') . "</span></td>";
                echo "<td><span class='status " . ($check['writable'] ? 'ok' : 'error') . "'>" . ($check['writable'] ? '✅' : '❌') . "</span></td>";
                echo "<td>" . ($check['exists'] ? $check['permissions'] : 'N/A') . "</td>";
                echo "<td>";
                if (!$check['exists']) {
                    echo "<a href='?action=create_dir&dir=" . urlencode($path) . "' class='btn fix-btn'>Crear</a>";
                } elseif (!$check['writable']) {
                    echo "<a href='?action=fix_permissions&dir=" . urlencode($path) . "' class='btn fix-btn'>Arreglar</a>";
                }
                echo "</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            // Procesamiento de acciones
            if (isset($_GET['action'])) {
                if ($_GET['action'] === 'create_dir' && isset($_GET['dir'])) {
                    $dir = $_GET['dir'];
                    if (mkdir($dir, 0755, true)) {
                        echo "<div class='status ok'>✅ Directorio creado: $dir</div>";
                    } else {
                        echo "<div class='status error'>❌ Error creando directorio: $dir</div>";
                    }
                } elseif ($_GET['action'] === 'fix_permissions' && isset($_GET['dir'])) {
                    $dir = $_GET['dir'];
                    if (chmod($dir, 0755)) {
                        echo "<div class='status ok'>✅ Permisos corregidos: $dir</div>";
                    } else {
                        echo "<div class='status error'>❌ Error corrigiendo permisos: $dir</div>";
                    }
                }
            }
            ?>
        </div>

        <!-- 2. Test de Subida -->
        <div class="section">
            <h2>🔧 Test de Subida de Imágenes</h2>
            <?php
            echo "Probando creación de imagen...<br>";
            testImageUpload();
            ?>
        </div>

        <!-- 3. Verificar Base de Datos -->
        <div class="section">
            <h2>🗄️ Verificación de Base de Datos</h2>
            <?php
            if (isset($conexion)) {
                try {
                    // Verificar tablas
                    $tables = ['shop_products', 'shop_product_images', 'accounts', 'transporting'];
                    foreach ($tables as $table) {
                        $stmt = $conexion->query("SELECT COUNT(*) FROM $table");
                        $count = $stmt->fetchColumn();
                        echo "<div class='status ok'>✅ Tabla $table: $count registros</div>";
                    }
                    
                    // Verificar productos con imágenes
                    echo "<h3>Productos y sus imágenes:</h3>";
                    $stmt = $conexion->query("
                        SELECT p.id, p.name, p.seller_id,
                               (SELECT COUNT(*) FROM shop_product_images spi WHERE spi.product_id = p.id) as image_count,
                               a.full_name as seller_name
                        FROM shop_products p 
                        LEFT JOIN accounts a ON p.seller_id = a.id 
                        ORDER BY p.id DESC LIMIT 10
                    ");
                    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (empty($products)) {
                        echo "<div class='status warning'>⚠️ No hay productos en la base de datos</div>";
                    } else {
                        echo "<table>";
                        echo "<tr><th>ID</th><th>Producto</th><th>Vendedor</th><th>Imágenes</th><th>Detalles</th></tr>";
                        foreach ($products as $product) {
                            echo "<tr>";
                            echo "<td>{$product['id']}</td>";
                            echo "<td>{$product['name']}</td>";
                            echo "<td>{$product['seller_name']} (ID: {$product['seller_id']})</td>";
                            echo "<td>{$product['image_count']}</td>";
                            echo "<td><a href='?check_product={$product['id']}' class='btn'>Ver detalles</a></td>";
                            echo "</tr>";
                        }
                        echo "</table>";
                    }
                    
                } catch (Exception $e) {
                    echo "<div class='status error'>❌ Error de base de datos: " . $e->getMessage() . "</div>";
                }
            } else {
                echo "<div class='status error'>❌ No hay conexión a la base de datos</div>";
            }
            ?>
        </div>

        <!-- 4. Detalles de Producto Específico -->
        <?php if (isset($_GET['check_product']) && isset($conexion)): ?>
        <div class="section">
            <h2>🔍 Detalles del Producto <?= $_GET['check_product'] ?></h2>
            <?php
            $productId = (int)$_GET['check_product'];
            
            // Información del producto
            $stmt = $conexion->prepare("
                SELECT p.*, a.full_name as seller_name, a.username,
                       t.search_input as origin, t.destination_input as destination
                FROM shop_products p 
                LEFT JOIN accounts a ON p.seller_id = a.id
                LEFT JOIN transporting t ON p.trip_id = t.id
                WHERE p.id = ?
            ");
            $stmt->execute([$productId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($product) {
                echo "<h3>Información del Producto:</h3>";
                echo "<table>";
                foreach ($product as $key => $value) {
                    echo "<tr><td><strong>$key</strong></td><td>" . htmlspecialchars($value ?: 'NULL') . "</td></tr>";
                }
                echo "</table>";
                
                // Verificar imágenes
                echo "<h3>Imágenes del Producto:</h3>";
                $imageResults = checkImageFiles($productId, $conexion);
                
                if (empty($imageResults)) {
                    echo "<div class='status warning'>⚠️ No hay imágenes registradas para este producto</div>";
                } else {
                    echo "<table>";
                    echo "<tr><th>Ruta DB</th><th>Archivo Existe</th><th>Tamaño</th><th>Primaria</th><th>Preview</th></tr>";
                    foreach ($imageResults as $img) {
                        echo "<tr>";
                        echo "<td><code>{$img['db_path']}</code></td>";
                        echo "<td><span class='status " . ($img['file_exists'] ? 'ok' : 'error') . "'>" . ($img['file_exists'] ? '✅' : '❌') . "</span></td>";
                        echo "<td>" . ($img['file_size'] > 0 ? number_format($img['file_size']/1024, 1) . ' KB' : '0 KB') . "</td>";
                        echo "<td>" . ($img['is_primary'] ? '⭐ Sí' : 'No') . "</td>";
                        echo "<td>";
                        if ($img['file_exists']) {
                            echo "<img src='{$img['db_path']}' class='image-preview' alt='Preview'>";
                        } else {
                            echo "❌ No disponible";
                        }
                        echo "</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                }
            } else {
                echo "<div class='status error'>❌ Producto no encontrado</div>";
            }
            ?>
        </div>
        <?php endif; ?>

        <!-- 5. Verificar Configuración PHP -->
        <div class="section">
            <h2>⚙️ Configuración PHP</h2>
            <?php
            $phpSettings = [
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'max_file_uploads' => ini_get('max_file_uploads'),
                'file_uploads' => ini_get('file_uploads') ? 'Habilitado' : 'Deshabilitado',
                'GD Extension' => extension_loaded('gd') ? 'Instalada' : 'No instalada'
            ];
            
            echo "<table>";
            foreach ($phpSettings as $setting => $value) {
                $status = 'ok';
                if ($setting === 'file_uploads' && $value === 'Deshabilitado') $status = 'error';
                if ($setting === 'GD Extension' && $value === 'No instalada') $status = 'error';
                
                echo "<tr><td><strong>$setting</strong></td><td><span class='status $status'>$value</span></td></tr>";
            }
            echo "</table>";
            ?>
        </div>

        <!-- 6. Tests de URLs -->
        <div class="section">
            <h2>🔗 Test de URLs y Rutas</h2>
            <?php
            $currentUrl = "http" . (isset($_SERVER['HTTPS']) ? "s" : "") . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            $baseDir = dirname($currentUrl);
            
            echo "<table>";
            echo "<tr><th>Tipo</th><th>Ruta</th><th>Status</th></tr>";
            
            // Test de rutas importantes
            $testUrls = [
                'Script actual' => $currentUrl,
                'Directorio base' => $baseDir,
                'Shop actions' => $baseDir . '/shop-actions.php',
                'Get user trips' => $baseDir . '/get-user-trips.php',
                'Uploads dir' => $baseDir . '/uploads/shop_products/'
            ];
            
            foreach ($testUrls as $name => $url) {
                echo "<tr><td>$name</td><td><a href='$url' target='_blank'>$url</a></td><td>📎</td></tr>";
            }
            echo "</table>";
            ?>
        </div>

        <!-- 7. Soluciones Recomendadas -->
        <div class="section">
            <h2>🛠️ Soluciones Recomendadas</h2>
            
            <h3>1. Crear estructura de directorios:</h3>
            <div class="code">mkdir -p uploads/shop_products<br>chmod -R 755 uploads/</div>
            
            <h3>2. Verificar rutas en shop-actions.php:</h3>
            <div class="code">
// En la función uploadImages(), cambiar:<br>
$upload_dir = __DIR__ . '/uploads/shop_products/';<br><br>
// Por una ruta relativa más robusta:<br>
$upload_dir = dirname(__FILE__) . '/uploads/shop_products/';
            </div>
            
            <h3>3. Actualizar consulta SQL para imágenes:</h3>
            <div class="code">
// En getProducts(), asegurar que las rutas sean correctas:<br>
$img_stmt = $conexion->prepare("SELECT image_path FROM shop_product_images WHERE product_id = ? ORDER BY is_primary DESC, id ASC");<br>
$img_stmt->execute([$product['id']]);<br>
$images = $img_stmt->fetchAll(PDO::FETCH_COLUMN);<br>
$product['images'] = $images;
            </div>
            
            <h3>4. Verificar permisos del servidor:</h3>
            <div class="code">
# En el servidor, ejecutar:<br>
chown -R www-data:www-data uploads/<br>
chmod -R 755 uploads/
            </div>
        </div>

        <!-- Botones de acción -->
        <div style="text-align: center; margin-top: 30px;">
            <a href="?" class="btn">🔄 Recargar Diagnóstico</a>
            <a href="index.php" class="btn">🏪 Ir al Shop</a>
            <a href="shop-manage-products.php" class="btn">📦 Gestionar Productos</a>
        </div>
    </div>
</body>
</html>