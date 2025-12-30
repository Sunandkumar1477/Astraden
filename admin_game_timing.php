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
        $delete_stmt = $conn->prepare("DELETE FROM game_sessions WHERE id = ?");
        $delete_stmt->bind_param("i", $session_id);
        if ($delete_stmt->execute()) {
            $message = "Session deleted successfully.";
        } else {
            $error = "Failed to delete session.";
        }
        $delete_stmt->close();
    }
}

date_default_timezone_set('Asia/Kolkata');
$available_games = ['earth-defender' => '🛡️ Earth Defender'];

// Check and add always_available column
try {
    $check_col = $conn->query("SHOW COLUMNS FROM game_sessions LIKE 'always_available'");
    if ($check_col->num_rows == 0) {
        $conn->query("ALTER TABLE game_sessions ADD COLUMN always_available TINYINT(1) DEFAULT 0");
    }
} catch (Exception $e) {
    // Silently handle errors - column might already exist
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_session'])) {
    $session_id = intval($_POST['session_id'] ?? 0);
    $game_name = trim($_POST['game_name'] ?? 'earth-defender');
    $always_available = isset($_POST['always_available']) ? 1 : 0;
    $credits_required = intval($_POST['credits_required'] ?? 30);
    $credits_per_chance = intval($_POST['credits_per_chance'] ?? 30);
    $start_date = trim($_POST['start_date'] ?? '');
    $start_time = trim($_POST['start_time'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');
    $end_time = trim($_POST['end_time'] ?? '');
    
    // Update play credits in games table
    if ($credits_per_chance > 0) {
        $display_name = $game_name === 'earth-defender' ? 'Earth Defender' : ucfirst(str_replace('-', ' ', $game_name));
        $update_game_stmt = $conn->prepare("INSERT INTO games (game_name, display_name, credits_per_chance) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE credits_per_chance = ?, updated_at = NOW()");
        $update_game_stmt->bind_param("ssii", $game_name, $display_name, $credits_per_chance, $credits_per_chance);
        $update_game_stmt->execute();
        $update_game_stmt->close();
    }
    
    if ($always_available) {
        // Always available mode - no time restrictions, uses credits_per_chance from games table
        if ($credits_per_chance <= 0) {
            $error = "Play credits per chance must be greater than 0.";
        } else {
            // Set default values for always available sessions
            $session_date = date('Y-m-d');
            $session_time = '00:00:00';
            $duration = 0; // No duration limit
            // Store play credits in credits_required for always available mode
            $credits_required = $credits_per_chance;
            
            if ($session_id > 0) {
                $stmt = $conn->prepare("UPDATE game_sessions SET game_name=?, session_date=?, session_time=?, duration_minutes=?, credits_required=?, always_available=? WHERE id=?");
                $stmt->bind_param("sssiiii", $game_name, $session_date, $session_time, $duration, $credits_required, $always_available, $session_id);
                $stmt->execute();
                $message = "Session updated - Always Available mode.";
            } else {
                $conn->query("UPDATE game_sessions SET is_active = 0 WHERE game_name = '$game_name'");
                $stmt = $conn->prepare("INSERT INTO game_sessions (game_name, session_date, session_time, duration_minutes, credits_required, always_available, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
                $stmt->bind_param("sssiii", $game_name, $session_date, $session_time, $duration, $credits_required, $always_available);
                $stmt->execute();
                $message = "New session published - Always Available mode.";
            }
        }
    } else {
        // Time-restricted mode - requires date/time fields
        if (!empty($start_date) && !empty($start_time) && !empty($end_date) && !empty($end_time)) {
            $start_dt = new DateTime("$start_date $start_time", new DateTimeZone('Asia/Kolkata'));
            $end_dt = new DateTime("$end_date $end_time", new DateTimeZone('Asia/Kolkata'));
            if ($end_dt < $start_dt) {
                $error = "End date/time must be after start date/time.";
            } else {
                $duration = ($start_dt->diff($end_dt)->days * 24 * 60) + ($start_dt->diff($end_dt)->h * 60) + $start_dt->diff($end_dt)->i;
                $session_date = $start_date;
                $session_time = $start_time;
                // Store play credits in credits_required for time-restricted sessions (will be used when converting to always available)
                $credits_required = $credits_per_chance;

                if ($session_id > 0) {
                    $stmt = $conn->prepare("UPDATE game_sessions SET game_name=?, session_date=?, session_time=?, duration_minutes=?, credits_required=?, always_available=? WHERE id=?");
                    $stmt->bind_param("sssiiii", $game_name, $session_date, $session_time, $duration, $credits_required, $always_available, $session_id);
                    $stmt->execute();
                    $message = "Session updated.";
                } else {
                    $conn->query("UPDATE game_sessions SET is_active = 0 WHERE game_name = '$game_name'");
                    $stmt = $conn->prepare("INSERT INTO game_sessions (game_name, session_date, session_time, duration_minutes, credits_required, always_available, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
                    $stmt->bind_param("sssiii", $game_name, $session_date, $session_time, $duration, $credits_required, $always_available);
                    $stmt->execute();
                    $message = "New session published.";
                }
            }
        } else {
            $error = "Please fill all date and time fields.";
        }
    }
}

$selected_game = $_GET['game'] ?? 'earth-defender';
$edit_session = null;
$current_play_credits = 30; // Default value

// Get current play credits from games table
$game_credits_stmt = $conn->prepare("SELECT credits_per_chance FROM games WHERE game_name = ?");
$game_credits_stmt->bind_param("s", $selected_game);
$game_credits_stmt->execute();
$game_credits_result = $game_credits_stmt->get_result();
if ($game_credits_result->num_rows > 0) {
    $game_credits_row = $game_credits_result->fetch_assoc();
    $current_play_credits = intval($game_credits_row['credits_per_chance']);
}
$game_credits_stmt->close();

if (isset($_GET['edit'])) {
    $edit_stmt = $conn->prepare("SELECT * FROM game_sessions WHERE id = ?");
    $edit_stmt->bind_param("i", $_GET['edit']);
    $edit_stmt->execute();
    $edit_session = $edit_stmt->get_result()->fetch_assoc();
    
    // Get play credits for the game being edited
    if ($edit_session) {
        $edit_game_name = $edit_session['game_name'];
        $edit_game_credits_stmt = $conn->prepare("SELECT credits_per_chance FROM games WHERE game_name = ?");
        $edit_game_credits_stmt->bind_param("s", $edit_game_name);
        $edit_game_credits_stmt->execute();
        $edit_game_credits_result = $edit_game_credits_stmt->get_result();
        if ($edit_game_credits_result->num_rows > 0) {
            $edit_game_credits_row = $edit_game_credits_result->fetch_assoc();
            $current_play_credits = intval($edit_game_credits_row['credits_per_chance']);
        }
        $edit_game_credits_stmt->close();
    }
    
    // Calculate end date and time from start date/time and duration (only for time-restricted sessions)
    if ($edit_session && (!isset($edit_session['always_available']) || $edit_session['always_available'] == 0)) {
        $start_dt = new DateTime($edit_session['session_date'] . ' ' . $edit_session['session_time'], new DateTimeZone('Asia/Kolkata'));
        $end_dt = clone $start_dt;
        $end_dt->modify('+' . $edit_session['duration_minutes'] . ' minutes');
        $edit_session['end_date'] = $end_dt->format('Y-m-d');
        $edit_session['end_time'] = $end_dt->format('H:i');
        $edit_session['start_date'] = $edit_session['session_date'];
        $edit_session['start_time'] = $edit_session['session_time'];
    } elseif ($edit_session && isset($edit_session['always_available']) && $edit_session['always_available'] == 1) {
        // For always available sessions, set default date/time values
        $edit_session['start_date'] = date('Y-m-d');
        $edit_session['start_time'] = '00:00:00';
        $edit_session['end_date'] = date('Y-m-d');
        $edit_session['end_time'] = '23:59:59';
    }
}

$current_session = $conn->query("SELECT * FROM game_sessions WHERE game_name = '$selected_game' AND is_active = 1 LIMIT 1")->fetch_assoc();
$all_sessions = $conn->query("SELECT * FROM game_sessions WHERE game_name = '$selected_game' ORDER BY session_date DESC, session_time DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);

// Get play credits for all sessions to display in table
$session_play_credits = [];
foreach ($all_sessions as $s) {
    $session_game_name = $s['game_name'];
    if (!isset($session_play_credits[$session_game_name])) {
        $session_credits_stmt = $conn->prepare("SELECT credits_per_chance FROM games WHERE game_name = ?");
        $session_credits_stmt->bind_param("s", $session_game_name);
        $session_credits_stmt->execute();
        $session_credits_result = $session_credits_stmt->get_result();
        if ($session_credits_result->num_rows > 0) {
            $session_credits_row = $session_credits_result->fetch_assoc();
            $session_play_credits[$session_game_name] = intval($session_credits_row['credits_per_chance']);
        } else {
            $session_play_credits[$session_game_name] = 30; // Default
        }
        $session_credits_stmt->close();
    }
}

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
        .error-msg { padding: 15px; border-radius: 10px; margin-bottom: 25px; background: rgba(255, 0, 110, 0.1); border: 1px solid #ff006e; color: #ff006e; font-weight: bold; }
        
        .toggle-group { display: flex; align-items: center; gap: 20px; margin-bottom: 25px; background: rgba(255,255,255,0.03); padding: 15px; border-radius: 12px; }
        .switch { position: relative; display: inline-block; width: 60px; height: 30px; flex-shrink: 0; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #333; transition: .4s; border-radius: 34px; border: 1px solid rgba(255,255,255,0.1); }
        .slider:before { position: absolute; content: ""; height: 22px; width: 22px; left: 4px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: var(--primary-cyan); }
        input:checked + .slider:before { transform: translateX(30px); }
        
        .btn-delete { background: rgba(255, 0, 110, 0.1); border: 1px solid #ff006e; color: #ff006e; padding: 6px 12px; border-radius: 6px; font-family: 'Orbitron', sans-serif; font-size: 0.7rem; font-weight: 700; cursor: pointer; text-decoration: none; display: inline-block; margin-left: 10px; }
        .btn-delete:hover { background: rgba(255, 0, 110, 0.2); }
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
            <a href="admin_game_credits.php" class="menu-item"><i class="fas fa-gamepad ic-costs"></i> <span>Play Costs</span></a>
            <a href="admin_game_leaderboard.php" class="menu-item"><i class="fas fa-ranking-star ic-leaderboard"></i> <span>Leaderboards</span></a>
        </div>
        <div class="sidebar-footer"><a href="admin_logout.php" class="logout-btn"><i class="fas fa-power-off"></i> <span>Authorization Exit</span></a></div>
    </nav>

    <main class="main-content">
        <h2 class="section-title"><i class="fas fa-clock ic-sessions" style="margin-right:15px;"></i> GAME SESSIONS</h2>

        <?php if($message): ?><div class="msg"><?php echo $message; ?></div><?php endif; ?>
        <?php if($error): ?><div class="error-msg"><?php echo $error; ?></div><?php endif; ?>

        <?php if($current_session): 
            $is_always_available = isset($current_session['always_available']) && $current_session['always_available'] == 1;
        ?>
        <div class="active-banner">
            <div>
                <h3><?php echo $is_always_available ? 'ALWAYS AVAILABLE - LIVE' : 'LIVE BROADCAST IN PROGRESS'; ?></h3>
                <div class="active-info">
                    <div class="info-bit"><span>Game</span><strong><?php echo $available_games[$selected_game]; ?></strong></div>
                    <?php if ($is_always_available): ?>
                        <div class="info-bit"><span>Mode</span><strong>ALWAYS AVAILABLE</strong></div>
                    <?php else: ?>
                        <div class="info-bit"><span>Opening</span><strong><?php echo date('h:i A', strtotime($current_session['session_time'])); ?> IST</strong></div>
                        <div class="info-bit"><span>Duration</span><strong><?php echo $current_session['duration_minutes']; ?> MIN</strong></div>
                    <?php endif; ?>
                    <div class="info-bit"><span>Play Cost</span><strong><?php echo $current_play_credits; ?> ⚡</strong></div>
                </div>
            </div>
            <a href="?game=<?php echo $selected_game; ?>&edit=<?php echo $current_session['id']; ?>" class="info-bit" style="text-decoration:none;color:var(--primary-cyan);font-weight:bold;">EDIT SESSION</a>
        </div>
        <?php endif; ?>

        <div class="config-card">
            <form method="POST" id="sessionForm">
                <?php if($edit_session): ?><input type="hidden" name="session_id" value="<?php echo $edit_session['id']; ?>"><?php endif; ?>
                <div class="form-group" style="margin-bottom:20px;"><label>TARGET GAME</label><select name="game_name"><?php foreach($available_games as $k=>$v): ?><option value="<?php echo $k; ?>" <?php echo ($edit_session && $edit_session['game_name'] === $k) ? 'selected' : ''; ?>><?php echo $v; ?></option><?php endforeach; ?></select></div>
                
                <div class="toggle-group">
                    <label class="switch">
                        <input type="checkbox" name="always_available" id="always_available_toggle" <?php echo ($edit_session && $edit_session['always_available']) ? 'checked' : ''; ?> onchange="toggleSessionMode(this)">
                        <span class="slider"></span>
                    </label>
                    <div>
                        <strong style="display:block;color:var(--color-sessions);">ALWAYS AVAILABLE</strong>
                        <small style="color:rgba(255,255,255,0.4);">Enable always available mode - no time restrictions, just set credits.</small>
                    </div>
                </div>

                <div id="always_available_section" style="display: <?php echo ($edit_session && $edit_session['always_available']) ? 'block' : 'none'; ?>;">
                    <div class="form-group" style="margin-bottom:25px;">
                        <label>PLAY CREDITS PER CHANCE (⚡)</label>
                        <input type="number" name="credits_per_chance" value="<?php echo $current_play_credits; ?>" min="1" required>
                        <small style="color:rgba(255,255,255,0.4);display:block;margin-top:5px;">Credits cost per game play/chance</small>
                    </div>
                </div>

                <div id="time_restricted_section" style="display: <?php echo ($edit_session && $edit_session['always_available']) ? 'none' : 'block'; ?>;">
                    <div class="form-group" style="margin-bottom:20px;">
                        <label>PLAY CREDITS PER CHANCE (⚡)</label>
                        <input type="number" name="credits_per_chance" value="<?php echo $current_play_credits; ?>" min="1" required>
                        <small style="color:rgba(255,255,255,0.4);display:block;margin-top:5px;">Credits cost per game play/chance</small>
                    </div>
                    <div class="form-grid">
                        <div class="form-group"><label>START DATE</label><input type="date" name="start_date" value="<?php echo $edit_session ? ($edit_session['start_date'] ?? $edit_session['session_date']) : date('Y-m-d'); ?>" id="start_date"></div>
                        <div class="form-group"><label>START TIME (IST)</label><input type="time" name="start_time" value="<?php echo $edit_session ? ($edit_session['start_time'] ?? $edit_session['session_time']) : ''; ?>" id="start_time"></div>
                        <div class="form-group"><label>END DATE</label><input type="date" name="end_date" value="<?php echo $edit_session ? ($edit_session['end_date'] ?? date('Y-m-d')) : date('Y-m-d'); ?>" id="end_date"></div>
                        <div class="form-group"><label>END TIME (IST)</label><input type="time" name="end_time" value="<?php echo $edit_session ? ($edit_session['end_time'] ?? '') : ''; ?>" id="end_time"></div>
                    </div>
                </div>
                <button type="submit" name="set_session" class="btn-publish"><?php echo $edit_session ? 'UPDATE MISSION LOG' : 'PUBLISH LIVE WINDOW'; ?></button>
            </form>
        </div>

        <div class="table-card">
            <table>
                <thead><tr><th>Start Date & Time</th><th>End Date & Time</th><th>Duration</th><th>Play Credits</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach($all_sessions as $s): 
                        $is_always_available = isset($s['always_available']) && $s['always_available'] == 1;
                        if ($is_always_available) {
                            $start_display = 'ALWAYS AVAILABLE';
                            $end_display = 'NO RESTRICTIONS';
                            $duration_display = '∞';
                        } else {
                            $start_dt = new DateTime($s['session_date'] . ' ' . $s['session_time'], new DateTimeZone('Asia/Kolkata'));
                            $end_dt = clone $start_dt;
                            $end_dt->modify('+' . $s['duration_minutes'] . ' minutes');
                            $start_display = date('d M Y, h:i A', strtotime($s['session_date'] . ' ' . $s['session_time'])) . ' IST';
                            $end_display = $end_dt->format('d M Y, h:i A') . ' IST';
                            $hours = floor($s['duration_minutes'] / 60);
                            $minutes = $s['duration_minutes'] % 60;
                            if ($hours > 0) {
                                $duration_display = $hours . 'h ' . $minutes . 'm';
                            } else {
                                $duration_display = $minutes . 'm';
                            }
                        }
                        // Get play credits for this session
                        $session_play_cost = isset($session_play_credits[$s['game_name']]) ? $session_play_credits[$s['game_name']] : 30;
                    ?>
                    <tr>
                        <td><?php echo $start_display; ?></td>
                        <td><?php echo $end_display; ?></td>
                        <td style="color:var(--primary-cyan);font-weight:bold;"><?php echo $duration_display; ?></td>
                        <td style="color:var(--color-credits);font-weight:bold;"><?php echo $session_play_cost; ?> ⚡</td>
                        <td><span class="status-pill <?php echo $s['is_active'] ? 'status-live' : 'status-end'; ?>"><?php echo $s['is_active'] ? 'LIVE' : 'ENDED'; ?></span></td>
                        <td>
                            <a href="?game=<?php echo $selected_game; ?>&edit=<?php echo $s['id']; ?>" style="color:var(--primary-cyan);text-decoration:none;font-weight:bold;font-size:0.8rem;">EDIT</a>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this session?');">
                                <input type="hidden" name="session_id" value="<?php echo $s['id']; ?>">
                                <button type="submit" name="delete_session" class="btn-delete">DELETE</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
    <script>
        function toggleSessionMode(checkbox) {
            const isAlwaysAvailable = checkbox.checked;
            const alwaysAvailableSection = document.getElementById('always_available_section');
            const timeRestrictedSection = document.getElementById('time_restricted_section');
            
            if (isAlwaysAvailable) {
                alwaysAvailableSection.style.display = 'block';
                timeRestrictedSection.style.display = 'none';
                // Remove required attributes from time fields
                document.getElementById('start_date').removeAttribute('required');
                document.getElementById('start_time').removeAttribute('required');
                document.getElementById('end_date').removeAttribute('required');
                document.getElementById('end_time').removeAttribute('required');
            } else {
                alwaysAvailableSection.style.display = 'none';
                timeRestrictedSection.style.display = 'block';
                // Add required attributes to time fields
                document.getElementById('start_date').setAttribute('required', 'required');
                document.getElementById('start_time').setAttribute('required', 'required');
                document.getElementById('end_date').setAttribute('required', 'required');
                document.getElementById('end_time').setAttribute('required', 'required');
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.querySelector('.sidebar-menu');
            if (sidebar) {
                const savedScroll = localStorage.getItem('sidebar_scroll');
                if (savedScroll) sidebar.scrollTop = savedScroll;
                sidebar.addEventListener('scroll', () => { localStorage.setItem('sidebar_scroll', sidebar.scrollTop); });
                const activeItem = sidebar.querySelector('.menu-item.active');
                if (activeItem) activeItem.scrollIntoView({ block: 'center' });
            }
        });
    </script>
</body>
</html>