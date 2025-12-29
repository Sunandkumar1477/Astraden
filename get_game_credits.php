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
$cols = "game_name, credits_per_chance, is_active, is_contest_active, game_mode, play_type, normal_play_credits, contest_credits_required, contest_first_prize, contest_second_prize, contest_third_prize, first_prize, second_prize, third_prize";

if ($game_name) {
    $stmt = $conn->prepare("SELECT $cols FROM games WHERE game_name = ?");
    $stmt->bind_param("s", $game_name);
    $stmt->execute();
    $game = $stmt->get_result()->fetch_assoc();
    
    if ($game) {
        $play_type = $game['play_type'] ?: 'normal';
        $is_contest = intval($game['is_contest_active']);
        
        // Determine credits based on play type
        if ($play_type === 'normal') {
            $final_credits = intval($game['normal_play_credits'] ?: $game['credits_per_chance'] ?: 30);
        } else {
            $final_credits = intval($game['contest_credits_required'] ?: $game['credits_per_chance'] ?: 30);
        }
        
        echo json_encode([
            'success' => true,
            'game_name' => $game['game_name'],
            'credits_per_chance' => $final_credits,
            'play_type' => $play_type,
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
        $play_type = $g['play_type'] ?: 'normal';
        $is_contest = intval($g['is_contest_active']);
        
        // Determine credits based on play type
        if ($play_type === 'normal') {
            $final_credits = intval($g['normal_play_credits'] ?: $g['credits_per_chance'] ?: 30);
        } else {
            $final_credits = intval($g['contest_credits_required'] ?: $g['credits_per_chance'] ?: 30);
        }
        
        $response[$g['game_name']] = [
            'credits_per_chance' => $final_credits,
            'play_type' => $play_type,
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