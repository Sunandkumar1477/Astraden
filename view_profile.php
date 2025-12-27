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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>My Profile - Space Games Hub</title>
    <link rel="icon" type="image/svg+xml" href="logo.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #050508;
            --card-bg: rgba(15, 15, 25, 0.9);
            --cyan: #00ffff;
            --purple: #9d4edd;
            --gold: #FFD700;
            --danger: #ff4d4d;
            --text-main: #e2e8f0;
            --glow-shadow: 0 0 15px rgba(0, 255, 255, 0.3);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: 'Rajdhani', sans-serif;
            background: var(--bg-dark);
            color: var(--text-main);
            min-height: 100vh;
            line-height: 1.4;
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(157, 78, 221, 0.15) 0%, transparent 40%),
                radial-gradient(circle at 90% 80%, rgba(0, 255, 255, 0.1) 0%, transparent 40%);
            background-attachment: fixed;
            padding: 15px;
        }

        /* --- NAVIGATION / HEADER --- */
        .header {
            max-width: 900px;
            margin: 10px auto 20px;
            display: flex;
            flex-direction: row;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background: var(--card-bg);
            border: 1px solid var(--cyan);
            border-radius: 12px;
            box-shadow: var(--glow-shadow);
        }

        .header h1 {
            font-family: 'Orbitron', sans-serif;
            font-size: clamp(1rem, 4vw, 1.5rem);
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--cyan);
        }

        .header-buttons {
            display: flex;
            gap: 10px;
        }

        /* --- BUTTONS --- */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(0, 255, 255, 0.1);
            border: 1px solid var(--cyan);
            color: var(--cyan);
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .btn:active {
            transform: scale(0.95);
            background: rgba(0, 255, 255, 0.3);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--cyan), var(--purple));
            color: #fff;
            border: none;
            box-shadow: 0 4px 15px rgba(0, 255, 255, 0.3);
        }

        /* --- PROFILE MAIN DISPLAY --- */
        .container {
            max-width: 900px;
            margin: 0 auto 50px;
        }

        .profile-display {
            background: var(--card-bg);
            border: 1px solid rgba(0, 255, 255, 0.3);
            border-radius: 20px;
            padding: 30px 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .profile-display::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--cyan), var(--purple), var(--cyan));
        }

        .profile-icon-display {
            width: clamp(120px, 30vw, 180px);
            height: clamp(120px, 30vw, 180px);
            border-radius: 50%;
            border: 3px solid var(--cyan);
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: clamp(3rem, 10vw, 5rem);
            box-shadow: 0 0 25px rgba(0, 255, 255, 0.2);
            background: #1a1a2e;
        }

        .icon-boy1 { background: linear-gradient(135deg, #667eea, #764ba2); }
        .icon-girl1 { background: linear-gradient(135deg, #fa709a, #fee140); }
        .icon-beard { background: linear-gradient(135deg, #4facfe, #00f2fe); }
        .icon-bald { background: linear-gradient(135deg, #43e97b, #38f9d7); }
        .icon-fashion { background: linear-gradient(135deg, #a8edea, #fed6e3); }
        .icon-specs { background: linear-gradient(135deg, #30cfd0, #330867); }

        .profile-name {
            font-family: 'Orbitron', sans-serif;
            font-size: clamp(1.5rem, 6vw, 2.2rem);
            font-weight: 900;
            margin-bottom: 15px;
            color: #fff;
            letter-spacing: 2px;
        }

        /* --- CREDITS --- */
        .credits-display {
            margin: 10px 0 30px;
            padding: 15px 30px;
            background: rgba(0, 0, 0, 0.4);
            border-radius: 12px;
            border-left: 4px solid var(--gold);
            border-right: 4px solid var(--gold);
            display: inline-block;
        }

        .credits-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 3px;
            color: var(--gold);
            margin-bottom: 5px;
            font-weight: 700;
        }

        .credits-value {
            font-family: 'Orbitron', sans-serif;
            font-size: 2.5rem;
            font-weight: 900;
            color: var(--gold);
            text-shadow: 0 0 10px rgba(255, 215, 0, 0.3);
        }

        /* --- INFO GRID --- */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
            margin-top: 20px;
        }

        .info-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(0, 255, 255, 0.2);
            border-radius: 12px;
            padding: 15px;
            text-align: left;
            transition: border-color 0.3s;
        }

        .info-card h3 {
            font-family: 'Orbitron', sans-serif;
            color: var(--purple);
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 6px;
        }

        .info-card p {
            color: var(--cyan);
            font-size: 1rem;
            font-weight: 700;
            word-break: break-all;
        }

        /* --- BIO --- */
        .bio-section {
            margin-top: 25px;
            padding: 20px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 12px;
            border: 1px dashed var(--cyan);
            text-align: left;
        }

        .bio-section h3 {
            font-family: 'Orbitron', sans-serif;
            color: var(--purple);
            font-size: 0.8rem;
            margin-bottom: 10px;
            letter-spacing: 2px;
        }

        .bio-section p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.95rem;
            line-height: 1.5;
        }

        /* --- ACTION SECTIONS (Security/Danger) --- */
        .action-box {
            margin-top: 25px;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }

        .security-box {
            background: rgba(0, 255, 255, 0.05);
            border: 1px solid var(--cyan);
        }

        .danger-box {
            background: rgba(255, 77, 77, 0.05);
            border: 1px solid var(--danger);
        }

        .action-box h3 {
            font-family: 'Orbitron', sans-serif;
            font-size: 1rem;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .action-box p {
            font-size: 0.85rem;
            margin-bottom: 15px;
            opacity: 0.8;
        }

        .btn-action {
            width: 100%;
            max-width: 300px;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-weight: 800;
            font-family: 'Orbitron', sans-serif;
            cursor: pointer;
            color: white;
            text-transform: uppercase;
            transition: transform 0.2s;
        }

        .btn-pw { background: linear-gradient(90deg, var(--cyan), var(--purple)); }
        .btn-del { background: linear-gradient(90deg, var(--danger), #990000); }

        /* --- MODALS --- */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(8px);
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 15px;
            z-index: 2000;
        }

        .password-modal, .delete-modal {
            background: #0f172a;
            border: 2px solid var(--cyan);
            border-radius: 20px;
            width: 100%;
            max-width: 450px;
            padding: 25px;
            box-shadow: 0 0 40px rgba(0, 0, 0, 0.5);
            max-height: 90vh;
            overflow-y: auto;
        }

        .delete-modal { border-color: var(--danger); }

        .form-group { margin-bottom: 15px; text-align: left; }
        .form-group label { display: block; font-size: 0.8rem; margin-bottom: 5px; color: var(--cyan); font-weight: 700; }
        .form-control {
            width: 100%;
            padding: 12px;
            background: #000;
            border: 1px solid rgba(0, 255, 255, 0.3);
            border-radius: 8px;
            color: var(--cyan);
            font-family: inherit;
        }

        .modal-footer {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        /* --- MOBILE ADAPTATION --- */
        @media (max-width: 600px) {
            body { padding: 10px; }
            .header { padding: 12px; }
            .header h1 { font-size: 1.1rem; }
            .header-buttons .btn { padding: 6px 12px; font-size: 0.75rem; }
            
            .profile-display { padding: 25px 15px; }
            .credits-display { padding: 10px 20px; width: 100%; }
            .credits-value { font-size: 2rem; }
            
            .info-grid { grid-template-columns: 1fr 1fr; }
            .info-card { padding: 12px; }
            .info-card p { font-size: 0.9rem; }
            
            .modal-footer { flex-direction: column-reverse; }
            .modal-footer button { width: 100%; }
        }

        @media (max-width: 360px) {
            .info-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🌍 Profile</h1>
        <div class="header-buttons">
            <a href="profile.php" class="btn btn-primary">Edit</a>
            <a href="index.php" class="btn">Back</a>
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
            
            <div class="credits-display">
                <div class="credits-label">Available Credits</div>
                <div class="credits-value">
                    <?php echo number_format($profile['credits'] ?? 0); ?>
                </div>
            </div>
            
            <div class="info-grid">
                <div class="info-card">
                    <h3>ID Name</h3>
                    <p><?php echo htmlspecialchars($user['username']); ?></p>
                </div>
                
                <div class="info-card">
                    <h3>Mobile</h3>
                    <p><?php echo htmlspecialchars($user['mobile_number']); ?></p>
                </div>
                
                <div class="info-card">
                    <h3>Location</h3>
                    <p><?php echo htmlspecialchars($profile['state'] ?? 'Not Set'); ?></p>
                </div>

                <div class="info-card">
                    <h3>Joined</h3>
                    <p><?php echo date('M Y', strtotime($user['created_at'])); ?></p>
                </div>
                
                <?php if ($profile && $profile['phone_pay_number']): ?>
                <div class="info-card">
                    <h3>PhonePay</h3>
                    <p><?php echo htmlspecialchars($profile['phone_pay_number']); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if ($profile && $profile['google_pay_number']): ?>
                <div class="info-card">
                    <h3>G-Pay</h3>
                    <p><?php echo htmlspecialchars($profile['google_pay_number']); ?></p>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if ($profile && $profile['bio']): ?>
            <div class="bio-section">
                <h3>Bio Data</h3>
                <p><?php echo nl2br(htmlspecialchars($profile['bio'])); ?></p>
            </div>
            <?php endif; ?>
            
            <?php if (!$profile || !$profile['full_name']): ?>
            <div style="margin-top: 30px; padding: 20px; background: rgba(157, 78, 221, 0.2); border: 1px solid var(--purple); border-radius: 12px;">
                <p style="color: var(--purple); font-weight: bold; margin-bottom: 15px;">Your profile is incomplete!</p>
                <a href="profile.php" class="btn btn-primary">Complete Profile</a>
            </div>
            <?php endif; ?>
            
            <!-- Security Section -->
            <div class="action-box security-box">
                <h3>🔐 Privacy</h3>
                <p>Manage your account security settings.</p>
                <button onclick="showChangePasswordModal()" class="btn-action btn-pw">Change Password</button>
            </div>
            
            <!-- Danger Zone -->
            <div class="action-box danger-box">
                <h3>⚠️ Danger Zone</h3>
                <p>Permanently remove your account data.</p>
                <button onclick="showDeleteModal()" class="btn-action btn-del">Delete Account</button>
            </div>
        </div>
    </div>
    
    <!-- Change Password Modal -->
    <div class="modal-overlay" id="changePasswordModal" style="display: none;">
        <div class="password-modal">
            <div style="text-align: right;">
                <button onclick="closeChangePasswordModal()" style="background:none; border:none; color:#fff; font-size:1.5rem;">&times;</button>
            </div>
            <h2 style="font-family:'Orbitron'; color:var(--cyan); margin-bottom:20px; text-align:center;">Change Password</h2>
            
            <div id="pw-msg-container"></div>

            <form method="POST" action="change_password.php" id="changePasswordForm" onsubmit="return validatePasswordChange(event)">
                <div id="currentPasswordSection">
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" id="current_password" name="current_password" class="form-control" required>
                        <div id="currentPasswordError" style="color:var(--danger); font-size:0.8rem; margin-top:5px; display:none;"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" onclick="closeChangePasswordModal()" class="btn" style="flex:1">Cancel</button>
                        <button type="button" onclick="verifyCurrentPassword()" class="btn-primary btn" id="verifyPasswordBtn" style="flex:2">Next Step</button>
                    </div>
                </div>
                
                <div id="newPasswordSection" style="display: none;">
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" id="new_password" name="new_password" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        <small id="passwordMatch"></small>
                    </div>
                    <div class="modal-footer">
                        <button type="button" onclick="goBackToCurrentPassword()" class="btn" style="flex:1">Back</button>
                        <button type="submit" name="change_password" class="btn btn-primary" style="flex:2">Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete Account Modal -->
    <div class="modal-overlay" id="deleteAccountModal" style="display: none;">
        <div class="delete-modal">
            <h2 style="font-family:'Orbitron'; color:var(--danger); margin-bottom:15px; text-align:center;">⚠️ TERMINATE ACCOUNT</h2>
            <p style="color:var(--danger); font-weight:bold; margin-bottom:15px; font-size:0.9rem;">THIS ACTION IS IRREVERSIBLE!</p>
            <p style="font-size:0.85rem; margin-bottom:15px;">You will lose all credits, scores, and profile data.</p>
            
            <form method="POST" action="delete_account.php" onsubmit="return confirmFinalDelete(event)">
                <div class="form-group">
                    <label>Type <span style="color:#fff">DELETE</span> below:</label>
                    <input type="text" id="confirmText" name="confirm_text" class="form-control" style="border-color:var(--danger); text-align:center; font-weight:bold; color:var(--danger);" required>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeDeleteModal()" class="btn" style="flex:1">Keep Account</button>
                    <button type="submit" name="confirm_delete" class="btn" style="background:var(--danger); color:white; border:none; flex:1">Confirm Delete</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function showDeleteModal() { document.getElementById('deleteAccountModal').style.display = 'flex'; }
        function closeDeleteModal() { document.getElementById('deleteAccountModal').style.display = 'none'; }
        
        function confirmFinalDelete(event) {
            const confirmText = document.getElementById('confirmText').value.trim();
            if (confirmText !== 'DELETE') {
                event.preventDefault();
                alert('Please type "DELETE" exactly.');
                return false;
            }
            return confirm('Final warning: Are you absolutely sure?');
        }
        
        function showChangePasswordModal() { document.getElementById('changePasswordModal').style.display = 'flex'; resetPasswordForm(); }
        function closeChangePasswordModal() { document.getElementById('changePasswordModal').style.display = 'none'; }
        
        function resetPasswordForm() {
            document.getElementById('changePasswordForm').reset();
            document.getElementById('currentPasswordError').style.display = 'none';
            document.getElementById('currentPasswordSection').style.display = 'block';
            document.getElementById('newPasswordSection').style.display = 'none';
        }

        function goBackToCurrentPassword() {
            document.getElementById('currentPasswordSection').style.display = 'block';
            document.getElementById('newPasswordSection').style.display = 'none';
        }
        
        function verifyCurrentPassword() {
            const currentPassword = document.getElementById('current_password').value;
            const errorDiv = document.getElementById('currentPasswordError');
            const verifyBtn = document.getElementById('verifyPasswordBtn');
            
            if (!currentPassword) return;
            
            verifyBtn.disabled = true;
            verifyBtn.textContent = 'Checking...';
            
            const formData = new FormData();
            formData.append('current_password', currentPassword);
            
            fetch('verify_current_password.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('currentPasswordSection').style.display = 'none';
                    document.getElementById('newPasswordSection').style.display = 'block';
                } else {
                    errorDiv.textContent = data.message || 'Incorrect password.';
                    errorDiv.style.display = 'block';
                    verifyBtn.disabled = false;
                    verifyBtn.textContent = 'Next Step';
                }
            })
            .catch(() => {
                errorDiv.textContent = 'Error connecting to server.';
                errorDiv.style.display = 'block';
                verifyBtn.disabled = false;
                verifyBtn.textContent = 'Next Step';
            });
        }
        
        function validatePasswordChange(event) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword.length < 6) {
                alert('Minimum 6 characters.');
                event.preventDefault(); return false;
            }
            if (newPassword !== confirmPassword) {
                alert('Passwords match error.');
                event.preventDefault(); return false;
            }
            return true;
        }

        document.getElementById('confirm_password')?.addEventListener('input', function() {
            const newP = document.getElementById('new_password').value;
            const matchEl = document.getElementById('passwordMatch');
            if (this.value) {
                matchEl.textContent = (newP === this.value) ? '✓ Match' : '✗ No Match';
                matchEl.style.color = (newP === this.value) ? '#00ff00' : '#ff0000';
            } else { matchEl.textContent = ''; }
        });
    </script>
</body>
</html>