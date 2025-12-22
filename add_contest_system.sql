-- Update games table with contest-related columns
ALTER TABLE `games` 
ADD COLUMN `is_contest_active` TINYINT(1) DEFAULT 0,
ADD COLUMN `is_claim_active` TINYINT(1) DEFAULT 0,
ADD COLUMN `contest_first_prize` INT(11) DEFAULT 0,
ADD COLUMN `contest_second_prize` INT(11) DEFAULT 0,
ADD COLUMN `contest_third_prize` INT(11) DEFAULT 0;

-- Create contest_scores table to track scores during a contest
CREATE TABLE IF NOT EXISTS `contest_scores` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `game_name` VARCHAR(50) NOT NULL,
    `score` BIGINT(20) NOT NULL DEFAULT 0,
    `game_mode` ENUM('money', 'credits') NOT NULL DEFAULT 'money',
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_user_game` (`user_id`, `game_name`),
    CONSTRAINT `fk_contest_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for contest winners to track claims
CREATE TABLE IF NOT EXISTS `contest_winners` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `game_name` VARCHAR(50) NOT NULL,
    `rank` INT(11) NOT NULL,
    `prize_credits` INT(11) NOT NULL,
    `is_claimed` TINYINT(1) DEFAULT 0,
    `claimed_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_winner_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

