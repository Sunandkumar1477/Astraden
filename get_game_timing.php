<?php
session_start();
require_once 'connection.php';

header('Content-Type: application/json');

// Set timezone to India (IST)
date_default_timezone_set('Asia/Kolkata');

$game_name = $_GET['game'] ?? 'earth-defender';

// Check if game_sessions table exists
$check_table = $conn->query("SHOW TABLES LIKE 'game_sessions'");
if ($check_table->num_rows == 0) {
    echo json_encode([
        'success' => true,
        'has_session' => false,
        'timing' => null
    ]);
    exit;
}

// Get active session for the game
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

if ($result->num_rows > 0) {
    $session = $result->fetch_assoc();
    
    // Calculate closing time
    $session_datetime_str = $session['session_date'] . ' ' . $session['session_time'];
    $session_start_dt = new DateTime($session_datetime_str, new DateTimeZone('Asia/Kolkata'));
    $session_end_dt = clone $session_start_dt;
    $session_end_dt->modify('+' . $session['duration_minutes'] . ' minutes');
    
    // Format duration in hours (if >= 1 hour) or minutes
    $duration_hours = floor($session['duration_minutes'] / 60);
    $duration_minutes_remainder = $session['duration_minutes'] % 60;
    
    $duration_display = '';
    if ($duration_hours > 0) {
        $duration_display = $duration_hours . ($duration_hours == 1 ? ' hr' : ' hrs');
        if ($duration_minutes_remainder > 0) {
            $duration_display .= ' ' . $duration_minutes_remainder . ' min';
        }
    } else {
        $duration_display = $session['duration_minutes'] . ' min';
    }
    
    // Format date (e.g., "15 jan 2024")
    $date_display = date('d M Y', strtotime($session['session_date']));
    
    // Format time (e.g., "10:00 am - 11:00 am")
    $start_time_display = date('h:i a', strtotime($session['session_time']));
    $end_time_display = date('h:i a', $session_end_dt->getTimestamp());
    
    // Calculate time until game starts (in seconds)
    $now = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
    $time_until_start = 0;
    $is_started = false;
    
    if ($session_start_dt > $now) {
        // Game hasn't started yet
        $diff = $now->diff($session_start_dt);
        $time_until_start = ($diff->days * 24 * 60 * 60) + ($diff->h * 60 * 60) + ($diff->i * 60) + $diff->s;
    } else {
        // Game has already started
        $is_started = true;
    }
    
    echo json_encode([
        'success' => true,
        'has_session' => true,
        'timing' => [
            'date' => strtolower($date_display),
            'time' => strtolower($start_time_display . ' - ' . $end_time_display),
            'duration' => strtolower($duration_display),
            'session_date' => $session['session_date'],
            'session_time' => $session['session_time'],
            'duration_minutes' => $session['duration_minutes'],
            'time_until_start_seconds' => $time_until_start,
            'is_started' => $is_started,
            'session_start_timestamp' => $session_start_dt->getTimestamp()
        ]
    ]);
} else {
    echo json_encode([
        'success' => true,
        'has_session' => false,
        'timing' => null
    ]);
}

$stmt->close();
$conn->close();
?>

