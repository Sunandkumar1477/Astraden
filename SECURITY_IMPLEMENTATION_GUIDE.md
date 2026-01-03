# Astraden Security Implementation Guide

## Overview
This guide documents the comprehensive security system implemented for the Astraden browser-based space game to prevent hacking, cheating, and exploitation.

## Security Features Implemented

### 1. Security Middleware System (`security_middleware.php`)
- **CSRF Protection**: Token-based protection against Cross-Site Request Forgery
- **Rate Limiting**: Prevents spam and bot attacks
- **IP Blocking**: Automatic blocking of abusive IPs
- **Replay Attack Protection**: Nonce-based system prevents request replay
- **Input Validation & Sanitization**: Comprehensive validation and sanitization
- **Activity Logging**: All security events are logged

### 2. Secure API Base (`secure_api_base.php`)
- All API endpoints should use this base class
- Automatic authentication checks
- CSRF token validation
- Nonce validation for replay protection
- Rate limiting enforcement

### 3. Database Security Tables
Run `create_security_tables.sql` to create:
- `rate_limits`: Tracks API request rates per IP
- `security_logs`: Logs all security events
- `blocked_ips`: Manages blocked IP addresses
- `request_nonces`: Prevents replay attacks

## Implementation Steps

### Step 1: Database Setup
```sql
-- Run the security tables SQL
SOURCE create_security_tables.sql;

-- Or manually execute the SQL in create_security_tables.sql
```

### Step 2: Update Connection File
The `connection.php` file should initialize the security middleware:
```php
require_once 'security_middleware.php';
$GLOBALS['security'] = new SecurityMiddleware($conn);
```

### Step 3: Secure API Endpoints

#### Example: Secure Game API
```php
<?php
require_once 'secure_api_base.php';

// Initialize secure API (requires auth, CSRF, nonce)
$api = new SecureAPI(true, true, true);

// Get sanitized input
$input = $api->sanitizeInput($_POST, [
    'score' => ['type' => 'int'],
    'game_name' => ['type' => 'string']
]);

// Validate input
$api->validateInput($input, [
    'score' => ['required' => true, 'type' => 'int', 'min' => 0, 'max' => 1000000],
    'game_name' => ['required' => true, 'type' => 'regex', 'pattern' => '/^[a-z0-9-]+$/', 'message' => 'Invalid game name']
]);

// Validate score server-side
$user_id = $_SESSION['user_id'];
$score_validation = $api->getSecurity()->validateScore(
    $input['score'], 
    $input['game_name'], 
    $user_id, 
    $input['session_id']
);

if (!$score_validation['valid']) {
    $api->respondError($score_validation['reason'], 400);
}

// Process score...
$api->respondSuccess(['score' => $input['score']], 'Score saved');
?>
```

### Step 4: Client-Side Security

#### A. CSRF Token & Nonce in Forms
```javascript
// Get security tokens before making requests
async function getSecurityTokens() {
    const response = await fetch('secure_api_base.php?get_tokens=1');
    const data = await response.json();
    return data.tokens;
}

// Use tokens in API calls
async function saveScore(score) {
    const tokens = await getSecurityTokens();
    
    const formData = new FormData();
    formData.append('score', score);
    formData.append('csrf_token', tokens.csrf_token);
    formData.append('nonce', tokens.nonce);
    
    const response = await fetch('game_api.php?action=save_score', {
        method: 'POST',
        body: formData
    });
    
    return response.json();
}
```

#### B. JavaScript Obfuscation
1. **Use a JavaScript obfuscator**:
   - [JavaScript Obfuscator](https://obfuscator.io/)
   - [UglifyJS](https://github.com/mishoo/UglifyJS)
   - [Terser](https://terser.org/)

2. **Obfuscate all game files**:
   ```bash
   # Install terser
   npm install -g terser
   
   # Obfuscate game file
   terser earth-defender.php --compress --mangle --output earth-defender.min.js
   ```

3. **DevTools Protection** (add to game HTML):
   ```javascript
   // Disable right-click context menu
   document.addEventListener('contextmenu', e => e.preventDefault());
   
   // Disable F12, Ctrl+Shift+I, Ctrl+Shift+J, Ctrl+U
   document.addEventListener('keydown', function(e) {
       if (e.key === 'F12' || 
           (e.ctrlKey && e.shiftKey && ['I', 'J', 'C'].includes(e.key)) ||
           (e.ctrlKey && e.key === 'U')) {
           e.preventDefault();
           return false;
       }
   });
   
   // Detect DevTools (basic)
   let devtools = {open: false};
   setInterval(() => {
       if (window.outerHeight - window.innerHeight > 200 || 
           window.outerWidth - window.innerWidth > 200) {
           if (!devtools.open) {
               devtools.open = true;
               // Log suspicious activity
               fetch('security_log.php', {
                   method: 'POST',
                   body: JSON.stringify({action: 'devtools_detected'})
               });
           }
       }
   }, 1000);
   ```

### Step 5: Environment Variables
1. Copy `config.env.example` to `.env`
2. Update with your actual database credentials
3. Update `connection.php` to read from `.env`:
   ```php
   // Load environment variables
   if (file_exists('.env')) {
       $env = parse_ini_file('.env');
       $servername = $env['DB_HOST'] ?? 'localhost';
       $username = $env['DB_USER'] ?? 'root';
       $password = $env['DB_PASS'] ?? '';
       $db_name = $env['DB_NAME'] ?? 'astraden';
   }
   ```

### Step 6: Update Existing APIs

#### game_api.php
Replace the current implementation with secure version:
- Use `SecureAPI` class
- Add CSRF and nonce validation
- Implement server-side score validation
- Add rate limiting

#### bidding_api.php
- Add authentication checks
- Validate all inputs
- Use prepared statements (already done)
- Add rate limiting

## Security Best Practices

### Server-Side Score Validation
**NEVER trust client-submitted scores.** Always validate:
1. Score format (integer, non-negative)
2. Score limits (game-specific maximums)
3. Score progression (unrealistic jumps)
4. Session validity
5. Credits were actually deducted

### Rate Limiting Configuration
Adjust in `security_middleware.php`:
- `$rateLimitWindow`: Time window in seconds
- `$maxRequestsPerWindow`: Max requests per window
- `$maxLoginAttempts`: Max login attempts per hour

### IP Blocking
- Automatic blocking after 10 violations in 1 hour
- Manual blocking via admin panel (to be implemented)
- Block duration: 24 hours (configurable)

### Monitoring
Check `security_logs` table regularly:
```sql
SELECT * FROM security_logs 
WHERE status IN ('blocked', 'suspicious') 
ORDER BY created_at DESC 
LIMIT 100;
```

## Testing Security

### Test CSRF Protection
```javascript
// This should fail
fetch('game_api.php?action=save_score', {
    method: 'POST',
    body: JSON.stringify({score: 999999}) // No CSRF token
});
```

### Test Rate Limiting
```bash
# Make 101 requests quickly
for i in {1..101}; do
    curl http://yoursite.com/game_api.php?action=leaderboard
done
# Should block after 100 requests
```

### Test Score Validation
```javascript
// This should be rejected
saveScore(999999999); // Exceeds max score
```

## Maintenance

### Regular Cleanup
The security middleware automatically cleans up:
- Old rate limit records (1 hour)
- Expired nonces (1 hour)
- Old security logs (90 days)
- Expired IP blocks

### Manual IP Unblocking
```sql
DELETE FROM blocked_ips WHERE ip_address = 'X.X.X.X';
```

## Additional Recommendations

1. **Use HTTPS**: Always use SSL/TLS in production
2. **Regular Updates**: Keep PHP and dependencies updated
3. **Backup Security Logs**: Archive logs for analysis
4. **Monitor Alerts**: Set up alerts for suspicious activity
5. **Penetration Testing**: Regular security audits
6. **WAF**: Consider Web Application Firewall (Cloudflare, etc.)

## Support

For security issues or questions, review:
- `security_middleware.php` - Core security logic
- `secure_api_base.php` - API security base
- `security_logs` table - Security event history

