<?php
// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection settings
$servername = "skiller7.cvceqwaw4sm7.ap-south-2.rds.amazonaws.com";
$username = "skiller7";
$password = "JESUSjesus1477";
$db_name = "astraden";

// Create MySQLi connection
$conn = new mysqli($servername, $username, $password, $db_name);

// Create PDO connection
try {
    $connect = new PDO("mysql:host=$servername;dbname=$db_name", $username, $password);
    $connect->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("PDO Connection failed: " . $e->getMessage());
}

// Check the MySQLi connection
if ($conn->connect_error) {
    die("MySQLi Connection failed: " . $conn->connect_error);
}

// Utility function for generating a unique ID
if (!function_exists('uniqid_id')) {
    function uniqid_id() {
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';
        for ($i = 0; $i < 20; $i++) {
            $randomString .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $randomString;
    }
}


// Check if the user is logged in via session
$isLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$userInfo = [];

// If not logged in via session, check for persistent cookie
if (!$isLoggedIn && isset($_COOKIE['remember_token']) && isset($_COOKIE['user_id'])) {
    $remember_token = $_COOKIE['remember_token'];
    $cookie_user_id = $_COOKIE['user_id'];
    
    try {
        // Verify the token in database
        $token_query = "SELECT u.id, u.name, u.email, ut.expires_at 
                        FROM users u 
                        JOIN user_tokens ut ON u.id = ut.user_id 
                        WHERE ut.token = ? AND ut.user_id = ? AND ut.expires_at > NOW()";
        $token_stmt = $conn->prepare($token_query);
        $token_stmt->bind_param('si', $remember_token, $cookie_user_id);
        $token_stmt->execute();
        $token_result = $token_stmt->get_result();
        
        if ($token_result->num_rows > 0) {
            $user_data = $token_result->fetch_assoc();
            
            // Restore session
            $_SESSION['user_id'] = $user_data['id'];
            $_SESSION['user_name'] = $user_data['name'];
            $_SESSION['user_email'] = $user_data['email'];
            
            $isLoggedIn = true;
            $user_id = $user_data['id'];
            
            // Clean up expired tokens for this user
            $cleanup_stmt = $conn->prepare("DELETE FROM user_tokens WHERE user_id = ? AND expires_at <= NOW()");
            $cleanup_stmt->bind_param('i', $user_id);
            $cleanup_stmt->execute();
        } else {
            // Invalid or expired token, clear cookies
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
            setcookie('user_id', '', time() - 3600, '/', '', true, true);
        }
    } catch (Exception $e) {
        // Database error, clear cookies and log error
        error_log("Persistent login error: " . $e->getMessage());
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        setcookie('user_id', '', time() - 3600, '/', '', true, true);
    }
}

if ($isLoggedIn) {
    $user_id = $_SESSION['user_id'];

    // Fetch user data from the database
    $query = "SELECT profile_photo FROM user_information WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $userInfo = $result->fetch_assoc();
    }
}
// // Set session variable for login status and user data
// $isLoggedIn = isset($_SESSION['user_token']);
// $userInfo = [];

// if ($isLoggedIn) {
//     // Retrieve user data using the session token
//     $stmt = $conn->prepare("SELECT * FROM users WHERE token = ?");
//     $stmt->bind_param("s", $_SESSION['user_token']);
//     $stmt->execute();
//     $result = $stmt->get_result();

//     if ($result && $result->num_rows > 0) {
//         $userInfo = $result->fetch_assoc();
//     }
// }
?>
