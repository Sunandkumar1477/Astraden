-- ============================================
-- SQL Table Creation Script for Space Games Hub
-- Copy and paste this code into phpMyAdmin SQL tab
-- ============================================

-- Step 1: Create users table
CREATE TABLE IF NOT EXISTS users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    mobile_number VARCHAR(15) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_mobile (mobile_number),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 2: Create login_logs table
CREATE TABLE IF NOT EXISTS login_logs (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL DEFAULT 0,
    username VARCHAR(50) NOT NULL,
    mobile_number VARCHAR(15) NOT NULL,
    action VARCHAR(20) NOT NULL COMMENT 'login, logout, failed_login, register',
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    logout_time TIMESTAMP NULL,
    session_duration INT(11) NULL COMMENT 'Duration in seconds',
    status VARCHAR(20) DEFAULT 'success' COMMENT 'success, failed',
    failure_reason VARCHAR(255) NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_username (username),
    INDEX idx_action (action),
    INDEX idx_login_time (login_time),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 3: Add foreign key constraint (Optional - Run after both tables are created)
ALTER TABLE login_logs 
ADD CONSTRAINT fk_login_logs_user_id 
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

-- ============================================
-- Instructions:
-- 1. Open phpMyAdmin: http://localhost/phpmyadmin
-- 2. Select your database (e.g., 'hn')
-- 3. Click on the "SQL" tab
-- 4. Copy and paste all the SQL code above
-- 5. Click "Go" to execute
-- ============================================
