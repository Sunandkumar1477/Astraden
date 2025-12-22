<?php
require_once 'connection.php';

// Set header for better display
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - Games Hub</title>
    <link rel="icon" type="image/svg+xml" href="logo.svg">
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #1a1a2e;
            color: #00ffff;
        }
        .success { color: #00ff00; }
        .error { color: #ff0000; }
        .info { color: #ffff00; }
        h1, h2 { color: #00ffff; }
        a { color: #00ffff; text-decoration: none; }
        a:hover { text-decoration: underline; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; border: 1px solid #00ffff; text-align: left; }
        th { background: rgba(0, 255, 255, 0.2); }
    </style>
</head>
<body>
    <h1>🚀 Database Setup - Space Games Hub</h1>
    
<?php
echo "<h2>Step 1: Creating Database Tables...</h2>";

// Step 1: Create users table
$sql_users = "CREATE TABLE IF NOT EXISTS users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    mobile_number VARCHAR(15) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_mobile (mobile_number),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql_users) === TRUE) {
    echo "<p class='success'>✓ Table 'users' created successfully or already exists.</p>";
} else {
    echo "<p class='error'>✗ Error creating users table: " . $conn->error . "</p>";
    exit;
}

// Step 2: Create login_logs table (without foreign key first, then add it)
$sql_logs = "CREATE TABLE IF NOT EXISTS login_logs (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL DEFAULT 0,
    username VARCHAR(50) NOT NULL,
    mobile_number VARCHAR(15) NOT NULL,
    action VARCHAR(20) NOT NULL COMMENT 'login, logout, failed_login, register',
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    logout_time TIMESTAMP NULL,
    session_duration INT(11) NULL COMMENT 'Duration in seconds',
    status VARCHAR(20) DEFAULT 'success' COMMENT 'success, failed',
    failure_reason VARCHAR(255) NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_username (username),
    INDEX idx_action (action),
    INDEX idx_login_time (login_time),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql_logs) === TRUE) {
    echo "<p class='success'>✓ Table 'login_logs' created successfully or already exists.</p>";
} else {
    echo "<p class='error'>✗ Error creating login_logs table: " . $conn->error . "</p>";
}

// Step 3: Add foreign key constraint if it doesn't exist
$fk_check = $conn->query("SELECT COUNT(*) as count 
    FROM information_schema.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'login_logs' 
    AND CONSTRAINT_NAME = 'fk_login_logs_user_id'");

if ($fk_check && $fk_check->num_rows > 0) {
    $row = $fk_check->fetch_assoc();
    if ($row['count'] == 0) {
        // Try to add foreign key
        $fk_sql = "ALTER TABLE login_logs 
                   ADD CONSTRAINT fk_login_logs_user_id 
                   FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE";
        
        if ($conn->query($fk_sql) === TRUE) {
            echo "<p class='success'>✓ Foreign key constraint added successfully.</p>";
        } else {
            echo "<p class='info'>ℹ Foreign key constraint could not be added (may already exist or users table empty): " . $conn->error . "</p>";
        }
    } else {
        echo "<p class='info'>ℹ Foreign key constraint already exists.</p>";
    }
}

echo "<h2>Step 2: Verifying Table Structure...</h2>";

// Verify users table structure
$users_check = $conn->query("DESCRIBE users");
if ($users_check && $users_check->num_rows > 0) {
    echo "<p class='success'>✓ Users table structure verified.</p>";
    echo "<table>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = $users_check->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='error'>✗ Could not verify users table structure.</p>";
}

// Verify login_logs table structure
$logs_check = $conn->query("DESCRIBE login_logs");
if ($logs_check && $logs_check->num_rows > 0) {
    echo "<p class='success'>✓ Login_logs table structure verified.</p>";
    echo "<table>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = $logs_check->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='error'>✗ Could not verify login_logs table structure.</p>";
}

echo "<h2>Step 3: Checking Existing Data...</h2>";

// Count users
$users_count = $conn->query("SELECT COUNT(*) as count FROM users");
if ($users_count) {
    $row = $users_count->fetch_assoc();
    echo "<p class='info'>ℹ Total users in database: <strong>" . $row['count'] . "</strong></p>";
}

// Count login logs
$logs_count = $conn->query("SELECT COUNT(*) as count FROM login_logs");
if ($logs_count) {
    $row = $logs_count->fetch_assoc();
    echo "<p class='info'>ℹ Total login logs in database: <strong>" . $row['count'] . "</strong></p>";
}

// Show recent users
$recent_users = $conn->query("SELECT id, username, mobile_number, created_at, last_login FROM users ORDER BY created_at DESC LIMIT 5");
if ($recent_users && $recent_users->num_rows > 0) {
    echo "<h3>Recent Users:</h3>";
    echo "<table>";
    echo "<tr><th>ID</th><th>Username</th><th>Mobile</th><th>Created At</th><th>Last Login</th></tr>";
    while ($row = $recent_users->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['username']) . "</td>";
        echo "<td>" . htmlspecialchars($row['mobile_number']) . "</td>";
        echo "<td>" . htmlspecialchars($row['created_at'] ?? 'Never') . "</td>";
        echo "<td>" . htmlspecialchars($row['last_login'] ?? 'Never') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Show recent login logs
$recent_logs = $conn->query("SELECT id, user_id, username, action, status, login_time FROM login_logs ORDER BY login_time DESC LIMIT 10");
if ($recent_logs && $recent_logs->num_rows > 0) {
    echo "<h3>Recent Login Activity:</h3>";
    echo "<table>";
    echo "<tr><th>ID</th><th>User ID</th><th>Username</th><th>Action</th><th>Status</th><th>Time</th></tr>";
    while ($row = $recent_logs->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['user_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['username']) . "</td>";
        echo "<td>" . htmlspecialchars($row['action']) . "</td>";
        echo "<td>" . htmlspecialchars($row['status']) . "</td>";
        echo "<td>" . htmlspecialchars($row['login_time']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<h2>✅ Setup Complete!</h2>";
echo "<p class='success'>All database tables are ready. User registration and login data will be saved automatically.</p>";
echo "<p><a href='index.php'>← Go to Home Page</a></p>";

$conn->close();
?>
</body>
</html>

