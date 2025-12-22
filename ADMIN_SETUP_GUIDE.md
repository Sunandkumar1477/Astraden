# Admin Panel Setup Guide - Space Games Hub

## 🔐 Advanced Security Admin System

This admin panel includes the most advanced security features to prevent unauthorized access.

## 🚀 Quick Setup

### Step 1: Create Admin Tables

**Copy and paste this SQL code into phpMyAdmin:**

Open `create_admin_table.sql` file and copy all SQL code, then:
1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Select your database (e.g., `hn`)
3. Click on the **SQL** tab
4. Paste the SQL code
5. Click **Go** to execute

### Step 2: Default Admin Credentials

**Default Login:**
- **Username:** `admin`
- **Password:** `Admin@123`

⚠️ **IMPORTANT:** Change the default password immediately after first login!

### Step 3: Access Admin Panel

Navigate to:
```
http://localhost/Games/admin_login.php
```

## 🔒 Security Features

### 1. **Multi-Layer Authentication**
- ✅ Username/Password authentication
- ✅ CAPTCHA verification (math challenge)
- ✅ IP address verification
- ✅ Session integrity checks
- ✅ Account lockout after failed attempts

### 2. **Session Security**
- ✅ IP address binding (prevents session hijacking)
- ✅ User agent verification
- ✅ 30-minute session timeout
- ✅ Automatic logout on security breach detection

### 3. **Account Protection**
- ✅ Account locks after 5 failed login attempts
- ✅ 30-minute lockout period
- ✅ IP whitelist support (optional)
- ✅ Failed attempt tracking

### 4. **Activity Logging**
- ✅ All admin actions logged
- ✅ Login/logout tracking
- ✅ Security breach detection
- ✅ IP address logging
- ✅ User agent tracking

### 5. **Access Control**
- ✅ Only verified admins can access
- ✅ Session validation on every page
- ✅ Automatic redirect if unauthorized
- ✅ Security headers (X-Frame-Options, etc.)

## 📋 Admin Features

### Dashboard (`admin_dashboard.php`)
- **Statistics Overview:**
  - Total users
  - Users registered today
  - Logins today
  - Failed login attempts
  - Registrations today

- **User Management:**
  - View all registered users
  - Search users by username/mobile
  - View user details
  - Track user activity

- **Activity Monitoring:**
  - Recent login activity
  - Failed login attempts
  - Admin activity log
  - Real-time tracking

### User Details (`admin_user_details.php`)
- Complete user information
- Full login history
- Session durations
- IP addresses
- Failure reasons

## 🗄️ Database Tables

### 1. **admin_users** Table
Stores admin accounts with security settings.

**Fields:**
- `id` - Auto-increment primary key
- `username` - Unique admin username
- `password` - Hashed password (bcrypt)
- `email` - Admin email
- `full_name` - Admin full name
- `ip_whitelist` - Allowed IP addresses (optional)
- `last_login` - Last login timestamp
- `last_login_ip` - Last login IP address
- `failed_login_attempts` - Failed attempt counter
- `account_locked_until` - Account lock expiration
- `is_active` - Account status

### 2. **admin_logs** Table
Stores all admin activities and security events.

**Fields:**
- `id` - Auto-increment primary key
- `admin_id` - Foreign key to admin_users
- `admin_username` - Admin username
- `action` - Action type (login, logout, view_user, etc.)
- `description` - Action description
- `ip_address` - IP address of action
- `user_agent` - Browser/device info
- `target_user_id` - Target user (if applicable)
- `created_at` - Timestamp

## 📁 Files Overview

### Core Files:
- `admin_login.php` - Admin login page
- `admin_check.php` - Access control middleware (include in all admin pages)
- `admin_dashboard.php` - Main admin dashboard
- `admin_user_details.php` - Individual user tracking
- `admin_logout.php` - Secure logout

### Setup Files:
- `create_admin_table.sql` - SQL table creation script

## 🔐 How Security Works

### Login Process:
1. User enters credentials
2. CAPTCHA verification
3. Database authentication
4. IP whitelist check (if enabled)
5. Session creation with IP binding
6. Activity logging

### Page Access:
1. `admin_check.php` verifies session
2. Checks IP address hasn't changed
3. Verifies user agent
4. Validates session timeout
5. Confirms admin account is active
6. Updates activity timestamp

### Security Breach Detection:
- IP address mismatch → Immediate logout
- User agent change → Immediate logout
- Session timeout → Automatic logout
- Failed attempts → Account lockout

## 🛡️ Advanced Security Tips

### 1. **Enable IP Whitelist**
Edit admin user in database:
```sql
UPDATE admin_users 
SET ip_whitelist = 'YOUR_IP_ADDRESS' 
WHERE username = 'admin';
```

### 2. **Change Default Password**
After first login, update password:
```sql
UPDATE admin_users 
SET password = '$2y$10$NEW_HASHED_PASSWORD' 
WHERE username = 'admin';
```

### 3. **Monitor Admin Logs**
Check `admin_logs` table regularly for suspicious activity.

### 4. **Session Security**
- Sessions expire after 30 minutes of inactivity
- Sessions are bound to IP address
- Multiple security headers prevent attacks

## 📊 Viewing Admin Logs

### All Admin Activities:
```sql
SELECT * FROM admin_logs ORDER BY created_at DESC;
```

### Security Breaches:
```sql
SELECT * FROM admin_logs WHERE action = 'security_breach';
```

### Failed Login Attempts:
```sql
SELECT * FROM admin_logs WHERE action = 'failed_login';
```

## ✅ Security Checklist

- [x] Password hashing (bcrypt)
- [x] SQL injection protection (prepared statements)
- [x] XSS protection (input sanitization)
- [x] CSRF protection (session validation)
- [x] Session hijacking prevention (IP binding)
- [x] Account lockout mechanism
- [x] Activity logging
- [x] Security headers
- [x] CAPTCHA verification
- [x] IP whitelist support

## 🎯 Summary

**The admin panel is protected with:**
- Multi-layer authentication
- Session security
- Activity tracking
- Breach detection
- Automatic protection mechanisms

**Nobody can access the admin panel except:**
- Verified admin users
- From authorized IP addresses (if whitelist enabled)
- With valid session
- Within timeout period

The system is fully secure and tracks every user activity! 🔒
