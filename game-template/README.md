# Game Integration Template

This folder contains templates and instructions for adding a new game to the project.

## Folder Structure

```
game-template/
├── README.md (this file)
├── game-template.php (template game file)
├── add-game-to-database.sql (SQL to register game)
├── integration-guide.md (step-by-step integration)
└── example-game-name.php (rename this to your game name)
```

## Quick Start

1. **Copy your game code** into this folder and rename it to `your-game-name.php`
2. **Follow the template** in `game-template.php` to integrate with the system
3. **Run the SQL** in `add-game-to-database.sql` (update game name first)
4. **Update index.php** to add your game card
5. **Update shop.php** to add your game to the available games list
6. **Test** the game with credits, scores, and sessions

## Game Requirements

Your game must:
- ✅ Use credits to play (deducted via game_api.php)
- ✅ Save scores via game_api.php
- ✅ Support sessions (time-based or always available)
- ✅ Support contest mode (optional)
- ✅ Work on mobile and desktop
- ✅ Use the same database connection pattern

## Key Integration Points

1. **Credits System**: Game must deduct credits before starting
2. **Score Saving**: Game must save scores via API
3. **Sessions**: Game must check for active sessions
4. **Leaderboard**: Scores automatically appear in leaderboard
5. **Shop Integration**: Scores can be converted to credits in shop

## Need Help?

See `integration-guide.md` for detailed step-by-step instructions.

