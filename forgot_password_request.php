<?php
// Prevent any output before JSON
ob_start();

session_start();
require_once 'connection.php';

// Clear any output buffer
ob_clean();

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$username = trim($_POST['username'] ?? '');
$mobile = trim($_POST['mobile'] ?? '');

// Validation
if (empty($username)) {
    echo json_encode(['success' => false, 'message' => 'Username is required']);
    exit;
}

if (empty($mobile)) {
    echo json_encode(['success' => false, 'message' => 'Mobile number is required']);
    exit;
}

// Validate mobile format
if (!preg_match('/^[0-9]{10,15}$/', $mobile)) {
    echo json_encode(['success' => false, 'message' => 'Invalid mobile number format. Please enter 10-15 digits.']);
    exit;
}

// Verify that username and mobile number match a user in the database
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND mobile_number = ?");
$stmt->bind_param("ss", $username, $mobile);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'Username and mobile number do not match. Please check your details.']);
    $conn->close();
    exit;
}

$user = $result->fetch_assoc();
$user_id = $user['id'];
$stmt->close();

// Check if table exists, create if not
try {
    $check_table = $conn->query("SHOW TABLES LIKE 'password_reset_requests'");
    if ($check_table->num_rows === 0) {
        // Create table if it doesn't exist
        $create_table_sql = "CREATE TABLE IF NOT EXISTS `password_reset_requests` (
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
            INDEX `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $conn->query($create_table_sql);
    }
} catch (Exception $e) {
    // Table creation failed, try to continue
}

// Insert password reset request into database
try {
    $insert_stmt = $conn->prepare("INSERT INTO password_reset_requests (user_id, username, mobile_number, status) VALUES (?, ?, ?, 'pending')");
    if ($insert_stmt) {
        $insert_stmt->bind_param("iss", $user_id, $username, $mobile);
        
        if ($insert_stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Password reset request submitted successfully! Admin will verify your details and send your new password within 24 hours.'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to submit request. Please try again.']);
        }
        $insert_stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}

$conn->close();
exit;
?>


