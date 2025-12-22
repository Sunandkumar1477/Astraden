-- Create password reset requests table
CREATE TABLE IF NOT EXISTS `password_reset_requests` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NULL COMMENT 'User ID if user is logged in',
  `username` VARCHAR(50) NULL COMMENT 'Username provided in request',
  `mobile_number` VARCHAR(15) NULL COMMENT 'Mobile number provided in request',
  `status` ENUM('pending', 'completed', 'rejected') DEFAULT 'pending',
  `admin_id` INT(11) NULL COMMENT 'Admin who processed the request',
  `new_password` VARCHAR(255) NULL COMMENT 'Temporary password set by admin',
  `processed_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_created_at` (`created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`admin_id`) REFERENCES `admin_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
