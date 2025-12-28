<?php
session_start();
require_once 'admin_check.php';
require_once 'connection.php';

$message = '';
$error = '';

// Handle delete session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_session'])) {
    $session_id = intval($_POST['session_id'] ?? 0);
    if ($session_id > 0) {
        $conn->query("DELETE FROM game_sessions WHERE id = $session_id");
        $message = "Session deleted.";
    }
}

date_default_timezone_set('Asia/Kolkata');
$available_games = ['earth-defender' => '🛡️ Earth Defender'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_session'])) {
    $session_id = intval($_POST['session_id'] ?? 0);
    $game_name = trim($_POST['game_name'] ?? 'earth-defender');
    $session_date = trim($_POST['session_date'] ?? '');
    $session_time = trim($_POST['session_time'] ?? '');
    $closing_time = trim($_POST['closing_time'] ?? '');
    $credits_required = intval($_POST['credits_required'] ?? 30);
    
    if (!empty($session_date) && !empty($session_time) && !empty($closing_time)) {
        $opening_dt = new DateTime("$session_date $session_time", new DateTimeZone('Asia/Kolkata'));
        $closing_dt = new DateTime("$session_date $closing_time", new DateTimeZone('Asia/Kolkata'));
        if ($closing_dt < $opening_dt) $closing_dt->modify('+1 day');
        $duration = ($opening_dt->diff($closing_dt)->h * 60) + $opening_dt->diff($closing_dt)->i;

        if ($session_id > 0) {
            $stmt = $conn->prepare("UPDATE game_sessions SET game_name=?, session_date=?, session_time=?, duration_minutes=?, credits_required=? WHERE id=?");
            $stmt->bind_param("sssiii", $game_name, $session_date, $session_time, $duration, $credits_required, $session_id);
            $stmt->execute();
            $message = "Session updated.";
        } else {
            $conn->query("UPDATE game_sessions SET is_active = 0 WHERE game_name = '$game_name'");
            $stmt = $conn->prepare("INSERT INTO game_sessions (game_name, session_date, session_time, duration_minutes, credits_required, is_active) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->bind_param("sssii", $game_name, $session_date, $session_time, $duration, $credits_required);
            $stmt->execute();
            $message = "New session published.";
        }
    }
}

$selected_game = $_GET['game'] ?? 'earth-defender';
$edit_session = null;
if (isset($_GET['edit'])) {
    $edit_stmt = $conn->prepare("SELECT * FROM game_sessions WHERE id = ?");
    $edit_stmt->bind_param("i", $_GET['edit']);
    $edit_stmt->execute();
    $edit_session = $edit_stmt->get_result()->fetch_assoc();
}

$current_session = $conn->query("SELECT * FROM game_sessions WHERE game_name = '$selected_game' AND is_active = 1 LIMIT 1")->fetch_assoc();
$all_sessions = $conn->query("SELECT * FROM game_sessions WHERE game_name = '$selected_game' ORDER BY session_date DESC, session_time DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Timing - Astraden Admin</title>
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

        .active-banner { background: rgba(0, 255, 204, 0.05); border: 2px solid #00ffcc; border-radius: 15px; padding: 25px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .active-banner h3 { font-family: 'Orbitron'; color: #00ffcc; font-size: 1rem; margin-bottom: 10px; }
        .active-info { display: flex; gap: 30px; }
        .info-bit span { display: block; font-size: 0.7rem; color: rgba(255,255,255,0.4); text-transform: uppercase; }
        .info-bit strong { font-size: 1rem; color: white; }

        .config-card { background: var(--card-bg); border: 1px solid rgba(0, 255, 255, 0.2); border-radius: 20px; padding: 35px; margin-bottom: 30px; }
        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 20px; }
        .form-group label { display: block; color: var(--primary-purple); font-weight: 700; font-size: 0.8rem; margin-bottom: 8px; }
        .form-group input, .form-group select { width: 100%; padding: 12px; background: rgba(0,0,0,0.5); border: 1px solid rgba(0,255,255,0.3); border-radius: 8px; color: white; outline: none; font-family: 'Rajdhani'; }

        .btn-publish { background: linear-gradient(135deg, var(--primary-cyan), var(--primary-purple)); border: none; color: white; padding: 15px; border-radius: 10px; font-family: 'Orbitron', sans-serif; font-weight: 900; width: 100%; cursor: pointer; }

        .table-card { background: var(--card-bg); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 15px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
        th { color: var(--primary-purple); font-family: 'Orbitron', sans-serif; font-size: 0.7rem; text-transform: uppercase; background: rgba(0,0,0,0.2); }
        
        .status-pill { padding: 4px 10px; border-radius: 4px; font-size: 0.7rem; font-weight: bold; }
        .status-live { background: rgba(0, 255, 204, 0.1); color: #00ffcc; border: 1px solid #00ffcc; }
        .status-end { background: rgba(255, 255, 255, 0.05); color: rgba(255,255,255,0.3); }

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
            <a href="admin_credit_timing.php" class="menu-item"><i class="fas fa-clock ic-timing"></i> <span>Purchase Timing</span></a>
            <a href="admin_credit_sale_limit.php" class="menu-item"><i class="fas fa-gauge-high ic-limits"></i> <span>Sale Limits</span></a>
            <div class="menu-category">Game Operations</div>
            <a href="admin_game_timing.php" class="menu-item active"><i class="fas fa-calendar-check ic-sessions"></i> <span>Game Sessions</span></a>
            <a href="admin_contest_management.php" class="menu-item"><i class="fas fa-trophy ic-contest"></i> <span>Contest Control</span></a>
            <a href="admin_game_credits.php" class="menu-item"><i class="fas fa-gamepad ic-costs"></i> <span>Play Costs</span></a>
            <a href="admin_game_prizes.php" class="menu-item"><i class="fas fa-award ic-prizes"></i> <span>Prize Setup</span></a>
            <a href="admin_game_leaderboard.php" class="menu-item"><i class="fas fa-ranking-star ic-leaderboard"></i> <span>Leaderboards</span></a>
        </div>
        <div class="sidebar-footer"><a href="admin_logout.php" class="logout-btn"><i class="fas fa-power-off"></i> <span>Authorization Exit</span></a></div>
    </nav>

    <main class="main-content">
        <h2 class="section-title"><i class="fas fa-clock ic-sessions" style="margin-right:15px;"></i> GAME SESSIONS</h2>

        <?php if($message): ?><div class="msg"><?php echo $message; ?></div><?php endif; ?>

        <?php if($current_session): ?>
        <div class="active-banner">
            <div>
                <h3>LIVE BROADCAST IN PROGRESS</h3>
                <div class="active-info">
                    <div class="info-bit"><span>Contest</span><strong><?php echo $available_games[$selected_game]; ?></strong></div>
                    <div class="info-bit"><span>Opening</span><strong><?php echo date('h:i A', strtotime($current_session['session_time'])); ?> IST</strong></div>
                    <div class="info-bit"><span>Duration</span><strong><?php echo $current_session['duration_minutes']; ?> MIN</strong></div>
                </div>
            </div>
            <a href="?game=<?php echo $selected_game; ?>&edit=<?php echo $current_session['id']; ?>" class="info-bit" style="text-decoration:none;color:var(--primary-cyan);font-weight:bold;">EDIT SESSION</a>
        </div>
        <?php endif; ?>

        <div class="config-card">
            <form method="POST">
                <?php if($edit_session): ?><input type="hidden" name="session_id" value="<?php echo $edit_session['id']; ?>"><?php endif; ?>
                <div class="form-grid">
                    <div class="form-group"><label>TARGET GAME</label><select name="game_name"><?php foreach($available_games as $k=>$v): ?><option value="<?php echo $k; ?>"><?php echo $v; ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>MISSION DATE</label><input type="date" name="session_date" value="<?php echo $edit_session ? $edit_session['session_date'] : date('Y-m-d'); ?>"></div>
                    <div class="form-group"><label>OPENING WINDOW (IST)</label><input type="time" name="session_time" value="<?php echo $edit_session ? $edit_session['session_time'] : ''; ?>"></div>
                    <div class="form-group"><label>CLOSING WINDOW (IST)</label><input type="time" name="closing_time"></div>
                </div>
                <div class="form-group" style="margin-bottom:25px;"><label>PARTICIPATION FEE (CREDITS)</label><input type="number" name="credits_required" value="<?php echo $edit_session ? $edit_session['credits_required'] : 30; ?>"></div>
                <button type="submit" name="set_session" class="btn-publish"><?php echo $edit_session ? 'UPDATE MISSION LOG' : 'PUBLISH LIVE WINDOW'; ?></button>
            </form>
        </div>

        <div class="table-card">
            <table>
                <thead><tr><th>Mission Date</th><th>Window</th><th>Cost</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach($all_sessions as $s): ?>
                    <tr>
                        <td><?php echo date('d M Y', strtotime($s['session_date'])); ?></td>
                        <td><?php echo date('h:i A', strtotime($s['session_time'])); ?> IST</td>
                        <td style="color:var(--color-credits);font-weight:bold;"><?php echo $s['credits_required']; ?> ⚡</td>
                        <td><span class="status-pill <?php echo $s['is_active'] ? 'status-live' : 'status-end'; ?>"><?php echo $s['is_active'] ? 'LIVE' : 'ENDED'; ?></span></td>
                        <td>
                            <a href="?game=<?php echo $selected_game; ?>&edit=<?php echo $s['id']; ?>" style="color:var(--primary-cyan);text-decoration:none;font-weight:bold;font-size:0.8rem;">EDIT</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>