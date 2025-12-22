<?php
require_once 'connection.php';

echo "<h2>Creating Database Tables...</h2>";

// Create users table
$sql_users = "CREATE TABLE IF NOT EXISTS users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    mobile_number VARCHAR(15) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_username (username),
    INDEX idx_mobile (mobile_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql_users) === TRUE) {
    echo "✓ Table 'users' created successfully or already exists.<br>";
} else {
    echo "✗ Error creating users table: " . $conn->error . "<br>";
}

// Create login_logs table
$sql_logs = "CREATE TABLE IF NOT EXISTS login_logs (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
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
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql_logs) === TRUE) {
    echo "✓ Table 'login_logs' created successfully or already exists.<br>";
} else {
    echo "✗ Error creating login_logs table: " . $conn->error . "<br>";
}

echo "<br><h3>All tables created successfully!</h3>";
echo "<p><a href='index.php'>Go to Home Page</a></p>";

$conn->close();
?>

