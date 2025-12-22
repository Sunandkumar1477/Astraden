<?php
session_start();
require_once 'admin_check.php';
require_once 'connection.php';
date_default_timezone_set('Asia/Kolkata');

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_timing'])) {
    $add_credits_date_from = trim($_POST['add_credits_date_from'] ?? '');
    $add_credits_time_from = trim($_POST['add_credits_time_from'] ?? '');
    $add_credits_date_to = trim($_POST['add_credits_date_to'] ?? '');
    $add_credits_time_to = trim($_POST['add_credits_time_to'] ?? '');
    $add_credits_enabled = isset($_POST['add_credits_enabled']) ? 1 : 0;
    
    $claim_credits_date_from = trim($_POST['claim_credits_date_from'] ?? '');
    $claim_credits_time_from = trim($_POST['claim_credits_time_from'] ?? '');
    $claim_credits_date_to = trim($_POST['claim_credits_date_to'] ?? '');
    $claim_credits_time_to = trim($_POST['claim_credits_time_to'] ?? '');
    $claim_credits_enabled = isset($_POST['claim_credits_enabled']) ? 1 : 0;
    
    // Process Add Credits
    $add_stmt = $conn->prepare("INSERT INTO credit_timing (timing_type, date_from, time_from, date_to, time_to, is_enabled) VALUES ('add_credits', ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE date_from=VALUES(date_from), time_from=VALUES(time_from), date_to=VALUES(date_to), time_to=VALUES(time_to), is_enabled=VALUES(is_enabled)");
    $add_stmt->bind_param("ssssi", $add_credits_date_from, $add_credits_time_from, $add_credits_date_to, $add_credits_time_to, $add_credits_enabled);
    $add_stmt->execute();
    
    // Process Claim Credits
    $claim_stmt = $conn->prepare("INSERT INTO credit_timing (timing_type, date_from, time_from, date_to, time_to, is_enabled) VALUES ('claim_credits', ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE date_from=VALUES(date_from), time_from=VALUES(time_from), date_to=VALUES(date_to), time_to=VALUES(time_to), is_enabled=VALUES(is_enabled)");
    $claim_stmt->bind_param("ssssi", $claim_credits_date_from, $claim_credits_time_from, $claim_credits_date_to, $claim_credits_time_to, $claim_credits_enabled);
    $claim_stmt->execute();
    
    $message = "Timing settings updated successfully!";
}

$timings = $conn->query("SELECT * FROM credit_timing")->fetch_all(MYSQLI_ASSOC);
$add_timing = null; $claim_timing = null;
foreach($timings as $t) {
    if($t['timing_type'] === 'add_credits') $add_timing = $t;
    else if($t['timing_type'] === 'claim_credits') $claim_timing = $t;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Timing - Astraden Admin</title>
    <!-- Favicon - Must be early in head for proper display -->
    <link rel="icon" type="image/svg+xml" href="logo.svg">
    <link rel="shortcut icon" type="image/svg+xml" href="logo.svg">
    <link rel="alternate icon" type="image/png" href="logo.svg">
    <link rel="apple-touch-icon" sizes="180x180" href="logo.svg">
    <link rel="icon" type="image/svg+xml" sizes="any" href="logo.svg">
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

        .config-card { background: var(--card-bg); border: 1px solid rgba(0, 255, 255, 0.2); border-radius: 20px; padding: 35px; margin-bottom: 30px; }
        .config-card h3 { font-family: 'Orbitron'; font-size: 1rem; color: var(--primary-purple); margin-bottom: 25px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 10px; }
        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 20px; }
        .form-group label { display: block; color: rgba(255,255,255,0.5); font-weight: 700; font-size: 0.75rem; margin-bottom: 8px; }
        .form-group input { width: 100%; padding: 12px; background: rgba(0,0,0,0.5); border: 1px solid rgba(0,255,255,0.3); border-radius: 8px; color: white; outline: none; }

        .toggle-row { display: flex; align-items: center; gap: 15px; margin-top: 10px; }
        .btn-save { background: linear-gradient(135deg, var(--primary-cyan), var(--primary-purple)); border: none; color: white; padding: 15px 40px; border-radius: 10px; font-family: 'Orbitron', sans-serif; font-weight: 900; cursor: pointer; display: block; margin: 30px auto 0; }

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
            <a href="admin_user_credits.php" class="menu-item"><i class="fas fa-coins ic-credits"></i> <span>Manual Credits</span></a>
            <a href="admin_credit_pricing.php" class="menu-item"><i class="fas fa-tags ic-pricing"></i> <span>Pricing Plans</span></a>
            <a href="admin_credit_timing.php" class="menu-item active"><i class="fas fa-clock ic-timing"></i> <span>Purchase Timing</span></a>
            <a href="admin_credit_sale_limit.php" class="menu-item"><i class="fas fa-gauge-high ic-limits"></i> <span>Sale Limits</span></a>
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
        <h2 class="section-title"><i class="fas fa-user-clock ic-timing" style="margin-right:15px;"></i> PURCHASE TIMING</h2>

        <?php if($message): ?><div class="msg"><?php echo $message; ?></div><?php endif; ?>

        <form method="POST">
            <div class="config-card">
                <h3><i class="fas fa-plus-circle" style="margin-right:10px;"></i> ADD CREDITS WINDOW</h3>
                <div class="form-grid">
                    <div class="form-group"><label>DATE FROM</label><input type="date" name="add_credits_date_from" value="<?php echo $add_timing['date_from'] ?? ''; ?>"></div>
                    <div class="form-group"><label>TIME FROM</label><input type="time" name="add_credits_time_from" value="<?php echo $add_timing['time_from'] ?? ''; ?>"></div>
                    <div class="form-group"><label>DATE TO</label><input type="date" name="add_credits_date_to" value="<?php echo $add_timing['date_to'] ?? ''; ?>"></div>
                    <div class="form-group"><label>TIME TO</label><input type="time" name="add_credits_time_to" value="<?php echo $add_timing['time_to'] ?? ''; ?>"></div>
                </div>
                <div class="toggle-row">
                    <input type="checkbox" name="add_credits_enabled" <?php echo ($add_timing['is_enabled']??1)?'checked':''; ?>>
                    <label style="font-size:0.8rem;font-weight:bold;">ENABLE DEPOSIT ACCESS</label>
                </div>
            </div>

            <div class="config-card">
                <h3><i class="fas fa-gift" style="margin-right:10px;"></i> CLAIM CREDITS WINDOW</h3>
                <div class="form-grid">
                    <div class="form-group"><label>DATE FROM</label><input type="date" name="claim_credits_date_from" value="<?php echo $claim_timing['date_from'] ?? ''; ?>"></div>
                    <div class="form-group"><label>TIME FROM</label><input type="time" name="claim_credits_time_from" value="<?php echo $claim_timing['time_from'] ?? ''; ?>"></div>
                    <div class="form-group"><label>DATE TO</label><input type="date" name="claim_credits_date_to" value="<?php echo $claim_timing['date_to'] ?? ''; ?>"></div>
                    <div class="form-group"><label>TIME TO</label><input type="time" name="claim_credits_time_to" value="<?php echo $claim_timing['time_to'] ?? ''; ?>"></div>
                </div>
                <div class="toggle-row">
                    <input type="checkbox" name="claim_credits_enabled" <?php echo ($claim_timing['is_enabled']??1)?'checked':''; ?>>
                    <label style="font-size:0.8rem;font-weight:bold;">ENABLE REWARD CLAIMING</label>
                </div>
            </div>

            <button type="submit" name="save_timing" class="btn-save">SYNC ALL WINDOWS</button>
        </form>
    </main>
</body>
</html>
