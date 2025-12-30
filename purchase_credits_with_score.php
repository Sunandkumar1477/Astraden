<?php
session_start();
require_once 'check_user_session.php';
require_once 'connection.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$game_name = $_POST['game_name'] ?? 'all';
$credits_amount = intval($_POST['credits_amount'] ?? 0);

if ($credits_amount <= 0) {
    header('Location: shop.php?error=' . urlencode('Invalid credits amount'));
    exit;
}

// Get conversion rate
$rate_stmt = $conn->prepare("SELECT score_per_credit FROM score_shop_settings WHERE game_name = ? AND is_active = 1");
$rate_stmt->bind_param("s", $game_name);
$rate_stmt->execute();
$rate_result = $rate_stmt->get_result();

if ($rate_result->num_rows == 0) {
    // Fallback to 'all' rate
    $rate_stmt->close();
    $rate_stmt = $conn->prepare("SELECT score_per_credit FROM score_shop_settings WHERE game_name = 'all' AND is_active = 1");
    $rate_stmt->execute();
    $rate_result = $rate_stmt->get_result();
}

if ($rate_result->num_rows == 0) {
    $rate_stmt->close();
    header('Location: shop.php?error=' . urlencode('Conversion rate not set. Please contact admin.'));
    exit;
}

$rate_data = $rate_result->fetch_assoc();
$score_per_credit = intval($rate_data['score_per_credit']);
$rate_stmt->close();

$required_score = $credits_amount * $score_per_credit;

// Get user's available score
$user_profile = $conn->query("SELECT available_score, credits FROM user_profile WHERE user_id = $user_id")->fetch_assoc();
$available_score = intval($user_profile['available_score'] ?? 0);

// Calculate actual available score from game_leaderboard
$total_score_query = $conn->query("
    SELECT SUM(score) as total_score 
    FROM game_leaderboard 
    WHERE user_id = $user_id AND credits_used > 0
");
$total_score_data = $total_score_query->fetch_assoc();
$actual_total_score = intval($total_score_data['total_score'] ?? 0);

// Use the actual score from leaderboard (available_score might be out of sync)
$user_score = $actual_total_score;

if ($game_name !== 'all') {
    // Get score for specific game
    $game_score_query = $conn->query("
        SELECT SUM(score) as total_score 
        FROM game_leaderboard 
        WHERE user_id = $user_id AND game_name = '$game_name' AND credits_used > 0
    ");
    $game_score_data = $game_score_query->fetch_assoc();
    $user_score = intval($game_score_data['total_score'] ?? 0);
}

if ($required_score > $user_score) {
    header('Location: shop.php?error=' . urlencode('Insufficient score! You need ' . number_format($required_score) . ' score but only have ' . number_format($user_score) . '.'));
    exit;
}

// Start transaction
$conn->begin_transaction();

try {
    // Deduct score from game_leaderboard (deduct from latest entries first)
    if ($game_name === 'all') {
        // Deduct from all games - get entries ordered by date
        $entries = $conn->query("
            SELECT id, score 
            FROM game_leaderboard 
            WHERE user_id = $user_id AND credits_used > 0 AND score > 0
            ORDER BY played_at DESC
        ")->fetch_all(MYSQLI_ASSOC);
        
        $remaining_to_deduct = $required_score;
        foreach ($entries as $entry) {
            if ($remaining_to_deduct <= 0) break;
            
            $entry_score = intval($entry['score']);
            $deduct_amount = min($entry_score, $remaining_to_deduct);
            
            $deduct_stmt = $conn->prepare("UPDATE game_leaderboard SET score = score - ? WHERE id = ?");
            $deduct_stmt->bind_param("ii", $deduct_amount, $entry['id']);
            $deduct_stmt->execute();
            $deduct_stmt->close();
            
            $remaining_to_deduct -= $deduct_amount;
        }
    } else {
        // Deduct from specific game
        $entries = $conn->query("
            SELECT id, score 
            FROM game_leaderboard 
            WHERE user_id = $user_id AND game_name = '$game_name' AND credits_used > 0 AND score > 0
            ORDER BY played_at DESC
        ")->fetch_all(MYSQLI_ASSOC);
        
        $remaining_to_deduct = $required_score;
        foreach ($entries as $entry) {
            if ($remaining_to_deduct <= 0) break;
            
            $entry_score = intval($entry['score']);
            $deduct_amount = min($entry_score, $remaining_to_deduct);
            
            $deduct_stmt = $conn->prepare("UPDATE game_leaderboard SET score = score - ? WHERE id = ?");
            $deduct_stmt->bind_param("ii", $deduct_amount, $entry['id']);
            $deduct_stmt->execute();
            $deduct_stmt->close();
            
            $remaining_to_deduct -= $deduct_amount;
        }
    }
    
    // Update available_score in user_profile to match actual score
    $conn->query("UPDATE user_profile SET available_score = GREATEST(0, available_score - $required_score) WHERE user_id = $user_id");
    
    // Add credits to user
    $conn->query("UPDATE user_profile SET credits = credits + $credits_amount WHERE user_id = $user_id");
    
    // Record purchase
    $purchase_stmt = $conn->prepare("INSERT INTO score_purchases (user_id, game_name, score_used, credits_received, conversion_rate) VALUES (?, ?, ?, ?, ?)");
    $purchase_stmt->bind_param("isiii", $user_id, $game_name, $required_score, $credits_amount, $score_per_credit);
    $purchase_stmt->execute();
    $purchase_stmt->close();
    
    $conn->commit();
    
    header('Location: shop.php?success=1');
    exit;
    
} catch (Exception $e) {
    $conn->rollback();
    header('Location: shop.php?error=' . urlencode('Purchase failed: ' . $e->getMessage()));
    exit;
}

$conn->close();
?>

