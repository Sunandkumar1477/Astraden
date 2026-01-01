<?php
session_start();
require_once 'security_headers.php';
require_once 'check_user_session.php';
require_once 'connection.php';

header('Content-Type: application/json');

$user_id = intval($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$reward_id = intval($_POST['reward_id'] ?? 0);
if ($reward_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid reward ID']);
    exit;
}

// Start transaction
$conn->begin_transaction();

try {
    // Get reward details with lock
    $reward_stmt = $conn->prepare("SELECT * FROM rewards WHERE id = ? AND is_active = 1 FOR UPDATE");
    $reward_stmt->bind_param("i", $reward_id);
    $reward_stmt->execute();
    $reward_result = $reward_stmt->get_result();
    
    if ($reward_result->num_rows === 0) {
        throw new Exception('Reward not found or inactive');
    }
    
    $reward = $reward_result->fetch_assoc();
    $reward_stmt->close();
    
    // Check if already sold
    if ($reward['is_sold'] == 1) {
        throw new Exception('This reward/coupon has already been purchased');
    }
    
    // Check expiration
    if ($reward['expire_date'] && strtotime($reward['expire_date']) < time()) {
        throw new Exception('This coupon has expired');
    }
    
    // Get user credits
    $credits_stmt = $conn->prepare("SELECT credits FROM user_profile WHERE user_id = ? FOR UPDATE");
    $credits_stmt->bind_param("i", $user_id);
    $credits_stmt->execute();
    $credits_result = $credits_stmt->get_result();
    $user_profile = $credits_result->fetch_assoc();
    $user_credits = intval($user_profile['credits'] ?? 0);
    $credits_stmt->close();
    
    // Check if user has enough credits
    if ($user_credits < $reward['credits_cost']) {
        throw new Exception('Insufficient credits');
    }
    
    // Deduct credits
    $new_credits = $user_credits - $reward['credits_cost'];
    $update_credits_stmt = $conn->prepare("UPDATE user_profile SET credits = ? WHERE user_id = ?");
    $update_credits_stmt->bind_param("ii", $new_credits, $user_id);
    $update_credits_stmt->execute();
    $update_credits_stmt->close();
    
    // Mark reward as sold
    $update_reward_stmt = $conn->prepare("UPDATE rewards SET is_sold = 1, purchased_by = ?, purchased_at = NOW() WHERE id = ?");
    $update_reward_stmt->bind_param("ii", $user_id, $reward_id);
    $update_reward_stmt->execute();
    $update_reward_stmt->close();
    
    // If it's a coupon, save to user_coupon_purchases table
    if ($reward['gift_type'] === 'coupon' && !empty($reward['coupon_code'])) {
        $insert_coupon_stmt = $conn->prepare("INSERT INTO user_coupon_purchases (user_id, reward_id, coupon_code) VALUES (?, ?, ?)");
        $insert_coupon_stmt->bind_param("iis", $user_id, $reward_id, $reward['coupon_code']);
        $insert_coupon_stmt->execute();
        $insert_coupon_stmt->close();
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Reward purchased successfully!',
        'remaining_credits' => $new_credits,
        'coupon_code' => ($reward['gift_type'] === 'coupon' ? $reward['coupon_code'] : null)
    ]);
    
} catch (Exception $e) {
    // Rollback transaction
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>

