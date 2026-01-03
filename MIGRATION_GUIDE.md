# Migration Guide: Adding Security to Existing Code

This guide helps you migrate your existing Astraden codebase to use the new security system.

## Step 1: Database Setup

```bash
# Connect to your MySQL database
mysql -u your_user -p astraden

# Run the security tables SQL
source create_security_tables.sql;
```

Or execute the SQL file directly:
```sql
SOURCE /path/to/create_security_tables.sql;
```

## Step 2: Update connection.php

The `connection.php` file has been updated to:
- Load credentials from `.env` file (if exists)
- Initialize security middleware automatically

**No changes needed** - it's already updated!

## Step 3: Create .env File

```bash
# Copy the example file
cp config.env.example .env

# Edit with your credentials
nano .env
```

Update these values:
```
DB_HOST=your_database_host
DB_USER=your_database_user
DB_PASS=your_database_password
DB_NAME=astraden
```

## Step 4: Update game_api.php

### Option A: Replace with Secure Version (Recommended)

1. Backup your current `game_api.php`:
   ```bash
   cp game_api.php game_api.php.backup
   ```

2. Use `game_api_secure.php` as a template and merge your existing logic

3. Or gradually migrate endpoints one by one

### Option B: Add Security to Existing File

Add at the top of `game_api.php`:
```php
<?php
session_start();
require_once 'secure_api_base.php';

// Initialize security (some endpoints are public)
$action = trim($_GET['action'] ?? '');
$public_actions = ['check_status', 'leaderboard', 'user_rank'];
$requires_auth = !in_array($action, $public_actions);

$api = new SecureAPI($requires_auth, true, true);
$conn = $api->getConnection();
$security = $api->getSecurity();
$user_id = $_SESSION['user_id'] ?? null;
```

Then update the `save_score` case:
```php
case 'save_score':
    // Get sanitized input
    $input = $api->sanitizeInput($_POST, [
        'score' => ['type' => 'int'],
        'session_id' => ['type' => 'int'],
        'game_name' => ['type' => 'string']
    ]);
    
    // Validate input
    $api->validateInput($input, [
        'score' => ['required' => true, 'type' => 'int', 'min' => 0, 'max' => 1000000],
        'game_name' => ['required' => true, 'type' => 'regex', 'pattern' => '/^[a-z0-9-]+$/']
    ]);
    
    // Server-side score validation
    $score_validation = $security->validateScore(
        $input['score'],
        $input['game_name'],
        $user_id,
        $input['session_id']
    );
    
    if (!$score_validation['valid']) {
        $api->respondError($score_validation['reason'], 400);
    }
    
    // Continue with your existing score saving logic...
    break;
```

## Step 5: Update Client-Side Code

### Add Security Script

In your game HTML files (e.g., `earth-defender.php`), add before closing `</body>`:

```html
<script src="client_security.js"></script>
```

### Update Score Submission

**Before:**
```javascript
const formData = new FormData();
formData.append('score', score);
formData.append('session_id', sessionId);
formData.append('game_name', 'earth-defender');

const response = await fetch('game_api.php?action=save_score', {
    method: 'POST',
    body: formData
});
```

**After:**
```javascript
const formData = new FormData();
formData.append('score', score);
formData.append('session_id', sessionId);
formData.append('game_name', 'earth-defender');
formData.append('credits_used', creditsUsed);

// Use secure request wrapper
const response = await window.astradenSecurity.secureRequest(
    'game_api.php?action=save_score',
    {
        method: 'POST',
        body: formData
    }
);
```

### Alternative: Manual Token Management

If you prefer manual control:
```javascript
// Get tokens first
const tokenResponse = await fetch('secure_api_base.php?get_tokens=1');
const tokenData = await tokenResponse.json();
const tokens = tokenData.tokens;

// Add to form data
formData.append('csrf_token', tokens.csrf_token);
formData.append('nonce', tokens.nonce);

// Make request
const response = await fetch('game_api.php?action=save_score', {
    method: 'POST',
    body: formData
});
```

## Step 6: Update bidding_api.php

Add security at the top:
```php
<?php
session_start();
require_once 'secure_api_base.php';

$action = $_GET['action'] ?? '';
$public_actions = ['get_active_biddings', 'get_bidding_details'];
$requires_auth = !in_array($action, $public_actions);

$api = new SecureAPI($requires_auth, true, true);
$conn = $api->getConnection();
$security = $api->getSecurity();
```

Update each action to use sanitized input:
```php
if ($action === 'place_bid') {
    $input = $api->sanitizeInput($_POST, [
        'bidding_id' => ['type' => 'int'],
        'bid_amount' => ['type' => 'float']
    ]);
    
    $api->validateInput($input, [
        'bidding_id' => ['required' => true, 'type' => 'int', 'min' => 1],
        'bid_amount' => ['required' => true, 'type' => 'float', 'min' => 0.01]
    ]);
    
    // Continue with existing logic using $input['bidding_id'] and $input['bid_amount']
}
```

## Step 7: Obfuscate JavaScript

### Install Terser
```bash
npm install -g terser
```

### Obfuscate Game Files
```bash
# For PHP files with embedded JS, extract JS first, then:
terser game.js --compress --mangle --output game.min.js

# Or use online tool: https://obfuscator.io/
```

### Add to HTML
```html
<!-- Replace original script -->
<script src="earth-defender.min.js"></script>
```

## Step 8: Test Security Features

### Test CSRF Protection
```javascript
// This should fail (no CSRF token)
fetch('game_api.php?action=save_score', {
    method: 'POST',
    body: JSON.stringify({score: 1000})
});
// Expected: 403 Forbidden
```

### Test Rate Limiting
```bash
# Make 101 rapid requests
for i in {1..101}; do
    curl http://yoursite.com/game_api.php?action=leaderboard
done
# Expected: Blocked after 100 requests
```

### Test Score Validation
```javascript
// This should be rejected (score too high)
window.astradenSecurity.secureRequest('game_api.php?action=save_score', {
    method: 'POST',
    body: new FormData().append('score', '999999999')
});
// Expected: "Score exceeds maximum allowed"
```

## Step 9: Monitor Security Logs

Check logs regularly:
```sql
-- Recent blocked attempts
SELECT * FROM security_logs 
WHERE status = 'blocked' 
ORDER BY created_at DESC 
LIMIT 20;

-- Suspicious scores
SELECT * FROM security_logs 
WHERE action LIKE '%score%' AND status = 'suspicious'
ORDER BY created_at DESC;
```

## Step 10: Configure Score Limits

Set maximum scores per game:
```sql
UPDATE games SET max_score_limit = 1000000 WHERE game_name = 'earth-defender';
UPDATE games SET max_score_limit = 500000 WHERE game_name = 'cosmos-captain';
UPDATE games SET max_score_limit = 2000000 WHERE game_name = 'galactic-gardener';
```

## Troubleshooting

### "CSRF token validation failed"
- Ensure `client_security.js` is loaded
- Check that tokens are being sent with requests
- Verify session is active

### "Rate limit exceeded"
- Normal behavior for excessive requests
- Adjust limits in `security_middleware.php` if needed
- Check if IP is blocked: `SELECT * FROM blocked_ips WHERE ip_address = 'X.X.X.X'`

### "Invalid or expired nonce"
- Nonces expire after 5 minutes
- Each request needs a fresh nonce
- Ensure `getNonce()` is called for each request

### Scores being rejected
- Check score limits in `games` table
- Verify session is valid
- Check security logs for reason

## Rollback Plan

If you need to rollback:

1. Restore `game_api.php` from backup
2. Remove `client_security.js` from HTML
3. Security middleware will still run but won't block if not used

The security system is designed to be non-breaking - existing code will continue to work, but won't have security features until migrated.

## Need Help?

1. Check `SECURITY_IMPLEMENTATION_GUIDE.md` for detailed docs
2. Review `SECURITY_SUMMARY.md` for overview
3. Check security logs in database
4. Review `security_middleware.php` for configuration options

