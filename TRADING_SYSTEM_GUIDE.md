# Trading System Guide - Fluxon ↔ Astrons

## Overview

The trading system allows users to exchange Fluxon (game scores) for Astrons (credits) at admin-set prices. All trades follow the exact prices set by the admin in the Shop Pricing page.

## How It Works

### Example Scenario:
- User has: **100 Fluxon** and **10 Astrons**
- Admin set price: **10 Fluxon = 1 Astron**
- User trades: **100 Fluxon** for **10 Astrons**

### Result:
- **Astrons**: 10 (existing) + 10 (from trade) = **20 Astrons total** ✓
- **Fluxon**: 100 - 100 = **0 Fluxon remaining** ✓
- **Purchase tracked**: Recorded in `game_leaderboard` with negative score ✓

## Trading Process

### Step 1: User Initiates Trade
- User clicks "Trade" button on an offer
- System checks if user has sufficient Fluxon
- System verifies prices match admin settings

### Step 2: Transaction Processing
1. **Add Astrons to existing balance**
   ```sql
   UPDATE user_profile SET credits = credits + ? WHERE user_id = ?
   ```
   - Adds new Astrons to existing balance
   - Example: 10 + 10 = 20 Astrons

2. **Deduct Fluxon from total_score**
   ```sql
   UPDATE users SET total_score = total_score + ? WHERE id = ?
   ```
   - Subtracts spent Fluxon (uses negative value)
   - Example: 100 - 100 = 0 Fluxon

3. **Track purchase in game_leaderboard**
   ```sql
   INSERT INTO game_leaderboard (user_id, game_name, score, ...) 
   VALUES (?, 'shop-purchase', -100, ...)
   ```
   - Records the purchase with negative score
   - Tracks how much Fluxon was spent

### Step 3: Update Balances
- Re-fetches updated Fluxon balance from `users.total_score`
- Re-fetches updated Astrons balance from `user_profile.credits`
- Updates UI with new balances

## Admin Price Settings

### Where to Set Prices:
**Admin Panel → Shop Pricing**

### Forward Trading (Fluxon → Astrons):
- **Fluxon Amount**: How much Fluxon user pays
- **Astrons Reward**: How many Astrons user gets

### Example Admin Settings:
| Type | Fluxon Cost | Astrons Reward | Rate |
|------|-------------|----------------|------|
| Basic | 100 | 10 | 10:1 |
| Standard | 500 | 50 | 10:1 |
| Premium | 1000 | 100 | 10:1 |

## User Experience

### What Users See:
1. **Current Balance Display**
   - Total Fluxon available
   - Current Astrons balance

2. **Available Trades**
   - Shows what they can purchase based on their balance
   - Displays: "You Pay X Fluxon → Get Y Astrons"
   - Shows balance after trade

3. **Trade Confirmation**
   - Confirmation dialog before trade
   - Success message with new balances
   - Real-time balance updates

### Example Trade Display:
```
You Pay: 100 Fluxon
You Get: +10 Astrons
Your Balance After: 0 Fluxon, 20 Astrons
Rate: 10 Fluxon = 1 Astron
```

## Database Tables Used

### 1. `shop_pricing`
Stores admin-set trading prices:
- `fluxon_amount`: Cost in Fluxon
- `astrons_reward`: Reward in Astrons
- `is_active`: Whether offer is active

### 2. `users`
Stores user's total Fluxon:
- `total_score`: Total Fluxon balance
- Updated when Fluxon is spent

### 3. `user_profile`
Stores user's Astrons:
- `credits`: Total Astrons balance
- Updated when Astrons are added

### 4. `game_leaderboard`
Tracks all purchases:
- Negative scores for Fluxon spent
- `game_name = 'shop-purchase'`
- Records timestamp and amount

## Security Features

1. **Price Verification**
   - All prices verified against database
   - Prevents price manipulation
   - Uses admin-set prices only

2. **Balance Validation**
   - Checks balance before transaction
   - Prevents overspending
   - Real-time balance checks

3. **Transaction Safety**
   - Uses database transactions
   - Atomic operations (all or nothing)
   - Rollback on errors

## Testing the System

### Test Case 1: Basic Trade
1. User has 100 Fluxon, 10 Astrons
2. Admin set: 100 Fluxon = 10 Astrons
3. User trades 100 Fluxon
4. **Expected Result:**
   - Astrons: 10 + 10 = 20 ✓
   - Fluxon: 100 - 100 = 0 ✓

### Test Case 2: Multiple Trades
1. User has 500 Fluxon, 0 Astrons
2. Admin set: 100 Fluxon = 10 Astrons
3. User trades 3 times (300 Fluxon total)
4. **Expected Result:**
   - Astrons: 0 + 10 + 10 + 10 = 30 ✓
   - Fluxon: 500 - 300 = 200 ✓

### Test Case 3: Insufficient Balance
1. User has 50 Fluxon
2. Admin set: 100 Fluxon = 10 Astrons
3. User tries to trade
4. **Expected Result:**
   - Trade disabled/blocked ✓
   - Error message shown ✓

## Troubleshooting

### Issue: Astrons not adding correctly
**Solution:** Check `user_profile.credits` column exists and is being updated

### Issue: Fluxon not deducting
**Solution:** Verify `users.total_score` column exists and is being updated

### Issue: Prices not matching admin settings
**Solution:** Clear browser cache and refresh page

### Issue: Trade not completing
**Solution:** Check database transaction logs and error messages

## Summary

The trading system:
- ✅ Uses admin-set prices exclusively
- ✅ Adds Astrons to existing balance
- ✅ Deducts Fluxon from total_score
- ✅ Tracks all purchases in game_leaderboard
- ✅ Provides real-time balance updates
- ✅ Includes security and validation

All trades follow the exact prices set by the admin in the Shop Pricing page.

