<?php
// create_upload_directories.php - Crear directorios necesarios para el shop
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Directorios de Upload - SendVialo Shop</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .info { color: blue; }
        .directory-item { 
            padding: 10px; 
            margin: 5px 0; 
            border: 1px solid #ddd; 
            border-radius: 5px; 
            background: #f9f9f9; 
        }
    </style>
</head>
<body>
    <h1>🗂️ Crear Directorios de Upload</h1>
    
    <?php
    // Directorios que necesita el shop
    $directories = [
        'uploads' => __DIR__ . '/uploads',
        'uploads/shop_products' => __DIR__ . '/uploads/shop_products',
        'uploads/shop_products/thumbs' => __DIR__ . '/uploads/shop_products/thumbs',
    ];
    
    $all_success = true;
    
    foreach ($directories as $name => $path) {
        echo "<div class='directory-item'>";
        echo "<strong>$name:</strong> $path<br>";
        
        if (is_dir($path)) {
            echo "<span class='success'>✅ Ya existe</span>";
            
            // Verificar permisos
            if (is_writable($path)) {
                echo " - <span class='success'>Escribible ✅</span>";
            } else {
                echo " - <span class='error'>No escribible ❌</span>";
                $all_success = false;
            }
        } else {
            // Intentar crear el directorio
            if (mkdir($path, 0755, true)) {
                echo "<span class='success'>✅ Creado exitosamente</span>";
                
                // Verificar permisos después de crear
                if (is_writable($path)) {
                    echo " - <span class='success'>Escribible ✅</span>";
                } else {
                    echo " - <span class='error'>No escribible ❌</span>";
                    $all_success = false;
                }
            } else {
                echo "<span class='error'>❌ Error al crear</span>";
                $all_success = false;
            }
        }
        echo "</div>";
    }
    
    // Crear archivo .htaccess para seguridad
    $htaccess_path = __DIR__ . '/uploads/.htaccess';
    $htaccess_content = "# Protección para directorio uploads
Options -Indexes
<Files ~ \"\\.(php|php3|php4|php5|phtml|pl|py|jsp|asp|sh|cgi)$\">
    deny from all
</Files>
";
    
    echo "<div class='directory-item'>";
    echo "<strong>.htaccess de seguridad:</strong> $htaccess_path<br>";
    
    if (file_exists($htaccess_path)) {
        echo "<span class='success'>✅ Ya existe</span>";
    } else {
        if (file_put_contents($htaccess_path, $htaccess_content)) {
            echo "<span class='success'>✅ Creado exitosamente</span>";
        } else {
            echo "<span class='error'>❌ Error al crear</span>";
            $all_success = false;
        }
    }
    echo "</div>";
    
    // Crear archivo index.php para evitar listado
    $index_files = [
        __DIR__ . '/uploads/index.php',
        __DIR__ . '/uploads/shop_products/index.php',
        __DIR__ . '/uploads/shop_products/thumbs/index.php'
    ];
    
    $index_content = "<?php\n// Archivo de protección - no borrar\nheader('HTTP/1.0 403 Forbidden');\nexit('Acceso denegado');\n?>";
    
    foreach ($index_files as $index_file) {
        $relative_path = str_replace(__DIR__ . '/', '', $index_file);
        echo "<div class='directory-item'>";
        echo "<strong>index.php protector:</strong> $relative_path<br>";
        
        if (file_exists($index_file)) {
            echo "<span class='success'>✅ Ya existe</span>";
        } else {
            if (file_put_contents($index_file, $index_content)) {
                echo "<span class='success'>✅ Creado exitosamente</span>";
            } else {
                echo "<span class='error'>❌ Error al crear</span>";
                $all_success = false;
            }
        }
        echo "</div>";
    }
    
    // Resumen final
    echo "<hr>";
    if ($all_success) {
        echo "<h2 class='success'>🎉 ¡Configuración completada exitosamente!</h2>";
        echo "<p class='info'>Todos los directorios están listos. Ahora puedes:</p>";
        echo "<ul>";
        echo "<li>✅ Crear productos con imágenes</li>";
        echo "<li>✅ Subir múltiples imágenes por producto</li>";
        echo "<li>✅ Usar el shop completamente</li>";
        echo "</ul>";
        echo "<p><strong>Próximo paso:</strong> <a href='index.php'>Ir al Shop</a> o <a href='shop-manage-products.php'>Gestionar productos</a></p>";
    } else {
        echo "<h2 class='error'>⚠️ Algunos problemas detectados</h2>";
        echo "<p class='error'>Revisa los errores de arriba. Posibles soluciones:</p>";
        echo "<ul>";
        echo "<li>Verificar permisos del directorio web</li>";
        echo "<li>Ejecutar desde línea de comandos con permisos apropiados</li>";
        echo "<li>Contactar al administrador del servidor</li>";
        echo "</ul>";
    }
    ?>
    
    <hr>
    <h3>🔧 Información adicional</h3>
    <p><strong>Directorio base:</strong> <?php echo __DIR__; ?></p>
    <p><strong>Usuario PHP:</strong> <?php echo function_exists('posix_getpwuid') && function_exists('posix_geteuid') ? posix_getpwuid(posix_geteuid())['name'] : 'No disponible'; ?></p>
    <p><strong>Permisos actuales del directorio:</strong> <?php echo substr(sprintf('%o', fileperms(__DIR__)), -4); ?></p>
    
    <p><a href="test_shop.php">⬅️ Volver al diagnóstico</a></p>
</body>
</html>