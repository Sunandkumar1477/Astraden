<?php
session_start();
require_once 'connection.php';

// Log logout if user was logged in
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'] ?? 'Unknown';
    $mobile = $_SESSION['mobile'] ?? '';
    $login_log_id = $_SESSION['login_log_id'] ?? null;
    $login_time = $_SESSION['login_time'] ?? time();
    
    // Calculate session duration
    $logout_time = time();
    $session_duration = $logout_time - $login_time;
    
    // Get IP address
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
    
    $ip_address = getClientIP();
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    try {
        // Update login log with logout time and duration if log_id exists
        if ($login_log_id) {
            $update_stmt = $conn->prepare("UPDATE login_logs SET logout_time = CURRENT_TIMESTAMP, session_duration = ? WHERE id = ?");
            if ($update_stmt) {
                $update_stmt->bind_param("ii", $session_duration, $login_log_id);
                $update_stmt->execute();
                $update_stmt->close();
            }
        }
        
        // Also create a separate logout log entry
        $log_stmt = $conn->prepare("INSERT INTO login_logs (user_id, username, mobile_number, action, ip_address, user_agent, status) VALUES (?, ?, ?, 'logout', ?, ?, 'success')");
        if ($log_stmt) {
            $log_stmt->bind_param("issss", $user_id, $username, $mobile, $ip_address, $user_agent);
            $log_stmt->execute();
            $log_stmt->close();
        }
    } catch (Exception $e) {
        // Table might not exist, continue without logging
    }
    
    // Clear session token from database (if column exists)
    if (isset($_SESSION['user_id'])) {
        $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'session_token'");
        $has_session_column = $check_column->num_rows > 0;
        $check_column->close();
        
        if ($has_session_column) {
            $clear_token_stmt = $conn->prepare("UPDATE users SET session_token = NULL WHERE id = ?");
            $clear_token_stmt->bind_param("i", $_SESSION['user_id']);
            $clear_token_stmt->execute();
            $clear_token_stmt->close();
        }
    }
    
    if (isset($conn)) {
        $conn->close();
    }
}

session_destroy();
header('Location: index.php');
exit;
?>

