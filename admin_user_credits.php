<?php
session_start();
require_once 'admin_check.php';
require_once 'connection.php';

$message = '';
$error = '';
$search_query = '';
$search_results = [];

// Handle credit update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_credits'])) {
    $user_id = intval($_POST['user_id'] ?? 0);
    $new_credits = intval($_POST['credits'] ?? 0);
    $action_type = trim($_POST['action_type'] ?? 'set');
    
    if ($user_id > 0 && $new_credits >= 0) {
        $user_data = $conn->query("SELECT username FROM users WHERE id = $user_id")->fetch_assoc();
        if ($user_data) {
            if ($action_type === 'add') {
                $conn->query("UPDATE user_profile SET credits = credits + $new_credits WHERE user_id = $user_id");
                $desc = "Added $new_credits credits to {$user_data['username']}";
            } else {
                $conn->query("UPDATE user_profile SET credits = $new_credits WHERE user_id = $user_id");
                $desc = "Set credits for {$user_data['username']} to $new_credits";
            }
            
            if ($conn->affected_rows === 0 && $action_type === 'set') {
                $conn->query("INSERT INTO user_profile (user_id, credits) VALUES ($user_id, $new_credits)");
            }

            $conn->query("INSERT INTO admin_logs (admin_id, admin_username, action, description, ip_address, target_user_id) VALUES ({$_SESSION['admin_id']}, '{$_SESSION['admin_username']}', 'update_credits', '$desc', '{$_SERVER['REMOTE_ADDR']}', $user_id)");
            $message = "Credits updated for {$user_data['username']}";
        }
    }
}

if (isset($_GET['search'])) {
    $search_query = trim($_GET['search']);
    if ($search_query) {
        $stmt = $conn->prepare("SELECT u.id, u.username, u.mobile_number, u.referral_code, COALESCE(up.credits, 0) as credits FROM users u LEFT JOIN user_profile up ON u.id = up.user_id WHERE u.username LIKE ? OR u.mobile_number LIKE ? LIMIT 50");
        $param = "%$search_query%";
        $stmt->bind_param("ss", $param, $param);
        $stmt->execute();
        $search_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

$top_users = $conn->query("SELECT u.id, u.username, u.mobile_number, u.referral_code, COALESCE(up.credits, 0) as credits FROM users u LEFT JOIN user_profile up ON u.id = up.user_id ORDER BY up.credits DESC LIMIT 20")->fetch_all(MYSQLI_ASSOC);
$stats = $conn->query("SELECT SUM(credits) as total, COUNT(*) as users FROM user_profile")->fetch_assoc();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Credits Management - Astraden Admin</title>
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

        .stats-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .stat-box { background: var(--card-bg); border: 1px solid rgba(0, 255, 255, 0.2); border-radius: 15px; padding: 20px; text-align: center; }
        .stat-box span { color: var(--primary-purple); font-size: 0.8rem; text-transform: uppercase; }
        .stat-box h3 { font-family: 'Orbitron', sans-serif; font-size: 1.8rem; color: white; }

        .search-box { background: var(--card-bg); border: 1px solid rgba(0, 255, 255, 0.2); border-radius: 15px; padding: 20px; margin-bottom: 30px; display: flex; gap: 15px; }
        .search-box input { flex: 1; padding: 12px; background: rgba(0,0,0,0.5); border: 1px solid rgba(0,255,255,0.3); border-radius: 10px; color: white; font-family: 'Rajdhani', sans-serif; outline: none; }
        .search-btn { background: linear-gradient(135deg, var(--primary-cyan), var(--primary-purple)); border: none; color: white; padding: 0 30px; border-radius: 10px; font-family: 'Orbitron', sans-serif; font-weight: 700; cursor: pointer; }

        .table-card { background: var(--card-bg); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 15px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
        th { color: var(--primary-purple); font-family: 'Orbitron', sans-serif; font-size: 0.7rem; text-transform: uppercase; background: rgba(0,0,0,0.2); }
        
        .update-form { display: flex; gap: 8px; }
        .update-form input { width: 80px; padding: 6px; background: rgba(0,0,0,0.5); border: 1px solid rgba(0,255,255,0.3); border-radius: 4px; color: white; font-weight: bold; text-align: center; }
        .update-form select { padding: 6px; background: rgba(0,0,0,0.5); border: 1px solid rgba(0,255,255,0.3); border-radius: 4px; color: var(--primary-cyan); cursor: pointer; }
        .update-btn { background: rgba(0, 255, 255, 0.1); border: 1px solid var(--primary-cyan); color: var(--primary-cyan); padding: 6px 12px; border-radius: 4px; font-family: 'Orbitron', sans-serif; font-size: 0.6rem; font-weight: 900; cursor: pointer; }
        .update-btn:hover { background: var(--primary-cyan); color: black; }

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
            <a href="admin_user_credits.php" class="menu-item active"><i class="fas fa-coins ic-credits"></i> <span>Manual Credits</span></a>
            <a href="admin_credit_pricing.php" class="menu-item"><i class="fas fa-tags ic-pricing"></i> <span>Pricing Plans</span></a>
            <a href="admin_credit_timing.php" class="menu-item"><i class="fas fa-clock ic-timing"></i> <span>Purchase Timing</span></a>
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
        <h2 class="section-title"><i class="fas fa-wallet ic-credits" style="margin-right:15px;"></i> MANUAL CREDITS</h2>

        <?php if($message): ?><div class="msg"><?php echo $message; ?></div><?php endif; ?>

        <div class="stats-row">
            <div class="stat-box"><span>Total Credits Circulating</span><h3><?php echo number_format($stats['total']); ?></h3></div>
            <div class="stat-box"><span>Active Balance Holders</span><h3><?php echo number_format($stats['users']); ?></h3></div>
        </div>

        <form method="GET" class="search-box">
            <input type="text" name="search" placeholder="Search user by identity or mobile..." value="<?php echo htmlspecialchars($search_query); ?>">
            <button type="submit" class="search-btn">FIND USER</button>
        </form>

        <div class="table-card">
            <table>
                <thead>
                    <tr><th>User Profile</th><th>Code</th><th>Balance</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php 
                    $list = $search_query ? $search_results : $top_users;
                    foreach($list as $u): ?>
                    <tr>
                        <td><strong><?php echo $u['username']; ?></strong><br><small style="color:rgba(255,255,255,0.4);"><?php echo $u['mobile_number']; ?></small></td>
                        <td style="font-family:'Orbitron';color:#FFD700;"><?php echo $u['referral_code'] ?: '-'; ?></td>
                        <td style="color:var(--primary-cyan);font-weight:bold;"><?php echo number_format($u['credits']); ?> ⚡</td>
                        <td>
                            <form method="POST" class="update-form" onsubmit="return confirm('Confirm credit update?');">
                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                <input type="number" name="credits" value="100" required>
                                <select name="action_type"><option value="add">ADD</option><option value="set">SET</option></select>
                                <button type="submit" name="update_credits" class="update-btn">UPDATE</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
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
