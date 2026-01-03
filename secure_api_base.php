<?php
/**
 * Secure API Base - All API endpoints should extend this
 * Provides authentication, CSRF protection, rate limiting, and input validation
 */

session_start();
require_once 'connection.php';
require_once 'security_middleware.php';

// Initialize security middleware
if ($GLOBALS['security'] === null) {
    $GLOBALS['security'] = new SecurityMiddleware($conn);
}

$security = $GLOBALS['security'];

// Get client info
$client_ip = $security->getClientIP();
$user_id = $_SESSION['user_id'] ?? null;

// Check if IP is blocked
if ($security->isIPBlocked($client_ip)) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Rate limiting (endpoint-specific limits can be overridden)
$endpoint = $_SERVER['REQUEST_URI'] ?? 'unknown';
$rate_limit = $security->checkRateLimit($endpoint);

if (!$rate_limit['allowed']) {
    header('HTTP/1.1 429 Too Many Requests');
    header('Retry-After: ' . ($rate_limit['retry_after'] ?? 60));
    echo json_encode([
        'success' => false, 
        'message' => 'Rate limit exceeded. Please try again later.',
        'retry_after' => $rate_limit['retry_after'] ?? 60
    ]);
    exit;
}

// Set JSON header
header('Content-Type: application/json');

/**
 * Secure API Response Helper
 */
class SecureAPI {
    private $security;
    private $conn;
    private $requires_auth;
    private $requires_csrf;
    private $requires_nonce;
    
    public function __construct($requires_auth = true, $requires_csrf = true, $requires_nonce = true) {
        global $security, $conn;
        $this->security = $security;
        $this->conn = $conn;
        $this->requires_auth = $requires_auth;
        $this->requires_csrf = $requires_csrf;
        $this->requires_nonce = $requires_nonce;
        
        $this->validateRequest();
    }
    
    private function validateRequest() {
        // Check authentication
        if ($this->requires_auth) {
            if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
                $this->security->logSecurityEvent(null, $this->security->getClientIP(), 'unauthorized_access', $_SERVER['REQUEST_URI'] ?? '', 'blocked', 'Authentication required');
                $this->respondError('Authentication required', 401);
            }
        }
        
        // Check CSRF token for POST/PUT/DELETE requests
        if ($this->requires_csrf && in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'])) {
            $csrf_token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
            if (!$csrf_token || !$this->security->validateCSRFToken($csrf_token, $_SESSION['user_id'] ?? null)) {
                $this->security->logSecurityEvent($_SESSION['user_id'] ?? null, $this->security->getClientIP(), 'csrf_failed', $_SERVER['REQUEST_URI'] ?? '', 'blocked', 'Invalid CSRF token');
                $this->respondError('Invalid security token', 403);
            }
        }
        
        // Check nonce for replay attack protection
        if ($this->requires_nonce && in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'])) {
            $nonce = $_POST['nonce'] ?? $_SERVER['HTTP_X_NONCE'] ?? null;
            if (!$nonce || !$this->security->validateNonce($nonce, $_SESSION['user_id'] ?? null)) {
                $this->security->logSecurityEvent($_SESSION['user_id'] ?? null, $this->security->getClientIP(), 'nonce_failed', $_SERVER['REQUEST_URI'] ?? '', 'blocked', 'Invalid or expired nonce');
                $this->respondError('Request expired or already processed', 403);
            }
        }
    }
    
    public function sanitizeInput($data, $rules = []) {
        $sanitized = [];
        foreach ($data as $key => $value) {
            $type = $rules[$key]['type'] ?? 'string';
            $sanitized[$key] = $this->security->sanitizeInput($value, $type);
        }
        return $sanitized;
    }
    
    public function validateInput($data, $rules) {
        $errors = $this->security->validateInput($data, $rules);
        if (!empty($errors)) {
            $this->respondError('Validation failed', 400, ['errors' => $errors]);
        }
        return true;
    }
    
    public function respondSuccess($data = [], $message = 'Success') {
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
        exit;
    }
    
    public function respondError($message, $http_code = 400, $additional = []) {
        http_response_code($http_code);
        echo json_encode(array_merge([
            'success' => false,
            'message' => $message
        ], $additional));
        exit;
    }
    
    public function getSecurity() {
        return $this->security;
    }
    
    public function getConnection() {
        return $this->conn;
    }
}

/**
 * Generate CSRF token and nonce for client
 */
function getSecurityTokens() {
    global $security;
    $user_id = $_SESSION['user_id'] ?? null;
    
    return [
        'csrf_token' => $security->generateCSRFToken($user_id),
        'nonce' => $security->generateNonce($user_id, $_SERVER['REQUEST_URI'] ?? ''),
        'timestamp' => time()
    ];
}

// If this is a GET request for tokens, return them
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_tokens'])) {
    echo json_encode([
        'success' => true,
        'tokens' => getSecurityTokens()
    ]);
    exit;
}

?>

