<?php
session_start();
require_once 'admin_check.php';
require_once 'connection.php';

$message = '';
$error = '';
$search_query = '';
$users = [];

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

// Get users
$sql = "SELECT u.id, u.username, u.mobile_number, u.password, u.created_at, u.last_login, COALESCE(up.credits, 0) as credits FROM users u LEFT JOIN user_profile up ON u.id = up.user_id";
if (!empty($search_query)) {
    $search_term = "%{$search_query}%";
    $stmt = $conn->prepare("$sql WHERE u.username LIKE ? OR u.mobile_number LIKE ? ORDER BY u.created_at DESC");
    $stmt->bind_param("ss", $search_term, $search_term);
    $stmt->execute();
    $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $users = $conn->query("$sql ORDER BY u.created_at DESC LIMIT 100")->fetch_all(MYSQLI_ASSOC);
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Directory - Astraden Admin</title>
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
        .section-title { font-family: 'Orbitron', sans-serif; color: var(--primary-cyan); margin-bottom: 30px; letter-spacing: 3px; display: flex; align-items: center; gap: 15px; }

        .search-box { background: var(--card-bg); border: 1px solid rgba(0, 255, 255, 0.2); border-radius: 15px; padding: 20px; margin-bottom: 30px; display: flex; gap: 15px; }
        .search-box input { flex: 1; padding: 12px 20px; background: rgba(0, 0, 0, 0.5); border: 1px solid rgba(0, 255, 255, 0.3); border-radius: 10px; color: white; font-family: 'Rajdhani', sans-serif; font-size: 1rem; outline: none; }
        .search-btn { background: linear-gradient(135deg, var(--primary-cyan), var(--primary-purple)); border: none; color: white; padding: 0 30px; border-radius: 10px; font-family: 'Orbitron', sans-serif; font-weight: 700; cursor: pointer; }

        .table-card { background: var(--card-bg); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 15px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
        th { color: var(--primary-purple); font-family: 'Orbitron', sans-serif; font-size: 0.75rem; text-transform: uppercase; background: rgba(0,0,0,0.2); }
        
        .user-name { color: var(--primary-cyan); font-weight: 700; }
        .credits-val { color: #FFD700; font-weight: bold; }
        .action-btn { background: rgba(0, 255, 255, 0.1); border: 1px solid var(--primary-cyan); color: var(--primary-cyan); padding: 6px 12px; border-radius: 6px; font-family: 'Orbitron', sans-serif; font-size: 0.7rem; font-weight: 700; cursor: pointer; text-decoration: none; transition: 0.3s; }
        .action-btn:hover { background: var(--primary-cyan); color: black; }

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
            <a href="admin_contest_management.php" class="menu-item"><i class="fas fa-trophy ic-contest"></i> <span>Contest Control</span></a>
            <a href="admin_game_credits.php" class="menu-item"><i class="fas fa-gamepad ic-costs"></i> <span>Play Costs</span></a>
            <a href="admin_game_prizes.php" class="menu-item"><i class="fas fa-award ic-prizes"></i> <span>Prize Setup</span></a>
            <a href="admin_game_leaderboard.php" class="menu-item"><i class="fas fa-ranking-star ic-leaderboard"></i> <span>Leaderboards</span></a>
        </div>
        <div class="sidebar-footer"><a href="admin_logout.php" class="logout-btn"><i class="fas fa-power-off"></i> <span>Authorization Exit</span></a></div>
    </nav>

    <main class="main-content">
        <h2 class="section-title"><i class="fas fa-users-viewfinder ic-users" style="margin-right:15px;"></i> USER DIRECTORY</h2>

        <?php if($message): ?><div class="msg msg-success"><?php echo $message; ?></div><?php endif; ?>
        <?php if($error): ?><div class="msg msg-error"><?php echo $error; ?></div><?php endif; ?>

        <form method="GET" class="search-box">
            <input type="text" name="search" placeholder="Search by identity or contact number..." value="<?php echo htmlspecialchars($search_query); ?>">
            <button type="submit" class="search-btn">SEARCH</button>
            <?php if($search_query): ?><a href="admin_view_all_users.php" class="action-btn" style="display:flex;align-items:center;">CLEAR</a><?php endif; ?>
        </form>

        <div class="table-card">
            <table>
                <thead>
                    <tr><th>ID</th><th>User Details</th><th>Contact</th><th>Balance</th><th>Join Date</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach($users as $u): ?>
                    <tr>
                        <td>#<?php echo $u['id']; ?></td>
                        <td><span class="user-name"><?php echo htmlspecialchars($u['username']); ?></span></td>
                        <td><?php echo htmlspecialchars($u['mobile_number']); ?></td>
                        <td><span class="credits-val"><?php echo number_format($u['credits']); ?></span> ⚡</td>
                        <td><?php echo date('d M Y', strtotime($u['created_at'])); ?></td>
                        <td>
                            <button onclick="openModal(<?php echo $u['id']; ?>, '<?php echo addslashes($u['username']); ?>')" class="action-btn">RESET PASSWORD</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
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
        if (window.history.replaceState) window.history.replaceState(null, null, window.location.href);
    </script>
</body>
</html>
