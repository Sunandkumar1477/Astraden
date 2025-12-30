<?php
session_start();
require_once 'connection.php';

header('Content-Type: application/json');

date_default_timezone_set('Asia/Kolkata');

$game_name = $_GET['game'] ?? null;

// Check if games table exists
$check_table = $conn->query("SHOW TABLES LIKE 'games'");
if ($check_table->num_rows == 0) {
    echo json_encode(['success' => true, 'game_name' => $game_name, 'credits_per_chance' => 30]);
    exit;
}

// Columns to fetch
$cols = "game_name, credits_per_chance, is_active, is_contest_active, game_mode, contest_credits_required, contest_first_prize, contest_second_prize, contest_third_prize, first_prize, second_prize, third_prize";

// Function to get credits from active session if available
function getSessionCredits($conn, $game_name) {
    $check_sessions_table = $conn->query("SHOW TABLES LIKE 'game_sessions'");
    if ($check_sessions_table->num_rows == 0) {
        return null;
    }
    
    $session_stmt = $conn->prepare("SELECT credits_required, always_available, session_date, session_time, duration_minutes FROM game_sessions WHERE game_name = ? AND is_active = 1 ORDER BY created_at DESC LIMIT 1");
    $session_stmt->bind_param("s", $game_name);
    $session_stmt->execute();
    $session_result = $session_stmt->get_result();
    
    if ($session_result->num_rows > 0) {
        $session = $session_result->fetch_assoc();
        $is_always_available = isset($session['always_available']) && $session['always_available'] == 1;
        
        if ($is_always_available) {
            // Always available session - use its credits
            $session_stmt->close();
            return isset($session['credits_required']) ? intval($session['credits_required']) : null;
        } else {
            // Time-restricted session - check if it's currently active
            $now = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
            $now_timestamp = $now->getTimestamp();
            $session_datetime_str = $session['session_date'] . ' ' . $session['session_time'];
            $session_start_dt = new DateTime($session_datetime_str, new DateTimeZone('Asia/Kolkata'));
            $session_start = $session_start_dt->getTimestamp();
            $session_end = $session_start + ($session['duration_minutes'] * 60);
            
            if ($now_timestamp >= $session_start && $now_timestamp <= $session_end) {
                // Session is active - use its credits
                $session_stmt->close();
                return isset($session['credits_required']) ? intval($session['credits_required']) : null;
            }
        }
    }
    $session_stmt->close();
    return null;
}

if ($game_name) {
    $stmt = $conn->prepare("SELECT $cols FROM games WHERE game_name = ?");
    $stmt->bind_param("s", $game_name);
    $stmt->execute();
    $game = $stmt->get_result()->fetch_assoc();
    
    if ($game) {
        $is_contest = intval($game['is_contest_active']);
        
        // Check if there's an active session with credits set
        $session_credits = getSessionCredits($conn, $game_name);
        
        // Use session credits if available, otherwise use games table credits
        if ($session_credits !== null) {
            $final_credits = $session_credits;
        } else {
            $final_credits = $is_contest ? intval($game['contest_credits_required']) : intval($game['credits_per_chance']);
        }
        
        echo json_encode([
            'success' => true,
            'game_name' => $game['game_name'],
            'credits_per_chance' => $final_credits,
            'is_contest_active' => $is_contest,
            'game_mode' => $game['game_mode'] ?: 'money',
            'contest_prizes' => [
                '1st' => intval($game['contest_first_prize']),
                '2nd' => intval($game['contest_second_prize']),
                '3rd' => intval($game['contest_third_prize'])
            ],
            'normal_prizes' => [
                '1st' => floatval($game['first_prize']),
                '2nd' => floatval($game['second_prize']),
                '3rd' => floatval($game['third_prize'])
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Game not found']);
    }
} else {
    $games = $conn->query("SELECT $cols FROM games WHERE is_active = 1")->fetch_all(MYSQLI_ASSOC);
    $response = [];
    foreach ($games as $g) {
        $is_contest = intval($g['is_contest_active']);
        
        // Check if there's an active session with credits set
        $session_credits = getSessionCredits($conn, $g['game_name']);
        
        // Use session credits if available, otherwise use games table credits
        if ($session_credits !== null) {
            $final_credits = $session_credits;
        } else {
            $final_credits = $is_contest ? intval($g['contest_credits_required']) : intval($g['credits_per_chance']);
        }
        
        $response[$g['game_name']] = [
            'credits_per_chance' => $final_credits,
            'is_contest_active' => $is_contest,
            'game_mode' => $g['game_mode'] ?: 'money',
            'contest_prizes' => [
                '1st' => intval($g['contest_first_prize']),
                '2nd' => intval($g['contest_second_prize']),
                '3rd' => intval($g['contest_third_prize'])
            ]
        ];
    }
    echo json_encode(['success' => true, 'games' => $response]);
}
$conn->close();
?>