<?php
session_start();
require_once 'connection.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'get_active_biddings') {
    // Get active bidding items (between start_time and end_time)
    $items = $conn->query("SELECT bi.*, 
        COALESCE((SELECT u.username FROM users u WHERE u.id = bi.current_bidder_id LIMIT 1), '') as current_bidder_name,
        COALESCE((SELECT COUNT(*) FROM bidding_history WHERE bidding_item_id = bi.id), 0) as total_bids
        FROM bidding_items bi 
        WHERE bi.is_active = 1 AND bi.is_completed = 0 
        AND (bi.start_time IS NULL OR bi.start_time <= NOW()) 
        AND bi.end_time > NOW()
        ORDER BY bi.end_time ASC")->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode(['success' => true, 'items' => $items]);
    exit;
}

if ($action === 'get_bidding_details') {
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid bidding ID']);
        exit;
    }
    
    $item = $conn->query("SELECT bi.*, 
        COALESCE((SELECT u.username FROM users u WHERE u.id = bi.current_bidder_id LIMIT 1), '') as current_bidder_name,
        COALESCE((SELECT COUNT(*) FROM bidding_history WHERE bidding_item_id = bi.id), 0) as total_bids
        FROM bidding_items bi 
        WHERE bi.id = $id")->fetch_assoc();
    
    if (!$item) {
        echo json_encode(['success' => false, 'message' => 'Bidding item not found']);
        exit;
    }
    
    // Get recent bids
    $recent_bids = $conn->query("SELECT bh.*, up.username 
        FROM bidding_history bh 
        JOIN user_profile up ON bh.user_id = up.user_id 
        WHERE bh.bidding_item_id = $id 
        ORDER BY bh.bid_time DESC 
        LIMIT 10")->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode(['success' => true, 'item' => $item, 'recent_bids' => $recent_bids]);
    exit;
}

if ($action === 'place_bid') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Please login to place a bid']);
        exit;
    }
    
    $user_id = intval($_SESSION['user_id']);
    $bidding_id = intval($_POST['bidding_id'] ?? 0);
    $bid_amount = floatval($_POST['bid_amount'] ?? 0);
    
    if ($bidding_id <= 0 || $bid_amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid bid amount']);
        exit;
    }
    
    // Get bidding item
    $item = $conn->query("SELECT * FROM bidding_items WHERE id = $bidding_id")->fetch_assoc();
    $now = time();
    $start_time = $item['start_time'] ? strtotime($item['start_time']) : 0;
    $end_time = strtotime($item['end_time']);
    
    if (!$item || !$item['is_active'] || $item['is_completed'] || ($start_time > 0 && $now < $start_time) || $now >= $end_time) {
        echo json_encode(['success' => false, 'message' => 'Bidding is not active']);
        exit;
    }
    
    // Check minimum bid
    $min_bid = $item['current_bid'] + $item['bid_increment'];
    if ($bid_amount < $min_bid) {
        echo json_encode(['success' => false, 'message' => "Minimum bid is $min_bid Astrons"]);
        exit;
    }
    
    // Check user Astrons balance
    $user_astrons = $conn->query("SELECT astrons_balance FROM user_astrons WHERE user_id = $user_id")->fetch_assoc();
    $balance = $user_astrons ? floatval($user_astrons['astrons_balance']) : 0;
    
    if ($balance < $bid_amount) {
        echo json_encode(['success' => false, 'message' => 'Insufficient Astrons balance']);
        exit;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Deduct Astrons
        $conn->query("UPDATE user_astrons SET astrons_balance = astrons_balance - $bid_amount WHERE user_id = $user_id");
        
        // Return previous bidder's Astrons (if any)
        if ($item['current_bidder_id'] && $item['current_bid'] > 0) {
            $conn->query("UPDATE user_astrons SET astrons_balance = astrons_balance + {$item['current_bid']} WHERE user_id = {$item['current_bidder_id']}");
        }
        
        // Update bidding item
        $conn->query("UPDATE bidding_items SET current_bid = $bid_amount, current_bidder_id = $user_id WHERE id = $bidding_id");
        
        // Add to bidding history
        $stmt = $conn->prepare("INSERT INTO bidding_history (bidding_item_id, user_id, bid_amount) VALUES (?, ?, ?)");
        $stmt->bind_param("iid", $bidding_id, $user_id, $bid_amount);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'Bid placed successfully!']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to place bid']);
    }
    exit;
}

if ($action === 'get_user_astrons') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'balance' => 0]);
        exit;
    }
    
    $user_id = intval($_SESSION['user_id']);
    $user_astrons = $conn->query("SELECT astrons_balance FROM user_astrons WHERE user_id = $user_id")->fetch_assoc();
    $balance = $user_astrons ? floatval($user_astrons['astrons_balance']) : 0;
    
    echo json_encode(['success' => true, 'balance' => $balance]);
    exit;
}

if ($action === 'buy_astrons') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Please login']);
        exit;
    }
    
    $user_id = intval($_SESSION['user_id']);
    $credits = intval($_POST['credits'] ?? 0);
    
    if ($credits <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid credits amount']);
        exit;
    }
    
    // Get user credits
    $user_credits = $conn->query("SELECT credits FROM user_profile WHERE user_id = $user_id")->fetch_assoc();
    $balance = $user_credits ? intval($user_credits['credits']) : 0;
    
    if ($balance < $credits) {
        echo json_encode(['success' => false, 'message' => 'Insufficient credits']);
        exit;
    }
    
    // Get Astrons per credit
    $settings = $conn->query("SELECT astrons_per_credit FROM bidding_settings LIMIT 1")->fetch_assoc();
    $astrons_per_credit = $settings ? floatval($settings['astrons_per_credit']) : 1.00;
    $astrons_received = $credits * $astrons_per_credit;
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Deduct credits
        $conn->query("UPDATE user_profile SET credits = credits - $credits WHERE user_id = $user_id");
        
        // Add Astrons
        $conn->query("INSERT INTO user_astrons (user_id, astrons_balance) VALUES ($user_id, $astrons_received) 
            ON DUPLICATE KEY UPDATE astrons_balance = astrons_balance + $astrons_received");
        
        // Record purchase
        $stmt = $conn->prepare("INSERT INTO astrons_purchases (user_id, credits_used, astrons_received) VALUES (?, ?, ?)");
        $stmt->bind_param("iid", $user_id, $credits, $astrons_received);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Astrons purchased successfully']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to purchase Astrons']);
    }
    exit;
}

if ($action === 'claim_win') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Please login']);
        exit;
    }
    
    $user_id = intval($_SESSION['user_id']);
    $win_id = intval($_POST['win_id'] ?? 0);
    
    if ($win_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid win ID']);
        exit;
    }
    
    // Check if win belongs to user and not claimed
    $win = $conn->query("SELECT * FROM user_wins WHERE id = $win_id AND user_id = $user_id AND is_claimed = 0")->fetch_assoc();
    
    if (!$win) {
        echo json_encode(['success' => false, 'message' => 'Win not found or already claimed']);
        exit;
    }
    
    // Mark as claimed
    $conn->query("UPDATE user_wins SET is_claimed = 1, claimed_at = NOW() WHERE id = $win_id");
    
    echo json_encode(['success' => true, 'message' => 'Prize claimed successfully']);
    exit;
}

$conn->close();
?>

