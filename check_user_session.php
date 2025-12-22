<?php
/**
 * Single Device Login Check
 * Include this file at the top of protected pages to verify session
 */
session_start();
if (!isset($GLOBALS['conn']) || !$GLOBALS['conn']) {
    require_once 'connection.php';
    $GLOBALS['conn'] = $conn;
} else {
    $conn = $GLOBALS['conn'];
}

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $session_token = $_SESSION['session_token'] ?? null;
    
    // Check if session_token column exists
    $has_session_column = false;
    try {
        $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'session_token'");
        if ($check_column) {
            $has_session_column = $check_column->num_rows > 0;
            $check_column->close();
        }
    } catch (Exception $e) {
        // Column doesn't exist, continue without session token check
        $has_session_column = false;
    }
    
    // Verify session token matches database (only if column exists and token is set)
    if ($has_session_column && $session_token !== null) {
        try {
            $verify_stmt = $conn->prepare("SELECT id, session_token FROM users WHERE id = ? AND session_token = ?");
            $verify_stmt->bind_param("is", $user_id, $session_token);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result();
            
            if ($verify_result->num_rows === 0) {
                // Session token doesn't match - user logged in on another device
                $verify_stmt->close();
                session_destroy();
                if (isset($conn)) {
                    $conn->close();
                }
                
                // Redirect with message
                header('Location: index.php?logout=another_device');
                exit;
            }
            $verify_stmt->close();
        } catch (Exception $e) {
            // Error verifying session, continue anyway
        }
    }
    
    // Don't close connection here - let the calling file close it
    // Connection will be reused by view_profile.php or profile.php
} else {
    // No session, redirect to login
    if (isset($conn)) {
        $conn->close();
    }
    header('Location: index.php');
    exit;
}
?>

