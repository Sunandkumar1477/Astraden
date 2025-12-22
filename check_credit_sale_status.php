<?php
require_once 'connection.php';

header('Content-Type: application/json');

// Create table if it doesn't exist
$create_table = $conn->query("
    CREATE TABLE IF NOT EXISTS `credit_sale_limit` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `total_limit` INT(11) NOT NULL DEFAULT 10000 COMMENT 'Total credits that can be sold',
      `is_enabled` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Enable/disable limit checking',
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Get limit settings
$limit_stmt = $conn->query("SELECT * FROM credit_sale_limit WHERE id = 1");
if ($limit_stmt->num_rows == 0) {
    // Insert default
    $conn->query("INSERT INTO credit_sale_limit (total_limit, is_enabled, sale_mode) VALUES (10000, 1, 'limit')");
    $limit_stmt = $conn->query("SELECT * FROM credit_sale_limit WHERE id = 1");
}
$limit_data = $limit_stmt->fetch_assoc();
$limit_stmt->close();

// Check if sale_mode column exists, if not add it
$check_column = $conn->query("SHOW COLUMNS FROM credit_sale_limit LIKE 'sale_mode'");
if ($check_column->num_rows == 0) {
    $conn->query("ALTER TABLE credit_sale_limit ADD COLUMN sale_mode ENUM('timing', 'limit') NOT NULL DEFAULT 'limit' COMMENT 'Sale mode: timing-based or limit-based'");
    $limit_data['sale_mode'] = 'limit';
}
$check_column->close();

// Check if last_reset_at column exists, if not add it
$check_reset = $conn->query("SHOW COLUMNS FROM credit_sale_limit LIKE 'last_reset_at'");
if ($check_reset->num_rows == 0) {
    $conn->query("ALTER TABLE credit_sale_limit ADD COLUMN last_reset_at TIMESTAMP NULL COMMENT 'Timestamp when sale count was last reset'");
}
$check_reset->close();

// Calculate total credits sold (only count transactions after last reset)
$last_reset_at = $limit_data['last_reset_at'] ?? null;
if ($last_reset_at) {
    $sold_stmt = $conn->prepare("
        SELECT COALESCE(SUM(
            CASE 
                WHEN transaction_code = '150' THEN 150
                WHEN transaction_code = '100' THEN 100
                ELSE CAST(transaction_code AS UNSIGNED)
            END
        ), 0) as total_sold
        FROM transaction_codes 
        WHERE status = 'verified' AND created_at > ?
    ");
    $sold_stmt->bind_param("s", $last_reset_at);
    $sold_stmt->execute();
    $sold_result = $sold_stmt->get_result();
    $sold_data = $sold_result->fetch_assoc();
    $total_sold = intval($sold_data['total_sold'] ?? 0);
    $sold_stmt->close();
} else {
    // Count all verified transactions if no reset has been done
    $sold_stmt = $conn->query("
        SELECT COALESCE(SUM(
            CASE 
                WHEN transaction_code = '150' THEN 150
                WHEN transaction_code = '100' THEN 100
                ELSE CAST(transaction_code AS UNSIGNED)
            END
        ), 0) as total_sold
        FROM transaction_codes 
        WHERE status = 'verified'
    ");
    $sold_data = $sold_stmt->fetch_assoc();
    $total_sold = intval($sold_data['total_sold'] ?? 0);
    $sold_stmt->close();
}

$total_limit = intval($limit_data['total_limit'] ?? 10000);
$is_enabled = intval($limit_data['is_enabled'] ?? 1);
$sale_mode = $limit_data['sale_mode'] ?? 'limit';
$remaining = max(0, $total_limit - $total_sold);
$percentage_sold = $total_limit > 0 ? round(($total_sold / $total_limit) * 100, 2) : 0;

// Check if limit is reached (only check if limit-based mode)
$can_buy = true;
if ($sale_mode === 'limit' && $is_enabled && $remaining <= 0) {
    $can_buy = false;
}

$conn->close();

echo json_encode([
    'can_buy' => $can_buy,
    'total_limit' => $total_limit,
    'total_sold' => $total_sold,
    'remaining' => $remaining,
    'percentage_sold' => $percentage_sold,
    'is_enabled' => $is_enabled,
    'sale_mode' => $sale_mode,
    'message' => $can_buy ? 
        "{$remaining} credits remaining for sale" : 
        "Credit sale limit reached! No more credits can be purchased at this time."
]);



