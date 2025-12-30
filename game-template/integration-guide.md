# Game Integration Guide

Complete step-by-step guide to add your game to the project.

## Step 1: Prepare Your Game File

1. Copy your game code into the `game-template` folder
2. Rename it to `your-game-name.php` (use lowercase, hyphens for spaces)
3. Open `game-template.php` and copy the integration code into your game file

## Step 2: Update Your Game File

In your game PHP file, make these changes:

### A. Update Game Name
```php
// At the top of your file, change:
$game_name = 'your-game-name'; // Change to your actual game name
```

### B. Add Required PHP Code
Copy the PHP code from `game-template.php` (lines 1-46) to the top of your game file:
- Session handling
- Database connection
- Game settings fetch
- Credits retrieval

### C. Add Required HTML Structure
Copy the UI layer HTML from `game-template.php`:
- Credits display
- Contest timer (if needed)
- Game over modal

### D. Add Required JavaScript
Copy the JavaScript functions from `game-template.php`:
- `initGame()` - Initialize game and check status
- `startGame()` - Deduct credits and start
- `saveScore(score)` - Save score when game ends
- `updateCreditsDisplay()` - Update credits UI

### E. Integrate with Your Game Logic

When your game starts:
```javascript
// Call startGame() when user clicks play
startGame();

// In your game loop, when game ends:
saveScore(yourFinalScore);
```

## Step 3: Add Game to Database

1. Open `add-game-to-database.sql`
2. Replace all instances of `'your-game-name'` with your actual game name
3. Replace `'Your Game Display Name'` with the name users will see
4. Adjust `credits_per_chance` (default: 30)
5. Run the SQL file in your database

## Step 4: Add Game to index.php

Find the games section in `index.php` and add your game card:

```html
<div class="game-card" data-game="your-game-name" data-type="defense" onclick="launchGame('your-game-name'); return false;" style="cursor: pointer;">
    <div class="game-icon">🎮</div> <!-- Change icon -->
    <div class="game-countdown-badge" id="countdown-badge-your-game-name" style="display: none;">
        <!-- Countdown badge code -->
    </div>
    <h3 class="game-title">Your Game Name</h3>
    <p class="game-description">Your game description</p>
    <span class="credits-badge" id="credits-badge-your-game-name">
        <i class="fas fa-bolt"></i>
        <span id="credits-amount-your-game-name">30</span> Credits
    </span>
    <!-- Add other badges (prizes, timing) if needed -->
    <button class="play-btn" onclick="launchGame('your-game-name')">Play Now</button>
</div>
```

Also update the `gameFiles` object in JavaScript:
```javascript
const gameFiles = {
    'earth-defender': 'earth-defender.php',
    'your-game-name': 'your-game-name.php' // Add this line
};
```

And update the `gameCategories` object:
```javascript
const gameCategories = {
    'earth-defender': {
        name: 'Earth Defender',
        type: 'defense',
        icon: '🛡️'
    },
    'your-game-name': { // Add this
        name: 'Your Game Name',
        type: 'action', // or 'defense', 'strategy', 'arcade'
        icon: '🎮'
    }
};
```

## Step 5: Add Game to shop.php

In `shop.php`, find the `$available_games` array and add your game:

```php
$available_games = [
    'earth-defender' => '🛡️ Earth Defender',
    'your-game-name' => '🎮 Your Game Name' // Add this line
];
```

## Step 6: Test Your Game

1. **Test Credits Deduction**: Play game and verify credits are deducted
2. **Test Score Saving**: Play game and verify score is saved
3. **Test Leaderboard**: Check if score appears in leaderboard
4. **Test Shop**: Check if scores can be converted to credits
5. **Test Sessions**: Verify game works with time-based sessions
6. **Test Mobile**: Verify game works on mobile devices

## Step 7: Optional Features

### Contest Mode
If you want contest mode:
1. In admin panel, go to Contest Management
2. Enable contest for your game
3. Set prize amounts
4. Contest scores will be tracked separately

### Shop Integration
If you want scores convertible to credits:
1. The SQL already includes shop settings
2. Adjust `score_per_credit` in the SQL (default: 100)
3. Users can convert scores to credits in shop.php

### Game Sessions
If you want time-based sessions:
1. In admin panel, go to Game Timing
2. Create sessions for your game
3. Set date, time, and duration
4. Game will only be available during those times

## Common Issues

### Game not appearing in index.php
- Check game name matches in all files
- Check database entry exists
- Check `is_active = 1` in games table

### Credits not deducting
- Check `game_api.php` is accessible
- Check game name matches exactly
- Check user has enough credits

### Scores not saving
- Check `saveScore()` is called with correct score
- Check `game_api.php` is accessible
- Check database connection

### Game not working on mobile
- Check viewport meta tag
- Check touch controls
- Check responsive CSS

## File Checklist

- [ ] Game PHP file created and integrated
- [ ] Database entry added (SQL executed)
- [ ] Added to index.php (game card)
- [ ] Added to shop.php (available games)
- [ ] Game name consistent across all files
- [ ] Credits deduction working
- [ ] Score saving working
- [ ] Leaderboard showing scores
- [ ] Mobile responsive
- [ ] Tested on desktop and mobile

## Need Help?

Refer to `earth-defender.php` as a complete working example.

