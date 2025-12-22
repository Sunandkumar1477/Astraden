-- ============================================
-- User Profile Table Creation Script
-- Copy and paste this code into phpMyAdmin SQL tab
-- ============================================

-- Create user_profile table
CREATE TABLE IF NOT EXISTS user_profile (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL UNIQUE,
    full_name VARCHAR(100) NULL,
    profile_photo VARCHAR(20) NULL COMMENT 'Profile icon identifier: boy1, boy2, boy3, boy4, girl1, girl2, girl3, girl4',
    phone_pay_number VARCHAR(20) NULL,
    google_pay_number VARCHAR(20) NULL,
    state VARCHAR(50) NULL,
    credits INT(11) DEFAULT 0,
    credits_color VARCHAR(20) DEFAULT '#00ffff' COMMENT 'Hex color for credits display',
    bio TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add profile column to existing table (if table already exists)
ALTER TABLE user_profile 
ADD COLUMN IF NOT EXISTS credits_color VARCHAR(20) DEFAULT '#00ffff' COMMENT 'Hex color for credits display'
AFTER credits;

-- ============================================
-- Instructions:
-- 1. Open phpMyAdmin: http://localhost/phpmyadmin
-- 2. Select your database (e.g., 'hn')
-- 3. Click on the "SQL" tab
-- 4. Copy and paste all the SQL code above
-- 5. Click "Go" to execute
-- ============================================
