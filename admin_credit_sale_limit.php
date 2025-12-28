<?php
session_start();
require_once 'admin_check.php';
require_once 'connection.php';

$message = '';
$error = '';

// Handle updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_limit'])) {
        $total_limit = intval($_POST['total_limit'] ?? 0);
        $is_enabled = isset($_POST['is_enabled']) ? 1 : 0;
        $sale_mode = in_array($_POST['sale_mode'] ?? '', ['timing', 'limit']) ? $_POST['sale_mode'] : 'limit';
        
        if ($total_limit > 0) {
            $stmt = $conn->prepare("UPDATE credit_sale_limit SET total_limit = ?, is_enabled = ?, sale_mode = ? WHERE id = 1");
            $stmt->bind_param("iis", $total_limit, $is_enabled, $sale_mode);
            $stmt->execute();
            $message = "Sale limits updated successfully!";
        }
    }
    
    if (isset($_POST['reset_sale_count'])) {
        $conn->query("UPDATE credit_sale_limit SET last_reset_at = NOW() WHERE id = 1");
        $message = "Sale count reset successfully!";
    }
}

// Get limit settings
$limit_data = $conn->query("SELECT * FROM credit_sale_limit WHERE id = 1")->fetch_assoc();
$last_reset_at = $limit_data['last_reset_at'] ?? '2000-01-01 00:00:00';

// Calculate total sold since last reset
$sold_data = $conn->query("SELECT COALESCE(SUM(CASE WHEN transaction_code = '150' THEN 150 WHEN transaction_code = '100' THEN 100 ELSE CAST(transaction_code AS UNSIGNED) END), 0) as total_sold FROM transaction_codes WHERE status = 'verified' AND created_at > '$last_reset_at'")->fetch_assoc();
$total_sold = intval($sold_data['total_sold']);
$total_limit = intval($limit_data['total_limit']);
$remaining = max(0, $total_limit - $total_sold);
$percentage = $total_limit > 0 ? round(($total_sold / $total_limit) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sale Limits - Astraden Admin</title>
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
        .section-title { font-family: 'Orbitron', sans-serif; color: var(--primary-cyan); margin-bottom: 30px; letter-spacing: 3px; }

        .progress-card { background: var(--card-bg); border: 1px solid rgba(0, 255, 255, 0.2); border-radius: 20px; padding: 35px; margin-bottom: 30px; text-align: center; }
        .progress-circle { width: 150px; height: 150px; border: 5px solid rgba(255,255,255,0.05); border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; position: relative; }
        .progress-val { font-family: 'Orbitron'; font-size: 2.5rem; font-weight: 900; color: var(--primary-cyan); }
        .progress-bar-container { width: 100%; height: 12px; background: rgba(255,255,255,0.05); border-radius: 10px; margin-top: 20px; overflow: hidden; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, var(--primary-cyan), var(--primary-purple)); transition: 1s; }

        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 30px; }
        .stat-item { background: rgba(0,0,0,0.3); padding: 15px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05); }
        .stat-label { font-size: 0.75rem; color: rgba(255,255,255,0.4); text-transform: uppercase; margin-bottom: 5px; display: block; }
        .stat-value { font-family: 'Orbitron'; font-size: 1.2rem; font-weight: bold; }

        .config-card { background: var(--card-bg); border: 1px solid rgba(0, 255, 255, 0.2); border-radius: 20px; padding: 35px; margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; color: var(--primary-purple); font-weight: 700; font-size: 0.8rem; margin-bottom: 8px; }
        .form-group input, .form-group select { width: 100%; padding: 12px; background: rgba(0,0,0,0.5); border: 1px solid rgba(0,255,255,0.3); border-radius: 8px; color: white; outline: none; }

        .btn-update { background: linear-gradient(135deg, var(--primary-cyan), var(--primary-purple)); border: none; color: white; padding: 15px; border-radius: 10px; font-family: 'Orbitron', sans-serif; font-weight: 900; width: 100%; cursor: pointer; }
        .btn-reset { background: rgba(255, 0, 110, 0.1); border: 1px solid #ff006e; color: #ff006e; padding: 12px; border-radius: 10px; font-family: 'Orbitron', sans-serif; font-size: 0.75rem; font-weight: 700; width: 100%; cursor: pointer; margin-top: 20px; }

        .msg { padding: 15px; border-radius: 10px; margin-bottom: 25px; background: rgba(0, 255, 204, 0.1); border: 1px solid #00ffcc; color: #00ffcc; font-weight: bold; }
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
            <a href="admin_user_credits.php" class="menu-item"><i class="fas fa-coins ic-credits"></i> <span>Manual Astrons</span></a>
            <a href="admin_credit_pricing.php" class="menu-item"><i class="fas fa-tags ic-pricing"></i> <span>Pricing Plans</span></a>
            <a href="admin_credit_timing.php" class="menu-item"><i class="fas fa-clock ic-timing"></i> <span>Purchase Timing</span></a>
            <a href="admin_credit_sale_limit.php" class="menu-item active"><i class="fas fa-gauge-high ic-limits"></i> <span>Sale Limits</span></a>
            <div class="menu-category">Game Operations</div>
            <a href="admin_game_timing.php" class="menu-item"><i class="fas fa-calendar-check ic-sessions"></i> <span>Game Sessions</span></a>
            <a href="admin_contest_management.php" class="menu-item"><i class="fas fa-trophy ic-contest"></i> <span>Contest Control</span></a>
            <a href="admin_game_credits.php" class="menu-item"><i class="fas fa-gamepad ic-costs"></i> <span>Play Costs</span></a>
            <a href="admin_game_prizes.php" class="menu-item"><i class="fas fa-award ic-prizes"></i> <span>Prize Setup</span></a>
            <a href="admin_game_leaderboard.php" class="menu-item"><i class="fas fa-ranking-star ic-leaderboard"></i> <span>Leaderboards</span></a>
        </div>
        <div class="sidebar-footer"><a href="admin_logout.php" class="logout-btn"><i class="fas fa-power-off"></i> <span>Authorization Exit</span></a></div>
    </nav>

    <main class="main-content">
        <h2 class="section-title"><i class="fas fa-tachometer-alt ic-limits" style="margin-right:15px;"></i> SALE LIMITS</h2>

        <?php if($message): ?><div class="msg"><?php echo $message; ?></div><?php endif; ?>

        <div class="progress-card">
            <div class="progress-circle">
                <span class="progress-val">∞</span>
            </div>
            <p style="font-size:1.1rem;color:rgba(255,255,255,0.8);">UNLIMITED ASTRONS</p>
            <div class="progress-bar-container">
                <div class="progress-fill" style="width: 100%; background: linear-gradient(90deg, #00ff00, #00ffff);"></div>
            </div>
            <div class="stats-grid">
                <div class="stat-item"><span class="stat-label">Status</span><div class="stat-value" style="color:#00ff00;">UNLIMITED ⚡</div></div>
                <div class="stat-item"><span class="stat-label">Consumed</span><div class="stat-value" style="color:var(--color-reset);"><?php echo number_format($total_sold); ?> ⚡</div></div>
                <div class="stat-item"><span class="stat-label">Remaining</span><div class="stat-value" style="color:#00ff00;">∞ ⚡</div></div>
            </div>
        </div>

        <div class="config-card">
            <form method="POST">
                <div class="form-group"><label>TOTAL MISSION CREDIT LIMIT</label><input type="number" name="total_limit" value="<?php echo $total_limit; ?>" required></div>
                <div class="form-group">
                    <label>OPERATION MODE</label>
                    <select name="sale_mode">
                        <option value="limit" <?php echo $limit_data['sale_mode']==='limit'?'selected':''; ?>>LIMIT-BASED (DISPLAY REMAINING)</option>
                        <option value="timing" <?php echo $limit_data['sale_mode']==='timing'?'selected':''; ?>>TIMING-BASED (DISPLAY SCHEDULE)</option>
                    </select>
                </div>
                <div style="margin-bottom:20px;padding:15px;background:rgba(0,255,0,0.1);border:2px solid #00ff00;border-radius:8px;">
                    <p style="color:#00ff00;font-weight:bold;margin:0;">⚠️ UNLIMITED MODE ACTIVE</p>
                    <p style="color:rgba(255,255,255,0.7);font-size:0.85rem;margin:5px 0 0 0;">Credit limits are disabled. Users can claim unlimited Astrons.</p>
                </div>
                <div style="margin-bottom:20px;display:flex;align-items:center;gap:10px;opacity:0.5;">
                    <input type="checkbox" name="is_enabled" disabled>
                    <label style="font-size:0.8rem;font-weight:bold;color:rgba(255,255,255,0.5);">ENFORCE QUOTA CHECKING (DISABLED - UNLIMITED MODE)</label>
                </div>
                <button type="submit" name="update_limit" class="btn-update" disabled style="opacity:0.5;cursor:not-allowed;">LIMITS DISABLED</button>
            </form>
            
            <form method="POST" onsubmit="return confirm('RESET QUOTA COUNTER? (User credits will remain safe)');">
                <button type="submit" name="reset_sale_count" class="btn-reset">PURGE QUOTA COUNTER</button>
            </form>
        </div>
    </main>
</body>
</html>
