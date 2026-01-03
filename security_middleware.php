<?php
/**
 * Security Middleware System for Astraden
 * Comprehensive security layer for all API endpoints and pages
 */

// Prevent direct access
if (!defined('SECURITY_MIDDLEWARE_LOADED')) {
    define('SECURITY_MIDDLEWARE_LOADED', true);
}

class SecurityMiddleware {
    private $conn;
    private $rateLimitWindow = 60; // seconds
    private $maxRequestsPerWindow = 100; // per IP per window
    private $maxLoginAttempts = 5; // per IP per hour
    private $blockDuration = 3600; // seconds (1 hour)
    
    public function __construct($database_connection) {
        $this->conn = $database_connection;
        $this->initializeSecurityTables();
    }
    
    /**
     * Initialize security-related database tables
     */
    private function initializeSecurityTables() {
        // Rate limiting table
        $this->conn->query("CREATE TABLE IF NOT EXISTS `rate_limits` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `ip_address` VARCHAR(45) NOT NULL,
            `endpoint` VARCHAR(255) NOT NULL,
            `request_count` INT(11) NOT NULL DEFAULT 1,
            `window_start` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `last_request` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_ip_endpoint` (`ip_address`, `endpoint`, `window_start`),
            KEY `idx_ip` (`ip_address`),
            KEY `idx_window` (`window_start`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // Security logs table
        $this->conn->query("CREATE TABLE IF NOT EXISTS `security_logs` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `user_id` INT(11) DEFAULT NULL,
            `ip_address` VARCHAR(45) NOT NULL,
            `user_agent` TEXT,
            `action` VARCHAR(100) NOT NULL,
            `endpoint` VARCHAR(255) NOT NULL,
            `status` ENUM('allowed', 'blocked', 'suspicious', 'failed') NOT NULL,
            `reason` TEXT,
            `request_data` TEXT,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_ip` (`ip_address`),
            KEY `idx_user` (`user_id`),
            KEY `idx_status` (`status`),
            KEY `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // Blocked IPs table
        $this->conn->query("CREATE TABLE IF NOT EXISTS `blocked_ips` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `ip_address` VARCHAR(45) NOT NULL,
            `reason` TEXT NOT NULL,
            `blocked_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `expires_at` TIMESTAMP NULL DEFAULT NULL,
            `is_permanent` TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_ip` (`ip_address`),
            KEY `idx_expires` (`expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // Request nonces table (for replay attack protection)
        $this->conn->query("CREATE TABLE IF NOT EXISTS `request_nonces` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `nonce` VARCHAR(64) NOT NULL,
            `user_id` INT(11) DEFAULT NULL,
            `ip_address` VARCHAR(45) NOT NULL,
            `endpoint` VARCHAR(255) NOT NULL,
            `expires_at` TIMESTAMP NOT NULL,
            `used` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_nonce` (`nonce`),
            KEY `idx_expires` (`expires_at`),
            KEY `idx_used` (`used`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // Clean up old records periodically
        $this->cleanupOldRecords();
    }
    
    /**
     * Get client IP address
     */
    public function getClientIP() {
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
        
        // Handle multiple IPs (take first)
        if (strpos($ipaddress, ',') !== false) {
            $ipaddress = trim(explode(',', $ipaddress)[0]);
        }
        
        return filter_var($ipaddress, FILTER_VALIDATE_IP) ? $ipaddress : 'UNKNOWN';
    }
    
    /**
     * Check if IP is blocked
     */
    public function isIPBlocked($ip_address = null) {
        if ($ip_address === null) {
            $ip_address = $this->getClientIP();
        }
        
        $stmt = $this->conn->prepare("
            SELECT id, reason, expires_at, is_permanent 
            FROM blocked_ips 
            WHERE ip_address = ? 
            AND (is_permanent = 1 OR expires_at IS NULL OR expires_at > NOW())
        ");
        $stmt->bind_param("s", $ip_address);
        $stmt->execute();
        $result = $stmt->get_result();
        $blocked = $result->num_rows > 0;
        $stmt->close();
        
        return $blocked;
    }
    
    /**
     * Block an IP address
     */
    public function blockIP($ip_address, $reason, $duration_hours = 24, $permanent = false) {
        $expires_at = $permanent ? null : date('Y-m-d H:i:s', time() + ($duration_hours * 3600));
        
        $stmt = $this->conn->prepare("
            INSERT INTO blocked_ips (ip_address, reason, expires_at, is_permanent) 
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                reason = VALUES(reason),
                expires_at = VALUES(expires_at),
                is_permanent = VALUES(is_permanent),
                blocked_at = CURRENT_TIMESTAMP
        ");
        $is_permanent = $permanent ? 1 : 0;
        $stmt->bind_param("sssi", $ip_address, $reason, $expires_at, $is_permanent);
        $stmt->execute();
        $stmt->close();
        
        $this->logSecurityEvent(null, $ip_address, 'ip_blocked', $_SERVER['REQUEST_URI'] ?? '', 'blocked', $reason);
    }
    
    /**
     * Rate limiting check
     */
    public function checkRateLimit($endpoint, $max_requests = null, $window_seconds = null) {
        $ip_address = $this->getClientIP();
        
        // Check if IP is blocked
        if ($this->isIPBlocked($ip_address)) {
            $this->logSecurityEvent(null, $ip_address, 'rate_limit_blocked', $endpoint, 'blocked', 'IP is blocked');
            return ['allowed' => false, 'reason' => 'IP address is blocked'];
        }
        
        $max_requests = $max_requests ?? $this->maxRequestsPerWindow;
        $window_seconds = $window_seconds ?? $this->rateLimitWindow;
        
        $window_start = date('Y-m-d H:i:s', floor(time() / $window_seconds) * $window_seconds);
        
        // Get or create rate limit record
        $stmt = $this->conn->prepare("
            INSERT INTO rate_limits (ip_address, endpoint, request_count, window_start) 
            VALUES (?, ?, 1, ?)
            ON DUPLICATE KEY UPDATE 
                request_count = request_count + 1,
                last_request = CURRENT_TIMESTAMP
        ");
        $stmt->bind_param("sss", $ip_address, $endpoint, $window_start);
        $stmt->execute();
        $stmt->close();
        
        // Check current count
        $stmt = $this->conn->prepare("
            SELECT request_count 
            FROM rate_limits 
            WHERE ip_address = ? AND endpoint = ? AND window_start = ?
        ");
        $stmt->bind_param("sss", $ip_address, $endpoint, $window_start);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $count = intval($data['request_count'] ?? 0);
        $stmt->close();
        
        if ($count > $max_requests) {
            // Auto-block after excessive requests
            if ($count > ($max_requests * 3)) {
                $this->blockIP($ip_address, "Excessive requests: {$count} requests in {$window_seconds} seconds", 1);
            }
            
            $this->logSecurityEvent(null, $ip_address, 'rate_limit_exceeded', $endpoint, 'blocked', "{$count} requests exceeded limit of {$max_requests}");
            return ['allowed' => false, 'reason' => 'Rate limit exceeded', 'retry_after' => $window_seconds];
        }
        
        return ['allowed' => true, 'remaining' => max(0, $max_requests - $count)];
    }
    
    /**
     * Generate CSRF token
     */
    public function generateCSRFToken($user_id = null) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(32));
        $token_key = $user_id ? "csrf_token_{$user_id}" : 'csrf_token';
        $_SESSION[$token_key] = $token;
        $_SESSION[$token_key . '_time'] = time();
        
        return $token;
    }
    
    /**
     * Validate CSRF token
     */
    public function validateCSRFToken($token, $user_id = null) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $token_key = $user_id ? "csrf_token_{$user_id}" : 'csrf_token';
        $stored_token = $_SESSION[$token_key] ?? null;
        $token_time = $_SESSION[$token_key . '_time'] ?? 0;
        
        // Token expires after 2 hours
        if (time() - $token_time > 7200) {
            return false;
        }
        
        if ($stored_token === null || !hash_equals($stored_token, $token)) {
            $this->logSecurityEvent($user_id, $this->getClientIP(), 'csrf_validation_failed', $_SERVER['REQUEST_URI'] ?? '', 'blocked', 'Invalid CSRF token');
            return false;
        }
        
        return true;
    }
    
    /**
     * Generate nonce for replay attack protection
     */
    public function generateNonce($user_id = null, $endpoint = null) {
        $nonce = bin2hex(random_bytes(32));
        $ip_address = $this->getClientIP();
        $endpoint = $endpoint ?? ($_SERVER['REQUEST_URI'] ?? '');
        $expires_at = date('Y-m-d H:i:s', time() + 300); // 5 minutes
        
        $stmt = $this->conn->prepare("
            INSERT INTO request_nonces (nonce, user_id, ip_address, endpoint, expires_at) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("siss", $nonce, $user_id, $ip_address, $endpoint, $expires_at);
        $stmt->execute();
        $stmt->close();
        
        return $nonce;
    }
    
    /**
     * Validate nonce (prevents replay attacks)
     */
    public function validateNonce($nonce, $user_id = null) {
        if (empty($nonce)) {
            return false;
        }
        
        $ip_address = $this->getClientIP();
        
        $stmt = $this->conn->prepare("
            SELECT id, used, expires_at 
            FROM request_nonces 
            WHERE nonce = ? 
            AND (user_id = ? OR user_id IS NULL)
            AND ip_address = ?
        ");
        $stmt->bind_param("sis", $nonce, $user_id, $ip_address);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        
        if (!$data) {
            $this->logSecurityEvent($user_id, $ip_address, 'nonce_validation_failed', $_SERVER['REQUEST_URI'] ?? '', 'blocked', 'Invalid or expired nonce');
            return false;
        }
        
        if ($data['used'] == 1) {
            $this->logSecurityEvent($user_id, $ip_address, 'nonce_replay_attempt', $_SERVER['REQUEST_URI'] ?? '', 'blocked', 'Nonce already used (replay attack)');
            return false;
        }
        
        if (strtotime($data['expires_at']) < time()) {
            return false;
        }
        
        // Mark as used
        $stmt = $this->conn->prepare("UPDATE request_nonces SET used = 1 WHERE id = ?");
        $stmt->bind_param("i", $data['id']);
        $stmt->execute();
        $stmt->close();
        
        return true;
    }
    
    /**
     * Sanitize input
     */
    public function sanitizeInput($input, $type = 'string') {
        if (is_array($input)) {
            return array_map(function($item) use ($type) {
                return $this->sanitizeInput($item, $type);
            }, $input);
        }
        
        switch ($type) {
            case 'int':
                return intval($input);
            case 'float':
                return floatval($input);
            case 'email':
                return filter_var($input, FILTER_SANITIZE_EMAIL);
            case 'url':
                return filter_var($input, FILTER_SANITIZE_URL);
            case 'string':
            default:
                // Remove null bytes and trim
                $input = str_replace("\0", '', $input);
                $input = trim($input);
                // Escape for HTML
                return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        }
    }
    
    /**
     * Validate input
     */
    public function validateInput($input, $rules) {
        $errors = [];
        
        foreach ($rules as $field => $rule_set) {
            $value = $input[$field] ?? null;
            $required = $rule_set['required'] ?? false;
            
            if ($required && ($value === null || $value === '')) {
                $errors[$field] = "Field '{$field}' is required";
                continue;
            }
            
            if ($value === null || $value === '') {
                continue; // Skip validation for empty optional fields
            }
            
            // Type validation
            if (isset($rule_set['type'])) {
                switch ($rule_set['type']) {
                    case 'int':
                        if (!is_numeric($value) || intval($value) != $value) {
                            $errors[$field] = "Field '{$field}' must be an integer";
                        }
                        break;
                    case 'float':
                        if (!is_numeric($value)) {
                            $errors[$field] = "Field '{$field}' must be a number";
                        }
                        break;
                    case 'email':
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field] = "Field '{$field}' must be a valid email";
                        }
                        break;
                    case 'regex':
                        if (!preg_match($rule_set['pattern'], $value)) {
                            $errors[$field] = $rule_set['message'] ?? "Field '{$field}' format is invalid";
                        }
                        break;
                }
            }
            
            // Range validation
            if (isset($rule_set['min']) && $value < $rule_set['min']) {
                $errors[$field] = "Field '{$field}' must be at least {$rule_set['min']}";
            }
            if (isset($rule_set['max']) && $value > $rule_set['max']) {
                $errors[$field] = "Field '{$field}' must be at most {$rule_set['max']}";
            }
            
            // Length validation
            if (isset($rule_set['min_length']) && strlen($value) < $rule_set['min_length']) {
                $errors[$field] = "Field '{$field}' must be at least {$rule_set['min_length']} characters";
            }
            if (isset($rule_set['max_length']) && strlen($value) > $rule_set['max_length']) {
                $errors[$field] = "Field '{$field}' must be at most {$rule_set['max_length']} characters";
            }
        }
        
        return $errors;
    }
    
    /**
     * Log security event
     */
    public function logSecurityEvent($user_id, $ip_address, $action, $endpoint, $status, $reason = null, $request_data = null) {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $request_data_json = $request_data ? json_encode($request_data) : null;
        
        $stmt = $this->conn->prepare("
            INSERT INTO security_logs (user_id, ip_address, user_agent, action, endpoint, status, reason, request_data) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("issssss", $user_id, $ip_address, $user_agent, $action, $endpoint, $status, $reason, $request_data_json);
        $stmt->execute();
        $stmt->close();
        
        // Auto-block after multiple suspicious activities
        if ($status === 'suspicious' || $status === 'blocked') {
            $this->checkAndAutoBlock($ip_address);
        }
    }
    
    /**
     * Check and auto-block IP after multiple violations
     */
    private function checkAndAutoBlock($ip_address) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as violation_count 
            FROM security_logs 
            WHERE ip_address = ? 
            AND status IN ('blocked', 'suspicious') 
            AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->bind_param("s", $ip_address);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $violation_count = intval($data['violation_count'] ?? 0);
        $stmt->close();
        
        // Block after 10 violations in 1 hour
        if ($violation_count >= 10 && !$this->isIPBlocked($ip_address)) {
            $this->blockIP($ip_address, "Auto-blocked after {$violation_count} security violations", 24);
        }
    }
    
    /**
     * Clean up old records
     */
    private function cleanupOldRecords() {
        // Clean up old rate limits (older than 1 hour)
        $this->conn->query("DELETE FROM rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        
        // Clean up old nonces (older than 1 hour)
        $this->conn->query("DELETE FROM request_nonces WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        
        // Clean up old security logs (older than 90 days)
        $this->conn->query("DELETE FROM security_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
        
        // Clean up expired IP blocks
        $this->conn->query("DELETE FROM blocked_ips WHERE expires_at IS NOT NULL AND expires_at < NOW() AND is_permanent = 0");
    }
    
    /**
     * Validate score submission (server-side validation)
     * This ensures scores are reasonable and not manipulated
     */
    public function validateScore($score, $game_name, $user_id, $session_id) {
        // Score must be non-negative integer
        if (!is_numeric($score) || $score < 0 || $score != intval($score)) {
            return ['valid' => false, 'reason' => 'Invalid score format'];
        }
        
        $score = intval($score);
        
        // Get game-specific max score limits (configured per game)
        $stmt = $this->conn->prepare("SELECT max_score_limit FROM games WHERE game_name = ?");
        $stmt->bind_param("s", $game_name);
        $stmt->execute();
        $result = $stmt->get_result();
        $game_data = $result->fetch_assoc();
        $stmt->close();
        
        $max_score = intval($game_data['max_score_limit'] ?? 1000000); // Default max
        
        if ($score > $max_score) {
            $this->logSecurityEvent($user_id, $this->getClientIP(), 'suspicious_score', 'save_score', 'suspicious', "Score {$score} exceeds max limit {$max_score}");
            return ['valid' => false, 'reason' => 'Score exceeds maximum allowed'];
        }
        
        // Check for rapid score increases (potential cheating)
        $stmt = $this->conn->prepare("
            SELECT MAX(score) as max_score 
            FROM game_leaderboard 
            WHERE user_id = ? AND game_name = ? 
            AND played_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->bind_param("is", $user_id, $game_name);
        $stmt->execute();
        $result = $stmt->get_result();
        $prev_data = $result->fetch_assoc();
        $stmt->close();
        
        $prev_max = intval($prev_data['max_score'] ?? 0);
        
        // If score increased by more than 500% in 1 hour, flag as suspicious
        if ($prev_max > 0 && $score > ($prev_max * 5)) {
            $this->logSecurityEvent($user_id, $this->getClientIP(), 'suspicious_score_increase', 'save_score', 'suspicious', "Score jumped from {$prev_max} to {$score}");
            // Still allow, but log for review
        }
        
        // Verify session exists and is valid
        $stmt = $this->conn->prepare("SELECT id FROM game_sessions WHERE id = ? AND is_active = 1");
        $stmt->bind_param("i", $session_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $session_valid = $result->num_rows > 0;
        $stmt->close();
        
        if (!$session_valid) {
            return ['valid' => false, 'reason' => 'Invalid or inactive game session'];
        }
        
        return ['valid' => true];
    }
}

// Global security instance (will be initialized in connection.php)
$GLOBALS['security'] = null;

