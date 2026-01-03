<?php
/**
 * Security Log Endpoint
 * Receives client-side security events and logs them
 */

session_start();
require_once 'connection.php';
require_once 'security_middleware.php';

// Initialize security
if ($GLOBALS['security'] === null) {
    $GLOBALS['security'] = new SecurityMiddleware($conn);
}

$security = $GLOBALS['security'];

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? 'unknown';
$user_id = $_SESSION['user_id'] ?? null;
$ip_address = $security->getClientIP();

// Log the security event
$security->logSecurityEvent(
    $user_id,
    $ip_address,
    'client_security_event',
    'security_log',
    'suspicious',
    "Client-side event: {$action}",
    $input
);

echo json_encode(['success' => true]);

$conn->close();
?>

