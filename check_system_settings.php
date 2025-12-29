<?php
require_once 'connection.php';

header('Content-Type: application/json');

// Create system_settings table if it doesn't exist
$create_table = $conn->query("
    CREATE TABLE IF NOT EXISTS `system_settings` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `setting_key` VARCHAR(100) NOT NULL UNIQUE,
      `setting_value` TEXT,
      `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `unique_setting_key` (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Get show_credit_purchase setting
$settings_stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'show_credit_purchase'");
$settings_stmt->execute();
$settings_result = $settings_stmt->get_result();
$show_credit_purchase = 1; // Default to showing
if ($settings_result->num_rows > 0) {
    $setting = $settings_result->fetch_assoc();
    $show_credit_purchase = (int)$setting['setting_value'];
}
$settings_stmt->close();

$conn->close();

echo json_encode([
    'success' => true,
    'show_credit_purchase' => $show_credit_purchase
]);

