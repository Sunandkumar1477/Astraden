<?php
session_start();
require_once 'check_user_session.php';
require_once 'connection.php';

// Get user scores per game and total
$user_id = $_SESSION['user_id'];

// Get user's credits
$user_profile = $conn->query("SELECT credits FROM user_profile WHERE user_id = $user_id")->fetch_assoc();
$user_credits = intval($user_profile['credits'] ?? 0);

// Get scores per game (from actual game_leaderboard)
$scores_per_game = [];
$games_query = $conn->query("
    SELECT game_name, SUM(score) as total_score 
    FROM game_leaderboard 
    WHERE user_id = $user_id AND credits_used > 0 AND score > 0
    GROUP BY game_name
");
while ($row = $games_query->fetch_assoc()) {
    $scores_per_game[$row['game_name']] = intval($row['total_score']);
}

// Get total score across all games
$total_score_query = $conn->query("
    SELECT SUM(score) as total_score 
    FROM game_leaderboard 
    WHERE user_id = $user_id AND credits_used > 0 AND score > 0
");
$total_score_data = $total_score_query->fetch_assoc();
$total_score = intval($total_score_data['total_score'] ?? 0);

// Sync available_score with actual total score
$conn->query("UPDATE user_profile SET available_score = $total_score WHERE user_id = $user_id");

// Available score is the total score (can be used to buy credits)
$available_score = $total_score;

// Get conversion rates and claim credits settings
$conversion_rates = [];
$claim_credits_score = 0;
$rates_query = $conn->query("SELECT * FROM score_shop_settings WHERE is_active = 1");
while ($row = $rates_query->fetch_assoc()) {
    $conversion_rates[$row['game_name']] = intval($row['score_per_credit']);
    if ($row['game_name'] === 'all') {
        $claim_credits_score = intval($row['claim_credits_score'] ?? 0);
    }
}

$available_games = ['earth-defender' => '🛡️ Earth Defender'];
$default_rate = $conversion_rates['all'] ?? 100;

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="robots" content="noindex, nofollow">
    <title>Shop - Astra Den</title>
    <link rel="icon" type="image/svg+xml" href="Alogo.svg">
    <link rel="shortcut icon" type="image/svg+xml" href="Alogo.svg">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/index.css">
    <style>
        /* Simplified styles for better mobile performance */
        body {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            padding-top: 80px;
            background: #0a0a0f;
        }
        
        /* Simplified space background - static on mobile */
        #space-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            background: radial-gradient(ellipse at center, #1a1a2e 0%, #0a0a0f 100%);
            pointer-events: none;
        }
        
        /* Main shop container */
        .shop-container { 
            max-width: 1200px; 
            margin: 0 auto; 
            padding: 20px 15px;
            position: relative;
            z-index: 10;
            min-height: calc(100vh - 160px);
        }
        
        .shop-header { 
            text-align: center; 
            margin-bottom: 30px;
            position: relative;
            z-index: 10;
        }
        .shop-header h1 { 
            font-family: 'Orbitron', sans-serif; 
            color: #00ffff; 
            font-size: 2rem; 
            margin-bottom: 10px; 
        }
        .shop-header p { 
            color: rgba(255,255,255,0.8); 
            font-size: 1rem;
        }
        
        /* Score summary cards - simplified */
        .score-summary { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 15px; 
            margin-bottom: 30px;
        }
        .score-card { 
            background: rgba(15, 15, 25, 0.9); 
            border: 1px solid rgba(0, 255, 255, 0.3); 
            border-radius: 12px; 
            padding: 20px; 
            text-align: center;
        }
        .score-card h3 { 
            font-family: 'Orbitron', sans-serif; 
            color: #9d4edd; 
            font-size: 0.85rem; 
            text-transform: uppercase; 
            margin-bottom: 12px; 
            letter-spacing: 1px; 
        }
        .score-card .score-value { 
            font-size: 2rem; 
            font-weight: 900; 
            color: #fbbf24; 
            display: block;
        }
        .score-card .score-label { 
            color: rgba(255,255,255,0.7); 
            font-size: 0.85rem; 
            margin-top: 5px; 
        }
        
        /* Games scores section - simplified */
        .games-scores { 
            background: rgba(15, 15, 25, 0.9); 
            border: 1px solid rgba(0, 255, 255, 0.2); 
            border-radius: 12px; 
            padding: 20px; 
            margin-bottom: 30px;
        }
        .games-scores h2 { 
            font-family: 'Orbitron', sans-serif; 
            color: #00ffff; 
            margin-bottom: 20px; 
            font-size: 1.3rem;
        }
        .game-score-item { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            padding: 12px; 
            background: rgba(0,0,0,0.3); 
            border-radius: 8px; 
            margin-bottom: 8px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .game-score-item:last-child { 
            margin-bottom: 0; 
        }
        .game-name { 
            font-weight: 700; 
            color: white;
            font-size: 1rem;
        }
        .game-score { 
            font-size: 1.2rem; 
            font-weight: 900; 
            color: #fbbf24;
        }
        
        /* Purchase sections - simplified */
        .purchase-section { 
            background: rgba(15, 15, 25, 0.9); 
            border: 1px solid rgba(0, 255, 255, 0.2); 
            border-radius: 12px; 
            padding: 20px;
            margin-bottom: 20px;
        }
        .purchase-section h2 { 
            font-family: 'Orbitron', sans-serif; 
            color: #00ffff; 
            margin-bottom: 20px; 
            font-size: 1.3rem;
        }
        .purchase-form { 
            display: grid; 
            gap: 15px; 
        }
        .form-group { 
            margin-bottom: 15px;
        }
        .form-group label { 
            display: block; 
            color: #9d4edd; 
            font-weight: 700; 
            font-size: 0.85rem; 
            margin-bottom: 8px; 
            text-transform: uppercase; 
        }
        .form-group select, .form-group input { 
            width: 100%; 
            padding: 12px; 
            background: rgba(0,0,0,0.5); 
            border: 1px solid rgba(0,255,255,0.3); 
            border-radius: 8px; 
            color: white; 
            font-size: 1rem; 
            font-family: 'Rajdhani', sans-serif;
        }
        .form-group input[type="number"] { 
            font-size: 1.1rem; 
            font-weight: 700; 
        }
        .rate-info { 
            color: rgba(255,255,255,0.7); 
            font-size: 0.85rem; 
            margin-top: 5px; 
        }
        .purchase-summary { 
            background: rgba(0,255,255,0.1); 
            border: 1px solid #00ffff; 
            border-radius: 10px; 
            padding: 15px; 
            margin: 15px 0; 
        }
        .purchase-summary h3 { 
            font-family: 'Orbitron', sans-serif; 
            color: #00ffff; 
            margin-bottom: 12px; 
            font-size: 0.95rem; 
        }
        .summary-row { 
            display: flex; 
            justify-content: space-between; 
            margin-bottom: 8px; 
            font-size: 0.9rem;
        }
        .summary-row:last-child { 
            margin-bottom: 0; 
            border-top: 1px solid rgba(0,255,255,0.3); 
            padding-top: 8px; 
            margin-top: 8px; 
            font-weight: 900; 
            font-size: 1.1rem; 
        }
        .summary-label { 
            color: rgba(255,255,255,0.8); 
        }
        .summary-value { 
            color: #fbbf24; 
            font-weight: 700; 
        }
        .btn-purchase { 
            background: linear-gradient(135deg, #00ffff, #9d4edd); 
            border: none; 
            color: white; 
            padding: 15px; 
            border-radius: 8px; 
            font-family: 'Orbitron', sans-serif; 
            font-weight: 900; 
            font-size: 1rem; 
            cursor: pointer; 
            width: 100%; 
            transition: opacity 0.2s;
        }
        .btn-purchase:hover { 
            opacity: 0.9;
        }
        .btn-purchase:disabled { 
            opacity: 0.5; 
            cursor: not-allowed;
        }
        
        /* Messages */
        .message { 
            padding: 12px; 
            border-radius: 8px; 
            margin-bottom: 20px; 
            text-align: center; 
            font-weight: 700;
            font-size: 0.9rem;
        }
        .msg-success { 
            background: rgba(0, 255, 204, 0.2); 
            border: 1px solid #00ffcc; 
            color: #00ffcc; 
        }
        .msg-error { 
            background: rgba(255, 0, 110, 0.2); 
            border: 1px solid #ff006e; 
            color: #ff006e; 
        }
        
        /* Responsive - Mobile optimized */
        @media (max-width: 768px) {
            body {
                padding-top: 60px;
            }
            .shop-container { 
                padding: 15px 10px; 
            }
            .shop-header h1 { 
                font-size: 1.5rem; 
            }
            .shop-header p {
                font-size: 0.9rem;
            }
            .score-card .score-value { 
                font-size: 1.8rem; 
            }
            .score-summary {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            .games-scores,
            .purchase-section {
                padding: 15px;
            }
            .games-scores h2,
            .purchase-section h2 {
                font-size: 1.1rem;
                margin-bottom: 15px;
            }
            .game-score-item {
                padding: 10px;
            }
            .game-name,
            .game-score {
                font-size: 0.9rem;
            }
            .form-group select,
            .form-group input {
                padding: 10px;
                font-size: 0.95rem;
            }
            .btn-purchase {
                padding: 12px;
                font-size: 0.95rem;
            }
        }
        
        /* Disable animations on mobile for performance */
        @media (max-width: 768px) {
            .star,
            .shooting-star {
                display: none !important;
            }
            #space-background {
                background: #0a0a0f !important;
            }
        }
    </style>
</head>
<body class="no-select" oncontextmenu="return false;">
    <!-- Space Background -->
    <div id="space-background"></div>
    
    <!-- User Info Bar (shown when logged in) -->
    <div class="user-info-bar hidden" id="userInfoBar">
        <!-- Mobile Dropdown Button (shown only on mobile) -->
        <div class="user-info-container mobile-only">
            <button class="user-dropdown-btn" id="mobileUserDropdownBtn" onclick="toggleMobileUserDropdown(event)">
                <div class="user-btn-content">
                    <span class="user-label">User</span>
                    <span class="username-display" id="mobileDisplayUsername">User</span>
                </div>
                <span class="dropdown-arrow">▼</span>
            </button>
            <div class="user-dropdown-content" id="mobileUserDropdown">
                <div class="dropdown-header">
                    <div class="user-profile-summary">
                        <div class="user-profile-icon" id="mobileUserProfileIcon">👤</div>
                        <div>
                            <div class="user-profile-name" id="dropdownDisplayUsername">User</div>
                        </div>
                    </div>
                </div>
                <div class="dropdown-body">
                    <!-- Credits Info -->
                    <div class="menu-item" id="mobileCreditsItem" style="display: none;" onclick="toggleMobileCreditsDropdown(event)">
                        <div class="item-icon">⚡</div>
                        <div class="item-info">
                            <div class="item-label">Credits</div>
                            <div class="item-value" id="mobileCreditsValue" style="color: #FFD700;">0</div>
                        </div>
                    </div>
                    <!-- Shop Link -->
                    <a href="shop.php" class="menu-item">
                        <div class="item-icon">🛒</div>
                        <div class="item-info">
                            <div class="item-label">Shop</div>
                            <div class="item-value">Score: <span id="mobileShopScore"><?php echo number_format($total_score); ?></span></div>
                        </div>
                    </a>
                    <!-- Profile Link -->
                    <a href="view_profile.php" class="menu-item">
                        <div class="item-icon">🌍</div>
                        <div class="item-info">
                            <div class="item-label">Profile</div>
                            <div class="item-value">View Profile</div>
                        </div>
                    </a>
                    <!-- Logout Link -->
                    <a href="logout.php" class="logout-link">Logout</a>
                </div>
            </div>
        </div>
        
        <!-- Desktop User Info (hidden on mobile) -->
        <div class="desktop-user-info">
            <div class="user-welcome">Welcome, <span id="displayUsername"></span></div>
            <a href="shop.php" class="shop-btn-desktop" style="display: none;" id="shopBtnDesktop" title="Shop - Total Score: <?php echo number_format($total_score); ?>">
                <i class="fas fa-store"></i> Shop (<span id="shopBtnScore"><?php echo number_format($total_score); ?></span>)
            </a>
            <div class="user-referral-code" id="userReferralCode" style="display: none;" onclick="toggleReferralDropdown(event)" title="Your Referral Code">
                <span class="referral-icon">🎁</span>
                <span class="referral-code-value" id="referralCodeValue">----</span>
            </div>
            <div class="user-credits" id="userCredits" style="display: none;" onclick="toggleCreditsDropdown(event)" title="Credits">
                <span class="power-icon">⚡</span>
                <span class="user-credits-value" id="creditsValue">0</span>
            </div>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
    
    <div class="shop-container">
        <div class="shop-header">
            <h1><i class="fas fa-store"></i> SHOP</h1>
            <p>Convert your game scores into credits!</p>
        </div>
        
        <?php if(isset($_GET['success'])): ?>
            <div class="message msg-success">✓ Credits purchased successfully!</div>
        <?php endif; ?>
        <?php if(isset($_GET['error'])): ?>
            <div class="message msg-error">✗ <?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>
        
        <div class="score-summary">
            <div class="score-card">
                <h3>Total Score</h3>
                <div class="score-value"><?php echo number_format($total_score); ?></div>
                <div class="score-label">Across All Games</div>
            </div>
            <div class="score-card">
                <h3>Available Score</h3>
                <div class="score-value"><?php echo number_format($available_score); ?></div>
                <div class="score-label">Can Be Used to Buy Credits</div>
            </div>
            <div class="score-card">
                <h3>Current Credits</h3>
                <div class="score-value" style="color: #ffd700;"><?php echo number_format($user_credits); ?></div>
                <div class="score-label">⚡ Credits</div>
            </div>
        </div>
        
        <div class="games-scores">
            <h2><i class="fas fa-gamepad"></i> Scores by Game</h2>
            <?php if(empty($scores_per_game)): ?>
                <p style="color: rgba(255,255,255,0.5); text-align: center; padding: 20px;">No scores yet. Play games to earn scores!</p>
            <?php else: ?>
                <?php foreach($scores_per_game as $game_name => $score): ?>
                    <div class="game-score-item">
                        <span class="game-name"><?php echo $available_games[$game_name] ?? ucfirst(str_replace('-', ' ', $game_name)); ?></span>
                        <span class="game-score"><?php echo number_format($score); ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="purchase-section">
            <h2><i class="fas fa-shopping-cart"></i> Buy Credits with Score</h2>
            <form id="purchaseForm" method="POST" action="purchase_credits_with_score.php">
                <div class="form-group">
                    <label>Select Game Score to Use</label>
                    <select name="game_name" id="gameSelect" required>
                        <option value="all">All Games (Total Score)</option>
                        <?php foreach($scores_per_game as $game_name => $score): ?>
                            <option value="<?php echo $game_name; ?>" data-score="<?php echo $score; ?>">
                                <?php echo $available_games[$game_name] ?? ucfirst(str_replace('-', ' ', $game_name)); ?> (<?php echo number_format($score); ?> score)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn-purchase" id="purchaseBtn" style="margin-bottom: 20px;">
                    <i class="fas fa-shopping-cart"></i> PURCHASE CREDITS
                </button>
                
                <div class="purchase-summary">
                    <h3>Purchase Summary</h3>
                    <div class="summary-row">
                        <span class="summary-label">Credits to Buy:</span>
                        <span class="summary-value" id="creditsDisplay">1 ⚡</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Score Required:</span>
                        <span class="summary-value" id="scoreRequired">-</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Available Score:</span>
                        <span class="summary-value" id="availableScoreDisplay"><?php echo number_format($available_score); ?></span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Score After Purchase:</span>
                        <span class="summary-value" id="remainingScore">-</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Amount of Credits to Buy</label>
                    <input type="number" name="credits_amount" id="creditsAmount" min="1" value="1" required>
                    <div class="rate-info" id="rateInfo">Conversion rate: Loading...</div>
                </div>
            </form>
        </div>
        
        <div style="text-align: center; margin-top: 40px;">
            <a href="index.php" style="color: #00ffff; text-decoration: none; font-weight: 700; font-family: 'Orbitron', sans-serif;">
                <i class="fas fa-arrow-left"></i> Back to Games
            </a>
        </div>
    </div>
    
    <script>
        // Initialize space background - optimized for mobile
        function createStars() {
            const spaceBg = document.getElementById('space-background');
            if (!spaceBg) return;
            
            // Check if mobile device
            const isMobile = window.innerWidth <= 768;
            
            // Clear existing stars
            spaceBg.innerHTML = '';
            
            // Only create stars on desktop, reduce count significantly
            if (!isMobile) {
                // Reduced star count for better performance
                const starCount = 30; // Reduced from 100
                for (let i = 0; i < starCount; i++) {
                    const star = document.createElement('div');
                    star.className = 'star';
                    star.style.left = Math.random() * 100 + '%';
                    star.style.top = Math.random() * 100 + '%';
                    star.style.animationDelay = Math.random() * 3 + 's';
                    spaceBg.appendChild(star);
                }
                
                // Disable shooting stars for better performance
                // Removed shooting star creation
            }
        }
        
        // Check session and show user info
        function checkSession() {
            fetch('check_session.php')
                .then(response => response.json())
                .then(data => {
                    if (data.logged_in) {
                        const userInfoBar = document.getElementById('userInfoBar');
                        if (userInfoBar) userInfoBar.classList.remove('hidden');
                        
                        const displayUsername = document.getElementById('displayUsername');
                        const mobileDisplayUsername = document.getElementById('mobileDisplayUsername');
                        const dropdownDisplayUsername = document.getElementById('dropdownDisplayUsername');
                        
                        if (displayUsername) displayUsername.textContent = data.user.username;
                        if (mobileDisplayUsername) mobileDisplayUsername.textContent = data.user.username;
                        if (dropdownDisplayUsername) dropdownDisplayUsername.textContent = data.user.username;
                        
                        // Show shop button (desktop)
                        const shopBtnDesktop = document.getElementById('shopBtnDesktop');
                        if (shopBtnDesktop) shopBtnDesktop.style.display = 'flex';
                        
                        // Show credits
                        if (data.user.credits !== undefined) {
                            const creditsValue = document.getElementById('creditsValue');
                            const mobileCreditsValue = document.getElementById('mobileCreditsValue');
                            const userCredits = document.getElementById('userCredits');
                            const mobileCreditsItem = document.getElementById('mobileCreditsItem');
                            
                            if (creditsValue) creditsValue.textContent = data.user.credits;
                            if (mobileCreditsValue) mobileCreditsValue.textContent = data.user.credits;
                            if (userCredits) userCredits.style.display = 'flex';
                            if (mobileCreditsItem) mobileCreditsItem.style.display = 'flex';
                        }
                        
                        // Show referral code if available
                        if (data.referral_code) {
                            const referralElement = document.getElementById('userReferralCode');
                            const referralValueElement = document.getElementById('referralCodeValue');
                            if (referralElement) referralElement.style.display = 'flex';
                            if (referralValueElement) referralValueElement.textContent = data.referral_code;
                        }
                    }
                })
                .catch(error => console.error('Error checking session:', error));
        }
        
        // Toggle functions for dropdowns
        function toggleMobileUserDropdown(event) {
            event.stopPropagation();
            const dropdown = document.getElementById('mobileUserDropdown');
            const btn = document.getElementById('mobileUserDropdownBtn');
            if (dropdown && btn) {
                dropdown.classList.toggle('show');
                btn.classList.toggle('active');
            }
        }
        
        function toggleCreditsDropdown(event) {
            event.stopPropagation();
            const dropdown = document.getElementById('creditsDropdown');
            if (dropdown) dropdown.classList.toggle('show');
        }
        
        function toggleReferralDropdown(event) {
            event.stopPropagation();
            const dropdown = document.getElementById('referralDropdown');
            if (dropdown) dropdown.classList.toggle('show');
        }
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.user-dropdown-btn') && !event.target.closest('.user-dropdown-content')) {
                const dropdown = document.getElementById('mobileUserDropdown');
                const btn = document.getElementById('mobileUserDropdownBtn');
                if (dropdown && btn) {
                    dropdown.classList.remove('show');
                    btn.classList.remove('active');
                }
            }
            if (!event.target.closest('.user-credits') && !event.target.closest('.credits-dropdown')) {
                const dropdown = document.getElementById('creditsDropdown');
                if (dropdown) dropdown.classList.remove('show');
            }
            if (!event.target.closest('.user-referral-code') && !event.target.closest('.referral-dropdown')) {
                const dropdown = document.getElementById('referralDropdown');
                if (dropdown) dropdown.classList.remove('show');
            }
        });
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            createStars();
            checkSession();
        });
        
        // Recreate stars on window resize (for responsive behavior)
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                createStars();
            }, 250);
        });
        
        const conversionRates = <?php echo json_encode($conversion_rates); ?>;
        const scoresPerGame = <?php echo json_encode($scores_per_game); ?>;
        const totalScore = <?php echo $total_score; ?>;
        const availableScore = <?php echo $total_score; ?>;
        const defaultRate = <?php echo $default_rate; ?>;
        
        const gameSelect = document.getElementById('gameSelect');
        const creditsAmount = document.getElementById('creditsAmount');
        const rateInfo = document.getElementById('rateInfo');
        const scoreRequired = document.getElementById('scoreRequired');
        const remainingScore = document.getElementById('remainingScore');
        const creditsDisplay = document.getElementById('creditsDisplay');
        const purchaseBtn = document.getElementById('purchaseBtn');
        const availableScoreDisplay = document.getElementById('availableScoreDisplay');
        
        function updatePurchaseSummary() {
            const selectedGame = gameSelect.value;
            const credits = parseInt(creditsAmount.value) || 0;
            const rate = conversionRates[selectedGame] || conversionRates['all'] || defaultRate;
            const requiredScore = credits * rate;
            const gameScore = selectedGame === 'all' ? totalScore : (scoresPerGame[selectedGame] || 0);
            const remaining = gameScore - requiredScore;
            
            // Update available score display based on selection
            availableScoreDisplay.textContent = numberFormat(gameScore);
            
            rateInfo.textContent = `Conversion rate: ${rate} score = 1 credit`;
            creditsDisplay.textContent = credits + ' ⚡';
            scoreRequired.textContent = numberFormat(requiredScore);
            remainingScore.textContent = numberFormat(Math.max(0, remaining));
            
            if (requiredScore > gameScore || credits <= 0) {
                purchaseBtn.disabled = true;
                purchaseBtn.style.opacity = '0.5';
            } else {
                purchaseBtn.disabled = false;
                purchaseBtn.style.opacity = '1';
            }
        }
        
        function numberFormat(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }
        
        gameSelect.addEventListener('change', updatePurchaseSummary);
        creditsAmount.addEventListener('input', updatePurchaseSummary);
        
        updatePurchaseSummary();
        
        document.getElementById('purchaseForm').addEventListener('submit', function(e) {
            const selectedGame = gameSelect.value;
            const credits = parseInt(creditsAmount.value) || 0;
            const rate = conversionRates[selectedGame] || conversionRates['all'] || defaultRate;
            const requiredScore = credits * rate;
            const gameScore = selectedGame === 'all' ? availableScore : (scoresPerGame[selectedGame] || 0);
            
            if (requiredScore > gameScore) {
                e.preventDefault();
                alert('Insufficient score! You need ' + numberFormat(requiredScore) + ' score but only have ' + numberFormat(gameScore) + '.');
                return false;
            }
            
            if (credits <= 0) {
                e.preventDefault();
                alert('Please enter a valid amount of credits to buy.');
                return false;
            }
            
            if (!confirm(`Purchase ${credits} credits for ${numberFormat(requiredScore)} score?`)) {
                e.preventDefault();
                return false;
            }
        });
    </script>
</body>
</html>

