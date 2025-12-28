<?php
session_start();
require_once 'connection.php';

header('Content-Type: application/json');

$game_name = $_GET['game'] ?? null;

// Check if games table exists
$check_table = $conn->query("SHOW TABLES LIKE 'games'");
if ($check_table && $check_table->num_rows == 0) {
    if ($check_table) $check_table->close();
    echo json_encode(['success' => true, 'game_name' => $game_name, 'credits_per_chance' => 30]);
    exit;
}
if ($check_table) $check_table->close();

// Columns to fetch
$cols = "game_name, credits_per_chance, is_active, is_contest_active, game_mode, contest_credits_required, contest_first_prize, contest_second_prize, contest_third_prize, first_prize, second_prize, third_prize, contest_start_datetime, contest_end_datetime, disable_normal_play";

if ($game_name) {
    try {
        $stmt = $conn->prepare("SELECT $cols FROM games WHERE game_name = ?");
        if ($stmt) {
            $stmt->bind_param("s", $game_name);
            $stmt->execute();
            $game = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        } else {
            $game = null;
        }
    } catch (Exception $e) {
        $game = null;
    }
    
    if ($game) {
        $is_contest = intval($game['is_contest_active'] ?? 0);
        $normal_cost = intval($game['credits_per_chance'] ?? 30);
        $contest_cost = intval($game['contest_credits_required'] ?? 30);
        $final_credits = $is_contest ? $contest_cost : $normal_cost;
        $disable_normal_play = intval($game['disable_normal_play'] ?? 0);
        $contest_start_datetime = $game['contest_start_datetime'] ?? null;
        $contest_end_datetime = $game['contest_end_datetime'] ?? null;
        
        // Check if contest is within time range (only for contest play, not normal play)
        $is_contest_in_time_window = false;
        if ($is_contest && $contest_start_datetime && $contest_end_datetime) {
            $now = time();
            $start_ts = strtotime($contest_start_datetime);
            $end_ts = strtotime($contest_end_datetime);
            
            // Contest is only active if current time is within the range
            if ($now >= $start_ts && $now <= $end_ts) {
                $is_contest_in_time_window = true;
            }
        } else if ($is_contest) {
            // If contest is active but no timing set, consider it always active
            $is_contest_in_time_window = true;
        }
        
        echo json_encode([
            'success' => true,
            'game_name' => $game['game_name'],
            'credits_per_chance' => $final_credits,
            'normal_credits_required' => $normal_cost,
            'contest_credits_required' => $contest_cost,
            'is_contest_active' => $is_contest_in_time_window, // Only true if within time window
            'is_contest_enabled' => $is_contest, // Original contest setting (not time-dependent)
            'disable_normal_play' => $disable_normal_play,
            'game_mode' => $game['game_mode'] ?? 'credits',
            'contest_prizes' => [
                '1st' => intval($game['contest_first_prize'] ?? 0),
                '2nd' => intval($game['contest_second_prize'] ?? 0),
                '3rd' => intval($game['contest_third_prize'] ?? 0)
            ],
            'normal_prizes' => [
                '1st' => floatval($game['first_prize'] ?? 0),
                '2nd' => floatval($game['second_prize'] ?? 0),
                '3rd' => floatval($game['third_prize'] ?? 0)
            ]
        ]);
    } else {
        // Game not found - return default values instead of error
        echo json_encode([
            'success' => true,
            'game_name' => $game_name,
            'credits_per_chance' => 30,
            'normal_credits_required' => 30,
            'contest_credits_required' => 30,
            'is_contest_active' => 0,
            'disable_normal_play' => 0,
            'game_mode' => 'credits',
            'contest_prizes' => ['1st' => 0, '2nd' => 0, '3rd' => 0],
            'normal_prizes' => ['1st' => 0, '2nd' => 0, '3rd' => 0]
        ]);
    }
} else {
    $games = $conn->query("SELECT $cols FROM games WHERE is_active = 1")->fetch_all(MYSQLI_ASSOC);
    $response = [];
    foreach ($games as $g) {
        $is_contest = intval($g['is_contest_active'] ?? 0);
        $normal_cost = intval($g['credits_per_chance'] ?? 30);
        $contest_cost = intval($g['contest_credits_required'] ?? 30);
        $final_credits = $is_contest ? $contest_cost : $normal_cost;
        $disable_normal_play = intval($g['disable_normal_play'] ?? 0);
        $contest_start_datetime = $g['contest_start_datetime'] ?? null;
        $contest_end_datetime = $g['contest_end_datetime'] ?? null;
        
        // Check if contest is within time range (only for contest play, not normal play)
        $is_contest_in_time_window = false;
        if ($is_contest && $contest_start_datetime && $contest_end_datetime) {
            $now = time();
            $start_ts = strtotime($contest_start_datetime);
            $end_ts = strtotime($contest_end_datetime);
            
            // Contest is only active if current time is within the range
            if ($now >= $start_ts && $now <= $end_ts) {
                $is_contest_in_time_window = true;
            }
        } else if ($is_contest) {
            // If contest is active but no timing set, consider it always active
            $is_contest_in_time_window = true;
        }
        
        $response[$g['game_name']] = [
            'credits_per_chance' => $final_credits,
            'normal_credits_required' => $normal_cost,
            'contest_credits_required' => $contest_cost,
            'is_contest_active' => $is_contest_in_time_window, // Only true if within time window
            'is_contest_enabled' => $is_contest, // Original contest setting (not time-dependent)
            'disable_normal_play' => $disable_normal_play,
            'game_mode' => $g['game_mode'] ?? 'credits',
            'contest_prizes' => [
                '1st' => intval($g['contest_first_prize'] ?? 0),
                '2nd' => intval($g['contest_second_prize'] ?? 0),
                '3rd' => intval($g['contest_third_prize'] ?? 0)
            ]
        ];
    }
    echo json_encode(['success' => true, 'games' => $response]);
}
$conn->close();
?>
