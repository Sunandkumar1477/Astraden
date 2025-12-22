<?php
require_once 'connection.php';

// Create login_logs table
$sql = "CREATE TABLE IF NOT EXISTS login_logs (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    username VARCHAR(50) NOT NULL,
    mobile_number VARCHAR(15) NOT NULL,
    action VARCHAR(20) NOT NULL COMMENT 'login, logout, failed_login',
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

if ($conn->query($sql) === TRUE) {
    echo "Table 'login_logs' created successfully or already exists.<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

$conn->close();
?>

