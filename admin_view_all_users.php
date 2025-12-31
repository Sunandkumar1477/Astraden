<?php
session_start();
require_once 'admin_check.php';
require_once 'connection.php';

$message = '';
$error = '';
$search_query = '';
$users = [];

// Handle account deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_account'])) {
    $user_id = intval($_POST['user_id'] ?? 0);
    
    if ($user_id > 0) {
        // Get user details before deletion
        $user_stmt = $conn->prepare("SELECT username, mobile_number, referred_by FROM users WHERE id = ?");
        $user_stmt->bind_param("i", $user_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        
        if ($user_result->num_rows > 0) {
            $user_data = $user_result->fetch_assoc();
            $user_stmt->close();
            
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // If user was referred by someone, subtract referral credits
                if ($user_data['referred_by']) {
                    // Get referral credits amount from system settings
                    $settings_stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'referral_credits'");
                    $settings_stmt->execute();
                    $settings_result = $settings_stmt->get_result();
                    $referral_credits = 0;
                    if ($settings_result->num_rows > 0) {
                        $setting = $settings_result->fetch_assoc();
                        $referral_credits = (int)$setting['setting_value'];
                    }
                    $settings_stmt->close();
                    
                    // Subtract referral credits from referrer if they exist
                    if ($referral_credits > 0) {
                        $ref_update_stmt = $conn->prepare("UPDATE user_profile SET credits = GREATEST(0, credits - ?) WHERE user_id = ?");
                        $ref_update_stmt->bind_param("ii", $referral_credits, $user_data['referred_by']);
                        $ref_update_stmt->execute();
                        $ref_update_stmt->close();
                    }
                }
                
                // Delete user profile first (due to foreign key)
                $delete_profile_stmt = $conn->prepare("DELETE FROM user_profile WHERE user_id = ?");
                $delete_profile_stmt->bind_param("i", $user_id);
                $delete_profile_stmt->execute();
                $delete_profile_stmt->close();
                
                // Delete user account (this will cascade to related records)
                $delete_user_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $delete_user_stmt->bind_param("i", $user_id);
                $delete_user_stmt->execute();
                $delete_user_stmt->close();
                
                // Commit transaction
                $conn->commit();
                
                // Log admin action
                $log_stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, admin_username, action, description, ip_address, user_agent, target_user_id) VALUES (?, ?, 'delete_user_account', ?, ?, ?, ?)");
                $description = "Permanently deleted account: '{$user_data['username']}' (ID: {$user_id}, Mobile: {$user_data['mobile_number']})";
                $log_stmt->bind_param("issssi", $_SESSION['admin_id'], $_SESSION['admin_username'], $description, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '', $user_id);
                $log_stmt->execute();
                $log_stmt->close();
                
                $message = "Account '{$user_data['username']}' has been permanently deleted. Referral credits have been reversed if applicable.";
                
            } catch (Exception $e) {
                // Rollback on error
                $conn->rollback();
                $error = "Failed to delete account: " . $e->getMessage();
            }
        } else {
            $error = "User not found.";
        }
    }
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_user_password'])) {
    $user_id = intval($_POST['user_id'] ?? 0);
    $new_password = trim($_POST['new_password'] ?? '');
    
    if ($user_id > 0 && !empty($new_password)) {
        if (strlen($new_password) < 6) {
            $error = "Password must be at least 6 characters long.";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $user_stmt = $conn->prepare("SELECT username, mobile_number FROM users WHERE id = ?");
            $user_stmt->bind_param("i", $user_id);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            
            if ($user_result->num_rows > 0) {
                $user_data = $user_result->fetch_assoc();
                $user_stmt->close();
                $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update_stmt->bind_param("si", $hashed_password, $user_id);
                
                if ($update_stmt->execute()) {
                    $log_stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, admin_username, action, description, ip_address, user_agent, target_user_id) VALUES (?, ?, 'reset_user_password', ?, ?, ?, ?)");
                    $description = "Reset password for '{$user_data['username']}' (ID: {$user_id})";
                    $log_stmt->bind_param("issssi", $_SESSION['admin_id'], $_SESSION['admin_username'], $description, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], $user_id);
                    $log_stmt->execute();
                    $log_stmt->close();
                    $message = "Password updated for {$user_data['username']}. New: {$new_password}";
                } else {
                    $error = "Failed to update database.";
                }
                $update_stmt->close();
            } else {
                $error = "User not found.";
            }
        }
    }
}

// Handle search
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['search'])) {
    $search_query = trim($_GET['search'] ?? '');
}

// Function to validate mobile number
function isValidMobileNumber($mobile) {
    // Remove any spaces, dashes, or other characters
    $clean_mobile = preg_replace('/[^0-9]/', '', $mobile);
    // Check if it's 10-15 digits (standard mobile number length)
    return preg_match('/^[0-9]{10,15}$/', $clean_mobile);
}

// Get users with all details
$sql = "SELECT u.id, u.username, u.mobile_number, u.password, u.referral_code, u.referred_by, u.created_at, u.last_login, COALESCE(up.credits, 0) as credits 
        FROM users u 
        LEFT JOIN user_profile up ON u.id = up.user_id";
if (!empty($search_query)) {
    $search_term = "%{$search_query}%";
    $stmt = $conn->prepare("$sql WHERE u.username LIKE ? OR u.mobile_number LIKE ? OR u.referral_code LIKE ? ORDER BY u.created_at DESC");
    $stmt->bind_param("sss", $search_term, $search_term, $search_term);
    $stmt->execute();
    $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $users = $conn->query("$sql ORDER BY u.created_at DESC")->fetch_all(MYSQLI_ASSOC);
}

// Validate mobile numbers and mark invalid ones
foreach ($users as &$user) {
    $user['invalid_mobile'] = !isValidMobileNumber($user['mobile_number']);
}
unset($user);

// Keep connection open for referrer lookups in the loop
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Directory - Astraden Admin</title>
    <!-- Favicon - Must be early in head for proper display -->
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

        .menu-item:hover, .menu-item.active { background: rgba(255, 255, 255, 0.05); color: white; border-left-color: var(--primary-cyan); }
        .sidebar-footer { padding: 20px; border-top: 1px solid rgba(0, 255, 255, 0.1); }
        .logout-btn { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 12px; background: rgba(255, 0, 110, 0.1); border: 1px solid #ff006e; color: #ff006e; text-decoration: none; border-radius: 8px; font-family: 'Orbitron', sans-serif; font-size: 0.8rem; font-weight: 700; }

        .main-content { margin-left: var(--sidebar-width); flex: 1; padding: 40px; }
        .section-title { font-family: 'Orbitron', sans-serif; color: var(--primary-cyan); margin-bottom: 30px; letter-spacing: 3px; display: flex; align-items: center; gap: 15px; }

        .search-box { background: var(--card-bg); border: 1px solid rgba(0, 255, 255, 0.2); border-radius: 15px; padding: 20px; margin-bottom: 30px; display: flex; gap: 15px; }
        .search-box input { flex: 1; padding: 12px 20px; background: rgba(0, 0, 0, 0.5); border: 1px solid rgba(0, 255, 255, 0.3); border-radius: 10px; color: white; font-family: 'Rajdhani', sans-serif; font-size: 1rem; outline: none; }
        .search-btn { background: linear-gradient(135deg, var(--primary-cyan), var(--primary-purple)); border: none; color: white; padding: 0 30px; border-radius: 10px; font-family: 'Orbitron', sans-serif; font-weight: 700; cursor: pointer; }

        .table-card { background: var(--card-bg); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 15px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1200px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid rgba(255, 255, 255, 0.05); font-size: 0.9rem; }
        th { color: var(--primary-purple); font-family: 'Orbitron', sans-serif; font-size: 0.75rem; text-transform: uppercase; background: rgba(0,0,0,0.2); position: sticky; top: 0; z-index: 10; }
        tr:hover { background: rgba(0, 255, 255, 0.05); }
        tr.invalid-mobile { background: rgba(255, 0, 110, 0.15) !important; border-left: 4px solid #ff006e; }
        tr.invalid-mobile:hover { background: rgba(255, 0, 110, 0.25) !important; }
        
        .user-name { color: var(--primary-cyan); font-weight: 700; }
        .credits-val { color: #FFD700; font-weight: bold; }
        .password-hash { font-family: 'Courier New', monospace; font-size: 0.75rem; color: rgba(255, 255, 255, 0.6); word-break: break-all; max-width: 200px; }
        .referral-code { color: var(--primary-purple); font-weight: 700; font-family: 'Orbitron', sans-serif; }
        .action-btn { background: rgba(0, 255, 255, 0.1); border: 1px solid var(--primary-cyan); color: var(--primary-cyan); padding: 6px 12px; border-radius: 6px; font-family: 'Orbitron', sans-serif; font-size: 0.7rem; font-weight: 700; cursor: pointer; text-decoration: none; transition: 0.3s; white-space: nowrap; }
        .action-btn:hover { background: var(--primary-cyan); color: black; }
        .info-badge { display: inline-block; padding: 4px 8px; background: rgba(157, 78, 221, 0.2); border: 1px solid var(--primary-purple); border-radius: 4px; font-size: 0.75rem; color: var(--primary-purple); }
        .invalid-badge { display: inline-block; padding: 4px 8px; background: rgba(255, 0, 110, 0.2); border: 1px solid #ff006e; border-radius: 4px; font-size: 0.7rem; color: #ff006e; font-weight: 700; margin-left: 8px; font-family: 'Orbitron', sans-serif; }

        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.9); display: none; justify-content: center; align-items: center; z-index: 10000; backdrop-filter: blur(5px); }
        .modal-overlay.show { display: flex; }
        .modal { background: var(--card-bg); border: 2px solid var(--primary-cyan); border-radius: 20px; padding: 40px; max-width: 450px; width: 90%; }
        .modal h2 { font-family: 'Orbitron', sans-serif; color: var(--primary-cyan); margin-bottom: 25px; text-align: center; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; color: var(--primary-purple); font-weight: 700; font-size: 0.8rem; margin-bottom: 8px; }
        .form-group input { width: 100%; padding: 12px; background: rgba(0,0,0,0.5); border: 1px solid rgba(0,255,255,0.3); border-radius: 8px; color: white; outline: none; }
        .modal-btns { display: flex; gap: 15px; margin-top: 30px; }
        .modal-btn { flex: 1; padding: 12px; border-radius: 10px; font-family: 'Orbitron', sans-serif; font-weight: 700; cursor: pointer; border: none; }
        .btn-confirm { background: linear-gradient(135deg, var(--primary-cyan), var(--primary-purple)); color: white; }
        .btn-cancel { background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); }

        .msg { padding: 15px; border-radius: 10px; margin-bottom: 25px; font-weight: bold; }
        .msg-success { background: rgba(0, 255, 204, 0.1); border: 1px solid #00ffcc; color: #00ffcc; }
        .msg-error { background: rgba(255, 0, 110, 0.1); border: 1px solid #ff006e; color: #ff006e; }
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
            <a href="admin_view_all_users.php" class="menu-item active"><i class="fas fa-users ic-users"></i> <span>User Directory</span></a>
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
        </div>
        <div class="sidebar-footer"><a href="admin_logout.php" class="logout-btn"><i class="fas fa-power-off"></i> <span>Authorization Exit</span></a></div>
    </nav>

    <main class="main-content">
        <h2 class="section-title"><i class="fas fa-users-viewfinder ic-users" style="margin-right:15px;"></i> USER DIRECTORY</h2>

        <?php if($message): ?><div class="msg msg-success"><?php echo $message; ?></div><?php endif; ?>
        <?php if($error): ?><div class="msg msg-error"><?php echo $error; ?></div><?php endif; ?>
        
        <?php 
        $invalid_count = 0;
        foreach($users as $u) {
            if ($u['invalid_mobile']) $invalid_count++;
        }
        if($invalid_count > 0): 
        ?>
            <div class="msg" style="background: rgba(255, 0, 110, 0.1); border: 1px solid #ff006e; color: #ff006e; margin-bottom: 25px;">
                <strong>⚠️ WARNING:</strong> <?php echo $invalid_count; ?> user(s) with invalid mobile numbers detected. These rows are highlighted in red. Please verify and delete invalid accounts.
            </div>
        <?php endif; ?>

        <form method="GET" class="search-box">
            <input type="text" name="search" placeholder="Search by username, phone number, or referral code..." value="<?php echo htmlspecialchars($search_query); ?>">
            <button type="submit" class="search-btn">SEARCH</button>
            <?php if($search_query): ?><a href="admin_view_all_users.php" class="action-btn" style="display:flex;align-items:center;">CLEAR</a><?php endif; ?>
        </form>
        
        <div style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(0, 255, 255, 0.2); border-radius: 10px; padding: 15px; margin-bottom: 20px; font-size: 0.85rem; color: rgba(255, 255, 255, 0.7);">
            <strong style="color: var(--primary-cyan);">📊 Total Users:</strong> <?php echo count($users); ?> | 
            <strong style="color: var(--primary-purple);">Note:</strong> Passwords are encrypted (hashed) for security. Use "Reset Password" to set a new password.
        </div>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Phone Number</th>
                        <th>Password (Hashed)</th>
                        <th>Referral Code</th>
                        <th>Credits</th>
                        <th>Join Date</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($users)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 40px; color: rgba(255,255,255,0.5);">
                            No users found. <?php if($search_query): ?>Try a different search term.<?php else: ?>Users will appear here once they register.<?php endif; ?>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach($users as $u): 
                        // Get referrer username if exists
                        $referrer_username = null;
                        if ($u['referred_by']) {
                            $ref_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
                            $ref_stmt->bind_param("i", $u['referred_by']);
                            $ref_stmt->execute();
                            $ref_result = $ref_stmt->get_result();
                            if ($ref_result->num_rows > 0) {
                                $referrer_username = $ref_result->fetch_assoc()['username'];
                            }
                            $ref_stmt->close();
                        }
                    ?>
                    <tr class="<?php echo $u['invalid_mobile'] ? 'invalid-mobile' : ''; ?>">
                        <td><strong style="color: var(--primary-cyan);">#<?php echo $u['id']; ?></strong></td>
                        <td><span class="user-name"><?php echo htmlspecialchars($u['username']); ?></span></td>
                        <td>
                            <?php echo htmlspecialchars($u['mobile_number']); ?>
                            <?php if($u['invalid_mobile']): ?>
                                <span class="invalid-badge">INVALID</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="password-hash" title="Encrypted password (hashed for security)">
                                <?php echo substr(htmlspecialchars($u['password']), 0, 50); ?><?php echo strlen($u['password']) > 50 ? '...' : ''; ?>
                            </div>
                        </td>
                        <td>
                            <?php if($u['referral_code']): ?>
                                <span class="referral-code"><?php echo htmlspecialchars($u['referral_code']); ?></span>
                                <?php if($referrer_username): ?>
                                    <br><small style="color: rgba(255,255,255,0.5);">Referred by: <?php echo htmlspecialchars($referrer_username); ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color: rgba(255,255,255,0.3);">-</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="credits-val"><?php echo number_format($u['credits']); ?></span> ⚡</td>
                        <td><?php echo date('d M Y', strtotime($u['created_at'])); ?></td>
                        <td>
                            <?php if($u['last_login']): ?>
                                <?php echo date('d M Y H:i', strtotime($u['last_login'])); ?>
                            <?php else: ?>
                                <span style="color: rgba(255,255,255,0.3);">Never</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button onclick="openModal(<?php echo $u['id']; ?>, '<?php echo addslashes($u['username']); ?>')" class="action-btn">RESET PASSWORD</button>
                            <button onclick="deleteAccount(<?php echo $u['id']; ?>, '<?php echo addslashes($u['username']); ?>')" class="action-btn" style="background: rgba(255, 0, 110, 0.1); border-color: #ff006e; color: #ff006e; margin-left: 5px;">DELETE</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <div class="modal-overlay" id="resetModal">
        <div class="modal">
            <h2>RESET ACCESS</h2>
            <form method="POST">
                <input type="hidden" name="user_id" id="modal_user_id">
                <div class="form-group">
                    <label>TARGET IDENTITY</label>
                    <input type="text" id="modal_username" readonly style="border-color:transparent;color:var(--primary-cyan);font-weight:bold;">
                </div>
                <div class="form-group">
                    <label>NEW PASSKEY</label>
                    <input type="text" name="new_password" placeholder="At least 6 characters" required minlength="6">
                </div>
                <div class="modal-btns">
                    <button type="button" onclick="closeModal()" class="modal-btn btn-cancel">CANCEL</button>
                    <button type="submit" name="reset_user_password" class="modal-btn btn-confirm">UPDATE PASS</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Sidebar scroll preservation
        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.querySelector('.sidebar-menu');
            if (sidebar) {
                const savedScroll = localStorage.getItem('sidebar_scroll');
                if (savedScroll) sidebar.scrollTop = savedScroll;
                sidebar.addEventListener('scroll', () => { localStorage.setItem('sidebar_scroll', sidebar.scrollTop); });
                const activeItem = sidebar.querySelector('.menu-item.active');
                if (activeItem) {
                    const rect = activeItem.getBoundingClientRect();
                    const containerRect = sidebar.getBoundingClientRect();
                    if (rect.bottom > containerRect.bottom || rect.top < containerRect.top) {
                        activeItem.scrollIntoView({ block: 'center' });
                    }
                }
            }
        });

        function openModal(id, name) {
            document.getElementById('modal_user_id').value = id;
            document.getElementById('modal_username').value = name;
            document.getElementById('resetModal').classList.add('show');
        }
        function closeModal() { document.getElementById('resetModal').classList.remove('show'); }
        
        function deleteAccount(userId, username) {
            const confirmMsg = `⚠️ WARNING: This will PERMANENTLY DELETE the account "${username}" (ID: ${userId}).\n\n` +
                             `This action will:\n` +
                             `• Delete the user account permanently\n` +
                             `• Remove all user data\n` +
                             `• Reverse referral credits if user was referred\n` +
                             `• This action CANNOT be undone!\n\n` +
                             `Are you absolutely sure you want to proceed?`;
            
            if (confirm(confirmMsg)) {
                // Double confirmation
                if (confirm('FINAL CONFIRMATION: This will permanently delete the account.\n\nClick OK to proceed, Cancel to abort.')) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.style.display = 'none';
                    
                    const userIdInput = document.createElement('input');
                    userIdInput.type = 'hidden';
                    userIdInput.name = 'user_id';
                    userIdInput.value = userId;
                    
                    const submitInput = document.createElement('input');
                    submitInput.type = 'hidden';
                    submitInput.name = 'delete_user_account';
                    submitInput.value = '1';
                    
                    form.appendChild(userIdInput);
                    form.appendChild(submitInput);
                    document.body.appendChild(form);
                    form.submit();
                }
            }
        }
        
        if (window.history.replaceState) window.history.replaceState(null, null, window.location.href);
    </script>
</body>
</html>
<?php
// Close connection at the end after all queries
if (isset($conn)) {
    $conn->close();
}
?>
