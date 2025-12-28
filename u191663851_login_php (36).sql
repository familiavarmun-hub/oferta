-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 27-12-2025 a las 17:17:06
-- Versión del servidor: 11.8.3-MariaDB-log
-- Versión de PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `u191663851_login_php`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `request_favorites`
--

CREATE TABLE `request_favorites` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `share_meta_config`
--

CREATE TABLE `share_meta_config` (
  `id` int(11) NOT NULL,
  `og_title` varchar(255) DEFAULT NULL,
  `og_description` text DEFAULT NULL,
  `og_image` varchar(255) DEFAULT NULL,
  `twitter_title` varchar(255) DEFAULT NULL,
  `twitter_description` text DEFAULT NULL,
  `twitter_image` varchar(255) DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `shop_categories`
--

CREATE TABLE `shop_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `shop_chat_messages`
--

CREATE TABLE `shop_chat_messages` (
  `id` int(11) NOT NULL,
  `proposal_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text DEFAULT NULL,
  `photo_path` varchar(500) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `message_type` enum('text','photo','location','mixed') DEFAULT 'text',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabla de mensajes del chat del shop con soporte para texto, fotos y ubicaciones';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `shop_conversations`
--

CREATE TABLE `shop_conversations` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL COMMENT 'FK a shop_products.id',
  `buyer_id` int(11) NOT NULL COMMENT 'FK a accounts.id - Comprador interesado',
  `seller_id` int(11) NOT NULL COMMENT 'FK a accounts.id - Vendedor del producto',
  `status` enum('active','archived','blocked') DEFAULT 'active' COMMENT 'Estado de la conversación',
  `last_message_at` timestamp NULL DEFAULT NULL COMMENT 'Fecha del último mensaje',
  `last_message_preview` varchar(255) DEFAULT NULL COMMENT 'Preview del último mensaje',
  `unread_count_buyer` int(11) DEFAULT 0 COMMENT 'Mensajes no leídos por el comprador',
  `unread_count_seller` int(11) DEFAULT 0 COMMENT 'Mensajes no leídos por el vendedor',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Conversaciones de chat entre compradores y vendedores';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `shop_coupons`
--

CREATE TABLE `shop_coupons` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `type` enum('percentage','fixed') DEFAULT 'percentage',
  `value` decimal(10,2) NOT NULL,
  `min_amount` decimal(10,2) DEFAULT NULL,
  `max_discount` decimal(10,2) DEFAULT NULL,
  `usage_limit` int(11) DEFAULT NULL,
  `used_count` int(11) DEFAULT 0,
  `valid_from` timestamp NULL DEFAULT current_timestamp(),
  `valid_until` timestamp NULL DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `shop_coupon_usage`
--

CREATE TABLE `shop_coupon_usage` (
  `id` int(11) NOT NULL,
  `coupon_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `discount_amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `shop_deliveries`
--

CREATE TABLE `shop_deliveries` (
  `id` int(11) NOT NULL,
  `proposal_id` int(11) NOT NULL COMMENT 'FK a shop_request_proposals.id',
  `payment_id` int(11) NOT NULL COMMENT 'FK a payments_in_custody.id',
  `requester_id` int(11) NOT NULL COMMENT 'FK a accounts.id - Usuario solicitante',
  `traveler_id` int(11) NOT NULL COMMENT 'FK a accounts.id - Viajero',
  `qr_code_unique_id` varchar(255) NOT NULL COMMENT 'ID único del QR generado',
  `qr_code_path` varchar(500) DEFAULT NULL COMMENT 'Ruta del archivo QR en servidor',
  `qr_data_json` text NOT NULL COMMENT 'JSON con datos codificados en el QR',
  `delivery_state` enum('pending','in_transit','at_destination','delivered') DEFAULT 'pending' COMMENT 'Estado de la entrega',
  `qr_scanned_at` timestamp NULL DEFAULT NULL COMMENT 'Fecha/hora cuando se escaneó el QR',
  `qr_scanned_by` int(11) DEFAULT NULL COMMENT 'FK a accounts.id - Usuario que escaneó',
  `payment_released` tinyint(1) DEFAULT 0 COMMENT 'Si el pago fue liberado al viajero',
  `payment_released_at` timestamp NULL DEFAULT NULL COMMENT 'Fecha/hora de liberación del pago',
  `email_sent` tinyint(1) DEFAULT 0 COMMENT 'Si se envió email con QR',
  `email_sent_at` timestamp NULL DEFAULT NULL COMMENT 'Fecha/hora de envío de email',
  `notes` text DEFAULT NULL COMMENT 'Notas adicionales sobre la entrega',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracking de entregas con sistema QR para Shop Request';

--
-- Disparadores `shop_deliveries`
--
DELIMITER $$
CREATE TRIGGER `trg_shop_delivery_state_change` AFTER UPDATE ON `shop_deliveries` FOR EACH ROW BEGIN
    IF OLD.delivery_state != NEW.delivery_state THEN
        INSERT INTO shop_delivery_state_history (
            delivery_id,
            previous_state,
            new_state,
            change_method,
            notes,
            created_at
        ) VALUES (
            NEW.id,
            OLD.delivery_state,
            NEW.delivery_state,
            'automatic',
            CONCAT('Estado cambiado de ', OLD.delivery_state, ' a ', NEW.delivery_state),
            NOW()
        );
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `shop_delivery_state_history`
--

CREATE TABLE `shop_delivery_state_history` (
  `id` int(11) NOT NULL,
  `delivery_id` int(11) NOT NULL COMMENT 'FK a shop_deliveries.id',
  `previous_state` enum('pending','in_transit','at_destination','delivered') DEFAULT NULL COMMENT 'Estado anterior',
  `new_state` enum('pending','in_transit','at_destination','delivered') NOT NULL COMMENT 'Nuevo estado',
  `changed_by` int(11) DEFAULT NULL COMMENT 'FK a accounts.id - Usuario que cambió el estado',
  `change_method` enum('manual','qr_scan','automatic') DEFAULT 'manual' COMMENT 'Método de cambio',
  `notes` text DEFAULT NULL COMMENT 'Notas sobre el cambio',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historial de cambios de estado de entregas';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `shop_favorites`
--

CREATE TABLE `shop_favorites` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `shop_followers`
--

CREATE TABLE `shop_followers` (
  `id` int(11) NOT NULL,
  `follower_id` int(11) NOT NULL COMMENT 'ID del usuario que sigue',
  `seller_id` int(11) NOT NULL COMMENT 'ID del vendedor seguido',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Relación de seguidores de vendedores';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `shop_messages`
--

CREATE TABLE `shop_messages` (
  `id` int(11) NOT NULL,
  `conversation_id` int(11) DEFAULT NULL COMMENT 'FK a shop_conversations.id',
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `order_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `message_type` enum('text','price_offer','system') DEFAULT 'text' COMMENT 'Tipo de mensaje',
  `price_offer_amount` decimal(10,2) DEFAULT NULL COMMENT 'Monto de la oferta de precio',
  `price_offer_currency` varchar(3) DEFAULT NULL COMMENT 'Moneda de la oferta',
  `price_offer_status` enum('pending','accepted','rejected','countered','expired') DEFAULT NULL COMMENT 'Estado de la oferta',
  `price_offer_expires_at` timestamp NULL DEFAULT NULL COMMENT 'Fecha de expiración de la oferta',
  `replied_to_message_id` int(11) DEFAULT NULL COMMENT 'ID del mensaje al que responde',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Datos adicionales (imágenes, archivos, etc)' CHECK (json_valid(`metadata`)),
  `is_price_offer` tinyint(1) DEFAULT 0 COMMENT 'Si es un mensaje de oferta de precio',
  `parent_offer_id` int(11) DEFAULT NULL COMMENT 'ID de la oferta padre si es una contraoferta'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Disparadores `shop_messages`
--
DELIMITER $$
CREATE TRIGGER `after_message_insert` AFTER INSERT ON `shop_messages` FOR EACH ROW BEGIN
    UPDATE shop_conversations
    SET
        last_message_at = NEW.created_at,
        last_message_preview = CASE
            WHEN NEW.message_type = 'price_offer' THEN CONCAT('? Oferta: ', NEW.price_offer_amount)
            WHEN NEW.message_type = 'system' THEN NEW.message
            ELSE LEFT(NEW.message, 100)
        END,
        unread_count_buyer = CASE
            WHEN NEW.receiver_id = (SELECT buyer_id FROM shop_conversations WHERE id = NEW.conversation_id) 
                 AND NEW.sender_id != (SELECT buyer_id FROM shop_conversations WHERE id = NEW.conversation_id) 
            THEN unread_count_buyer + 1
            ELSE unread_count_buyer
        END,
        unread_count_seller = CASE
            WHEN NEW.receiver_id = (SELECT seller_id FROM shop_conversations WHERE id = NEW.conversation_id) 
                 AND NEW.sender_id != (SELECT seller_id FROM shop_conversations WHERE id = NEW.conversation_id) 
            THEN unread_count_seller + 1
            ELSE unread_count_seller
        END
    WHERE id = NEW.conversation_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_price_offer_insert` AFTER INSERT ON `shop_messages` FOR EACH ROW BEGIN
    IF NEW.message_type = 'price_offer' THEN
        -- Obtener información del producto y conversación
        INSERT INTO shop_price_offer_history (
            conversation_id,
            product_id,
            message_id,
            sender_id,
            receiver_id,
            original_price,
            offered_price,
            currency,
            discount_percentage,
            status,
            expires_at
        )
        SELECT
            NEW.conversation_id,
            c.product_id,
            NEW.id,
            NEW.sender_id,
            NEW.receiver_id,
            p.price,
            NEW.price_offer_amount,
            NEW.price_offer_currency,
            ROUND(((p.price - NEW.price_offer_amount) / p.price) * 100, 2),
            NEW.price_offer_status,
            DATE_ADD(NOW(), INTERVAL 48 HOUR)
        FROM shop_conversations c
        JOIN shop_products p ON c.product_id = p.id
        WHERE c.id = NEW.conversation_id;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_price_offer_message` AFTER INSERT ON `shop_messages` FOR EACH ROW BEGIN
    IF NEW.message_type = 'price_offer' AND NEW.price_offer_amount IS NOT NULL THEN
        INSERT INTO shop_price_offer_history (
            conversation_id,
            product_id,
            message_id,
            buyer_id,
            seller_id,
            original_price,
            offered_price,
            status,
            expires_at
        )
        SELECT
            NEW.conversation_id,
            c.product_id,
            NEW.id,
            c.buyer_id,
            c.seller_id,
            p.price,
            NEW.price_offer_amount,
            NEW.price_offer_status,
            DATE_ADD(NOW(), INTERVAL 48 HOUR)
        FROM shop_conversations c
        JOIN shop_products p ON c.product_id = p.id
        WHERE c.id = NEW.conversation_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `shop_notifications`
--

CREATE TABLE `shop_notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `shop_notifications_stats`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `shop_notifications_stats` (
`user_id` int(11)
,`total_notifications` bigint(21)
,`unread_count` decimal(22,0)
,`important_count` decimal(22,0)
,`last_notification_at` timestamp
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `shop_orders`
--

CREATE TABLE `shop_orders` (
  `id` int(11) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `buyer_id` int(11) NOT NULL,
  `status` enum('pending','confirmed','paid','shipped','delivered','cancelled','refunded') DEFAULT 'pending',
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `currency` enum('EUR','USD','BOB','BRL','ARS','VES','COP','MXN','NIO','CUP','PEN') DEFAULT 'EUR',
  `shipping_address` text DEFAULT NULL,
  `billing_address` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_id` varchar(255) DEFAULT NULL,
  `tracking_number` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `delivered_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `shop_order_items`
--

CREATE TABLE `shop_order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `currency` enum('EUR','USD','BOB','BRL','ARS','VES','COP','MXN','NIO','CUP','PEN') DEFAULT 'EUR',
  `status` enum('pending','confirmed','shipped','delivered','cancelled') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `shop_payment_releases`
--

CREATE TABLE `shop_payment_releases` (
  `id` int(11) NOT NULL,
  `delivery_id` int(11) NOT NULL,
  `payment_id` int(11) NOT NULL,
  `release_type` enum('AUTO_QR_STRIPE','AUTO_QR_PAYPAL','MANUAL') NOT NULL,
  `amount_released` decimal(10,2) NOT NULL,
  `platform_amount` decimal(10,2) NOT NULL,
  `stripe_transfer_id` varchar(255) DEFAULT NULL,
  `paypal_payout_id` varchar(255) DEFAULT NULL,
  `traveler_id` int(11) NOT NULL,
  `released_by` varchar(100) DEFAULT 'CRONJOB_QR',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `shop_price_offer_history`
--

CREATE TABLE `shop_price_offer_history` (
  `id` int(11) NOT NULL,
  `message_id` int(11) NOT NULL COMMENT 'ID del mensaje que contiene la oferta',
  `conversation_id` int(11) NOT NULL COMMENT 'ID de la conversación',
  `product_id` int(11) NOT NULL COMMENT 'ID del producto',
  `buyer_id` int(11) NOT NULL COMMENT 'ID del comprador que hace la oferta',
  `seller_id` int(11) NOT NULL COMMENT 'ID del vendedor',
  `offered_price` decimal(10,2) NOT NULL COMMENT 'Precio ofrecido',
  `original_price` decimal(10,2) NOT NULL COMMENT 'Precio original del producto',
  `status` enum('pending','accepted','rejected','countered','expired') DEFAULT 'pending',
  `parent_offer_id` int(11) DEFAULT NULL COMMENT 'Si es una contraoferta, ID de la oferta anterior',
  `offer_number` int(11) DEFAULT 1 COMMENT 'Número de oferta en la cadena de negociación',
  `expires_at` timestamp NULL DEFAULT NULL COMMENT 'Las ofertas expiran después de 48 horas',
  `accepted_at` timestamp NULL DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `shop_products`
--

CREATE TABLE `shop_products` (
  `id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `trip_id` int(11) DEFAULT NULL,
  `custom_origin` varchar(255) DEFAULT NULL COMMENT 'Origen personalizado (Google Places)',
  `custom_destination` varchar(255) DEFAULT NULL COMMENT 'Destino personalizado (Google Places)',
  `custom_travel_date` date DEFAULT NULL COMMENT 'Fecha de viaje personalizada',
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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `shop_product_deliveries`
--

CREATE TABLE `shop_product_deliveries` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL COMMENT 'FK a shop_orders.id',
  `order_item_id` int(11) NOT NULL COMMENT 'FK a shop_order_items.id',
  `payment_id` int(11) NOT NULL COMMENT 'FK a payments_in_custody.id',
  `buyer_id` int(11) NOT NULL COMMENT 'FK a accounts.id (comprador)',
  `seller_id` int(11) NOT NULL COMMENT 'FK a accounts.id (vendedor/viajero)',
  `product_id` int(11) NOT NULL COMMENT 'FK a shop_products.id',
  `qr_code_unique_id` varchar(100) NOT NULL COMMENT 'ID único del código QR',
  `qr_code_path` varchar(500) NOT NULL COMMENT 'Ruta del archivo QR',
  `qr_data_json` text NOT NULL COMMENT 'Datos JSON del QR',
  `delivery_state` enum('pending','in_transit','at_destination','delivered') DEFAULT 'pending',
  `qr_scanned_at` datetime DEFAULT NULL COMMENT 'Fecha de escaneo del QR',
  `qr_scanned_by` int(11) DEFAULT NULL COMMENT 'Usuario que escaneó (buyer_id)',
  `payment_released` tinyint(1) DEFAULT 0 COMMENT '¿Pago liberado al vendedor?',
  `payment_released_at` datetime DEFAULT NULL COMMENT 'Fecha de liberación del pago',
  `email_sent` tinyint(1) DEFAULT 0 COMMENT '¿Emails enviados?',
  `email_sent_at` datetime DEFAULT NULL COMMENT 'Fecha de envío de emails',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Entregas de productos comprados con códigos QR';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `shop_product_delivery_state_history`
--

CREATE TABLE `shop_product_delivery_state_history` (
  `id` int(11) NOT NULL,
  `delivery_id` int(11) NOT NULL COMMENT 'FK a shop_product_deliveries.id',
  `previous_state` varchar(50) NOT NULL COMMENT 'Estado anterior',
  `new_state` varchar(50) NOT NULL COMMENT 'Nuevo estado',
  `changed_by` int(11) NOT NULL COMMENT 'FK a accounts.id (quien cambió el estado)',
  `change_method` varchar(50) DEFAULT 'manual' COMMENT 'Método: manual, qr_scan, automatic',
  `notes` text DEFAULT NULL COMMENT 'Notas adicionales',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historial de estados de entregas de productos';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `shop_product_images`
--

CREATE TABLE `shop_product_images` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `image_path` varchar(500) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `alt_text` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `shop_product_negotiations`
--

CREATE TABLE `shop_product_negotiations` (
  `id` int(11) NOT NULL,
  `proposal_id` int(11) NOT NULL COMMENT 'FK a shop_product_proposals.id',
  `user_id` int(11) NOT NULL COMMENT 'FK a accounts.id - Usuario que hace la contraoferta',
  `user_type` enum('buyer','seller') NOT NULL COMMENT 'Tipo de usuario',
  `proposed_price` decimal(10,2) NOT NULL COMMENT 'Precio propuesto en la contraoferta',
  `proposed_currency` varchar(3) DEFAULT 'EUR' COMMENT 'Moneda',
  `quantity` int(11) DEFAULT 1 COMMENT 'Cantidad',
  `message` text DEFAULT NULL COMMENT 'Mensaje de la contraoferta',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historial de negociaciones/contraofertas de productos';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `shop_product_offers`
--

CREATE TABLE `shop_product_offers` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL COMMENT 'FK a shop_products.id',
  `buyer_id` int(11) NOT NULL COMMENT 'FK a accounts.id (comprador que hace oferta)',
  `seller_id` int(11) NOT NULL COMMENT 'FK a accounts.id (vendedor/viajero)',
  `offered_price` decimal(10,2) NOT NULL COMMENT 'Precio ofrecido por el comprador',
  `offered_currency` enum('EUR','USD','BOB','BRL','ARS','VES','COP','MXN','NIO','CUP','PEN') DEFAULT 'EUR',
  `quantity` int(11) DEFAULT 1 COMMENT 'Cantidad deseada',
  `message` text DEFAULT NULL COMMENT 'Mensaje del comprador',
  `delivery_preference` varchar(255) DEFAULT NULL COMMENT 'Preferencia de entrega',
  `status` enum('pending','accepted','rejected','expired') DEFAULT 'pending',
  `negotiation_count` int(11) DEFAULT 0 COMMENT 'Contador de negociaciones',
  `response_message` text DEFAULT NULL COMMENT 'Respuesta del vendedor',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL COMMENT 'Fecha de expiración de la oferta'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ofertas de compradores para productos';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `shop_product_price_negotiation`
--

CREATE TABLE `shop_product_price_negotiation` (
  `id` int(11) NOT NULL,
  `offer_id` int(11) NOT NULL COMMENT 'FK a shop_product_offers.id',
  `product_id` int(11) NOT NULL COMMENT 'FK a shop_products.id',
  `user_id` int(11) NOT NULL COMMENT 'FK a accounts.id (quien hace la oferta)',
  `user_type` enum('buyer','seller') NOT NULL COMMENT 'Tipo de usuario que hace la oferta',
  `offer_type` enum('initial','counteroffer') NOT NULL DEFAULT 'counteroffer' COMMENT 'Tipo de oferta',
  `offered_price` decimal(10,2) NOT NULL COMMENT 'Precio ofrecido',
  `offered_currency` enum('EUR','USD','BOB','BRL','ARS','VES','COP','MXN','NIO','CUP','PEN') DEFAULT 'EUR',
  `quantity` int(11) DEFAULT 1 COMMENT 'Cantidad',
  `message` text DEFAULT NULL COMMENT 'Mensaje de la contraoferta',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historial de negociación de precios para productos';

--
-- Disparadores `shop_product_price_negotiation`
--
DELIMITER $$
CREATE TRIGGER `trg_update_negotiation_count` AFTER INSERT ON `shop_product_price_negotiation` FOR EACH ROW BEGIN
    UPDATE shop_product_offers
    SET negotiation_count = negotiation_count + 1,
        updated_at = NOW()
    WHERE id = NEW.offer_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_update_product_negotiation_count` AFTER INSERT ON `shop_product_price_negotiation` FOR EACH ROW BEGIN
    UPDATE `shop_product_offers`
    SET `negotiation_count` = (
        SELECT COUNT(*)
        FROM `shop_product_price_negotiation`
        WHERE `offer_id` = NEW.`offer_id`
    )
    WHERE `id` = NEW.`offer_id`;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `shop_product_proposals`
--

CREATE TABLE `shop_product_proposals` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL COMMENT 'FK a shop_products.id',
  `buyer_id` int(11) NOT NULL COMMENT 'FK a accounts.id - Usuario que hace la oferta',
  `proposed_price` decimal(10,2) NOT NULL COMMENT 'Precio ofertado',
  `proposed_currency` varchar(3) DEFAULT 'EUR' COMMENT 'Moneda de la oferta',
  `quantity` int(11) DEFAULT 1 COMMENT 'Cantidad solicitada',
  `message` text DEFAULT NULL COMMENT 'Mensaje de la propuesta',
  `status` enum('pending','accepted','rejected') DEFAULT 'pending' COMMENT 'Estado de la propuesta',
  `counteroffer_count` int(11) DEFAULT 0 COMMENT 'Número de contraofertas',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Propuestas/ofertas de compradores para productos';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `shop_proposal_negotiations`
--

CREATE TABLE `shop_proposal_negotiations` (
  `id` int(11) NOT NULL,
  `proposal_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_type` enum('requester','traveler') NOT NULL,
  `proposed_price` decimal(10,2) NOT NULL,
  `proposed_currency` varchar(3) NOT NULL,
  `estimated_delivery` date DEFAULT NULL,
  `message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `shop_ratings`
--

CREATE TABLE `shop_ratings` (
  `id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `buyer_id` int(11) NOT NULL,
  `rating` decimal(2,1) NOT NULL,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `shop_requests`
--

CREATE TABLE `shop_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `category` varchar(50) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `budget_amount` decimal(10,2) NOT NULL,
  `budget_currency` varchar(3) DEFAULT 'EUR',
  `origin_country` varchar(255) DEFAULT NULL,
  `origin_flexible` tinyint(1) DEFAULT 0,
  `destination_city` varchar(255) NOT NULL,
  `deadline_date` date DEFAULT NULL,
  `urgency` enum('flexible','moderate','urgent') DEFAULT 'flexible',
  `status` enum('open','negotiating','accepted','completed','cancelled') DEFAULT 'open',
  `reference_images` text DEFAULT NULL,
  `reference_links` text DEFAULT NULL,
  `payment_method` varchar(100) DEFAULT 'negotiable',
  `includes_product_cost` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `shop_request_messages`
--

CREATE TABLE `shop_request_messages` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `proposal_id` int(11) DEFAULT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `shop_request_price_negotiation`
--

CREATE TABLE `shop_request_price_negotiation` (
  `id` int(11) NOT NULL,
  `proposal_id` int(11) NOT NULL COMMENT 'FK a shop_request_proposals.id',
  `request_id` int(11) NOT NULL COMMENT 'FK a shop_requests.id',
  `user_id` int(11) NOT NULL COMMENT 'FK a accounts.id (quien hace la oferta)',
  `user_type` enum('requester','traveler') NOT NULL COMMENT 'Tipo de usuario que hace la oferta',
  `offer_type` enum('initial','counteroffer') NOT NULL DEFAULT 'counteroffer' COMMENT 'Tipo de oferta',
  `proposed_price` decimal(10,2) NOT NULL COMMENT 'Precio propuesto',
  `proposed_currency` enum('EUR','USD','BOB','BRL','ARS','VES','COP','MXN','NIO','CUP','PEN') DEFAULT 'EUR',
  `estimated_delivery` date DEFAULT NULL COMMENT 'Fecha estimada de entrega',
  `message` text DEFAULT NULL COMMENT 'Mensaje de la contraoferta',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historial de negociación de precios para requests';

--
-- Disparadores `shop_request_price_negotiation`
--
DELIMITER $$
CREATE TRIGGER `trg_update_request_counteroffer_count` AFTER INSERT ON `shop_request_price_negotiation` FOR EACH ROW BEGIN
    UPDATE `shop_request_proposals`
    SET `counteroffer_count` = (
        SELECT COUNT(*)
        FROM `shop_request_price_negotiation`
        WHERE `proposal_id` = NEW.`proposal_id`
    )
    WHERE `id` = NEW.`proposal_id`;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `shop_request_proposals`
--

CREATE TABLE `shop_request_proposals` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `traveler_id` int(11) NOT NULL,
  `proposed_price` decimal(10,2) NOT NULL,
  `current_price` decimal(10,2) DEFAULT NULL COMMENT 'Precio actual tras negociación (última oferta aceptada)',
  `accepted_price` decimal(10,2) DEFAULT NULL COMMENT 'Precio final acordado (inmutable)',
  `proposed_currency` varchar(3) DEFAULT 'EUR',
  `current_currency` varchar(3) DEFAULT NULL COMMENT 'Moneda actual tras negociación',
  `accepted_currency` varchar(3) DEFAULT NULL COMMENT 'Moneda del precio acordado',
  `estimated_delivery` date DEFAULT NULL,
  `current_estimated_delivery` date DEFAULT NULL COMMENT 'Fecha de entrega actualizada tras negociación',
  `accepted_delivery` date DEFAULT NULL COMMENT 'Fecha de entrega acordada',
  `message` text DEFAULT NULL,
  `status` enum('pending','accepted','rejected','withdrawn') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `accepted_at` timestamp NULL DEFAULT NULL COMMENT 'Cuándo se aceptó la propuesta',
  `accepted_by_user_id` int(11) DEFAULT NULL COMMENT 'ID de quien aceptó (requester)',
  `is_counteroffer` tinyint(1) DEFAULT 0,
  `parent_proposal_id` int(11) DEFAULT NULL,
  `counteroffer_count` int(11) DEFAULT 0,
  `last_offer_by` enum('traveler','requester') DEFAULT NULL COMMENT 'Quién hizo la última oferta',
  `last_offer_at` timestamp NULL DEFAULT NULL COMMENT 'Cuándo se hizo la última oferta',
  `payment_id` int(11) DEFAULT NULL COMMENT 'FK a payments_in_custody.id'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `shop_request_proposal_history`
--

CREATE TABLE `shop_request_proposal_history` (
  `id` int(11) NOT NULL,
  `proposal_id` int(11) NOT NULL COMMENT 'FK a shop_request_proposals',
  `offered_by` enum('requester','traveler') NOT NULL COMMENT 'Quién hizo la oferta',
  `user_id` int(11) NOT NULL COMMENT 'ID del usuario que ofrece',
  `offered_price` decimal(10,2) NOT NULL COMMENT 'Precio ofertado',
  `offered_currency` varchar(3) NOT NULL DEFAULT 'EUR' COMMENT 'Moneda',
  `offered_delivery` date DEFAULT NULL COMMENT 'Fecha de entrega propuesta',
  `message` text DEFAULT NULL COMMENT 'Mensaje explicativo',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'Cuándo se hizo la oferta'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historial completo de todas las ofertas y contraofertas';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `shop_seller_ratings`
--

CREATE TABLE `shop_seller_ratings` (
  `id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `buyer_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `rating` tinyint(1) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `comment` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `shop_trip_info`
--

CREATE TABLE `shop_trip_info` (
  `id` int(11) NOT NULL,
  `trip_id` int(11) NOT NULL COMMENT 'FK a transporting.id',
  `enabled` tinyint(1) DEFAULT 1,
  `categories` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`categories`)),
  `available_space` varchar(50) DEFAULT NULL,
  `budget` decimal(10,2) DEFAULT NULL,
  `currency` enum('EUR','USD','BOB','BRL','ARS','VES','COP','MXN','NIO','CUP','PEN') DEFAULT 'EUR',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `request_favorites`
--
ALTER TABLE `request_favorites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_favorite` (`user_id`,`request_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_request_id` (`request_id`);

--
-- Indices de la tabla `share_meta_config`
--
ALTER TABLE `share_meta_config`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `shop_categories`
--
ALTER TABLE `shop_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_slug` (`slug`),
  ADD KEY `idx_active` (`active`),
  ADD KEY `idx_sort_order` (`sort_order`);

--
-- Indices de la tabla `shop_chat_messages`
--
ALTER TABLE `shop_chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `receiver_id` (`receiver_id`),
  ADD KEY `idx_proposal` (`proposal_id`),
  ADD KEY `idx_users` (`sender_id`,`receiver_id`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_location` (`latitude`,`longitude`),
  ADD KEY `idx_message_type` (`message_type`);

--
-- Indices de la tabla `shop_conversations`
--
ALTER TABLE `shop_conversations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_conversation` (`product_id`,`buyer_id`) COMMENT 'Una conversación por comprador por producto',
  ADD KEY `idx_buyer` (`buyer_id`),
  ADD KEY `idx_seller` (`seller_id`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_last_message` (`last_message_at`);

--
-- Indices de la tabla `shop_coupons`
--
ALTER TABLE `shop_coupons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_code` (`code`),
  ADD KEY `idx_active` (`active`),
  ADD KEY `idx_valid_from` (`valid_from`),
  ADD KEY `idx_valid_until` (`valid_until`);

--
-- Indices de la tabla `shop_coupon_usage`
--
ALTER TABLE `shop_coupon_usage`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_coupon_id` (`coupon_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_order_id` (`order_id`);

--
-- Indices de la tabla `shop_deliveries`
--
ALTER TABLE `shop_deliveries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `qr_code_unique_id` (`qr_code_unique_id`),
  ADD UNIQUE KEY `unique_proposal_delivery` (`proposal_id`) COMMENT 'Una entrega por propuesta',
  ADD KEY `idx_payment` (`payment_id`),
  ADD KEY `idx_requester` (`requester_id`),
  ADD KEY `idx_traveler` (`traveler_id`),
  ADD KEY `idx_qr_unique_id` (`qr_code_unique_id`),
  ADD KEY `idx_delivery_state` (`delivery_state`),
  ADD KEY `idx_payment_released` (`payment_released`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `fk_shop_deliveries_scanned_by` (`qr_scanned_by`);

--
-- Indices de la tabla `shop_delivery_state_history`
--
ALTER TABLE `shop_delivery_state_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_delivery` (`delivery_id`),
  ADD KEY `idx_changed_by` (`changed_by`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indices de la tabla `shop_favorites`
--
ALTER TABLE `shop_favorites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_product` (`user_id`,`product_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_product_id` (`product_id`);

--
-- Indices de la tabla `shop_followers`
--
ALTER TABLE `shop_followers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_follow` (`follower_id`,`seller_id`),
  ADD KEY `follower_id` (`follower_id`),
  ADD KEY `seller_id` (`seller_id`),
  ADD KEY `idx_followers_seller_date` (`seller_id`,`created_at`);

--
-- Indices de la tabla `shop_messages`
--
ALTER TABLE `shop_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sender_id` (`sender_id`),
  ADD KEY `idx_receiver_id` (`receiver_id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_messages_conversation` (`sender_id`,`receiver_id`,`created_at`),
  ADD KEY `idx_conversation` (`conversation_id`),
  ADD KEY `idx_message_type` (`message_type`),
  ADD KEY `idx_price_offer_status` (`price_offer_status`),
  ADD KEY `fk_message_replied_to` (`replied_to_message_id`),
  ADD KEY `idx_message_offer` (`id`,`is_price_offer`,`price_offer_status`);

--
-- Indices de la tabla `shop_notifications`
--
ALTER TABLE `shop_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_is_read` (`is_read`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_user_read_created` (`user_id`,`is_read`,`created_at` DESC);

--
-- Indices de la tabla `shop_orders`
--
ALTER TABLE `shop_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD UNIQUE KEY `unique_order_number` (`order_number`),
  ADD KEY `idx_buyer_id` (`buyer_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_shop_orders_total` (`total_amount`),
  ADD KEY `idx_shop_orders_status_created` (`status`,`created_at`);

--
-- Indices de la tabla `shop_order_items`
--
ALTER TABLE `shop_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_seller_id` (`seller_id`);

--
-- Indices de la tabla `shop_payment_releases`
--
ALTER TABLE `shop_payment_releases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_delivery` (`delivery_id`),
  ADD KEY `idx_payment` (`payment_id`),
  ADD KEY `idx_traveler` (`traveler_id`);

--
-- Indices de la tabla `shop_price_offer_history`
--
ALTER TABLE `shop_price_offer_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `message_id` (`message_id`),
  ADD KEY `parent_offer_id` (`parent_offer_id`),
  ADD KEY `idx_conversation` (`conversation_id`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_buyer` (`buyer_id`),
  ADD KEY `idx_seller` (`seller_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_offer_status_expires` (`status`,`expires_at`);

--
-- Indices de la tabla `shop_products`
--
ALTER TABLE `shop_products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_seller_id` (`seller_id`),
  ADD KEY `idx_trip_id` (`trip_id`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_currency` (`currency`),
  ADD KEY `idx_active` (`active`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_shop_products_price` (`price`),
  ADD KEY `idx_shop_products_featured` (`featured`,`active`),
  ADD KEY `idx_custom_origin` (`custom_origin`),
  ADD KEY `idx_custom_destination` (`custom_destination`),
  ADD KEY `idx_custom_travel_date` (`custom_travel_date`);

--
-- Indices de la tabla `shop_product_deliveries`
--
ALTER TABLE `shop_product_deliveries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `qr_code_unique_id` (`qr_code_unique_id`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_order_item_id` (`order_item_id`),
  ADD KEY `idx_payment_id` (`payment_id`),
  ADD KEY `idx_buyer_id` (`buyer_id`),
  ADD KEY `idx_seller_id` (`seller_id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_delivery_state` (`delivery_state`),
  ADD KEY `idx_qr_unique` (`qr_code_unique_id`),
  ADD KEY `qr_scanned_by` (`qr_scanned_by`),
  ADD KEY `idx_delivery_qr_pending` (`delivery_state`,`qr_scanned_at`);

--
-- Indices de la tabla `shop_product_delivery_state_history`
--
ALTER TABLE `shop_product_delivery_state_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_delivery_id` (`delivery_id`),
  ADD KEY `idx_changed_by` (`changed_by`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indices de la tabla `shop_product_images`
--
ALTER TABLE `shop_product_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_is_primary` (`is_primary`);

--
-- Indices de la tabla `shop_product_negotiations`
--
ALTER TABLE `shop_product_negotiations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_proposal` (`proposal_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indices de la tabla `shop_product_offers`
--
ALTER TABLE `shop_product_offers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_buyer_id` (`buyer_id`),
  ADD KEY `idx_seller_id` (`seller_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_offer_status_negotiation` (`status`,`negotiation_count`),
  ADD KEY `idx_shop_product_offers_buyer_status` (`buyer_id`,`status`),
  ADD KEY `idx_shop_product_offers_seller_status` (`seller_id`,`status`),
  ADD KEY `idx_shop_product_offers_product_status` (`product_id`,`status`);

--
-- Indices de la tabla `shop_product_price_negotiation`
--
ALTER TABLE `shop_product_price_negotiation`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_offer_id` (`offer_id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indices de la tabla `shop_product_proposals`
--
ALTER TABLE `shop_product_proposals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_buyer` (`buyer_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indices de la tabla `shop_proposal_negotiations`
--
ALTER TABLE `shop_proposal_negotiations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_proposal` (`proposal_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indices de la tabla `shop_ratings`
--
ALTER TABLE `shop_ratings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `seller_id` (`seller_id`),
  ADD KEY `buyer_id` (`buyer_id`);

--
-- Indices de la tabla `shop_requests`
--
ALTER TABLE `shop_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_category` (`category`);

--
-- Indices de la tabla `shop_request_messages`
--
ALTER TABLE `shop_request_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_request` (`request_id`);

--
-- Indices de la tabla `shop_request_price_negotiation`
--
ALTER TABLE `shop_request_price_negotiation`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_proposal_id` (`proposal_id`),
  ADD KEY `idx_request_id` (`request_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indices de la tabla `shop_request_proposals`
--
ALTER TABLE `shop_request_proposals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_proposal` (`request_id`,`traveler_id`),
  ADD KEY `idx_request` (`request_id`),
  ADD KEY `idx_traveler` (`traveler_id`),
  ADD KEY `idx_parent_proposal` (`parent_proposal_id`),
  ADD KEY `idx_payment` (`payment_id`),
  ADD KEY `idx_proposal_status_count` (`status`,`counteroffer_count`),
  ADD KEY `idx_accepted_at` (`accepted_at`),
  ADD KEY `idx_status` (`status`);

--
-- Indices de la tabla `shop_request_proposal_history`
--
ALTER TABLE `shop_request_proposal_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_proposal` (`proposal_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_offered_by` (`offered_by`);

--
-- Indices de la tabla `shop_seller_ratings`
--
ALTER TABLE `shop_seller_ratings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_buyer_seller_order` (`buyer_id`,`seller_id`,`order_id`),
  ADD KEY `idx_seller_id` (`seller_id`),
  ADD KEY `idx_buyer_id` (`buyer_id`),
  ADD KEY `idx_order_id` (`order_id`);

--
-- Indices de la tabla `shop_trip_info`
--
ALTER TABLE `shop_trip_info`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_trip_shop` (`trip_id`),
  ADD KEY `idx_enabled` (`enabled`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `request_favorites`
--
ALTER TABLE `request_favorites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `share_meta_config`
--
ALTER TABLE `share_meta_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `shop_categories`
--
ALTER TABLE `shop_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `shop_chat_messages`
--
ALTER TABLE `shop_chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `shop_conversations`
--
ALTER TABLE `shop_conversations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `shop_coupons`
--
ALTER TABLE `shop_coupons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `shop_coupon_usage`
--
ALTER TABLE `shop_coupon_usage`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `shop_deliveries`
--
ALTER TABLE `shop_deliveries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `shop_delivery_state_history`
--
ALTER TABLE `shop_delivery_state_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `shop_favorites`
--
ALTER TABLE `shop_favorites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `shop_followers`
--
ALTER TABLE `shop_followers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `shop_messages`
--
ALTER TABLE `shop_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `shop_notifications`
--
ALTER TABLE `shop_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `shop_orders`
--
ALTER TABLE `shop_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `shop_order_items`
--
ALTER TABLE `shop_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `shop_payment_releases`
--
ALTER TABLE `shop_payment_releases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `shop_price_offer_history`
--
ALTER TABLE `shop_price_offer_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `shop_products`
--
ALTER TABLE `shop_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `shop_product_deliveries`
--
ALTER TABLE `shop_product_deliveries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `shop_product_delivery_state_history`
--
ALTER TABLE `shop_product_delivery_state_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `shop_product_images`
--
ALTER TABLE `shop_product_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `shop_product_negotiations`
--
ALTER TABLE `shop_product_negotiations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `shop_product_offers`
--
ALTER TABLE `shop_product_offers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `shop_product_price_negotiation`
--
ALTER TABLE `shop_product_price_negotiation`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `shop_product_proposals`
--
ALTER TABLE `shop_product_proposals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `shop_proposal_negotiations`
--
ALTER TABLE `shop_proposal_negotiations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `shop_ratings`
--
ALTER TABLE `shop_ratings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `shop_requests`
--
ALTER TABLE `shop_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `shop_request_messages`
--
ALTER TABLE `shop_request_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `shop_request_price_negotiation`
--
ALTER TABLE `shop_request_price_negotiation`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `shop_request_proposals`
--
ALTER TABLE `shop_request_proposals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `shop_request_proposal_history`
--
ALTER TABLE `shop_request_proposal_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `shop_seller_ratings`
--
ALTER TABLE `shop_seller_ratings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `shop_trip_info`
--
ALTER TABLE `shop_trip_info`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------

--
-- Estructura para la vista `shop_notifications_stats`
--
DROP TABLE IF EXISTS `shop_notifications_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`u191663851_varmun1795`@`127.0.0.1` SQL SECURITY DEFINER VIEW `shop_notifications_stats`  AS SELECT `shop_notifications`.`user_id` AS `user_id`, count(0) AS `total_notifications`, sum(case when `shop_notifications`.`is_read` = 0 then 1 else 0 end) AS `unread_count`, sum(case when `shop_notifications`.`type` in ('new_proposal','payment_received','delivery_completed') then 1 else 0 end) AS `important_count`, max(`shop_notifications`.`created_at`) AS `last_notification_at` FROM `shop_notifications` GROUP BY `shop_notifications`.`user_id` ;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `shop_chat_messages`
--
ALTER TABLE `shop_chat_messages`
  ADD CONSTRAINT `shop_chat_messages_ibfk_1` FOREIGN KEY (`proposal_id`) REFERENCES `shop_request_proposals` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shop_chat_messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shop_chat_messages_ibfk_3` FOREIGN KEY (`receiver_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `shop_conversations`
--
ALTER TABLE `shop_conversations`
  ADD CONSTRAINT `fk_conversation_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_conversation_product` FOREIGN KEY (`product_id`) REFERENCES `shop_products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_conversation_seller` FOREIGN KEY (`seller_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `shop_coupon_usage`
--
ALTER TABLE `shop_coupon_usage`
  ADD CONSTRAINT `fk_shop_coupon_usage_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `shop_coupons` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_shop_coupon_usage_order` FOREIGN KEY (`order_id`) REFERENCES `shop_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_shop_coupon_usage_user` FOREIGN KEY (`user_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `shop_deliveries`
--
ALTER TABLE `shop_deliveries`
  ADD CONSTRAINT `fk_shop_deliveries_proposal` FOREIGN KEY (`proposal_id`) REFERENCES `shop_request_proposals` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_shop_deliveries_scanned_by` FOREIGN KEY (`qr_scanned_by`) REFERENCES `accounts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `shop_delivery_state_history`
--
ALTER TABLE `shop_delivery_state_history`
  ADD CONSTRAINT `fk_shop_delivery_history_changed_by` FOREIGN KEY (`changed_by`) REFERENCES `accounts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_shop_delivery_history_delivery` FOREIGN KEY (`delivery_id`) REFERENCES `shop_deliveries` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `shop_favorites`
--
ALTER TABLE `shop_favorites`
  ADD CONSTRAINT `fk_shop_favorites_product` FOREIGN KEY (`product_id`) REFERENCES `shop_products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_shop_favorites_user` FOREIGN KEY (`user_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `shop_followers`
--
ALTER TABLE `shop_followers`
  ADD CONSTRAINT `fk_followers_follower` FOREIGN KEY (`follower_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_followers_seller` FOREIGN KEY (`seller_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `shop_messages`
--
ALTER TABLE `shop_messages`
  ADD CONSTRAINT `fk_message_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `shop_conversations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_message_product` FOREIGN KEY (`product_id`) REFERENCES `shop_products` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_message_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_message_replied_to` FOREIGN KEY (`replied_to_message_id`) REFERENCES `shop_messages` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_message_sender` FOREIGN KEY (`sender_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_shop_messages_order` FOREIGN KEY (`order_id`) REFERENCES `shop_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_shop_messages_product` FOREIGN KEY (`product_id`) REFERENCES `shop_products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_shop_messages_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_shop_messages_sender` FOREIGN KEY (`sender_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `shop_notifications`
--
ALTER TABLE `shop_notifications`
  ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_shop_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `shop_orders`
--
ALTER TABLE `shop_orders`
  ADD CONSTRAINT `fk_shop_orders_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `shop_order_items`
--
ALTER TABLE `shop_order_items`
  ADD CONSTRAINT `fk_shop_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `shop_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_shop_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `shop_products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_shop_order_items_seller` FOREIGN KEY (`seller_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `shop_price_offer_history`
--
ALTER TABLE `shop_price_offer_history`
  ADD CONSTRAINT `shop_price_offer_history_ibfk_1` FOREIGN KEY (`message_id`) REFERENCES `shop_messages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shop_price_offer_history_ibfk_2` FOREIGN KEY (`conversation_id`) REFERENCES `shop_conversations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shop_price_offer_history_ibfk_3` FOREIGN KEY (`product_id`) REFERENCES `shop_products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shop_price_offer_history_ibfk_4` FOREIGN KEY (`buyer_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shop_price_offer_history_ibfk_5` FOREIGN KEY (`seller_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shop_price_offer_history_ibfk_6` FOREIGN KEY (`parent_offer_id`) REFERENCES `shop_price_offer_history` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `shop_products`
--
ALTER TABLE `shop_products`
  ADD CONSTRAINT `fk_shop_products_seller` FOREIGN KEY (`seller_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_shop_products_trip` FOREIGN KEY (`trip_id`) REFERENCES `transporting` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `shop_product_deliveries`
--
ALTER TABLE `shop_product_deliveries`
  ADD CONSTRAINT `shop_product_deliveries_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `shop_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shop_product_deliveries_ibfk_2` FOREIGN KEY (`order_item_id`) REFERENCES `shop_order_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shop_product_deliveries_ibfk_3` FOREIGN KEY (`payment_id`) REFERENCES `payments_in_custody` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shop_product_deliveries_ibfk_4` FOREIGN KEY (`buyer_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shop_product_deliveries_ibfk_5` FOREIGN KEY (`seller_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shop_product_deliveries_ibfk_6` FOREIGN KEY (`product_id`) REFERENCES `shop_products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shop_product_deliveries_ibfk_7` FOREIGN KEY (`qr_scanned_by`) REFERENCES `accounts` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `shop_product_delivery_state_history`
--
ALTER TABLE `shop_product_delivery_state_history`
  ADD CONSTRAINT `shop_product_delivery_state_history_ibfk_1` FOREIGN KEY (`delivery_id`) REFERENCES `shop_product_deliveries` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shop_product_delivery_state_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `accounts` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `shop_product_images`
--
ALTER TABLE `shop_product_images`
  ADD CONSTRAINT `fk_shop_images_product` FOREIGN KEY (`product_id`) REFERENCES `shop_products` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `shop_product_negotiations`
--
ALTER TABLE `shop_product_negotiations`
  ADD CONSTRAINT `fk_product_negotiations_proposal` FOREIGN KEY (`proposal_id`) REFERENCES `shop_product_proposals` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_product_negotiations_user` FOREIGN KEY (`user_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `shop_product_offers`
--
ALTER TABLE `shop_product_offers`
  ADD CONSTRAINT `shop_product_offers_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `shop_products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shop_product_offers_ibfk_2` FOREIGN KEY (`buyer_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shop_product_offers_ibfk_3` FOREIGN KEY (`seller_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `shop_product_price_negotiation`
--
ALTER TABLE `shop_product_price_negotiation`
  ADD CONSTRAINT `shop_product_price_negotiation_ibfk_1` FOREIGN KEY (`offer_id`) REFERENCES `shop_product_offers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shop_product_price_negotiation_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `shop_products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shop_product_price_negotiation_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `shop_product_proposals`
--
ALTER TABLE `shop_product_proposals`
  ADD CONSTRAINT `fk_product_proposals_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_product_proposals_product` FOREIGN KEY (`product_id`) REFERENCES `shop_products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `shop_ratings`
--
ALTER TABLE `shop_ratings`
  ADD CONSTRAINT `shop_ratings_ibfk_1` FOREIGN KEY (`seller_id`) REFERENCES `accounts` (`id`),
  ADD CONSTRAINT `shop_ratings_ibfk_2` FOREIGN KEY (`buyer_id`) REFERENCES `accounts` (`id`);

--
-- Filtros para la tabla `shop_request_price_negotiation`
--
ALTER TABLE `shop_request_price_negotiation`
  ADD CONSTRAINT `shop_request_price_negotiation_ibfk_1` FOREIGN KEY (`proposal_id`) REFERENCES `shop_request_proposals` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shop_request_price_negotiation_ibfk_2` FOREIGN KEY (`request_id`) REFERENCES `shop_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shop_request_price_negotiation_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `shop_request_proposal_history`
--
ALTER TABLE `shop_request_proposal_history`
  ADD CONSTRAINT `shop_request_proposal_history_ibfk_1` FOREIGN KEY (`proposal_id`) REFERENCES `shop_request_proposals` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `shop_seller_ratings`
--
ALTER TABLE `shop_seller_ratings`
  ADD CONSTRAINT `fk_shop_seller_ratings_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_shop_seller_ratings_order` FOREIGN KEY (`order_id`) REFERENCES `shop_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_shop_seller_ratings_seller` FOREIGN KEY (`seller_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `shop_trip_info`
--
ALTER TABLE `shop_trip_info`
  ADD CONSTRAINT `fk_shop_trip_info_trip` FOREIGN KEY (`trip_id`) REFERENCES `transporting` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
