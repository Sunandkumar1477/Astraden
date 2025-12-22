<?php
session_start();
require_once 'check_user_session.php';

// Connection is already established in check_user_session.php
$conn = $GLOBALS['conn'];

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['change_password'])) {
    header('Location: view_profile.php');
    exit;
}

$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// Validation
$errors = [];

if (empty($current_password)) {
    $errors[] = 'Current password is required';
}

if (empty($new_password)) {
    $errors[] = 'New password is required';
} elseif (strlen($new_password) < 6) {
    $errors[] = 'New password must be at least 6 characters long';
}

if (empty($confirm_password)) {
    $errors[] = 'Please confirm your new password';
} elseif ($new_password !== $confirm_password) {
    $errors[] = 'New password and confirm password do not match';
}

if ($current_password === $new_password) {
    $errors[] = 'New password must be different from your current password';
}

if (!empty($errors)) {
    $_SESSION['password_error'] = implode('. ', $errors);
    header('Location: view_profile.php');
    exit;
}

// Get current password from database
$stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['password_error'] = 'User not found. Please try again.';
    $stmt->close();
    header('Location: view_profile.php');
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();

// Verify current password
if (!password_verify($current_password, $user['password'])) {
    $_SESSION['password_error'] = 'Current password is incorrect. Please try again.';
    header('Location: view_profile.php');
    exit;
}

// Hash new password
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

// Update password in database
$update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
$update_stmt->bind_param("si", $hashed_password, $user_id);

if ($update_stmt->execute()) {
    // Log password change (if login_logs table exists)
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $log_stmt = $conn->prepare("INSERT INTO login_logs (user_id, username, mobile_number, action, ip_address, user_agent, status) VALUES (?, ?, ?, 'password_change', ?, ?, 'success')");
        if ($log_stmt) {
            $log_stmt->bind_param("issss", $user_id, $_SESSION['username'], $_SESSION['mobile'], $ip_address, $user_agent);
            $log_stmt->execute();
            $log_stmt->close();
        }
    } catch (Exception $e) {
        // Table might not exist, continue without logging
    }
    
    $_SESSION['password_success'] = 'Password updated successfully!';
} else {
    $_SESSION['password_error'] = 'Failed to update password. Please try again.';
}

$update_stmt->close();
$conn->close();

header('Location: view_profile.php');
exit;
?>


