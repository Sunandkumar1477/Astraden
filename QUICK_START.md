# Quick Start: Astraden Security Setup

Get your security system up and running in 5 minutes!

## 🚀 5-Minute Setup

### 1. Create Database Tables (1 minute)
```sql
-- Connect to MySQL
mysql -u your_user -p astraden

-- Run the SQL
source create_security_tables.sql;
```

### 2. Create .env File (1 minute)
```bash
# Copy example
cp config.env.example .env

# Edit with your credentials
nano .env
```

Update:
```
DB_HOST=your_host
DB_USER=your_user
DB_PASS=your_password
DB_NAME=astraden
```

### 3. Add Security to Game Files (2 minutes)

**In your game HTML file** (e.g., `earth-defender.php`), add before `</body>`:
```html
<script src="client_security.js"></script>
```

**Update score submission**:
```javascript
// OLD:
const response = await fetch('game_api.php?action=save_score', {
    method: 'POST',
    body: formData
});

// NEW:
const response = await window.astradenSecurity.secureRequest(
    'game_api.php?action=save_score',
    { method: 'POST', body: formData }
);
```

### 4. Update game_api.php (1 minute)

Add at the top (after `session_start()`):
```php
require_once 'secure_api_base.php';

$action = trim($_GET['action'] ?? '');
$public_actions = ['check_status', 'leaderboard', 'user_rank'];
$requires_auth = !in_array($action, $public_actions);

$api = new SecureAPI($requires_auth, true, true);
$conn = $api->getConnection();
$security = $api->getSecurity();
```

Update `save_score` case to add validation:
```php
case 'save_score':
    // Add server-side validation
    $score_validation = $security->validateScore(
        intval($_POST['score']),
        $_POST['game_name'],
        $_SESSION['user_id'],
        intval($_POST['session_id'])
    );
    
    if (!$score_validation['valid']) {
        $api->respondError($score_validation['reason'], 400);
    }
    
    // Continue with existing code...
    break;
```

## ✅ Done!

Your security system is now active. Test it:

```javascript
// This should work (with tokens)
await window.astradenSecurity.secureRequest('game_api.php?action=save_score', {...});

// This should fail (no tokens)
await fetch('game_api.php?action=save_score', {method: 'POST', body: formData});
```

## 📋 Next Steps (Optional)

1. **Set score limits per game**:
   ```sql
   UPDATE games SET max_score_limit = 1000000 WHERE game_name = 'earth-defender';
   ```

2. **Obfuscate JavaScript**:
   ```bash
   npm install -g terser
   terser game.js --compress --mangle --output game.min.js
   ```

3. **Monitor security logs**:
   ```sql
   SELECT * FROM security_logs WHERE status = 'blocked' ORDER BY created_at DESC LIMIT 10;
   ```

## 🆘 Troubleshooting

**"CSRF token validation failed"**
- Make sure `client_security.js` is loaded
- Check browser console for errors

**"Rate limit exceeded"**
- Normal for excessive requests
- Adjust in `security_middleware.php` if needed

**Scores being rejected**
- Check `max_score_limit` in `games` table
- Review `security_logs` for details

## 📚 Full Documentation

- `SECURITY_IMPLEMENTATION_GUIDE.md` - Complete guide
- `MIGRATION_GUIDE.md` - Detailed migration steps
- `SECURITY_SUMMARY.md` - Feature overview

---

**That's it! Your game is now secured against common attacks.**

