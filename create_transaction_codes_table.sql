-- ============================================
-- Transaction Codes Table Creation Script
-- Copy and paste this code into phpMyAdmin SQL tab
-- ============================================

-- Create transaction_codes table
CREATE TABLE IF NOT EXISTS transaction_codes (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    transaction_code VARCHAR(4) NOT NULL,
    status VARCHAR(20) DEFAULT 'pending' COMMENT 'pending, verified, rejected',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_transaction_code (transaction_code),
    INDEX idx_status (status),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Instructions:
-- 1. Open phpMyAdmin: http://localhost/phpmyadmin
-- 2. Select your database (e.g., 'hn')
-- 3. Click on the "SQL" tab
-- 4. Copy and paste all the SQL code above
-- 5. Click "Go" to execute
-- ============================================
