<?php
/**
 * Admin Access Control - Advanced Security
 * Include this file at the top of any admin page
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'connection.php';

// Security headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

// Verify session integrity
$admin_id = $_SESSION['admin_id'];
$session_ip = $_SESSION['admin_ip'] ?? '';
$session_user_agent = $_SESSION['admin_user_agent'] ?? '';

// Get current IP and User Agent
function getClientIP() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}

$current_ip = getClientIP();
$current_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

// Verify IP address hasn't changed (prevent session hijacking)
if ($session_ip !== $current_ip && !empty($session_ip)) {
    // Log suspicious activity
    $log_stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, admin_username, action, description, ip_address, user_agent) VALUES (?, ?, 'security_breach', 'IP address mismatch detected', ?, ?)");
    $log_stmt->bind_param("isss", $admin_id, $_SESSION['admin_username'], $current_ip, $current_user_agent);
    $log_stmt->execute();
    $log_stmt->close();
    
    session_destroy();
    header('Location: admin_login.php?error=session_invalid');
    exit;
}

// Verify user agent hasn't changed significantly
if ($session_user_agent !== $current_user_agent && !empty($session_user_agent)) {
    // Log suspicious activity
    $log_stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, admin_username, action, description, ip_address, user_agent) VALUES (?, ?, 'security_breach', 'User agent mismatch detected', ?, ?)");
    $log_stmt->bind_param("isss", $admin_id, $_SESSION['admin_username'], $current_ip, $current_user_agent);
    $log_stmt->execute();
    $log_stmt->close();
    
    session_destroy();
    header('Location: admin_login.php?error=session_invalid');
    exit;
}

// Check if admin account still exists and is active
$stmt = $conn->prepare("SELECT id, username, email, full_name, is_active FROM admin_users WHERE id = ? AND is_active = 1");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    session_destroy();
    header('Location: admin_login.php?error=account_invalid');
    exit;
}

$admin_data = $result->fetch_assoc();
$stmt->close();

// Check session timeout (30 minutes)
$session_timeout = 30 * 60; // 30 minutes
if (isset($_SESSION['admin_login_time']) && (time() - $_SESSION['admin_login_time']) > $session_timeout) {
    // Log timeout
    $log_stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, admin_username, action, description, ip_address, user_agent) VALUES (?, ?, 'session_timeout', 'Session expired due to inactivity', ?, ?)");
    $log_stmt->bind_param("isss", $admin_id, $_SESSION['admin_username'], $current_ip, $current_user_agent);
    $log_stmt->execute();
    $log_stmt->close();
    
    session_destroy();
    header('Location: admin_login.php?error=session_expired');
    exit;
}

// Update last activity time
$_SESSION['admin_login_time'] = time();

// Admin is verified - continue
$GLOBALS['admin_id'] = $admin_id;
$GLOBALS['admin_username'] = $_SESSION['admin_username'];
$GLOBALS['admin_email'] = $_SESSION['admin_email'];
$GLOBALS['admin_full_name'] = $_SESSION['admin_full_name'];

?>
