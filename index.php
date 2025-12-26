<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="robots" content="noindex, nofollow">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#0a0a0f">
    <title>Space Boom Play - Select Your Game</title>
    <link rel="icon" type="image/svg+xml" href="logo.svg">
    
    <!-- Google Fonts - Space Theme -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome for WhatsApp Icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="css/index.css">
</head>
<body class="no-select" oncontextmenu="return false;">
    <!-- Space Background -->
    <div id="space-background"></div>

    <!-- Account Deleted Message -->
    <?php if (isset($_GET['account_deleted']) && $_GET['account_deleted'] == '1'): ?>
    <div id="accountDeletedMessage" style="position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 10001; background: rgba(0, 255, 0, 0.2); border: 2px solid #00ff00; color: #00ff00; padding: 15px 25px; border-radius: 10px; font-weight: 700; text-align: center; max-width: 500px; box-shadow: 0 5px 20px rgba(0, 255, 0, 0.5);">
        ✓ Your account has been permanently deleted. Thank you for using Space Boom Play!
    </div>
    <script>
        // Remove URL parameter after showing message
        if (window.history.replaceState) {
            const url = new URL(window.location);
            url.searchParams.delete('account_deleted');
            window.history.replaceState({}, '', url);
        }
        
        // Hide message after 5 seconds
        setTimeout(function() {
            const msg = document.getElementById('accountDeletedMessage');
            if (msg) {
                msg.style.opacity = '0';
                msg.style.transition = 'opacity 0.5s ease';
                setTimeout(function() {
                    msg.style.display = 'none';
                }, 500);
            }
        }, 5000);
    </script>
    <?php endif; ?>

    <!-- Loading Screen -->
    <div class="loading" id="loading">
        <div class="spinner"></div>
        <div class="loading-text">LOADING GAME...</div>
    </div>

    <!-- User Info Bar (shown when logged in) -->
    <div class="user-info-bar hidden" id="userInfoBar">
        <div class="user-welcome">Welcome, <span id="displayUsername"></span></div>
        <div class="user-referral-code" id="userReferralCode" style="display: none;" onclick="toggleReferralDropdown(event)" title="Your Referral Code">
            <span class="referral-icon">🎁</span>
            <span class="referral-code-value" id="referralCodeValue">----</span>
            <!-- Referral Code Dropdown -->
            <div class="referral-dropdown" id="referralDropdown">
                <div class="referral-section">
                    <div class="referral-section-title">🎁 Your Referral Code</div>
                    <div class="referral-code-display">
                        <div class="referral-code-box" id="referralCodeBox">
                            <span id="referralCodeText">----</span>
                            <button class="copy-referral-btn" onclick="copyReferralCode(event)" title="Copy Code">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                        <div class="referral-message">
                            <p>💰 Share your code with friends and earn <strong>10% credits</strong> when they purchase credits for the first time!</p>
                            <p style="margin-top: 8px; font-size: 0.85rem; color: rgba(255, 255, 255, 0.7);">
                                They can enter your code during registration.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="user-credits" id="userCredits" style="display: none;" onclick="toggleCreditsDropdown(event)" title="Credits">
            <span class="power-icon">⚡</span>
            <span class="user-credits-value" id="creditsValue">0</span>
            <!-- Credits Dropdown -->
            <div class="credits-dropdown" id="creditsDropdown">
                <!-- Credit Timing Notice -->
                <div id="creditTimingNotice" class="credit-timing-notice" style="display: none;">
                    <div class="timing-notice-content">
                        <div class="timing-icon">⏰</div>
                        <div class="timing-text">
                            <div class="timing-title" id="timingTitle">Credits Available</div>
                            <div class="timing-message" id="timingMessage"></div>
                            <div class="timing-info">
                                <div class="timing-item">
                                    <span class="timing-label">Buy:</span>
                                    <span class="timing-countdown" id="addTimingCountdown">--</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Times Up Message -->
                <div id="timesUpMessage" class="times-up-message" style="display: none;">
                    <div class="times-up-content">
                        <div class="times-up-icon">⏰</div>
                        <div class="times-up-text">
                            <div class="times-up-title">Times Up!</div>
                            <div class="times-up-desc">Credit purchase period has ended. Claim credits is always available.</div>
                        </div>
                    </div>
                </div>
                
                <!-- Credit Limit Notice (shown when sale_mode is 'limit') -->
                <div id="creditLimitNotice" class="credit-timing-notice" style="display: none;">
                    <div class="timing-notice-content">
                        <div class="timing-icon">📊</div>
                        <div class="timing-text">
                            <div class="timing-title" id="limitTitle">Limited Credits</div>
                            <div class="timing-message" id="limitMessage">Limited credits available for sale</div>
                        </div>
                    </div>
                </div>
                
                <!-- Credits Options (Claim Credits is always available) -->
                <div id="creditsOptionsWrapper">
                    <div id="creditsOptionsContainer">
                        <!-- Credit packages will be loaded dynamically here -->
                        <div style="text-align: center; padding: 20px; color: rgba(0, 255, 255, 0.6);">Loading credit packages...</div>
                    </div>
                    <button class="add-credits-btn" onclick="showQRCode(event)" disabled id="addCreditsBtn">Add Credits</button>
                    <button class="claim-credits-btn" onclick="checkClaimTimingAndOpen()" id="claimCreditsBtn" style="display: block !important;">Claim Credits</button>
                </div>
            </div>
        </div>
        <div class="user-rank" id="userRank" style="display: none;" onclick="toggleRankDropdown(event)">
            <span class="user-rank-label">Your Rank:</span>
            <span class="user-rank-value" id="rankValue">-</span>
            <!-- Rank Dropdown -->
            <div class="rank-dropdown" id="rankDropdown">
                <div class="rank-section">
                    <div class="rank-section-title">🏆 Your Ranking</div>
                    <div id="userRankDisplay" class="no-rank-message">Loading...</div>
                </div>
                <div class="rank-section">
                    <div class="rank-section-title">📊 Top 10 Gamers</div>
                    <div class="leaderboard-list" id="leaderboardList">
                        <div class="no-rank-message">Loading...</div>
                    </div>
                </div>
            </div>
        </div>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

    <!-- Profile Planet Button -->
    <a href="view_profile.php" class="profile-planet-btn hidden" id="profilePlanetBtn" title="View Profile">
        <span class="profile-icon" id="profileIcon">🌍</span>
        <span class="profile-text">Profile</span>
    </a>

    <!-- Auth Buttons (shown when not logged in) -->
    <div class="auth-buttons" id="authButtons">
        <button class="auth-btn" id="loginBtn" onclick="if(typeof openModal==='function'){openModal('login');}else if(window.openModal){window.openModal('login');}else{const m=document.getElementById('loginModal');if(m){m.classList.add('show');document.body.style.overflow='hidden';}}">Login</button>
        <button class="auth-btn" id="registerBtn" onclick="if(typeof openModal==='function'){openModal('register');}else if(window.openModal){window.openModal('register');}else{const m=document.getElementById('registerModal');if(m){m.classList.add('show');document.body.style.overflow='hidden';}}">Register</button>
    </div>

    <!-- Registration Modal -->
    <div class="modal-overlay" id="registerModal">
        <div class="auth-modal">
            <button class="close-modal" onclick="closeModal('register')">&times;</button>
            <div class="modal-header">
                <h2>Create Account</h2>
                <p>Join the Space Boom Play</p>
            </div>
            <form class="auth-form" id="registerForm" onsubmit="handleRegister(event)">
                <div class="error-message" id="registerError"></div>
                <div class="success-message" id="registerSuccess"></div>
                
                <div class="form-group">
                    <label for="regUsername">Username</label>
                    <input type="text" id="regUsername" name="username" placeholder="Enter username" required 
                           pattern="[a-zA-Z0-9_]{3,50}" title="3-50 characters, letters, numbers, and underscores only">
                </div>

                <div class="form-group">
                    <label for="regMobile">Mobile Number</label>
                    <input type="tel" id="regMobile" name="mobile" placeholder="Enter mobile number" required 
                           pattern="[0-9]{10,15}" title="10-15 digits only">
                </div>

                <div class="form-group">
                    <label for="regPassword">Password</label>
                    <input type="password" id="regPassword" name="password" placeholder="Enter password" required 
                           minlength="6" title="Minimum 6 characters">
                </div>

                <div class="form-group">
                    <label for="regConfirmPassword">Re-enter Password</label>
                    <input type="password" id="regConfirmPassword" name="confirm_password" placeholder="Re-enter password" required 
                           minlength="6">
                </div>
                
                <div class="form-group">
                    <label for="referralCode">Referral Code (Optional)</label>
                    <input type="text" id="referralCode" name="referral_code" placeholder="Enter friend's referral code" 
                           maxlength="4" pattern="[A-Z0-9]{4}" title="Enter 4 alphanumeric characters"
                           style="text-transform: uppercase;">
                    <small style="color: rgba(0, 255, 255, 0.7); font-size: 0.8rem; display: block; margin-top: 5px;">
                        💰 Earn 10% credits when your friend purchases credits for the first time!
                    </small>
                </div>
                
                <div class="form-group" style="margin-top: 20px;">
                    <style>
                        .custom-checkbox-wrapper {
                            position: relative;
                            display: flex;
                            align-items: flex-start;
                            cursor: pointer;
                            font-size: 0.9rem;
                        }
                        
                        .custom-checkbox-wrapper input[type="checkbox"] {
                            position: absolute;
                            opacity: 0;
                            cursor: pointer;
                            height: 0;
                            width: 0;
                        }
                        
                        .custom-checkbox {
                            position: relative;
                            display: inline-block;
                            width: 22px;
                            height: 22px;
                            min-width: 22px;
                            min-height: 22px;
                            margin-right: 12px;
                            margin-top: 2px;
                            border: 2px solid var(--primary-cyan);
                            border-radius: 50%;
                            background: transparent;
                            transition: all 0.3s ease;
                            box-shadow: 0 0 10px rgba(0, 255, 255, 0.3);
                        }
                        
                        .custom-checkbox-wrapper:hover .custom-checkbox {
                            box-shadow: 0 0 15px rgba(0, 255, 255, 0.5);
                            border-color: var(--primary-purple);
                        }
                        
                        .custom-checkbox-wrapper input[type="checkbox"]:checked ~ .custom-checkbox {
                            background: var(--primary-cyan);
                            border-color: var(--primary-cyan);
                            box-shadow: 0 0 20px rgba(0, 255, 255, 0.6);
                        }
                        
                        .custom-checkbox-wrapper input[type="checkbox"]:checked ~ .custom-checkbox::after {
                            content: '';
                            position: absolute;
                            left: 50%;
                            top: 50%;
                            transform: translate(-50%, -50%) rotate(45deg);
                            width: 6px;
                            height: 10px;
                            border: solid #0a0a0f;
                            border-width: 0 2px 2px 0;
                            display: block;
                        }
                        
                        .checkbox-label-text {
                            color: rgba(255, 255, 255, 0.95);
                            line-height: 1.5;
                            flex: 1;
                        }
                    </style>
                    <label class="custom-checkbox-wrapper">
                        <input type="checkbox" id="acceptTerms" name="accept_terms" required>
                        <span class="custom-checkbox"></span>
                        <span class="checkbox-label-text">
                            I have read, understood, and agree to the 
                            <a href="terms.php" target="_blank" style="color: var(--primary-cyan); text-decoration: underline; font-weight: 600;">
                                Terms and Conditions
                            </a>
                            . I acknowledge that Space Boom Play is solely a contest platform provider and I accept all risks and limitations of liability as stated in the Terms.
                        </span>
                    </label>
                    <small style="color: rgba(255, 0, 110, 0.8); font-size: 0.85rem; display: block; margin-top: 8px; margin-left: 34px;">
                        ⚠️ You must accept the Terms and Conditions to create an account.
                    </small>
                </div>

                <button type="submit" class="submit-btn" id="registerSubmitBtn">Create Account</button>
            </form>
            <div class="switch-form">
                Already have an account? <a onclick="switchToLogin()">Login here</a>
            </div>
        </div>
    </div>

    <!-- Login Modal -->
    <div class="modal-overlay" id="loginModal">
        <div class="auth-modal">
            <button class="close-modal" onclick="closeModal('login')">&times;</button>
            <div class="modal-header">
                <h2>Login</h2>
                <p>Welcome back, Space Explorer</p>
            </div>
            <form class="auth-form" id="loginForm" onsubmit="handleLogin(event)">
                <div class="error-message" id="loginError"></div>
                <div class="success-message" id="loginSuccess"></div>
                
                <div class="form-group">
                    <label for="loginUsername">Username or Mobile</label>
                    <input type="text" id="loginUsername" name="username" placeholder="Enter username or mobile" required>
                </div>

                <div class="form-group">
                    <label for="loginPassword">Password</label>
                    <input type="password" id="loginPassword" name="password" placeholder="Enter password" required>
                </div>

                <button type="submit" class="submit-btn" id="loginSubmitBtn">Login</button>
            </form>
            <div class="switch-form">
                Don't have an account? <a onclick="switchToRegister()">Register here</a>
            </div>
            <div class="switch-form" style="margin-top: 10px;">
                <a href="#" onclick="openModal('forgotPassword'); return false;" style="color: rgba(0, 255, 255, 0.7); text-decoration: underline;">Forgot Password?</a>
            </div>
        </div>
    </div>

    <!-- Forgot Password Modal -->
    <div class="modal-overlay" id="forgotPasswordModal">
        <div class="auth-modal">
            <button class="close-modal" onclick="closeModal('forgotPassword')">&times;</button>
            <div class="modal-header">
                <h2>Forgot Password</h2>
                <p>Enter your username and mobile number</p>
            </div>
            <form class="auth-form" id="forgotPasswordForm" onsubmit="handleForgotPassword(event)">
                <div class="error-message" id="forgotPasswordError"></div>
                <div class="success-message" id="forgotPasswordSuccess"></div>
                
                <div class="form-group">
                    <label for="forgotUsername">Username <span style="color: #ff0000;">*</span></label>
                    <input type="text" id="forgotUsername" name="username" placeholder="Enter your username" required>
                </div>

                <div class="form-group">
                    <label for="forgotMobile">Mobile Number <span style="color: #ff0000;">*</span></label>
                    <input type="text" id="forgotMobile" name="mobile" placeholder="Enter your mobile number" pattern="[0-9]{10,15}" required>
                    <small style="color: rgba(0, 255, 255, 0.6); font-size: 0.85rem; margin-top: 5px; display: block;">Both username and mobile number are required</small>
                </div>

                <button type="submit" class="submit-btn" id="forgotPasswordSubmitBtn">Submit Request</button>
            </form>
            <div class="switch-form">
                Remember your password? <a onclick="closeModal('forgotPassword'); openModal('login');">Login here</a>
            </div>
        </div>
    </div>

    <!-- Login Confirmation Modal -->
    <div class="modal-overlay" id="loginConfirmationModal">
        <div class="auth-modal">
            <button class="close-modal" onclick="closeModal('loginConfirmation')">&times;</button>
            <div class="modal-header">
                <h2>Already Logged In</h2>
            </div>
            <div style="padding: 20px;">
                <p id="loginConfirmationMessage" style="color: rgba(0, 255, 255, 0.9); margin-bottom: 25px; line-height: 1.6;"></p>
                <div style="display: flex; gap: 15px; justify-content: center;">
                    <button type="button" class="submit-btn" id="loginConfirmBtn" style="flex: 1; background: linear-gradient(135deg, #00ffff, #9d4edd);">Continue on This Device</button>
                    <button type="button" class="submit-btn" id="loginCancelBtn" style="flex: 1; background: rgba(0, 255, 255, 0.2); border: 2px solid #00ffff; color: #00ffff;">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- QR Code Modal -->
    <div class="modal-overlay" id="qrModal">
        <div class="qr-modal">
            <button class="close-modal" onclick="closeModal('qr')">&times;</button>
            <h3>Payment QR Code</h3>
            <div class="amount" id="qrAmount">Amount: ₹150/-</div>
            <img src="WhatsApp Image 2025-12-18 at 4.25.46 PM.jpeg" alt="Payment QR Code">
            <p style="color: rgba(0, 255, 255, 0.8); font-size: 0.9rem; margin-bottom: 15px;">Scan the QR code to make payment</p>
            
            <!-- Verification Info -->
            <div class="verification-info">
                <div class="verification-message">
                    <div class="verification-icon">⏰</div>
                    <div class="verification-text">
                        <p class="verification-main">Your credits will be added after payment is successful within <strong>120 minutes</strong> for verification.</p>
                        <p class="verification-fast">Want credits faster? Get verified in <strong>30 minutes</strong> by sending your payment transaction via WhatsApp!</p>
                        <p class="verification-whatsapp">WhatsApp verification will take before <strong>50 minutes</strong>.</p>
                    </div>
                </div>
                <a href="https://wa.me/917842108868?text=Hi,%20I%20just%20made%20a%20payment%20for%20credits.%20Please%20send%20screenshot%20transaction%20screenshot%20to%20verify%20my%20transaction." target="_blank" rel="noopener" class="whatsapp-verify-btn">
                    <i class="fab fa-whatsapp"></i>
                    <span>Send Payment Screenshot via WhatsApp</span>
                </a>
                <div class="refund-notice">
                    <div class="refund-icon">💰</div>
                    <div class="refund-text">
                        <p>If you don't get credits, please contact us via WhatsApp. Your money will be refunded within <strong>24 hours</strong>.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Claim Credits Modal -->
    <div class="modal-overlay" id="claimCreditsModal">
        <div class="claim-modal">
            <button class="close-modal" onclick="closeModal('claimCredits')">&times;</button>
            <h3>Claim Credits</h3>
            <form id="claimCreditsForm" onsubmit="handleClaimCredits(event)">
                <div class="error-message" id="claimError"></div>
                <div class="success-message" id="claimSuccess"></div>
                <div class="claim-form-group">
                    <label for="transactionCode">Transaction Code (Last 4 digits/letters)</label>
                    <input type="text" id="transactionCode" name="transaction_code" placeholder="Enter last 4 digits/letters" required maxlength="4" pattern="[A-Za-z0-9]{4}" title="Enter exactly 4 alphanumeric characters">
                </div>
                <button type="submit" class="claim-submit-btn" id="claimSubmitBtn">Submit</button>
            </form>
        </div>
    </div>

    <!-- Main Container -->
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>SPACE BOOM PLAY</h1>
            <p>Select Your Adventure</p>
        </div>

        <!-- Categories -->
        <div class="categories">
            <button class="category-btn active" data-category="all">All Games</button>
            <button class="category-btn" data-category="action">Action</button>
            <button class="category-btn" data-category="defense">Defense</button>
            <button class="category-btn" data-category="strategy">Strategy</button>
            <button class="category-btn" data-category="arcade">Arcade</button>
        </div>

        <!-- Games Sections -->
        <div class="games-section" data-category="defense">
            <h2 class="section-title">DEFENSE GAMES</h2>
            <div class="games-grid">
                <div class="game-card" data-game="earth-defender" data-type="defense" onclick="launchGame('earth-defender'); return false;" style="cursor: pointer;">
                    <!-- Countdown Timer Badge -->
                    <div class="game-countdown-badge" id="countdown-badge-earth-defender" style="display: none;">
                        <span class="countdown-icon">⏰</span>
                        <span class="countdown-text">
                            <span id="countdown-minutes-earth-defender">0</span> min
                        </span>
                    </div>
                    <div class="game-icon">🛡️</div>
                    <div class="game-title">Earth Defender</div>
                    <div class="game-description">
                        Protect Earth from incoming asteroids! Rotate the planet and shoot to defend against the cosmic threat.
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-top: 10px;">
                        <span class="game-type">Defense</span>
                        <span class="credits-badge" id="credits-badge-earth-defender">
                            <span class="power-icon">⚡</span>
                            <span id="credits-amount-earth-defender">30</span> Credits
                        </span>
                    </div>
                    <!-- Prizes Display -->
                    <div class="game-prizes" id="prizes-earth-defender" style="margin-top: 15px; display: none;">
                        <div class="prize-item">
                            <span class="prize-medal">🥇</span>
                            <span class="prize-text">1st: ₹<span id="first-prize-earth-defender">0</span></span>
                        </div>
                        <div class="prize-item">
                            <span class="prize-medal">🥈</span>
                            <span class="prize-text">2nd: ₹<span id="second-prize-earth-defender">0</span></span>
                        </div>
                        <div class="prize-item">
                            <span class="prize-medal">🥉</span>
                            <span class="prize-text">3rd: ₹<span id="third-prize-earth-defender">0</span></span>
                        </div>
                    </div>
                    <!-- Game Timing Badge -->
                    <div class="game-timing-badge" id="timing-badge-earth-defender" style="display: none;">
                        <div class="timing-item">
                            <span class="timing-label">Duration:</span>
                            <span id="timing-duration-earth-defender">-</span>
                        </div>
                        <div class="timing-item">
                            <span class="timing-label">Time:</span>
                            <span id="timing-time-earth-defender">-</span>
                        </div>
                        <div class="timing-item">
                            <span class="timing-label">Date:</span>
                            <span id="timing-date-earth-defender">-</span>
                        </div>
                    </div>
                    <button class="play-btn" onclick="launchGame('earth-defender')">Play Now</button>
                </div>
            </div>
        </div>

        <!-- Placeholder sections for future games -->
        <div class="games-section hidden" data-category="action">
            <h2 class="section-title">ACTION GAMES</h2>
            <div class="games-grid">
                <div class="coming-soon-card">
                    <div class="coming-soon-icon">⚔️</div>
                    <div class="coming-soon-text">Coming Soon</div>
                    <div class="coming-soon-subtext">Exciting action games are on the way!</div>
                </div>
            </div>
        </div>

        <div class="games-section hidden" data-category="strategy">
            <h2 class="section-title">STRATEGY GAMES</h2>
            <div class="games-grid">
                <div class="coming-soon-card">
                    <div class="coming-soon-icon">♟️</div>
                    <div class="coming-soon-text">Coming Soon</div>
                    <div class="coming-soon-subtext">Strategic challenges await you!</div>
                </div>
            </div>
        </div>

        <div class="games-section hidden" data-category="arcade">
            <h2 class="section-title">ARCADE GAMES</h2>
            <div class="games-grid">
                <div class="coming-soon-card">
                    <div class="coming-soon-icon">🎮</div>
                    <div class="coming-soon-text">Coming Soon</div>
                    <div class="coming-soon-subtext">Classic arcade fun coming your way!</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Security: Prevent common hacking attempts
        (function() {
            'use strict';
            
            // Disable F12, Ctrl+Shift+I, Ctrl+Shift+J, Ctrl+U
            document.addEventListener('keydown', function(e) {
                if (e.key === 'F12' || 
                    (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'J')) ||
                    (e.ctrlKey && e.key === 'U')) {
                    e.preventDefault();
                    return false;
                }
            });

            // Disable right-click context menu
            document.addEventListener('contextmenu', function(e) {
                e.preventDefault();
                return false;
            });

            // Prevent text selection
            document.addEventListener('selectstart', function(e) {
                e.preventDefault();
                return false;
            });

            // Obfuscate game paths (basic security)
            const gamePaths = {
                'earth-defender': 'earth-defender.php'
            };

            // Validate game name to prevent path traversal
            function sanitizeGameName(gameName) {
                const allowed = /^[a-z0-9-]+$/i;
                if (!allowed.test(gameName)) {
                    console.error('Invalid game name');
                    return null;
                }
                return gameName.toLowerCase();
            }

            // Launch game with security checks
            function launchGame(gameName) {
                const sanitized = sanitizeGameName(gameName);
                if (!sanitized || !gamePaths[sanitized]) {
                    alert('Game not found or invalid!');
                    return;
                }

                // Show loading
                const loading = document.getElementById('loading');
                loading.classList.add('show');

                // Redirect after short delay for smooth transition
                setTimeout(() => {
                    window.location.href = gamePaths[sanitized];
                }, 500);
            }

            // Make launchGame available globally
            window.launchGame = launchGame;

            // Load game credits and prizes badges
            async function loadGameCredits() {
                try {
                    const response = await fetch('get_game_credits.php');
                    const data = await response.json();
                    
                    if (data.success && data.games) {
                        // Update credits and prizes for each game
                        Object.keys(data.games).forEach(gameName => {
                            const gameData = data.games[gameName];
                            const creditsAmount = typeof gameData === 'object' ? gameData.credits_per_chance : gameData;
                            const badgeElement = document.getElementById(`credits-amount-${gameName}`);
                            if (badgeElement) {
                                badgeElement.textContent = creditsAmount;
                            }
                            
                            // Update prizes if available
                            if (typeof gameData === 'object' && gameData.first_prize !== undefined) {
                                updateGamePrizes(gameName, gameData.first_prize, gameData.second_prize, gameData.third_prize);
                            }
                        });
                    } else if (data.success && data.game_name) {
                        // Single game response
                        const badgeElement = document.getElementById(`credits-amount-${data.game_name}`);
                        if (badgeElement) {
                            badgeElement.textContent = data.credits_per_chance;
                        }
                        
                        // Update prizes if available
                        if (data.first_prize !== undefined) {
                            updateGamePrizes(data.game_name, data.first_prize, data.second_prize, data.third_prize);
                        }
                    }
                } catch (error) {
                    console.error('Error loading game credits:', error);
                }
            }

            // Update game prizes display
            function updateGamePrizes(gameName, firstPrize, secondPrize, thirdPrize) {
                const prizesContainer = document.getElementById(`prizes-${gameName}`);
                if (!prizesContainer) return;
                
                // Only show if at least one prize is set
                if (firstPrize > 0 || secondPrize > 0 || thirdPrize > 0) {
                    prizesContainer.style.display = 'flex';
                    
                    const firstPrizeEl = document.getElementById(`first-prize-${gameName}`);
                    const secondPrizeEl = document.getElementById(`second-prize-${gameName}`);
                    const thirdPrizeEl = document.getElementById(`third-prize-${gameName}`);
                    
                    if (firstPrizeEl) firstPrizeEl.textContent = Math.round(firstPrize).toLocaleString();
                    if (secondPrizeEl) secondPrizeEl.textContent = Math.round(secondPrize).toLocaleString();
                    if (thirdPrizeEl) thirdPrizeEl.textContent = Math.round(thirdPrize).toLocaleString();
                } else {
                    prizesContainer.style.display = 'none';
                }
            }

            // Countdown timer data
            let gameCountdownData = {
                'earth-defender': {
                    timeUntilStart: 0,
                    isStarted: false,
                    startTimestamp: 0,
                    intervalId: null
                }
            };
            
            // Update countdown timer
            function updateGameCountdown(gameName) {
                const countdownData = gameCountdownData[gameName];
                if (!countdownData || countdownData.isStarted) {
                    return;
                }
                
                const now = Math.floor(Date.now() / 1000);
                const timeUntilStart = countdownData.startTimestamp - now;
                
                if (timeUntilStart <= 0) {
                    // Game has started
                    countdownData.isStarted = true;
                    const badge = document.getElementById(`countdown-badge-${gameName}`);
                    if (badge) {
                        badge.style.display = 'none';
                    }
                    if (countdownData.intervalId) {
                        clearInterval(countdownData.intervalId);
                        countdownData.intervalId = null;
                    }
                    return;
                }
                
                const minutes = Math.floor(timeUntilStart / 60);
                const minutesEl = document.getElementById(`countdown-minutes-${gameName}`);
                if (minutesEl) {
                    minutesEl.textContent = minutes;
                }
            }
            
            // Load game timing badges
            async function loadGameTiming() {
                try {
                    const response = await fetch('get_game_timing.php?game=earth-defender');
                    const data = await response.json();
                    
                    if (data.success && data.has_session && data.timing) {
                        const timing = data.timing;
                        const badgeElement = document.getElementById('timing-badge-earth-defender');
                        
                        if (badgeElement) {
                            badgeElement.style.display = 'flex';
                            
                            // Update timing display
                            const durationEl = document.getElementById('timing-duration-earth-defender');
                            const timeEl = document.getElementById('timing-time-earth-defender');
                            const dateEl = document.getElementById('timing-date-earth-defender');
                            
                            if (durationEl) durationEl.textContent = timing.duration;
                            if (timeEl) timeEl.textContent = timing.time;
                            if (dateEl) dateEl.textContent = timing.date;
                        }
                        
                        // Setup countdown timer
                        if (timing.time_until_start_seconds !== undefined && timing.time_until_start_seconds > 0 && !timing.is_started) {
                            const countdownBadge = document.getElementById('countdown-badge-earth-defender');
                            if (countdownBadge) {
                                countdownBadge.style.display = 'flex';
                                
                                // Calculate start timestamp (server timestamp + time until start)
                                const serverTime = Math.floor(Date.now() / 1000);
                                const startTimestamp = serverTime + timing.time_until_start_seconds;
                                
                                // Store countdown data
                                gameCountdownData['earth-defender'] = {
                                    timeUntilStart: timing.time_until_start_seconds,
                                    isStarted: false,
                                    startTimestamp: startTimestamp,
                                    intervalId: null
                                };
                                
                                // Initial update
                                updateGameCountdown('earth-defender');
                                
                                // Update every second
                                if (gameCountdownData['earth-defender'].intervalId) {
                                    clearInterval(gameCountdownData['earth-defender'].intervalId);
                                }
                                gameCountdownData['earth-defender'].intervalId = setInterval(() => {
                                    updateGameCountdown('earth-defender');
                                }, 1000);
                            }
                        } else {
                            // Hide countdown if game has started or no time until start
                            const countdownBadge = document.getElementById('countdown-badge-earth-defender');
                            if (countdownBadge) {
                                countdownBadge.style.display = 'none';
                            }
                            // Clear interval if exists
                            if (gameCountdownData['earth-defender'] && gameCountdownData['earth-defender'].intervalId) {
                                clearInterval(gameCountdownData['earth-defender'].intervalId);
                                gameCountdownData['earth-defender'].intervalId = null;
                            }
                        }
                    } else {
                        // Hide badges if no session
                        const badgeElement = document.getElementById('timing-badge-earth-defender');
                        if (badgeElement) {
                            badgeElement.style.display = 'none';
                        }
                        const countdownBadge = document.getElementById('countdown-badge-earth-defender');
                        if (countdownBadge) {
                            countdownBadge.style.display = 'none';
                        }
                    }
                } catch (error) {
                    console.error('Error loading game timing:', error);
                }
            }

            // Load credits, prizes, and timing on page load
            loadGameCredits();
            loadGameTiming();

            // Category filtering - ensure buttons are always clickable
            function setupCategoryButtons() {
                document.querySelectorAll('.category-btn').forEach(btn => {
                    // Clone button to remove any existing listeners
                    const newBtn = btn.cloneNode(true);
                    btn.parentNode.replaceChild(newBtn, btn);
                    
                    // Click handler
                    newBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        
                        const category = this.dataset.category;
                        
                        // Update active button
                        document.querySelectorAll('.category-btn').forEach(b => b.classList.remove('active'));
                        this.classList.add('active');

                        // Show/hide game sections
                        document.querySelectorAll('.games-section').forEach(section => {
                            if (category === 'all' || section.dataset.category === category) {
                                section.classList.remove('hidden');
                            } else {
                                section.classList.add('hidden');
                            }
                        });
                        
                        return false;
                    }, true); // Capture phase
                    
                    // Touch handler for mobile
                    newBtn.addEventListener('touchstart', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        
                        const category = this.dataset.category;
                        
                        // Update active button
                        document.querySelectorAll('.category-btn').forEach(b => b.classList.remove('active'));
                        this.classList.add('active');

                        // Show/hide game sections
                        document.querySelectorAll('.games-section').forEach(section => {
                            if (category === 'all' || section.dataset.category === category) {
                                section.classList.remove('hidden');
                            } else {
                                section.classList.add('hidden');
                            }
                        });
                        
                        return false;
                    }, { passive: false, capture: true });
                });
            }
            
            // Setup category buttons immediately
            setupCategoryButtons();
            
            // Also setup after a short delay to ensure DOM is ready
            setTimeout(setupCategoryButtons, 100);

            // Mobile detection and optimization
            const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
            const screenWidth = window.innerWidth;
            const isLowEndDevice = navigator.hardwareConcurrency && navigator.hardwareConcurrency < 4;

            // Create animated stars with mobile optimization
            function createStars() {
                const spaceBg = document.getElementById('space-background');
                
                // Adaptive star count based on device
                let starCount;
                if (screenWidth < 480) {
                    starCount = 50; // Very small phones
                } else if (screenWidth < 768) {
                    starCount = 80; // Small phones
                } else if (screenWidth < 1024) {
                    starCount = 120; // Tablets
                } else {
                    starCount = 200; // Desktop
                }

                // Further reduce for low-end devices
                if (isLowEndDevice) {
                    starCount = Math.floor(starCount * 0.6);
                }

                for (let i = 0; i < starCount; i++) {
                    const star = document.createElement('div');
                    star.className = 'star';
                    star.style.left = Math.random() * 100 + '%';
                    star.style.top = Math.random() * 100 + '%';
                    star.style.animationDelay = Math.random() * 3 + 's';
                    star.style.animationDuration = (Math.random() * 2 + 2) + 's';
                    spaceBg.appendChild(star);
                }

                // Create shooting stars occasionally (disabled on very small screens)
                if (screenWidth >= 480) {
                    setInterval(() => {
                        if (Math.random() > 0.7 && !isLowEndDevice) {
                            const shootingStar = document.createElement('div');
                            shootingStar.className = 'shooting-star';
                            shootingStar.style.left = Math.random() * 100 + '%';
                            shootingStar.style.top = '-100px';
                            shootingStar.style.animationDelay = '0s';
                            spaceBg.appendChild(shootingStar);
                            
                            setTimeout(() => {
                                shootingStar.remove();
                            }, 3000);
                        }
                    }, 5000);
                }
            }

            // Touch event optimizations for mobile
            if (isTouchDevice) {
                // Prevent double-tap zoom on iOS
                let lastTouchEnd = 0;
                document.addEventListener('touchend', function(event) {
                    const now = Date.now();
                    if (now - lastTouchEnd <= 300) {
                        event.preventDefault();
                    }
                    lastTouchEnd = now;
                }, false);

                // Add touch feedback
                document.querySelectorAll('.game-card, .category-btn, .play-btn').forEach(element => {
                    element.addEventListener('touchstart', function() {
                        this.style.transition = 'transform 0.1s';
                    });
                    element.addEventListener('touchend', function() {
                        setTimeout(() => {
                            this.style.transition = '';
                        }, 100);
                    });
                });
            }

            // Initialize on load
            window.addEventListener('load', function() {
                createStars();
                
                // Performance optimization: Reduce animations on low-end devices
                if (isLowEndDevice) {
                    document.querySelectorAll('.nebula').forEach(n => {
                        n.style.animation = 'none';
                        n.style.opacity = '0.1';
                    });
                    
                    // Reduce star animations
                    document.querySelectorAll('.star').forEach(star => {
                        star.style.animationDuration = '4s';
                    });
                }

                // Mobile-specific optimizations
                if (isMobile) {
                    // Disable hover effects on mobile
                    document.body.classList.add('mobile-device');
                    
                    // Optimize scrolling
                    document.body.style.webkitOverflowScrolling = 'touch';
                    
                    // Prevent pull-to-refresh on mobile
                    document.body.style.overscrollBehavior = 'none';
                }
            });

            // Handle resize with mobile optimization
            let resizeTimer;
            let lastWidth = window.innerWidth;
            window.addEventListener('resize', function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function() {
                    const currentWidth = window.innerWidth;
                    // Only recreate stars if width changed significantly (orientation change)
                    if (Math.abs(currentWidth - lastWidth) > 200) {
                        const spaceBg = document.getElementById('space-background');
                        spaceBg.innerHTML = '';
                        createStars();
                        lastWidth = currentWidth;
                    }
                }, 300);
            });

            // Handle orientation change on mobile
            window.addEventListener('orientationchange', function() {
                setTimeout(function() {
                    const spaceBg = document.getElementById('space-background');
                    spaceBg.innerHTML = '';
                    createStars();
                }, 100);
            });

            // Prevent drag and drop
            document.addEventListener('dragstart', function(e) {
                e.preventDefault();
            });

            // Additional security: Clear console on load
            if (typeof console !== 'undefined') {
                console.clear();
            }
        })();

        // ============================================
        // AUTHENTICATION FUNCTIONS
        // ============================================

        // Check session on page load
        let selectedCreditsAmount = 0; // Will be set when packages load
        let selectedCreditsPrice = 0; // Will be set when packages load
        let creditPackages = []; // Store loaded packages

        function checkSession() {
            fetch('check_session.php')
                .then(response => response.json())
                .then(data => {
                    if (data.logged_in) {
                        document.getElementById('userInfoBar').classList.remove('hidden');
                        document.getElementById('authButtons').classList.add('hidden');
                        document.getElementById('displayUsername').textContent = data.user.username;
                        
                        // Show profile button
                        document.getElementById('profilePlanetBtn').classList.remove('hidden');
                        
                        // Show referral code if available
                        if (data.referral_code) {
                            const referralElement = document.getElementById('userReferralCode');
                            const referralValueElement = document.getElementById('referralCodeValue');
                            const referralCodeText = document.getElementById('referralCodeText');
                            
                            referralElement.style.display = 'flex';
                            referralValueElement.textContent = data.referral_code;
                            referralCodeText.textContent = data.referral_code;
                        } else {
                            document.getElementById('userReferralCode').style.display = 'none';
                        }
                        
                        // Check if user has profile
                        fetch('check_user_profile.php')
                            .then(response => response.json())
                            .then(profileData => {
                                // Update profile button icon based on user's profile photo
                                const profileIconElement = document.getElementById('profileIcon');
                                if (profileIconElement) {
                                    const iconMap = {
                                        'boy1': '👨',
                                        'girl1': '👩',
                                        'beard': '🧔',
                                        'bald': '👨‍🦲',
                                        'fashion': '👸',
                                        'specs': '👨‍💼'
                                    };
                                    const profilePhoto = profileData.profile_photo;
                                    // Show user's profile icon if set, otherwise show default icon
                                    const iconEmoji = (profilePhoto && iconMap[profilePhoto]) ? iconMap[profilePhoto] : '🌍';
                                    profileIconElement.textContent = iconEmoji;
                                }
                                
                                if (profileData.has_profile) {
                                    // Show credits display
                                    const creditsElement = document.getElementById('userCredits');
                                    const creditsValueElement = document.getElementById('creditsValue');
                                    
                                    creditsElement.style.display = 'flex';
                                    
                                    const creditsColor = '#FFD700'; // Gold color for value
                                    const iconColor = '#00ff00'; // Green color for icon
                                    
                                    creditsElement.style.borderColor = iconColor; // Green border
                                    creditsValueElement.textContent = data.credits.toLocaleString();
                                    creditsValueElement.style.color = creditsColor; // Gold value
                                    
                                    // Ensure power icon stays green (force green color)
                                    const powerIcon = creditsElement.querySelector('.power-icon');
                                    if (powerIcon) {
                                        powerIcon.style.setProperty('color', '#00ff00', 'important');
                                    }
                                    
                                    // Show rank dropdown
                                    document.getElementById('userRank').style.display = 'flex';
                                } else {
                                    document.getElementById('userCredits').style.display = 'none';
                                    document.getElementById('userRank').style.display = 'none';
                                }
                            })
                            .catch(error => {
                                console.error('Profile check error:', error);
                            });
                    } else {
                        document.getElementById('userInfoBar').classList.add('hidden');
                        document.getElementById('authButtons').classList.remove('hidden');
                        document.getElementById('profilePlanetBtn').classList.add('hidden');
                        document.getElementById('userCredits').style.display = 'none';
                        document.getElementById('userReferralCode').style.display = 'none';
                        document.getElementById('userRank').style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Session check error:', error);
                });
        }

        // Referral dropdown functions
        function toggleReferralDropdown(event) {
            event.stopPropagation();
            const dropdown = document.getElementById('referralDropdown');
            dropdown.classList.toggle('show');
        }
        
        function copyReferralCode(event) {
            event.stopPropagation();
            const codeText = document.getElementById('referralCodeText').textContent;
            
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(codeText).then(() => {
                    const btn = event.target.closest('.copy-referral-btn');
                    const originalHTML = btn.innerHTML;
                    btn.innerHTML = '<i class="fas fa-check"></i>';
                    btn.style.background = 'rgba(81, 207, 102, 0.5)';
                    btn.style.borderColor = '#51cf66';
                    
                    setTimeout(() => {
                        btn.innerHTML = originalHTML;
                        btn.style.background = 'rgba(157, 78, 221, 0.3)';
                        btn.style.borderColor = 'var(--primary-purple)';
                    }, 2000);
                }).catch(err => {
                    console.error('Failed to copy:', err);
                    alert('Failed to copy code. Please copy manually: ' + codeText);
                });
            } else {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = codeText;
                textArea.style.position = 'fixed';
                textArea.style.opacity = '0';
                document.body.appendChild(textArea);
                textArea.select();
                try {
                    document.execCommand('copy');
                    const btn = event.target.closest('.copy-referral-btn');
                    const originalHTML = btn.innerHTML;
                    btn.innerHTML = '<i class="fas fa-check"></i>';
                    btn.style.background = 'rgba(81, 207, 102, 0.5)';
                    btn.style.borderColor = '#51cf66';
                    
                    setTimeout(() => {
                        btn.innerHTML = originalHTML;
                        btn.style.background = 'rgba(157, 78, 221, 0.3)';
                        btn.style.borderColor = 'var(--primary-purple)';
                    }, 2000);
                } catch (err) {
                    alert('Failed to copy. Please copy manually: ' + codeText);
                }
                document.body.removeChild(textArea);
            }
        }
        
        // Credits dropdown functions
        function toggleCreditsDropdown(event) {
            event.stopPropagation();
            const dropdown = document.getElementById('creditsDropdown');
            dropdown.classList.toggle('show');
            
            // Refresh timing when dropdown opens
            if (dropdown.classList.contains('show')) {
                checkCreditTiming();
            }
        }

        function selectCreditsOption(event, amount, price) {
            event.stopPropagation();
            selectedCreditsAmount = amount;
            selectedCreditsPrice = price;
            
            // Update selected state
            document.querySelectorAll('.credits-option').forEach(opt => opt.classList.remove('selected'));
            event.currentTarget.classList.add('selected');
            
            // Enable add credits button
            document.getElementById('addCreditsBtn').disabled = false;
        }

        function showQRCode(event) {
            event.stopPropagation();
            
            // Check credit sale limit first
            fetch('check_credit_sale_status.php')
                .then(response => response.json())
                .then(saleData => {
                    if (!saleData.can_buy) {
                        alert('Credit Sale Limit Reached!\n\n' + saleData.message + '\n\nPlease try again later or contact support.');
                        return;
                    }
                    
                    // If limit-based mode, skip timing check
                    if (saleData.sale_mode === 'limit') {
                        if (selectedCreditsPrice > 0) {
                            document.getElementById('qrAmount').textContent = `Amount: ₹${Math.round(selectedCreditsPrice)}/-`;
                        } else {
                            document.getElementById('qrAmount').textContent = `Amount: ₹${selectedCreditsAmount}/-`;
                        }
                        openModal('qr');
                        return;
                    }
                    
                    // Timing-based mode: Check if add credits is currently available
                    fetch('check_credit_timing.php?type=add_credits')
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.is_active) {
                                if (selectedCreditsPrice > 0) {
                                    document.getElementById('qrAmount').textContent = `Amount: ₹${Math.round(selectedCreditsPrice)}/-`;
                                } else {
                                    document.getElementById('qrAmount').textContent = `Amount: ₹${selectedCreditsAmount}/-`;
                                }
                                openModal('qr');
                            } else {
                                alert('Add Credits is not available at this time.\n\n' + (data.message || 'Please try again later.'));
                            }
                        })
                        .catch(error => {
                            console.error('Error checking timing:', error);
                            // Allow if check fails (fallback)
                            if (selectedCreditsPrice > 0) {
                                document.getElementById('qrAmount').textContent = `Amount: ₹${Math.round(selectedCreditsPrice)}/-`;
                            } else {
                                document.getElementById('qrAmount').textContent = `Amount: ₹${selectedCreditsAmount}/-`;
                            }
                            openModal('qr');
                        });
                })
                .catch(error => {
                    console.error('Error checking credit sale status:', error);
                    // Fallback: check timing only
                    fetch('check_credit_timing.php?type=add_credits')
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.is_active) {
                                if (selectedCreditsPrice > 0) {
                                    document.getElementById('qrAmount').textContent = `Amount: ₹${Math.round(selectedCreditsPrice)}/-`;
                                } else {
                                    document.getElementById('qrAmount').textContent = `Amount: ₹${selectedCreditsAmount}/-`;
                                }
                                openModal('qr');
                            } else {
                                alert('Add Credits is not available at this time.\n\n' + (data.message || 'Please try again later.'));
                            }
                        });
                });
        }
        
        function checkClaimTimingAndOpen() {
            // Claim credits is always available (no timing restrictions)
            openModal('claimCredits');
        }

        // Load credit packages from API
        function loadCreditPackages() {
            fetch('get_credit_packages.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.packages && data.packages.length > 0) {
                        creditPackages = data.packages;
                        const container = document.getElementById('creditsOptionsContainer');
                        container.innerHTML = '';
                        
                        // Find the first popular package, or first package as default
                        let defaultPackage = data.packages.find(p => p.is_popular) || data.packages[0];
                        selectedCreditsAmount = defaultPackage.credit_amount;
                        selectedCreditsPrice = defaultPackage.price;
                        
                        data.packages.forEach((package, index) => {
                            const optionDiv = document.createElement('div');
                            optionDiv.className = 'credits-option';
                            if (package.is_popular || (index === 0 && !defaultPackage.is_popular)) {
                                optionDiv.classList.add('selected');
                            }
                            
                            optionDiv.onclick = function(e) {
                                selectCreditsOption(e, package.credit_amount, package.price);
                            };
                            
                            optionDiv.innerHTML = `
                                <div class="credits-option-title">${package.credit_amount.toLocaleString()} Credits</div>
                                <div class="credits-option-price">= ₹${Math.round(package.price)}/-</div>
                                ${package.is_popular ? '<span class="popular-badge">Most Popular</span>' : ''}
                            `;
                            
                            container.appendChild(optionDiv);
                        });
                        
                        // Enable add credits button if default package is selected
                        document.getElementById('addCreditsBtn').disabled = false;
                    } else {
                        const container = document.getElementById('creditsOptionsContainer');
                        container.innerHTML = '<div style="text-align: center; padding: 20px; color: rgba(255, 0, 0, 0.6);">No credit packages available. Please contact administrator.</div>';
                    }
                })
                .catch(error => {
                    console.error('Error loading credit packages:', error);
                    const container = document.getElementById('creditsOptionsContainer');
                    container.innerHTML = '<div style="text-align: center; padding: 20px; color: rgba(255, 0, 0, 0.6);">Error loading credit packages. Please refresh the page.</div>';
                });
        }

        // Rank dropdown functions
        function toggleRankDropdown(event) {
            event.stopPropagation();
            const dropdown = document.getElementById('rankDropdown');
            dropdown.classList.toggle('show');
            if (dropdown.classList.contains('show')) {
                loadUserRank();
            }
        }

        async function loadUserRank() {
            const rankValue = document.getElementById('rankValue');
            const userRankDisplay = document.getElementById('userRankDisplay');
            const leaderboardList = document.getElementById('leaderboardList');
            
            try {
                const response = await fetch('game_api.php?action=user_rank&game=earth-defender');
                
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                
                const data = await response.json();
                
                if (data.success) {
                    const userTotalPoints = data.user_total_points || data.user_best_score || 0;
                    
                    if (data.user_rank !== null && data.user_rank !== undefined && userTotalPoints > 0) {
                        rankValue.textContent = '#' + data.user_rank;
                        userRankDisplay.innerHTML = `
                            <div class="rank-display">
                                <span class="rank-number">#${data.user_rank}</span>
                                <span class="rank-name">You</span>
                                <span class="rank-score">${userTotalPoints.toLocaleString()} Total Pts</span>
                            </div>
                        `;
                    } else {
                        rankValue.textContent = 'N/A';
                        userRankDisplay.innerHTML = '<div class="no-rank-message">Play with credits to get ranked!</div>';
                    }
                    
                    if (data.leaderboard && data.leaderboard.length > 0) {
                        const currentUserId = <?php echo isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 'null'; ?>;
                        leaderboardList.innerHTML = data.leaderboard.map((entry) => {
                            const isCurrentUser = entry.user_id == currentUserId;
                            const totalPoints = entry.total_points || entry.score || 0;
                            return `
                                <div class="leaderboard-item-rank ${isCurrentUser ? 'is-current-user' : ''}">
                                    <span class="rank-number">#${entry.rank}</span>
                                    <span class="rank-name">${entry.full_name || entry.username}</span>
                                    <span class="rank-score">${totalPoints.toLocaleString()} Total Pts</span>
                                </div>
                            `;
                        }).join('');
                    } else {
                        leaderboardList.innerHTML = '<div class="no-rank-message">No scores yet</div>';
                    }
                } else {
                    // API returned success: false
                    rankValue.textContent = 'N/A';
                    userRankDisplay.innerHTML = '<div class="no-rank-message">Unable to load rank</div>';
                    leaderboardList.innerHTML = '<div class="no-rank-message">Unable to load leaderboard</div>';
                    console.error('API Error:', data.message || 'Unknown error');
                }
            } catch (error) {
                console.error('Error loading user rank:', error);
                rankValue.textContent = 'N/A';
                userRankDisplay.innerHTML = '<div class="no-rank-message">Error loading rank</div>';
                leaderboardList.innerHTML = '<div class="no-rank-message">Error loading leaderboard</div>';
            }
        }

        // Claim credits handler
        function handleClaimCredits(e) {
            e.preventDefault();
            
            const form = e.target;
            const submitBtn = document.getElementById('claimSubmitBtn');
            const errorEl = document.getElementById('claimError');
            const successEl = document.getElementById('claimSuccess');
            
            // Claim credits is always available (no timing restrictions)
            // Proceed with claim credits
            const formData = new FormData(form);
                    
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Submitting...';
                    
                    errorEl.classList.remove('show');
                    successEl.classList.remove('show');
                    
            fetch('claim_credits.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                            successEl.textContent = data.message;
                            successEl.classList.add('show');
                            form.reset();
                            setTimeout(() => {
                                closeModal('claimCredits');
                                checkSession();
                            }, 2000);
                        } else {
                            errorEl.textContent = data.message;
                            errorEl.classList.add('show');
                        }
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Submit';
                })
                .catch(error => {
                    errorEl.textContent = 'An error occurred. Please try again.';
                    errorEl.classList.add('show');
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Submit';
                    console.error('Claim credits error:', error);
                });
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            const creditsDropdown = document.getElementById('creditsDropdown');
            const creditsElement = document.getElementById('userCredits');
            const referralDropdown = document.getElementById('referralDropdown');
            const referralElement = document.getElementById('userReferralCode');
            const rankDropdown = document.getElementById('rankDropdown');
            const rankElement = document.getElementById('userRank');
            
            if (creditsDropdown && !creditsElement.contains(event.target)) {
                creditsDropdown.classList.remove('show');
            }
            
            if (referralDropdown && !referralElement.contains(event.target)) {
                referralDropdown.classList.remove('show');
            }
            
            if (rankDropdown && !rankElement.contains(event.target)) {
                rankDropdown.classList.remove('show');
            }
        });

        // Prevent form resubmission
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }

        // Modal functions
        function openModal(type) {
            const modalMap = {
                'qr': 'qrModal',
                'claimCredits': 'claimCreditsModal',
                'login': 'loginModal',
                'register': 'registerModal',
                'loginConfirmation': 'loginConfirmationModal',
                'forgotPassword': 'forgotPasswordModal'
            };
            const modalId = modalMap[type] || (type + 'Modal');
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('show');
                document.body.style.overflow = 'hidden';
            } else {
                console.error('Modal not found:', modalId, 'for type:', type);
            }
        }
        
        // Make openModal globally accessible
        window.openModal = openModal;

        function closeModal(type) {
            const modalMap = {
                'qr': 'qrModal',
                'claimCredits': 'claimCreditsModal',
                'login': 'loginModal',
                'register': 'registerModal',
                'loginConfirmation': 'loginConfirmationModal',
                'forgotPassword': 'forgotPasswordModal'
            };
            const modalId = modalMap[type] || (type + 'Modal');
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('show');
                document.body.style.overflow = '';
                // Clear form errors
                const errorEl = document.getElementById(type + 'Error');
                const successEl = document.getElementById(type + 'Success');
                if (errorEl) errorEl.classList.remove('show');
                if (successEl) successEl.classList.remove('show');
                // Reset forms
                if (type === 'register') {
                    document.getElementById('registerForm').reset();
                } else if (type === 'login') {
                    document.getElementById('loginForm').reset();
                } else if (type === 'claimCredits') {
                    document.getElementById('claimCreditsForm').reset();
                }
            }
        }
        
        // Make closeModal globally accessible
        window.closeModal = closeModal;

        function switchToLogin() {
            closeModal('register');
            setTimeout(() => {
                openModal('login');
            }, 300);
        }

        function switchToRegister() {
            closeModal('login');
            setTimeout(() => {
                openModal('register');
            }, 300);
        }

        // Close modal when clicking outside
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('show');
                    document.body.style.overflow = '';
                }
            });
        });

        // Register handler
        function handleRegister(e) {
            e.preventDefault();
            
            const form = e.target;
            const submitBtn = document.getElementById('registerSubmitBtn');
            const errorEl = document.getElementById('registerError');
            const successEl = document.getElementById('registerSuccess');
            
            // Convert referral code to uppercase
            const referralInput = document.getElementById('referralCode');
            if (referralInput && referralInput.value) {
                referralInput.value = referralInput.value.toUpperCase();
            }
            
            // Get form data
            const formData = new FormData(form);
            
            // Disable submit button
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating...';
            
            // Clear previous messages
            errorEl.classList.remove('show');
            successEl.classList.remove('show');
            
            // Validate passwords match
            const password = formData.get('password');
            const confirmPassword = formData.get('confirm_password');
            
            if (password !== confirmPassword) {
                errorEl.textContent = 'Passwords do not match!';
                errorEl.classList.add('show');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Create Account';
                return;
            }
            
            // Validate terms acceptance
            const acceptTerms = document.getElementById('acceptTerms');
            if (!acceptTerms || !acceptTerms.checked) {
                errorEl.textContent = 'You must accept the Terms and Conditions to create an account!';
                errorEl.classList.add('show');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Create Account';
                return;
            }
            
            // Send request
            fetch('register.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Invalid JSON response:', text);
                        throw new Error('Invalid response from server');
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    successEl.textContent = data.message;
                    successEl.classList.add('show');
                    setTimeout(() => {
                        closeModal('register');
                        checkSession();
                        location.reload();
                    }, 1500);
                } else {
                    errorEl.textContent = data.message || 'Registration failed. Please try again.';
                    errorEl.classList.add('show');
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Create Account';
                }
            })
            .catch(error => {
                console.error('Registration error:', error);
                errorEl.textContent = 'An error occurred. Please try again.';
                errorEl.classList.add('show');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Create Account';
            });
        }

        // Login handler
        function handleLogin(e, forceLogin = false) {
            e.preventDefault();
            
            const form = e.target;
            const submitBtn = document.getElementById('loginSubmitBtn');
            const errorEl = document.getElementById('loginError');
            const successEl = document.getElementById('loginSuccess');
            
            // Get form data
            const formData = new FormData(form);
            
            // Add force_login parameter if confirming
            if (forceLogin) {
                formData.append('force_login', 'true');
            }
            
            // Disable submit button
            submitBtn.disabled = true;
            submitBtn.textContent = 'Logging in...';
            
            // Clear previous messages
            errorEl.classList.remove('show');
            successEl.classList.remove('show');
            
            // Send request
            fetch('login.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Invalid JSON response:', text);
                        throw new Error('Invalid response from server');
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    successEl.textContent = data.message;
                    successEl.classList.add('show');
                    setTimeout(() => {
                        closeModal('login');
                        checkSession();
                        location.reload();
                    }, 1000);
                } else if (data.requires_confirmation) {
                    // Show confirmation modal
                    showLoginConfirmationModal(data.message, form);
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Login';
                } else {
                    errorEl.textContent = data.message || 'Login failed. Please try again.';
                    errorEl.classList.add('show');
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Login';
                }
            })
            .catch(error => {
                console.error('Login error:', error);
                errorEl.textContent = 'An error occurred. Please try again.';
                errorEl.classList.add('show');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Login';
            });
        }
        
        // Show login confirmation modal
        function showLoginConfirmationModal(message, form) {
            const modal = document.getElementById('loginConfirmationModal');
            const messageEl = document.getElementById('loginConfirmationMessage');
            const confirmBtn = document.getElementById('loginConfirmBtn');
            const cancelBtn = document.getElementById('loginCancelBtn');
            
            if (modal && messageEl) {
                messageEl.textContent = message;
                modal.classList.add('show');
                
                // Store form reference for confirmation
                if (confirmBtn) {
                    confirmBtn.onclick = function() {
                        closeModal('loginConfirmation');
                        handleLogin({ preventDefault: () => {}, target: form }, true);
                    };
                }
                
                if (cancelBtn) {
                    cancelBtn.onclick = function() {
                        closeModal('loginConfirmation');
                    };
                }
            }
        }

        // Format time remaining
        function formatTimeRemaining(seconds) {
            if (!seconds || seconds <= 0) return '0 sec';
            
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const secs = seconds % 60;
            
            if (hours > 0) {
                return `${hours} hr`;
            } else if (minutes > 0) {
                return `${minutes} min ${secs} sec`;
            } else {
                return `${secs} sec`;
            }
        }
        
        // Credit sale status data
        let creditSaleStatus = null;
        
        // Update timing notice display
        function updateTimingNotice() {
            const notice = document.getElementById('creditTimingNotice');
            const limitNotice = document.getElementById('creditLimitNotice');
            const timesUpMsg = document.getElementById('timesUpMessage');
            const creditsWrapper = document.getElementById('creditsOptionsWrapper');
            const title = document.getElementById('timingTitle');
            const message = document.getElementById('timingMessage');
            const addCountdown = document.getElementById('addTimingCountdown');
            const claimCountdown = document.getElementById('claimTimingCountdown');
            
            if (!notice || !limitNotice || !timesUpMsg || !creditsWrapper) return;
            
            // Check sale mode first
            if (creditSaleStatus && creditSaleStatus.sale_mode === 'limit') {
                // Limit-based mode: Hide timing notice, show limit notice
                notice.style.display = 'none';
                timesUpMsg.style.display = 'none';
                
                // Update limit notice
                const limitTitle = document.getElementById('limitTitle');
                const limitMessage = document.getElementById('limitMessage');
                
                if (limitTitle && limitMessage) {
                    if (creditSaleStatus.remaining > 0) {
                        limitNotice.style.display = 'block';
                        limitNotice.className = 'credit-timing-notice available';
                        limitTitle.textContent = 'Limited Credits';
                        limitMessage.textContent = 'Limited credits available for sale';
                    } else {
                        limitNotice.style.display = 'block';
                        limitNotice.className = 'credit-timing-notice unavailable';
                        limitTitle.textContent = 'Limited Credits';
                        limitMessage.textContent = 'Credit sale limit reached';
                    }
                    // Always show credits wrapper - claim credits is always available
                    creditsWrapper.style.display = 'block';
                }
                return;
            }
            
            // Timing-based mode: Show timing notice, hide limit notice
            limitNotice.style.display = 'none';
            
            if (!title || !message || !addCountdown || !claimCountdown) return;
            
            // Check if we have timing data (only for add credits, claim credits is always available)
            const hasAddTiming = addCreditsTiming && addCreditsTiming.success && addCreditsTiming.from && addCreditsTiming.to;
            
            if (!hasAddTiming) {
                notice.style.display = 'none';
                timesUpMsg.style.display = 'none';
                // Always show credits wrapper - claim credits is always available
                creditsWrapper.style.display = 'block';
                return;
            }
            
            // Check if add credits timing has ended (claim credits is always available)
            const addEnded = hasAddTiming && !addCreditsTiming.is_active && !addCreditsTiming.time_until_start;
            
            if (addEnded) {
                // Show Times Up message for buy credits only, but keep claim credits available
                notice.style.display = 'none';
                timesUpMsg.style.display = 'block';
                // Always show credits wrapper - claim credits is always available
                creditsWrapper.style.display = 'block';
                // Ensure claim credits button is always visible and enabled
                const claimBtn = document.getElementById('claimCreditsBtn');
                if (claimBtn) {
                    claimBtn.style.display = 'block';
                    claimBtn.disabled = false;
                    claimBtn.style.opacity = '1';
                    claimBtn.style.cursor = 'pointer';
                }
                return;
            }
            
            // Show normal timing notice and options
            notice.style.display = 'block';
            timesUpMsg.style.display = 'none';
            creditsWrapper.style.display = 'block';
            
            let addMessage = '';
            let claimMessage = '';
            
            // Update Add Credits timing
            if (hasAddTiming) {
                if (addCreditsTiming.is_active) {
                    addCountdown.textContent = addCreditsTiming.time_remaining ? formatTimeRemaining(addCreditsTiming.time_remaining) : 'Now';
                    addCountdown.style.color = '#51cf66';
                    addMessage = 'Available';
                } else if (addCreditsTiming.time_until_start) {
                    addCountdown.textContent = formatTimeRemaining(addCreditsTiming.time_until_start);
                    addCountdown.style.color = '#ff6b6b';
                    addMessage = 'Starts in';
                } else {
                    addCountdown.textContent = 'Ended';
                    addCountdown.style.color = '#ff6b6b';
                    addMessage = 'Ended';
                }
            } else {
                addCountdown.textContent = 'Always';
                addCountdown.style.color = '#51cf66';
                addMessage = 'Available';
            }
            
            // Update Claim Credits timing
            if (hasClaimTiming) {
                if (claimCreditsTiming.is_active) {
                    claimCountdown.textContent = claimCreditsTiming.time_remaining ? formatTimeRemaining(claimCreditsTiming.time_remaining) : 'Now';
                    claimCountdown.style.color = '#51cf66';
                    claimMessage = 'Available';
                } else if (claimCreditsTiming.time_until_start) {
                    claimCountdown.textContent = formatTimeRemaining(claimCreditsTiming.time_until_start);
                    claimCountdown.style.color = '#ff6b6b';
                    claimMessage = 'Starts in';
                } else {
                    claimCountdown.textContent = 'Ended';
                    claimCountdown.style.color = '#ff6b6b';
                    claimMessage = 'Ended';
                }
            } else {
                claimCountdown.textContent = 'Always';
                claimCountdown.style.color = '#51cf66';
                claimMessage = 'Available';
            }
            
            // Update notice style and message based on availability
            const addAvailable = hasAddTiming ? addCreditsTiming.is_active : true;
            // Claim credits is always available
            
            if (addAvailable) {
                notice.className = 'credit-timing-notice available';
                title.textContent = 'Credits Available';
                message.textContent = 'You can buy or claim credits now';
            } else {
                notice.className = 'credit-timing-notice unavailable';
                title.textContent = 'Buy Credits Unavailable';
                
                // Build message based on status (claim credits is always available)
                if (hasAddTiming && addCreditsTiming.time_until_start) {
                    message.textContent = `You can't buy credits now. Buy starts in ${formatTimeRemaining(addCreditsTiming.time_until_start)}. Claim credits is always available.`;
                } else {
                    message.textContent = 'You can\'t buy credits now. Buy timing ended. Claim credits is always available.';
                }
            }
        }
        
        // Credit timing data storage
        let addCreditsTiming = null;
        let claimCreditsTiming = null;
        let timingUpdateInterval = null;
        
        // Update countdown timers
        function updateTimingCountdowns() {
            let needsRefresh = false;
            
            // Update add credits timing
            if (addCreditsTiming && addCreditsTiming.success) {
                if (addCreditsTiming.is_active && addCreditsTiming.time_remaining) {
                    addCreditsTiming.time_remaining--;
                    if (addCreditsTiming.time_remaining <= 0) {
                        addCreditsTiming.is_active = false;
                        needsRefresh = true;
                    }
                } else if (!addCreditsTiming.is_active && addCreditsTiming.time_until_start) {
                    addCreditsTiming.time_until_start--;
                    if (addCreditsTiming.time_until_start <= 0) {
                        needsRefresh = true;
                    }
                }
            }
            
            // Claim credits timing removed - always available
            
            updateTimingNotice();
            
            if (needsRefresh) {
                checkCreditTiming(); // Refresh timing when countdown reaches zero
            }
        }
        
        // Check credit timing availability
        function checkCreditTiming() {
            let addTimingLoaded = false;
            let claimTimingLoaded = false;
            
            // Check credit sale limit first
            fetch('check_credit_sale_status.php')
                .then(response => response.json())
                .then(saleData => {
                    creditSaleStatus = saleData;
                    
                    // If limit-based mode, skip timing checks
                    if (saleData.sale_mode === 'limit') {
                        const addBtn = document.getElementById('addCreditsBtn');
                        if (addBtn) {
                            if (!saleData.can_buy) {
                                addBtn.disabled = true;
                                addBtn.style.opacity = '0.5';
                                addBtn.title = saleData.message || 'Credit sale limit reached';
                            } else {
                                addBtn.disabled = false;
                                addBtn.style.opacity = '1';
                                addBtn.title = '';
                            }
                        }
                        updateTimingNotice();
                        return;
                    }
                    
                    // Timing-based mode: Check add credits timing
                    fetch('check_credit_timing.php?type=add_credits')
                        .then(response => response.json())
                        .then(data => {
                            addCreditsTiming = data;
                            addTimingLoaded = true;
                            const addBtn = document.getElementById('addCreditsBtn');
                            if (addBtn && data.success) {
                                // Disable if timing not active OR sale limit reached
                                if (!data.is_active || !saleData.can_buy) {
                                    addBtn.disabled = true;
                                    addBtn.textContent = 'Add Credits';
                                    addBtn.style.opacity = '0.5';
                                    if (!saleData.can_buy) {
                                        addBtn.title = saleData.message || 'Credit sale limit reached';
                                    } else {
                                        addBtn.title = data.message || 'Add Credits is not available at this time';
                                    }
                                } else {
                                    addBtn.disabled = false;
                                    addBtn.textContent = 'Add Credits';
                                    addBtn.style.opacity = '1';
                                    addBtn.title = '';
                                }
                            }
                            
                            if (claimTimingLoaded) {
                                updateTimingNotice();
                            }
                        })
                        .catch(error => {
                            console.error('Error checking add credits timing:', error);
                            addTimingLoaded = true;
                            if (claimTimingLoaded) {
                                updateTimingNotice();
                            }
                        });
                })
                .catch(error => {
                    console.error('Error checking credit sale status:', error);
                    // Fallback: check timing only
                    fetch('check_credit_timing.php?type=add_credits')
                        .then(response => response.json())
                        .then(data => {
                            addCreditsTiming = data;
                            addTimingLoaded = true;
                            const addBtn = document.getElementById('addCreditsBtn');
                            if (addBtn && data.success) {
                                if (!data.is_active) {
                                    addBtn.disabled = true;
                                    addBtn.textContent = 'Add Credits';
                                    addBtn.style.opacity = '0.5';
                                    addBtn.title = data.message || 'Add Credits is not available at this time';
                                } else {
                                    addBtn.disabled = false;
                                    addBtn.textContent = 'Add Credits';
                                    addBtn.style.opacity = '1';
                                    addBtn.title = '';
                                }
                            }
                            
                            if (claimTimingLoaded) {
                                updateTimingNotice();
                            }
                        });
                });
            
            // Claim credits is always available (no timing check needed)
            claimTimingLoaded = true;
            const claimBtn = document.getElementById('claimCreditsBtn');
            if (claimBtn) {
                claimBtn.disabled = false;
                claimBtn.style.opacity = '1';
                claimBtn.style.cursor = 'pointer';
                claimBtn.style.display = 'block';
                claimBtn.textContent = 'Claim Credits';
                claimBtn.title = '';
            }
            
            if (addTimingLoaded) {
                updateTimingNotice();
            }
        }
        
        // Check session on page load
        window.addEventListener('load', function() {
            checkSession();
            loadCreditPackages(); // Load credit packages on page load
            checkCreditTiming(); // Check credit timing availability
            
            // Update countdown every second
            timingUpdateInterval = setInterval(updateTimingCountdowns, 1000);
            
            // Refresh timing data every minute
            setInterval(checkCreditTiming, 60000);
            
            // Also check credit sale status periodically
            setInterval(function() {
                fetch('check_credit_sale_status.php')
                    .then(response => response.json())
                    .then(data => {
                        creditSaleStatus = data;
                        const addBtn = document.getElementById('addCreditsBtn');
                        if (addBtn) {
                            if (!data.can_buy) {
                                addBtn.disabled = true;
                                addBtn.style.opacity = '0.5';
                                addBtn.title = data.message || 'Credit sale limit reached';
                            } else if (data.sale_mode === 'limit') {
                                addBtn.disabled = false;
                                addBtn.style.opacity = '1';
                                addBtn.title = '';
                            }
                        }
                        updateTimingNotice();
                    })
                    .catch(error => console.error('Error checking credit sale status:', error));
            }, 60000);
        });
        
        // Clear interval on page unload
        window.addEventListener('beforeunload', function() {
            if (timingUpdateInterval) {
                clearInterval(timingUpdateInterval);
            }
        });

        // Make functions globally available
        window.openModal = openModal;
        window.closeModal = closeModal;
        window.switchToLogin = switchToLogin;
        window.switchToRegister = switchToRegister;
        window.handleRegister = handleRegister;
        window.handleLogin = handleLogin;
        window.handleForgotPassword = handleForgotPassword;
        window.toggleCreditsDropdown = toggleCreditsDropdown;
        window.selectCreditsOption = selectCreditsOption;
        window.showQRCode = showQRCode;
        window.toggleRankDropdown = toggleRankDropdown;
        window.handleClaimCredits = handleClaimCredits;
        
        // Forgot Password handler
        function handleForgotPassword(e) {
            e.preventDefault();
            
            const form = e.target;
            const submitBtn = document.getElementById('forgotPasswordSubmitBtn');
            const errorEl = document.getElementById('forgotPasswordError');
            const successEl = document.getElementById('forgotPasswordSuccess');
            
            // Get form data
            const formData = new FormData(form);
            
            // Disable submit button
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting...';
            
            // Clear previous messages
            errorEl.classList.remove('show');
            successEl.classList.remove('show');
            
            // Send request
            fetch('forgot_password_request.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    successEl.textContent = data.message || 'Request submitted successfully! Your password reset request has been sent to admin.';
                    successEl.classList.add('show');
                    form.reset();
                    // Auto close modal after 5 seconds
                    setTimeout(() => {
                        closeModal('forgotPassword');
                        openModal('login');
                    }, 5000);
                } else {
                    errorEl.textContent = data.message || 'An error occurred. Please try again.';
                    errorEl.classList.add('show');
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Submit Request';
                }
            })
            .catch(error => {
                errorEl.textContent = 'An error occurred. Please try again.';
                errorEl.classList.add('show');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Submit Request';
                console.error('Forgot password error:', error);
            });
        }
        
        
        // Ensure login/register buttons work even if script loads late
        document.addEventListener('DOMContentLoaded', function() {
            const loginBtn = document.getElementById('loginBtn');
            const registerBtn = document.getElementById('registerBtn');
            
            if (loginBtn) {
                loginBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    openModal('login');
                });
            }
            
            if (registerBtn) {
                registerBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    openModal('register');
                });
            }
        });
        
        // Also ensure functions work immediately (not just on DOMContentLoaded)
        // This handles cases where DOMContentLoaded already fired
        if (document.readyState === 'loading') {
            // Still loading, wait for DOMContentLoaded
        } else {
            // DOM already loaded, set up listeners immediately
            const loginBtn = document.getElementById('loginBtn');
            const registerBtn = document.getElementById('registerBtn');
            
            if (loginBtn && !loginBtn.hasAttribute('data-listener-added')) {
                loginBtn.setAttribute('data-listener-added', 'true');
                loginBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    openModal('login');
                });
            }
            
            if (registerBtn && !registerBtn.hasAttribute('data-listener-added')) {
                registerBtn.setAttribute('data-listener-added', 'true');
                registerBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    openModal('register');
                });
            }
        }
    </script>

    <!-- Footer -->
    <footer class="site-footer">
        <div class="footer-content">
            <!-- About Section -->
            <div class="footer-section">
                <h3>About Space Boom Play</h3>
                <p>Space Boom Play is your ultimate destination for exciting space-themed gaming adventures. Experience thrilling games, compete for real prizes, and join a community of passionate gamers.</p>
                <p>Play, compete, and win real cash prizes in our exciting gaming tournaments. Join thousands of players in the galaxy's most thrilling gaming experience!</p>
            </div>
            
            <!-- Features Section -->
            <div class="footer-section">
                <h3>Features</h3>
                <div class="footer-features">
                    <div class="footer-feature-item">
                        <i class="fas fa-gamepad"></i>
                        <span>Multiple Space-Themed Games</span>
                    </div>
                    <div class="footer-feature-item">
                        <i class="fas fa-trophy"></i>
                        <span>Real Cash Prize Contests</span>
                    </div>
                    <div class="footer-feature-item">
                        <i class="fas fa-coins"></i>
                        <span>Credits System</span>
                    </div>
                    <div class="footer-feature-item">
                        <i class="fas fa-users"></i>
                        <span>User Profiles & Rankings</span>
                    </div>
                    <div class="footer-feature-item">
                        <i class="fas fa-gift"></i>
                        <span>Referral Rewards Program</span>
                    </div>
                    <div class="footer-feature-item">
                        <i class="fas fa-shield-alt"></i>
                        <span>Secure Payment System</span>
                    </div>
                </div>
            </div>
            
            <!-- Quick Links Section -->
            <div class="footer-section">
                <h3>Quick Links</h3>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="view_profile.php">My Profile</a></li>
                    <li><a href="terms.php">Terms and Conditions</a></li>
                    <li><a href="#" onclick="const m=document.getElementById('loginModal');if(m){m.classList.add('show');document.body.style.overflow='hidden';}else if(typeof openModal==='function'){openModal('login');}return false;">Login</a></li>
                    <li><a href="#" onclick="const m=document.getElementById('registerModal');if(m){m.classList.add('show');document.body.style.overflow='hidden';}else if(typeof openModal==='function'){openModal('register');}return false;">Register</a></li>
                </ul>
            </div>
            
            <!-- Contact Us Section -->
            <div class="footer-section">
                <h3>Contact Us</h3>
                <p>Need help? Have questions? We're here for you 24/7!</p>
                <p style="margin-top: 10px; font-size: 0.9rem; color: rgba(0, 255, 255, 0.6);">
                    For support, payment verification, credit issues, or any queries, contact us via WhatsApp.
                </p>
                <div class="footer-whatsapp">
                    <a href="https://wa.me/917842108868" target="_blank" rel="noopener" aria-label="Contact us on WhatsApp">
                        <i class="fab fa-whatsapp"></i>
                        <span>Chat with Us on WhatsApp</span>
                    </a>
                </div>
            </div>
            
            <!-- Support & Info Section -->
            <div class="footer-section">
                <h3>Support & Information</h3>
                <p><strong>Payment Verification:</strong></p>
                <p style="font-size: 0.85rem; margin-top: 5px;">
                    • Automatic: 30-120 minutes<br>
                    • WhatsApp: 30-50 minutes<br>
                    • Refund Policy: 24 hours
                </p>
                <p style="margin-top: 15px;"><strong>Game Credits:</strong></p>
                <p style="font-size: 0.85rem; margin-top: 5px;">
                    Purchase credits to play games and compete for real cash prizes. Credits are non-refundable but can be used for all games.
                </p>
            </div>
            
            <!-- Game Categories Section -->
            <div class="footer-section">
                <h3>Game Categories</h3>
                <ul>
                    <li><a href="index.php#defense">🛡️ Defense Games</a></li>
                    <li><a href="index.php#action">⚔️ Action Games</a></li>
                    <li><a href="index.php#strategy">♟️ Strategy Games</a></li>
                    <li><a href="index.php#arcade">🎮 Arcade Games</a></li>
                </ul>
                <p style="margin-top: 15px; font-size: 0.85rem; color: rgba(0, 255, 255, 0.6);">
                    More exciting games coming soon! Stay tuned for updates.
                </p>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> Space Boom Play. All rights reserved.</p>
            <p style="margin-top: 10px; font-size: 0.85rem;">
                Experience the future of gaming | Play. Compete. Win.
            </p>
            <p style="margin-top: 10px; font-size: 0.8rem;">
                <a href="terms.php" style="color: rgba(0, 255, 255, 0.7); text-decoration: underline;">Terms and Conditions</a>
            </p>
        </div>
    </footer>
</body>
</html>