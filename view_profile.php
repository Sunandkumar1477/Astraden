<?php
require_once 'check_user_session.php';
// Connection is already established in check_user_session.php
$conn = $GLOBALS['conn'];

$user_id = $_SESSION['user_id'];

// Get user profile
$profile_stmt = $conn->prepare("SELECT * FROM user_profile WHERE user_id = ?");
$profile_stmt->bind_param("i", $user_id);
$profile_stmt->execute();
$profile_result = $profile_stmt->get_result();
$profile = $profile_result->fetch_assoc();
$profile_stmt->close();

// Get user info
$user_stmt = $conn->prepare("SELECT username, mobile_number, created_at FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();
$user_stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Space Games Hub</title>
    <link rel="icon" type="image/svg+xml" href="logo.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Rajdhani', sans-serif;
            background: #0a0a0f;
            color: #00ffff;
            min-height: 100vh;
            padding: 20px;
            background-image: 
                radial-gradient(circle at 20% 50%, rgba(157, 78, 221, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(0, 255, 255, 0.1) 0%, transparent 50%);
        }
        .header {
            max-width: 1000px;
            margin: 0 auto 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: rgba(15, 15, 25, 0.8);
            border: 2px solid #00ffff;
            border-radius: 10px;
        }
        .header h1 {
            font-family: 'Orbitron', sans-serif;
            font-size: 2rem;
            text-transform: uppercase;
            letter-spacing: 3px;
        }
        .header-buttons {
            display: flex;
            gap: 10px;
        }
        .btn {
            background: rgba(0, 255, 255, 0.2);
            border: 2px solid #00ffff;
            color: #00ffff;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn:hover {
            background: rgba(0, 255, 255, 0.4);
        }
        .btn-primary {
            background: linear-gradient(135deg, #00ffff, #9d4edd);
            color: white;
            border: none;
        }
        .btn-primary:hover {
            box-shadow: 0 5px 20px rgba(0, 255, 255, 0.5);
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .profile-display {
            background: rgba(15, 15, 25, 0.8);
            border: 2px solid #00ffff;
            border-radius: 15px;
            padding: 40px;
            text-align: center;
        }
        .profile-icon-container {
            margin-bottom: 30px;
        }
        .profile-icon-display {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            border: 4px solid #00ffff;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 6rem;
            box-shadow: 0 0 30px rgba(0, 255, 255, 0.5);
            position: relative;
            overflow: hidden;
        }
        /* Profile Icons */
        .icon-boy1 { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .icon-girl1 { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .icon-beard { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .icon-bald { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        .icon-fashion { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); }
        .icon-specs { background: linear-gradient(135deg, #30cfd0 0%, #330867 100%); }
        .profile-name {
            font-family: 'Orbitron', sans-serif;
            font-size: 2.5rem;
            font-weight: 900;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 3px;
        }
        .credits-display {
            margin: 30px 0;
            padding: 25px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 15px;
            border: 3px solid;
            display: inline-block;
            min-width: 250px;
        }
        .credits-label {
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 3px;
            margin-bottom: 15px;
            color: rgba(0, 255, 255, 0.7);
        }
        .credits-value {
            font-family: 'Orbitron', sans-serif;
            font-size: 3.5rem;
            font-weight: 900;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 40px;
        }
        .info-card {
            background: rgba(0, 0, 0, 0.3);
            border: 2px solid #00ffff;
            border-radius: 10px;
            padding: 20px;
            text-align: left;
        }
        .info-card h3 {
            font-family: 'Orbitron', sans-serif;
            color: #9d4edd;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 10px;
            border-bottom: 1px solid rgba(0, 255, 255, 0.2);
            padding-bottom: 8px;
        }
        .info-card p {
            color: #00ffff;
            font-size: 1.1rem;
            font-weight: 600;
        }
        .bio-section {
            margin-top: 30px;
            padding: 20px;
            background: rgba(0, 0, 0, 0.3);
            border: 2px solid #00ffff;
            border-radius: 10px;
            text-align: left;
        }
        .bio-section h3 {
            font-family: 'Orbitron', sans-serif;
            color: #9d4edd;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 15px;
        }
        .bio-section p {
            color: rgba(0, 255, 255, 0.8);
            line-height: 1.6;
            font-size: 1rem;
        }
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
            }
            .header-buttons {
                width: 100%;
                flex-direction: column;
            }
            .btn {
                width: 100%;
                text-align: center;
            }
            .profile-icon-display {
                width: 150px;
                height: 150px;
                font-size: 4rem;
            }
            .profile-name {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🌍 My Profile</h1>
        <div class="header-buttons">
            <a href="profile.php" class="btn btn-primary">Edit Profile</a>
            <a href="index.php" class="btn">← Back to Games</a>
        </div>
    </div>

    <div class="container">
        <div class="profile-display">
            <div class="profile-icon-container">
                <?php 
                $selected_icon = $profile['profile_photo'] ?? '';
                $icon_classes = [
                    'boy1' => '👨',
                    'girl1' => '👩',
                    'beard' => '🧔',
                    'bald' => '👨‍🦲',
                    'fashion' => '👸',
                    'specs' => '👨‍💼'
                ];
                $icon_class = $selected_icon ? 'icon-' . $selected_icon : '';
                $icon_emoji = $icon_classes[$selected_icon] ?? '🌍';
                ?>
                <div class="profile-icon-display <?php echo htmlspecialchars($icon_class); ?>">
                    <?php echo $icon_emoji; ?>
                </div>
            </div>
            
            <div class="profile-name">
                <?php echo htmlspecialchars($profile['full_name'] ?? $_SESSION['username'] ?? 'User'); ?>
            </div>
            
            <div class="credits-display" style="border-color: #FFD700;">
                <div class="credits-label">Credits</div>
                <div class="credits-value" style="color: #FFD700;">
                    <?php echo number_format($profile['credits'] ?? 0); ?>
                </div>
            </div>
            
            <div class="info-grid">
                <div class="info-card">
                    <h3>Username</h3>
                    <p><?php echo htmlspecialchars($user['username']); ?></p>
                </div>
                
                <div class="info-card">
                    <h3>Mobile Number</h3>
                    <p><?php echo htmlspecialchars($user['mobile_number']); ?></p>
                </div>
                
                <?php if ($profile && $profile['state']): ?>
                <div class="info-card">
                    <h3>State</h3>
                    <p><?php echo htmlspecialchars($profile['state']); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if ($profile && $profile['phone_pay_number']): ?>
                <div class="info-card">
                    <h3>Phone Pay</h3>
                    <p><?php echo htmlspecialchars($profile['phone_pay_number']); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if ($profile && $profile['google_pay_number']): ?>
                <div class="info-card">
                    <h3>Google Pay</h3>
                    <p><?php echo htmlspecialchars($profile['google_pay_number']); ?></p>
                </div>
                <?php endif; ?>
                
                <div class="info-card">
                    <h3>Member Since</h3>
                    <p><?php echo date('F Y', strtotime($user['created_at'])); ?></p>
                </div>
            </div>
            
            <?php if ($profile && $profile['bio']): ?>
            <div class="bio-section">
                <h3>About Me</h3>
                <p><?php echo nl2br(htmlspecialchars($profile['bio'])); ?></p>
            </div>
            <?php endif; ?>
            
            <?php if (!$profile || !$profile['full_name']): ?>
            <div style="margin-top: 30px; padding: 20px; background: rgba(157, 78, 221, 0.2); border: 2px solid #9d4edd; border-radius: 10px;">
                <p style="color: #9d4edd; font-size: 1.1rem; margin-bottom: 15px;">Complete your profile to unlock all features!</p>
                <a href="profile.php" class="btn btn-primary" style="display: inline-block;">Create Profile Now</a>
            </div>
            <?php endif; ?>
            
            <!-- Change Password Section -->
            <div style="margin-top: 40px; padding: 25px; background: rgba(0, 255, 255, 0.1); border: 2px solid #00ffff; border-radius: 10px;">
                <h3 style="font-family: 'Orbitron', sans-serif; color: #00ffff; font-size: 1.2rem; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 15px;">
                    🔐 Change Password
                </h3>
                <p style="color: rgba(255, 255, 255, 0.8); margin-bottom: 20px; line-height: 1.6;">
                    Update your account password to keep your account secure.
                </p>
                <button onclick="showChangePasswordModal()" class="btn-change-password" style="background: linear-gradient(135deg, #00ffff, #9d4edd); border: none; color: white; padding: 12px 25px; border-radius: 8px; font-weight: 700; cursor: pointer; transition: all 0.3s;">
                    Change Password
                </button>
            </div>
            
            <!-- Delete Account Section -->
            <div style="margin-top: 40px; padding: 25px; background: rgba(255, 0, 0, 0.1); border: 2px solid #ff0000; border-radius: 10px;">
                <h3 style="font-family: 'Orbitron', sans-serif; color: #ff0000; font-size: 1.2rem; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 15px;">
                    ⚠️ Danger Zone
                </h3>
                <p style="color: rgba(255, 255, 255, 0.8); margin-bottom: 15px; line-height: 1.6;">
                    Deleting your account will permanently remove all your data including profile, credits, scores, and referral information. This action cannot be undone.
                </p>
                <button onclick="showDeleteModal()" class="btn-delete" style="background: linear-gradient(135deg, #ff0000, #cc0000); border: none; color: white; padding: 12px 25px; border-radius: 8px; font-weight: 700; cursor: pointer; transition: all 0.3s;">
                    Delete My Account
                </button>
            </div>
        </div>
    </div>
    
    <!-- Change Password Modal -->
    <div class="modal-overlay" id="changePasswordModal" style="display: none;">
        <div class="password-modal">
            <div class="password-modal-header">
                <h2>🔐 Change Password</h2>
                <button onclick="closeChangePasswordModal()" style="background: none; border: none; color: #00ffff; font-size: 1.5rem; cursor: pointer; position: absolute; top: 15px; right: 15px;">&times;</button>
            </div>
            <div class="password-modal-content">
                <?php if (isset($_SESSION['password_error'])): ?>
                    <div class="error-message" style="background: rgba(255, 0, 0, 0.2); border: 2px solid #ff0000; color: #ff0000; padding: 10px; border-radius: 8px; margin-bottom: 15px;">
                        <?php 
                        echo htmlspecialchars($_SESSION['password_error']); 
                        unset($_SESSION['password_error']);
                        ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['password_success'])): ?>
                    <div class="success-message" id="passwordSuccessMessage" style="background: rgba(0, 255, 0, 0.2); border: 2px solid #00ff00; color: #00ff00; padding: 10px; border-radius: 8px; margin-bottom: 15px;">
                        <?php 
                        echo htmlspecialchars($_SESSION['password_success']); 
                        unset($_SESSION['password_success']);
                        ?>
                    </div>
                    <script>
                        // Auto reload page after password change success
                        setTimeout(function() {
                            window.location.reload();
                        }, 2000); // Reload after 2 seconds
                    </script>
                <?php endif; ?>
                <form method="POST" action="change_password.php" id="changePasswordForm" onsubmit="return validatePasswordChange(event)">
                    <div id="currentPasswordSection">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" placeholder="Enter your current password" required 
                                   style="width: 100%; padding: 12px; background: rgba(0, 0, 0, 0.5); border: 2px solid #00ffff; border-radius: 8px; color: #00ffff; font-size: 1rem;">
                            <div id="currentPasswordError" style="color: #ff0000; font-size: 0.9rem; margin-top: 8px; display: none;"></div>
                        </div>
                        <div class="password-modal-buttons">
                            <button type="button" onclick="closeChangePasswordModal()" class="btn-cancel">Cancel</button>
                            <button type="button" onclick="verifyCurrentPassword()" class="btn-confirm-password" id="verifyPasswordBtn">Verify Password</button>
                        </div>
                    </div>
                    
                    <div id="newPasswordSection" style="display: none;">
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" placeholder="Enter new password (min 6 characters)" required 
                                   style="width: 100%; padding: 12px; background: rgba(0, 0, 0, 0.5); border: 2px solid #00ffff; border-radius: 8px; color: #00ffff; font-size: 1rem;">
                            <small style="color: rgba(0, 255, 255, 0.6); font-size: 0.85rem; margin-top: 5px; display: block;">Password must be at least 6 characters long</small>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your new password" required 
                                   style="width: 100%; padding: 12px; background: rgba(0, 0, 0, 0.5); border: 2px solid #00ffff; border-radius: 8px; color: #00ffff; font-size: 1rem;">
                            <small id="passwordMatch" style="color: rgba(0, 255, 255, 0.6); font-size: 0.85rem; margin-top: 5px; display: block;"></small>
                        </div>
                        <div class="password-modal-buttons">
                            <button type="button" onclick="goBackToCurrentPassword()" class="btn-cancel">Back</button>
                            <button type="submit" name="change_password" class="btn-confirm-password">Update Password</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Account Modal -->
    <div class="modal-overlay" id="deleteAccountModal" style="display: none;">
        <div class="delete-modal">
            <div class="delete-modal-header">
                <h2>⚠️ Delete Account</h2>
            </div>
            <div class="delete-modal-content">
                <p class="warning-text">This action cannot be undone!</p>
                <p>All your data will be permanently deleted:</p>
                <ul class="delete-list">
                    <li>Your profile information</li>
                    <li>All credits</li>
                    <li>Game scores and leaderboard entries</li>
                    <li>Transaction history</li>
                    <li>Referral information</li>
                </ul>
                <?php if (isset($_SESSION['delete_error'])): ?>
                    <div class="error-message" style="background: rgba(255, 0, 0, 0.2); border: 2px solid #ff0000; color: #ff0000; padding: 10px; border-radius: 8px; margin: 15px 0;">
                        <?php 
                        echo htmlspecialchars($_SESSION['delete_error']); 
                        unset($_SESSION['delete_error']);
                        ?>
                    </div>
                <?php endif; ?>
                <form method="POST" action="delete_account.php" id="deleteAccountForm" onsubmit="return confirmFinalDelete(event)">
                    <div class="form-group">
                        <label for="confirmText">Type <strong>DELETE</strong> to confirm:</label>
                        <input type="text" id="confirmText" name="confirm_text" placeholder="Type DELETE" required 
                               style="width: 100%; padding: 12px; background: rgba(0, 0, 0, 0.5); border: 2px solid #ff0000; border-radius: 8px; color: #ff0000; font-weight: 700; text-transform: uppercase; font-size: 1.1rem; text-align: center; letter-spacing: 2px;">
                    </div>
                    <div class="delete-modal-buttons">
                        <button type="button" onclick="closeDeleteModal()" class="btn-cancel">Cancel</button>
                        <button type="submit" name="confirm_delete" class="btn-confirm-delete">Delete Account Permanently</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <style>
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10000;
        }
        
        .delete-modal {
            background: rgba(15, 15, 25, 0.95);
            border: 3px solid #ff0000;
            border-radius: 15px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 0 30px rgba(255, 0, 0, 0.5);
        }
        
        .delete-modal-header h2 {
            font-family: 'Orbitron', sans-serif;
            color: #ff0000;
            text-align: center;
            margin-bottom: 20px;
            font-size: 1.8rem;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .delete-modal-content {
            color: rgba(255, 255, 255, 0.9);
        }
        
        .warning-text {
            color: #ff0000;
            font-weight: 700;
            font-size: 1.1rem;
            text-align: center;
            margin-bottom: 15px;
        }
        
        .delete-list {
            list-style: none;
            padding: 0;
            margin: 15px 0;
        }
        
        .delete-list li {
            padding: 8px 0;
            padding-left: 25px;
            position: relative;
            color: rgba(255, 255, 255, 0.8);
        }
        
        .delete-list li:before {
            content: '✗';
            position: absolute;
            left: 0;
            color: #ff0000;
            font-weight: 700;
        }
        
        .delete-modal-buttons {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }
        
        .btn-cancel {
            flex: 1;
            padding: 12px;
            background: rgba(0, 255, 255, 0.2);
            border: 2px solid #00ffff;
            color: #00ffff;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-cancel:hover {
            background: rgba(0, 255, 255, 0.4);
        }
        
        .btn-confirm-delete {
            flex: 1;
            padding: 12px;
            background: linear-gradient(135deg, #ff0000, #cc0000);
            border: none;
            color: white;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-confirm-delete:hover {
            background: linear-gradient(135deg, #cc0000, #990000);
            transform: scale(1.02);
        }
        
        .btn-delete:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 20px rgba(255, 0, 0, 0.5);
        }
        
        .btn-change-password:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 20px rgba(0, 255, 255, 0.5);
        }
        
        .password-modal {
            background: rgba(15, 15, 25, 0.95);
            border: 3px solid #00ffff;
            border-radius: 15px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 0 30px rgba(0, 255, 255, 0.5);
            position: relative;
        }
        
        .password-modal-header {
            position: relative;
        }
        
        .password-modal-header h2 {
            font-family: 'Orbitron', sans-serif;
            color: #00ffff;
            text-align: center;
            margin-bottom: 20px;
            font-size: 1.8rem;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .password-modal-content {
            color: rgba(255, 255, 255, 0.9);
        }
        
        .password-modal-content .form-group {
            margin-bottom: 20px;
        }
        
        .password-modal-content label {
            display: block;
            margin-bottom: 8px;
            color: #00ffff;
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .password-modal-buttons {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }
        
        .btn-confirm-password {
            flex: 1;
            padding: 12px;
            background: linear-gradient(135deg, #00ffff, #9d4edd);
            border: none;
            color: white;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-confirm-password:hover {
            transform: scale(1.02);
            box-shadow: 0 5px 20px rgba(0, 255, 255, 0.5);
        }
    </style>
    
    <script>
        function showDeleteModal() {
            document.getElementById('deleteAccountModal').style.display = 'flex';
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteAccountModal').style.display = 'none';
            document.getElementById('confirmText').value = '';
        }
        
        function confirmFinalDelete(event) {
            const confirmText = document.getElementById('confirmText').value.trim();
            
            if (confirmText !== 'DELETE') {
                event.preventDefault();
                alert('Please type "DELETE" exactly to confirm account deletion.');
                return false;
            }
            
            // Final confirmation
            if (!confirm('⚠️ FINAL WARNING: This will permanently delete your account and all data. This cannot be undone. Are you absolutely sure?')) {
                event.preventDefault();
                return false;
            }
            
            return true;
        }
        
        function showChangePasswordModal() {
            document.getElementById('changePasswordModal').style.display = 'flex';
            // Reset to initial state
            resetPasswordForm();
        }
        
        function closeChangePasswordModal() {
            document.getElementById('changePasswordModal').style.display = 'none';
            resetPasswordForm();
        }
        
        function resetPasswordForm() {
            document.getElementById('changePasswordForm').reset();
            document.getElementById('passwordMatch').textContent = '';
            document.getElementById('currentPasswordError').style.display = 'none';
            document.getElementById('currentPasswordError').textContent = '';
            document.getElementById('currentPasswordSection').style.display = 'block';
            document.getElementById('newPasswordSection').style.display = 'none';
        }
        
        function goBackToCurrentPassword() {
            document.getElementById('currentPasswordSection').style.display = 'block';
            document.getElementById('newPasswordSection').style.display = 'none';
            document.getElementById('new_password').value = '';
            document.getElementById('confirm_password').value = '';
            document.getElementById('passwordMatch').textContent = '';
        }
        
        function verifyCurrentPassword() {
            const currentPassword = document.getElementById('current_password').value;
            const errorDiv = document.getElementById('currentPasswordError');
            const verifyBtn = document.getElementById('verifyPasswordBtn');
            
            if (!currentPassword) {
                errorDiv.textContent = 'Please enter your current password';
                errorDiv.style.display = 'block';
                return;
            }
            
            // Disable button and show loading
            verifyBtn.disabled = true;
            verifyBtn.textContent = 'Verifying...';
            errorDiv.style.display = 'none';
            
            // Verify password via AJAX
            const formData = new FormData();
            formData.append('current_password', currentPassword);
            
            fetch('verify_current_password.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        // Password is correct, show new password fields
                        document.getElementById('currentPasswordSection').style.display = 'none';
                        document.getElementById('newPasswordSection').style.display = 'block';
                        errorDiv.style.display = 'none';
                    } else {
                        // Password is wrong
                        errorDiv.textContent = data.message || 'Wrong password';
                        errorDiv.style.display = 'block';
                        verifyBtn.disabled = false;
                        verifyBtn.textContent = 'Verify Password';
                    }
                } catch (parseError) {
                    console.error('JSON Parse Error:', parseError);
                    console.error('Response Text:', text);
                    errorDiv.textContent = 'Wrong password';
                    errorDiv.style.display = 'block';
                    verifyBtn.disabled = false;
                    verifyBtn.textContent = 'Verify Password';
                }
            })
            .catch(error => {
                console.error('Password verification error:', error);
                errorDiv.textContent = 'Wrong password';
                errorDiv.style.display = 'block';
                verifyBtn.disabled = false;
                verifyBtn.textContent = 'Verify Password';
            });
        }
        
        function validatePasswordChange(event) {
            const currentPassword = document.getElementById('current_password').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            // Check if new password is at least 6 characters
            if (newPassword.length < 6) {
                event.preventDefault();
                alert('New password must be at least 6 characters long.');
                return false;
            }
            
            // Check if passwords match
            if (newPassword !== confirmPassword) {
                event.preventDefault();
                alert('New password and confirm password do not match. Please try again.');
                return false;
            }
            
            // Check if new password is different from current password
            if (currentPassword === newPassword) {
                event.preventDefault();
                alert('New password must be different from your current password.');
                return false;
            }
            
            return true;
        }
        
        // Real-time password match validation
        document.getElementById('confirm_password')?.addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            const matchElement = document.getElementById('passwordMatch');
            
            if (confirmPassword.length > 0) {
                if (newPassword === confirmPassword) {
                    matchElement.textContent = '✓ Passwords match';
                    matchElement.style.color = '#00ff00';
                } else {
                    matchElement.textContent = '✗ Passwords do not match';
                    matchElement.style.color = '#ff0000';
                }
            } else {
                matchElement.textContent = '';
            }
        });
        
        // Close modals when clicking outside
        document.getElementById('changePasswordModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeChangePasswordModal();
            }
        });
        
        document.getElementById('deleteAccountModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });
    </script>
</body>
</html>

