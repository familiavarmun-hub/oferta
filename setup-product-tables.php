<?php
/**
 * setup-product-tables.php
 * Script para crear las tablas necesarias para el sistema de productos OFERTA
 * Ejecutar una vez para configurar la base de datos
 */

require_once 'config.php';

echo "<h2>🔧 Setup de Tablas para Productos OFERTA</h2>";
echo "<pre>";

try {
    // Tabla de productos
    $sql = "CREATE TABLE IF NOT EXISTS shop_products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        seller_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        category VARCHAR(100),
        price DECIMAL(10,2) NOT NULL,
        currency VARCHAR(10) DEFAULT 'EUR',
        stock INT DEFAULT 1,
        origin_country VARCHAR(100),
        origin_city VARCHAR(100),
        destination_country VARCHAR(100),
        destination_city VARCHAR(100),
        delivery_date DATE,
        active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_seller (seller_id),
        INDEX idx_category (category),
        INDEX idx_active (active),
        INDEX idx_destination (destination_city)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $conexion->exec($sql);
    echo "✅ Tabla shop_products creada/verificada\n";

    // Tabla de imágenes de productos
    $sql = "CREATE TABLE IF NOT EXISTS shop_product_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        image_path VARCHAR(500) NOT NULL,
        is_primary TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_product (product_id),
        FOREIGN KEY (product_id) REFERENCES shop_products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $conexion->exec($sql);
    echo "✅ Tabla shop_product_images creada/verificada\n";

    // Tabla de favoritos de productos
    $sql = "CREATE TABLE IF NOT EXISTS product_favorites (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        user_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_favorite (product_id, user_id),
        INDEX idx_user (user_id),
        FOREIGN KEY (product_id) REFERENCES shop_products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $conexion->exec($sql);
    echo "✅ Tabla product_favorites creada/verificada\n";

    // Tabla de vistas de productos
    $sql = "CREATE TABLE IF NOT EXISTS product_views (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        user_id INT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_view (product_id, user_id),
        INDEX idx_product (product_id),
        FOREIGN KEY (product_id) REFERENCES shop_products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $conexion->exec($sql);
    echo "✅ Tabla product_views creada/verificada\n";

    // Tabla de órdenes de productos
    $sql = "CREATE TABLE IF NOT EXISTS shop_product_orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        buyer_id INT NOT NULL,
        quantity INT DEFAULT 1,
        unit_price DECIMAL(10,2) NOT NULL,
        total_price DECIMAL(10,2) NOT NULL,
        currency VARCHAR(10) DEFAULT 'EUR',
        status ENUM('pending', 'confirmed', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
        payment_status ENUM('pending', 'paid', 'refunded') DEFAULT 'pending',
        shipping_address TEXT,
        notes TEXT,
        qr_code VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_product (product_id),
        INDEX idx_buyer (buyer_id),
        INDEX idx_status (status),
        FOREIGN KEY (product_id) REFERENCES shop_products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $conexion->exec($sql);
    echo "✅ Tabla shop_product_orders creada/verificada\n";

    // Verificar si hay productos
    $stmt = $conexion->query("SELECT COUNT(*) FROM shop_products");
    $count = $stmt->fetchColumn();

    echo "\n📊 Estado actual:\n";
    echo "   - Productos en la base de datos: $count\n";

    if ($count == 0) {
        echo "\n⚠️  No hay productos en la base de datos.\n";
        echo "   Los productos se mostrarán cuando los vendedores los publiquen.\n";
        echo "   O puedes insertar datos de prueba ejecutando: setup-product-tables.php?sample=1\n";
    }

    // Insertar datos de ejemplo si se solicita
    if (isset($_GET['sample']) && $_GET['sample'] == '1' && $count == 0) {
        echo "\n📦 Insertando productos de ejemplo...\n";

        // Obtener un usuario existente para usar como vendedor
        $stmt = $conexion->query("SELECT id FROM accounts LIMIT 1");
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $sellerId = $user['id'];

            $sampleProducts = [
                [
                    'name' => 'iPhone 15 Pro Max 256GB',
                    'description' => 'Nuevo, sellado. Lo traigo de Estados Unidos. Garantía internacional.',
                    'category' => 'Electrónica',
                    'price' => 1199.00,
                    'currency' => 'EUR',
                    'stock' => 3,
                    'origin_country' => 'Estados Unidos',
                    'origin_city' => 'Miami',
                    'destination_country' => 'España',
                    'destination_city' => 'Madrid',
                    'delivery_date' => date('Y-m-d', strtotime('+10 days'))
                ],
                [
                    'name' => 'PlayStation 5 Digital Edition',
                    'description' => 'Consola PS5 versión digital. Nueva en caja. Precio más económico que en tiendas.',
                    'category' => 'Electrónica',
                    'price' => 449.00,
                    'currency' => 'EUR',
                    'stock' => 2,
                    'origin_country' => 'Estados Unidos',
                    'origin_city' => 'New York',
                    'destination_country' => 'España',
                    'destination_city' => 'Barcelona',
                    'delivery_date' => date('Y-m-d', strtotime('+7 days'))
                ],
                [
                    'name' => 'Nike Air Jordan 1 Retro High OG',
                    'description' => 'Zapatillas originales, edición limitada. Tallas disponibles: 42, 43, 44.',
                    'category' => 'Moda',
                    'price' => 189.00,
                    'currency' => 'EUR',
                    'stock' => 5,
                    'origin_country' => 'Estados Unidos',
                    'origin_city' => 'Los Angeles',
                    'destination_country' => 'España',
                    'destination_city' => 'Valencia',
                    'delivery_date' => date('Y-m-d', strtotime('+14 days'))
                ],
                [
                    'name' => 'Vitaminas Centrum Adults 365 tabletas',
                    'description' => 'Multivitamínico completo. Presentación de 365 tabletas (1 año). Mucho más económico que en farmacias locales.',
                    'category' => 'Salud',
                    'price' => 35.00,
                    'currency' => 'EUR',
                    'stock' => 10,
                    'origin_country' => 'Estados Unidos',
                    'origin_city' => 'Miami',
                    'destination_country' => 'España',
                    'destination_city' => 'Sevilla',
                    'delivery_date' => date('Y-m-d', strtotime('+5 days'))
                ],
                [
                    'name' => 'Perfume Chanel N°5 100ml',
                    'description' => 'Original, sellado. Comprado en Duty Free. Precio especial.',
                    'category' => 'Belleza',
                    'price' => 120.00,
                    'currency' => 'EUR',
                    'stock' => 4,
                    'origin_country' => 'Francia',
                    'origin_city' => 'París',
                    'destination_country' => 'España',
                    'destination_city' => 'Madrid',
                    'delivery_date' => date('Y-m-d', strtotime('+3 days'))
                ],
                [
                    'name' => 'MacBook Air M3 8GB 256GB',
                    'description' => 'Último modelo, color Space Gray. Nuevo, sellado con garantía Apple.',
                    'category' => 'Electrónica',
                    'price' => 1099.00,
                    'currency' => 'EUR',
                    'stock' => 2,
                    'origin_country' => 'Estados Unidos',
                    'origin_city' => 'San Francisco',
                    'destination_country' => 'España',
                    'destination_city' => 'Bilbao',
                    'delivery_date' => date('Y-m-d', strtotime('+12 days'))
                ]
            ];

            $stmt = $conexion->prepare("INSERT INTO shop_products
                (seller_id, name, description, category, price, currency, stock,
                 origin_country, origin_city, destination_country, destination_city, delivery_date)
                VALUES
                (:seller_id, :name, :description, :category, :price, :currency, :stock,
                 :origin_country, :origin_city, :destination_country, :destination_city, :delivery_date)");

            foreach ($sampleProducts as $product) {
                $stmt->execute([
                    ':seller_id' => $sellerId,
                    ':name' => $product['name'],
                    ':description' => $product['description'],
                    ':category' => $product['category'],
                    ':price' => $product['price'],
                    ':currency' => $product['currency'],
                    ':stock' => $product['stock'],
                    ':origin_country' => $product['origin_country'],
                    ':origin_city' => $product['origin_city'],
                    ':destination_country' => $product['destination_country'],
                    ':destination_city' => $product['destination_city'],
                    ':delivery_date' => $product['delivery_date']
                ]);
                echo "   ✅ Producto '{$product['name']}' insertado\n";
            }

            echo "\n🎉 Se insertaron " . count($sampleProducts) . " productos de ejemplo.\n";
        } else {
            echo "   ⚠️ No hay usuarios en la base de datos para asignar como vendedor.\n";
        }
    }

    echo "\n✅ Setup completado correctamente.\n";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>
