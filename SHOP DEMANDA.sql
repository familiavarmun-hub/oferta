-- Versión sin foreign keys - no da errores
CREATE TABLE IF NOT EXISTS shop_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    category VARCHAR(50) NOT NULL,
    quantity INT DEFAULT 1,
    budget_amount DECIMAL(10,2) NOT NULL,
    budget_currency VARCHAR(3) DEFAULT 'EUR',
    origin_country VARCHAR(255) NULL,
    origin_flexible BOOLEAN DEFAULT FALSE,
    destination_city VARCHAR(255) NOT NULL,
    deadline_date DATE NULL,
    urgency ENUM('flexible', 'moderate', 'urgent') DEFAULT 'flexible',
    status ENUM('open', 'negotiating', 'accepted', 'completed', 'cancelled') DEFAULT 'open',
    reference_images TEXT NULL,
    reference_links TEXT NULL,
    payment_method VARCHAR(100) DEFAULT 'negotiable',
    includes_product_cost BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shop_request_proposals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    traveler_id INT NOT NULL,
    proposed_price DECIMAL(10,2) NOT NULL,
    proposed_currency VARCHAR(3) DEFAULT 'EUR',
    estimated_delivery DATE NULL,
    message TEXT NULL,
    status ENUM('pending', 'accepted', 'rejected', 'withdrawn') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_request (request_id),
    INDEX idx_traveler (traveler_id),
    UNIQUE KEY unique_proposal (request_id, traveler_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shop_request_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    proposal_id INT NULL,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;