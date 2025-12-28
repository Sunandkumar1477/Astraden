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

// Check the MySQLi connection
if ($conn->connect_error) {
    error_log("MySQLi Connection failed: " . $conn->connect_error);
    die("Database connection failed. Please try again later.");
}

// Set charset to utf8mb4 for proper character support
$conn->set_charset("utf8mb4");

// Create PDO connection
try {
    $connect = new PDO("mysql:host=$servername;dbname=$db_name;charset=utf8mb4", $username, $password);
    $connect->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("PDO Connection failed: " . $e->getMessage());
    // Don't die here, MySQLi connection is primary
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
        // Check if user_tokens table exists before trying to use it
        $table_check = $conn->query("SHOW TABLES LIKE 'user_tokens'");
        if ($table_check && $table_check->num_rows > 0) {
            // Verify the token in database
            $token_query = "SELECT u.id, u.username, u.mobile_number, u.name, u.email, ut.expires_at 
                            FROM users u 
                            JOIN user_tokens ut ON u.id = ut.user_id 
                            WHERE ut.token = ? AND ut.user_id = ? AND ut.expires_at > NOW()";
            $token_stmt = $conn->prepare($token_query);
            if ($token_stmt) {
                $token_stmt->bind_param('si', $remember_token, $cookie_user_id);
                $token_stmt->execute();
                $token_result = $token_stmt->get_result();
                
                if ($token_result->num_rows > 0) {
                    $user_data = $token_result->fetch_assoc();
                    
                    // Restore session
                    $_SESSION['user_id'] = $user_data['id'];
                    if (isset($user_data['username'])) {
                        $_SESSION['username'] = $user_data['username'];
                    }
                    if (isset($user_data['mobile_number'])) {
                        $_SESSION['mobile'] = $user_data['mobile_number'];
                    }
                    if (isset($user_data['name'])) {
                        $_SESSION['user_name'] = $user_data['name'];
                    }
                    if (isset($user_data['email'])) {
                        $_SESSION['user_email'] = $user_data['email'];
                    }
                    
                    $isLoggedIn = true;
                    $user_id = $user_data['id'];
                    
                    // Clean up expired tokens for this user
                    $cleanup_stmt = $conn->prepare("DELETE FROM user_tokens WHERE user_id = ? AND expires_at <= NOW()");
                    if ($cleanup_stmt) {
                        $cleanup_stmt->bind_param('i', $user_id);
                        $cleanup_stmt->execute();
                        $cleanup_stmt->close();
                    }
                } else {
                    // Invalid or expired token, clear cookies
                    setcookie('remember_token', '', time() - 3600, '/', '', true, true);
                    setcookie('user_id', '', time() - 3600, '/', '', true, true);
                }
                $token_stmt->close();
            }
        } else {
            // Table doesn't exist, clear cookies
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
            setcookie('user_id', '', time() - 3600, '/', '', true, true);
        }
        if ($table_check) {
            $table_check->close();
        }
    } catch (Exception $e) {
        // Database error, clear cookies and log error silently
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        setcookie('user_id', '', time() - 3600, '/', '', true, true);
    }
}

if ($isLoggedIn) {
    $user_id = $_SESSION['user_id'];

    // Fetch user data from the database (only if table exists)
    try {
        $table_check = $conn->query("SHOW TABLES LIKE 'user_information'");
        if ($table_check && $table_check->num_rows > 0) {
            $query = "SELECT profile_photo FROM user_information WHERE user_id = ?";
            $stmt = $conn->prepare($query);
            if ($stmt) {
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result && $result->num_rows > 0) {
                    $userInfo = $result->fetch_assoc();
                }
                $stmt->close();
            }
        }
        if ($table_check) {
            $table_check->close();
        }
    } catch (Exception $e) {
        // Table doesn't exist or error, continue without user info
        $userInfo = [];
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
