<?php
session_start();
require_once 'connection.php';

header('Content-Type: application/json');

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $session_token = $_SESSION['session_token'] ?? null;
    
    // Check if session_token column exists
    $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'session_token'");
    $has_session_column = $check_column->num_rows > 0;
    $check_column->close();
    
    // Verify session token matches database (only if column exists)
    // Silently log out if there's a DIFFERENT active token in database (user logged in elsewhere)
    if ($has_session_column) {
        // Get the current session token from database
        $db_token_stmt = $conn->prepare("SELECT session_token FROM users WHERE id = ?");
        $db_token_stmt->bind_param("i", $user_id);
        $db_token_stmt->execute();
        $db_token_result = $db_token_stmt->get_result();
        $db_user = $db_token_result->fetch_assoc();
        $db_token_stmt->close();
        
        $db_session_token = $db_user['session_token'] ?? null;
        
        // Only show conflict if:
        // 1. There's a token in the database (not null/empty)
        // 2. The token in database is different from session token
        // 3. The session token exists (not null/empty)
        if ($db_session_token !== null && 
            $db_session_token !== '' && 
            $session_token !== null && 
            $session_token !== '' && 
            $db_session_token !== $session_token) {
            // Different active session token in database - user logged in on another device
            // Silently log out without showing message
            session_destroy();
            $conn->close();
            echo json_encode([
                'logged_in' => false
            ]);
            exit;
        }
        
        // If session token doesn't exist in session but exists in database, update session
        // This handles cases where session was lost but user is still logged in
        if (($session_token === null || $session_token === '') && 
            $db_session_token !== null && 
            $db_session_token !== '') {
            $_SESSION['session_token'] = $db_session_token;
        }
    }
    
    // Get user profile with credits
    $profile_stmt = $conn->prepare("SELECT credits, credits_color FROM user_profile WHERE user_id = ?");
    $profile_stmt->bind_param("i", $user_id);
    $profile_stmt->execute();
    $profile_result = $profile_stmt->get_result();
    $profile = $profile_result->fetch_assoc();
    $profile_stmt->close();
    
    $credits = $profile['credits'] ?? 0;
    $credits_color = $profile['credits_color'] ?? '#00ffff';
    
    // Get referral code
    $ref_stmt = $conn->prepare("SELECT referral_code FROM users WHERE id = ?");
    $ref_stmt->bind_param("i", $user_id);
    $ref_stmt->execute();
    $ref_result = $ref_stmt->get_result();
    $ref_data = $ref_result->fetch_assoc();
    $referral_code = $ref_data['referral_code'] ?? null;
    $ref_stmt->close();
    
    $conn->close();
    
    echo json_encode([
        'logged_in' => true,
        'user' => [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'] ?? '',
            'mobile' => $_SESSION['mobile'] ?? ''
        ],
        'credits' => (int)$credits,
        'credits_color' => $credits_color,
        'referral_code' => $referral_code
    ]);
} else {
    echo json_encode(['logged_in' => false]);
}
?>

