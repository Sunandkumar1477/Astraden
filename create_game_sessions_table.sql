-- ============================================
-- Game Sessions and Leaderboard Table Creation
-- Copy and paste this code into phpMyAdmin SQL tab
-- ============================================

-- Table for game session timing (set by admin)
CREATE TABLE IF NOT EXISTS game_sessions (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    game_name VARCHAR(50) NOT NULL DEFAULT 'earth-defender',
    session_date DATE NOT NULL,
    session_time TIME NOT NULL,
    duration_minutes INT(11) DEFAULT 60 COMMENT 'How long the game session lasts',
    credits_required INT(11) DEFAULT 30 COMMENT 'Credits needed to play',
    is_active TINYINT(1) DEFAULT 1 COMMENT 'Whether this session is active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_game_name (game_name),
    INDEX idx_session_datetime (session_date, session_time),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for game leaderboard (scores)
CREATE TABLE IF NOT EXISTS game_leaderboard (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    game_name VARCHAR(50) NOT NULL DEFAULT 'earth-defender',
    score INT(11) NOT NULL DEFAULT 0,
    credits_used INT(11) NOT NULL DEFAULT 0 COMMENT 'Credits used for this game',
    played_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    session_id INT(11) NULL COMMENT 'Reference to game_sessions',
    INDEX idx_user_id (user_id),
    INDEX idx_game_name (game_name),
    INDEX idx_score (score DESC),
    INDEX idx_played_at (played_at DESC),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES game_sessions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Instructions:
-- 1. Open phpMyAdmin: http://localhost/phpmyadmin
-- 2. Select your database (e.g., 'games')
-- 3. Click on the "SQL" tab
-- 4. Copy and paste all the SQL code above
-- 5. Click "Go" to execute
-- ============================================
