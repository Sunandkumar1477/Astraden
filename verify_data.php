<?php
require_once 'connection.php';
session_start();

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Favicon - Must be early in head for proper display -->
    <link rel="icon" type="image/svg+xml" href="logo.svg">
    <link rel="shortcut icon" type="image/svg+xml" href="logo.svg">
    <link rel="alternate icon" type="image/png" href="logo.svg">
    <link rel="apple-touch-icon" sizes="180x180" href="logo.svg">
    <link rel="icon" type="image/svg+xml" sizes="any" href="logo.svg">
    <title>Verify Data - Games Hub</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: #1a1a2e;
            color: #00ffff;
        }
        .success { color: #00ff00; }
        .error { color: #ff0000; }
        .info { color: #ffff00; }
        h1, h2 { color: #00ffff; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; border: 1px solid #00ffff; text-align: left; font-size: 0.9rem; }
        th { background: rgba(0, 255, 255, 0.2); }
        .btn { display: inline-block; padding: 10px 20px; background: #00ffff; color: #000; text-decoration: none; margin: 10px 5px; border-radius: 5px; }
        .btn:hover { background: #00cccc; }
    </style>
</head>
<body>
    <h1>🔍 Data Verification - Space Games Hub</h1>
    
    <div>
        <a href="index.php" class="btn">Home</a>
        <a href="setup_database.php" class="btn">Setup Database</a>
    </div>

<?php
// Check if tables exist
$tables_check = $conn->query("SHOW TABLES LIKE 'users'");
if ($tables_check && $tables_check->num_rows > 0) {
    echo "<p class='success'>✓ Users table exists</p>";
} else {
    echo "<p class='error'>✗ Users table does not exist. Please run setup_database.php first.</p>";
    exit;
}

$tables_check = $conn->query("SHOW TABLES LIKE 'login_logs'");
if ($tables_check && $tables_check->num_rows > 0) {
    echo "<p class='success'>✓ Login_logs table exists</p>";
} else {
    echo "<p class='error'>✗ Login_logs table does not exist. Please run setup_database.php first.</p>";
    exit;
}

// Get all users
echo "<h2>All Registered Users</h2>";
$users = $conn->query("SELECT id, username, mobile_number, created_at, last_login FROM users ORDER BY created_at DESC");
if ($users && $users->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>ID</th><th>Username</th><th>Mobile Number</th><th>Created At</th><th>Last Login</th><th>Actions</th></tr>";
    while ($user = $users->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($user['id']) . "</td>";
        echo "<td>" . htmlspecialchars($user['username']) . "</td>";
        echo "<td>" . htmlspecialchars($user['mobile_number']) . "</td>";
        echo "<td>" . htmlspecialchars($user['created_at']) . "</td>";
        echo "<td>" . htmlspecialchars($user['last_login'] ?? 'Never') . "</td>";
        echo "<td><a href='?view_logs=" . $user['id'] . "'>View Logs</a></td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<p class='info'>Total users: " . $users->num_rows . "</p>";
} else {
    echo "<p class='info'>No users registered yet.</p>";
}

// Show login logs
echo "<h2>All Login/Logout Activity</h2>";
$logs = $conn->query("SELECT id, user_id, username, mobile_number, action, ip_address, status, login_time, logout_time, session_duration, failure_reason 
                      FROM login_logs 
                      ORDER BY login_time DESC 
                      LIMIT 50");
if ($logs && $logs->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>ID</th><th>User ID</th><th>Username</th><th>Action</th><th>Status</th><th>IP Address</th><th>Login Time</th><th>Logout Time</th><th>Duration (sec)</th><th>Failure Reason</th></tr>";
    while ($log = $logs->fetch_assoc()) {
        $row_class = $log['status'] == 'failed' ? 'error' : 'success';
        echo "<tr class='$row_class'>";
        echo "<td>" . htmlspecialchars($log['id']) . "</td>";
        echo "<td>" . htmlspecialchars($log['user_id']) . "</td>";
        echo "<td>" . htmlspecialchars($log['username']) . "</td>";
        echo "<td>" . htmlspecialchars($log['action']) . "</td>";
        echo "<td>" . htmlspecialchars($log['status']) . "</td>";
        echo "<td>" . htmlspecialchars($log['ip_address']) . "</td>";
        echo "<td>" . htmlspecialchars($log['login_time']) . "</td>";
        echo "<td>" . htmlspecialchars($log['logout_time'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($log['session_duration'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($log['failure_reason'] ?? 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='info'>No login activity logged yet.</p>";
}

// Show specific user logs if requested
if (isset($_GET['view_logs']) && is_numeric($_GET['view_logs'])) {
    $user_id = intval($_GET['view_logs']);
    echo "<h2>Login Activity for User ID: $user_id</h2>";
    
    $user_info = $conn->query("SELECT username, mobile_number FROM users WHERE id = $user_id");
    if ($user_info && $user_info->num_rows > 0) {
        $info = $user_info->fetch_assoc();
        echo "<p class='info'>Username: <strong>" . htmlspecialchars($info['username']) . "</strong> | Mobile: <strong>" . htmlspecialchars($info['mobile_number']) . "</strong></p>";
    }
    
    $user_logs = $conn->query("SELECT * FROM login_logs WHERE user_id = $user_id ORDER BY login_time DESC");
    if ($user_logs && $user_logs->num_rows > 0) {
        echo "<table>";
        echo "<tr><th>Action</th><th>Status</th><th>IP Address</th><th>Login Time</th><th>Logout Time</th><th>Duration</th><th>Failure Reason</th></tr>";
        while ($log = $user_logs->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($log['action']) . "</td>";
            echo "<td>" . htmlspecialchars($log['status']) . "</td>";
            echo "<td>" . htmlspecialchars($log['ip_address']) . "</td>";
            echo "<td>" . htmlspecialchars($log['login_time']) . "</td>";
            echo "<td>" . htmlspecialchars($log['logout_time'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($log['session_duration'] ?? 'N/A') . " seconds</td>";
            echo "<td>" . htmlspecialchars($log['failure_reason'] ?? 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='info'>No activity logs for this user.</p>";
    }
}

// Statistics
echo "<h2>Statistics</h2>";
$stats = $conn->query("SELECT 
    (SELECT COUNT(*) FROM users) as total_users,
    (SELECT COUNT(*) FROM login_logs WHERE action = 'register') as total_registrations,
    (SELECT COUNT(*) FROM login_logs WHERE action = 'login' AND status = 'success') as successful_logins,
    (SELECT COUNT(*) FROM login_logs WHERE action = 'failed_login') as failed_logins,
    (SELECT COUNT(*) FROM login_logs WHERE action = 'logout') as total_logouts");

if ($stats && $stats->num_rows > 0) {
    $stat = $stats->fetch_assoc();
    echo "<table>";
    echo "<tr><th>Metric</th><th>Count</th></tr>";
    echo "<tr><td>Total Users</td><td>" . $stat['total_users'] . "</td></tr>";
    echo "<tr><td>Total Registrations</td><td>" . $stat['total_registrations'] . "</td></tr>";
    echo "<tr><td>Successful Logins</td><td>" . $stat['successful_logins'] . "</td></tr>";
    echo "<tr><td>Failed Login Attempts</td><td>" . $stat['failed_logins'] . "</td></tr>";
    echo "<tr><td>Total Logouts</td><td>" . $stat['total_logouts'] . "</td></tr>";
    echo "</table>";
}

$conn->close();
?>
</body>
</html>

