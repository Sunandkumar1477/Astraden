<?php
// Set timezone to India (IST)
date_default_timezone_set('Asia/Kolkata');

header('Content-Type: application/json');
require_once 'connection.php';

$timing_type = $_GET['type'] ?? ''; // 'add_credits' or 'claim_credits'

if (!in_array($timing_type, ['add_credits', 'claim_credits'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid timing type'
    ]);
    exit;
}

// Check if table exists
$check_table = $conn->query("SHOW TABLES LIKE 'credit_timing'");
if ($check_table->num_rows == 0) {
    // No timing restrictions set
    echo json_encode([
        'success' => true,
        'is_active' => true,
        'message' => 'No timing restrictions'
    ]);
    exit;
}

// Get timing settings
$stmt = $conn->prepare("SELECT * FROM credit_timing WHERE timing_type = ?");
$stmt->bind_param("s", $timing_type);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    // No timing set, allow access
    echo json_encode([
        'success' => true,
        'is_active' => true,
        'message' => 'No timing restrictions set'
    ]);
    $stmt->close();
    exit;
}

$timing = $result->fetch_assoc();
$stmt->close();

// Check if enabled
if (!$timing['is_enabled']) {
    echo json_encode([
        'success' => true,
        'is_active' => false,
        'message' => 'This feature is currently disabled'
    ]);
    exit;
}

// Check timing
$now = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
$is_active = true;
$message = 'Available';
$time_remaining = null;
$time_until_start = null;
$time_until_end = null;

if ($timing['date_from'] && $timing['time_from'] && $timing['date_to'] && $timing['time_to']) {
    $from_dt = new DateTime($timing['date_from'] . ' ' . $timing['time_from'], new DateTimeZone('Asia/Kolkata'));
    $to_dt = new DateTime($timing['date_to'] . ' ' . $timing['time_to'], new DateTimeZone('Asia/Kolkata'));
    
    // Handle case where end time is next day
    if ($to_dt < $from_dt) {
        $to_dt->modify('+1 day');
    }
    
    $is_active = ($now >= $from_dt && $now <= $to_dt);
    
    if ($is_active) {
        // Currently active - show time remaining until end
        $diff = $now->diff($to_dt);
        $total_seconds = ($diff->days * 86400) + ($diff->h * 3600) + ($diff->i * 60) + $diff->s;
        $time_remaining = $total_seconds;
        $time_until_end = $total_seconds;
        
        if ($diff->days > 0 || $diff->h > 0) {
            $hours = ($diff->days * 24) + $diff->h;
            $message = "Available for {$hours} hr";
        } else if ($diff->i > 0) {
            $message = "Available for {$diff->i} min {$diff->s} sec";
        } else {
            $message = "Available for {$diff->s} sec";
        }
    } else {
        if ($now < $from_dt) {
            // Not started yet - show time until start
            $diff = $now->diff($from_dt);
            $total_seconds = ($diff->days * 86400) + ($diff->h * 3600) + ($diff->i * 60) + $diff->s;
            $time_until_start = $total_seconds;
            
            if ($diff->days > 0 || $diff->h > 0) {
                $hours = ($diff->days * 24) + $diff->h;
                $message = "Starts in {$hours} hr";
            } else if ($diff->i > 0) {
                $message = "Starts in {$diff->i} min {$diff->s} sec";
            } else {
                $message = "Starts in {$diff->s} sec";
            }
        } else {
            // Already ended
            $message = "Ended";
        }
    }
}

$conn->close();

echo json_encode([
    'success' => true,
    'is_active' => $is_active,
    'message' => $message,
    'time_remaining' => $time_remaining,
    'time_until_start' => $time_until_start,
    'time_until_end' => $time_until_end,
    'from' => $timing['date_from'] && $timing['time_from'] ? $timing['date_from'] . ' ' . $timing['time_from'] : null,
    'to' => $timing['date_to'] && $timing['time_to'] ? $timing['date_to'] . ' ' . $timing['time_to'] : null
]);



