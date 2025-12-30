<?php
session_start();
require_once 'check_user_session.php';
require_once 'connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: shop.php?error=' . urlencode('Invalid request method.'));
    exit;
}

$user_id = $_SESSION['user_id'];

// Get claim credits score setting
$claim_setting = $conn->query("SELECT claim_credits_score FROM score_shop_settings WHERE game_name = 'all' AND is_active = 1 LIMIT 1")->fetch_assoc();
$claim_credits_score = intval($claim_setting['claim_credits_score'] ?? 0);

if ($claim_credits_score <= 0) {
    header('Location: shop.php?error=' . urlencode('Claim credits feature is currently disabled.'));
    exit;
}

// Get user's total score
$total_score_query = $conn->query("
    SELECT SUM(score) as total_score 
    FROM game_leaderboard 
    WHERE user_id = $user_id AND credits_used > 0 AND score > 0
");
$total_score_data = $total_score_query->fetch_assoc();
$total_score = intval($total_score_data['total_score'] ?? 0);

if ($total_score < $claim_credits_score) {
    header('Location: shop.php?error=' . urlencode('Insufficient score! You need ' . number_format($claim_credits_score) . ' score but only have ' . number_format($total_score) . '.'));
    exit;
}

// Get user's current credits
$user_profile = $conn->query("SELECT credits, available_score FROM user_profile WHERE user_id = $user_id")->fetch_assoc();
$current_credits = intval($user_profile['credits'] ?? 0);
$available_score = intval($user_profile['available_score'] ?? 0);

// Start transaction
$conn->begin_transaction();

try {
    // Deduct score from available_score
    $deduct_score_stmt = $conn->prepare("UPDATE user_profile SET available_score = available_score - ? WHERE user_id = ?");
    $deduct_score_stmt->bind_param("ii", $claim_credits_score, $user_id);
    $deduct_score_stmt->execute();
    $deduct_score_stmt->close();

    // Add 1 credit to user_profile.credits
    $add_credits_stmt = $conn->prepare("UPDATE user_profile SET credits = credits + 1 WHERE user_id = ?");
    $add_credits_stmt->bind_param("i", $user_id);
    $add_credits_stmt->execute();
    $add_credits_stmt->close();

    // Log the claim (using score_purchases table)
    $log_stmt = $conn->prepare("INSERT INTO score_purchases (user_id, game_name, score_used, credits_gained) VALUES (?, 'all', ?, 1)");
    $log_stmt->bind_param("ii", $user_id, $claim_credits_score);
    $log_stmt->execute();
    $log_stmt->close();

    $conn->commit();
    header('Location: shop.php?success=' . urlencode('1 credit claimed successfully with ' . number_format($claim_credits_score) . ' score!'));
    exit;
} catch (Exception $e) {
    $conn->rollback();
    header('Location: shop.php?error=' . urlencode('Failed to claim credits: ' . $e->getMessage()));
    exit;
}

$conn->close();
?>

