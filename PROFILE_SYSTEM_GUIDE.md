# User Profile System Guide - Space Games Hub

## 🌍 Profile System Overview

Users can create and manage their profiles with a unique planet icon button. The profile includes personal information, payment methods, and a credits system.

## 🚀 Quick Setup

### Step 1: Create Profile Table

**Copy and paste this SQL code into phpMyAdmin:**

Open `create_user_profile_table.sql` file and copy all SQL code, then:
1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Select your database (e.g., `hn`)
3. Click on the **SQL** tab
4. Paste the SQL code
5. Click **Go** to execute

### Step 2: Create Uploads Directory

Create the following directory structure:
```
Games/
  └── uploads/
      └── profiles/
```

Or run this command in your terminal:
```bash
mkdir -p uploads/profiles
```

## 🌍 Planet Icon Button

### Location
- **Top right corner** of the index page (when logged in)
- **Animated rotating planet icon** (🌍)
- **Hover effect** with glow animation
- **Links to profile view page**

### Features
- ✅ Only visible when user is logged in
- ✅ Smooth rotation animation
- ✅ Hover effects
- ✅ Mobile responsive

## 📋 Profile Features

### 1. **Profile Photo**
- Upload profile picture
- Supported formats: JPG, PNG, GIF, WEBP
- Max size: 5MB
- Auto-resize and optimization
- Stored in `uploads/profiles/` directory

### 2. **Personal Information**
- Full Name
- Bio/About Me section
- State selection (All Indian states)

### 3. **Payment Methods**
- Phone Pay number
- Google Pay number
- Secure storage in database

### 4. **Credits System**
- Credits display with custom color
- Color picker for credits display
- Default color: Cyan (#00ffff)
- Credits stored in database

## 🗄️ Database Table Structure

### **user_profile** Table

**Fields:**
- `id` - Auto-increment primary key
- `user_id` - Foreign key to users table (UNIQUE)
- `full_name` - User's full name
- `profile_photo` - Path to profile photo file
- `phone_pay_number` - Phone Pay payment number
- `google_pay_number` - Google Pay payment number
- `state` - Selected state
- `credits` - User credits (default: 0)
- `credits_color` - Hex color for credits display (default: #00ffff)
- `bio` - User biography/description
- `created_at` - Profile creation timestamp
- `updated_at` - Last update timestamp

## 📁 Files Overview

### Core Files:
- `profile.php` - Profile creation/edit page
- `view_profile.php` - Profile display page
- `create_user_profile_table.sql` - SQL table creation script

### Updated Files:
- `index.php` - Added planet icon button

## 🎨 Profile Pages

### 1. **Profile Edit Page** (`profile.php`)
- Create new profile
- Edit existing profile
- Upload profile photo
- Set payment methods
- Select state
- Choose credits color
- Write bio

### 2. **Profile View Page** (`view_profile.php`)
- Display complete profile
- Show profile photo
- Display credits with custom color
- Show all information
- Link to edit profile

## 🔄 User Flow

1. **User logs in** → Planet icon appears
2. **Click planet icon** → View profile page
3. **Click "Edit Profile"** → Edit profile page
4. **Fill in information** → Save profile
5. **Profile saved** → Redirect to view page

## 📱 Mobile Support

- ✅ Responsive design
- ✅ Touch-friendly buttons
- ✅ Mobile-optimized forms
- ✅ Adaptive layouts

## 🎯 Features Summary

### Profile Creation:
- [x] Full name
- [x] Profile photo upload
- [x] Phone Pay number
- [x] Google Pay number
- [x] State selection
- [x] Credits color picker
- [x] Bio/About me

### Profile Display:
- [x] Profile photo
- [x] Full name
- [x] Credits with custom color
- [x] All payment methods
- [x] State information
- [x] Bio display
- [x] Account information

### UI Features:
- [x] Planet icon button (animated)
- [x] Space-themed design
- [x] Color-coded credits
- [x] Modern interface
- [x] Mobile responsive

## 🚀 How to Use

### For Users:

1. **Login** to your account
2. **Click the planet icon** (🌍) in top right corner
3. **View your profile** or click "Edit Profile"
4. **Fill in your information:**
   - Upload profile photo
   - Enter full name
   - Add payment numbers
   - Select your state
   - Choose credits color
   - Write a bio
5. **Click "Save Profile"**
6. **View your profile** with all information

### For Developers:

1. Run `create_user_profile_table.sql` in phpMyAdmin
2. Create `uploads/profiles/` directory
3. Set proper permissions (777 for uploads folder)
4. Users can now create profiles!

## 📊 Credits System

- Credits are stored in `user_profile` table
- Default: 0 credits
- Color can be customized per user
- Displayed prominently on profile page
- Can be updated programmatically

## 🔒 Security Features

- ✅ File upload validation
- ✅ Image type checking
- ✅ File size limits
- ✅ Secure file storage
- ✅ User authentication required
- ✅ SQL injection protection

## 🎨 Customization

### Change Credits Default Color:
```sql
UPDATE user_profile SET credits_color = '#YOUR_COLOR' WHERE user_id = ?;
```

### Change Planet Icon:
Edit `index.php` and change the emoji in the planet button.

## ✅ Summary

**Complete profile system with:**
- Planet icon button (🌍)
- Profile photo upload
- Payment methods
- State selection
- Credits with custom colors
- Bio section
- Beautiful space-themed UI
- Mobile responsive design

Users can now create unique profiles with all their information! 🌍✨

