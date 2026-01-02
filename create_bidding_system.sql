-- Create bidding system tables

-- System settings for bidding
CREATE TABLE IF NOT EXISTS `bidding_settings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `is_active` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Enable/disable bidding system',
  `astrons_per_credit` DECIMAL(10, 2) NOT NULL DEFAULT 1.00 COMMENT 'How many Astrons = 1 Credit',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default settings
INSERT INTO `bidding_settings` (`is_active`, `astrons_per_credit`) 
VALUES (0, 1.00)
ON DUPLICATE KEY UPDATE `is_active` = VALUES(`is_active`);

-- User Astrons balance
CREATE TABLE IF NOT EXISTS `user_astrons` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `astrons_balance` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_id` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `user_profile`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Astrons purchase history
CREATE TABLE IF NOT EXISTS `astrons_purchases` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `credits_used` INT(11) NOT NULL,
  `astrons_received` DECIMAL(10, 2) NOT NULL,
  `purchase_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `user_profile`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bidding items/posts
CREATE TABLE IF NOT EXISTS `bidding_items` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `prize_amount` DECIMAL(10, 2) NOT NULL COMMENT 'Prize in Indian Rupees (₹)',
  `starting_price` DECIMAL(10, 2) NOT NULL COMMENT 'Starting bid in Astrons',
  `current_bid` DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'Current highest bid in Astrons',
  `current_bidder_id` INT(11) DEFAULT NULL COMMENT 'User ID of current highest bidder',
  `bid_increment` DECIMAL(10, 2) NOT NULL DEFAULT 1.00 COMMENT 'Minimum bid increment in Astrons',
  `end_time` DATETIME NOT NULL COMMENT 'Bidding end date and time',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `is_completed` TINYINT(1) NOT NULL DEFAULT 0,
  `winner_id` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_end_time` (`end_time`),
  KEY `idx_winner_id` (`winner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bidding history (all bids placed)
CREATE TABLE IF NOT EXISTS `bidding_history` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `bidding_item_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `bid_amount` DECIMAL(10, 2) NOT NULL,
  `bid_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bidding_item_id` (`bidding_item_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_bid_time` (`bid_time`),
  FOREIGN KEY (`bidding_item_id`) REFERENCES `bidding_items`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `user_profile`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User wins (completed biddings)
CREATE TABLE IF NOT EXISTS `user_wins` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `bidding_item_id` INT(11) NOT NULL,
  `win_amount` DECIMAL(10, 2) NOT NULL COMMENT 'Prize amount in ₹',
  `bid_amount` DECIMAL(10, 2) NOT NULL COMMENT 'Final bid amount in Astrons',
  `win_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_claimed` TINYINT(1) NOT NULL DEFAULT 0,
  `claimed_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_bidding_item_id` (`bidding_item_id`),
  KEY `idx_is_claimed` (`is_claimed`),
  FOREIGN KEY (`user_id`) REFERENCES `user_profile`(`user_id`) ON DELETE CASCADE,
  FOREIGN KEY (`bidding_item_id`) REFERENCES `bidding_items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

