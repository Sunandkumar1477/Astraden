-- Create games table to store default credits per game
CREATE TABLE IF NOT EXISTS `games` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `game_name` VARCHAR(50) NOT NULL,
  `display_name` VARCHAR(100) NOT NULL,
  `credits_per_chance` INT(11) NOT NULL DEFAULT 30 COMMENT 'Credits required per play/chance',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_game_name` (`game_name`),
  INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default game
INSERT INTO `games` (`game_name`, `display_name`, `credits_per_chance`, `is_active`) 
VALUES ('earth-defender', 'Earth Defender', 30, 1)
ON DUPLICATE KEY UPDATE `credits_per_chance` = VALUES(`credits_per_chance`);
