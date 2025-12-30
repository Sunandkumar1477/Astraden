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

// Get conversion rates
$conversion_rates = [];
$rates_query = $conn->query("SELECT * FROM score_shop_settings WHERE is_active = 1");
while ($row = $rates_query->fetch_assoc()) {
    $conversion_rates[$row['game_name']] = intval($row['score_per_credit']);
}

$available_games = ['earth-defender' => '🛡️ Earth Defender'];
$default_rate = $conversion_rates['all'] ?? 100;

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Score Shop - Astra Den</title>
    <link rel="icon" type="image/svg+xml" href="Alogo.svg">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/index.css">
    <style>
        .shop-container { max-width: 1200px; margin: 0 auto; padding: 40px 20px; }
        .shop-header { text-align: center; margin-bottom: 40px; }
        .shop-header h1 { font-family: 'Orbitron', sans-serif; color: #00ffff; font-size: 2.5rem; margin-bottom: 10px; text-shadow: 0 0 20px #00ffff; }
        .shop-header p { color: rgba(255,255,255,0.6); font-size: 1.1rem; }
        
        .score-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .score-card { background: rgba(15, 15, 25, 0.95); border: 2px solid rgba(0, 255, 255, 0.3); border-radius: 20px; padding: 25px; text-align: center; }
        .score-card h3 { font-family: 'Orbitron', sans-serif; color: #9d4edd; font-size: 0.9rem; text-transform: uppercase; margin-bottom: 15px; letter-spacing: 2px; }
        .score-card .score-value { font-size: 2.5rem; font-weight: 900; color: #fbbf24; text-shadow: 0 0 20px #fbbf24; }
        .score-card .score-label { color: rgba(255,255,255,0.5); font-size: 0.9rem; margin-top: 5px; }
        
        .games-scores { background: rgba(15, 15, 25, 0.95); border: 1px solid rgba(0, 255, 255, 0.2); border-radius: 20px; padding: 30px; margin-bottom: 40px; }
        .games-scores h2 { font-family: 'Orbitron', sans-serif; color: #00ffff; margin-bottom: 25px; font-size: 1.5rem; }
        .game-score-item { display: flex; justify-content: space-between; align-items: center; padding: 15px; background: rgba(0,0,0,0.3); border-radius: 10px; margin-bottom: 10px; }
        .game-score-item:last-child { margin-bottom: 0; }
        .game-name { font-weight: 700; color: white; }
        .game-score { font-size: 1.3rem; font-weight: 900; color: #fbbf24; }
        
        .purchase-section { background: rgba(15, 15, 25, 0.95); border: 1px solid rgba(0, 255, 255, 0.2); border-radius: 20px; padding: 30px; }
        .purchase-section h2 { font-family: 'Orbitron', sans-serif; color: #00ffff; margin-bottom: 25px; font-size: 1.5rem; }
        .purchase-form { display: grid; gap: 20px; }
        .form-group { }
        .form-group label { display: block; color: #9d4edd; font-weight: 700; font-size: 0.9rem; margin-bottom: 8px; text-transform: uppercase; }
        .form-group select, .form-group input { width: 100%; padding: 15px; background: rgba(0,0,0,0.5); border: 1px solid rgba(0,255,255,0.3); border-radius: 10px; color: white; font-size: 1rem; font-family: 'Rajdhani', sans-serif; }
        .form-group input[type="number"] { font-size: 1.2rem; font-weight: 700; }
        .rate-info { color: rgba(255,255,255,0.5); font-size: 0.85rem; margin-top: 5px; }
        .purchase-summary { background: rgba(0,255,255,0.1); border: 2px solid #00ffff; border-radius: 15px; padding: 20px; margin: 20px 0; }
        .purchase-summary h3 { font-family: 'Orbitron', sans-serif; color: #00ffff; margin-bottom: 15px; font-size: 1rem; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 10px; }
        .summary-row:last-child { margin-bottom: 0; border-top: 1px solid rgba(0,255,255,0.3); padding-top: 10px; margin-top: 10px; font-weight: 900; font-size: 1.2rem; }
        .summary-label { color: rgba(255,255,255,0.7); }
        .summary-value { color: #fbbf24; font-weight: 700; }
        .btn-purchase { background: linear-gradient(135deg, #00ffff, #9d4edd); border: none; color: white; padding: 18px; border-radius: 10px; font-family: 'Orbitron', sans-serif; font-weight: 900; font-size: 1.1rem; cursor: pointer; width: 100%; transition: transform 0.2s; }
        .btn-purchase:hover { transform: scale(1.02); }
        .btn-purchase:disabled { opacity: 0.5; cursor: not-allowed; }
        
        .message { padding: 15px; border-radius: 10px; margin-bottom: 20px; text-align: center; font-weight: 700; }
        .msg-success { background: rgba(0, 255, 204, 0.1); border: 2px solid #00ffcc; color: #00ffcc; }
        .msg-error { background: rgba(255, 0, 110, 0.1); border: 2px solid #ff006e; color: #ff006e; }
        
        @media (max-width: 768px) {
            .shop-container { padding: 20px 15px; }
            .shop-header h1 { font-size: 1.8rem; }
            .score-card .score-value { font-size: 2rem; }
        }
    </style>
</head>
<body class="no-select">
    <div id="space-background"></div>
    
    <div class="shop-container">
        <div class="shop-header">
            <h1><i class="fas fa-store"></i> SCORE SHOP</h1>
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

