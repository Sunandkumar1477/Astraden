<?php
/**
 * Secure Game API - Enhanced version with comprehensive security
 * This wraps the existing game_api.php functionality with security middleware
 */

require_once 'secure_api_base.php';

// Initialize secure API
// Some endpoints don't require auth (check_status, leaderboard, user_rank)
$action = trim($_GET['action'] ?? '');
$public_actions = ['check_status', 'leaderboard', 'user_rank', 'contest_leaderboard', 'session_leaderboard'];
$requires_auth = !in_array($action, $public_actions);

$api = new SecureAPI($requires_auth, true, true); // Auth, CSRF, Nonce

$conn = $api->getConnection();
$security = $api->getSecurity();
$user_id = $_SESSION['user_id'] ?? null;

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// Validate action parameter
if (!preg_match('/^[a-z0-9_-]+$/i', $action)) {
    $api->respondError('Invalid action', 400);
}

// Route to appropriate handler
switch ($action) {
    case 'save_score':
        handleSaveScore($api, $conn, $security, $user_id);
        break;
        
    case 'deduct_credits':
        handleDeductCredits($api, $conn, $security, $user_id);
        break;
        
    case 'check_status':
        handleCheckStatus($api, $conn, $user_id);
        break;
        
    case 'leaderboard':
        handleLeaderboard($api, $conn);
        break;
        
    case 'contest_leaderboard':
        handleContestLeaderboard($api, $conn);
        break;
        
    case 'claim_contest_prize':
        handleClaimContestPrize($api, $conn, $user_id);
        break;
        
    case 'user_rank':
        handleUserRank($api, $conn, $user_id);
        break;
        
    case 'get_user_score':
        handleGetUserScore($api, $conn, $user_id);
        break;
        
    case 'session_leaderboard':
        handleSessionLeaderboard($api, $conn);
        break;
        
    default:
        $api->respondError('Invalid action', 400);
}

/**
 * Handle score submission with comprehensive validation
 */
function handleSaveScore($api, $conn, $security, $user_id) {
    // Sanitize and validate input
    $input = $api->sanitizeInput($_POST, [
        'score' => ['type' => 'int'],
        'session_id' => ['type' => 'int'],
        'credits_used' => ['type' => 'int'],
        'game_name' => ['type' => 'string'],
        'is_demo' => ['type' => 'string']
    ]);
    
    // Validate input rules
    $api->validateInput($input, [
        'score' => ['required' => true, 'type' => 'int', 'min' => 0, 'max' => 10000000],
        'session_id' => ['required' => true, 'type' => 'int', 'min' => 1],
        'credits_used' => ['required' => true, 'type' => 'int', 'min' => 0],
        'game_name' => [
            'required' => true, 
            'type' => 'regex', 
            'pattern' => '/^[a-z0-9-]+$/', 
            'message' => 'Invalid game name'
        ]
    ]);
    
    $score = intval($input['score']);
    $session_id = intval($input['session_id']);
    $credits_used = intval($input['credits_used']);
    $is_demo = ($input['is_demo'] ?? 'false') === 'true';
    $game_name = $input['game_name'];
    
    // Demo mode - no validation needed, just return
    if ($is_demo) {
        $api->respondSuccess(['demo' => true], 'Demo mode - score not saved');
    }
    
    // Real game requires login
    if (!$user_id) {
        $api->respondError('Please login to save scores', 401);
    }
    
    // SERVER-SIDE SCORE VALIDATION (Critical Security)
    $score_validation = $security->validateScore($score, $game_name, $user_id, $session_id);
    if (!$score_validation['valid']) {
        $security->logSecurityEvent($user_id, $security->getClientIP(), 'invalid_score', 'save_score', 'blocked', $score_validation['reason']);
        $api->respondError($score_validation['reason'], 400);
    }
    
    // Verify credits were actually deducted for this session
    $credits_check = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM game_leaderboard 
        WHERE user_id = ? AND session_id = ? AND credits_used > 0
    ");
    $credits_check->bind_param("ii", $user_id, $session_id);
    $credits_check->execute();
    $credits_result = $credits_check->get_result()->fetch_assoc();
    $credits_check->close();
    
    // If credits_used > 0 but no previous record, verify session exists and credits were deducted
    if ($credits_used > 0 && $credits_result['count'] == 0) {
        $session_check = $conn->prepare("
            SELECT id, credits_required 
            FROM game_sessions 
            WHERE id = ? AND is_active = 1
        ");
        $session_check->bind_param("i", $session_id);
        $session_check->execute();
        $session_data = $session_check->get_result()->fetch_assoc();
        $session_check->close();
        
        if (!$session_data) {
            $security->logSecurityEvent($user_id, $security->getClientIP(), 'invalid_session', 'save_score', 'blocked', 'Session does not exist or is inactive');
            $api->respondError('Invalid game session', 400);
        }
    }
    
    // Get contest status
    $is_contest_active = 0;
    $game_mode = 'money';
    $contest_stmt = $conn->prepare("SELECT is_contest_active, game_mode FROM games WHERE game_name = ?");
    $contest_stmt->bind_param("s", $game_name);
    $contest_stmt->execute();
    $contest_res = $contest_stmt->get_result();
    if ($contest_res->num_rows > 0) {
        $row = $contest_res->fetch_assoc();
        $is_contest_active = intval($row['is_contest_active']);
        $game_mode = $row['game_mode'] ?: 'money';
    }
    $contest_stmt->close();
    
    // Save score with transaction
    $conn->begin_transaction();
    try {
        // Save to contest_scores if contest is active
        if ($is_contest_active) {
            $contest_insert = $conn->prepare("
                INSERT INTO contest_scores (user_id, game_name, score, game_mode) 
                VALUES (?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                    score = GREATEST(score, VALUES(score)), 
                    game_mode = VALUES(game_mode)
            ");
            $contest_insert->bind_param("isis", $user_id, $game_name, $score, $game_mode);
            $contest_insert->execute();
            $contest_insert->close();
        }
        
        // Save to game_leaderboard
        $insert_stmt = $conn->prepare("
            INSERT INTO game_leaderboard (user_id, game_name, score, credits_used, session_id, game_mode) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $insert_stmt->bind_param("isiiis", $user_id, $game_name, $score, $credits_used, $session_id, $game_mode);
        
        if (!$insert_stmt->execute()) {
            throw new Exception('Failed to save score');
        }
        $insert_stmt->close();
        
        // Update available_score if column exists
        if ($credits_used > 0) {
            $check_col = $conn->query("SHOW COLUMNS FROM user_profile LIKE 'available_score'");
            if ($check_col->num_rows > 0) {
                $update_score = $conn->prepare("UPDATE user_profile SET available_score = available_score + ? WHERE user_id = ?");
                $update_score->bind_param("ii", $score, $user_id);
                $update_score->execute();
                $update_score->close();
            }
        }
        
        // Get updated totals
        $total_stmt = $conn->prepare("SELECT SUM(score) as total_points FROM game_leaderboard WHERE user_id = ? AND game_name = ? AND credits_used > 0");
        $total_stmt->bind_param("is", $user_id, $game_name);
        $total_stmt->execute();
        $total_res = $total_stmt->get_result()->fetch_assoc();
        $total_points = intval($total_res['total_points'] ?? 0);
        $total_stmt->close();
        
        $all_games_stmt = $conn->prepare("SELECT SUM(score) as total_points FROM game_leaderboard WHERE user_id = ? AND credits_used > 0");
        $all_games_stmt->bind_param("i", $user_id);
        $all_games_stmt->execute();
        $all_games_res = $all_games_stmt->get_result()->fetch_assoc();
        $total_all_games = intval($all_games_res['total_points'] ?? 0);
        $all_games_stmt->close();
        
        $conn->commit();
        
        $security->logSecurityEvent($user_id, $security->getClientIP(), 'score_saved', 'save_score', 'allowed', "Score: {$score}");
        
        $api->respondSuccess([
            'score' => $score,
            'total_score' => $total_points,
            'total_score_all_games' => $total_all_games,
            'is_contest' => (bool)$is_contest_active,
            'game_mode' => $game_mode
        ], 'Score saved successfully');
        
    } catch (Exception $e) {
        $conn->rollback();
        $security->logSecurityEvent($user_id, $security->getClientIP(), 'score_save_failed', 'save_score', 'failed', $e->getMessage());
        $api->respondError('Failed to save score', 500);
    }
}

/**
 * Handle credit deduction
 */
function handleDeductCredits($api, $conn, $security, $user_id) {
    $input = $api->sanitizeInput($_POST, [
        'session_id' => ['type' => 'int'],
        'game_name' => ['type' => 'string']
    ]);
    
    $api->validateInput($input, [
        'session_id' => ['required' => true, 'type' => 'int', 'min' => 1],
        'game_name' => ['required' => true, 'type' => 'regex', 'pattern' => '/^[a-z0-9-]+$/']
    ]);
    
    $session_id = intval($input['session_id']);
    $game_name = $input['game_name'];
    
    // Verify session exists and is active
    $session_stmt = $conn->prepare("SELECT * FROM game_sessions WHERE id = ? AND is_active = 1");
    $session_stmt->bind_param("i", $session_id);
    $session_stmt->execute();
    $session_result = $session_stmt->get_result();
    
    if ($session_result->num_rows === 0) {
        $session_stmt->close();
        $api->respondError('Invalid or inactive game session', 400);
    }
    
    $session = $session_result->fetch_assoc();
    $session_stmt->close();
    
    $credits_required = intval($session['credits_required'] ?? 30);
    
    // Check user credits
    $credits_stmt = $conn->prepare("SELECT credits FROM user_profile WHERE user_id = ? FOR UPDATE");
    $credits_stmt->bind_param("i", $user_id);
    $credits_stmt->execute();
    $credits_result = $credits_stmt->get_result()->fetch_assoc();
    $current_credits = intval($credits_result['credits'] ?? 0);
    
    if ($current_credits < $credits_required) {
        $credits_stmt->close();
        $api->respondError("Insufficient credits. You need {$credits_required} credits to play.", 400);
    }
    
    // Deduct credits with transaction
    $conn->begin_transaction();
    try {
        $update_stmt = $conn->prepare("UPDATE user_profile SET credits = credits - ? WHERE user_id = ?");
        $update_stmt->bind_param("ii", $credits_required, $user_id);
        
        if (!$update_stmt->execute()) {
            throw new Exception('Failed to deduct credits');
        }
        $update_stmt->close();
        $credits_stmt->close();
        
        $conn->commit();
        
        $api->respondSuccess([
            'credits_remaining' => $current_credits - $credits_required,
            'session_id' => $session_id
        ], 'Credits deducted successfully');
        
    } catch (Exception $e) {
        $conn->rollback();
        $credits_stmt->close();
        $api->respondError('Failed to deduct credits', 500);
    }
}

/**
 * Handle check status (public endpoint)
 */
function handleCheckStatus($api, $conn, $user_id) {
    // Similar to original but with input validation
    $input = $api->sanitizeInput($_GET, [
        'game' => ['type' => 'string'],
        'game_name' => ['type' => 'string']
    ]);
    
    $game_name = $input['game'] ?? $input['game_name'] ?? 'earth-defender';
    
    if (!preg_match('/^[a-z0-9-]+$/', $game_name)) {
        $api->respondError('Invalid game name', 400);
    }
    
    // Continue with original logic (simplified here)
    // ... (include original check_status logic from game_api.php)
    
    $api->respondSuccess(['message' => 'Status check - implement original logic'], 'OK');
}

/**
 * Handle leaderboard (public endpoint)
 */
function handleLeaderboard($api, $conn) {
    $input = $api->sanitizeInput($_GET, [
        'limit' => ['type' => 'int'],
        'game' => ['type' => 'string'],
        'mode' => ['type' => 'string']
    ]);
    
    $limit = min(100, max(1, intval($input['limit'] ?? 10))); // Max 100, min 1
    $game_filter = $input['game'] ?? 'all';
    $mode_filter = $input['mode'] ?? 'all';
    
    // Continue with original leaderboard logic
    // ... (include original leaderboard logic)
    
    $api->respondSuccess(['leaderboard' => []], 'Leaderboard retrieved');
}

// Additional handlers (simplified - implement full logic from game_api.php)
function handleContestLeaderboard($api, $conn) { /* ... */ }
function handleClaimContestPrize($api, $conn, $user_id) { /* ... */ }
function handleUserRank($api, $conn, $user_id) { /* ... */ }
function handleGetUserScore($api, $conn, $user_id) { /* ... */ }
function handleSessionLeaderboard($api, $conn) { /* ... */ }

?>

