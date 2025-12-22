# Database Setup Guide - Space Games Hub

## 📋 Overview

This system automatically saves all user registration, login, and logout data to SQL tables. All user activities are logged for security and tracking purposes.

## 📋 Quick Copy SQL Code for phpMyAdmin

### Copy This SQL Code to Create Tables in phpMyAdmin:

#### 1. Create `users` Table:
```sql
CREATE TABLE IF NOT EXISTS users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    mobile_number VARCHAR(15) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_mobile (mobile_number),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 2. Create `login_logs` Table:
```sql
CREATE TABLE IF NOT EXISTS login_logs (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL DEFAULT 0,
    username VARCHAR(50) NOT NULL,
    mobile_number VARCHAR(15) NOT NULL,
    action VARCHAR(20) NOT NULL COMMENT 'login, logout, failed_login, register',
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    logout_time TIMESTAMP NULL,
    session_duration INT(11) NULL COMMENT 'Duration in seconds',
    status VARCHAR(20) DEFAULT 'success' COMMENT 'success, failed',
    failure_reason VARCHAR(255) NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_username (username),
    INDEX idx_action (action),
    INDEX idx_login_time (login_time),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 3. Add Foreign Key (Optional - Run after both tables are created):
```sql
ALTER TABLE login_logs 
ADD CONSTRAINT fk_login_logs_user_id 
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
```

**How to Use:**
1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Select your database (e.g., `hn`)
3. Click on the **SQL** tab
4. Copy and paste the first SQL code above
5. Click **Go** to execute
6. Repeat for the second SQL code
7. Optionally run the foreign key SQL (step 3)

## 🗄️ Database Tables

### 1. **users** Table
Stores all registered user accounts.

**Fields:**
- `id` - Auto-increment primary key
- `username` - Unique username (3-50 characters)
- `mobile_number` - Unique mobile number (10-15 digits)
- `password` - Hashed password (bcrypt)
- `created_at` - Registration timestamp
- `last_login` - Last login timestamp
- `updated_at` - Last update timestamp

### 2. **login_logs** Table
Stores all login/logout activities and security events.

**Fields:**
- `id` - Auto-increment primary key
- `user_id` - Foreign key to users table
- `username` - Username at time of action
- `mobile_number` - Mobile number at time of action
- `action` - Type: `login`, `logout`, `failed_login`, `register`
- `ip_address` - Client IP address
- `user_agent` - Browser/device information
- `login_time` - Timestamp of action
- `logout_time` - Logout timestamp (if applicable)
- `session_duration` - Session duration in seconds
- `status` - `success` or `failed`
- `failure_reason` - Reason for failure (if applicable)

## 🚀 Setup Instructions

### Step 1: Create Database Tables

#### Option A: Using phpMyAdmin (Manual Method)

**Copy and paste the following SQL code into phpMyAdmin:**

##### SQL Code for `users` Table:
```sql
CREATE TABLE IF NOT EXISTS users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    mobile_number VARCHAR(15) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_mobile (mobile_number),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

##### SQL Code for `login_logs` Table:
```sql
CREATE TABLE IF NOT EXISTS login_logs (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL DEFAULT 0,
    username VARCHAR(50) NOT NULL,
    mobile_number VARCHAR(15) NOT NULL,
    action VARCHAR(20) NOT NULL COMMENT 'login, logout, failed_login, register',
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    logout_time TIMESTAMP NULL,
    session_duration INT(11) NULL COMMENT 'Duration in seconds',
    status VARCHAR(20) DEFAULT 'success' COMMENT 'success, failed',
    failure_reason VARCHAR(255) NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_username (username),
    INDEX idx_action (action),
    INDEX idx_login_time (login_time),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

##### SQL Code for Foreign Key (Optional - Run after both tables are created):
```sql
ALTER TABLE login_logs 
ADD CONSTRAINT fk_login_logs_user_id 
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
```

**Instructions:**
1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Select your database (e.g., `hn`)
3. Click on the "SQL" tab
4. Copy and paste the first SQL code (users table)
5. Click "Go" to execute
6. Repeat for the second SQL code (login_logs table)
7. Optionally run the foreign key constraint SQL

#### Option B: Using Setup Script (Automatic Method)

Open your browser and navigate to:
```
http://localhost/Games/setup_database.php
```

This will:
- ✓ Create the `users` table
- ✓ Create the `login_logs` table
- ✓ Add foreign key constraints
- ✓ Verify table structures
- ✓ Show existing data

### Step 2: Verify Setup

Check that everything is working:
```
http://localhost/Games/verify_data.php
```

This page shows:
- All registered users
- All login/logout activity
- Statistics and analytics

## 📝 How Data is Saved

### Registration Process (`register.php`)

When a user registers:
1. ✅ **User data saved to `users` table:**
   - Username
   - Mobile number
   - Hashed password
   - Created timestamp

2. ✅ **Registration logged to `login_logs` table:**
   - User ID
   - Username
   - Mobile number
   - Action: `register`
   - IP address
   - User agent
   - Status: `success`

3. ✅ **Auto-login logged to `login_logs` table:**
   - User ID
   - Action: `login`
   - IP address
   - User agent
   - Status: `success`

### Login Process (`login.php`)

When a user logs in:

**Successful Login:**
1. ✅ **User's `last_login` updated in `users` table**
2. ✅ **Login logged to `login_logs` table:**
   - User ID
   - Username
   - Mobile number
   - Action: `login`
   - IP address
   - User agent
   - Status: `success`
   - Login timestamp

**Failed Login:**
1. ✅ **Failed attempt logged to `login_logs` table:**
   - Username (or user_id if user exists)
   - Action: `failed_login`
   - IP address
   - User agent
   - Status: `failed`
   - Failure reason: "User not found" or "Invalid password"

### Logout Process (`logout.php`)

When a user logs out:
1. ✅ **Logout logged to `login_logs` table:**
   - User ID
   - Username
   - Mobile number
   - Action: `logout`
   - IP address
   - User agent
   - Status: `success`
   - Logout timestamp
   - Session duration (calculated)

2. ✅ **Original login record updated:**
   - Logout timestamp added
   - Session duration calculated and saved

## 🔒 Security Features

- ✅ **Password Hashing:** All passwords are hashed using bcrypt
- ✅ **SQL Injection Protection:** All queries use prepared statements
- ✅ **Input Validation:** All inputs are validated and sanitized
- ✅ **IP Tracking:** All activities logged with IP addresses
- ✅ **Failed Login Tracking:** Failed attempts are logged for security
- ✅ **Session Management:** Secure session handling

## 📊 Data Verification

### View All Users
```sql
SELECT * FROM users ORDER BY created_at DESC;
```

### View All Login Activity
```sql
SELECT * FROM login_logs ORDER BY login_time DESC;
```

### View Failed Login Attempts
```sql
SELECT * FROM login_logs WHERE status = 'failed' ORDER BY login_time DESC;
```

### View User's Login History
```sql
SELECT * FROM login_logs WHERE user_id = 1 ORDER BY login_time DESC;
```

### View Statistics
```sql
SELECT 
    (SELECT COUNT(*) FROM users) as total_users,
    (SELECT COUNT(*) FROM login_logs WHERE action = 'login' AND status = 'success') as successful_logins,
    (SELECT COUNT(*) FROM login_logs WHERE action = 'failed_login') as failed_logins;
```

## 🧪 Testing

1. **Test Registration:**
   - Go to `http://localhost/Games/`
   - Click "Register"
   - Fill in the form
   - Check `verify_data.php` to see the new user

2. **Test Login:**
   - Click "Login"
   - Enter credentials
   - Check `verify_data.php` to see login log

3. **Test Logout:**
   - Click "Logout"
   - Check `verify_data.php` to see logout log with session duration

## 📁 Files Overview

- `setup_database.php` - Creates all database tables
- `verify_data.php` - View all saved data
- `register.php` - Handles user registration
- `login.php` - Handles user login
- `logout.php` - Handles user logout
- `check_session.php` - Checks if user is logged in
- `connection.php` - Database connection file

## ✅ Data Saved Checklist

Every user action saves data:

- [x] Username
- [x] Mobile number
- [x] Password (hashed)
- [x] Registration timestamp
- [x] Last login timestamp
- [x] Login attempts (successful and failed)
- [x] Logout events
- [x] IP addresses
- [x] User agent (browser/device)
- [x] Session durations
- [x] Failure reasons (for failed attempts)

## 🎯 Summary

**All user data is automatically saved to SQL tables:**
- User accounts → `users` table
- All activities → `login_logs` table
- Security events → `login_logs` table
- Session data → Calculated and stored

The system is fully automated - no manual data entry required!
