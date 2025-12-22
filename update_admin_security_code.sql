-- ============================================
-- Update Admin Table with Security Code
-- Run this if admin_users table already exists
-- ============================================

-- Add security_code column to existing admin_users table
ALTER TABLE admin_users 
ADD COLUMN IF NOT EXISTS security_code VARCHAR(20) NOT NULL DEFAULT '7777777' COMMENT 'Fixed security code for login' 
AFTER full_name;

-- Update existing admin users with security code if not set
UPDATE admin_users 
SET security_code = '7777777' 
WHERE security_code IS NULL OR security_code = '';

-- ============================================
-- Instructions:
-- 1. Open phpMyAdmin: http://localhost/phpmyadmin
-- 2. Select your database (e.g., 'hn')
-- 3. Click on the "SQL" tab
-- 4. Copy and paste the SQL code above
-- 5. Click "Go" to execute
-- 
-- This will add the security_code column and set default value to 7777777
-- ============================================
