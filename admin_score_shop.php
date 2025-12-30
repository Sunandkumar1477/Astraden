<?php
session_start();
require_once 'admin_check.php';
require_once 'connection.php';

$message = '';
$error = '';

// Check and create score_shop_settings table if not exists
try {
    $check_table = $conn->query("SHOW TABLES LIKE 'score_shop_settings'");
    if ($check_table->num_rows == 0) {
        $conn->query("CREATE TABLE IF NOT EXISTS score_shop_settings (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            game_name VARCHAR(50) NOT NULL DEFAULT 'all',
            score_per_credit INT(11) NOT NULL DEFAULT 100,
            claim_credits_score INT(11) DEFAULT 0 COMMENT 'Score required to claim credits (0 = disabled)',
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_game (game_name),
            INDEX idx_is_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // Insert default
        $conn->query("INSERT INTO score_shop_settings (game_name, score_per_credit, claim_credits_score, is_active) VALUES ('all', 100, 0, 1)");
    } else {
        // Check if claim_credits_score column exists
        $check_col = $conn->query("SHOW COLUMNS FROM score_shop_settings LIKE 'claim_credits_score'");
        if ($check_col->num_rows == 0) {
            $conn->query("ALTER TABLE score_shop_settings ADD COLUMN claim_credits_score INT(11) DEFAULT 0 COMMENT 'Score required to claim credits (0 = disabled)'");
        }
    }
} catch (Exception $e) {
    // Table might already exist
}

// Check and add available_score column to user_profile
try {
    $check_col = $conn->query("SHOW COLUMNS FROM user_profile LIKE 'available_score'");
    if ($check_col->num_rows == 0) {
        $conn->query("ALTER TABLE user_profile ADD COLUMN available_score INT(11) DEFAULT 0 COMMENT 'Total available score across all games that can be used to buy credits'");
    }
} catch (Exception $e) {
    // Column might already exist
}

// Handle update conversion rate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_rate'])) {
    $game_name = trim($_POST['game_name'] ?? 'all');
    $score_per_credit = intval($_POST['score_per_credit'] ?? 100);
    $claim_credits_score = intval($_POST['claim_credits_score'] ?? 0);
    
    if ($score_per_credit <= 0) {
        $error = "Score per credit must be greater than 0.";
    } else {
        $stmt = $conn->prepare("INSERT INTO score_shop_settings (game_name, score_per_credit, claim_credits_score, is_active) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE score_per_credit = ?, claim_credits_score = ?, updated_at = NOW()");
        $stmt->bind_param("siiiii", $game_name, $score_per_credit, $claim_credits_score, $score_per_credit, $claim_credits_score);
        
        if ($stmt->execute()) {
            $desc = "Updated score shop rate for " . ($game_name === 'all' ? 'All Games' : $game_name) . " to {$score_per_credit} score per credit" . ($claim_credits_score > 0 ? " and claim credits score to {$claim_credits_score}" : "");
            $conn->query("INSERT INTO admin_logs (admin_id, admin_username, action, description, ip_address) VALUES ({$_SESSION['admin_id']}, '{$_SESSION['admin_username']}', 'update_score_shop', '$desc', '{$_SERVER['REMOTE_ADDR']}')");
            $message = "Score shop settings updated successfully!";
        } else {
            $error = "Failed to update settings.";
        }
        $stmt->close();
    }
}

$available_games = [
    'all' => 'All Games', 
    'earth-defender' => '🛡️ Earth Defender',
    'cosmos-captain' => '🚀 Cosmos Captain'
];
$settings = $conn->query("SELECT * FROM score_shop_settings ORDER BY game_name ASC")->fetch_all(MYSQLI_ASSOC);
$settings_map = [];
foreach ($settings as $s) {
    $settings_map[$s['game_name']] = $s;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Score Shop Settings - Astraden Admin</title>
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
            --color-shop: #fbbf24;
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
        .ic-shop { color: var(--color-shop); text-shadow: 0 0 10px var(--color-shop); }
        .menu-item:hover, .menu-item.active { background: rgba(255, 255, 255, 0.05); color: white; border-left-color: var(--primary-cyan); }
        .sidebar-footer { padding: 20px; border-top: 1px solid rgba(0, 255, 255, 0.1); }
        .logout-btn { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 12px; background: rgba(255, 0, 110, 0.1); border: 1px solid #ff006e; color: #ff006e; text-decoration: none; border-radius: 8px; font-family: 'Orbitron', sans-serif; font-size: 0.8rem; font-weight: 700; }

        .main-content { margin-left: var(--sidebar-width); flex: 1; padding: 40px; }
        .section-title { font-family: 'Orbitron', sans-serif; color: var(--primary-cyan); margin-bottom: 30px; letter-spacing: 3px; }

        .config-card { background: var(--card-bg); border: 1px solid rgba(0, 255, 255, 0.2); border-radius: 20px; padding: 35px; margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; color: var(--primary-purple); font-weight: 700; font-size: 0.8rem; margin-bottom: 8px; }
        .form-group input, .form-group select { width: 100%; padding: 12px; background: rgba(0,0,0,0.5); border: 1px solid rgba(0,255,255,0.3); border-radius: 8px; color: white; outline: none; font-family: 'Rajdhani'; }

        .btn-update { background: linear-gradient(135deg, var(--primary-cyan), var(--primary-purple)); border: none; color: white; padding: 15px; border-radius: 10px; font-family: 'Orbitron', sans-serif; font-weight: 900; width: 100%; cursor: pointer; }

        .table-card { background: var(--card-bg); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 15px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
        th { color: var(--primary-purple); font-family: 'Orbitron', sans-serif; font-size: 0.7rem; text-transform: uppercase; background: rgba(0,0,0,0.2); }

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
            <a href="admin_dashboard.php" class="menu-item"><i class="fas fa-chart-line"></i> <span>Overview</span></a>
            <div class="menu-category">User Control</div>
            <a href="admin_view_all_users.php" class="menu-item"><i class="fas fa-users"></i> <span>User Directory</span></a>
            <a href="admin_password_reset_requests.php" class="menu-item"><i class="fas fa-key"></i> <span>Reset Requests</span></a>
            <div class="menu-category">Financials</div>
            <a href="admin_transaction_codes.php" class="menu-item"><i class="fas fa-qrcode"></i> <span>Verify Payments</span></a>
            <a href="admin_user_credits.php" class="menu-item"><i class="fas fa-coins"></i> <span>Manual Credits</span></a>
            <a href="admin_credit_pricing.php" class="menu-item"><i class="fas fa-tags"></i> <span>Pricing Plans</span></a>
            <a href="admin_credit_timing.php" class="menu-item"><i class="fas fa-clock"></i> <span>Purchase Timing</span></a>
            <a href="admin_credit_sale_limit.php" class="menu-item"><i class="fas fa-gauge-high"></i> <span>Sale Limits</span></a>
            <div class="menu-category">Game Operations</div>
            <a href="admin_game_timing.php" class="menu-item"><i class="fas fa-calendar-check"></i> <span>Game Sessions</span></a>
            <a href="admin_game_credits.php" class="menu-item"><i class="fas fa-gamepad"></i> <span>Play Costs</span></a>
            <a href="admin_game_leaderboard.php" class="menu-item"><i class="fas fa-ranking-star"></i> <span>Leaderboards</span></a>
            <a href="admin_score_shop.php" class="menu-item active"><i class="fas fa-store ic-shop"></i> <span>Score Shop</span></a>
        </div>
        <div class="sidebar-footer"><a href="admin_logout.php" class="logout-btn"><i class="fas fa-power-off"></i> <span>Authorization Exit</span></a></div>
    </nav>

    <main class="main-content">
        <h2 class="section-title"><i class="fas fa-store ic-shop" style="margin-right:15px;"></i> SCORE SHOP SETTINGS</h2>

        <?php if($message): ?><div class="msg"><?php echo $message; ?></div><?php endif; ?>
        <?php if($error): ?><div class="error-msg"><?php echo $error; ?></div><?php endif; ?>

        <div class="config-card">
            <form method="POST">
                <div class="form-group">
                    <label>SELECT GAME</label>
                    <select name="game_name">
                        <?php foreach($available_games as $k=>$v): ?>
                            <option value="<?php echo $k; ?>"><?php echo $v; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>SCORE PER CREDIT (⚡)</label>
                    <input type="number" name="score_per_credit" value="<?php echo isset($settings_map['all']) ? $settings_map['all']['score_per_credit'] : 100; ?>" min="1" required>
                    <small style="color:rgba(255,255,255,0.4);display:block;margin-top:5px;">How many score points = 1 credit (e.g., 100 score = 1 credit)</small>
                </div>
                <div class="form-group">
                    <label>CLAIM CREDITS SCORE (🎁)</label>
                    <input type="number" name="claim_credits_score" value="<?php echo isset($settings_map['all']) ? ($settings_map['all']['claim_credits_score'] ?? 0) : 0; ?>" min="0" required>
                    <small style="color:rgba(255,255,255,0.4);display:block;margin-top:5px;">Score required to claim credits (set 0 to disable claim feature)</small>
                </div>
                <button type="submit" name="update_rate" class="btn-update">UPDATE SETTINGS</button>
            </form>
        </div>

        <div class="table-card">
            <table>
                <thead><tr><th>Game</th><th>Score Per Credit</th><th>Claim Credits Score</th><th>Status</th><th>Last Updated</th></tr></thead>
                <tbody>
                    <?php foreach($settings as $s): ?>
                    <tr>
                        <td><strong style="color:var(--primary-cyan);"><?php echo $available_games[$s['game_name']] ?? $s['game_name']; ?></strong></td>
                        <td><span style="color:var(--color-shop);font-weight:bold;"><?php echo $s['score_per_credit']; ?> Score = 1 ⚡</span></td>
                        <td><span style="color:#9d4edd;font-weight:bold;"><?php echo ($s['claim_credits_score'] ?? 0) > 0 ? $s['claim_credits_score'] . ' Score' : 'Disabled'; ?></span></td>
                        <td><span style="color:#00ffcc;font-weight:bold;font-size:0.75rem;"><?php echo $s['is_active'] ? 'ACTIVE' : 'INACTIVE'; ?></span></td>
                        <td style="font-size:0.8rem;color:rgba(255,255,255,0.4);"><?php echo $s['updated_at'] ? date('M d, H:i', strtotime($s['updated_at'])) : date('M d, H:i', strtotime($s['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>

