<?php
session_start();
require_once 'connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$username = trim($_POST['username'] ?? '');
$mobile = trim($_POST['mobile'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$referral_code = strtoupper(trim($_POST['referral_code'] ?? ''));
$accept_terms = isset($_POST['accept_terms']) && $_POST['accept_terms'] === 'on';

// Validation
$errors = [];

// Validate terms acceptance
if (!$accept_terms) {
    $errors[] = 'You must accept the Terms and Conditions to create an account';
}

if (empty($username)) {
    $errors[] = 'Username is required';
} elseif (strlen($username) < 3 || strlen($username) > 50) {
    $errors[] = 'Username must be between 3 and 50 characters';
} elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    $errors[] = 'Username can only contain letters, numbers, and underscores';
}

if (empty($mobile)) {
    $errors[] = 'Mobile number is required';
} elseif (!preg_match('/^[0-9]{10,15}$/', $mobile)) {
    $errors[] = 'Mobile number must be 10-15 digits';
}

if (empty($password)) {
    $errors[] = 'Password is required';
} elseif (strlen($password) < 6) {
    $errors[] = 'Password must be at least 6 characters';
}

if ($password !== $confirm_password) {
    $errors[] = 'Passwords do not match';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
    exit;
}

// Check if username already exists
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR mobile_number = ?");
$stmt->bind_param("ss", $username, $mobile);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $check_stmt = $conn->prepare("SELECT username FROM users WHERE username = ?");
    $check_stmt->bind_param("s", $username);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Username already exists']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Mobile number already registered']);
    }
    $stmt->close();
    exit;
}
$stmt->close();

// Validate referral code if provided
$referred_by = null;
if (!empty($referral_code)) {
    if (!preg_match('/^[A-Z0-9]{4}$/', $referral_code)) {
        echo json_encode(['success' => false, 'message' => 'Invalid referral code format. Must be 4 alphanumeric characters.']);
        exit;
    }
    
    // Check if referral code exists
    $ref_stmt = $conn->prepare("SELECT id FROM users WHERE referral_code = ?");
    $ref_stmt->bind_param("s", $referral_code);
    $ref_stmt->execute();
    $ref_result = $ref_stmt->get_result();
    
    if ($ref_result->num_rows > 0) {
        $ref_data = $ref_result->fetch_assoc();
        $referred_by = $ref_data['id'];
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid referral code. Please check and try again.']);
        $ref_stmt->close();
        exit;
    }
    $ref_stmt->close();
}

// Generate unique 4-digit referral code for new user
function generateReferralCode($conn) {
    $max_attempts = 100;
    $attempts = 0;
    
    while ($attempts < $max_attempts) {
        // Generate random 4-digit alphanumeric code
        $code = strtoupper(substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 4));
        
        // Check if code already exists
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE referral_code = ?");
        $check_stmt->bind_param("s", $code);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows == 0) {
            $check_stmt->close();
            return $code;
        }
        
        $check_stmt->close();
        $attempts++;
    }
    
    // Fallback: use timestamp-based code if random fails
    return strtoupper(substr(md5(uniqid(rand(), true)), 0, 4));
}

$new_referral_code = generateReferralCode($conn);

// Hash password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Insert user with referral code
if ($referred_by) {
    $stmt = $conn->prepare("INSERT INTO users (username, mobile_number, password, referral_code, referred_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $username, $mobile, $hashed_password, $new_referral_code, $referred_by);
} else {
    $stmt = $conn->prepare("INSERT INTO users (username, mobile_number, password, referral_code) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $username, $mobile, $hashed_password, $new_referral_code);
}

if ($stmt->execute()) {
    $user_id = $stmt->insert_id;
    
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
    
    // Log registration
    try {
        $log_stmt = $conn->prepare("INSERT INTO login_logs (user_id, username, mobile_number, action, ip_address, user_agent, status) VALUES (?, ?, ?, 'register', ?, ?, 'success')");
        if ($log_stmt) {
            $log_stmt->bind_param("issss", $user_id, $username, $mobile, $ip_address, $user_agent);
            $log_stmt->execute();
            $log_stmt->close();
        }
    } catch (Exception $e) {
        // Table might not exist, continue without logging
    }
    
    // Auto login after registration
    $_SESSION['user_id'] = $user_id;
    $_SESSION['username'] = $username;
    $_SESSION['mobile'] = $mobile;
    $_SESSION['login_time'] = time();
    
    // Log successful login after registration
    try {
        $login_log_stmt = $conn->prepare("INSERT INTO login_logs (user_id, username, mobile_number, action, ip_address, user_agent, status) VALUES (?, ?, ?, 'login', ?, ?, 'success')");
        if ($login_log_stmt) {
            $login_log_stmt->bind_param("issss", $user_id, $username, $mobile, $ip_address, $user_agent);
            $login_log_stmt->execute();
            $log_id = $login_log_stmt->insert_id;
            $login_log_stmt->close();
            $_SESSION['login_log_id'] = $log_id;
        } else {
            $_SESSION['login_log_id'] = null;
        }
    } catch (Exception $e) {
        // Table might not exist, continue without logging
        $_SESSION['login_log_id'] = null;
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Registration successful!',
        'user' => [
            'id' => $user_id,
            'username' => $username
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
}

$stmt->close();
$conn->close();
?>

