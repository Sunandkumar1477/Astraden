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

// Get current IST time
$now = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
$now_timestamp = $now->getTimestamp();

// First check for active time-restricted session
$time_restricted_stmt = $conn->prepare("
    SELECT * FROM game_sessions 
    WHERE game_name = ? 
    AND is_active = 1 
    AND always_available = 0
    ORDER BY created_at DESC 
    LIMIT 1
");
$time_restricted_stmt->bind_param("s", $game_name);
$time_restricted_stmt->execute();
$time_restricted_result = $time_restricted_stmt->get_result();

$session = null;
$is_always_available = false;

if ($time_restricted_result->num_rows > 0) {
    $time_restricted_session = $time_restricted_result->fetch_assoc();
    $session_datetime_str = $time_restricted_session['session_date'] . ' ' . $time_restricted_session['session_time'];
    $session_start_dt = new DateTime($session_datetime_str, new DateTimeZone('Asia/Kolkata'));
    $session_start = $session_start_dt->getTimestamp();
    $session_end = $session_start + ($time_restricted_session['duration_minutes'] * 60);
    
    // Check if time-restricted session is currently active
    if ($now_timestamp >= $session_start && $now_timestamp <= $session_end) {
        // Time-restricted session is active - use it
        $session = $time_restricted_session;
        $is_always_available = false;
    }
}
$time_restricted_stmt->close();

// If no active time-restricted session, check for always-available session
if (!$session) {
    $always_available_stmt = $conn->prepare("
        SELECT * FROM game_sessions 
        WHERE game_name = ? 
        AND is_active = 1 
        AND always_available = 1
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $always_available_stmt->bind_param("s", $game_name);
    $always_available_stmt->execute();
    $always_available_result = $always_available_stmt->get_result();
    
    if ($always_available_result->num_rows > 0) {
        $session = $always_available_result->fetch_assoc();
        $is_always_available = true;
    }
    $always_available_stmt->close();
}

if ($session) {
    if ($is_always_available) {
        // Always available mode - no time/date restrictions
        echo json_encode([
            'success' => true,
            'has_session' => true,
            'timing' => [
                'always_available' => true,
                'credits_required' => isset($session['credits_required']) ? $session['credits_required'] : 30
            ]
        ]);
    } else {
        // Time-restricted mode - calculate time/date information
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
                'always_available' => false,
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
    }
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

