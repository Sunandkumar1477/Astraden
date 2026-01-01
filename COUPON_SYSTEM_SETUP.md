# Coupon Code System Setup Guide

## Overview
This system allows admins to create coupon codes that users can purchase with credits. Coupon codes are only visible to the purchaser after purchase.

## Database Setup

1. Run the SQL file to add new columns and create the purchase tracking table:
   ```sql
   -- Run: add_coupon_system.sql
   ```

2. The system adds these fields to the `rewards` table:
   - `showcase_date` - Date when coupon appears on rewards page
   - `display_days` - Number of days to display before expiring (e.g., if expire in 5 days, show only 3 days)
   - `about_coupon` - Description about the coupon

3. Creates `user_coupon_purchases` table to track who purchased which coupon code.

## Admin Features

### Creating a Coupon Code

1. Go to **Admin Panel > Rewards & Coupons**
2. Select **"Coupon"** as Gift Type
3. Fill in required fields:
   - **Gift Name**: Name of the coupon
   - **About Coupon**: Description about the coupon
   - **Coupon Code**: The actual code (e.g., SAVE50, WELCOME2024)
   - **Coupon Details**: Benefits and details
   - **Credits Cost**: Credits required to purchase
   - **Showcase Date & Time**: When the coupon appears on rewards page
   - **Display Days**: How many days to show (e.g., if expires in 5 days, enter 3 to show only 3 days)
   - **Expire Date**: When the coupon expires

### Editing/Deleting Coupons

- Admins can edit coupon details
- Admins can delete coupons
- All changes are logged

## User Features

### Viewing Coupons

- Coupons appear on the rewards page based on:
  - Showcase date has passed
  - Display days haven't expired
  - Not already sold out

### Purchasing Coupons

1. User clicks "PURCHASE NOW" button
2. Credits are deducted
3. Coupon code is revealed in an alert
4. Coupon code is saved to user's account
5. Coupon shows as "SOLD OUT" for other users

### Viewing Purchased Coupons

- Users can see their purchased coupon codes on the rewards page
- Code is displayed in a highlighted box
- Expire date and showcase date are shown

## Features

✅ Coupon codes visible only to purchaser
✅ Showcase date/time control
✅ Display days limit (show only X days before expiring)
✅ Expire date tracking
✅ Sold out status
✅ Admin can create, edit, delete
✅ Purchase tracking in database

## Example Scenario

1. Admin creates coupon:
   - Expires: Dec 31, 2024 (5 days from now)
   - Display Days: 3
   - Showcase Date: Dec 26, 2024
   - Result: Coupon shows from Dec 26-29, then disappears (even though it expires Dec 31)

2. User purchases coupon:
   - Pays credits
   - Receives coupon code in alert
   - Code is saved to their account
   - Can view code anytime on rewards page

3. After purchase:
   - Coupon shows "SOLD OUT" for all users
   - Original purchaser can still see their code

