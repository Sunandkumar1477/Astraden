<?php
// Prevent any output before JSON
ob_start();

session_start();
require_once 'check_user_session.php';

// Clear any output buffer
ob_clean();

// Connection is already established in check_user_session.php
$conn = $GLOBALS['conn'];

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$current_password = $_POST['current_password'] ?? '';

if (empty($current_password)) {
    echo json_encode(['success' => false, 'message' => 'Current password is required']);
    exit;
}

try {
    // Get current password from database
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    if (!$stmt) {
        throw new Exception('Database prepare failed');
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    // Verify current password
    if (password_verify($current_password, $user['password'])) {
        echo json_encode(['success' => true, 'message' => 'Current password is correct']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Wrong password']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Wrong password']);
}

if (isset($conn)) {
    $conn->close();
}
exit;
?>


