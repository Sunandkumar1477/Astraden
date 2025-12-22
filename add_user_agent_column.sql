-- Add last_session_user_agent column to users table for tracking browser sessions
-- This allows preventing same user from logging in on different browsers on the same device

ALTER TABLE users
ADD COLUMN IF NOT EXISTS last_session_user_agent VARCHAR(255) NULL COMMENT 'User agent of last active session for browser detection';
