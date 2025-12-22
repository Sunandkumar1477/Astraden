<?php
session_start();
require_once 'check_user_session.php';
require_once 'connection.php';

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Unknown';

// Handle account deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    $confirm_text = trim($_POST['confirm_text'] ?? '');
    
    // Require user to type "DELETE" to confirm
    if ($confirm_text !== 'DELETE') {
        $_SESSION['delete_error'] = 'Please type "DELETE" exactly to confirm account deletion.';
        header('Location: view_profile.php');
        exit;
    }
    
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Delete user profile
        $profile_stmt = $conn->prepare("DELETE FROM user_profile WHERE user_id = ?");
        $profile_stmt->bind_param("i", $user_id);
        $profile_stmt->execute();
        $profile_stmt->close();
        
        // Delete transaction codes
        $trans_stmt = $conn->prepare("DELETE FROM transaction_codes WHERE user_id = ?");
        $trans_stmt->bind_param("i", $user_id);
        $trans_stmt->execute();
        $trans_stmt->close();
        
        // Delete referral earnings where user is referrer
        $ref_earn_stmt = $conn->prepare("DELETE FROM referral_earnings WHERE referrer_id = ?");
        $ref_earn_stmt->bind_param("i", $user_id);
        $ref_earn_stmt->execute();
        $ref_earn_stmt->close();
        
        // Delete referral earnings where user was referred
        $ref_earn2_stmt = $conn->prepare("DELETE FROM referral_earnings WHERE referred_user_id = ?");
        $ref_earn2_stmt->bind_param("i", $user_id);
        $ref_earn2_stmt->execute();
        $ref_earn2_stmt->close();
        
        // Delete game leaderboard scores (check if table exists)
        $check_leaderboard = $conn->query("SHOW TABLES LIKE 'game_leaderboard'");
        if ($check_leaderboard->num_rows > 0) {
            $leaderboard_stmt = $conn->prepare("DELETE FROM game_leaderboard WHERE user_id = ?");
            $leaderboard_stmt->bind_param("i", $user_id);
            $leaderboard_stmt->execute();
            $leaderboard_stmt->close();
        }
        $check_leaderboard->close();
        
        // Delete user sessions
        $check_sessions = $conn->query("SHOW TABLES LIKE 'user_sessions'");
        if ($check_sessions->num_rows > 0) {
            $sessions_stmt = $conn->prepare("DELETE FROM user_sessions WHERE user_id = ?");
            $sessions_stmt->bind_param("i", $user_id);
            $sessions_stmt->execute();
            $sessions_stmt->close();
        }
        $check_sessions->close();
        
        // Delete login logs
        $check_logs = $conn->query("SHOW TABLES LIKE 'login_logs'");
        if ($check_logs->num_rows > 0) {
            $logs_stmt = $conn->prepare("DELETE FROM login_logs WHERE user_id = ?");
            $logs_stmt->bind_param("i", $user_id);
            $logs_stmt->execute();
            $logs_stmt->close();
        }
        $check_logs->close();
        
        // Update referred_by to NULL for users who were referred by this user
        $check_ref_col = $conn->query("SHOW COLUMNS FROM users LIKE 'referred_by'");
        if ($check_ref_col->num_rows > 0) {
            $update_ref_stmt = $conn->prepare("UPDATE users SET referred_by = NULL WHERE referred_by = ?");
            $update_ref_stmt->bind_param("i", $user_id);
            $update_ref_stmt->execute();
            $update_ref_stmt->close();
        }
        $check_ref_col->close();
        
        // Finally, delete the user account
        $user_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $user_stmt->bind_param("i", $user_id);
        $user_stmt->execute();
        $user_stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        // Destroy session and redirect
        session_destroy();
        header('Location: index.php?account_deleted=1');
        exit;
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $_SESSION['delete_error'] = 'Failed to delete account. Please try again or contact support.';
        header('Location: view_profile.php');
        exit;
    }
} else {
    // Invalid request
    header('Location: view_profile.php');
    exit;
}



