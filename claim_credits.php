<?php
session_start();
require_once 'connection.php';

// Set timezone to India (IST)
date_default_timezone_set('Asia/Kolkata');

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Please login first'
    ]);
    exit;
}

// Claim credits timing removed - always available

$user_id = $_SESSION['user_id'];
$transaction_code = trim($_POST['transaction_code'] ?? '');

// Validate input
if (empty($transaction_code)) {
    echo json_encode([
        'success' => false,
        'message' => 'Transaction code is required'
    ]);
    exit;
}

// Validate format (4 alphanumeric characters)
if (!preg_match('/^[A-Za-z0-9]{4}$/', $transaction_code)) {
    echo json_encode([
        'success' => false,
        'message' => 'Transaction code must be exactly 4 alphanumeric characters'
    ]);
    exit;
}

// Convert to uppercase
$transaction_code = strtoupper($transaction_code);

try {
    // Check if transaction_codes table exists, if not create it
    $check_table = $conn->query("SHOW TABLES LIKE 'transaction_codes'");
    if ($check_table->num_rows == 0) {
        // Create table if it doesn't exist
        $create_table = "CREATE TABLE IF NOT EXISTS transaction_codes (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11) NOT NULL,
            transaction_code VARCHAR(4) NOT NULL,
            status VARCHAR(20) DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_transaction_code (transaction_code),
            INDEX idx_status (status),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $conn->query($create_table);
    }
    
    // Check if this transaction code already exists for ANY user
    $check_stmt = $conn->prepare("SELECT id, user_id FROM transaction_codes WHERE transaction_code = ?");
    $check_stmt->bind_param("s", $transaction_code);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $existing_code = $check_result->fetch_assoc();
        // Check if it's the same user trying to submit again
        if ($existing_code['user_id'] == $user_id) {
            $message = 'This transaction code has already been submitted by you';
        } else {
            // Code is already used by another user
            $message = 'This code is already used, sorry';
        }
        
        echo json_encode([
            'success' => false,
            'message' => $message
        ]);
        $check_stmt->close();
        exit;
    }
    $check_stmt->close();
    
    // Insert transaction code
    $insert_stmt = $conn->prepare("INSERT INTO transaction_codes (user_id, transaction_code, status) VALUES (?, ?, 'pending')");
    $insert_stmt->bind_param("is", $user_id, $transaction_code);
    
    if ($insert_stmt->execute()) {
        // Get updated credits
        $credits_stmt = $conn->prepare("SELECT credits FROM user_profile WHERE user_id = ?");
        $credits_stmt->bind_param("i", $user_id);
        $credits_stmt->execute();
        $credits_result = $credits_stmt->get_result();
        $credits_data = $credits_result->fetch_assoc();
        $credits = $credits_data['credits'] ?? 0;
        $credits_stmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'Transaction code submitted successfully! Your credits will be updated after verification.',
            'credits' => (int)$credits
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to save transaction code. Please try again.'
        ]);
    }
    
    $insert_stmt->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again later.'
    ]);
}

$conn->close();
?>
