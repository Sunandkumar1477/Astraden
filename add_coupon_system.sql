-- Add coupon code system fields to rewards table
ALTER TABLE `rewards` 
ADD COLUMN IF NOT EXISTS `showcase_date` DATETIME NULL COMMENT 'Date when coupon appears on rewards page',
ADD COLUMN IF NOT EXISTS `display_days` INT(11) DEFAULT 0 COMMENT 'Number of days to display before expiring (e.g., if expire in 5 days, show only 3 days)',
ADD COLUMN IF NOT EXISTS `about_coupon` TEXT NULL COMMENT 'About coupon code description',
ADD INDEX idx_showcase_date (`showcase_date`);

-- Create user_coupon_purchases table to track who purchased which coupon
CREATE TABLE IF NOT EXISTS `user_coupon_purchases` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `reward_id` INT(11) NOT NULL,
  `coupon_code` VARCHAR(100) NOT NULL,
  `purchased_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX idx_user_id (`user_id`),
  INDEX idx_reward_id (`reward_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`reward_id`) REFERENCES `rewards`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

