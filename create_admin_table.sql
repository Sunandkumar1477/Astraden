-- ============================================
-- Admin Table Creation Script
-- Copy and paste this code into phpMyAdmin SQL tab
-- ============================================

-- Create admin table
CREATE TABLE IF NOT EXISTS admin_users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    security_code VARCHAR(20) NOT NULL DEFAULT '7777777' COMMENT 'Fixed security code for login',
    ip_whitelist TEXT NULL COMMENT 'Comma-separated IP addresses',
    last_login TIMESTAMP NULL,
    last_login_ip VARCHAR(45) NULL,
    failed_login_attempts INT(11) DEFAULT 0,
    account_locked_until TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create admin activity logs table
CREATE TABLE IF NOT EXISTS admin_logs (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    admin_id INT(11) NOT NULL,
    admin_username VARCHAR(50) NOT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    target_user_id INT(11) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_id (admin_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin (password: Admin@123 - CHANGE THIS!)
-- Default credentials: admin / Admin@123
-- Security Code: 7777777
INSERT INTO admin_users (username, password, email, full_name, security_code, is_active) 
VALUES ('admin', '$2y$10$IlHbIy.bPoLCzrrO/PO67.Xrw/a6O60rEp2/wYVmPOesGtJD.UYWa', 'admin@gameshub.com', 'System Administrator', '7777777', 1)
ON DUPLICATE KEY UPDATE username=username;

-- Add security_code column to existing table (if table already exists)
ALTER TABLE admin_users 
ADD COLUMN IF NOT EXISTS security_code VARCHAR(20) NOT NULL DEFAULT '7777777' COMMENT 'Fixed security code for login' 
AFTER full_name;

-- Update existing admin users with security code if not set
UPDATE admin_users SET security_code = '7777777' WHERE security_code IS NULL OR security_code = '';

-- ============================================
-- Instructions:
-- 1. Open phpMyAdmin: http://localhost/phpmyadmin
-- 2. Select your database (e.g., 'hn')
-- 3. Click on the "SQL" tab
-- 4. Copy and paste all the SQL code above
-- 5. Click "Go" to execute
-- 
-- Default Admin Credentials:
-- Username: admin
-- Password: Admin@123
-- 
-- ⚠️ IMPORTANT: Change the default password immediately!
-- ============================================
