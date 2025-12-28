-- ============================================
-- Add total_score column to users table
-- This stores the total score (Fluxon) for each user
-- ============================================

-- Add total_score column to users table
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS total_score BIGINT(20) NOT NULL DEFAULT 0 
COMMENT 'Total score (Fluxon) - sum of all scores from game_leaderboard';

-- Create index for faster queries
CREATE INDEX IF NOT EXISTS idx_total_score ON users(total_score DESC);

-- Initialize total_score for existing users based on game_leaderboard
UPDATE users u
SET total_score = COALESCE((
    SELECT SUM(score) 
    FROM game_leaderboard gl 
    WHERE gl.user_id = u.id
), 0);

-- ============================================
-- Instructions:
-- 1. Open phpMyAdmin: http://localhost/phpmyadmin
-- 2. Select your database
-- 3. Click on the "SQL" tab
-- 4. Copy and paste this SQL code
-- 5. Click "Go" to execute
-- ============================================

