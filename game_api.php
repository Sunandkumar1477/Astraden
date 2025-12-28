<?php
session_start();
require_once 'connection.php';

// Set timezone to India (IST) - CRITICAL for correct time calculations
date_default_timezone_set('Asia/Kolkata');

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// Allow check_status, leaderboard, and user_rank without login (for demo mode and guests)
// Other actions still require login
$requires_login = !in_array($action, ['check_status', 'leaderboard', 'user_rank']);

if ($requires_login && !isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Ensure database columns are present for the new contest features (with proper error handling)
try {
    // Check and add game_mode column to games table
    $check_col = $conn->query("SHOW COLUMNS FROM games LIKE 'game_mode'");
    if ($check_col->num_rows == 0) {
        $conn->query("ALTER TABLE games ADD COLUMN game_mode ENUM('money', 'credits') DEFAULT 'money'");
    }
    
    // Check and add contest_credits_required column
    $check_col = $conn->query("SHOW COLUMNS FROM games LIKE 'contest_credits_required'");
    if ($check_col->num_rows == 0) {
        $conn->query("ALTER TABLE games ADD COLUMN contest_credits_required INT(11) DEFAULT 0");
    }
    
    // Check and add game_mode to contest_scores if table exists
    $check_table = $conn->query("SHOW TABLES LIKE 'contest_scores'");
    if ($check_table->num_rows > 0) {
        $check_col = $conn->query("SHOW COLUMNS FROM contest_scores LIKE 'game_mode'");
        if ($check_col->num_rows == 0) {
            $conn->query("ALTER TABLE contest_scores ADD COLUMN game_mode ENUM('money', 'credits') DEFAULT 'money'");
        }
    }
    
    // Check and add game_mode to game_leaderboard
    $check_col = $conn->query("SHOW COLUMNS FROM game_leaderboard LIKE 'game_mode'");
    if ($check_col->num_rows == 0) {
        $conn->query("ALTER TABLE game_leaderboard ADD COLUMN game_mode ENUM('money', 'credits') DEFAULT 'money'");
    }
} catch (Exception $e) {
    // Silently handle errors - columns might already exist
}

// Check if tables exist
$check_sessions = $conn->query("SHOW TABLES LIKE 'game_sessions'");
$check_leaderboard = $conn->query("SHOW TABLES LIKE 'game_leaderboard'");

if ($check_sessions->num_rows == 0 || $check_leaderboard->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Game system not initialized']);
    exit;
}

switch ($action) {
    case 'check_status':
        // Check if game session is active
        $game_name = $_GET['game'] ?? 'earth-defender';
        
        // Get user's total points for this game
        $user_total_points = 0;
        if ($user_id) {
            $total_stmt = $conn->prepare("SELECT SUM(score) as total_points FROM game_leaderboard WHERE user_id = ? AND game_name = ? AND credits_used > 0");
            $total_stmt->bind_param("is", $user_id, $game_name);
            $total_stmt->execute();
            $total_res = $total_stmt->get_result()->fetch_assoc();
            $user_total_points = intval($total_res['total_points'] ?? 0);
            $total_stmt->close();
        }

        // Get credits per chance and contest status from games table
        $credits_per_chance = 30; // Default fallback
        $is_contest_active = 0;
        $is_claim_active = 0;
        $prizes = ['1st' => 0, '2nd' => 0, '3rd' => 0];

        $check_games_table = $conn->query("SHOW TABLES LIKE 'games'");
        $game_mode = 'credits'; // Default game mode
        if ($check_games_table->num_rows > 0) {
            // Remove is_active requirement - we need contest settings even if game is not marked active
            $games_stmt = $conn->prepare("SELECT credits_per_chance, is_contest_active, is_claim_active, contest_credits_required, contest_first_prize, contest_second_prize, contest_third_prize, game_mode FROM games WHERE game_name = ?");
            $games_stmt->bind_param("s", $game_name);
            $games_stmt->execute();
            $games_result = $games_stmt->get_result();
            if ($games_result->num_rows > 0) {
                $game_data = $games_result->fetch_assoc();
                $is_contest_active = intval($game_data['is_contest_active']);
                $credits_per_chance = $is_contest_active ? intval($game_data['contest_credits_required']) : intval($game_data['credits_per_chance']);
                $is_claim_active = intval($game_data['is_claim_active']);
                $game_mode = $game_data['game_mode'] ?: 'credits';
                $prizes = [
                    '1st' => intval($game_data['contest_first_prize']),
                    '2nd' => intval($game_data['contest_second_prize']),
                    '3rd' => intval($game_data['contest_third_prize'])
                ];
            }
            $games_stmt->close();
        }
        
        // Get current IST time
        $now = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
        $now_timestamp = $now->getTimestamp();
        
        $stmt = $conn->prepare("
            SELECT * FROM game_sessions 
            WHERE game_name = ? 
            AND is_active = 1 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->bind_param("s", $game_name);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Remove all session timing restrictions - games can be played anytime
        // Try to get a session for reference, but don't require it
        $session = null;
        if ($result->num_rows > 0) {
            $session = $result->fetch_assoc();
        }
        
        // Always allow games to be played - no timing restrictions
        $is_active = true; // Always active - no restrictions
        
        // Create a virtual session if none exists
        if (!$session) {
            $session = [
                'id' => 0,
                'session_date' => date('Y-m-d'),
                'session_time' => date('H:i:s'),
                'duration_minutes' => 1440, // 24 hours
                'credits_required' => $credits_per_chance
            ];
        }
        
        // Set timestamps to allow play anytime
        $session_start = $now_timestamp - 86400; // Start 24 hours ago
        $session_end = $now_timestamp + 86400; // End 24 hours from now
        
        echo json_encode([
            'success' => true,
            'is_active' => true, // Always active - no restrictions
            'is_contest_active' => $is_contest_active,
            'is_claim_active' => $is_claim_active,
            'game_mode' => $game_mode,
            'contest_prizes' => $prizes,
            'user_total_points' => $user_total_points,
            'session' => [
                'id' => $session['id'] ?? 0,
                'date' => $session['session_date'] ?? date('Y-m-d'),
                'time' => $session['session_time'] ?? date('H:i:s'),
                'duration' => $session['duration_minutes'] ?? 1440,
                'credits_required' => $credits_per_chance,
                'start_timestamp' => $session_start,
                'end_timestamp' => $session_end,
                'time_until_start' => 0 // Always ready
            ]
        ]);
        $stmt->close();
        break;
        
    case 'deduct_credits':
        // Deduct credits when user starts game
        if (!$user_id) {
            echo json_encode(['success' => false, 'message' => 'Please login first']);
            exit;
        }
        
        $session_id = intval($_POST['session_id'] ?? 0);
        $game_name = $_POST['game_name'] ?? 'earth-defender';
        $play_mode = $_POST['play_mode'] ?? 'normal'; // 'normal' or 'contest'
        $credits_amount = intval($_POST['credits_amount'] ?? 0); // Amount passed from frontend
        
        // Get credits per chance from games table (admin-set value) - this is the source of truth
        $normal_cost = 30; // Default fallback
        $contest_cost = 30; // Default fallback
        $check_games_table = $conn->query("SHOW TABLES LIKE 'games'");
        if ($check_games_table && $check_games_table->num_rows > 0) {
            $games_stmt = $conn->prepare("SELECT credits_per_chance, contest_credits_required FROM games WHERE game_name = ?");
            if ($games_stmt) {
                $games_stmt->bind_param("s", $game_name);
                $games_stmt->execute();
                $games_result = $games_stmt->get_result();
                if ($games_result && $games_result->num_rows > 0) {
                    $game_data = $games_result->fetch_assoc();
                    $normal_cost = intval($game_data['credits_per_chance'] ?? 30);
                    $contest_cost = intval($game_data['contest_credits_required'] ?? 30);
                }
                $games_stmt->close();
            }
        }
        if ($check_games_table) {
            $check_games_table->close();
        }
        
        // Use the amount passed from frontend, or determine based on play mode
        if ($credits_amount > 0) {
            $credits_required = $credits_amount;
        } else {
            $credits_required = ($play_mode === 'contest') ? $contest_cost : $normal_cost;
        }
        
        // Get active session (if session_id not provided, get latest active)
        // Allow payment even if no session exists (for pre-launch payment)
        $session = null;
        $session_stmt = null;
        if ($session_id > 0) {
            $session_stmt = $conn->prepare("SELECT * FROM game_sessions WHERE id = ? AND is_active = 1");
            if ($session_stmt) {
                $session_stmt->bind_param("i", $session_id);
                $session_stmt->execute();
                $session_result = $session_stmt->get_result();
                if ($session_result->num_rows > 0) {
                    $session = $session_result->fetch_assoc();
                    $session_id = $session['id'];
                }
                $session_stmt->close();
                $session_stmt = null;
            }
        } else {
            $session_stmt = $conn->prepare("SELECT * FROM game_sessions WHERE game_name = ? AND is_active = 1 ORDER BY created_at DESC LIMIT 1");
            if ($session_stmt) {
                $session_stmt->bind_param("s", $game_name);
                $session_stmt->execute();
                $session_result = $session_stmt->get_result();
                if ($session_result->num_rows > 0) {
                    $session = $session_result->fetch_assoc();
                    $session_id = $session['id'];
                }
                $session_stmt->close();
                $session_stmt = null;
            }
        }
        
        // If no session exists, create a virtual session ID (0) - payment can still proceed
        if (!$session) {
            $session_id = 0; // Virtual session for pre-launch payment
        }
        
        // Remove all session timing restrictions - games can be played anytime
        // No need to check if session is active - always allow play
        
        // Use credits from games table, not from session
        
        // Check user credits - ensure user_profile exists
        $credits_stmt = $conn->prepare("SELECT credits FROM user_profile WHERE user_id = ?");
        if (!$credits_stmt) {
            echo json_encode(['success' => false, 'message' => 'Database error: Unable to check credits']);
            if ($session_stmt) {
                $session_stmt->close();
            }
            exit;
        }
        
        $credits_stmt->bind_param("i", $user_id);
        $credits_stmt->execute();
        $credits_result = $credits_stmt->get_result();
        $credits_data = $credits_result->fetch_assoc();
        $current_credits = $credits_data['credits'] ?? 0;
        
        // If user_profile doesn't exist, create it
        if ($credits_data === null) {
            $create_profile = $conn->prepare("INSERT INTO user_profile (user_id, credits) VALUES (?, 0)");
            if ($create_profile) {
                $create_profile->bind_param("i", $user_id);
                $create_profile->execute();
                $create_profile->close();
            }
            $current_credits = 0;
        }
        
        if ($current_credits < $credits_required) {
            echo json_encode([
                'success' => false,
                'message' => "Insufficient Astrons. You need {$credits_required} Astrons to play.",
                'credits' => $current_credits,
                'required' => $credits_required
            ]);
            $credits_stmt->close();
            if ($session_stmt) {
                $session_stmt->close();
            }
            exit;
        }
        
        // Deduct credits
        $update_stmt = $conn->prepare("UPDATE user_profile SET credits = credits - ? WHERE user_id = ?");
        if (!$update_stmt) {
            echo json_encode(['success' => false, 'message' => 'Database error: Unable to deduct credits']);
            $credits_stmt->close();
            if ($session_stmt) {
                $session_stmt->close();
            }
            exit;
        }
        
        $update_stmt->bind_param("ii", $credits_required, $user_id);
        
        if ($update_stmt->execute()) {
            $new_balance = max(0, $current_credits - $credits_required);
            echo json_encode([
                'success' => true,
                'message' => 'Astrons deducted successfully',
                'credits_remaining' => $new_balance,
                'session_id' => $session_id
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to deduct Astrons: ' . $update_stmt->error]);
        }
        $update_stmt->close();
        $credits_stmt->close();
        if ($session_stmt) {
            $session_stmt->close();
        }
        break;
        
    case 'save_score':
        // Save game score (demo mode doesn't save, requires login for real games)
        $score = intval($_POST['score'] ?? 0);
        $session_id = intval($_POST['session_id'] ?? 0);
        $credits_used = intval($_POST['credits_used'] ?? 0);
        $is_demo = isset($_POST['is_demo']) && $_POST['is_demo'] === 'true';
        $game_name = $_POST['game_name'] ?? 'earth-defender';

        // Check if contest mode is active
        $is_contest_active = 0;
        $game_mode = 'credits';
        $contest_stmt = $conn->prepare("SELECT is_contest_active, game_mode FROM games WHERE game_name = ?");
        $contest_stmt->bind_param("s", $game_name);
        $contest_stmt->execute();
        $contest_res = $contest_stmt->get_result();
        if ($contest_res->num_rows > 0) {
            $row = $contest_res->fetch_assoc();
            $is_contest_active = intval($row['is_contest_active']);
            $game_mode = $row['game_mode'] ?: 'credits';
        }
        $contest_stmt->close();
        
        // Demo mode scores are not saved (works without login)
        if ($is_demo) {
            echo json_encode([
                'success' => true,
                'message' => 'Demo mode - score not saved',
                'demo' => true
            ]);
            exit;
        }
        
        // Real game requires login
        if (!$user_id) {
            echo json_encode([
                'success' => false,
                'message' => 'Please login to save scores'
            ]);
            exit;
        }
        
        if ($score < 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid score']);
            exit;
        }

        if ($is_contest_active) {
            // Save to contest_scores (ONLY the best score for this contest)
            // We include game_mode so we know what type of contest this best score belongs to
            $contest_insert = $conn->prepare("INSERT INTO contest_scores (user_id, game_name, score, game_mode) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE score = GREATEST(score, VALUES(score)), game_mode = VALUES(game_mode)");
            $contest_insert->bind_param("isis", $user_id, $game_name, $score, $game_mode);
            $contest_insert->execute();
            $contest_insert->close();
        }
        
        // ALWAYS save to game_leaderboard so it counts towards "Total Points" on the main leaderboard
        $insert_stmt = $conn->prepare("INSERT INTO game_leaderboard (user_id, game_name, score, credits_used, session_id, game_mode) VALUES (?, ?, ?, ?, ?, ?)");
        $insert_stmt->bind_param("isiiis", $user_id, $game_name, $score, $credits_used, $session_id, $game_mode);
        
        if ($insert_stmt->execute()) {
            // Update total_score in users table (add the new score)
            $update_total_stmt = $conn->prepare("UPDATE users SET total_score = total_score + ? WHERE id = ?");
            $update_total_stmt->bind_param("ii", $score, $user_id);
            $update_total_stmt->execute();
            $update_total_stmt->close();
            
            // Get updated total score from users table
            $total_stmt = $conn->prepare("SELECT total_score FROM users WHERE id = ?");
            $total_stmt->bind_param("i", $user_id);
            $total_stmt->execute();
            $total_res = $total_stmt->get_result()->fetch_assoc();
            $total_points = intval($total_res['total_score'] ?? 0);
            $total_stmt->close();
            
            // Also get game-specific total for backward compatibility
            $game_total_stmt = $conn->prepare("SELECT SUM(score) as total_points FROM game_leaderboard WHERE user_id = ? AND game_name = ? AND credits_used > 0");
            $game_total_stmt->bind_param("is", $user_id, $game_name);
            $game_total_stmt->execute();
            $game_total_res = $game_total_stmt->get_result()->fetch_assoc();
            $game_total_points = intval($game_total_res['total_points'] ?? 0);
            $game_total_stmt->close();

            echo json_encode([
                'success' => true,
                'message' => 'Score saved successfully',
                'score' => $score,
                'total_score' => $total_points,
                'game_total_score' => $game_total_points,
                'is_contest' => (bool)$is_contest_active,
                'game_mode' => $game_mode
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save score']);
        }
        $insert_stmt->close();
        break;
        
    case 'leaderboard':
        // Get top 10 leaderboard based on TOTAL POINTS (sum of all scores)
        $limit = intval($_GET['limit'] ?? 10);
        $game_filter = $_GET['game'] ?? 'all';
        $mode_filter = $_GET['mode'] ?? 'all';

        $where_clauses = ["gl.credits_used > 0"];
        if ($game_filter !== 'all') {
            $where_clauses[] = "gl.game_name = '" . $conn->real_escape_string($game_filter) . "'";
        }
        if ($mode_filter !== 'all') {
            $where_clauses[] = "gl.game_mode = '" . $conn->real_escape_string($mode_filter) . "'";
        }
        $where_sql = implode(" AND ", $where_clauses);

        $stmt = $conn->prepare("
            SELECT 
                u.id as user_id,
                u.username,
                up.full_name,
                up.credits_color,
                COALESCE(SUM(gl.score), 0) as total_points,
                COUNT(gl.id) as games_played
            FROM users u
            LEFT JOIN user_profile up ON u.id = up.user_id
            LEFT JOIN game_leaderboard gl ON u.id = gl.user_id AND $where_sql
            GROUP BY u.id, u.username, up.full_name, up.credits_color
            HAVING total_points > 0
            ORDER BY total_points DESC
            LIMIT ?
        ");
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $leaderboard = $result->fetch_all(MYSQLI_ASSOC);
        
        // Add rank numbers
        $ranked_leaderboard = [];
        $current_rank = 1;
        $prev_points = null;
        foreach ($leaderboard as $index => $entry) {
            $entry['total_points'] = intval($entry['total_points']);
            if ($prev_points !== null && $entry['total_points'] < $prev_points) {
                $current_rank = $index + 1;
            }
            $entry['rank'] = $current_rank;
            $entry['score'] = $entry['total_points']; // For compatibility
            $ranked_leaderboard[] = $entry;
            $prev_points = $entry['total_points'];
        }
        
        echo json_encode([
            'success' => true,
            'leaderboard' => $ranked_leaderboard
        ]);
        $stmt->close();
        break;
        
    case 'contest_leaderboard':
        $game_name = $_GET['game'] ?? 'earth-defender';
        $stmt = $conn->prepare("
            SELECT cs.score, u.username, up.full_name, up.credits_color
            FROM contest_scores cs
            JOIN users u ON cs.user_id = u.id
            LEFT JOIN user_profile up ON u.id = up.user_id
            WHERE cs.game_name = ?
            ORDER BY cs.score DESC
            LIMIT 10
        ");
        $stmt->bind_param("s", $game_name);
        $stmt->execute();
        $res = $stmt->get_result();
        $leaderboard = $res->fetch_all(MYSQLI_ASSOC);
        
        echo json_encode([
            'success' => true,
            'leaderboard' => $leaderboard
        ]);
        $stmt->close();
        break;

    case 'claim_contest_prize':
        // Handle claiming contest prizes
        $game_name = $_GET['game'] ?? 'earth-defender';
        
        // Check if claim is active
        $claim_check = $conn->prepare("SELECT is_claim_active, contest_first_prize, contest_second_prize, contest_third_prize FROM games WHERE game_name = ?");
        $claim_check->bind_param("s", $game_name);
        $claim_check->execute();
        $claim_res = $claim_check->get_result()->fetch_assoc();
        
        if (!$claim_res['is_claim_active']) {
            echo json_encode(['success' => false, 'message' => 'Claiming is not active yet.']);
            exit;
        }

        // Determine user rank in contest
        $rank_stmt = $conn->prepare("
            SELECT rank_pos FROM (
                SELECT user_id, RANK() OVER (ORDER BY score DESC) as rank_pos
                FROM contest_scores
                WHERE game_name = ?
            ) as rankings
            WHERE user_id = ?
        ");
        $rank_stmt->bind_param("si", $game_name, $user_id);
        $rank_stmt->execute();
        $rank_data = $rank_stmt->get_result()->fetch_assoc();
        $rank = $rank_data['rank_pos'] ?? 0;

        if ($rank < 1 || $rank > 3) {
            echo json_encode(['success' => false, 'message' => 'You did not qualify for a prize (Top 3 only).']);
            exit;
        }

        // Check if already claimed
        $check_claimed = $conn->prepare("SELECT id FROM contest_winners WHERE user_id = ? AND game_name = ? AND is_claimed = 1");
        $check_claimed->bind_param("is", $user_id, $game_name);
        $check_claimed->execute();
        if ($check_claimed->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'You have already claimed your prize for this contest.']);
            exit;
        }

        // Get prize amount
        $prize_credits = 0;
        if ($rank == 1) $prize_credits = $claim_res['contest_first_prize'];
        elseif ($rank == 2) $prize_credits = $claim_res['contest_second_prize'];
        elseif ($rank == 3) $prize_credits = $claim_res['contest_third_prize'];

        if ($prize_credits <= 0) {
            echo json_encode(['success' => false, 'message' => 'No prize Astrons assigned for your rank.']);
            exit;
        }

        // Transaction: Add credits and mark as claimed
        $conn->begin_transaction();
        try {
            // Update user credits
            $update_credits = $conn->prepare("UPDATE user_profile SET credits = credits + ? WHERE user_id = ?");
            $update_credits->bind_param("ii", $prize_credits, $user_id);
            $update_credits->execute();

            // Insert into winners/claims log
            $log_claim = $conn->prepare("INSERT INTO contest_winners (user_id, game_name, rank, prize_credits, is_claimed, claimed_at) VALUES (?, ?, ?, ?, 1, NOW())");
            $log_claim->bind_param("isii", $user_id, $game_name, $rank, $prize_credits);
            $log_claim->execute();

            $conn->commit();
            echo json_encode(['success' => true, 'message' => "Congratulations! You claimed $prize_credits Astrons for Rank $rank!", 'credits_added' => $prize_credits]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'An error occurred while claiming.']);
        }
        break;

    case 'user_rank':
        // Get user's rank and top 10 leaderboard
        // Following Admin Leaderboard logic exactly: SUM of scores where credits_used > 0
        $game_name = $_GET['game'] ?? 'earth-defender';
        
        try {
            // 1. Get current contest status for UI labeling only
            $is_contest = 0;
            $game_mode = 'credits';
            $g_stmt = $conn->prepare("SELECT is_contest_active, game_mode FROM games WHERE game_name = ?");
            $g_stmt->bind_param("s", $game_name);
            $g_stmt->execute();
            $g_res = $g_stmt->get_result();
            if ($g_res->num_rows > 0) {
                $g_data = $g_res->fetch_assoc();
                $is_contest = intval($g_data['is_contest_active']);
                $game_mode = $g_data['game_mode'] ?: 'credits';
            }
            $g_stmt->close();

            // 2. Calculate User's Rank based on SUM of scores (Admin's "Perfect" logic)
            // Filtered by current game to show game-specific standing
            $rank_query = "
                SELECT rank_pos, total_points FROM (
                    SELECT user_id, SUM(score) as total_points, RANK() OVER (ORDER BY SUM(score) DESC) as rank_pos
                    FROM game_leaderboard
                    WHERE game_name = ? AND credits_used > 0
                    GROUP BY user_id
                ) as rankings
                WHERE user_id = ?
            ";
            $r_stmt = $conn->prepare($rank_query);
            $r_stmt->bind_param("si", $game_name, $user_id);
            $r_stmt->execute();
            $r_res = $r_stmt->get_result()->fetch_assoc();
            $user_rank = $r_res['rank_pos'] ?? null;
            $user_total_points = intval($r_res['total_points'] ?? 0);
            $r_stmt->close();
            
            // 3. Get Top 10 Leaderboard using exact Admin Page logic (SUM of scores)
            $leaderboard_stmt = $conn->prepare("
                SELECT 
                    u.id as user_id,
                    u.username,
                    up.full_name,
                    up.credits_color,
                    SUM(gl.score) as total_points,
                    COUNT(gl.id) as games_played
                FROM users u
                JOIN game_leaderboard gl ON u.id = gl.user_id
                LEFT JOIN user_profile up ON u.id = up.user_id
                WHERE gl.game_name = ? AND gl.credits_used > 0
                GROUP BY u.id, u.username, up.full_name, up.credits_color
                ORDER BY total_points DESC
                LIMIT 10
            ");
            $leaderboard_stmt->bind_param("s", $game_name);
            $leaderboard_stmt->execute();
            $leaderboard_result = $leaderboard_stmt->get_result();
            $leaderboard = $leaderboard_result->fetch_all(MYSQLI_ASSOC);
            
            // Add rank numbers (handling ties)
            $ranked_leaderboard = [];
            $current_rank = 1;
            $prev_points = null;
            foreach ($leaderboard as $index => $entry) {
                $entry['total_points'] = intval($entry['total_points']);
                if ($prev_points !== null && $entry['total_points'] < $prev_points) {
                    $current_rank = $index + 1;
                }
                $entry['rank'] = $current_rank;
                $ranked_leaderboard[] = $entry;
                $prev_points = $entry['total_points'];
            }
            
            echo json_encode([
                'success' => true,
                'is_contest' => (bool)$is_contest,
                'game_mode' => $game_mode,
                'user_rank' => $user_rank,
                'user_total_points' => $user_total_points,
                'leaderboard' => $ranked_leaderboard
            ]);
            $leaderboard_stmt->close();
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Error loading rank: ' . $e->getMessage(),
                'user_rank' => null,
                'user_total_points' => 0,
                'leaderboard' => []
            ]);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

$conn->close();
?>

