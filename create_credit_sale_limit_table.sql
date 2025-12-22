-- Create table for credit sale limits
CREATE TABLE IF NOT EXISTS `credit_sale_limit` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `total_limit` INT(11) NOT NULL DEFAULT 10000 COMMENT 'Total credits that can be sold',
  `is_enabled` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Enable/disable limit checking',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default limit (10000 credits)
INSERT INTO `credit_sale_limit` (`total_limit`, `is_enabled`) 
VALUES (10000, 1)
ON DUPLICATE KEY UPDATE `total_limit` = VALUES(`total_limit`);
