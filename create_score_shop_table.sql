-- ============================================
-- Score Shop System Table Creation
-- Copy and paste this code into phpMyAdmin SQL tab
-- ============================================

-- Table for score-to-credits conversion rates (set by admin)
CREATE TABLE IF NOT EXISTS score_shop_settings (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    game_name VARCHAR(50) NOT NULL DEFAULT 'all' COMMENT 'Game name or "all" for all games',
    score_per_credit INT(11) NOT NULL DEFAULT 100 COMMENT 'Score required per credit (e.g., 100 score = 1 credit)',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_game (game_name),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default settings
INSERT INTO score_shop_settings (game_name, score_per_credit, is_active) 
VALUES ('all', 100, 1)
ON DUPLICATE KEY UPDATE score_per_credit = VALUES(score_per_credit);

-- Table to track user's available score (score that can be used to buy credits)
-- We'll use a column in user_profile or create a separate table
-- For simplicity, we'll add columns to track available score per game

-- Add available_score column to user_profile (if not exists)
ALTER TABLE user_profile 
ADD COLUMN IF NOT EXISTS available_score INT(11) DEFAULT 0 COMMENT 'Total available score across all games that can be used to buy credits';

-- Create table to track score purchases history
CREATE TABLE IF NOT EXISTS score_purchases (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    game_name VARCHAR(50) NOT NULL DEFAULT 'all',
    score_used INT(11) NOT NULL,
    credits_received INT(11) NOT NULL,
    conversion_rate INT(11) NOT NULL COMMENT 'Score per credit at time of purchase',
    purchased_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_game_name (game_name),
    INDEX idx_purchased_at (purchased_at DESC),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Instructions:
-- 1. Open phpMyAdmin: http://localhost/phpmyadmin
-- 2. Select your database
-- 3. Click on the "SQL" tab
-- 4. Copy and paste all the SQL code above
-- 5. Click "Go" to execute
-- ============================================

