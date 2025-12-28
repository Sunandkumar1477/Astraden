<?php
session_start();
require_once 'admin_check.php';
require_once 'connection.php';

$message = '';
$error = '';

// Auto-add missing columns
$check_column = $conn->query("SHOW COLUMNS FROM game_leaderboard LIKE 'game_mode'");
if ($check_column->num_rows == 0) {
    $conn->query("ALTER TABLE game_leaderboard ADD COLUMN game_mode ENUM('money', 'credits') DEFAULT 'money'");
}
$check_column->close();

$available_games = ['earth-defender' => '🛡️ Earth Defender'];
$selected_game_view = $_GET['game_view'] ?? 'all';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_all_scores'])) {
    if (($_POST['confirm_text'] ?? '') === 'CLEAR ALL') {
        $game_clear = $_POST['game_name_clear'] ?? '';
        // Validate game name
        if (array_key_exists($game_clear, $available_games)) {
            $clear_stmt = $conn->prepare("DELETE FROM game_leaderboard WHERE game_name = ?");
            $clear_stmt->bind_param("s", $game_clear);
            if ($clear_stmt->execute()) {
                $message = "Leaderboard purged for " . $available_games[$game_clear];
            } else {
                $error = "Failed to purge leaderboard: " . $clear_stmt->error;
            }
            $clear_stmt->close();
        } else {
            $error = "Invalid game name";
        }
    }
}

// Get Leaderboard Data
$selected_mode = $_GET['mode_view'] ?? 'all';

// Build query conditions safely
$where_conditions = ["gl.credits_used > 0"];
$params = [];
$types = "";

if ($selected_game_view !== 'all' && array_key_exists($selected_game_view, $available_games)) {
    $where_conditions[] = "gl.game_name = ?";
    $params[] = $selected_game_view;
    $types .= "s";
}

if ($selected_mode !== 'all' && in_array($selected_mode, ['money', 'credits'])) {
    $where_conditions[] = "gl.game_mode = ?";
    $params[] = $selected_mode;
    $types .= "s";
}

$where_sql = implode(" AND ", $where_conditions);

// Build the SQL query - use WHERE clause instead of JOIN conditions
$sql = "SELECT u.id, u.username, up.full_name, COALESCE(SUM(gl.score), 0) as total_points, COUNT(gl.id) as games_played, COALESCE(up.credits, 0) as credits 
        FROM users u
        LEFT JOIN user_profile up ON u.id = up.user_id
        LEFT JOIN game_leaderboard gl ON u.id = gl.user_id
        WHERE " . $where_sql . "
        GROUP BY u.id, u.username, up.full_name, up.credits
        HAVING total_points > 0
        ORDER BY total_points DESC";

// Execute query with prepared statement
$stmt = $conn->prepare($sql);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $leaderboard = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
} else {
    $error = "Query preparation failed: " . $conn->error;
    $leaderboard = [];
}

$user_id = intval($_GET['user_id'] ?? 0);
$selected_user = null;
if ($user_id > 0) {
    $user_stmt = $conn->prepare("SELECT u.*, up.* FROM users u LEFT JOIN user_profile up ON u.id = up.user_id WHERE u.id = ?");
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $selected_user = $user_result->fetch_assoc();
    $user_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboards - Astraden Admin</title>
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

        .config-card { background: var(--card-bg); border: 1px solid rgba(255, 0, 110, 0.2); border-radius: 20px; padding: 25px; margin-bottom: 30px; }
        .config-card h3 { font-family: 'Orbitron'; color: #ff006e; font-size: 0.9rem; margin-bottom: 15px; }
        .purge-form { display: flex; gap: 10px; }
        .purge-form input { flex: 1; padding: 10px; background: rgba(0,0,0,0.5); border: 1px solid #ff006e; border-radius: 8px; color: #ff006e; text-align: center; font-weight: bold; }
        .btn-purge { background: #ff006e; color: white; border: none; padding: 0 20px; border-radius: 8px; font-family: 'Orbitron'; font-weight: bold; cursor: pointer; }

        .filter-row { margin-bottom: 20px; }
        .filter-row select { padding: 10px 20px; background: var(--card-bg); border: 1px solid var(--primary-cyan); border-radius: 8px; color: var(--primary-cyan); font-family: 'Orbitron'; font-size: 0.8rem; cursor: pointer; outline: none; }

        .table-card { background: var(--card-bg); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 15px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
        th { color: var(--primary-purple); font-family: 'Orbitron', sans-serif; font-size: 0.7rem; text-transform: uppercase; background: rgba(0,0,0,0.2); }
        
        .rank-id { width: 40px; height: 40px; border: 1px solid var(--primary-cyan); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-family: 'Orbitron'; font-weight: bold; color: var(--primary-cyan); }
        .points-val { color: #00ffcc; font-family: 'Orbitron'; font-weight: bold; }
        .view-btn { color: var(--primary-cyan); text-decoration: none; font-size: 0.8rem; font-weight: bold; }

        /* User Modal */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.9); display: none; justify-content: center; align-items: center; z-index: 10000; backdrop-filter: blur(5px); }
        .modal-overlay.show { display: flex; }
        .modal { background: var(--card-bg); border: 2px solid var(--primary-cyan); border-radius: 20px; padding: 40px; max-width: 500px; width: 90%; position: relative; }
        .close-modal { position: absolute; top: 20px; right: 20px; color: #ff006e; cursor: pointer; font-size: 1.5rem; }
        .info-card { background: rgba(255,255,255,0.03); padding: 15px; border-radius: 12px; margin-bottom: 15px; border-left: 3px solid var(--primary-cyan); }
        .info-label { font-size: 0.7rem; color: rgba(255,255,255,0.4); text-transform: uppercase; margin-bottom: 5px; display: block; }
        .info-val { font-size: 1.1rem; font-weight: bold; color: white; }

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
            <a href="admin_shop_pricing.php" class="menu-item"><i class="fas fa-store ic-shop"></i> <span>Shop Pricing</span></a>
            <div class="menu-category">Game Operations</div>
            <a href="admin_game_credits.php" class="menu-item"><i class="fas fa-gamepad ic-costs"></i> <span>Play Costs</span></a>
            <a href="admin_game_leaderboard.php" class="menu-item active"><i class="fas fa-ranking-star ic-leaderboard"></i> <span>Leaderboards</span></a>
        </div>
        <div class="sidebar-footer"><a href="admin_logout.php" class="logout-btn"><i class="fas fa-power-off"></i> <span>Authorization Exit</span></a></div>
    </nav>

    <main class="main-content">
        <h2 class="section-title"><i class="fas fa-star ic-leaderboard" style="margin-right:15px;"></i> LEADERBOARDS</h2>

        <?php if($message): ?><div class="msg"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if($error): ?><div class="msg" style="background: rgba(255, 0, 0, 0.1); border-color: #ff0000; color: #ff0000;"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <div class="config-card">
            <h3>PURGE LEADERBOARD DATA</h3>
            <form method="POST" class="purge-form" onsubmit="return confirm('WARNING: THIS ACTION CANNOT BE UNDONE. PURGE DATA?');">
                <select name="game_name_clear" style="padding:10px;background:rgba(0,0,0,0.5);border:1px solid #ff006e;color:white;border-radius:8px;">
                    <?php foreach($available_games as $k=>$v): ?><option value="<?php echo $k; ?>"><?php echo $v; ?></option><?php endforeach; ?>
                    </select>
                <input type="text" name="confirm_text" placeholder="TYPE 'CLEAR ALL' TO CONFIRM" required>
                <button type="submit" name="clear_all_scores" class="btn-purge">PURGE NOW</button>
            </form>
        </div>

        <div class="filter-row" style="display: flex; gap: 15px;">
            <select onchange="const urlParams = new URLSearchParams(window.location.search); urlParams.set('game_view', this.value); window.location.search = urlParams.toString();">
                <option value="all" <?php echo $selected_game_view==='all'?'selected':''; ?>>ALL GAMES</option>
                <?php foreach($available_games as $k=>$v): ?><option value="<?php echo $k; ?>" <?php echo $selected_game_view===$k?'selected':''; ?>><?php echo strtoupper($v); ?></option><?php endforeach; ?>
            </select>
            
            <select onchange="const urlParams = new URLSearchParams(window.location.search); urlParams.set('mode_view', this.value); window.location.search = urlParams.toString();">
                <option value="all" <?php echo $selected_mode==='all'?'selected':''; ?>>ALL CONTESTS</option>
                <option value="credits" <?php echo $selected_mode==='credits'?'selected':''; ?>>⚡ ASTRONS CONTESTS ONLY</option>
            </select>
        </div>

        <div class="table-card">
        <table>
                <thead><tr><th>Rank</th><th>Player Identity</th><th>Combat Score</th><th>Missions</th><th>Balance</th><th>Details</th></tr></thead>
            <tbody>
                    <?php if (empty($leaderboard)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px; color: rgba(255, 255, 255, 0.3);">
                            No leaderboard data found
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php $rank=1; foreach($leaderboard as $l): ?>
                    <tr>
                        <td><div class="rank-id"><?php echo $rank++; ?></div></td>
                        <td><strong style="color:var(--primary-cyan);"><?php echo htmlspecialchars($l['username'] ?? 'Unknown'); ?></strong><br><small style="color:rgba(255,255,255,0.4);"><?php echo htmlspecialchars($l['full_name'] ?? ''); ?></small></td>
                        <td class="points-val"><?php echo number_format($l['total_points'] ?? 0); ?></td>
                        <td><?php echo $l['games_played'] ?? 0; ?></td>
                        <td style="color:#FFD700;font-weight:bold;"><?php echo number_format($l['credits'] ?? 0); ?> ⚡</td>
                        <td><a href="?user_id=<?php echo $l['id']; ?>&game_view=<?php echo htmlspecialchars($selected_game_view); ?>&mode_view=<?php echo htmlspecialchars($selected_mode); ?>" class="view-btn">VIEW INTEL</a></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
            </tbody>
        </table>
    </div>
    </main>

    <?php if($selected_user): ?>
    <div class="modal-overlay show" onclick="window.location.href='admin_game_leaderboard.php?game_view=<?php echo $selected_game_view; ?>'">
        <div class="modal" onclick="event.stopPropagation()">
            <i class="fas fa-times close-modal" onclick="window.location.href='admin_game_leaderboard.php?game_view=<?php echo $selected_game_view; ?>'"></i>
            <h2 style="font-family:'Orbitron';color:var(--primary-cyan);margin-bottom:25px;">PLAYER INTEL</h2>
            <div class="info-card"><span class="info-label">IDENTIFIER</span><div class="info-val"><?php echo htmlspecialchars($selected_user['username']); ?></div></div>
            <div class="info-card"><span class="info-label">CONTACT</span><div class="info-val"><?php echo htmlspecialchars($selected_user['mobile_number']); ?></div></div>
            <div class="info-card"><span class="info-label">PAYMENT CHANNEL</span><div class="info-val" style="color:#FFD700;">PP: <?php echo $selected_user['phone_pay_number'] ?: 'N/A'; ?> | GP: <?php echo $selected_user['google_pay_number'] ?: 'N/A'; ?></div></div>
            <div class="info-card"><span class="info-label">CURRENT RESERVE</span><div class="info-val" style="color:#00ffcc;"><?php echo number_format($selected_user['credits']); ?> ⚡</div></div>
            <div class="info-card"><span class="info-label">REGION</span><div class="info-val"><?php echo htmlspecialchars($selected_user['state'] ?: 'UNKNOWN SECTOR'); ?></div></div>
        </div>
    </div>
    <?php endif; ?>
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
    </script>
</body>
</html>
