<?php
session_start();
require_once 'admin_check.php';
require_once 'connection.php';

$message = '';
$error = '';

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $request_id = intval($_POST['request_id'] ?? 0);
    $new_password = trim($_POST['new_password'] ?? '');
    $action = $_POST['action'] ?? 'complete';
    
    if ($request_id > 0) {
        $get_stmt = $conn->prepare("SELECT * FROM password_reset_requests WHERE id = ? AND status = 'pending'");
        $get_stmt->bind_param("i", $request_id);
        $get_stmt->execute();
        $get_result = $get_stmt->get_result();
        
        if ($get_result->num_rows > 0) {
            $request = $get_result->fetch_assoc();
            $user_id = $request['user_id'];
            
            if ($action === 'complete' && !empty($new_password)) {
                if (strlen($new_password) < 6) {
                    $error = "Password must be at least 6 characters long.";
                } else {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $conn->query("UPDATE users SET password = '$hashed_password' WHERE id = $user_id");
                    $conn->query("UPDATE password_reset_requests SET status = 'completed', admin_id = {$_SESSION['admin_id']}, new_password = '$new_password', processed_at = NOW() WHERE id = $request_id");
                    
                    $user_data = $conn->query("SELECT username, mobile_number FROM users WHERE id = $user_id")->fetch_assoc();
                    $desc = "Reset password for user '{$user_data['username']}'";
                    $conn->query("INSERT INTO admin_logs (admin_id, admin_username, action, description, ip_address, target_user_id) VALUES ({$_SESSION['admin_id']}, '{$_SESSION['admin_username']}', 'password_reset', '$desc', '{$_SERVER['REMOTE_ADDR']}', $user_id)");
                    
                    $message = "Password updated for {$user_data['username']}.";
                }
            } elseif ($action === 'reject') {
                $conn->query("UPDATE password_reset_requests SET status = 'rejected', admin_id = {$_SESSION['admin_id']}, processed_at = NOW() WHERE id = $request_id");
                $message = "Request rejected.";
            }
        }
    }
}

// Get requests
$requests = $conn->query("SELECT prr.*, u.username as user_username, u.mobile_number as user_mobile FROM password_reset_requests prr LEFT JOIN users u ON prr.user_id = u.id ORDER BY prr.created_at DESC")->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Requests - Astraden Admin</title>
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

        .table-card { background: var(--card-bg); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 15px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
        th { color: var(--primary-purple); font-family: 'Orbitron', sans-serif; font-size: 0.75rem; text-transform: uppercase; background: rgba(0,0,0,0.2); }
        
        .status-badge { padding: 4px 10px; border-radius: 4px; font-size: 0.7rem; font-weight: 900; }
        .status-pending { background: rgba(255, 165, 0, 0.1); color: #ffa500; border: 1px solid #ffa500; }
        .status-completed { background: rgba(0, 255, 204, 0.1); color: #00ffcc; border: 1px solid #00ffcc; }
        .status-rejected { background: rgba(255, 0, 110, 0.1); color: #ff006e; border: 1px solid #ff006e; }

        .action-btn { background: rgba(0, 255, 255, 0.1); border: 1px solid var(--primary-cyan); color: var(--primary-cyan); padding: 6px 12px; border-radius: 6px; font-family: 'Orbitron', sans-serif; font-size: 0.7rem; font-weight: 700; cursor: pointer; transition: 0.3s; }
        .action-btn:hover { background: var(--primary-cyan); color: black; }

        /* Modal */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.9); display: none; justify-content: center; align-items: center; z-index: 10000; backdrop-filter: blur(5px); }
        .modal-overlay.show { display: flex; }
        .modal { background: var(--card-bg); border: 2px solid var(--primary-cyan); border-radius: 20px; padding: 40px; max-width: 500px; width: 90%; }
        .modal h2 { font-family: 'Orbitron', sans-serif; color: var(--primary-cyan); margin-bottom: 25px; text-align: center; }
        .user-info-box { background: rgba(0, 255, 255, 0.05); padding: 15px; border-radius: 10px; margin-bottom: 20px; border: 1px solid rgba(0, 255, 255, 0.1); }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 0.9rem; }
        .info-label { color: var(--primary-purple); font-weight: bold; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; color: rgba(255,255,255,0.5); font-weight: 700; font-size: 0.75rem; margin-bottom: 8px; }
        .form-group input { width: 100%; padding: 12px; background: rgba(0,0,0,0.5); border: 1px solid rgba(0,255,255,0.3); border-radius: 8px; color: white; outline: none; }
        .modal-btns { display: flex; gap: 15px; margin-top: 30px; }
        .modal-btn { flex: 1; padding: 12px; border-radius: 10px; font-family: 'Orbitron', sans-serif; font-weight: 700; cursor: pointer; border: none; transition: 0.3s; }
        .btn-update { background: linear-gradient(135deg, var(--primary-cyan), var(--primary-purple)); color: white; }
        .btn-reject { background: rgba(255, 0, 110, 0.1); color: #ff006e; border: 1px solid #ff006e; }
        .btn-cancel { background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); }

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
            <a href="admin_password_reset_requests.php" class="menu-item active"><i class="fas fa-key ic-reset"></i> <span>Reset Requests</span></a>
            <div class="menu-category">Financials</div>
            <a href="admin_shop_pricing.php" class="menu-item"><i class="fas fa-store ic-shop"></i> <span>Shop Pricing</span></a>
            <div class="menu-category">Game Operations</div>
            <a href="admin_game_credits.php" class="menu-item"><i class="fas fa-gamepad ic-costs"></i> <span>Play Costs</span></a>
            <a href="admin_game_leaderboard.php" class="menu-item"><i class="fas fa-ranking-star ic-leaderboard"></i> <span>Leaderboards</span></a>
        </div>
        <div class="sidebar-footer"><a href="admin_logout.php" class="logout-btn"><i class="fas fa-power-off"></i> <span>Authorization Exit</span></a></div>
    </nav>

    <main class="main-content">
        <h2 class="section-title"><i class="fas fa-key-skeleton ic-reset" style="margin-right:15px;"></i> RESET REQUESTS</h2>

        <?php if($message): ?><div class="msg"><?php echo $message; ?></div><?php endif; ?>

        <div class="table-card">
            <table>
                <thead>
                    <tr><th>ID</th><th>User Details</th><th>Contact</th><th>Status</th><th>Requested At</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php if(empty($requests)): ?>
                        <tr><td colspan="6" style="text-align:center;padding:40px;color:rgba(255,255,255,0.2);">NO PENDING REQUESTS</td></tr>
                    <?php endif;
                    foreach($requests as $r): ?>
                    <tr>
                        <td>#<?php echo $r['id']; ?></td>
                        <td><strong style="color:var(--primary-cyan);"><?php echo htmlspecialchars($r['user_username']); ?></strong></td>
                        <td style="color:rgba(255,255,255,0.6);"><?php echo htmlspecialchars($r['user_mobile']); ?></td>
                        <td><span class="status-badge status-<?php echo $r['status']; ?>"><?php echo strtoupper($r['status']); ?></span></td>
                        <td style="font-size:0.8rem;color:rgba(255,255,255,0.4);"><?php echo date('M d, H:i', strtotime($r['created_at'])); ?></td>
                        <td>
                            <?php if($r['status'] === 'pending'): ?>
                                <button onclick="openResetModal(<?php echo $r['id']; ?>, '<?php echo addslashes($r['user_username']); ?>', '<?php echo $r['user_mobile']; ?>')" class="action-btn">PROCESS</button>
                            <?php else: ?>
                                <span style="font-size:0.7rem;color:rgba(255,255,255,0.2);">PROCESSED</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <div class="modal-overlay" id="resetModal">
        <div class="modal">
            <h2>PROCESS RESET</h2>
            <div class="user-info-box">
                <div class="info-row"><span class="info-label">IDENTITY:</span> <strong id="modal_user"></strong></div>
                <div class="info-row"><span class="info-label">CONTACT:</span> <strong id="modal_contact"></strong></div>
            </div>
            <form method="POST" id="resetForm">
                <input type="hidden" name="request_id" id="modal_rid">
                <input type="hidden" name="action" id="modal_action" value="complete">
                <div class="form-group">
                    <label>NEW PASSKEY GENERATION</label>
                    <input type="text" name="new_password" id="modal_pass" placeholder="Enter new passkey" minlength="6">
                </div>
                <div class="modal-btns">
                    <button type="button" onclick="closeResetModal()" class="modal-btn btn-cancel">CANCEL</button>
                    <button type="button" onclick="rejectReq()" class="modal-btn btn-reject">REJECT</button>
                    <button type="submit" name="reset_password" class="modal-btn btn-update">AUTHORIZE RESET</button>
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

        function openResetModal(id, user, contact) {
            document.getElementById('modal_rid').value = id;
            document.getElementById('modal_user').textContent = user;
            document.getElementById('modal_contact').textContent = contact;
            document.getElementById('modal_action').value = 'complete';
            document.getElementById('modal_pass').value = '';
            document.getElementById('resetModal').classList.add('show');
        }
        function closeResetModal() { document.getElementById('resetModal').classList.remove('show'); }
        function rejectReq() {
            if(confirm('Reject this request?')) {
                document.getElementById('modal_action').value = 'reject';
                document.getElementById('resetForm').submit();
            }
        }
        if (window.history.replaceState) window.history.replaceState(null, null, window.location.href);
    </script>
</body>
</html>
