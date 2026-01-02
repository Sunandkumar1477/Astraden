<?php
session_start();
require_once 'admin_check.php';
require_once 'connection.php';

$message = '';
$error = '';

// Check for success message from redirect
if (isset($_GET['msg']) && $_GET['msg'] === 'created') {
    $message = "Bidding item created successfully!";
}

// Create bidding tables if they don't exist
try {
    // Create bidding_settings table
    $conn->query("CREATE TABLE IF NOT EXISTS `bidding_settings` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `is_active` TINYINT(1) NOT NULL DEFAULT 0,
        `astrons_per_credit` DECIMAL(10, 2) NOT NULL DEFAULT 1.00,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Create bidding_items table
    $conn->query("CREATE TABLE IF NOT EXISTS `bidding_items` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `title` VARCHAR(255) NOT NULL,
        `description` TEXT,
        `prize_amount` DECIMAL(10, 2) NOT NULL,
        `starting_price` DECIMAL(10, 2) NOT NULL,
        `current_bid` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
        `current_bidder_id` INT(11) DEFAULT NULL,
        `bid_increment` DECIMAL(10, 2) NOT NULL DEFAULT 1.00,
        `start_time` DATETIME NOT NULL COMMENT 'Bidding start date and time',
        `end_time` DATETIME NOT NULL COMMENT 'Bidding end date and time',
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `is_completed` TINYINT(1) NOT NULL DEFAULT 0,
        `winner_id` INT(11) DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Add start_time column if it doesn't exist (for existing tables)
    try {
        $conn->query("ALTER TABLE `bidding_items` ADD COLUMN `start_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Bidding start date and time' AFTER `bid_increment`");
    } catch (Exception $e) {
        // Column might already exist, that's okay
    }
    
    // Create bidding_history table
    $conn->query("CREATE TABLE IF NOT EXISTS `bidding_history` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `bidding_item_id` INT(11) NOT NULL,
        `user_id` INT(11) NOT NULL,
        `bid_amount` DECIMAL(10, 2) NOT NULL,
        `bid_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_bidding_item_id` (`bidding_item_id`),
        KEY `idx_user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Create user_astrons table
    $conn->query("CREATE TABLE IF NOT EXISTS `user_astrons` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) NOT NULL,
        `astrons_balance` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Create astrons_purchases table
    $conn->query("CREATE TABLE IF NOT EXISTS `astrons_purchases` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) NOT NULL,
        `credits_used` INT(11) NOT NULL,
        `astrons_received` DECIMAL(10, 2) NOT NULL,
        `purchase_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Create user_wins table
    $conn->query("CREATE TABLE IF NOT EXISTS `user_wins` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) NOT NULL,
        `bidding_item_id` INT(11) NOT NULL,
        `win_amount` DECIMAL(10, 2) NOT NULL,
        `bid_amount` DECIMAL(10, 2) NOT NULL,
        `win_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `is_claimed` TINYINT(1) NOT NULL DEFAULT 0,
        `claimed_at` TIMESTAMP NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_bidding_item_id` (`bidding_item_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {
    // Tables might already exist
}

$bidding_settings = $conn->query("SELECT * FROM bidding_settings LIMIT 1")->fetch_assoc();
if (!$bidding_settings) {
    // Initialize default settings (1 credit per astron = 1 astrons per credit)
    $conn->query("INSERT INTO bidding_settings (is_active, astrons_per_credit) VALUES (0, 1.00)");
    $bidding_settings = ['is_active' => 0, 'astrons_per_credit' => 1.00];
}
// Calculate credits_per_astron for display (inverse of astrons_per_credit)
if (isset($bidding_settings['astrons_per_credit']) && $bidding_settings['astrons_per_credit'] > 0) {
    $bidding_settings['credits_per_astron'] = 1.0 / floatval($bidding_settings['astrons_per_credit']);
} else {
    $bidding_settings['credits_per_astron'] = 1.00;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update bidding settings
    if (isset($_POST['update_settings'])) {
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        // Admin enters "credits per astron" (e.g., 2 means 2 credits for 1 astron)
        // We convert to astrons_per_credit (e.g., 2 credits per astron = 0.5 astrons per credit)
        $credits_per_astron = floatval($_POST['credits_per_astron'] ?? 1.00);
        
        if ($credits_per_astron <= 0) {
            $error = "Credits per Astron must be greater than 0.";
        } else {
            // Convert: if 2 credits per astron, then 1 credit = 0.5 astrons
            $astrons_per_credit = 1.0 / $credits_per_astron;
            $stmt = $conn->prepare("UPDATE bidding_settings SET is_active = ?, astrons_per_credit = ? WHERE id = 1");
            $stmt->bind_param("id", $is_active, $astrons_per_credit);
            if ($stmt->execute()) {
                // Also update system_settings to show bidding in index page
                $show_bidding_stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('show_bidding', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $show_bidding_stmt->bind_param("ss", $is_active, $is_active);
                $show_bidding_stmt->execute();
                $show_bidding_stmt->close();
                
                $message = "Bidding settings updated successfully!";
                $bidding_settings['is_active'] = $is_active;
                $bidding_settings['astrons_per_credit'] = $astrons_per_credit;
                // Store credits_per_astron for display
                $bidding_settings['credits_per_astron'] = $credits_per_astron;
                $conn->query("INSERT INTO admin_logs (admin_id, admin_username, action, description, ip_address) VALUES ({$_SESSION['admin_id']}, '{$_SESSION['admin_username']}', 'update_bidding_settings', 'Updated bidding settings', '{$_SERVER['REMOTE_ADDR']}')");
            } else {
                $error = "Failed to update settings.";
            }
            $stmt->close();
        }
    }
    
    // Create new bidding item
    if (isset($_POST['create_bidding'])) {
        $prize_amount = floatval($_POST['prize_amount'] ?? 0);
        $bid_amount_per_bid = floatval($_POST['bid_amount_per_bid'] ?? 0.20);
        $start_date = trim($_POST['start_date'] ?? '');
        $start_time = trim($_POST['start_time'] ?? '');
        $end_date = trim($_POST['end_date'] ?? '');
        $end_time = trim($_POST['end_time'] ?? '');
        
        // Validate all required fields with specific error messages
        $validation_errors = [];
        if (empty($start_date)) {
            $validation_errors[] = "Start date is required";
        }
        if (empty($start_time)) {
            $validation_errors[] = "Start time is required";
        }
        if (empty($end_date)) {
            $validation_errors[] = "End date is required";
        }
        if (empty($end_time)) {
            $validation_errors[] = "End time is required";
        }
        if ($prize_amount <= 0) {
            $validation_errors[] = "Prize amount must be greater than 0";
        }
        if ($bid_amount_per_bid <= 0) {
            $validation_errors[] = "Bid amount per bid must be greater than 0";
        }
        
        if (!empty($validation_errors)) {
            $error = "Please fix the following errors: " . implode(", ", $validation_errors);
        } else {
            // Auto-generate title
            $title = "Bidding Item - ₹" . number_format($prize_amount, 2);
            $description = "Prize: ₹" . number_format($prize_amount, 2) . " | Bid Amount: " . $bid_amount_per_bid . " Astrons per bid";
            
            // Starting price is 0, bid increment is the bid amount per bid
            $starting_price = 0;
            $bid_increment = $bid_amount_per_bid;
            
            // Combine date and time
            $start_datetime = $start_date . ' ' . $start_time;
            $end_datetime = $end_date . ' ' . $end_time;
            
            // Validate that end time is after start time
            if (strtotime($end_datetime) <= strtotime($start_datetime)) {
                $error = "End date/time must be after start date/time.";
            } else {
                $stmt = $conn->prepare("INSERT INTO bidding_items (title, description, prize_amount, starting_price, current_bid, bid_increment, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                if (!$stmt) {
                    $error = "Database prepare error: " . $conn->error;
                } else {
                    $stmt->bind_param("ssddddss", $title, $description, $prize_amount, $starting_price, $starting_price, $bid_increment, $start_datetime, $end_datetime);
                    if ($stmt->execute()) {
                        $message = "Bidding item created successfully!";
                        $conn->query("INSERT INTO admin_logs (admin_id, admin_username, action, description, ip_address) VALUES ({$_SESSION['admin_id']}, '{$_SESSION['admin_username']}', 'create_bidding_item', 'Created bidding item: $title', '{$_SERVER['REMOTE_ADDR']}')");
                        $stmt->close();
                        // Redirect to prevent form resubmission
                        header("Location: admin_bidding_management.php?msg=created");
                        exit;
                    } else {
                        $error = "Failed to create bidding item: " . $stmt->error;
                        $stmt->close();
                    }
                }
            }
        }
    }
    
    // Update bidding item
    if (isset($_POST['update_bidding'])) {
        $id = intval($_POST['bidding_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $prize_amount = floatval($_POST['prize_amount'] ?? 0);
        $starting_price = floatval($_POST['starting_price'] ?? 0);
        $bid_increment = floatval($_POST['bid_increment'] ?? 1.00);
        $start_time = trim($_POST['start_date'] ?? '') . ' ' . trim($_POST['start_time'] ?? '');
        $end_time = trim($_POST['end_date'] ?? '') . ' ' . trim($_POST['end_time'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if ($id <= 0 || empty($title) || $prize_amount <= 0 || $starting_price <= 0 || empty($start_time) || empty($end_time)) {
            $error = "Please fill all required fields with valid values.";
        } else {
            $stmt = $conn->prepare("UPDATE bidding_items SET title = ?, description = ?, prize_amount = ?, starting_price = ?, bid_increment = ?, start_time = ?, end_time = ?, is_active = ? WHERE id = ?");
            $stmt->bind_param("ssddddsssi", $title, $description, $prize_amount, $starting_price, $bid_increment, $start_time, $end_time, $is_active, $id);
            if ($stmt->execute()) {
                $message = "Bidding item updated successfully!";
                $conn->query("INSERT INTO admin_logs (admin_id, admin_username, action, description, ip_address) VALUES ({$_SESSION['admin_id']}, '{$_SESSION['admin_username']}', 'update_bidding_item', 'Updated bidding item ID: $id', '{$_SERVER['REMOTE_ADDR']}')");
            } else {
                $error = "Failed to update bidding item.";
            }
            $stmt->close();
        }
    }
    
    // Complete bidding (mark as completed and set winner)
    if (isset($_POST['complete_bidding'])) {
        $id = intval($_POST['bidding_id'] ?? 0);
        if ($id > 0) {
            $item = $conn->query("SELECT * FROM bidding_items WHERE id = $id")->fetch_assoc();
            if ($item && $item['current_bidder_id']) {
                $stmt = $conn->prepare("UPDATE bidding_items SET is_completed = 1, winner_id = ? WHERE id = ?");
                $stmt->bind_param("ii", $item['current_bidder_id'], $id);
                if ($stmt->execute()) {
                    // Add to user_wins
                    $win_stmt = $conn->prepare("INSERT INTO user_wins (user_id, bidding_item_id, win_amount, bid_amount) VALUES (?, ?, ?, ?)");
                    $win_stmt->bind_param("iidd", $item['current_bidder_id'], $id, $item['prize_amount'], $item['current_bid']);
                    $win_stmt->execute();
                    $win_stmt->close();
                    
                    $message = "Bidding completed! Winner has been set.";
                    $conn->query("INSERT INTO admin_logs (admin_id, admin_username, action, description, ip_address) VALUES ({$_SESSION['admin_id']}, '{$_SESSION['admin_username']}', 'complete_bidding', 'Completed bidding item ID: $id', '{$_SERVER['REMOTE_ADDR']}')");
                }
                $stmt->close();
            } else {
                $error = "No bids placed on this item yet.";
            }
        }
    }
}

// Get all bidding items - handle if table doesn't exist
$bidding_items = [];
try {
    $bidding_items = $conn->query("SELECT bi.*, 
        (SELECT username FROM user_profile WHERE user_id = bi.current_bidder_id) as current_bidder_name,
        (SELECT COUNT(*) FROM bidding_history WHERE bidding_item_id = bi.id) as total_bids
        FROM bidding_items bi 
        ORDER BY bi.created_at DESC")->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    // Table might not exist yet - that's okay, will be empty array
    $bidding_items = [];
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bidding Management - Astraden Admin</title>
    <link rel="icon" type="image/svg+xml" href="Alogo.svg">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-cyan: #00ffff;
            --primary-purple: #9d4edd;
            --sidebar-width: 280px;
            --dark-bg: #05050a;
            --card-bg: rgba(15, 15, 25, 0.95);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Rajdhani', sans-serif; background: var(--dark-bg); color: white; min-height: 100vh; display: flex; }
        .space-bg { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: radial-gradient(circle at 10% 20%, #1a1a2e 0%, #05050a 100%); z-index: -1; }
        .sidebar { width: var(--sidebar-width); background: var(--card-bg); border-right: 1px solid rgba(0, 255, 255, 0.2); height: 100vh; position: fixed; left: 0; top: 0; display: flex; flex-direction: column; z-index: 1001; }
        .sidebar-header { padding: 30px 20px; text-align: center; border-bottom: 1px solid rgba(0, 255, 255, 0.1); }
        .sidebar-header h1 { font-family: 'Orbitron', sans-serif; font-size: 1.4rem; color: var(--primary-cyan); text-transform: uppercase; }
        .sidebar-menu { flex: 1; overflow-y: auto; padding: 20px 0; }
        .menu-category { padding: 15px 25px 10px; font-family: 'Orbitron', sans-serif; font-size: 0.7rem; color: rgba(255,255,255,0.4); text-transform: uppercase; letter-spacing: 2px; font-weight: 900; }
        .menu-item { padding: 12px 25px; display: flex; align-items: center; gap: 15px; text-decoration: none; color: rgba(255, 255, 255, 0.7); font-weight: 500; transition: 0.3s; border-left: 3px solid transparent; }
        .menu-item i { width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; border-radius: 6px; background: rgba(255,255,255,0.05); }
        .menu-item:hover, .menu-item.active { background: rgba(255, 255, 255, 0.05); color: white; border-left-color: var(--primary-cyan); }
        .sidebar-footer { padding: 20px; border-top: 1px solid rgba(0, 255, 255, 0.1); }
        .logout-btn { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 12px; background: rgba(255, 0, 110, 0.1); border: 1px solid #ff006e; color: #ff006e; text-decoration: none; border-radius: 8px; font-family: 'Orbitron', sans-serif; font-size: 0.8rem; font-weight: 700; }
        .main-content { margin-left: var(--sidebar-width); flex: 1; padding: 40px; }
        .section-title { font-family: 'Orbitron', sans-serif; color: var(--primary-cyan); margin-bottom: 30px; letter-spacing: 3px; }
        .config-card { background: var(--card-bg); border: 1px solid rgba(0, 255, 255, 0.2); border-radius: 20px; padding: 35px; margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; color: var(--primary-purple); font-weight: 700; font-size: 0.8rem; margin-bottom: 8px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; background: rgba(0,0,0,0.5); border: 1px solid rgba(0,255,255,0.3); border-radius: 8px; color: white; outline: none; }
        .form-group textarea { min-height: 100px; resize: vertical; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .btn-save, .btn-create { background: linear-gradient(135deg, var(--primary-cyan), var(--primary-purple)); border: none; color: white; padding: 15px; border-radius: 10px; font-family: 'Orbitron', sans-serif; font-weight: 900; width: 100%; cursor: pointer; }
        .btn-complete { background: linear-gradient(135deg, #00ff00, #00cc00); border: none; color: white; padding: 10px 20px; border-radius: 8px; font-family: 'Orbitron', sans-serif; font-weight: 700; cursor: pointer; }
        .toggle-group { display: flex; align-items: center; gap: 20px; margin-bottom: 20px; }
        .switch { position: relative; display: inline-block; width: 60px; height: 34px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 26px; width: 26px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: var(--primary-cyan); }
        input:checked + .slider:before { transform: translateX(26px); }
        .table-card { background: var(--card-bg); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 15px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
        th { color: var(--primary-purple); font-family: 'Orbitron', sans-serif; font-size: 0.7rem; text-transform: uppercase; background: rgba(0,0,0,0.2); }
        .msg { padding: 15px; border-radius: 10px; margin-bottom: 25px; background: rgba(0, 255, 204, 0.1); border: 1px solid #00ffcc; color: #00ffcc; font-weight: bold; }
        .error-msg { padding: 15px; border-radius: 10px; margin-bottom: 25px; background: rgba(255, 51, 51, 0.1); border: 1px solid #ff3333; color: #ff3333; font-weight: bold; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; }
        .badge-active { background: #00ff00; color: #000; }
        .badge-inactive { background: #ff3333; color: #fff; }
        .badge-completed { background: #FFD700; color: #000; }
    </style>
</head>
<body>
    <div class="space-bg"></div>
    <nav class="sidebar">
        <div class="sidebar-header"><h1>Astraden</h1></div>
        <div class="sidebar-menu">
            <div class="menu-category">General</div>
            <a href="admin_dashboard.php" class="menu-item"><i class="fas fa-chart-line ic-overview"></i> <span>Overview</span></a>
            <div class="menu-category">User Control</div>
            <a href="admin_view_all_users.php" class="menu-item"><i class="fas fa-users ic-users"></i> <span>User Directory</span></a>
            <a href="admin_password_reset_requests.php" class="menu-item"><i class="fas fa-key ic-reset"></i> <span>Reset Requests</span></a>
            <div class="menu-category">Financials</div>
            <a href="admin_transaction_codes.php" class="menu-item"><i class="fas fa-qrcode ic-verify"></i> <span>Verify Payments</span></a>
            <a href="admin_user_credits.php" class="menu-item"><i class="fas fa-coins ic-credits"></i> <span>Manual Credits</span></a>
            <a href="admin_credit_pricing.php" class="menu-item"><i class="fas fa-tags ic-pricing"></i> <span>Pricing Plans</span></a>
            <a href="admin_credit_timing.php" class="menu-item"><i class="fas fa-clock ic-timing"></i> <span>Purchase Timing</span></a>
            <a href="admin_credit_sale_limit.php" class="menu-item"><i class="fas fa-gauge-high ic-limits"></i> <span>Sale Limits</span></a>
            <div class="menu-category">Game Operations</div>
            <a href="admin_game_timing.php" class="menu-item"><i class="fas fa-calendar-check ic-sessions"></i> <span>Game Sessions</span></a>
            <a href="admin_game_credits.php" class="menu-item"><i class="fas fa-gamepad ic-costs"></i> <span>Play Costs</span></a>
            <a href="admin_game_leaderboard.php" class="menu-item"><i class="fas fa-ranking-star ic-leaderboard"></i> <span>Leaderboards</span></a>
            <a href="admin_bidding_management.php" class="menu-item active"><i class="fas fa-gavel" style="color: #ff6b35; text-shadow: 0 0 10px #ff6b35;"></i> <span>Bidding Management</span></a>
        </div>
        <div class="sidebar-footer"><a href="admin_logout.php" class="logout-btn"><i class="fas fa-power-off"></i> <span>Authorization Exit</span></a></div>
    </nav>
    <main class="main-content">
        <h2 class="section-title"><i class="fas fa-gavel" style="margin-right:15px;"></i> BIDDING MANAGEMENT</h2>
        <?php if($message): ?><div class="msg"><?php echo $message; ?></div><?php endif; ?>
        <?php if($error): ?><div class="error-msg"><?php echo $error; ?></div><?php endif; ?>
        
        <!-- Settings -->
        <div class="config-card">
            <h3 style="color: var(--primary-cyan); margin-bottom: 20px;">Bidding System Settings</h3>
            <form method="POST">
                <div class="toggle-group">
                    <label class="switch">
                        <input type="checkbox" name="is_active" <?php echo $bidding_settings['is_active'] ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                    <div>
                        <strong style="display:block;color:var(--primary-cyan);">ENABLE BIDDING SYSTEM</strong>
                        <small style="color:rgba(255,255,255,0.4);">When enabled, users can access bidding features.</small>
                    </div>
                </div>
                <div class="form-group">
                    <label>CREDITS PER ASTRON</label>
                    <input type="number" name="credits_per_astron" value="<?php echo number_format($bidding_settings['credits_per_astron'], 2); ?>" step="0.01" min="0.01" required>
                    <small style="color:rgba(255,255,255,0.4);">How many Credits users need to pay for 1 Astron. Users can only bid with Astrons, which they must buy using their Credits.</small>
                </div>
                <button type="submit" name="update_settings" class="btn-save">SAVE SETTINGS</button>
            </form>
        </div>
        
        <!-- Create Bidding Item -->
        <div class="config-card">
            <h3 style="color: var(--primary-cyan); margin-bottom: 20px;">Create New Bidding Item</h3>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label>PRIZE AMOUNT (₹) *</label>
                        <input type="number" name="prize_amount" step="0.01" min="0.01" required placeholder="e.g., 1000.00">
                        <small style="color:rgba(255,255,255,0.4);">Prize money value in Indian Rupees</small>
                    </div>
                    <div class="form-group">
                        <label>BID AMOUNT PER BID (Astrons) *</label>
                        <input type="number" name="bid_amount_per_bid" value="0.20" step="0.01" min="0.01" required placeholder="e.g., 0.20">
                        <small style="color:rgba(255,255,255,0.4);">How many Astrons per bid (e.g., 0.20 means user with 1 Astron can bid 5 times)</small>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>START DATE *</label>
                        <input type="date" name="start_date" required>
                    </div>
                    <div class="form-group">
                        <label>START TIME *</label>
                        <input type="time" name="start_time" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>END DATE *</label>
                        <input type="date" name="end_date" required>
                    </div>
                    <div class="form-group">
                        <label>END TIME *</label>
                        <input type="time" name="end_time" required>
                    </div>
                </div>
                <button type="submit" name="create_bidding" class="btn-create">CREATE BIDDING ITEM</button>
            </form>
        </div>
        
        <!-- Bidding Items List -->
        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Prize (₹)</th>
                        <th>Bid Amount</th>
                        <th>Current Bid</th>
                        <th>Bidder</th>
                        <th>Total Bids</th>
                        <th>Start Time</th>
                        <th>End Time</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($bidding_items)): ?>
                        <tr><td colspan="10" style="text-align:center;padding:40px;color:rgba(255,255,255,0.5);">No bidding items yet.</td></tr>
                    <?php else: ?>
                        <?php foreach($bidding_items as $item): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($item['title']); ?></strong></td>
                            <td>₹<?php echo number_format($item['prize_amount'], 2); ?></td>
                            <td><?php echo number_format($item['bid_increment'], 2); ?> Astrons</td>
                            <td><?php echo number_format($item['current_bid'], 2); ?> Astrons</td>
                            <td><?php echo $item['current_bidder_name'] ? htmlspecialchars($item['current_bidder_name']) : 'None'; ?></td>
                            <td><?php echo $item['total_bids']; ?></td>
                            <td><?php echo isset($item['start_time']) && $item['start_time'] ? date('M d, Y H:i', strtotime($item['start_time'])) : 'N/A'; ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($item['end_time'])); ?></td>
                            <td>
                                <?php if($item['is_completed']): ?>
                                    <span class="badge badge-completed">COMPLETED</span>
                                <?php elseif($item['is_active']): ?>
                                    <span class="badge badge-active">ACTIVE</span>
                                <?php else: ?>
                                    <span class="badge badge-inactive">INACTIVE</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if(!$item['is_completed'] && strtotime($item['end_time']) < time()): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="bidding_id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" name="complete_bidding" class="btn-complete">Complete</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>

