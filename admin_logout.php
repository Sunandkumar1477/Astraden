<?php
session_start();
require_once 'connection.php';

if (isset($_SESSION['admin_id'])) {
    $admin_id = $_SESSION['admin_id'];
    $admin_username = $_SESSION['admin_username'] ?? 'Unknown';
    
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
    
    // Log logout
    try {
        $log_stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, admin_username, action, description, ip_address, user_agent) VALUES (?, ?, 'logout', 'Admin logout', ?, ?)");
        $log_stmt->bind_param("isss", $admin_id, $admin_username, $ip_address, $user_agent);
        $log_stmt->execute();
        $log_stmt->close();
    } catch (Exception $e) {
        // Continue even if logging fails
    }
    
    $conn->close();
}

session_destroy();
header('Location: admin_login.php?message=logged_out');
exit;
?>

