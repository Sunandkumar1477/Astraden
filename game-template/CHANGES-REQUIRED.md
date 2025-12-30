# Required Changes Checklist

When adding your game, you MUST change these values in ALL files:

## 1. Game Name (Identifier)
**Format**: lowercase, use hyphens for spaces (e.g., `space-shooter`, `puzzle-game`)

Files to update:
- [ ] `your-game-name.php` - Line with `$game_name = 'your-game-name';`
- [ ] `your-game-name.php` - JavaScript `const GAME_NAME = 'your-game-name';`
- [ ] `add-game-to-database.sql` - All instances of `'your-game-name'`
- [ ] `index.php` - Game card `data-game="your-game-name"`
- [ ] `index.php` - `launchGame('your-game-name')`
- [ ] `index.php` - `gameFiles` object key
- [ ] `index.php` - `gameCategories` object key
- [ ] `shop.php` - `$available_games` array key

## 2. Game Display Name
**Format**: User-friendly name (e.g., `Space Shooter`, `Puzzle Game`)

Files to update:
- [ ] `add-game-to-database.sql` - `display_name` value
- [ ] `index.php` - Game card title `<h3>Your Game Name</h3>`
- [ ] `index.php` - `gameCategories` name value
- [ ] `shop.php` - `$available_games` array value

## 3. Game Icon/Emoji
**Format**: Single emoji or icon

Files to update:
- [ ] `index.php` - Game card icon `<div class="game-icon">🎮</div>`
- [ ] `index.php` - `gameCategories` icon value
- [ ] `shop.php` - `$available_games` emoji prefix

## 4. Game Type/Category
**Format**: `defense`, `action`, `strategy`, or `arcade`

Files to update:
- [ ] `index.php` - Game card `data-type="defense"`
- [ ] `index.php` - `gameCategories` type value

## 5. Credits Per Play
**Format**: Integer (default: 30)

Files to update:
- [ ] `add-game-to-database.sql` - `credits_per_chance` value

## 6. Page Title
**Format**: Browser tab title

Files to update:
- [ ] `your-game-name.php` - `<title>Your Game Name</title>`

## Example: Adding "Space Shooter" Game

### Game Name (Identifier): `space-shooter`
### Display Name: `Space Shooter`
### Icon: `🚀`
### Type: `action`
### Credits: `30`

### Changes in `space-shooter.php`:
```php
$game_name = 'space-shooter';
const GAME_NAME = 'space-shooter';
<title>Space Shooter</title>
```

### Changes in `add-game-to-database.sql`:
```sql
'space-shooter', 'Space Shooter', 30, ...
```

### Changes in `index.php`:
```html
<div class="game-card" data-game="space-shooter" data-type="action">
    <div class="game-icon">🚀</div>
    <h3 class="game-title">Space Shooter</h3>
    ...
</div>
```

```javascript
const gameFiles = {
    'earth-defender': 'earth-defender.php',
    'space-shooter': 'space-shooter.php'
};

const gameCategories = {
    'space-shooter': {
        name: 'Space Shooter',
        type: 'action',
        icon: '🚀'
    }
};
```

### Changes in `shop.php`:
```php
$available_games = [
    'earth-defender' => '🛡️ Earth Defender',
    'space-shooter' => '🚀 Space Shooter'
];
```

## Quick Copy-Paste Template

Replace these placeholders:
- `your-game-name` → Your game identifier
- `Your Game Name` → Your game display name
- `🎮` → Your game icon
- `action` → Your game type
- `30` → Credits per play

