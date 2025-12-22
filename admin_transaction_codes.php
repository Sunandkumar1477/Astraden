<?php
session_start();
require_once 'admin_check.php';
require_once 'connection.php';

$message = '';
$error = '';
$search_code = '';
$search_results = [];

// Handle credit provision
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['provide_credits'])) {
    $transaction_id = intval($_POST['transaction_id'] ?? 0);
    $credits_to_add = intval($_POST['credits_amount'] ?? 0);
    
    if ($transaction_id > 0 && $credits_to_add > 0) {
        $stmt = $conn->prepare("SELECT user_id, transaction_code FROM transaction_codes WHERE id = ?");
        $stmt->bind_param("i", $transaction_id);
        $stmt->execute();
        $transaction = $stmt->get_result()->fetch_assoc();
        
        if ($transaction) {
            $user_id = $transaction['user_id'];
            $conn->query("UPDATE user_profile SET credits = credits + $credits_to_add WHERE user_id = $user_id");
            $conn->query("UPDATE transaction_codes SET status = 'verified' WHERE id = $transaction_id");
            
            $user_name = $conn->query("SELECT username FROM users WHERE id = $user_id")->fetch_assoc()['username'] ?? 'User';
            $message = "✓ Verified: $credits_to_add Credits added to $user_name";
            
            $desc = "Verified transaction code {$transaction['transaction_code']} for $user_id ($credits_to_add Credits)";
            $conn->query("INSERT INTO admin_logs (admin_id, admin_username, action, description, ip_address) VALUES ({$_SESSION['admin_id']}, '{$_SESSION['admin_username']}', 'verify_payment', '$desc', '{$_SERVER['REMOTE_ADDR']}')");
        }
    }
}

if (isset($_GET['search'])) {
    $search_code = strtoupper(trim($_GET['search']));
    if ($search_code) {
        $stmt = $conn->prepare("SELECT tc.*, u.username, up.credits FROM transaction_codes tc JOIN users u ON tc.user_id = u.id LEFT JOIN user_profile up ON u.id = up.user_id WHERE tc.transaction_code = ?");
        $stmt->bind_param("s", $search_code);
        $stmt->execute();
        $search_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

$pending_transactions = $conn->query("SELECT tc.*, u.username, up.credits FROM transaction_codes tc JOIN users u ON tc.user_id = u.id LEFT JOIN user_profile up ON u.id = up.user_id WHERE tc.status = 'pending' ORDER BY tc.created_at DESC LIMIT 50")->fetch_all(MYSQLI_ASSOC);
$stats = $conn->query("SELECT COUNT(*) as total, SUM(status='pending') as pending FROM transaction_codes")->fetch_assoc();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Verification - Astraden Admin</title>
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

        .search-row { background: var(--card-bg); border: 1px solid rgba(0, 255, 255, 0.2); border-radius: 15px; padding: 25px; margin-bottom: 30px; display: flex; gap: 15px; align-items: center; }
        .search-row input { flex: 1; padding: 15px; background: rgba(0,0,0,0.5); border: 1px solid rgba(0,255,255,0.3); border-radius: 10px; color: white; font-family: 'Orbitron', sans-serif; font-size: 1.2rem; text-align: center; letter-spacing: 5px; outline: none; }
        .search-btn { background: linear-gradient(135deg, var(--primary-cyan), var(--primary-purple)); border: none; color: white; padding: 15px 40px; border-radius: 10px; font-family: 'Orbitron', sans-serif; font-weight: 700; cursor: pointer; }

        .table-card { background: var(--card-bg); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 15px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
        th { color: var(--primary-purple); font-family: 'Orbitron', sans-serif; font-size: 0.7rem; text-transform: uppercase; background: rgba(0,0,0,0.2); }
        
        .code-badge { font-family: 'Orbitron', sans-serif; color: var(--primary-cyan); font-weight: 900; letter-spacing: 2px; font-size: 1.1rem; }
        .verify-form { display: flex; gap: 10px; }
        .verify-form input { width: 80px; padding: 8px; background: rgba(0,0,0,0.5); border: 1px solid rgba(0,255,255,0.3); border-radius: 4px; color: white; text-align: center; font-weight: bold; }
        .verify-btn { background: var(--primary-cyan); color: black; border: none; padding: 8px 15px; border-radius: 4px; font-family: 'Orbitron', sans-serif; font-size: 0.65rem; font-weight: 900; cursor: pointer; }

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
            <a href="admin_transaction_codes.php" class="menu-item active"><i class="fas fa-qrcode ic-verify"></i> <span>Verify Payments</span></a>
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
        <div class="sidebar-footer"><a href="admin_logout.php" class="logout-btn"><i class="fas fa-power-off"></i> <span>Authorization Exit</span></a></div>
    </nav>

    <main class="main-content">
        <h2 class="section-title"><i class="fas fa-shield-check ic-verify" style="margin-right:15px;"></i> VERIFY PAYMENTS</h2>

        <?php if($message): ?><div class="msg"><?php echo $message; ?></div><?php endif; ?>

        <form method="GET" class="search-row">
            <i class="fas fa-search" style="color:var(--color-verify);font-size:1.5rem;"></i>
            <input type="text" name="search" placeholder="ENTER 4-DIGIT CODE" maxlength="4" value="<?php echo htmlspecialchars($search_code); ?>">
            <button type="submit" class="search-btn">SEARCH CODE</button>
        </form>

        <div class="table-card">
            <table>
                <thead>
                    <tr><th>User Identity</th><th>Transaction Code</th><th>Current Bal</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php 
                    $list = $search_code ? $search_results : $pending_transactions;
                    if(empty($list)): ?>
                        <tr><td colspan="4" style="text-align:center;padding:40px;color:rgba(255,255,255,0.2);">NO PENDING REQUESTS FOUND</td></tr>
                    <?php endif;
                    foreach($list as $t): ?>
                    <tr style="<?php echo $t['status'] === 'verified' ? 'background:rgba(74,222,128,0.05);' : ''; ?>">
                        <td><strong><?php echo $t['username']; ?></strong><br><small style="color:rgba(255,255,255,0.4);"><?php echo date('M d, H:i', strtotime($t['created_at'])); ?></small></td>
                        <td><span class="code-badge"><?php echo $t['transaction_code']; ?></span></td>
                        <td><?php echo number_format($t['credits']); ?> ⚡</td>
                        <td>
                            <?php if($t['status'] === 'pending'): ?>
                            <form method="POST" class="verify-form" onsubmit="return confirm('Verify this payment?');">
                                <input type="hidden" name="transaction_id" value="<?php echo $t['id']; ?>">
                                <input type="number" name="credits_amount" value="100" required>
                                <button type="submit" name="provide_credits" class="verify-btn">VERIFY</button>
                            </form>
                            <?php else: ?><span style="color:#4ade80;font-weight:bold;font-size:0.8rem;">✓ VERIFIED</span><?php endif; ?>
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
