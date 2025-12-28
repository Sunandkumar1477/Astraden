<?php
// Prevent any output before JSON
ob_start();

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
require_once 'connection.php';

// Check database connection
if (!$conn || $conn->connect_error) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed. Please try again later.']);
    exit;
}

// Clear any output buffer
while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// Validation
if (empty($username)) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Username is required']);
    exit;
}

if (empty($password)) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Password is required']);
    exit;
}

// Get IP address and user agent for logging
function getClientIP() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}

$ip_address = getClientIP();
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

// Check user (can login with username or mobile number)
$stmt = $conn->prepare("SELECT id, username, mobile_number, password FROM users WHERE username = ? OR mobile_number = ?");
if (!$stmt) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$stmt->bind_param("ss", $username, $username);
if (!$stmt->execute()) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
    $stmt->close();
    exit;
}

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Log failed login attempt (user not found)
    try {
        $log_stmt = $conn->prepare("INSERT INTO login_logs (user_id, username, mobile_number, action, ip_address, user_agent, status, failure_reason) VALUES (0, ?, '', 'failed_login', ?, ?, 'failed', 'User not found')");
        if ($log_stmt) {
            $log_stmt->bind_param("sss", $username, $ip_address, $user_agent);
            $log_stmt->execute();
            $log_stmt->close();
        }
    } catch (Exception $e) {
        // Table might not exist, continue without logging
    }
    
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid username']);
    $stmt->close();
    $conn->close();
    exit;
}

$user = $result->fetch_assoc();

// Check if password exists
if (empty($user['password'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Account password not set. Please contact support.']);
    $stmt->close();
    $conn->close();
    exit;
}

// Verify password FIRST before checking sessions
if (!password_verify($password, $user['password'])) {
    // Log failed login attempt (wrong password)
    try {
        $log_stmt = $conn->prepare("INSERT INTO login_logs (user_id, username, mobile_number, action, ip_address, user_agent, status, failure_reason) VALUES (?, ?, ?, 'failed_login', ?, ?, 'failed', 'Invalid password')");
        if ($log_stmt) {
            $log_stmt->bind_param("issss", $user['id'], $user['username'], $user['mobile_number'], $ip_address, $user_agent);
            $log_stmt->execute();
            $log_stmt->close();
        }
    } catch (Exception $e) {
        // Table might not exist, continue without logging
    }
    
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid password']);
    $stmt->close();
    $conn->close();
    exit;
}

// Check if user is already logged in on another device/browser
// Check if session_token column exists first
$has_session_column = false;
try {
    $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'session_token'");
    if ($check_column) {
        $has_session_column = $check_column->num_rows > 0;
        $check_column->close();
    }
} catch (Exception $e) {
    // Column doesn't exist or table error, continue without session token check
    $has_session_column = false;
}

// Check for active session and handle confirmation
$force_login = isset($_POST['force_login']) && $_POST['force_login'] === 'true';

if ($has_session_column && !$force_login) {
    try {
        // Check if last_session_user_agent column exists
        $has_user_agent_column = false;
        try {
            $check_ua_column = $conn->query("SHOW COLUMNS FROM users LIKE 'last_session_user_agent'");
            if ($check_ua_column) {
                $has_user_agent_column = $check_ua_column->num_rows > 0;
                $check_ua_column->close();
            }
        } catch (Exception $e) {
            $has_user_agent_column = false;
        }
        
        if ($has_user_agent_column) {
            $check_session_stmt = $conn->prepare("SELECT session_token, last_session_ip, last_session_user_agent, last_session_time FROM users WHERE id = ?");
        } else {
            $check_session_stmt = $conn->prepare("SELECT session_token, last_session_ip, last_session_time FROM users WHERE id = ?");
        }
        
        $check_session_stmt->bind_param("i", $user['id']);
        $check_session_stmt->execute();
        $session_result = $check_session_stmt->get_result();
        $session_data = $session_result->fetch_assoc();
        $check_session_stmt->close();
        
        // If user has an active session token, ask for confirmation
        if (!empty($session_data['session_token'])) {
            // Determine if it's same device or different device
            $same_ip = isset($session_data['last_session_ip']) && $session_data['last_session_ip'] === $ip_address;
            $different_browser = false;
            
            if ($has_user_agent_column && isset($session_data['last_session_user_agent'])) {
                $different_browser = $session_data['last_session_user_agent'] !== $user_agent;
            }
            
            // Return confirmation request
            ob_end_clean();
            echo json_encode([
                'success' => false,
                'requires_confirmation' => true,
                'message' => 'You are already logged in on another device or browser. Do you want to continue on this device? This will automatically log you out from the other device.',
                'same_device' => $same_ip && $different_browser
            ]);
            $stmt->close();
            $conn->close();
            exit;
        }
    } catch (Exception $e) {
        // Error checking session, continue with login anyway
    }
}

// Update last login
$update_stmt = $conn->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
if ($update_stmt) {
    $update_stmt->bind_param("i", $user['id']);
    $update_stmt->execute();
    $update_stmt->close();
}

// Generate unique session token
$session_token = bin2hex(random_bytes(32)); // 64 character token

// Update user with new session token and IP (if columns exist)
if ($has_session_column) {
    try {
        // Check if last_session_user_agent column exists
        $has_user_agent_column = false;
        try {
            $check_ua_column = $conn->query("SHOW COLUMNS FROM users LIKE 'last_session_user_agent'");
            if ($check_ua_column) {
                $has_user_agent_column = $check_ua_column->num_rows > 0;
                $check_ua_column->close();
            }
        } catch (Exception $e) {
            $has_user_agent_column = false;
        }
        
        if ($has_user_agent_column) {
            // Update with user agent
            $update_session_stmt = $conn->prepare("UPDATE users SET session_token = ?, last_session_ip = ?, last_session_user_agent = ?, last_session_time = CURRENT_TIMESTAMP WHERE id = ?");
            $update_session_stmt->bind_param("sssi", $session_token, $ip_address, $user_agent, $user['id']);
        } else {
            // Update without user agent
            $update_session_stmt = $conn->prepare("UPDATE users SET session_token = ?, last_session_ip = ?, last_session_time = CURRENT_TIMESTAMP WHERE id = ?");
            $update_session_stmt->bind_param("ssi", $session_token, $ip_address, $user['id']);
        }
        
        $update_session_stmt->execute();
        $update_session_stmt->close();
        
        // Store session token in session
        $_SESSION['session_token'] = $session_token;
    } catch (Exception $e) {
        // Error updating session token, continue with login anyway
        $_SESSION['session_token'] = null;
    }
} else {
    // Columns don't exist yet, set session_token to null
    $_SESSION['session_token'] = null;
}

// Set session
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['mobile'] = $user['mobile_number'];
$_SESSION['login_time'] = time(); // Store login timestamp for session duration calculation

// Log successful login
try {
    $log_stmt = $conn->prepare("INSERT INTO login_logs (user_id, username, mobile_number, action, ip_address, user_agent, status) VALUES (?, ?, ?, 'login', ?, ?, 'success')");
    if ($log_stmt) {
        $log_stmt->bind_param("issss", $user['id'], $user['username'], $user['mobile_number'], $ip_address, $user_agent);
        $log_stmt->execute();
        $log_id = $log_stmt->insert_id;
        $log_stmt->close();
        
        // Store log ID in session for logout tracking
        $_SESSION['login_log_id'] = $log_id;
    }
} catch (Exception $e) {
    // Table might not exist, continue without logging
    $_SESSION['login_log_id'] = null;
}

// Ensure we output valid JSON
// Clean all output buffers
while (ob_get_level() > 0) {
    ob_end_clean();
}

// Set proper headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

try {
    $response = [
        'success' => true,
        'message' => 'Login successful!',
        'user' => [
            'id' => (int)$user['id'],
            'username' => (string)$user['username']
        ]
    ];
    
    // Output JSON response
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Exception $e) {
    // If anything fails, send error response
    echo json_encode([
        'success' => false,
        'message' => 'Login failed. Please try again.'
    ], JSON_UNESCAPED_UNICODE);
}

if (isset($stmt)) {
    $stmt->close();
}
if (isset($conn)) {
    $conn->close();
}
exit;
?>

