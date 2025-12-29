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
        header('refresh:2;url=view_profile.php');
    } else {
        $error = 'Failed to update profile. Please try again.';
    }
    $stmt->close();
}

// Get user data
$profile_stmt = $conn->prepare("SELECT * FROM user_profile WHERE user_id = ?");
$profile_stmt->bind_param("i", $user_id);
$profile_stmt->execute();
$profile = $profile_stmt->get_result()->fetch_assoc();
$profile_stmt->close();

$user_stmt = $conn->prepare("SELECT username, mobile_number FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

$conn->close();

$indian_states = ['Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh', 'Goa', 'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jharkhand', 'Karnataka', 'Kerala', 'Madhya Pradesh', 'Maharashtra', 'Manipur', 'Meghalaya', 'Mizoram', 'Nagaland', 'Odisha', 'Punjab', 'Rajasthan', 'Sikkim', 'Tamil Nadu', 'Telangana', 'Tripura', 'Uttar Pradesh', 'Uttarakhand', 'West Bengal', 'Delhi', 'Jammu and Kashmir', 'Ladakh', 'Puducherry'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Edit Profile - Space Hub</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Rajdhani:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --cyan: #00ffff;
            --purple: #9d4edd;
            --gold: #FFD700;
            --bg-dark: #050508;
            --card-bg: rgba(15, 15, 25, 0.9);
            --glow: 0 0 15px rgba(0, 255, 255, 0.3);
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
            color: #fff;
            min-height: 100vh;
            padding: 10px;
            background-image: radial-gradient(circle at 50% 50%, #1a1a2e 0%, #050508 100%);
            background-attachment: fixed;
        }

        /* --- HEADER --- */
        .header {
            max-width: 1100px;
            margin: 10px auto 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background: var(--card-bg);
            border: 1px solid var(--cyan);
            border-radius: 12px;
            box-shadow: var(--glow);
        }

        .header h1 {
            font-family: 'Orbitron', sans-serif;
            font-size: clamp(1rem, 4vw, 1.4rem);
            color: var(--cyan);
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .back-btn {
            background: rgba(0, 255, 255, 0.1);
            border: 1px solid var(--cyan);
            color: var(--cyan);
            padding: 8px 15px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.8rem;
            transition: 0.3s;
        }

        /* --- LAYOUT --- */
        .main-wrapper {
            max-width: 1100px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 20px;
            padding-bottom: 40px;
        }

        /* --- LEFT SIDEBAR (Preview) --- */
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .card {
            background: var(--card-bg);
            border: 1px solid rgba(0, 255, 255, 0.2);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }

        .profile-icon-display {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            border: 3px solid var(--cyan);
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4.5rem;
            box-shadow: 0 0 20px rgba(0, 255, 255, 0.2);
            background: #111;
        }

        /* Icon Gradients */
        .icon-boy1 { background: linear-gradient(135deg, #667eea, #764ba2); }
        .icon-girl1 { background: linear-gradient(135deg, #fa709a, #fee140); }
        .icon-beard { background: linear-gradient(135deg, #4facfe, #00f2fe); }
        .icon-bald { background: linear-gradient(135deg, #43e97b, #38f9d7); }
        .icon-fashion { background: linear-gradient(135deg, #a8edea, #fed6e3); }
        .icon-specs { background: linear-gradient(135deg, #30cfd0, #330867); }

        .credits-box {
            border: 1px solid var(--gold);
            background: rgba(255, 215, 0, 0.05);
            padding: 15px;
            border-radius: 10px;
            margin: 15px 0;
        }

        .credits-value {
            font-family: 'Orbitron', sans-serif;
            font-size: 1.8rem;
            color: var(--gold);
            font-weight: 900;
        }

        /* --- RIGHT CONTENT (Form) --- */
        .content-area {
            background: var(--card-bg);
            border: 1px solid rgba(0, 255, 255, 0.2);
            border-radius: 15px;
            padding: clamp(20px, 5vw, 35px);
        }

        .content-area h2 {
            font-family: 'Orbitron', sans-serif;
            font-size: 1.2rem;
            margin-bottom: 25px;
            color: var(--purple);
            text-transform: uppercase;
            border-bottom: 1px solid rgba(157, 78, 221, 0.3);
            padding-bottom: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 0.75rem;
            text-transform: uppercase;
            color: var(--cyan);
            letter-spacing: 1px;
            margin-bottom: 8px;
            font-weight: 700;
        }

        .form-control {
            width: 100%;
            padding: 14px 16px;
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(0, 255, 255, 0.3);
            border-radius: 10px;
            color: #fff;
            font-family: inherit;
            font-size: 1rem;
            transition: 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--purple);
            box-shadow: 0 0 10px rgba(157, 78, 221, 0.4);
        }

        /* --- ICON SELECTOR --- */
        .icon-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: center;
            background: rgba(0, 0, 0, 0.3);
            padding: 15px;
            border-radius: 12px;
        }

        .icon-opt {
            cursor: pointer;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            border-radius: 10px;
            border: 2px solid transparent;
            transition: 0.2s;
        }

        .icon-opt input { display: none; }
        .icon-opt:hover { background: rgba(0, 255, 255, 0.1); transform: scale(1.1); }
        .icon-opt.selected { 
            border-color: var(--cyan); 
            background: rgba(0, 255, 255, 0.2);
            box-shadow: 0 0 10px var(--cyan);
        }

        .submit-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(90deg, var(--cyan), var(--purple));
            border: none;
            border-radius: 10px;
            color: #fff;
            font-family: 'Orbitron', sans-serif;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
            cursor: pointer;
            margin-top: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }

        .submit-btn:active { transform: scale(0.98); }

        /* --- RESPONSIVE --- */
        @media (max-width: 850px) {
            .main-wrapper {
                grid-template-columns: 1fr;
            }
            .sidebar {
                order: -1;
            }
        }

        @media (max-width: 500px) {
            body { padding: 8px; }
            .header { padding: 10px 15px; margin-bottom: 15px; }
            .content-area { padding: 20px 15px; }
            .profile-icon-display { width: 100px; height: 100px; font-size: 3rem; }
            .icon-opt { width: 44px; height: 44px; font-size: 1.5rem; }
        }

        /* Success/Error Alerts */
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 600;
        }
        .alert-success { background: rgba(16, 185, 129, 0.2); border: 1px solid #10b981; color: #10b981; }
        .alert-error { background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #ef4444; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🚀 Space Hub</h1>
        <a href="index.php" class="back-btn">Exit</a>
    </div>

    <div class="main-wrapper">
        <!-- Sidebar: Preview -->
        <div class="sidebar">
            <div class="card">
                <?php 
                $selected_icon = $profile['profile_photo'] ?? '';
                $icon_map = [
                    'boy1' => '👨', 'girl1' => '👩', 'beard' => '🧔', 
                    'bald' => '👨‍🦲', 'fashion' => '👸', 'specs' => '👨‍💼'
                ];
                $current_icon_emoji = $icon_map[$selected_icon] ?? '🌍';
                $current_icon_class = $selected_icon ? 'icon-' . $selected_icon : '';
                ?>
                <div id="previewIcon" class="profile-icon-display <?php echo $current_icon_class; ?>">
                    <?php echo $current_icon_emoji; ?>
                </div>
                
                <h3 id="previewName" style="font-family:'Orbitron'; font-size: 1.2rem; margin-bottom: 5px;">
                    <?php echo htmlspecialchars($profile['full_name'] ?? 'Recruit'); ?>
                </h3>
                <p style="font-size:0.8rem; color:var(--purple); opacity:0.8; margin-bottom:15px;">
                    @<?php echo htmlspecialchars($user['username']); ?>
                </p>

                <div class="credits-box">
                    <p style="font-size:0.7rem; text-transform:uppercase; color:var(--gold);">Stellar Credits</p>
                    <div class="credits-value"><?php echo number_format($profile['credits'] ?? 0); ?></div>
                </div>

                <div style="text-align: left; font-size: 0.85rem; border-top: 1px solid rgba(255,255,255,0.1); padding-top:15px;">
                    <p><strong>Mobile:</strong> <?php echo htmlspecialchars($user['mobile_number']); ?></p>
                    <p><strong>Region:</strong> <?php echo htmlspecialchars($profile['state'] ?? 'Unmapped'); ?></p>
                </div>
            </div>
        </div>

        <!-- Main Form -->
        <div class="content-area">
            <h2>System Profile Sync</h2>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Full Identity Name</label>
                    <input type="text" name="full_name" class="form-control" 
                           value="<?php echo htmlspecialchars($profile['full_name'] ?? ''); ?>" 
                           placeholder="Enter your legal star-name" onkeyup="syncName(this.value)">
                </div>

                <div class="form-group">
                    <label>Avatar Hologram</label>
                    <div class="icon-grid">
                        <?php foreach($icon_map as $key => $emoji): ?>
                            <label class="icon-opt <?php echo ($selected_icon === $key) ? 'selected' : ''; ?>" 
                                   onclick="updatePreview('<?php echo $key; ?>', '<?php echo $emoji; ?>')">
                                <input type="radio" name="profile_icon" value="<?php echo $key; ?>" 
                                       <?php echo ($selected_icon === $key) ? 'checked' : ''; ?>>
                                <span><?php echo $emoji; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Mobile Number</label>
                        <input type="text" name="phone_pay" class="form-control" value="<?php echo htmlspecialchars($profile['phone_pay_number'] ?? ''); ?>" placeholder="Enter communication mobile number">
                    </div>
                    <!-- <div class="form-group">
                        <label>G-Pay ID</label>
                        <input type="text" name="google_pay" class="form-control" value="<?php echo htmlspecialchars($profile['google_pay_number'] ?? ''); ?>">
                    </div> -->
                </div>

                <div class="form-group">
                    <label>Sector / State</label>
                    <select name="state" class="form-control">
                        <option value="">Select Territory</option>
                        <?php foreach ($indian_states as $s): ?>
                            <option value="<?php echo $s; ?>" <?php echo (isset($profile['state']) && $profile['state'] === $s) ? 'selected' : ''; ?>>
                                <?php echo $s; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Address</label>
                    <textarea name="bio" class="form-control" style="min-height:80px;" placeholder="Enter your address"><?php echo htmlspecialchars($profile['bio'] ?? ''); ?></textarea>
                </div>

                <button type="submit" class="submit-btn">Authorize Profile Update</button>
            </form>
        </div>
    </div>

    <script>
        function updatePreview(iconKey, emoji) {
            const display = document.getElementById('previewIcon');
            // Reset classes
            display.className = 'profile-icon-display icon-' + iconKey;
            display.textContent = emoji;

            // Highlight selected option
            document.querySelectorAll('.icon-opt').forEach(opt => opt.classList.remove('selected'));
            event.currentTarget.classList.add('selected');
        }

        function syncName(val) {
            document.getElementById('previewName').innerText = val || 'Recruit';
        }

        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>