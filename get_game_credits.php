<?php
session_start();
require_once 'connection.php';

header('Content-Type: application/json');

$game_name = $_GET['game'] ?? null;

// Check if games table exists
$check_table = $conn->query("SHOW TABLES LIKE 'games'");
if ($check_table->num_rows == 0) {
    echo json_encode(['success' => true, 'game_name' => $game_name, 'credits_per_chance' => 30]);
    exit;
}

// Columns to fetch
$cols = "game_name, credits_per_chance, is_active, is_contest_active, game_mode, contest_credits_required, contest_first_prize, contest_second_prize, contest_third_prize, first_prize, second_prize, third_prize";

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
        
        echo json_encode([
            'success' => true,
            'game_name' => $game['game_name'],
            'credits_per_chance' => $final_credits,
            'normal_credits_required' => $normal_cost,
            'contest_credits_required' => $contest_cost,
            'is_contest_active' => $is_contest,
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
            'game_mode' => 'credits',
            'contest_prizes' => ['1st' => 0, '2nd' => 0, '3rd' => 0],
            'normal_prizes' => ['1st' => 0, '2nd' => 0, '3rd' => 0]
        ]);
    }
} else {
    $games = $conn->query("SELECT $cols FROM games WHERE is_active = 1")->fetch_all(MYSQLI_ASSOC);
    $response = [];
    foreach ($games as $g) {
        $is_contest = intval($g['is_contest_active']);
        $final_credits = $is_contest ? intval($g['contest_credits_required']) : intval($g['credits_per_chance']);
        
        $response[$g['game_name']] = [
            'credits_per_chance' => $final_credits,
            'is_contest_active' => $is_contest,
            'game_mode' => $g['game_mode'] ?: 'credits',
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
