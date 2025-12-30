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
        /* Ensure body and content are visible */
        body {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            padding-top: 80px;
        }
        
        /* Space background should be behind everything */
        #space-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
        }
        
        /* Main shop container */
        .shop-container { 
            max-width: 1200px; 
            margin: 0 auto; 
            padding: 40px 20px;
            position: relative;
            z-index: 10;
            min-height: calc(100vh - 160px);
        }
        
        .shop-header { 
            text-align: center; 
            margin-bottom: 40px;
            position: relative;
            z-index: 10;
        }
        .shop-header h1 { 
            font-family: 'Orbitron', sans-serif; 
            color: #00ffff; 
            font-size: 2.5rem; 
            margin-bottom: 10px; 
            text-shadow: 0 0 20px #00ffff;
            position: relative;
            z-index: 10;
        }
        .shop-header p { 
            color: rgba(255,255,255,0.8); 
            font-size: 1.1rem;
            position: relative;
            z-index: 10;
        }
        
        /* Score summary cards */
        .score-summary { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
            gap: 20px; 
            margin-bottom: 40px;
            position: relative;
            z-index: 10;
        }
        .score-card { 
            background: rgba(15, 15, 25, 0.95); 
            border: 2px solid rgba(0, 255, 255, 0.3); 
            border-radius: 20px; 
            padding: 25px; 
            text-align: center;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
            position: relative;
            z-index: 10;
        }
        .score-card h3 { 
            font-family: 'Orbitron', sans-serif; 
            color: #9d4edd; 
            font-size: 0.9rem; 
            text-transform: uppercase; 
            margin-bottom: 15px; 
            letter-spacing: 2px; 
        }
        .score-card .score-value { 
            font-size: 2.5rem; 
            font-weight: 900; 
            color: #fbbf24; 
            text-shadow: 0 0 20px #fbbf24;
            display: block;
        }
        .score-card .score-label { 
            color: rgba(255,255,255,0.7); 
            font-size: 0.9rem; 
            margin-top: 5px; 
        }
        
        /* Games scores section */
        .games-scores { 
            background: rgba(15, 15, 25, 0.95); 
            border: 1px solid rgba(0, 255, 255, 0.2); 
            border-radius: 20px; 
            padding: 30px; 
            margin-bottom: 40px;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
            position: relative;
            z-index: 10;
        }
        .games-scores h2 { 
            font-family: 'Orbitron', sans-serif; 
            color: #00ffff; 
            margin-bottom: 25px; 
            font-size: 1.5rem;
            text-shadow: 0 0 10px #00ffff;
        }
        .game-score-item { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            padding: 15px; 
            background: rgba(0,0,0,0.3); 
            border-radius: 10px; 
            margin-bottom: 10px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .game-score-item:last-child { 
            margin-bottom: 0; 
        }
        .game-name { 
            font-weight: 700; 
            color: white;
            font-size: 1.1rem;
        }
        .game-score { 
            font-size: 1.3rem; 
            font-weight: 900; 
            color: #fbbf24;
            text-shadow: 0 0 10px #fbbf24;
        }
        
        /* Purchase sections */
        .purchase-section { 
            background: rgba(15, 15, 25, 0.95); 
            border: 1px solid rgba(0, 255, 255, 0.2); 
            border-radius: 20px; 
            padding: 30px;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
            position: relative;
            z-index: 10;
        }
        .purchase-section h2 { 
            font-family: 'Orbitron', sans-serif; 
            color: #00ffff; 
            margin-bottom: 25px; 
            font-size: 1.5rem;
            text-shadow: 0 0 10px #00ffff;
        }
        .purchase-form { 
            display: grid; 
            gap: 20px; 
        }
        .form-group { 
            margin-bottom: 15px;
        }
        .form-group label { 
            display: block; 
            color: #9d4edd; 
            font-weight: 700; 
            font-size: 0.9rem; 
            margin-bottom: 8px; 
            text-transform: uppercase; 
        }
        .form-group select, .form-group input { 
            width: 100%; 
            padding: 15px; 
            background: rgba(0,0,0,0.5); 
            border: 1px solid rgba(0,255,255,0.3); 
            border-radius: 10px; 
            color: white; 
            font-size: 1rem; 
            font-family: 'Rajdhani', sans-serif;
        }
        .form-group input[type="number"] { 
            font-size: 1.2rem; 
            font-weight: 700; 
        }
        .rate-info { 
            color: rgba(255,255,255,0.7); 
            font-size: 0.85rem; 
            margin-top: 5px; 
        }
        .purchase-summary { 
            background: rgba(0,255,255,0.1); 
            border: 2px solid #00ffff; 
            border-radius: 15px; 
            padding: 20px; 
            margin: 20px 0; 
        }
        .purchase-summary h3 { 
            font-family: 'Orbitron', sans-serif; 
            color: #00ffff; 
            margin-bottom: 15px; 
            font-size: 1rem; 
        }
        .summary-row { 
            display: flex; 
            justify-content: space-between; 
            margin-bottom: 10px; 
        }
        .summary-row:last-child { 
            margin-bottom: 0; 
            border-top: 1px solid rgba(0,255,255,0.3); 
            padding-top: 10px; 
            margin-top: 10px; 
            font-weight: 900; 
            font-size: 1.2rem; 
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
            padding: 18px; 
            border-radius: 10px; 
            font-family: 'Orbitron', sans-serif; 
            font-weight: 900; 
            font-size: 1.1rem; 
            cursor: pointer; 
            width: 100%; 
            transition: transform 0.2s;
            box-shadow: 0 4px 15px rgba(0, 255, 255, 0.3);
        }
        .btn-purchase:hover { 
            transform: scale(1.02);
            box-shadow: 0 6px 20px rgba(0, 255, 255, 0.5);
        }
        .btn-purchase:disabled { 
            opacity: 0.5; 
            cursor: not-allowed;
        }
        
        /* Messages */
        .message { 
            padding: 15px; 
            border-radius: 10px; 
            margin-bottom: 20px; 
            text-align: center; 
            font-weight: 700;
            position: relative;
            z-index: 10;
        }
        .msg-success { 
            background: rgba(0, 255, 204, 0.2); 
            border: 2px solid #00ffcc; 
            color: #00ffcc; 
        }
        .msg-error { 
            background: rgba(255, 0, 110, 0.2); 
            border: 2px solid #ff006e; 
            color: #ff006e; 
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            body {
                padding-top: 60px;
            }
            .shop-container { 
                padding: 20px 15px; 
            }
            .shop-header h1 { 
                font-size: 1.8rem; 
            }
            .score-card .score-value { 
                font-size: 2rem; 
            }
            .score-summary {
                grid-template-columns: 1fr;
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
        
        <!-- Claim Credits Section -->
        <div class="purchase-section" style="margin-bottom: 30px; background: linear-gradient(135deg, rgba(157, 78, 221, 0.15), rgba(0, 255, 255, 0.15)); border: 2px solid #9d4edd; box-shadow: 0 0 30px rgba(157, 78, 221, 0.3);">
            <h2><i class="fas fa-gift"></i> Claim Credits</h2>
            <?php if($claim_credits_score > 0): ?>
            <div style="padding: 20px; text-align: center;">
                <p style="color: rgba(255,255,255,0.9); margin-bottom: 25px; font-size: 1.2rem; font-weight: 700;">
                    🎁 Claim credits instantly with your score!
                </p>
                <div style="background: rgba(0,0,0,0.4); border-radius: 15px; padding: 25px; margin-bottom: 25px; border: 1px solid rgba(157, 78, 221, 0.3);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding: 10px; background: rgba(157, 78, 221, 0.1); border-radius: 10px;">
                        <span style="color: rgba(255,255,255,0.8); font-size: 1rem; font-weight: 600;">Score Required:</span>
                        <span style="color: #9d4edd; font-weight: 900; font-size: 1.8rem; text-shadow: 0 0 10px #9d4edd;"><?php echo number_format($claim_credits_score); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding: 10px; background: rgba(251, 191, 36, 0.1); border-radius: 10px;">
                        <span style="color: rgba(255,255,255,0.8); font-size: 1rem; font-weight: 600;">Your Total Score:</span>
                        <span style="color: #fbbf24; font-weight: 900; font-size: 1.8rem; text-shadow: 0 0 10px #fbbf24;"><?php echo number_format($total_score); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px; background: rgba(0, 255, 204, 0.1); border-radius: 10px;">
                        <span style="color: rgba(255,255,255,0.8); font-size: 1rem; font-weight: 600;">Credits You'll Get:</span>
                        <span style="color: #00ffcc; font-weight: 900; font-size: 1.8rem; text-shadow: 0 0 10px #00ffcc;">1 ⚡</span>
                    </div>
                </div>
                <form method="POST" action="claim_credits_with_score.php" id="claimForm">
                    <button type="submit" class="btn-purchase" style="background: linear-gradient(135deg, #9d4edd, #00ffff); font-size: 1.2rem; padding: 20px; box-shadow: 0 0 20px rgba(157, 78, 221, 0.5);" id="claimBtn" <?php echo $total_score < $claim_credits_score ? 'disabled' : ''; ?>>
                        <i class="fas fa-gift"></i> CLAIM 1 CREDIT NOW
                    </button>
                </form>
                <?php if($total_score < $claim_credits_score): ?>
                    <p style="color: #ff006e; margin-top: 20px; font-size: 1rem; font-weight: 700; padding: 15px; background: rgba(255, 0, 110, 0.1); border-radius: 10px; border: 1px solid #ff006e;">
                        ⚠️ You need <?php echo number_format($claim_credits_score - $total_score); ?> more score to claim credits. Keep playing to earn more!
                    </p>
                <?php else: ?>
                    <p style="color: #00ffcc; margin-top: 15px; font-size: 0.9rem; font-weight: 600;">
                        ✓ You have enough score! Click the button above to claim your credit.
                    </p>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div style="padding: 30px; text-align: center;">
                <p style="color: rgba(255,255,255,0.6); font-size: 1.1rem; margin-bottom: 15px;">
                    Claim credits feature is currently disabled.
                </p>
                <p style="color: rgba(255,255,255,0.4); font-size: 0.9rem;">
                    Admin needs to set the claim credits score in Score Shop Settings.
                </p>
            </div>
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
                
                <div class="form-group">
                    <label>Amount of Credits to Buy</label>
                    <input type="number" name="credits_amount" id="creditsAmount" min="1" value="1" required>
                    <div class="rate-info" id="rateInfo">Conversion rate: Loading...</div>
                </div>
                
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
                
                <button type="submit" class="btn-purchase" id="purchaseBtn">
                    <i class="fas fa-shopping-cart"></i> PURCHASE CREDITS
                </button>
            </form>
        </div>
        
        <div style="text-align: center; margin-top: 40px;">
            <a href="index.php" style="color: #00ffff; text-decoration: none; font-weight: 700; font-family: 'Orbitron', sans-serif;">
                <i class="fas fa-arrow-left"></i> Back to Games
            </a>
        </div>
    </div>
    
    <script>
        // Initialize space background
        function createStars() {
            const spaceBg = document.getElementById('space-background');
            if (!spaceBg) return;
            
            // Clear existing stars
            spaceBg.innerHTML = '';
            
            // Create stars
            for (let i = 0; i < 100; i++) {
                const star = document.createElement('div');
                star.className = 'star';
                star.style.left = Math.random() * 100 + '%';
                star.style.top = Math.random() * 100 + '%';
                star.style.animationDelay = Math.random() * 3 + 's';
                spaceBg.appendChild(star);
            }
            
            // Create shooting stars occasionally
            setInterval(() => {
                if (Math.random() > 0.7) {
                    const shootingStar = document.createElement('div');
                    shootingStar.className = 'shooting-star';
                    shootingStar.style.left = Math.random() * 100 + '%';
                    shootingStar.style.top = '-100px';
                    spaceBg.appendChild(shootingStar);
                    setTimeout(() => shootingStar.remove(), 3000);
                }
            }, 2000);
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

