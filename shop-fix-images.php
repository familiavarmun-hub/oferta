<?php
// shop-fix-images.php - Script para solucionar automáticamente problemas de imágenes
session_start();

// Solo permitir a administradores o en desarrollo
if (!isset($_SESSION['usuario_id'])) {
    die('Acceso denegado. Debes estar logueado.');
}

$config_paths = [__DIR__ . '/config.php', __DIR__ . '/../config.php'];
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

function createDirectoryStructure() {
    $dirs = [
        __DIR__ . '/uploads',
        __DIR__ . '/uploads/shop_products',
        __DIR__ . '/uploads/avatars',
        __DIR__ . '/uploads/temp'
    ];
    
    $created = [];
    $errors = [];
    
    foreach ($dirs as $dir) {
        if (!file_exists($dir)) {
            if (mkdir($dir, 0755, true)) {
                $created[] = $dir;
                // Crear .htaccess para seguridad
                $htaccess = $dir . '/.htaccess';
                file_put_contents($htaccess, "Options -Indexes\nDeny from all\n<Files ~ \"\\.(jpg|jpeg|png|gif|webp)$\">\n    Allow from all\n</Files>");
            } else {
                $errors[] = $dir;
            }
        }
    }
    
    return ['created' => $created, 'errors' => $errors];
}

function fixImagePaths($conexion) {
    $fixes = [];
    $errors = [];
    
    try {
        // Obtener todas las imágenes
        $stmt = $conexion->query("SELECT id, image_path FROM shop_product_images");
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($images as $img) {
            $oldPath = $img['image_path'];
            $newPath = $oldPath;
            
            // Normalizar rutas
            $newPath = str_replace('\\', '/', $newPath);
            $newPath = ltrim($newPath, '/');
            
            // Si la ruta no empieza con uploads/, añadirlo
            if (!str_starts_with($newPath, 'uploads/')) {
                $newPath = 'uploads/shop_products/' . basename($newPath);
            }
            
            if ($oldPath !== $newPath) {
                $updateStmt = $conexion->prepare("UPDATE shop_product_images SET image_path = ? WHERE id = ?");
                if ($updateStmt->execute([$newPath, $img['id']])) {
                    $fixes[] = ['id' => $img['id'], 'old' => $oldPath, 'new' => $newPath];
                } else {
                    $errors[] = ['id' => $img['id'], 'path' => $oldPath, 'error' => 'Update failed'];
                }
            }
        }
        
    } catch (Exception $e) {
        $errors[] = ['error' => $e->getMessage()];
    }
    
    return ['fixes' => $fixes, 'errors' => $errors];
}

function generateMissingThumbnails($conexion) {
    $generated = [];
    $errors = [];
    
    try {
        $stmt = $conexion->query("
            SELECT spi.id, spi.image_path, p.name 
            FROM shop_product_images spi 
            JOIN shop_products p ON spi.product_id = p.id
        ");
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($images as $img) {
            $fullPath = __DIR__ . '/' . ltrim($img['image_path'], '/');
            $thumbPath = dirname($fullPath) . '/thumb_' . basename($fullPath);
            
            if (file_exists($fullPath) && !file_exists($thumbPath)) {
                if (createThumbnail($fullPath, $thumbPath, 300, 300)) {
                    $generated[] = $thumbPath;
                } else {
                    $errors[] = ['path' => $fullPath, 'error' => 'Thumbnail generation failed'];
                }
            }
        }
        
    } catch (Exception $e) {
        $errors[] = ['error' => $e->getMessage()];
    }
    
    return ['generated' => $generated, 'errors' => $errors];
}

function createThumbnail($source, $destination, $width, $height) {
    if (!extension_loaded('gd')) return false;
    
    $info = getimagesize($source);
    if (!$info) return false;
    
    $srcWidth = $info[0];
    $srcHeight = $info[1];
    $mime = $info['mime'];
    
    // Crear imagen fuente
    switch ($mime) {
        case 'image/jpeg':
            $srcImage = imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $srcImage = imagecreatefrompng($source);
            break;
        case 'image/gif':
            $srcImage = imagecreatefromgif($source);
            break;
        case 'image/webp':
            $srcImage = imagecreatefromwebp($source);
            break;
        default:
            return false;
    }
    
    if (!$srcImage) return false;
    
    // Calcular dimensiones manteniendo proporción
    $ratio = min($width / $srcWidth, $height / $srcHeight);
    $newWidth = round($srcWidth * $ratio);
    $newHeight = round($srcHeight * $ratio);
    
    // Crear imagen de destino
    $destImage = imagecreatetruecolor($newWidth, $newHeight);
    
    // Preservar transparencia para PNG
    if ($mime === 'image/png') {
        imagealphablending($destImage, false);
        imagesavealpha($destImage, true);
        $transparent = imagecolorallocatealpha($destImage, 255, 255, 255, 127);
        imagefill($destImage, 0, 0, $transparent);
    }
    
    // Redimensionar
    imagecopyresampled($destImage, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $srcWidth, $srcHeight);
    
    // Guardar
    $saved = false;
    switch ($mime) {
        case 'image/jpeg':
            $saved = imagejpeg($destImage, $destination, 85);
            break;
        case 'image/png':
            $saved = imagepng($destImage, $destination, 8);
            break;
        case 'image/gif':
            $saved = imagegif($destImage, $destination);
            break;
        case 'image/webp':
            $saved = imagewebp($destImage, $destination, 85);
            break;
    }
    
    imagedestroy($srcImage);
    imagedestroy($destImage);
    
    return $saved;
}

function cleanOrphanedImages($conexion) {
    $cleaned = [];
    $errors = [];
    
    try {
        // Buscar imágenes en DB que no tienen archivo físico
        $stmt = $conexion->query("SELECT id, image_path FROM shop_product_images");
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($images as $img) {
            $fullPath = __DIR__ . '/' . ltrim($img['image_path'], '/');
            
            if (!file_exists($fullPath)) {
                // Eliminar registro de DB si el archivo no existe
                $deleteStmt = $conexion->prepare("DELETE FROM shop_product_images WHERE id = ?");
                if ($deleteStmt->execute([$img['id']])) {
                    $cleaned[] = ['id' => $img['id'], 'path' => $img['image_path']];
                }
            }
        }
        
        // Buscar archivos físicos huérfanos
        $uploadDir = __DIR__ . '/uploads/shop_products/';
        if (is_dir($uploadDir)) {
            $files = glob($uploadDir . '*');
            foreach ($files as $file) {
                if (is_file($file) && !str_contains(basename($file), '.htaccess')) {
                    $relativePath = 'uploads/shop_products/' . basename($file);
                    $checkStmt = $conexion->prepare("SELECT COUNT(*) FROM shop_product_images WHERE image_path = ?");
                    $checkStmt->execute([$relativePath]);
                    
                    if ($checkStmt->fetchColumn() == 0) {
                        if (unlink($file)) {
                            $cleaned[] = ['file' => $file, 'reason' => 'Orphaned file'];
                        }
                    }
                }
            }
        }
        
    } catch (Exception $e) {
        $errors[] = ['error' => $e->getMessage()];
    }
    
    return ['cleaned' => $cleaned, 'errors' => $errors];
}

// Ejecutar reparaciones
$results = [];

if (isset($_POST['fix_directories'])) {
    $results['directories'] = createDirectoryStructure();
}

if (isset($_POST['fix_paths']) && isset($conexion)) {
    $results['paths'] = fixImagePaths($conexion);
}

if (isset($_POST['generate_thumbnails']) && isset($conexion)) {
    $results['thumbnails'] = generateMissingThumbnails($conexion);
}

if (isset($_POST['clean_orphaned']) && isset($conexion)) {
    $results['cleaned'] = cleanOrphanedImages($conexion);
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔧 Auto-Fix Imágenes - SendVialo Shop</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f8f9fa;
        }
        .container {
            max-width: 1000px;
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
        .fix-section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            background: #f8f9fa;
        }
        .fix-section h2 {
            color: #495057;
            margin-top: 0;
        }
        .btn {
            background: #667eea;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            margin: 5px;
        }
        .btn:hover {
            background: #5a67d8;
        }
        .btn.danger {
            background: #dc3545;
        }
        .btn.danger:hover {
            background: #c82333;
        }
        .btn.success {
            background: #28a745;
        }
        .btn.success:hover {
            background: #218838;
        }
        .results {
            background: white;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            border-left: 4px solid #667eea;
        }
        .success {
            border-left-color: #28a745;
            background: #d4edda;
        }
        .error {
            border-left-color: #dc3545;
            background: #f8d7da;
        }
        .warning {
            border-left-color: #ffc107;
            background: #fff3cd;
        }
        pre {
            background: #f1f3f4;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Auto-Fix Imágenes - SendVialo Shop</h1>
        
        <div class="fix-section">
            <h2>📁 1. Crear Estructura de Directorios</h2>
            <p>Crea los directorios necesarios para las imágenes con los permisos correctos.</p>
            <form method="post" style="display: inline;">
                <button type="submit" name="fix_directories" class="btn success">
                    🏗️ Crear Directorios
                </button>
            </form>
            
            <?php if (isset($results['directories'])): ?>
            <div class="results success">
                <h4>Resultados:</h4>
                <?php if (!empty($results['directories']['created'])): ?>
                <p><strong>Directorios creados:</strong></p>
                <ul>
                    <?php foreach ($results['directories']['created'] as $dir): ?>
                    <li>✅ <?= htmlspecialchars($dir) ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                
                <?php if (!empty($results['directories']['errors'])): ?>
                <p><strong>Errores:</strong></p>
                <ul>
                    <?php foreach ($results['directories']['errors'] as $error): ?>
                    <li>❌ <?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="fix-section">
            <h2>🔗 2. Corregir Rutas de Imágenes</h2>
            <p>Normaliza las rutas de las imágenes en la base de datos.</p>
            <form method="post" style="display: inline;">
                <button type="submit" name="fix_paths" class="btn">
                    🔧 Corregir Rutas
                </button>
            </form>
            
            <?php if (isset($results['paths'])): ?>
            <div class="results <?= empty($results['paths']['errors']) ? 'success' : 'warning' ?>">
                <h4>Resultados:</h4>
                <?php if (!empty($results['paths']['fixes'])): ?>
                <p><strong>Rutas corregidas (<?= count($results['paths']['fixes']) ?>):</strong></p>
                <pre><?php
                foreach (array_slice($results['paths']['fixes'], 0, 10) as $fix) {
                    echo "ID {$fix['id']}: {$fix['old']} → {$fix['new']}\n";
                }
                if (count($results['paths']['fixes']) > 10) {
                    echo "... y " . (count($results['paths']['fixes']) - 10) . " más\n";
                }
                ?></pre>
                <?php else: ?>
                <p>✅ No se encontraron rutas que necesiten corrección.</p>
                <?php endif; ?>
                
                <?php if (!empty($results['paths']['errors'])): ?>
                <p><strong>Errores:</strong></p>
                <pre><?= htmlspecialchars(print_r($results['paths']['errors'], true)) ?></pre>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="fix-section">
            <h2>🖼️ 3. Generar Miniaturas</h2>
            <p>Crea miniaturas de las imágenes para mejorar el rendimiento.</p>
            <form method="post" style="display: inline;">
                <button type="submit" name="generate_thumbnails" class="btn">
                    🎨 Generar Miniaturas
                </button>
            </form>
            
            <?php if (isset($results['thumbnails'])): ?>
            <div class="results <?= empty($results['thumbnails']['errors']) ? 'success' : 'warning' ?>">
                <h4>Resultados:</h4>
                <p><strong>Miniaturas generadas:</strong> <?= count($results['thumbnails']['generated']) ?></p>
                <?php if (!empty($results['thumbnails']['errors'])): ?>
                <p><strong>Errores:</strong></p>
                <pre><?= htmlspecialchars(print_r($results['thumbnails']['errors'], true)) ?></pre>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="fix-section">
            <h2>🧹 4. Limpiar Imágenes Huérfanas</h2>
            <p><strong>⚠️ PELIGRO:</strong> Elimina registros de BD sin archivos y archivos sin registros.</p>
            <form method="post" style="display: inline;">
                <button type="submit" name="clean_orphaned" class="btn danger" 
                        onclick="return confirm('¿Estás seguro? Esta acción eliminará archivos y no se puede deshacer.')">
                    🗑️ Limpiar Huérfanas
                </button>
            </form>
            
            <?php if (isset($results['cleaned'])): ?>
            <div class="results <?= empty($results['cleaned']['errors']) ? 'success' : 'error' ?>">
                <h4>Resultados:</h4>
                <p><strong>Elementos limpiados:</strong> <?= count($results['cleaned']['cleaned']) ?></p>
                <?php if (!empty($results['cleaned']['cleaned'])): ?>
                <pre><?= htmlspecialchars(print_r(array_slice($results['cleaned']['cleaned'], 0, 10), true)) ?></pre>
                <?php endif; ?>
                
                <?php if (!empty($results['cleaned']['errors'])): ?>
                <p><strong>Errores:</strong></p>
                <pre><?= htmlspecialchars(print_r($results['cleaned']['errors'], true)) ?></pre>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Script de verificación completa -->
        <div class="fix-section">
            <h2>🔍 5. Script de Verificación SQL</h2>
            <p>Ejecuta estas consultas SQL para verificar el estado de las imágenes:</p>
            <pre>
-- Productos sin imágenes
SELECT p.id, p.name, p.seller_id 
FROM shop_products p 
LEFT JOIN shop_product_images spi ON p.id = spi.product_id 
WHERE spi.id IS NULL;

-- Imágenes sin productos
SELECT spi.id, spi.image_path 
FROM shop_product_images spi 
LEFT JOIN shop_products p ON spi.product_id = p.id 
WHERE p.id IS NULL;

-- Verificar rutas de imágenes
SELECT COUNT(*) as total_images,
       COUNT(CASE WHEN image_path LIKE 'uploads/%' THEN 1 END) as correct_paths,
       COUNT(CASE WHEN image_path NOT LIKE 'uploads/%' THEN 1 END) as incorrect_paths
FROM shop_product_images;

-- Productos con sus imágenes
SELECT p.name, 
       GROUP_CONCAT(spi.image_path) as images,
       COUNT(spi.id) as image_count
FROM shop_products p 
LEFT JOIN shop_product_images spi ON p.id = spi.product_id 
GROUP BY p.id, p.name
ORDER BY p.id DESC;
            </pre>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="shop-debug-images.php" class="btn">🔍 Volver al Diagnóstico</a>
            <a href="index.php" class="btn">🏪 Ir al Shop</a>
        </div>
    </div>
</body>
</html>