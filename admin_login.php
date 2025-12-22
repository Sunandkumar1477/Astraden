<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check database connection first
require_once 'connection.php';

// Check if connection is valid
if (!$conn || $conn->connect_error) {
    die("Database connection error. Please check your database configuration.");
}

// Security headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');

// Check if already logged in
if (isset($_SESSION['admin_id'])) {
    header('Location: admin_dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $security_code = trim($_POST['captcha'] ?? '');
    
    // Validate inputs
    if (empty($username) || empty($password)) {
        $error = 'Username and password are required';
    } elseif (empty($security_code)) {
        $error = 'Security code is required';
    } else {
        // Get client IP
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
        
        // Check if admin_users table exists
        $table_check = $conn->query("SHOW TABLES LIKE 'admin_users'");
        if ($table_check->num_rows == 0) {
            $error = 'Admin system not initialized. Please run the database setup first.';
        } else {
            // Check admin
            $stmt = $conn->prepare("SELECT id, username, password, email, full_name, security_code, ip_whitelist, failed_login_attempts, account_locked_until, is_active FROM admin_users WHERE username = ? AND is_active = 1");
            if (!$stmt) {
                $error = 'Database error. Please check your database configuration.';
            } else {
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    $error = 'Invalid credentials';
                    // Log failed attempt
                    try {
                        $log_stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, admin_username, action, description, ip_address, user_agent) VALUES (0, ?, 'failed_login', 'Invalid username', ?, ?)");
                        $log_stmt->bind_param("sss", $username, $ip_address, $user_agent);
                        $log_stmt->execute();
                        $log_stmt->close();
                    } catch (Exception $e) {}
                } else {
                    $admin = $result->fetch_assoc();
                    
                    // Check if account is locked
                    if ($admin['account_locked_until'] && strtotime($admin['account_locked_until']) > time()) {
                        $error = 'Account is temporarily locked. Please try again later.';
                    } else {
                        // Verify security code first
                        if ($security_code !== $admin['security_code']) {
                            $error = 'Invalid credentials';
                            try {
                                $log_stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, admin_username, action, description, ip_address, user_agent) VALUES (?, ?, 'failed_login', 'Invalid security code', ?, ?)");
                                $log_stmt->bind_param("isss", $admin['id'], $admin['username'], $ip_address, $user_agent);
                                $log_stmt->execute();
                                $log_stmt->close();
                            } catch (Exception $e) {}
                        } elseif (password_verify($password, $admin['password'])) {
                            // Successful login
                            $_SESSION['admin_id'] = $admin['id'];
                            $_SESSION['admin_username'] = $admin['username'];
                            $_SESSION['admin_email'] = $admin['email'];
                            $_SESSION['admin_full_name'] = $admin['full_name'];
                            $_SESSION['admin_login_time'] = time();
                            $_SESSION['admin_ip'] = $ip_address;
                            $_SESSION['admin_user_agent'] = $user_agent;
                            
                            // Reset failed attempts
                            $conn->query("UPDATE admin_users SET failed_login_attempts = 0, account_locked_until = NULL, last_login = CURRENT_TIMESTAMP, last_login_ip = '$ip_address' WHERE id = {$admin['id']}");
                            
                            header('Location: admin_dashboard.php');
                            exit;
                        } else {
                            $error = 'Invalid credentials';
                            $failed_attempts = $admin['failed_login_attempts'] + 1;
                            $lock_until = ($failed_attempts >= 5) ? date('Y-m-d H:i:s', time() + (30 * 60)) : null;
                            $conn->query("UPDATE admin_users SET failed_login_attempts = $failed_attempts, account_locked_until = " . ($lock_until ? "'$lock_until'" : "NULL") . " WHERE id = {$admin['id']}");
                        }
                    }
                }
                $stmt->close();
            }
        }
    }
}

// Security question
$security_questions = ["What is the master security?", "Enter the admin access:", "What is the system security key?", "Enter the authorized access:"];
$security_question = $security_questions[array_rand($security_questions)];

if ($conn && !$conn->connect_error) $conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Access - Astraden</title>
    <link rel="icon" type="image/svg+xml" href="logo.svg">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --primary-cyan: #00ffff; --primary-purple: #9d4edd; --dark-bg: #05050a; --card-bg: rgba(15, 15, 25, 0.95); }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Rajdhani', sans-serif; background: var(--dark-bg); color: white; min-height: 100vh; display: flex; justify-content: center; align-items: center; padding: 20px; overflow: hidden; }
        .space-bg { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: radial-gradient(circle at center, #1a1a2e 0%, #05050a 100%); z-index: -1; }
        
        .login-card { background: var(--card-bg); border: 2px solid var(--primary-cyan); border-radius: 20px; padding: 50px 40px; width: 100%; max-width: 450px; box-shadow: 0 0 50px rgba(0, 255, 255, 0.2); position: relative; overflow: hidden; }
        .login-card::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 5px; background: linear-gradient(90deg, var(--primary-cyan), var(--primary-purple)); }
        
        .login-header { text-align: center; margin-bottom: 40px; }
        .login-header h1 { font-family: 'Orbitron', sans-serif; color: var(--primary-cyan); font-size: 1.8rem; text-transform: uppercase; letter-spacing: 5px; margin-bottom: 10px; }
        .login-header p { color: rgba(255, 255, 255, 0.5); font-size: 0.9rem; text-transform: uppercase; letter-spacing: 2px; }
        
        .form-group { margin-bottom: 25px; }
        .form-group label { display: block; color: var(--primary-purple); font-weight: 700; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px; margin-bottom: 10px; }
        .form-group input { width: 100%; padding: 15px; background: rgba(0, 0, 0, 0.5); border: 1px solid rgba(0, 255, 255, 0.3); border-radius: 10px; color: white; font-family: 'Rajdhani', sans-serif; font-size: 1rem; outline: none; transition: all 0.3s; }
        .form-group input:focus { border-color: var(--primary-cyan); box-shadow: 0 0 15px rgba(0, 255, 255, 0.2); }
        
        .security-box { background: rgba(157, 78, 221, 0.1); border: 1px dashed var(--primary-purple); border-radius: 10px; padding: 15px; text-align: center; margin-bottom: 15px; color: var(--primary-cyan); font-weight: bold; font-family: 'Orbitron', sans-serif; font-size: 0.9rem; }
        
        .error-msg { background: rgba(255, 0, 110, 0.1); border: 1px solid #ff006e; color: #ff006e; padding: 12px; border-radius: 8px; margin-bottom: 25px; text-align: center; font-size: 0.9rem; font-weight: 600; }
        
        .submit-btn { width: 100%; padding: 16px; background: linear-gradient(135deg, var(--primary-cyan), var(--primary-purple)); border: none; border-radius: 10px; color: white; font-family: 'Orbitron', sans-serif; font-weight: 900; text-transform: uppercase; letter-spacing: 3px; cursor: pointer; transition: all 0.3s; margin-top: 10px; }
        .submit-btn:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(0, 255, 255, 0.4); filter: brightness(1.1); }
        
        .footer-note { text-align: center; margin-top: 30px; color: rgba(255, 255, 255, 0.3); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; }
    </style>
</head>
<body>
    <div class="space-bg"></div>
    <div class="login-card">
        <div class="login-header">
            <h1><i class="fas fa-user-shield"></i> ADMIN</h1>
            <p>Astraden Command Center</p>
        </div>
        
        <?php if ($error): ?><div class="error-msg"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Access Identity</label>
                <input type="text" name="username" required autocomplete="off" placeholder="Username">
            </div>
            <div class="form-group">
                <label>Access Key</label>
                <input type="password" name="password" required autocomplete="off" placeholder="Password">
            </div>
            <div class="form-group">
                <label>Security Clearance</label>
                <div class="security-box"><?php echo htmlspecialchars($security_question); ?></div>
                <input type="password" name="captcha" required placeholder="Security Answer" autocomplete="off">
            </div>
            <button type="submit" class="submit-btn">AUTHORIZE ACCESS</button>
        </form>
        
        <div class="footer-note">Authorized Personnel Only • Secure Session Enabled</div>
    </div>
    <script>if (window.history.replaceState) window.history.replaceState(null, null, window.location.href);</script>
</body>
</html>
