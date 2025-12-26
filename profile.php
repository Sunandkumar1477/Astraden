<?php
require_once 'check_user_session.php';
// Connection is already established in check_user_session.php
$conn = $GLOBALS['conn'];

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone_pay = trim($_POST['phone_pay'] ?? '');
    $google_pay = trim($_POST['google_pay'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $credits_color = trim($_POST['credits_color'] ?? '#00ffff');
    $bio = trim($_POST['bio'] ?? '');
    
    // Handle profile icon selection
    $profile_icon = trim($_POST['profile_icon'] ?? '');
    $valid_icons = ['boy1', 'girl1', 'beard', 'bald', 'fashion', 'specs'];
    if (!empty($profile_icon) && !in_array($profile_icon, $valid_icons)) {
        $error = 'Invalid icon selection';
        $profile_icon = null;
    }
    
    // Check if profile exists
    $check_stmt = $conn->prepare("SELECT id FROM user_profile WHERE user_id = ?");
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $check_stmt->close();
    
    if ($check_result->num_rows > 0) {
        // Update existing profile
        if ($profile_icon) {
            $stmt = $conn->prepare("UPDATE user_profile SET full_name = ?, profile_photo = ?, phone_pay_number = ?, google_pay_number = ?, state = ?, credits_color = ?, bio = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?");
            $stmt->bind_param("sssssssi", $full_name, $profile_icon, $phone_pay, $google_pay, $state, $credits_color, $bio, $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE user_profile SET full_name = ?, phone_pay_number = ?, google_pay_number = ?, state = ?, credits_color = ?, bio = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?");
            $stmt->bind_param("ssssssi", $full_name, $phone_pay, $google_pay, $state, $credits_color, $bio, $user_id);
        }
    } else {
        // Create new profile
        if ($profile_icon) {
            $stmt = $conn->prepare("INSERT INTO user_profile (user_id, full_name, profile_photo, phone_pay_number, google_pay_number, state, credits_color, bio) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssssss", $user_id, $full_name, $profile_icon, $phone_pay, $google_pay, $state, $credits_color, $bio);
        } else {
            $stmt = $conn->prepare("INSERT INTO user_profile (user_id, full_name, phone_pay_number, google_pay_number, state, credits_color, bio) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssss", $user_id, $full_name, $phone_pay, $google_pay, $state, $credits_color, $bio);
        }
    }
    
    if ($stmt->execute()) {
        $message = 'Profile updated successfully!';
        // Redirect to view profile after 2 seconds
        header('refresh:2;url=view_profile.php');
    } else {
        $error = 'Failed to update profile. Please try again.';
    }
    $stmt->close();
}

// Get user profile
$profile_stmt = $conn->prepare("SELECT * FROM user_profile WHERE user_id = ?");
$profile_stmt->bind_param("i", $user_id);
$profile_stmt->execute();
$profile_result = $profile_stmt->get_result();
$profile = $profile_result->fetch_assoc();
$profile_stmt->close();

// Get user info
$user_stmt = $conn->prepare("SELECT username, mobile_number FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();
$user_stmt->close();

$conn->close();

// Indian states list
$indian_states = [
    'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh',
    'Goa', 'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jharkhand',
    'Karnataka', 'Kerala', 'Madhya Pradesh', 'Maharashtra', 'Manipur',
    'Meghalaya', 'Mizoram', 'Nagaland', 'Odisha', 'Punjab',
    'Rajasthan', 'Sikkim', 'Tamil Nadu', 'Telangana', 'Tripura',
    'Uttar Pradesh', 'Uttarakhand', 'West Bengal', 'Delhi', 'Jammu and Kashmir',
    'Ladakh', 'Puducherry', 'Andaman and Nicobar Islands', 'Chandigarh', 'Dadra and Nagar Haveli',
    'Daman and Diu', 'Lakshadweep'
];
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
        }
        .header {
            max-width: 1200px;
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
        .back-btn {
            background: rgba(0, 255, 255, 0.2);
            border: 2px solid #00ffff;
            color: #00ffff;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        .back-btn:hover {
            background: rgba(0, 255, 255, 0.4);
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
        }
        .profile-card {
            background: rgba(15, 15, 25, 0.8);
            border: 2px solid #00ffff;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
        }
        .profile-icon-container {
            position: relative;
            margin-bottom: 20px;
        }
        .profile-icon-display {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            border: 3px solid #00ffff;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            box-shadow: 0 0 20px rgba(0, 255, 255, 0.5);
            position: relative;
            overflow: hidden;
        }
        .icon-selector {
            display: flex;
            flex-wrap: nowrap;
            gap: 8px;
            margin-top: 10px;
            padding: 8px;
            background: transparent;
            border-radius: 0;
            border: none;
            justify-content: flex-start;
            align-items: center;
        }
        .icon-option {
            flex: 0 0 auto;
            width: 40px;
            height: 40px;
            min-width: 40px;
            min-height: 40px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            transition: all 0.3s;
            background: transparent;
            position: relative;
            overflow: visible;
            user-select: none;
            -webkit-user-select: none;
            padding: 0;
            margin: 0;
        }
        .icon-option > * {
            position: relative;
            display: block;
            line-height: 1;
        }
        .icon-option:hover {
            transform: scale(1.2);
            opacity: 0.8;
        }
        .icon-option input[type="radio"] {
            display: none;
        }
        .icon-option.selected {
            transform: scale(1.15);
            opacity: 1;
            filter: drop-shadow(0 0 10px rgba(157, 78, 221, 0.8));
        }
        .icon-label {
            display: none;
        }
        /* Remove all backgrounds from icon selector options - show only icons */
        .icon-selector .icon-boy1,
        .icon-selector .icon-girl1,
        .icon-selector .icon-beard,
        .icon-selector .icon-bald,
        .icon-selector .icon-fashion,
        .icon-selector .icon-specs {
            background: transparent !important;
        }
        /* Keep gradients only for profile display */
        .profile-icon-display.icon-boy1 { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .profile-icon-display.icon-girl1 { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .profile-icon-display.icon-beard { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .profile-icon-display.icon-bald { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        .profile-icon-display.icon-fashion { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); }
        .profile-icon-display.icon-specs { background: linear-gradient(135deg, #30cfd0 0%, #330867 100%); }
        .credits-display {
            margin-top: 20px;
            padding: 15px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 10px;
            border: 2px solid;
        }
        .credits-label {
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 10px;
            color: rgba(0, 255, 255, 0.7);
        }
        .credits-value {
            font-family: 'Orbitron', sans-serif;
            font-size: 2.5rem;
            font-weight: 900;
        }
        .form-section {
            background: rgba(15, 15, 25, 0.8);
            border: 2px solid #00ffff;
            border-radius: 15px;
            padding: 30px;
        }
        .form-section h2 {
            font-family: 'Orbitron', sans-serif;
            font-size: 1.5rem;
            margin-bottom: 25px;
            text-transform: uppercase;
            letter-spacing: 2px;
            border-bottom: 2px solid #00ffff;
            padding-bottom: 10px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group:has(.icon-selector) {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            color: #00ffff;
            margin-bottom: 8px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 1px;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            background: rgba(0, 0, 0, 0.5);
            border: 2px solid #00ffff;
            border-radius: 8px;
            color: #00ffff;
            font-family: 'Rajdhani', sans-serif;
            font-size: 1rem;
            outline: none;
            transition: all 0.3s;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #9d4edd;
            box-shadow: 0 0 15px rgba(157, 78, 221, 0.5);
        }
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        .color-picker-group {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        .color-picker {
            width: 60px;
            height: 60px;
            border: 2px solid #00ffff;
            border-radius: 8px;
            cursor: pointer;
        }
        .color-preview {
            flex: 1;
            padding: 12px;
            background: rgba(0, 0, 0, 0.5);
            border: 2px solid #00ffff;
            border-radius: 8px;
            text-align: center;
            font-weight: 600;
        }
        .submit-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #00ffff, #9d4edd);
            border: none;
            border-radius: 8px;
            color: white;
            font-family: 'Orbitron', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }
        .submit-btn:hover {
            transform: scale(1.02);
            box-shadow: 0 5px 20px rgba(0, 255, 255, 0.5);
        }
        .message {
            background: rgba(0, 255, 0, 0.2);
            border: 1px solid #00ff00;
            color: #00ff00;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            text-align: center;
        }
        .error {
            background: rgba(255, 0, 0, 0.2);
            border: 1px solid #ff0000;
            color: #ff0000;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            text-align: center;
        }
        .user-info {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid rgba(0, 255, 255, 0.2);
        }
        .user-info-item {
            margin-bottom: 10px;
            font-size: 0.9rem;
        }
        .user-info-item strong {
            color: #9d4edd;
        }
        @media (max-width: 768px) {
            .container {
                grid-template-columns: 1fr;
            }
            .icon-selector {
                gap: 6px;
                padding: 6px;
                margin-top: 8px;
            }
            .icon-option {
                width: 38px;
                height: 38px;
                min-width: 38px;
                min-height: 38px;
                font-size: 1.8rem;
            }
            .profile-icon-display {
                width: 120px;
                height: 120px;
                font-size: 3rem;
            }
        }
        @media (max-width: 480px) {
            .icon-selector {
                gap: 5px;
                padding: 5px;
                margin-top: 6px;
            }
            .icon-option {
                width: 36px;
                height: 36px;
                min-width: 36px;
                min-height: 36px;
                font-size: 1.6rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🌍 My Profile</h1>
        <a href="index.php" class="back-btn">← Back to Games</a>
    </div>

    <div class="container">
        <!-- Profile Display Card -->
        <div class="profile-card">
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
                <div id="profileIconDisplay" class="profile-icon-display <?php echo htmlspecialchars($icon_class); ?>">
                    <?php echo $icon_emoji; ?>
                </div>
            </div>
            
            <h2 style="font-family: 'Orbitron', sans-serif; margin-top: 20px; font-size: 1.5rem;">
                <?php echo htmlspecialchars($profile['full_name'] ?? $_SESSION['username'] ?? 'User'); ?>
            </h2>
            
            <div class="credits-display" style="border-color: #FFD700;">
                <div class="credits-label">Credits</div>
                <div class="credits-value" style="color: #FFD700;">
                    <?php echo number_format($profile['credits'] ?? 0); ?>
                </div>
            </div>
            
            <div class="user-info">
                <div class="user-info-item"><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></div>
                <div class="user-info-item"><strong>Mobile:</strong> <?php echo htmlspecialchars($user['mobile_number']); ?></div>
                <?php if ($profile && $profile['state']): ?>
                    <div class="user-info-item"><strong>State:</strong> <?php echo htmlspecialchars($profile['state']); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Profile Form -->
        <div class="form-section">
            <h2>Edit Profile</h2>
            
            <?php if ($message): ?>
                <div class="message"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($profile['full_name'] ?? ''); ?>" placeholder="Enter your full name">
                </div>
                
                <div class="form-group">
                    <label>Choose Profile Icon</label>
                    <div class="icon-selector">
                        <!-- Boy -->
                        <label class="icon-option icon-boy1 <?php echo (isset($profile['profile_photo']) && $profile['profile_photo'] === 'boy1') ? 'selected' : ''; ?>">
                            <input type="radio" name="profile_icon" value="boy1" <?php echo (isset($profile['profile_photo']) && $profile['profile_photo'] === 'boy1') ? 'checked' : ''; ?>>
                            <span style="display: block; font-size: inherit; line-height: 1;">👨</span>
                        </label>
                        <!-- Girl -->
                        <label class="icon-option icon-girl1 <?php echo (isset($profile['profile_photo']) && $profile['profile_photo'] === 'girl1') ? 'selected' : ''; ?>">
                            <input type="radio" name="profile_icon" value="girl1" <?php echo (isset($profile['profile_photo']) && $profile['profile_photo'] === 'girl1') ? 'checked' : ''; ?>>
                            <span style="display: block; font-size: inherit; line-height: 1;">👩</span>
                        </label>
                        <!-- Beard Person -->
                        <label class="icon-option icon-beard <?php echo (isset($profile['profile_photo']) && $profile['profile_photo'] === 'beard') ? 'selected' : ''; ?>">
                            <input type="radio" name="profile_icon" value="beard" <?php echo (isset($profile['profile_photo']) && $profile['profile_photo'] === 'beard') ? 'checked' : ''; ?>>
                            <span style="display: block; font-size: inherit; line-height: 1;">🧔</span>
                        </label>
                        <!-- Bald Person -->
                        <label class="icon-option icon-bald <?php echo (isset($profile['profile_photo']) && $profile['profile_photo'] === 'bald') ? 'selected' : ''; ?>">
                            <input type="radio" name="profile_icon" value="bald" <?php echo (isset($profile['profile_photo']) && $profile['profile_photo'] === 'bald') ? 'checked' : ''; ?>>
                            <span style="display: block; font-size: inherit; line-height: 1;">👨‍🦲</span>
                        </label>
                        <!-- Fashion Girl -->
                        <label class="icon-option icon-fashion <?php echo (isset($profile['profile_photo']) && $profile['profile_photo'] === 'fashion') ? 'selected' : ''; ?>">
                            <input type="radio" name="profile_icon" value="fashion" <?php echo (isset($profile['profile_photo']) && $profile['profile_photo'] === 'fashion') ? 'checked' : ''; ?>>
                            <span style="display: block; font-size: inherit; line-height: 1;">👸</span>
                        </label>
                        <!-- Person with Specs -->
                        <label class="icon-option icon-specs <?php echo (isset($profile['profile_photo']) && $profile['profile_photo'] === 'specs') ? 'selected' : ''; ?>">
                            <input type="radio" name="profile_icon" value="specs" <?php echo (isset($profile['profile_photo']) && $profile['profile_photo'] === 'specs') ? 'checked' : ''; ?>>
                            <span style="display: block; font-size: inherit; line-height: 1;">👨‍💼</span>
                        </label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="phone_pay">Phone Pay Number</label>
                    <input type="text" id="phone_pay" name="phone_pay" value="<?php echo htmlspecialchars($profile['phone_pay_number'] ?? ''); ?>" placeholder="Enter Phone Pay number">
                </div>
                
                <div class="form-group">
                    <label for="google_pay">Google Pay Number</label>
                    <input type="text" id="google_pay" name="google_pay" value="<?php echo htmlspecialchars($profile['google_pay_number'] ?? ''); ?>" placeholder="Enter Google Pay number">
                </div>
                
                <div class="form-group">
                    <label for="state">State</label>
                    <select id="state" name="state">
                        <option value="">Select State</option>
                        <?php foreach ($indian_states as $state_option): ?>
                            <option value="<?php echo htmlspecialchars($state_option); ?>" <?php echo (isset($profile['state']) && $profile['state'] === $state_option) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($state_option); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="bio">Bio</label>
                    <textarea id="bio" name="bio" placeholder="Tell us about yourself..."><?php echo htmlspecialchars($profile['bio'] ?? ''); ?></textarea>
                </div>
                
                <button type="submit" class="submit-btn">Save Profile</button>
            </form>
        </div>
    </div>

    <script>
        // Icon mapping
        const iconMap = {
            'boy1': { emoji: '👨', class: 'icon-boy1' },
            'girl1': { emoji: '👩', class: 'icon-girl1' },
            'beard': { emoji: '🧔', class: 'icon-beard' },
            'bald': { emoji: '👨‍🦲', class: 'icon-bald' },
            'fashion': { emoji: '👸', class: 'icon-fashion' },
            'specs': { emoji: '👨‍💼', class: 'icon-specs' }
        };
        
        // Icon selection handler
        const profileIconDisplay = document.getElementById('profileIconDisplay');
        document.querySelectorAll('.icon-option input[type="radio"]').forEach(function(radio) {
            radio.addEventListener('change', function() {
                // Update selected state
                document.querySelectorAll('.icon-option').forEach(function(option) {
                    option.classList.remove('selected');
                });
                if (this.checked) {
                    this.closest('.icon-option').classList.add('selected');
                    
                    // Update profile display icon
                    const iconValue = this.value;
                    if (iconMap[iconValue]) {
                        // Remove all icon classes
                        profileIconDisplay.className = 'profile-icon-display';
                        // Add new icon class
                        profileIconDisplay.classList.add(iconMap[iconValue].class);
                        // Update emoji
                        profileIconDisplay.textContent = iconMap[iconValue].emoji;
                    }
                }
            });
        });
        
        // Prevent form resubmission on page refresh
        if ( window.history.replaceState )
        {
            window.history.replaceState( null, null, window.location.href);
        }
    </script>
</body>
</html>
