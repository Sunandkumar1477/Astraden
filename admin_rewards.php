<?php
require_once 'admin_check.php';
require_once 'connection.php';

$message = '';
$error = '';

// Create rewards table if it doesn't exist
$create_table = $conn->query("
    CREATE TABLE IF NOT EXISTS `rewards` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `gift_name` VARCHAR(255) NOT NULL,
      `gift_type` VARCHAR(100) NOT NULL COMMENT 'reward or coupon',
      `credits_cost` INT(11) NOT NULL DEFAULT 0,
      `time_duration` VARCHAR(50) NULL COMMENT 'Duration for time-based rewards',
      `coupon_code` VARCHAR(100) NULL COMMENT 'Coupon code if type is coupon',
      `coupon_details` TEXT NULL COMMENT 'Coupon details and benefits',
      `about_coupon` TEXT NULL COMMENT 'About coupon code description',
      `expire_date` DATETIME NULL COMMENT 'Expiration date for coupons',
      `showcase_date` DATETIME NULL COMMENT 'Date when coupon appears on rewards page',
      `display_days` INT(11) DEFAULT 0 COMMENT 'Number of days to display before expiring',
      `is_sold` TINYINT(1) DEFAULT 0 COMMENT '1 if purchased, 0 if available',
      `purchased_by` INT(11) NULL COMMENT 'User ID who purchased',
      `purchased_at` DATETIME NULL COMMENT 'Purchase timestamp',
      `is_active` TINYINT(1) DEFAULT 1,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      INDEX idx_gift_type (`gift_type`),
      INDEX idx_is_sold (`is_sold`),
      INDEX idx_is_active (`is_active`),
      INDEX idx_expire_date (`expire_date`),
      INDEX idx_showcase_date (`showcase_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Add new columns if they don't exist
$conn->query("ALTER TABLE `rewards` ADD COLUMN IF NOT EXISTS `showcase_date` DATETIME NULL COMMENT 'Date when coupon appears on rewards page'");
$conn->query("ALTER TABLE `rewards` ADD COLUMN IF NOT EXISTS `display_days` INT(11) DEFAULT 0 COMMENT 'Number of days to display before expiring'");
$conn->query("ALTER TABLE `rewards` ADD COLUMN IF NOT EXISTS `about_coupon` TEXT NULL COMMENT 'About coupon code description'");
$conn->query("ALTER TABLE `rewards` ADD INDEX IF NOT EXISTS idx_showcase_date (`showcase_date`)");

// Create user_coupon_purchases table if it doesn't exist
$conn->query("
    CREATE TABLE IF NOT EXISTS `user_coupon_purchases` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `user_id` INT(11) NOT NULL,
      `reward_id` INT(11) NOT NULL,
      `coupon_code` VARCHAR(100) NOT NULL,
      `purchased_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      INDEX idx_user_id (`user_id`),
      INDEX idx_reward_id (`reward_id`),
      FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
      FOREIGN KEY (`reward_id`) REFERENCES `rewards`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_reward'])) {
        $gift_name = trim($_POST['gift_name'] ?? '');
        $gift_type = trim($_POST['gift_type'] ?? 'reward');
        $credits_cost = intval($_POST['credits_cost'] ?? 0);
        $time_duration = trim($_POST['time_duration'] ?? '');
        $coupon_code = trim($_POST['coupon_code'] ?? '');
        $coupon_details = trim($_POST['coupon_details'] ?? '');
        $about_coupon = trim($_POST['about_coupon'] ?? '');
        $expire_date = !empty($_POST['expire_date']) ? $_POST['expire_date'] : null;
        $showcase_date = !empty($_POST['showcase_date']) ? $_POST['showcase_date'] : null;
        $display_days = intval($_POST['display_days'] ?? 0);
        
        if (empty($gift_name)) {
            $error = "Gift name is required.";
        } elseif ($credits_cost < 0) {
            $error = "Credits cost cannot be negative.";
        } elseif ($gift_type === 'coupon' && empty($coupon_code)) {
            $error = "Coupon code is required for coupons.";
        } elseif ($gift_type === 'coupon' && empty($expire_date)) {
            $error = "Expire date is required for coupons.";
        } elseif ($gift_type === 'coupon' && $display_days <= 0) {
            $error = "Display days must be greater than 0 for coupons.";
        } else {
            $stmt = $conn->prepare("INSERT INTO rewards (gift_name, gift_type, credits_cost, time_duration, coupon_code, coupon_details, about_coupon, expire_date, showcase_date, display_days) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssissssssi", $gift_name, $gift_type, $credits_cost, $time_duration, $coupon_code, $coupon_details, $about_coupon, $expire_date, $showcase_date, $display_days);
            
            if ($stmt->execute()) {
                $message = "Reward/Coupon added successfully!";
                $conn->query("INSERT INTO admin_logs (admin_id, admin_username, action, description, ip_address) VALUES ({$_SESSION['admin_id']}, '{$_SESSION['admin_username']}', 'add_reward', 'Added reward/coupon: $gift_name', '{$_SERVER['REMOTE_ADDR']}')");
            } else {
                $error = "Failed to add reward/coupon.";
            }
            $stmt->close();
        }
    } elseif (isset($_POST['update_reward'])) {
        $reward_id = intval($_POST['reward_id'] ?? 0);
        $gift_name = trim($_POST['gift_name'] ?? '');
        $gift_type = trim($_POST['gift_type'] ?? 'reward');
        $credits_cost = intval($_POST['credits_cost'] ?? 0);
        $time_duration = trim($_POST['time_duration'] ?? '');
        $coupon_code = trim($_POST['coupon_code'] ?? '');
        $coupon_details = trim($_POST['coupon_details'] ?? '');
        $about_coupon = trim($_POST['about_coupon'] ?? '');
        $expire_date = !empty($_POST['expire_date']) ? $_POST['expire_date'] : null;
        $showcase_date = !empty($_POST['showcase_date']) ? $_POST['showcase_date'] : null;
        $display_days = intval($_POST['display_days'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if ($reward_id > 0 && !empty($gift_name)) {
            $stmt = $conn->prepare("UPDATE rewards SET gift_name = ?, gift_type = ?, credits_cost = ?, time_duration = ?, coupon_code = ?, coupon_details = ?, about_coupon = ?, expire_date = ?, showcase_date = ?, display_days = ?, is_active = ? WHERE id = ?");
            $stmt->bind_param("ssissssssiii", $gift_name, $gift_type, $credits_cost, $time_duration, $coupon_code, $coupon_details, $about_coupon, $expire_date, $showcase_date, $display_days, $is_active, $reward_id);
            
            if ($stmt->execute()) {
                $message = "Reward/Coupon updated successfully!";
            } else {
                $error = "Failed to update reward/coupon.";
            }
            $stmt->close();
        }
    } elseif (isset($_POST['delete_reward'])) {
        $reward_id = intval($_POST['reward_id'] ?? 0);
        if ($reward_id > 0) {
            $stmt = $conn->prepare("DELETE FROM rewards WHERE id = ?");
            $stmt->bind_param("i", $reward_id);
            if ($stmt->execute()) {
                $message = "Reward/Coupon deleted successfully!";
            } else {
                $error = "Failed to delete reward/coupon.";
            }
            $stmt->close();
        }
    }
}

// Get all rewards
$rewards = $conn->query("SELECT r.*, u.username as purchaser_username FROM rewards r LEFT JOIN users u ON r.purchased_by = u.id ORDER BY r.created_at DESC")->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rewards & Coupons Management - Astraden Admin</title>
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
            --color-rewards: #fbbf24;
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
        .ic-rewards { color: var(--color-rewards); text-shadow: 0 0 10px var(--color-rewards); }
        .menu-item:hover, .menu-item.active { background: rgba(255, 255, 255, 0.05); color: white; border-left-color: var(--primary-cyan); }
        .sidebar-footer { padding: 20px; border-top: 1px solid rgba(0, 255, 255, 0.1); }
        .logout-btn { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 12px; background: rgba(255, 0, 110, 0.1); border: 1px solid #ff006e; color: #ff006e; text-decoration: none; border-radius: 8px; font-family: 'Orbitron', sans-serif; font-size: 0.8rem; font-weight: 700; }

        .main-content { margin-left: var(--sidebar-width); flex: 1; padding: 40px; }
        .section-title { font-family: 'Orbitron', sans-serif; color: var(--primary-cyan); margin-bottom: 30px; letter-spacing: 3px; }

        .config-card { background: var(--card-bg); border: 1px solid rgba(0, 255, 255, 0.2); border-radius: 20px; padding: 35px; margin-bottom: 30px; }
        .config-card h3 { font-family: 'Orbitron', sans-serif; font-size: 1.1rem; color: var(--primary-cyan); margin-bottom: 25px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px; }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; color: var(--primary-purple); font-weight: 700; font-size: 0.8rem; margin-bottom: 8px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; background: rgba(0,0,0,0.5); border: 1px solid rgba(0,255,255,0.3); border-radius: 8px; color: white; outline: none; font-family: 'Rajdhani', sans-serif; }
        .form-group textarea { min-height: 100px; resize: vertical; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

        .btn-save, .btn-delete { background: linear-gradient(135deg, var(--primary-cyan), var(--primary-purple)); border: none; color: white; padding: 12px 30px; border-radius: 10px; font-family: 'Orbitron', sans-serif; font-weight: 900; cursor: pointer; margin-right: 10px; }
        .btn-delete { background: linear-gradient(135deg, #ff006e, #f72585); }
        .btn-save:hover, .btn-delete:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0, 255, 255, 0.3); }

        .msg { padding: 15px; border-radius: 10px; margin-bottom: 25px; font-weight: bold; }
        .msg-success { background: rgba(0, 255, 204, 0.1); border: 1px solid #00ffcc; color: #00ffcc; }
        .msg-error { background: rgba(255, 0, 110, 0.1); border: 1px solid #ff006e; color: #ff006e; }

        .table-card { background: var(--card-bg); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 15px; overflow: hidden; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1000px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
        th { color: var(--primary-purple); font-family: 'Orbitron', sans-serif; font-size: 0.75rem; text-transform: uppercase; background: rgba(0,0,0,0.2); }
        tr:hover { background: rgba(0, 255, 255, 0.05); }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; }
        .badge-reward { background: rgba(0, 255, 255, 0.2); color: var(--primary-cyan); border: 1px solid var(--primary-cyan); }
        .badge-coupon { background: rgba(251, 191, 36, 0.2); color: var(--color-rewards); border: 1px solid var(--color-rewards); }
        .badge-sold { background: rgba(255, 0, 110, 0.2); color: #ff006e; border: 1px solid #ff006e; }
        .badge-active { background: rgba(74, 222, 128, 0.2); color: #4ade80; border: 1px solid #4ade80; }
        .badge-inactive { background: rgba(255, 255, 255, 0.1); color: rgba(255, 255, 255, 0.5); border: 1px solid rgba(255, 255, 255, 0.2); }
    </style>
</head>
<body>
    <div class="space-bg"></div>

    <nav class="sidebar">
        <div class="sidebar-header"><h1>Astraden</h1></div>
        <div class="sidebar-menu">
            <div class="menu-category">General</div>
            <a href="admin_dashboard.php" class="menu-item"><i class="fas fa-chart-line"></i> <span>Overview</span></a>
            <a href="admin_system_settings.php" class="menu-item"><i class="fas fa-cog"></i> <span>System Settings</span></a>
            <div class="menu-category">User Control</div>
            <a href="admin_view_all_users.php" class="menu-item"><i class="fas fa-users"></i> <span>User Directory</span></a>
            <a href="admin_password_reset_requests.php" class="menu-item"><i class="fas fa-key"></i> <span>Reset Requests</span></a>
            <div class="menu-category">Financials</div>
            <a href="admin_transaction_codes.php" class="menu-item"><i class="fas fa-qrcode"></i> <span>Verify Payments</span></a>
            <a href="admin_user_credits.php" class="menu-item"><i class="fas fa-coins"></i> <span>Manual Credits</span></a>
            <div class="menu-category">Rewards & Coupons</div>
            <a href="admin_rewards.php" class="menu-item active"><i class="fas fa-gift ic-rewards"></i> <span>Rewards & Coupons</span></a>
            <div class="menu-category">Game Operations</div>
            <a href="admin_game_timing.php" class="menu-item"><i class="fas fa-calendar-check"></i> <span>Game Sessions</span></a>
            <a href="admin_game_credits.php" class="menu-item"><i class="fas fa-gamepad"></i> <span>Play Costs</span></a>
            <a href="admin_game_leaderboard.php" class="menu-item"><i class="fas fa-ranking-star"></i> <span>Leaderboards</span></a>
        </div>
        <div class="sidebar-footer"><a href="admin_logout.php" class="logout-btn"><i class="fas fa-power-off"></i> <span>Authorization Exit</span></a></div>
    </nav>

    <main class="main-content">
        <h2 class="section-title"><i class="fas fa-gift ic-rewards" style="margin-right:15px;"></i> REWARDS & COUPONS MANAGEMENT</h2>

        <?php if($message): ?><div class="msg msg-success"><?php echo $message; ?></div><?php endif; ?>
        <?php if($error): ?><div class="msg msg-error"><?php echo $error; ?></div><?php endif; ?>

        <div class="config-card">
            <form method="POST">
                <h3>Add New Reward or Coupon</h3>
                
                <div class="form-group">
                    <label>GIFT NAME *</label>
                    <input type="text" name="gift_name" required placeholder="e.g., Premium Membership, Discount Coupon">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>GIFT TYPE *</label>
                        <select name="gift_type" id="gift_type" required onchange="toggleCouponFields()">
                            <option value="reward">Reward</option>
                            <option value="coupon">Coupon</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>CREDITS COST *</label>
                        <input type="number" name="credits_cost" required min="0" placeholder="Credits required to purchase">
                    </div>
                </div>

                <div class="form-group" id="time_duration_group">
                    <label>TIME DURATION</label>
                    <input type="text" name="time_duration" placeholder="e.g., 30 days, 1 year, Lifetime">
                </div>

                <div class="form-group" id="coupon_code_group" style="display: none;">
                    <label>COUPON CODE *</label>
                    <input type="text" name="coupon_code" placeholder="e.g., SAVE50, WELCOME2024">
                </div>

                <div class="form-group" id="about_coupon_group" style="display: none;">
                    <label>ABOUT COUPON *</label>
                    <textarea name="about_coupon" placeholder="Describe what this coupon is about..."></textarea>
                </div>

                <div class="form-group" id="coupon_details_group" style="display: none;">
                    <label>COUPON DETAILS & BENEFITS</label>
                    <textarea name="coupon_details" placeholder="Describe the coupon benefits and details..."></textarea>
                </div>

                <div class="form-row" id="date_row_group" style="display: none;">
                    <div class="form-group">
                        <label>EXPIRE DATE *</label>
                        <input type="datetime-local" name="expire_date" required>
                    </div>
                    <div class="form-group">
                        <label>SHOWCASE DATE & TIME *</label>
                        <input type="datetime-local" name="showcase_date" required>
                    </div>
                </div>

                <div class="form-group" id="display_days_group" style="display: none;">
                    <label>DISPLAY DAYS *</label>
                    <input type="number" name="display_days" min="1" placeholder="e.g., if expire in 5 days, show only 3 days" required>
                    <small style="color: rgba(255,255,255,0.5); font-size: 0.75rem;">Number of days to display before expiring (e.g., if coupon expires in 5 days, enter 3 to show only for 3 days)</small>
                </div>

                <button type="submit" name="add_reward" class="btn-save">ADD REWARD/COUPON</button>
            </form>
        </div>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Gift Name</th>
                        <th>Type</th>
                        <th>Credits Cost</th>
                        <th>Duration</th>
                        <th>Coupon Code</th>
                        <th>Expire Date</th>
                        <th>Status</th>
                        <th>Purchased By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($rewards)): ?>
                    <tr>
                        <td colspan="12" style="text-align: center; padding: 40px; color: rgba(255,255,255,0.5);">No rewards or coupons found. Add one above.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach($rewards as $r): ?>
                    <tr>
                        <td>#<?php echo $r['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($r['gift_name']); ?></strong></td>
                        <td><span class="badge <?php echo $r['gift_type'] === 'coupon' ? 'badge-coupon' : 'badge-reward'; ?>"><?php echo ucfirst($r['gift_type']); ?></span></td>
                        <td><?php echo number_format($r['credits_cost']); ?> ⚡</td>
                        <td><?php echo htmlspecialchars($r['time_duration'] ?: '-'); ?></td>
                        <td><?php echo htmlspecialchars($r['coupon_code'] ?: '-'); ?></td>
                        <td><?php echo $r['showcase_date'] ? date('d M Y H:i', strtotime($r['showcase_date'])) : '-'; ?></td>
                        <td><?php echo $r['display_days'] > 0 ? $r['display_days'] . ' days' : '-'; ?></td>
                        <td><?php echo $r['expire_date'] ? date('d M Y H:i', strtotime($r['expire_date'])) : '-'; ?></td>
                        <td>
                            <?php if($r['is_sold']): ?>
                                <span class="badge badge-sold">SOLD</span>
                            <?php elseif($r['is_active']): ?>
                                <span class="badge badge-active">ACTIVE</span>
                            <?php else: ?>
                                <span class="badge badge-inactive">INACTIVE</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $r['purchaser_username'] ? htmlspecialchars($r['purchaser_username']) : '-'; ?></td>
                        <td>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this reward/coupon?');">
                                <input type="hidden" name="reward_id" value="<?php echo $r['id']; ?>">
                                <button type="submit" name="delete_reward" class="btn-delete" style="padding: 6px 12px; font-size: 0.75rem;">DELETE</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        function toggleCouponFields() {
            const giftType = document.getElementById('gift_type').value;
            const couponCodeGroup = document.getElementById('coupon_code_group');
            const aboutCouponGroup = document.getElementById('about_coupon_group');
            const couponDetailsGroup = document.getElementById('coupon_details_group');
            const dateRowGroup = document.getElementById('date_row_group');
            const displayDaysGroup = document.getElementById('display_days_group');
            
            if (giftType === 'coupon') {
                couponCodeGroup.style.display = 'block';
                aboutCouponGroup.style.display = 'block';
                couponDetailsGroup.style.display = 'block';
                dateRowGroup.style.display = 'grid';
                displayDaysGroup.style.display = 'block';
                couponCodeGroup.querySelector('input').required = true;
                aboutCouponGroup.querySelector('textarea').required = true;
                dateRowGroup.querySelector('input[name="expire_date"]').required = true;
                dateRowGroup.querySelector('input[name="showcase_date"]').required = true;
                displayDaysGroup.querySelector('input').required = true;
            } else {
                couponCodeGroup.style.display = 'none';
                aboutCouponGroup.style.display = 'none';
                couponDetailsGroup.style.display = 'none';
                dateRowGroup.style.display = 'none';
                displayDaysGroup.style.display = 'none';
                couponCodeGroup.querySelector('input').required = false;
                aboutCouponGroup.querySelector('textarea').required = false;
                dateRowGroup.querySelector('input[name="expire_date"]').required = false;
                dateRowGroup.querySelector('input[name="showcase_date"]').required = false;
                displayDaysGroup.querySelector('input').required = false;
            }
        }
    </script>
</body>
</html>

