<?php
require_once 'admin_check.php';
require_once 'connection.php';

$message = '';
$error = '';

// Create system_settings table if it doesn't exist
$create_table = $conn->query("
    CREATE TABLE IF NOT EXISTS `system_settings` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `setting_key` VARCHAR(100) NOT NULL UNIQUE,
      `setting_value` TEXT,
      `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `unique_setting_key` (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $show_credit_purchase = isset($_POST['show_credit_purchase']) ? 1 : 0;
    $show_rewards = isset($_POST['show_rewards']) ? 1 : 0;
    $show_bidding = isset($_POST['show_bidding']) ? 1 : 0;
    $auto_credits_enabled = isset($_POST['auto_credits_enabled']) ? 1 : 0;
    $new_user_credits = isset($_POST['new_user_credits']) ? (int)$_POST['new_user_credits'] : 0;
    $referral_credits = isset($_POST['referral_credits']) ? (int)$_POST['referral_credits'] : 0;
    
    // Validate credit amounts
    if ($auto_credits_enabled && $new_user_credits < 0) {
        $error = "New user credits cannot be negative.";
    } elseif ($auto_credits_enabled && $referral_credits < 0) {
        $error = "Referral credits cannot be negative.";
    } else {
        // Insert or update all settings
        $settings = [
            'show_credit_purchase' => $show_credit_purchase,
            'show_rewards' => $show_rewards,
            'show_bidding' => $show_bidding,
            'auto_credits_enabled' => $auto_credits_enabled,
            'new_user_credits' => $new_user_credits,
            'referral_credits' => $referral_credits
        ];
        
        $success = true;
        foreach ($settings as $key => $value) {
            $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->bind_param("sss", $key, $value, $value);
            if (!$stmt->execute()) {
                $success = false;
            }
            $stmt->close();
        }
        
        if ($success) {
            $message = "System settings updated successfully!";
            // Log admin action
            $conn->query("INSERT INTO admin_logs (admin_id, admin_username, action, description, ip_address) VALUES ({$_SESSION['admin_id']}, '{$_SESSION['admin_username']}', 'update_system_settings', 'Updated system settings including auto-credits', '{$_SERVER['REMOTE_ADDR']}')");
        } else {
            $error = "Failed to update settings. Please try again.";
        }
    }
}

// Get current settings
function getSetting($conn, $key, $default = 0) {
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $setting = $result->fetch_assoc();
        $stmt->close();
        return is_numeric($setting['setting_value']) ? (int)$setting['setting_value'] : $setting['setting_value'];
    }
    $stmt->close();
    return $default;
}

$show_credit_purchase = getSetting($conn, 'show_credit_purchase', 1);
$show_rewards = getSetting($conn, 'show_rewards', 1);
$show_bidding = getSetting($conn, 'show_bidding', 0);
$auto_credits_enabled = getSetting($conn, 'auto_credits_enabled', 0);
$new_user_credits = getSetting($conn, 'new_user_credits', 0);
$referral_credits = getSetting($conn, 'referral_credits', 0);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Astraden Admin</title>
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="Alogo.svg">
    <link rel="shortcut icon" type="image/svg+xml" href="Alogo.svg">
    <link rel="alternate icon" type="image/png" href="Alogo.svg">
    <link rel="apple-touch-icon" sizes="180x180" href="Alogo.svg">
    <link rel="icon" type="image/svg+xml" sizes="any" href="Alogo.svg">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-cyan: #00ffff;
            --primary-purple: #9d4edd;
            --sidebar-width: 280px;
            --dark-bg: #05050a;
            --card-bg: rgba(15, 15, 25, 0.95);
            
            /* Icon Colors */
            --color-overview: #00ffff;
            --color-users: #4cc9f0;
            --color-reset: #f72585;
            --color-verify: #4ade80;
            --color-credits: #ffd700;
            --color-pricing: #f97316;
            --color-timing: #a855f7;
            --color-limits: #ef4444;
            --color-sessions: #3b82f6;
            --color-contest: #fbbf24;
            --color-costs: #ec4899;
            --color-prizes: #8b5cf6;
            --color-leaderboard: #10b981;
            --color-settings: #06b6d4;
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
        
        .ic-overview { color: var(--color-overview); text-shadow: 0 0 10px var(--color-overview); }
        .ic-users { color: var(--color-users); text-shadow: 0 0 10px var(--color-users); }
        .ic-reset { color: var(--color-reset); text-shadow: 0 0 10px var(--color-reset); }
        .ic-verify { color: var(--color-verify); text-shadow: 0 0 10px var(--color-verify); }
        .ic-credits { color: var(--color-credits); text-shadow: 0 0 10px var(--color-credits); }
        .ic-pricing { color: var(--color-pricing); text-shadow: 0 0 10px var(--color-pricing); }
        .ic-timing { color: var(--color-timing); text-shadow: 0 0 10px var(--color-timing); }
        .ic-limits { color: var(--color-limits); text-shadow: 0 0 10px var(--color-limits); }
        .ic-sessions { color: var(--color-sessions); text-shadow: 0 0 10px var(--color-sessions); }
        .ic-contest { color: var(--color-contest); text-shadow: 0 0 10px var(--color-contest); }
        .ic-costs { color: var(--color-costs); text-shadow: 0 0 10px var(--color-costs); }
        .ic-prizes { color: var(--color-prizes); text-shadow: 0 0 10px var(--color-prizes); }
        .ic-leaderboard { color: var(--color-leaderboard); text-shadow: 0 0 10px var(--color-leaderboard); }
        .ic-settings { color: var(--color-settings); text-shadow: 0 0 10px var(--color-settings); }

        .menu-item:hover, .menu-item.active { background: rgba(255, 255, 255, 0.05); color: white; border-left-color: var(--primary-cyan); }
        .sidebar-footer { padding: 20px; border-top: 1px solid rgba(0, 255, 255, 0.1); }
        .logout-btn { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 12px; background: rgba(255, 0, 110, 0.1); border: 1px solid #ff006e; color: #ff006e; text-decoration: none; border-radius: 8px; font-family: 'Orbitron', sans-serif; font-size: 0.8rem; font-weight: 700; }

        .main-content { margin-left: var(--sidebar-width); flex: 1; padding: 40px; }
        .section-title { font-family: 'Orbitron', sans-serif; color: var(--primary-cyan); margin-bottom: 30px; letter-spacing: 3px; }

        .config-card { background: var(--card-bg); border: 1px solid rgba(0, 255, 255, 0.2); border-radius: 20px; padding: 35px; margin-bottom: 30px; }
        .config-card h3 { font-family: 'Orbitron', sans-serif; font-size: 1.1rem; color: var(--primary-cyan); margin-bottom: 25px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px; }

        .toggle-group { display: flex; align-items: center; gap: 20px; margin-bottom: 25px; background: rgba(255,255,255,0.03); padding: 15px; border-radius: 12px; }
        .switch { position: relative; display: inline-block; width: 60px; height: 30px; flex-shrink: 0; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #333; transition: .4s; border-radius: 34px; border: 1px solid rgba(255,255,255,0.1); }
        .slider:before { position: absolute; content: ""; height: 22px; width: 22px; left: 4px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: var(--primary-cyan); }
        input:checked + .slider:before { transform: translateX(30px); }
        
        .input-group { margin-bottom: 25px; background: rgba(255,255,255,0.03); padding: 15px; border-radius: 12px; }
        .input-group label { display: block; color: var(--primary-cyan); font-weight: 700; margin-bottom: 8px; font-family: 'Orbitron', sans-serif; font-size: 0.9rem; }
        .input-group input[type="number"] { width: 100%; padding: 12px; background: rgba(255,255,255,0.05); border: 1px solid rgba(0, 255, 255, 0.2); border-radius: 8px; color: white; font-size: 1rem; font-family: 'Rajdhani', sans-serif; }
        .input-group input[type="number"]:focus { outline: none; border-color: var(--primary-cyan); box-shadow: 0 0 10px rgba(0, 255, 255, 0.3); }
        .input-group small { display: block; color: rgba(255,255,255,0.4); margin-top: 5px; font-size: 0.85rem; }

        .btn-save { background: linear-gradient(135deg, var(--primary-cyan), var(--primary-purple)); border: none; color: white; padding: 15px 40px; border-radius: 10px; font-family: 'Orbitron', sans-serif; font-weight: 900; cursor: pointer; display: block; margin: 30px auto 0; }

        .msg { padding: 15px; border-radius: 10px; margin-bottom: 25px; background: rgba(0, 255, 204, 0.1); border: 1px solid #00ffcc; color: #00ffcc; font-weight: bold; }
        .error-msg { padding: 15px; border-radius: 10px; margin-bottom: 25px; background: rgba(255, 0, 110, 0.1); border: 1px solid #ff006e; color: #ff006e; font-weight: bold; }
    </style>
</head>
<body>
    <div class="space-bg"></div>

    <nav class="sidebar">
        <div class="sidebar-header"><h1>Astraden</h1></div>
        <div class="sidebar-menu">
            <div class="menu-category">General</div>
            <a href="admin_dashboard.php" class="menu-item"><i class="fas fa-chart-line ic-overview"></i> <span>Overview</span></a>
            <a href="admin_system_settings.php" class="menu-item active"><i class="fas fa-cog ic-settings"></i> <span>System Settings</span></a>
            <div class="menu-category">User Control</div>
            <a href="admin_view_all_users.php" class="menu-item"><i class="fas fa-users ic-users"></i> <span>User Directory</span></a>
            <a href="admin_password_reset_requests.php" class="menu-item"><i class="fas fa-key ic-reset"></i> <span>Reset Requests</span></a>
            <div class="menu-category">Financials</div>
            <a href="admin_transaction_codes.php" class="menu-item"><i class="fas fa-qrcode ic-verify"></i> <span>Verify Payments</span></a>
            <a href="admin_user_credits.php" class="menu-item"><i class="fas fa-coins ic-credits"></i> <span>Manual Credits</span></a>
            <a href="admin_credit_pricing.php" class="menu-item"><i class="fas fa-tags ic-pricing"></i> <span>Pricing Plans</span></a>
            <a href="admin_credit_timing.php" class="menu-item"><i class="fas fa-clock ic-timing"></i> <span>Purchase Timing</span></a>
            <a href="admin_credit_sale_limit.php" class="menu-item"><i class="fas fa-gauge-high ic-limits"></i> <span>Sale Limits</span></a>
            <div class="menu-category">Rewards & Coupons</div>
            <a href="admin_rewards.php" class="menu-item"><i class="fas fa-gift ic-contest"></i> <span>Rewards & Coupons</span></a>
            <div class="menu-category">Game Operations</div>
            <a href="admin_game_timing.php" class="menu-item"><i class="fas fa-calendar-check ic-sessions"></i> <span>Game Sessions</span></a>
            <a href="admin_game_credits.php" class="menu-item"><i class="fas fa-gamepad ic-costs"></i> <span>Play Costs</span></a>
            <a href="admin_game_leaderboard.php" class="menu-item"><i class="fas fa-ranking-star ic-leaderboard"></i> <span>Leaderboards</span></a>
            <a href="admin_bidding_management.php" class="menu-item"><i class="fas fa-gavel" style="color: #ff6b35; text-shadow: 0 0 10px #ff6b35;"></i> <span>Bidding Management</span></a>
        </div>
        <div class="sidebar-footer"><a href="admin_logout.php" class="logout-btn"><i class="fas fa-power-off"></i> <span>Authorization Exit</span></a></div>
    </nav>

    <main class="main-content">
        <h2 class="section-title"><i class="fas fa-cog ic-settings" style="margin-right:15px;"></i> SYSTEM SETTINGS</h2>

        <?php if($message): ?><div class="msg"><?php echo $message; ?></div><?php endif; ?>
        <?php if($error): ?><div class="error-msg"><?php echo $error; ?></div><?php endif; ?>

        <div class="config-card">
            <form method="POST">
                <h3>User Interface Controls</h3>
                
                <div class="toggle-group">
                    <label class="switch">
                        <input type="checkbox" name="show_credit_purchase" <?php echo $show_credit_purchase ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                    <div>
                        <strong style="display:block;color:var(--primary-cyan);">SHOW CREDIT PURCHASE OPTIONS</strong>
                        <small style="color:rgba(255,255,255,0.4);">When enabled, users can see and access credit purchase options with price charts in their user info tab. When disabled, all credit purchase options will be hidden from users.</small>
                    </div>
                </div>

                <div class="toggle-group" style="margin-top: 25px;">
                    <label class="switch">
                        <input type="checkbox" name="show_rewards" <?php echo $show_rewards ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                    <div>
                        <strong style="display:block;color:var(--primary-cyan);">SHOW REWARD OPTIONS</strong>
                        <small style="color:rgba(255,255,255,0.4);">When enabled, users can see and access reward options (Rewards & Coupons, Claim Reward) in the index page. When disabled, all reward-related links and buttons will be hidden from users.</small>
                    </div>
                </div>

                <div class="toggle-group" style="margin-top: 25px;">
                    <label class="switch">
                        <input type="checkbox" name="show_bidding" <?php echo $show_bidding ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                    <div>
                        <strong style="display:block;color:var(--primary-cyan);">SHOW BIDDING OPTIONS</strong>
                        <small style="color:rgba(255,255,255,0.4);">When enabled, users can see and access bidding features (Bidding, My Wins) in the index page. When disabled, all bidding-related links and buttons will be hidden from users.</small>
                    </div>
                </div>

                <div class="config-card" style="margin-top: 30px;">
                    <h3>Automatic Credits on Registration</h3>
                    
                    <div class="toggle-group">
                        <label class="switch">
                            <input type="checkbox" name="auto_credits_enabled" id="auto_credits_enabled" <?php echo $auto_credits_enabled ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                        <div>
                            <strong style="display:block;color:var(--primary-cyan);">ENABLE AUTO CREDITS</strong>
                            <small style="color:rgba(255,255,255,0.4);">When enabled, new users will automatically receive credits upon registration. You can set the amount below.</small>
                        </div>
                    </div>

                    <div class="input-group" id="new_user_credits_group" style="<?php echo $auto_credits_enabled ? '' : 'opacity: 0.5; pointer-events: none;'; ?>">
                        <label for="new_user_credits">NEW USER CREDITS</label>
                        <input type="number" name="new_user_credits" id="new_user_credits" value="<?php echo $new_user_credits; ?>" min="0" step="1" required>
                        <small>Amount of credits automatically awarded to new users when they register.</small>
                    </div>

                    <div class="input-group" id="referral_credits_group" style="<?php echo $auto_credits_enabled ? '' : 'opacity: 0.5; pointer-events: none;'; ?>">
                        <label for="referral_credits">REFERRAL REWARD CREDITS</label>
                        <input type="number" name="referral_credits" id="referral_credits" value="<?php echo $referral_credits; ?>" min="0" step="1" required>
                        <small>Amount of credits automatically awarded to the referrer when a new user registers using their referral code.</small>
                    </div>
                </div>

                <button type="submit" name="update_settings" class="btn-save">SAVE SETTINGS</button>
                
                <script>
                    document.getElementById('auto_credits_enabled').addEventListener('change', function() {
                        const enabled = this.checked;
                        const newUserGroup = document.getElementById('new_user_credits_group');
                        const referralGroup = document.getElementById('referral_credits_group');
                        
                        if (enabled) {
                            newUserGroup.style.opacity = '1';
                            newUserGroup.style.pointerEvents = 'auto';
                            referralGroup.style.opacity = '1';
                            referralGroup.style.pointerEvents = 'auto';
                        } else {
                            newUserGroup.style.opacity = '0.5';
                            newUserGroup.style.pointerEvents = 'none';
                            referralGroup.style.opacity = '0.5';
                            referralGroup.style.pointerEvents = 'none';
                        }
                    });
                </script>
            </form>
        </div>
    </main>
</body>
</html>

