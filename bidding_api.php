<?php
// Error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();

// Set timezone to India (IST) for all date/time operations
date_default_timezone_set('Asia/Kolkata');

// Set JSON header first
header('Content-Type: application/json');

// Handle connection errors gracefully
try {
    require_once 'connection.php';
    
    // Check if connection is valid
    if (!isset($conn) || !$conn || $conn->connect_error) {
        echo json_encode([
            'success' => false, 
            'message' => 'Database connection failed',
            'items' => []
        ]);
        exit;
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to connect to database: ' . $e->getMessage(),
        'items' => []
    ]);
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'get_active_biddings') {
    try {
        // Get active bidding items (between start_time and end_time)
        // First check if table exists
        $table_check = $conn->query("SHOW TABLES LIKE 'bidding_items'");
        if (!$table_check || $table_check->num_rows == 0) {
            echo json_encode(['success' => false, 'message' => 'Bidding items table does not exist', 'items' => []]);
            exit;
        }
        
        // Check if bidding system is enabled
        $bidding_enabled = false;
        try {
            $settings_check = $conn->query("SELECT is_active FROM bidding_settings LIMIT 1");
            if ($settings_check && $settings_check->num_rows > 0) {
                $settings = $settings_check->fetch_assoc();
                $bidding_enabled = (bool)$settings['is_active'];
            }
        } catch (Exception $e) {
            // Settings table might not exist, continue
        }
        
        // Debug: Check total items first
        $total_count = 0;
        try {
            $total_check = $conn->query("SELECT COUNT(*) as total FROM bidding_items");
            if ($total_check) {
                $total_data = $total_check->fetch_assoc();
                $total_count = intval($total_data['total'] ?? 0);
            }
        } catch (Exception $e) {
            // Continue even if count fails
        }
        
        // Get all items for debugging
        $all_items = [];
        try {
            $all_items_query = "SELECT id, title, is_active, is_completed, start_time, end_time, NOW() as current_time FROM bidding_items";
            $all_items_result = $conn->query($all_items_query);
            if ($all_items_result) {
                $all_items = $all_items_result->fetch_all(MYSQLI_ASSOC);
            }
        } catch (Exception $e) {
            // Continue even if debug query fails
        }
        
        // Query for active bidding items
        // Show ALL items where is_active = 1 (regardless of completion status, end_time, or start_time)
        // Items will remain visible until admin deletes them (sets is_active = 0)
        // Recent items (newest) show first - sorted by created_at DESC or id DESC as fallback
        $query = "SELECT bi.*, 
            COALESCE((SELECT u.username FROM users u WHERE u.id = bi.current_bidder_id LIMIT 1), '') as current_bidder_name,
            COALESCE((SELECT COUNT(*) FROM bidding_history WHERE bidding_item_id = bi.id), 0) as total_bids
            FROM bidding_items bi 
            WHERE bi.is_active = 1
            ORDER BY bi.id DESC";
        
        $result = $conn->query($query);
        
        if (!$result) {
            $error_msg = $conn->error ? $conn->error : 'Unknown database error';
            echo json_encode([
                'success' => false, 
                'message' => 'Database error: ' . $error_msg, 
                'items' => [], 
                'debug' => [
                    'query' => $query, 
                    'error' => $error_msg,
                    'errno' => $conn->errno
                ]
            ]);
            exit;
        }
        
        $items = [];
        if ($result && $result->num_rows > 0) {
            $raw_items = $result->fetch_all(MYSQLI_ASSOC);
            
            // Convert UTC times from database to IST for display
            foreach ($raw_items as $item) {
                // Convert start_time from UTC to IST
                if ($item['start_time']) {
                    $start_utc = new DateTime($item['start_time'], new DateTimeZone('UTC'));
                    $start_utc->setTimezone(new DateTimeZone('Asia/Kolkata'));
                    $item['start_time'] = $start_utc->format('Y-m-d H:i:s');
                }
                
                // Convert end_time from UTC to IST
                if ($item['end_time']) {
                    $end_utc = new DateTime($item['end_time'], new DateTimeZone('UTC'));
                    $end_utc->setTimezone(new DateTimeZone('Asia/Kolkata'));
                    $item['end_time'] = $end_utc->format('Y-m-d H:i:s');
                }
                
                $items[] = $item;
            }
        }
        
        // Get current time in IST
        $now_ist = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
        $current_time_ist = $now_ist->format('Y-m-d H:i:s');
        
        // Debug info (always include for troubleshooting)
        $debug_info = [
            'total_items' => $total_count,
            'active_items' => count($items),
            'bidding_enabled' => $bidding_enabled,
            'all_items' => $all_items,
            'current_time' => $current_time_ist,
            'items_returned' => count($items)
        ];
        
        // If bidding system is disabled, still return items but with a warning message
        // This allows debugging even when system is disabled
        if (!$bidding_enabled) {
            $debug_info['warning'] = 'Bidding system is disabled in settings';
        }
        
        // Always return success with items (even if empty) so frontend can display properly
        echo json_encode([
            'success' => true, 
            'items' => $items, 
            'debug' => $debug_info,
            'message' => count($items) > 0 ? 'Items loaded successfully' : 'No active bidding items found'
        ]);
        exit;
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false, 
            'message' => 'Server error: ' . $e->getMessage(), 
            'items' => [],
            'debug' => [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]
        ]);
        exit;
    }
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
    $posted_bid_amount = floatval($_POST['bid_amount'] ?? 0);
    
    if ($bidding_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid bidding item']);
        exit;
    }
    
    // Get bidding item
    $item = $conn->query("SELECT * FROM bidding_items WHERE id = $bidding_id")->fetch_assoc();
    
    if (!$item) {
        echo json_encode(['success' => false, 'message' => 'Bidding item not found']);
        exit;
    }
    
    // Use the fixed bid_increment amount (BID AMOUNT PER BID set by admin)
    $bid_amount = floatval($item['bid_increment']);
    
    // Verify the posted amount matches the fixed amount (security check)
    if ($posted_bid_amount > 0 && abs($posted_bid_amount - $bid_amount) > 0.01) {
        // Allow if close enough (rounding differences), but use the fixed amount
        $bid_amount = floatval($item['bid_increment']);
    }
    
    if ($bid_amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid bid amount per bid']);
        exit;
    }
    
    // Get current time in IST
    $now_ist = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
    
    // Convert database times (UTC) to IST for comparison
    $start_time_utc = $item['start_time'] ? new DateTime($item['start_time'], new DateTimeZone('UTC')) : null;
    $end_time_utc = new DateTime($item['end_time'], new DateTimeZone('UTC'));
    
    // Convert to IST
    if ($start_time_utc) {
        $start_time_utc->setTimezone(new DateTimeZone('Asia/Kolkata'));
    }
    $end_time_utc->setTimezone(new DateTimeZone('Asia/Kolkata'));
    
    if (!$item['is_active'] || $item['is_completed'] || $now_ist >= $end_time_utc) {
        echo json_encode(['success' => false, 'message' => 'Bidding is not active']);
        exit;
    }
    
    // Check if bidding has started (compare in IST)
    if ($start_time_utc && $now_ist < $start_time_utc) {
        $diff = $now_ist->diff($start_time_utc);
        $hours = $diff->h + ($diff->days * 24);
        $minutes = $diff->i;
        echo json_encode(['success' => false, 'message' => "Bidding has not started yet. It will start in {$hours}h {$minutes}m"]);
        exit;
    }
    
    // Calculate new bid amount (current bid + fixed increment)
    $new_bid_amount = $item['current_bid'] + $bid_amount;
    
    // Check user Astrons balance (need the fixed bid amount)
    $user_astrons = $conn->query("SELECT astrons_balance FROM user_astrons WHERE user_id = $user_id")->fetch_assoc();
    $balance = $user_astrons ? floatval($user_astrons['astrons_balance']) : 0;
    
    if ($balance < $bid_amount) {
        echo json_encode(['success' => false, 'message' => 'Insufficient Astrons balance. You need ' . number_format($bid_amount, 2) . ' Astrons to place a bid.']);
        exit;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Deduct Astrons (the fixed bid amount per click)
        $conn->query("UPDATE user_astrons SET astrons_balance = astrons_balance - $bid_amount WHERE user_id = $user_id");
        
        // Return previous bidder's Astrons (if any)
        if ($item['current_bidder_id'] && $item['current_bid'] > 0) {
            $conn->query("UPDATE user_astrons SET astrons_balance = astrons_balance + {$item['current_bid']} WHERE user_id = {$item['current_bidder_id']}");
        }
        
        // Update bidding item with new bid amount (current + increment)
        $conn->query("UPDATE bidding_items SET current_bid = $new_bid_amount, current_bidder_id = $user_id WHERE id = $bidding_id");
        
        // Add to bidding history (record the new total bid amount)
        $stmt = $conn->prepare("INSERT INTO bidding_history (bidding_item_id, user_id, bid_amount) VALUES (?, ?, ?)");
        $stmt->bind_param("iid", $bidding_id, $user_id, $new_bid_amount);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Bid placed successfully! ' . number_format($bid_amount, 2) . ' Astrons deducted.',
            'new_bid_amount' => $new_bid_amount
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to place bid: ' . $e->getMessage()]);
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

