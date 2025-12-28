# Total Score Migration Guide

## Overview

The project has been updated to store the total score (Fluxon) directly in the `users` table instead of calculating `SUM(score)` from `game_leaderboard` every time. This improves performance and reduces database load.

## Changes Made

### 1. Database Schema Changes

**File:** `add_total_score_to_users.sql`

- Added `total_score` column to `users` table
- Column type: `BIGINT(20) NOT NULL DEFAULT 0`
- Added index on `total_score` for faster queries
- Initialized `total_score` for existing users based on `game_leaderboard`

### 2. Code Updates

#### `game_api.php`
- **Updated:** Score saving now updates `users.total_score` when a new score is saved
- **Change:** Added `UPDATE users SET total_score = total_score + ? WHERE id = ?` after inserting into `game_leaderboard`
- **Benefit:** Total score is maintained in real-time

#### `shop.php`
- **Updated:** Fluxon balance now reads from `users.total_score` instead of calculating `SUM(score)`
- **Updated:** Shop purchases now update `users.total_score` when Fluxon is deducted
- **Change:** Replaced `SELECT SUM(score) FROM game_leaderboard` with `SELECT total_score FROM users`
- **Benefit:** Faster page loads and reduced database queries

### 3. Migration Script

**File:** `migrate_total_score.php`

- Automatically adds `total_score` column if it doesn't exist
- Initializes `total_score` for all existing users
- Verifies data integrity
- Can be run multiple times safely

## How to Apply Changes

### Step 1: Run SQL Migration

**Option A: Using SQL File**
1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Select your database
3. Click on the "SQL" tab
4. Copy and paste the contents of `add_total_score_to_users.sql`
5. Click "Go" to execute

**Option B: Using PHP Migration Script**
1. Open in browser: `http://localhost/sboom/migrate_total_score.php`
2. The script will automatically:
   - Add the column if needed
   - Initialize data for all users
   - Verify the migration

### Step 2: Verify Changes

After running the migration, verify:
- Column exists: `SHOW COLUMNS FROM users LIKE 'total_score'`
- Data is initialized: `SELECT COUNT(*) FROM users WHERE total_score > 0`
- Data matches: Compare `SUM(total_score)` from users with `SUM(score)` from game_leaderboard

## How It Works

### Before (Old Method)
```php
// Every time we need total score:
$stmt = $conn->prepare("SELECT SUM(score) as total FROM game_leaderboard WHERE user_id = ?");
// This scans all rows in game_leaderboard for the user
```

### After (New Method)
```php
// Now we just read from users table:
$stmt = $conn->prepare("SELECT total_score FROM users WHERE id = ?");
// Much faster - single row lookup with index
```

### Score Updates

**When a score is saved:**
1. Insert into `game_leaderboard` (as before)
2. **NEW:** Update `users.total_score = total_score + new_score`

**When Fluxon is spent in shop:**
1. Insert negative score into `game_leaderboard` (as before)
2. **NEW:** Update `users.total_score = total_score - cost`

## Benefits

1. **Performance:** No more `SUM()` calculations on every page load
2. **Scalability:** Performance doesn't degrade as `game_leaderboard` grows
3. **Consistency:** Total score is always up-to-date
4. **Simplicity:** Single source of truth in `users` table

## Important Notes

- **Leaderboards:** Still use `SUM(score)` from `game_leaderboard` because they need filtering by:
  - Game name
  - Game mode (money/credits)
  - Credits used
  - Date ranges
  
- **Data Integrity:** The `total_score` is maintained automatically when scores are added/removed
- **Backward Compatibility:** Old code that uses `SUM(score)` will still work, but is slower

## Troubleshooting

### If total_score doesn't match SUM(score)

Run the migration script again:
```bash
http://localhost/sboom/migrate_total_score.php
```

Or manually sync:
```sql
UPDATE users u
SET total_score = COALESCE((
    SELECT SUM(score) 
    FROM game_leaderboard gl 
    WHERE gl.user_id = u.id
), 0);
```

### If column doesn't exist

Run the SQL migration:
```sql
ALTER TABLE users 
ADD COLUMN total_score BIGINT(20) NOT NULL DEFAULT 0 
COMMENT 'Total score (Fluxon)';
```

## Files Modified

1. `add_total_score_to_users.sql` - SQL migration
2. `migrate_total_score.php` - PHP migration script
3. `game_api.php` - Updates total_score when scores are saved
4. `shop.php` - Uses and updates total_score for Fluxon balance

## Testing

After migration, test:
1. Play a game and verify score increases
2. Purchase from shop and verify Fluxon decreases
3. Check that `users.total_score` matches `SUM(score)` from `game_leaderboard`

