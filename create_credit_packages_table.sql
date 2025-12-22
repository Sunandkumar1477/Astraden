-- Create credit_packages table
CREATE TABLE IF NOT EXISTS credit_packages (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    credit_amount INT(11) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    is_popular TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    display_order INT(11) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_display_order (display_order),
    UNIQUE KEY unique_credit_amount (credit_amount)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default credit packages
INSERT INTO credit_packages (credit_amount, price, is_popular, display_order) VALUES
(100, 100.00, 0, 1),
(150, 150.00, 1, 2)
ON DUPLICATE KEY UPDATE credit_amount=credit_amount;
