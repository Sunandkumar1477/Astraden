<?php
require_once 'connection.php';

// Check if is_contest_active exists
$check = $conn->query("SHOW COLUMNS FROM games LIKE 'is_contest_active'");
if ($check->num_rows == 0) {
    $sql = "ALTER TABLE `games` 
            ADD COLUMN `is_contest_active` TINYINT(1) DEFAULT 0,
            ADD COLUMN `is_claim_active` TINYINT(1) DEFAULT 0,
            ADD COLUMN `contest_first_prize` INT(11) DEFAULT 0,
            ADD COLUMN `contest_second_prize` INT(11) DEFAULT 0,
            ADD COLUMN `contest_third_prize` INT(11) DEFAULT 0";
    if ($conn->query($sql)) {
        echo "Games table updated successfully.<br>";
    } else {
        echo "Error updating games table: " . $conn->error . "<br>";
    }
} else {
    echo "Games table columns already exist.<br>";
}

// Create contest_scores table
$sql = "CREATE TABLE IF NOT EXISTS `contest_scores` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `game_name` VARCHAR(50) NOT NULL,
    `score` BIGINT(20) NOT NULL DEFAULT 0,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_user_game` (`user_id`, `game_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql)) {
    echo "contest_scores table created successfully.<br>";
} else {
    echo "Error creating contest_scores table: " . $conn->error . "<br>";
}

// Create contest_winners table
$sql = "CREATE TABLE IF NOT EXISTS `contest_winners` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `game_name` VARCHAR(50) NOT NULL,
    `rank` INT(11) NOT NULL,
    `prize_credits` INT(11) NOT NULL,
    `is_claimed` TINYINT(1) DEFAULT 0,
    `claimed_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql)) {
    echo "contest_winners table created successfully.<br>";
} else {
    echo "Error creating contest_winners table: " . $conn->error . "<br>";
}

echo "Database setup completed.";
?>

