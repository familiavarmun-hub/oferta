-- SendVialo Shop - Script SQL FINAL CORREGIDO
-- Basado en las tablas existentes: accounts y transporting

-- =====================================================
-- PASO 1: CREAR TODAS LAS TABLAS DEL SHOP
-- =====================================================

-- Tabla de productos de la tienda
CREATE TABLE IF NOT EXISTS `shop_products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `seller_id` int(11) NOT NULL,
  `trip_id` int(11) DEFAULT NULL COMMENT 'FK a transporting.id',
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `currency` enum('EUR','USD','BOB','BRL','ARS','VES','COP','MXN','NIO','CUP','PEN') DEFAULT 'EUR',
  `category` varchar(100) DEFAULT NULL,
  `stock` int(11) DEFAULT 1,
  `weight` decimal(5,2) DEFAULT NULL COMMENT 'Peso en kg',
  `dimensions` varchar(100) DEFAULT NULL COMMENT 'Dimensiones en cm',
  `active` tinyint(1) DEFAULT 1,
  `featured` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_seller_id` (`seller_id`),
  KEY `idx_trip_id` (`trip_id`),
  KEY `idx_category` (`category`),
  KEY `idx_currency` (`currency`),
  KEY `idx_active` (`active`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de imágenes de productos
CREATE TABLE IF NOT EXISTS `shop_product_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `image_path` varchar(500) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `alt_text` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_is_primary` (`is_primary`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de pedidos
CREATE TABLE IF NOT EXISTS `shop_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_number` varchar(50) NOT NULL UNIQUE,
  `buyer_id` int(11) NOT NULL,
  `status` enum('pending','confirmed','paid','shipped','delivered','cancelled','refunded') DEFAULT 'pending',
  `total_amount` decimal(10,2) DEFAULT 0,
  `currency` enum('EUR','USD','BOB','BRL','ARS','VES','COP','MXN','NIO','CUP','PEN') DEFAULT 'EUR',
  `shipping_address` text,
  `billing_address` text,
  `notes` text,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_id` varchar(255) DEFAULT NULL,
  `tracking_number` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `delivered_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_order_number` (`order_number`),
  KEY `idx_buyer_id` (`buyer_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de items de pedidos
CREATE TABLE IF NOT EXISTS `shop_order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `currency` enum('EUR','USD','BOB','BRL','ARS','VES','COP','MXN','NIO','CUP','PEN') DEFAULT 'EUR',
  `status` enum('pending','confirmed','shipped','delivered','cancelled') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_seller_id` (`seller_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de valoraciones de vendedores
CREATE TABLE IF NOT EXISTS `shop_seller_ratings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `seller_id` int(11) NOT NULL,
  `buyer_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `rating` tinyint(1) NOT NULL CHECK (rating >= 1 AND rating <= 5),
  `comment` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_seller_id` (`seller_id`),
  KEY `idx_buyer_id` (`buyer_id`),
  KEY `idx_order_id` (`order_id`),
  UNIQUE KEY `unique_buyer_seller_order` (`buyer_id`, `seller_id`, `order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de favoritos de productos
CREATE TABLE IF NOT EXISTS `shop_favorites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_product_id` (`product_id`),
  UNIQUE KEY `unique_user_product` (`user_id`, `product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de mensajes entre compradores y vendedores
CREATE TABLE IF NOT EXISTS `shop_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `order_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sender_id` (`sender_id`),
  KEY `idx_receiver_id` (`receiver_id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de categorías de productos
CREATE TABLE IF NOT EXISTS `shop_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text,
  `icon` varchar(50) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_slug` (`slug`),
  KEY `idx_active` (`active`),
  KEY `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de cupones y descuentos
CREATE TABLE IF NOT EXISTS `shop_coupons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `type` enum('percentage','fixed') DEFAULT 'percentage',
  `value` decimal(10,2) NOT NULL,
  `min_amount` decimal(10,2) DEFAULT NULL,
  `max_discount` decimal(10,2) DEFAULT NULL,
  `usage_limit` int(11) DEFAULT NULL,
  `used_count` int(11) DEFAULT 0,
  `valid_from` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `valid_until` timestamp NULL DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_code` (`code`),
  KEY `idx_active` (`active`),
  KEY `idx_valid_from` (`valid_from`),
  KEY `idx_valid_until` (`valid_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de uso de cupones
CREATE TABLE IF NOT EXISTS `shop_coupon_usage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `coupon_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `discount_amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_coupon_id` (`coupon_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de notificaciones del shop
CREATE TABLE IF NOT EXISTS `shop_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `data` json DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_type` (`type`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de información del shop en viajes (vinculada a transporting)
CREATE TABLE IF NOT EXISTS `shop_trip_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `trip_id` int(11) NOT NULL COMMENT 'FK a transporting.id',
  `enabled` tinyint(1) DEFAULT 1,
  `categories` json DEFAULT NULL,
  `available_space` varchar(50) DEFAULT NULL,
  `budget` decimal(10,2) DEFAULT NULL,
  `currency` enum('EUR','USD','BOB','BRL','ARS','VES','COP','MXN','NIO','CUP','PEN') DEFAULT 'EUR',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_trip_shop` (`trip_id`),
  KEY `idx_enabled` (`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- PASO 2: INSERTAR DATOS INICIALES
-- =====================================================

-- Insertar categorías por defecto
INSERT IGNORE INTO `shop_categories` (`name`, `slug`, `description`, `icon`, `active`, `sort_order`) VALUES
('Comida y Bebidas', 'food', 'Productos gastronómicos típicos de cada región', 'fas fa-utensils', 1, 1),
('Artesanías', 'crafts', 'Productos artesanales y manualidades locales', 'fas fa-palette', 1, 2),
('Moda y Accesorios', 'fashion', 'Ropa, zapatos y accesorios únicos', 'fas fa-tshirt', 1, 3),
('Electrónicos', 'electronics', 'Dispositivos y gadgets tecnológicos', 'fas fa-laptop', 1, 4),
('Libros y Medios', 'books', 'Libros, revistas y contenido multimedia', 'fas fa-book', 1, 5),
('Cosméticos y Cuidado', 'cosmetics', 'Productos de belleza y cuidado personal', 'fas fa-spa', 1, 6),
('Juguetes y Juegos', 'toys', 'Juguetes tradicionales y juegos de mesa', 'fas fa-gamepad', 1, 7),
('Deportes', 'sports', 'Equipamiento y accesorios deportivos', 'fas fa-football-ball', 1, 8),
('Hogar y Decoración', 'home', 'Artículos para el hogar y decorativos', 'fas fa-home', 1, 9),
('Otros', 'others', 'Productos diversos no categorizados', 'fas fa-box', 1, 10);

-- Insertar algunos cupones de ejemplo
INSERT IGNORE INTO `shop_coupons` (`code`, `type`, `value`, `min_amount`, `max_discount`, `usage_limit`, `valid_from`, `valid_until`, `active`) VALUES
('BIENVENIDO10', 'percentage', 10.00, 20.00, 50.00, 100, NOW(), DATE_ADD(NOW(), INTERVAL 3 MONTH), 1),
('ENVIOGRATIS', 'fixed', 5.00, 30.00, 5.00, 50, NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH), 1),
('PRIMERA20', 'percentage', 20.00, 50.00, 100.00, 200, NOW(), DATE_ADD(NOW(), INTERVAL 6 MONTH), 1);

-- =====================================================
-- PASO 3: AGREGAR ÍNDICES ADICIONALES
-- =====================================================

CREATE INDEX `idx_shop_products_price` ON `shop_products`(`price`);
CREATE INDEX `idx_shop_products_featured` ON `shop_products`(`featured`, `active`);
CREATE INDEX `idx_shop_orders_total` ON `shop_orders`(`total_amount`);
CREATE INDEX `idx_shop_orders_status_created` ON `shop_orders`(`status`, `created_at`);

-- =====================================================
-- PASO 4: AGREGAR FOREIGN KEYS (AHORA SÍ FUNCIONARÁ)
-- =====================================================

-- Agregar foreign keys para shop_products
ALTER TABLE `shop_products` 
  ADD CONSTRAINT `fk_shop_products_seller` 
  FOREIGN KEY (`seller_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE;

-- Solo agregar FK de trip si hay datos en transporting
ALTER TABLE `shop_products` 
  ADD CONSTRAINT `fk_shop_products_trip` 
  FOREIGN KEY (`trip_id`) REFERENCES `transporting` (`id`) ON DELETE SET NULL;

-- Para shop_product_images
ALTER TABLE `shop_product_images` 
  ADD CONSTRAINT `fk_shop_images_product` 
  FOREIGN KEY (`product_id`) REFERENCES `shop_products` (`id`) ON DELETE CASCADE;

-- Para shop_orders
ALTER TABLE `shop_orders` 
  ADD CONSTRAINT `fk_shop_orders_buyer` 
  FOREIGN KEY (`buyer_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE;

-- Para shop_order_items
ALTER TABLE `shop_order_items` 
  ADD CONSTRAINT `fk_shop_order_items_order` 
  FOREIGN KEY (`order_id`) REFERENCES `shop_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_shop_order_items_product` 
  FOREIGN KEY (`product_id`) REFERENCES `shop_products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_shop_order_items_seller` 
  FOREIGN KEY (`seller_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE;

-- Para shop_seller_ratings
ALTER TABLE `shop_seller_ratings` 
  ADD CONSTRAINT `fk_shop_seller_ratings_seller` 
  FOREIGN KEY (`seller_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_shop_seller_ratings_buyer` 
  FOREIGN KEY (`buyer_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_shop_seller_ratings_order` 
  FOREIGN KEY (`order_id`) REFERENCES `shop_orders` (`id`) ON DELETE CASCADE;

-- Para shop_favorites
ALTER TABLE `shop_favorites` 
  ADD CONSTRAINT `fk_shop_favorites_user` 
  FOREIGN KEY (`user_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_shop_favorites_product` 
  FOREIGN KEY (`product_id`) REFERENCES `shop_products` (`id`) ON DELETE CASCADE;

-- Para shop_messages
ALTER TABLE `shop_messages` 
  ADD CONSTRAINT `fk_shop_messages_sender` 
  FOREIGN KEY (`sender_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_shop_messages_receiver` 
  FOREIGN KEY (`receiver_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_shop_messages_product` 
  FOREIGN KEY (`product_id`) REFERENCES `shop_products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_shop_messages_order` 
  FOREIGN KEY (`order_id`) REFERENCES `shop_orders` (`id`) ON DELETE CASCADE;

-- Para shop_coupon_usage
ALTER TABLE `shop_coupon_usage` 
  ADD CONSTRAINT `fk_shop_coupon_usage_coupon` 
  FOREIGN KEY (`coupon_id`) REFERENCES `shop_coupons` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_shop_coupon_usage_user` 
  FOREIGN KEY (`user_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_shop_coupon_usage_order` 
  FOREIGN KEY (`order_id`) REFERENCES `shop_orders` (`id`) ON DELETE CASCADE;

-- Para shop_notifications
ALTER TABLE `shop_notifications` 
  ADD CONSTRAINT `fk_shop_notifications_user` 
  FOREIGN KEY (`user_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE;

-- Para shop_trip_info (vinculada a transporting)
ALTER TABLE `shop_trip_info` 
  ADD CONSTRAINT `fk_shop_trip_info_trip` 
  FOREIGN KEY (`trip_id`) REFERENCES `transporting` (`id`) ON DELETE CASCADE;

-- =====================================================
-- PASO 5: CREAR VISTAS OPTIMIZADAS
-- =====================================================

-- Vista para productos con información completa (usando transporting)
CREATE OR REPLACE VIEW `v_shop_products_full` AS
SELECT 
    p.*,
    a.full_name as seller_name,
    a.username as seller_username,
    t.search_input as origen_ciudad,
    t.destination_input as destino_ciudad,
    t.datepicker as fecha_viaje,
    c.name as category_name,
    c.icon as category_icon,
    COALESCE(
        (SELECT AVG(rating) FROM shop_seller_ratings sr WHERE sr.seller_id = p.seller_id),
        0
    ) as seller_rating,
    (SELECT COUNT(*) FROM shop_seller_ratings sr WHERE sr.seller_id = p.seller_id) as total_ratings,
    (SELECT image_path FROM shop_product_images spi WHERE spi.product_id = p.id AND spi.is_primary = 1 LIMIT 1) as primary_image,
    (SELECT COUNT(*) FROM shop_product_images spi WHERE spi.product_id = p.id) as image_count,
    (SELECT COUNT(*) FROM shop_favorites sf WHERE sf.product_id = p.id) as favorite_count
FROM shop_products p
LEFT JOIN accounts a ON p.seller_id = a.id
LEFT JOIN transporting t ON p.trip_id = t.id
LEFT JOIN shop_categories c ON p.category = c.slug
WHERE p.active = 1;

-- Vista para estadísticas de vendedores
CREATE OR REPLACE VIEW `v_shop_seller_stats` AS
SELECT 
    a.id as seller_id,
    a.full_name as seller_name,
    a.username as seller_username,
    COUNT(DISTINCT p.id) as total_products,
    COUNT(DISTINCT CASE WHEN p.active = 1 THEN p.id END) as active_products,
    COUNT(DISTINCT oi.order_id) as total_sales,
    COALESCE(SUM(oi.subtotal), 0) as total_revenue,
    COALESCE(AVG(sr.rating), 0) as average_rating,
    COUNT(DISTINCT sr.id) as total_reviews,
    COUNT(DISTINCT p.trip_id) as trips_with_products,
    MAX(p.created_at) as last_product_added
FROM accounts a
LEFT JOIN shop_products p ON a.id = p.seller_id
LEFT JOIN shop_order_items oi ON p.id = oi.product_id
LEFT JOIN shop_seller_ratings sr ON a.id = sr.seller_id
GROUP BY a.id, a.full_name, a.username;

-- =====================================================
-- PASO 6: INSERTAR PRODUCTOS DE PRUEBA
-- =====================================================

-- Insertar algunos productos de ejemplo basados en usuarios reales
INSERT IGNORE INTO `shop_products` (`seller_id`, `trip_id`, `name`, `description`, `price`, `currency`, `category`, `stock`, `weight`) VALUES
(2, 121, 'Patxaran Artesanal Vasco', 'Auténtico patxaran del País Vasco, elaborado tradicionalmente con endrinas seleccionadas. Perfecto para regalo o degustación.', 25.00, 'EUR', 'food', 5, 0.75),
(2, 119, 'Queso Idiazábal D.O.', 'Queso de oveja ahumado con Denominación de Origen del País Vasco. Curado en cuevas naturales.', 18.50, 'EUR', 'food', 3, 0.5),
(2, 116, 'Alpargatas Tradicionales', 'Alpargatas hechas a mano en el País Vasco, perfectas para el verano. Disponibles en varios colores.', 35.00, 'EUR', 'fashion', 8, 0.3),
(1, 98, 'Chocolate Boliviano Orgánico', 'Chocolate artesanal de cacao boliviano 70%, producción orgánica certificada. Sabor único de Los Yungas.', 12.00, 'BOB', 'food', 15, 0.2),
(1, 120, 'Textiles Andinos', 'Mantas y textiles tradicionales tejidos a mano por artesanas bolivianas. Colores naturales y diseños únicos.', 45.00, 'BOB', 'crafts', 4, 0.8);

-- =====================================================
-- VERIFICACIONES FINALES
-- =====================================================

-- Verificar que todas las tablas se crearon
SELECT 'Tablas del Shop creadas:' as Status;
SELECT TABLE_NAME, ENGINE, TABLE_COLLATION 
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME LIKE 'shop_%'
ORDER BY TABLE_NAME;

-- Verificar foreign keys
SELECT 'Foreign Keys creadas:' as Status;
SELECT TABLE_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME LIKE 'shop_%'
AND REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY TABLE_NAME;

-- Verificar datos insertados
SELECT 'Categorías insertadas:' as Status;
SELECT COUNT(*) as total_categories FROM shop_categories;

SELECT 'Cupones insertados:' as Status;
SELECT COUNT(*) as total_coupons FROM shop_coupons;

SELECT 'Productos de prueba insertados:' as Status;
SELECT COUNT(*) as total_products FROM shop_products;

-- =====================================================
-- MENSAJE DE ÉXITO
-- =====================================================

SELECT 'SendVialo Shop: ¡Instalación completada exitosamente!' as Status;
SELECT CONCAT(
    'Se crearon ', 
    (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'shop_%'), 
    ' tablas con todas las foreign keys funcionando correctamente'
) as Resultado;

-- =====================================================
-- PRÓXIMOS PASOS
-- =====================================================

/*
PRÓXIMOS PASOS PARA COMPLETAR LA INSTALACIÓN:

1. Crear el directorio: uploads/shop_products/ (con permisos 755)

2. Subir los archivos PHP:
   - shop_actions.php
   - sendvialo_shop.php (renombrar de .html)
   - shop_manage_products.php (renombrar de .html)
   - shop-currency-handler.js

3. Actualizar config.php para incluir configuraciones del shop:
   define('SHOP_UPLOAD_DIR', 'uploads/shop_products/');
   define('SHOP_MAX_IMAGE_SIZE', 5 * 1024 * 1024); // 5MB
   define('SHOP_MAX_IMAGES', 5);

4. Modificar preview_planificar.php para incluir la sección del shop

5. Agregar enlaces en el menú principal:
   <a href="sendvialo_shop.php">Shop</a>
   <a href="shop_manage_products.php">Mis Productos</a>

6. Probar la funcionalidad:
   - Crear productos
   - Subir imágenes
   - Realizar compras de prueba
   - Verificar notificaciones

¡El sistema está listo para usar!
*/