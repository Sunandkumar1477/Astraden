<?php
require_once 'admin_check.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: admin_dashboard.php');
    exit;
}

$user_id = intval($_GET['id']);

// Get user details
$user_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();

if ($user_result->num_rows === 0) {
    header('Location: admin_dashboard.php');
    exit;
}

$user = $user_result->fetch_assoc();
$user_stmt->close();

// Get user's login history
$logs_stmt = $conn->prepare("SELECT * FROM login_logs WHERE user_id = ? ORDER BY login_time DESC");
$logs_stmt->bind_param("i", $user_id);
$logs_stmt->execute();
$logs_result = $logs_stmt->get_result();

// Log admin action
$log_stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, admin_username, action, description, ip_address, user_agent, target_user_id) VALUES (?, ?, 'view_user', 'Viewed user details', ?, ?, ?)");
$ip = getClientIP();
$ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
$log_stmt->bind_param("isssi", $GLOBALS['admin_id'], $GLOBALS['admin_username'], $ip, $ua, $user_id);
$log_stmt->execute();
$log_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details - Admin</title>
    <!-- Favicon - Must be early in head for proper display -->
    <link rel="icon" type="image/svg+xml" href="Alogo.svg">
    <link rel="shortcut icon" type="image/svg+xml" href="Alogo.svg">
    <link rel="alternate icon" type="image/png" href="Alogo.svg">
    <link rel="apple-touch-icon" sizes="180x180" href="Alogo.svg">
    <link rel="icon" type="image/svg+xml" sizes="any" href="Alogo.svg">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Arial', sans-serif;
            background: #0a0a0f;
            color: #00ffff;
            min-height: 100vh;
            padding: 20px;
        }
        .header {
            background: rgba(15, 15, 25, 0.95);
            border-bottom: 2px solid #00ffff;
            padding: 20px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            font-size: 1.8rem;
            text-transform: uppercase;
            letter-spacing: 3px;
        }
        .back-btn {
            background: rgba(0, 255, 255, 0.2);
            border: 2px solid #00ffff;
            color: #00ffff;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        .back-btn:hover {
            background: rgba(0, 255, 255, 0.4);
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .user-info-card {
            background: rgba(15, 15, 25, 0.8);
            border: 2px solid #00ffff;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
        }
        .user-info-card h2 {
            color: #00ffff;
            margin-bottom: 20px;
            border-bottom: 2px solid #00ffff;
            padding-bottom: 10px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        .info-item {
            padding: 15px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
        }
        .info-item label {
            display: block;
            color: #9d4edd;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }
        .info-item .value {
            color: #00ffff;
            font-size: 1.1rem;
            font-weight: 600;
        }
        .section {
            background: rgba(15, 15, 25, 0.8);
            border: 2px solid #00ffff;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
        }
        .section h2 {
            color: #00ffff;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 2px;
            border-bottom: 2px solid #00ffff;
            padding-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid rgba(0, 255, 255, 0.2);
        }
        th {
            background: rgba(0, 255, 255, 0.1);
            color: #00ffff;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 1px;
        }
        td {
            color: rgba(0, 255, 255, 0.8);
        }
        .status-success {
            color: #00ff00;
        }
        .status-failed {
            color: #ff0000;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>👤 User Details</h1>
        <a href="admin_dashboard.php" class="back-btn">← Back to Dashboard</a>
    </div>

    <div class="container">
        <div class="user-info-card">
            <h2>User Information</h2>
            <div class="info-grid">
                <div class="info-item">
                    <label>User ID</label>
                    <div class="value"><?php echo htmlspecialchars($user['id']); ?></div>
                </div>
                <div class="info-item">
                    <label>Username</label>
                    <div class="value"><?php echo htmlspecialchars($user['username']); ?></div>
                </div>
                <div class="info-item">
                    <label>Mobile Number</label>
                    <div class="value"><?php echo htmlspecialchars($user['mobile_number']); ?></div>
                </div>
                <div class="info-item">
                    <label>Account Created</label>
                    <div class="value"><?php echo htmlspecialchars($user['created_at']); ?></div>
                </div>
                <div class="info-item">
                    <label>Last Login</label>
                    <div class="value"><?php echo htmlspecialchars($user['last_login'] ?? 'Never'); ?></div>
                </div>
            </div>
        </div>

        <div class="section">
            <h2>Login History</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Action</th>
                        <th>Status</th>
                        <th>IP Address</th>
                        <th>Login Time</th>
                        <th>Logout Time</th>
                        <th>Duration</th>
                        <th>Failure Reason</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logs_result->num_rows > 0): ?>
                        <?php while ($log = $logs_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['id']); ?></td>
                            <td><?php echo htmlspecialchars($log['action']); ?></td>
                            <td class="<?php echo $log['status'] === 'success' ? 'status-success' : 'status-failed'; ?>">
                                <?php echo htmlspecialchars($log['status']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                            <td><?php echo htmlspecialchars($log['login_time']); ?></td>
                            <td><?php echo htmlspecialchars($log['logout_time'] ?? 'N/A'); ?></td>
                            <td><?php echo $log['session_duration'] ? htmlspecialchars($log['session_duration']) . ' sec' : 'N/A'; ?></td>
                            <td><?php echo htmlspecialchars($log['failure_reason'] ?? 'N/A'); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; color: rgba(0, 255, 255, 0.5);">No login history found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>
        // Prevent form resubmission on page refresh
        if ( window.history.replaceState )
        {
            window.history.replaceState( null, null, window.location.href);
        }
    </script>
</body>
</html>
<?php
$logs_stmt->close();
$conn->close();
?>

