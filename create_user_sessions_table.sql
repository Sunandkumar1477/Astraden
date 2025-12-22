-- ============================================
-- User Sessions Table Creation Script
-- Copy and paste this code into phpMyAdmin SQL tab
-- ============================================

-- Add session_token column to users table
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS session_token VARCHAR(64) NULL COMMENT 'Unique session token for single device login',
ADD COLUMN IF NOT EXISTS last_session_ip VARCHAR(45) NULL COMMENT 'IP address of last login',
ADD COLUMN IF NOT EXISTS last_session_user_agent VARCHAR(255) NULL COMMENT 'User agent of last active session for browser detection',
ADD COLUMN IF NOT EXISTS last_session_time TIMESTAMP NULL COMMENT 'Last session timestamp';

-- Add index for faster lookups
ALTER TABLE users 
ADD INDEX IF NOT EXISTS idx_session_token (session_token);

-- ============================================
-- Instructions:
-- 1. Open phpMyAdmin: http://localhost/phpmyadmin
-- 2. Select your database (e.g., 'hn')
-- 3. Click on the "SQL" tab
-- 4. Copy and paste all the SQL code above
-- 5. Click "Go" to execute
-- ============================================
