-- Security System Database Tables
-- Run this SQL to create all security-related tables

-- Rate limiting table
CREATE TABLE IF NOT EXISTS `rate_limits` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `ip_address` VARCHAR(45) NOT NULL,
    `endpoint` VARCHAR(255) NOT NULL,
    `request_count` INT(11) NOT NULL DEFAULT 1,
    `window_start` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_request` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_ip_endpoint` (`ip_address`, `endpoint`, `window_start`),
    KEY `idx_ip` (`ip_address`),
    KEY `idx_window` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Security logs table
CREATE TABLE IF NOT EXISTS `security_logs` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) DEFAULT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` TEXT,
    `action` VARCHAR(100) NOT NULL,
    `endpoint` VARCHAR(255) NOT NULL,
    `status` ENUM('allowed', 'blocked', 'suspicious', 'failed') NOT NULL,
    `reason` TEXT,
    `request_data` TEXT,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ip` (`ip_address`),
    KEY `idx_user` (`user_id`),
    KEY `idx_status` (`status`),
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Blocked IPs table
CREATE TABLE IF NOT EXISTS `blocked_ips` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `ip_address` VARCHAR(45) NOT NULL,
    `reason` TEXT NOT NULL,
    `blocked_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NULL DEFAULT NULL,
    `is_permanent` TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_ip` (`ip_address`),
    KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Request nonces table (for replay attack protection)
CREATE TABLE IF NOT EXISTS `request_nonces` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `nonce` VARCHAR(64) NOT NULL,
    `user_id` INT(11) DEFAULT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `endpoint` VARCHAR(255) NOT NULL,
    `expires_at` TIMESTAMP NOT NULL,
    `used` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_nonce` (`nonce`),
    KEY `idx_expires` (`expires_at`),
    KEY `idx_used` (`used`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add max_score_limit column to games table (if not exists)
ALTER TABLE `games` 
ADD COLUMN IF NOT EXISTS `max_score_limit` INT(11) DEFAULT 1000000 COMMENT 'Maximum allowed score for this game';

