<?php
header('Content-Type: application/json');
require_once 'connection.php';

// Check if table exists
$table_exists = false;
$check_table = $conn->query("SHOW TABLES LIKE 'credit_packages'");
if ($check_table && $check_table->num_rows > 0) {
    $table_exists = true;
}

$packages = [];

if ($table_exists) {
    // Get all active credit packages
    $stmt = $conn->prepare("SELECT id, credit_amount, price, is_popular, display_order FROM credit_packages WHERE is_active = 1 ORDER BY display_order ASC, credit_amount ASC");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $packages[] = [
                'id' => intval($row['id']),
                'credit_amount' => intval($row['credit_amount']),
                'price' => floatval($row['price']),
                'is_popular' => (bool)$row['is_popular'],
                'display_order' => intval($row['display_order'])
            ];
        }
        
        $stmt->close();
    }
} else {
    // Return default packages if table doesn't exist
    $packages = [
        [
            'id' => 1,
            'credit_amount' => 100,
            'price' => 100.00,
            'is_popular' => false,
            'display_order' => 1
        ],
        [
            'id' => 2,
            'credit_amount' => 150,
            'price' => 150.00,
            'is_popular' => true,
            'display_order' => 2
        ]
    ];
}

$conn->close();

echo json_encode([
    'success' => true,
    'packages' => $packages,
    'table_exists' => $table_exists
]);



