<?php
require_once 'admin_check.php';

// Get statistics
$stats = $conn->query("SELECT 
    (SELECT COUNT(*) FROM users) as total_users,
    (SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()) as users_today,
    (SELECT COUNT(*) FROM login_logs WHERE action = 'login' AND status = 'success' AND DATE(login_time) = CURDATE()) as logins_today,
    (SELECT COUNT(*) FROM login_logs WHERE action = 'failed_login' AND DATE(login_time) = CURDATE()) as failed_logins_today,
    (SELECT COUNT(*) FROM login_logs WHERE action = 'register' AND DATE(login_time) = CURDATE()) as registrations_today
")->fetch_assoc();

// Get recent users
$recent_users = $conn->query("SELECT id, username, mobile_number, created_at, last_login FROM users ORDER BY created_at DESC LIMIT 5");

// Get recent login activity
$recent_logs = $conn->query("SELECT id, user_id, username, action, status, ip_address, login_time FROM login_logs ORDER BY login_time DESC LIMIT 10");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Favicon - Must be early in head for proper display -->
    <link rel="icon" type="image/svg+xml" href="Alogo.svg">
    <link rel="shortcut icon" type="image/svg+xml" href="Alogo.svg">
    <link rel="alternate icon" type="image/png" href="Alogo.svg">
    <link rel="apple-touch-icon" sizes="180x180" href="Alogo.svg">
    <link rel="icon" type="image/svg+xml" sizes="any" href="Alogo.svg">
    <title>Admin Command Center - Astraden</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-cyan: #00ffff;
            --primary-purple: #9d4edd;
            --sidebar-width: 280px;
            --dark-bg: #05050a;
            --card-bg: rgba(15, 15, 25, 0.95);
            --glow-cyan: 0 0 15px rgba(0, 255, 255, 0.3);
            
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
        body { font-family: 'Rajdhani', sans-serif; background: var(--dark-bg); color: white; min-height: 100vh; display: flex; overflow-x: hidden; }
        
        .space-bg { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: radial-gradient(circle at 10% 20%, #1a1a2e 0%, #05050a 100%); z-index: -1; }

        .sidebar {
            width: var(--sidebar-width);
            background: var(--card-bg);
            border-right: 1px solid rgba(0, 255, 255, 0.2);
            height: 100vh;
            position: fixed;
            left: 0; top: 0;
            display: flex;
            flex-direction: column;
            z-index: 1001;
            box-shadow: 10px 0 30px rgba(0, 0, 0, 0.5);
        }

        .sidebar-header { padding: 30px 20px; text-align: center; border-bottom: 1px solid rgba(0, 255, 255, 0.1); }
        .sidebar-header h1 { font-family: 'Orbitron', sans-serif; font-size: 1.4rem; color: var(--primary-cyan); letter-spacing: 2px; text-transform: uppercase; }

        .sidebar-menu { flex: 1; overflow-y: auto; padding: 20px 0; scroll-behavior: smooth; }
        .menu-category { padding: 15px 25px 10px; font-family: 'Orbitron', sans-serif; font-size: 0.7rem; color: rgba(255,255,255,0.4); text-transform: uppercase; letter-spacing: 2px; font-weight: 900; }

        .menu-item {
            padding: 12px 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            text-decoration: none;
            color: rgba(255, 255, 255, 0.7);
            font-weight: 500;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }

        .menu-item i { 
            width: 24px; height: 24px; 
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; 
            border-radius: 6px;
            background: rgba(255,255,255,0.05);
        }

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

        .menu-item:hover { background: rgba(255, 255, 255, 0.05); color: white; padding-left: 30px; }
        .menu-item.active { background: rgba(255, 255, 255, 0.08); color: white; border-left-color: var(--primary-cyan); font-weight: 700; }

        .sidebar-footer { padding: 20px; border-top: 1px solid rgba(0, 255, 255, 0.1); }
        .logout-btn { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 12px; background: rgba(255, 0, 110, 0.1); border: 1px solid #ff006e; color: #ff006e; text-decoration: none; border-radius: 8px; font-family: 'Orbitron', sans-serif; font-size: 0.8rem; font-weight: 700; }

        .main-content { margin-left: var(--sidebar-width); flex: 1; padding: 40px; max-width: calc(100% - var(--sidebar-width)); }
        .top-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .stat-card { background: var(--card-bg); border: 1px solid rgba(0, 255, 255, 0.2); border-radius: 15px; padding: 20px; position: relative; }
        .stat-label { font-size: 0.7rem; text-transform: uppercase; color: rgba(255,255,255,0.5); letter-spacing: 1.5px; margin-bottom: 5px; display: block; }
        .stat-value { font-family: 'Orbitron', sans-serif; font-size: 1.8rem; color: white; font-weight: 900; }

        /* Dashboard Quick Access Grid */
        .quick-access-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .access-card { 
            background: var(--card-bg); border: 1px solid rgba(255,255,255,0.05); border-radius: 15px; 
            padding: 25px; text-decoration: none; text-align: center; transition: 0.3s;
            display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 15px;
        }
        .access-card i { font-size: 2rem; }
        .access-card span { font-family: 'Orbitron'; font-size: 0.7rem; font-weight: 700; color: white; letter-spacing: 1px; }
        .access-card:hover { transform: translateY(-5px); border-color: var(--primary-cyan); background: rgba(0,255,255,0.05); }

        .content-section { background: var(--card-bg); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 20px; padding: 25px; margin-bottom: 30px; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .section-header h2 { font-family: 'Orbitron', sans-serif; font-size: 1rem; color: var(--primary-cyan); text-transform: uppercase; letter-spacing: 2px; }

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
        th { color: var(--primary-purple); font-family: 'Orbitron', sans-serif; font-size: 0.7rem; text-transform: uppercase; }
        td { color: rgba(255,255,255,0.8); font-size: 0.85rem; }

        @media (max-width: 1024px) {
            .sidebar { width: 80px; }
            .sidebar-header h1, .menu-category, .menu-item span, .sidebar-footer span { display: none; }
            .menu-item { justify-content: center; padding: 15px 0; }
            .main-content { margin-left: 80px; max-width: calc(100% - 80px); }
        }
    </style>
</head>
<body>
    <div class="space-bg"></div>

    <nav class="sidebar">
        <div class="sidebar-header"><h1>Astraden</h1></div>
        <div class="sidebar-menu" id="sidebarMenu">
            <div class="menu-category">General</div>
            <a href="admin_dashboard.php" class="menu-item active"><i class="fas fa-chart-line ic-overview"></i> <span>Overview</span></a>
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
            <a href="admin_game_timing.php" class="menu-item"><i class="fas fa-calendar-check ic-sessions"></i> <span>Game Sessions</span></a>
            <a href="admin_contest_management.php" class="menu-item"><i class="fas fa-trophy ic-contest"></i> <span>Contest Control</span></a>
            <a href="admin_game_credits.php" class="menu-item"><i class="fas fa-gamepad ic-costs"></i> <span>Play Costs</span></a>
            <a href="admin_game_prizes.php" class="menu-item"><i class="fas fa-award ic-prizes"></i> <span>Prize Setup</span></a>
            <a href="admin_game_leaderboard.php" class="menu-item"><i class="fas fa-ranking-star ic-leaderboard"></i> <span>Leaderboards</span></a>
        </div>
        <div class="sidebar-footer"><a href="admin_logout.php" class="logout-btn"><i class="fas fa-power-off"></i> <span>Exit</span></a></div>
    </nav>

    <main class="main-content">
        <header class="top-header">
            <h2 style="font-family: 'Orbitron'; letter-spacing: 3px;">SYSTEM OVERVIEW</h2>
            <div style="font-size: 0.9rem; color: var(--primary-cyan); font-family: 'Orbitron';">WELCOME, SUPER ADMIN</div>
        </header>

        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-label">TOTAL PLAYERS</span>
                <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
            </div>
            <div class="stat-card">
                <span class="stat-label">NEW TODAY</span>
                <div class="stat-value" style="color: var(--color-users);"><?php echo number_format($stats['users_today']); ?></div>
            </div>
            <div class="stat-card">
                <span class="stat-label">DAILY LOGINS</span>
                <div class="stat-value" style="color: var(--color-verify);"><?php echo number_format($stats['logins_today']); ?></div>
            </div>
            <div class="stat-card">
                <span class="stat-label">FAILED ACCESS</span>
                <div class="stat-value" style="color: #ff006e;"><?php echo number_format($stats['failed_logins_today']); ?></div>
            </div>
        </div>

        <div class="section-header"><h2>QUICK MISSION CONTROLS</h2></div>
        <div class="quick-access-grid">
            <a href="admin_view_all_users.php" class="access-card"><i class="fas fa-users ic-users"></i><span>DIRECTORY</span></a>
            <a href="admin_password_reset_requests.php" class="access-card"><i class="fas fa-key ic-reset"></i><span>RESETS</span></a>
            <a href="admin_transaction_codes.php" class="access-card"><i class="fas fa-qrcode ic-verify"></i><span>VERIFY</span></a>
            <a href="admin_user_credits.php" class="access-card"><i class="fas fa-coins ic-credits"></i><span>CREDITS</span></a>
            <a href="admin_contest_management.php" class="access-card"><i class="fas fa-trophy ic-contest"></i><span>CONTESTS</span></a>
            <a href="admin_game_leaderboard.php" class="access-card"><i class="fas fa-ranking-star ic-leaderboard"></i><span>BOARD</span></a>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 30px;">
            <section class="content-section">
                <div class="section-header"><h2>RECENT SIGNUPS</h2><a href="admin_view_all_users.php" style="color: var(--primary-cyan); font-size: 0.7rem; text-decoration: none;">VIEW ALL</a></div>
                <table>
                    <thead><tr><th>Identity</th><th>Timestamp</th></tr></thead>
                    <tbody>
                        <?php while ($user = $recent_users->fetch_assoc()): ?>
                        <tr><td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td><td><?php echo date('H:i', strtotime($user['created_at'])); ?></td></tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </section>

            <section class="content-section">
                <div class="section-header"><h2>ACCESS LOG</h2></div>
                <table>
                    <thead><tr><th>Identity</th><th>Status</th><th>Time</th></tr></thead>
                    <tbody>
                        <?php while ($log = $recent_logs->fetch_assoc()): ?>
                        <tr><td><strong><?php echo htmlspecialchars($log['username']); ?></strong></td><td><span style="color: <?php echo $log['status'] === 'success' ? '#00ffcc' : '#ff006e'; ?>; font-weight:bold; font-size:0.7rem;"><?php echo strtoupper($log['status']); ?></span></td><td><?php echo date('H:i', strtotime($log['login_time'])); ?></td></tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </section>
        </div>
    </main>

    <script>
        // Sidebar scroll preservation logic
        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('sidebarMenu');
            const savedScroll = localStorage.getItem('sidebar_scroll');
            if (savedScroll) sidebar.scrollTop = savedScroll;
            
            sidebar.addEventListener('scroll', () => {
                localStorage.setItem('sidebar_scroll', sidebar.scrollTop);
            });

            // Ensure active item is visible
            const activeItem = sidebar.querySelector('.menu-item.active');
            if (activeItem) {
                const rect = activeItem.getBoundingClientRect();
                const containerRect = sidebar.getBoundingClientRect();
                if (rect.bottom > containerRect.bottom || rect.top < containerRect.top) {
                    activeItem.scrollIntoView({ block: 'center' });
                }
            }
        });
    </script>
</body>
</html>