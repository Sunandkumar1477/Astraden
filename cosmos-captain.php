<?php
session_start();
// Require login to play Cosmos Captain
require_once 'check_user_session.php';
require_once 'connection.php';

// Get user credits (if logged in)
$user_credits = 0;
$is_logged_in = false;
$is_contest_active = 0;
$is_claim_active = 0;
$prizes = ['1st' => 0, '2nd' => 0, '3rd' => 0];

// Credits color is always gold for all users
$credits_color = '#FFD700'; // Gold color

// Game name - cosmos-captain
$game_name = 'cosmos-captain';

// Fetch game settings
$game_stmt = $conn->prepare("SELECT is_contest_active, is_claim_active, game_mode, contest_first_prize, contest_second_prize, contest_third_prize FROM games WHERE game_name = ?");
$game_stmt->bind_param("s", $game_name);
$game_stmt->execute();
$game_res = $game_stmt->get_result();
$game_mode = 'money';
if ($game_res->num_rows > 0) {
    $g_data = $game_res->fetch_assoc();
    $is_contest_active = (int)$g_data['is_contest_active'];
    $is_claim_active = (int)$g_data['is_claim_active'];
    $game_mode = $g_data['game_mode'] ?: 'money';
    $prizes = [
        '1st' => (int)$g_data['contest_first_prize'],
        '2nd' => (int)$g_data['contest_second_prize'],
        '3rd' => (int)$g_data['contest_third_prize']
    ];
}
$game_stmt->close();

if (isset($_SESSION['user_id'])) {
    $is_logged_in = true;
    $credits_stmt = $conn->prepare("SELECT credits FROM user_profile WHERE user_id = ?");
    $credits_stmt->bind_param("i", $_SESSION['user_id']);
    $credits_stmt->execute();
    $credits_result = $credits_stmt->get_result();
    if ($credits_result->num_rows > 0) {
        $credits_data = $credits_result->fetch_assoc();
        $user_credits = $credits_data['credits'] ?? 0;
    }
    $credits_stmt->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="Alogo.svg">
    <link rel="shortcut icon" type="image/svg+xml" href="Alogo.svg">
    <title>Cosmos Captain - Space Shooter</title>
    <style>
        :root {
            --primary-glow: #00f2ff;
            --danger-glow: #ff4d4d;
            --accent-glow: #bd00ff;
            --health-color: #2ecc71;
            --special-glow: #2eff8c;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            user-select: none;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            background-color: #050505;
            color: white;
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            overflow: hidden;
            width: 100vw;
            height: 100vh;
        }

        #game-container {
            position: relative;
            width: 100%;
            height: 100%;
            cursor: crosshair;
            background: radial-gradient(circle at center, #1a1a2e 0%, #050505 100%);
            transition: background-color 0.2s;
        }

        canvas {
            display: block;
        }

        #ui-layer {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .hud-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            pointer-events: auto;
        }

        .stat-box {
            background: rgba(0, 0, 0, 0.6);
            border: 1px solid var(--primary-glow);
            padding: 10px 20px;
            border-radius: 8px;
            backdrop-filter: blur(5px);
            box-shadow: 0 0 15px rgba(0, 242, 255, 0.2);
            min-width: 120px;
        }

        .health-container {
            margin-top: 10px;
            width: 200px;
            height: 12px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 6px;
            overflow: hidden;
        }

        #health-bar {
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, #2ecc71, #27ae60);
            box-shadow: 0 0 10px var(--health-color);
            transition: width 0.3s ease;
        }

        .score-label {
            font-size: 10px;
            text-transform: uppercase;
            color: var(--primary-glow);
            letter-spacing: 1px;
        }

        .score-value {
            font-size: 24px;
            font-weight: bold;
            font-family: 'Courier New', Courier, monospace;
        }

        #message-box {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0, 0, 0, 0.85);
            padding: 30px;
            border-radius: 15px;
            border: 2px solid var(--primary-glow);
            text-align: center;
            pointer-events: auto;
            max-width: 90%;
            width: 400px;
            z-index: 100;
        }

        h1 {
            color: var(--primary-glow);
            margin-bottom: 15px;
            font-size: 28px;
            text-shadow: 0 0 10px var(--primary-glow);
        }

        p {
            margin-bottom: 20px;
            line-height: 1.5;
            color: #ccc;
        }

        button {
            background: var(--primary-glow);
            color: black;
            border: none;
            padding: 12px 30px;
            font-size: 18px;
            font-weight: bold;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.2s;
            text-transform: uppercase;
        }

        button:hover {
            transform: scale(1.05);
            box-shadow: 0 0 20px var(--primary-glow);
        }

        .instructions {
            font-size: 14px;
            margin-top: 15px;
            color: #888;
            line-height: 1.4;
        }

        .special-hint {
            color: var(--special-glow);
            font-weight: bold;
        }

        .hidden {
            display: none !important;
        }

        @keyframes damage-flash {
            0% { background-color: rgba(255, 0, 0, 0.3); }
            100% { background-color: transparent; }
        }

        .hit-effect {
            animation: damage-flash 0.3s forwards;
        }
    </style>
</head>
<body>

    <div id="game-container">
        <canvas id="gameCanvas"></canvas>

        <div id="ui-layer">
            <div class="hud-top">
                <div>
                    <div class="stat-box">
                        <div class="score-label">Hull Integrity</div>
                        <div class="health-container">
                            <div id="health-bar"></div>
                        </div>
                    </div>
                </div>
                <div style="text-align: right;">
                    <div class="stat-box">
                        <div class="score-label">Mission Score</div>
                        <div id="score" class="score-value">0000</div>
                    </div>
                    <div class="stat-box" style="margin-top: 10px; display: none;" id="total-score-box">
                        <div class="score-label">Total Score</div>
                        <div id="total-score" class="score-value" style="color: #00ff00;">0</div>
                    </div>
                    <div class="stat-box" style="margin-top: 10px;">
                        <div class="score-label">Credits</div>
                        <div id="credits-display" class="score-value" style="color: <?php echo $credits_color; ?>;">
                            ⚡ <span id="credits-value"><?php echo $user_credits; ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if ($is_contest_active): ?>
            <div style="position: absolute; top: 20px; left: 50%; transform: translateX(-50%); color: #00ffff; text-shadow: 0 0 10px #00ffff; font-size: 18px; font-weight: bold; z-index: 100; pointer-events: auto;">
                🏆 Contest Active!
            </div>
            <?php endif; ?>

            <div id="message-box">
                <h1 id="msg-title">COSMOS CAPTAIN</h1>
                <p id="msg-text">Commander, our scanners detect heavy asteroid fields. Destroy standard rocks to clear path, or target <span class="special-hint">Emerald Asteroids</span> to repair the hull.</p>
                <button id="start-btn">Engage Engines</button>
                <div class="instructions">
                    Click/Tap to Fire Bullets<br>
                    Green Asteroids = +20 Health<br>
                    Collision = -25 Health
                </div>
                <p id="total-score-container" style="display: none; color: #00ff00; font-weight: bold; margin-top: 15px; font-size: 14px;">
                    Total Score: <span id="total-score-display">0</span>
                </p>
            </div>
            
            <!-- Credits Confirmation Modal -->
            <div id="credits-confirmation-modal" class="hidden" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.85); z-index: 1000; display: flex; align-items: center; justify-content: center;">
                <div style="background: rgba(10, 10, 20, 0.95); border: 2px solid var(--primary-glow); border-radius: 15px; padding: 30px; max-width: 400px; width: 90%; text-align: center; box-shadow: 0 0 30px rgba(0, 242, 255, 0.5);">
                    <h2 style="color: var(--primary-glow); margin-bottom: 20px; font-size: 24px; text-shadow: 0 0 10px var(--primary-glow);">Confirm Credits Payment</h2>
                    <p style="color: #ccc; margin-bottom: 15px; font-size: 16px;">To start the game, you need to pay:</p>
                    <div style="background: rgba(0, 242, 255, 0.1); border: 1px solid var(--primary-glow); border-radius: 8px; padding: 15px; margin: 20px 0;">
                        <div style="font-size: 32px; color: #FFD700; font-weight: bold; margin-bottom: 5px;">
                            ⚡ <span id="credits-to-pay">30</span> Credits
                        </div>
                        <div style="font-size: 14px; color: #888; margin-top: 5px;">
                            Your Credits: <span id="current-credits-display" style="color: #FFD700;">0</span>
                        </div>
                    </div>
                    <div style="display: flex; gap: 15px; justify-content: center; margin-top: 25px;">
                        <button id="confirm-pay-btn" style="background: var(--primary-glow); color: black; border: none; padding: 12px 30px; font-size: 16px; font-weight: bold; border-radius: 5px; cursor: pointer; transition: all 0.2s; text-transform: uppercase;">
                            Pay & Start
                        </button>
                        <button id="cancel-pay-btn" style="background: rgba(255, 77, 77, 0.2); color: #ff4d4d; border: 2px solid #ff4d4d; padding: 12px 30px; font-size: 16px; font-weight: bold; border-radius: 5px; cursor: pointer; transition: all 0.2s; text-transform: uppercase;">
                            Cancel
                        </button>
        <style>
            #confirm-pay-btn:hover {
                transform: scale(1.05);
                box-shadow: 0 0 20px var(--primary-glow);
            }
            #cancel-pay-btn:hover {
                background: rgba(255, 77, 77, 0.4);
                transform: scale(1.05);
                box-shadow: 0 0 15px rgba(255, 77, 77, 0.5);
            }
            #confirm-pay-btn:active, #cancel-pay-btn:active {
                transform: scale(0.98);
            }
        </style>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Game integration variables - cosmos-captain
        const GAME_NAME = 'cosmos-captain';
        const IS_LOGGED_IN = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
        const USER_CREDITS = <?php echo $user_credits; ?>;
        const IS_CONTEST_ACTIVE = <?php echo $is_contest_active ? 'true' : 'false'; ?>;
        const GAME_MODE = '<?php echo $game_mode; ?>';
        
        let currentSessionId = null;
        let creditsUsed = 0;
        let gameStarted = false;
        let gameInitialized = false;
        let userTotalScore = 0;
        let isTabVisible = true;
        let creditsRequired = 30;
        let currentUserCredits = USER_CREDITS;
        
        const canvas = document.getElementById('gameCanvas');
        const ctx = canvas.getContext('2d');
        const scoreEl = document.getElementById('score');
        const healthBar = document.getElementById('health-bar');
        const startBtn = document.getElementById('start-btn');
        const messageBox = document.getElementById('message-box');
        const msgTitle = document.getElementById('msg-title');
        const msgText = document.getElementById('msg-text');
        const gameContainer = document.getElementById('game-container');
        const creditsModal = document.getElementById('credits-confirmation-modal');
        const confirmPayBtn = document.getElementById('confirm-pay-btn');
        const cancelPayBtn = document.getElementById('cancel-pay-btn');
        const creditsToPayEl = document.getElementById('credits-to-pay');
        const currentCreditsDisplay = document.getElementById('current-credits-display');

        let width, height;
        let score = 0;
        let gameActive = false;
        let asteroids = [];
        let particles = [];
        let bullets = [];
        let stars = [];
        let ship = { x: 0, y: 0, targetX: 0, targetY: 0, health: 100, maxHealth: 100 };

        // Configuration
        const ASTEROID_SPAWN_RATE = 0.025;
        const SPECIAL_CHANCE = 0.15; 
        const STAR_COUNT = 150;
        const BULLET_SPEED = 18;
        
        // Sound effects using Web Audio API
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        let soundEnabled = true;
        
        function playSound(frequency, duration, type = 'sine', volume = 0.3) {
            if (!soundEnabled) return;
            try {
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();
                
                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);
                
                oscillator.frequency.value = frequency;
                oscillator.type = type;
                
                gainNode.gain.setValueAtTime(volume, audioContext.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + duration);
                
                oscillator.start(audioContext.currentTime);
                oscillator.stop(audioContext.currentTime + duration);
            } catch (e) {
                console.log('Sound error:', e);
            }
        }
        
        function playShootSound() {
            playSound(800, 0.1, 'square', 0.2);
        }
        
        function playExplosionSound() {
            playSound(150, 0.3, 'sawtooth', 0.4);
        }
        
        function playSpecialSound() {
            playSound(600, 0.2, 'sine', 0.3);
            setTimeout(() => playSound(800, 0.2, 'sine', 0.3), 100);
        }
        
        function playDamageSound() {
            playSound(200, 0.4, 'sawtooth', 0.5);
        }
        
        function playGameOverSound() {
            playSound(100, 0.5, 'sawtooth', 0.6);
            setTimeout(() => playSound(80, 0.5, 'sawtooth', 0.6), 200);
        }

        function init() {
            resize();
            createStars();
            resetGame();
            
            // Initialize game integration
            if (!gameInitialized) {
                initGameIntegration();
                gameInitialized = true;
            }
            
            requestAnimationFrame(gameLoop);
        }
        
        // Initialize game integration (credits, sessions)
        function initGameIntegration() {
            if (!IS_LOGGED_IN) {
                msgText.textContent = 'Please login to play Cosmos Captain.';
                startBtn.style.display = 'none';
                return;
            }
            
            // Fetch user total score
            fetchUserTotalScore();
            
            // Fetch credits required
            fetch(`get_game_credits.php?game=${GAME_NAME}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.credits_per_chance) {
                        creditsRequired = data.credits_per_chance;
                    }
                })
                .catch(error => {
                    console.error('Error fetching credits:', error);
                });
            
            // Check game status and get session
            fetch(`game_api.php?action=check_status&game_name=${GAME_NAME}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.is_active) {
                        currentSessionId = data.session.id;
                        currentUserCredits = data.user_credits || USER_CREDITS;
                        updateCreditsDisplay(currentUserCredits);
                        
                        // Update credits required from session if available
                        if (data.session && data.session.credits_required) {
                            creditsRequired = data.session.credits_required;
                        }
                    } else {
                        if (data.message) {
                            msgText.textContent = 'Game is not currently available. ' + data.message;
                            startBtn.style.display = 'none';
                        }
                    }
                })
                .catch(error => {
                    console.error('Error checking game status:', error);
                });
        }
        
        // Show credits confirmation modal
        function showCreditsConfirmation() {
            if (creditsToPayEl) {
                creditsToPayEl.textContent = creditsRequired;
            }
            if (currentCreditsDisplay) {
                currentCreditsDisplay.textContent = currentUserCredits;
            }
            if (creditsModal) {
                creditsModal.classList.remove('hidden');
            }
        }
        
        // Hide credits confirmation modal
        function hideCreditsConfirmation() {
            if (creditsModal) {
                creditsModal.classList.add('hidden');
            }
        }
        
        // Fetch user total score
        function fetchUserTotalScore() {
            fetch(`game_api.php?action=get_user_score&game_name=${GAME_NAME}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        userTotalScore = data.user_total_points || 0;
                        updateTotalScoreDisplay();
                    }
                })
                .catch(error => {
                    console.error('Error fetching total score:', error);
                });
        }
        
        // Update total score display
        function updateTotalScoreDisplay() {
            const totalScoreEl = document.getElementById('total-score');
            const totalScoreDisplay = document.getElementById('total-score-display');
            const totalScoreBox = document.getElementById('total-score-box');
            
            if (totalScoreEl) {
                totalScoreEl.textContent = userTotalScore.toLocaleString();
            }
            if (totalScoreDisplay) {
                totalScoreDisplay.textContent = userTotalScore.toLocaleString();
            }
            if (totalScoreBox && gameActive) {
                totalScoreBox.style.display = 'block';
            }
        }
        
        // Start game (deduct credits)
        function startGameWithCredits() {
            if (!IS_LOGGED_IN) {
                alert('Please login to play Cosmos Captain.');
                window.location.href = 'index.php';
                return Promise.resolve(false);
            }
            
            if (gameStarted) return Promise.resolve(true);
            
            if (!currentSessionId) {
                alert('No active game session. Please try again.');
                return Promise.resolve(false);
            }
            
            // Deduct credits
            const formData = new FormData();
            formData.append('game_name', GAME_NAME);
            formData.append('session_id', currentSessionId);
            
            return fetch('game_api.php?action=deduct_credits', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    creditsUsed = data.credits_used;
                    currentUserCredits = data.remaining_credits;
                    updateCreditsDisplay(data.remaining_credits);
                    gameStarted = true;
                    // Show total score box
                    const totalScoreBox = document.getElementById('total-score-box');
                    if (totalScoreBox) {
                        totalScoreBox.style.display = 'block';
                    }
                    return true;
                } else {
                    alert(data.message || 'Failed to start game');
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    }
                    return false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to start game');
                return false;
            });
        }
        
        // Save score when game ends
        function saveScore(score) {
            if (!IS_LOGGED_IN) {
                return;
            }
            
            if (!gameStarted || creditsUsed === 0 || !currentSessionId) {
                return;
            }
            
            const finalScore = Math.floor(score || 0);
            console.log("Submitting final score:", finalScore, "for session:", currentSessionId);
            
            const formData = new FormData();
            formData.append('score', finalScore);
            formData.append('session_id', currentSessionId);
            formData.append('credits_used', creditsUsed);
            formData.append('game_name', GAME_NAME);
            formData.append('is_demo', 'false');
            
            fetch('game_api.php?action=save_score', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log("Score save response:", data);
                if (data.success) {
                    console.log('Score saved:', finalScore);
                    if (data.total_score !== undefined) {
                        userTotalScore = data.total_score;
                        updateTotalScoreDisplay();
                    }
                    // Fetch updated total score across all games
                    fetchUserTotalScore();
                } else {
                    console.error('Failed to save score:', data.message);
                }
            })
            .catch(error => {
                console.error('Error saving score:', error);
            });
        }
        
        // Update credits display
        function updateCreditsDisplay(credits) {
            const creditsValueEl = document.getElementById('credits-value');
            if (creditsValueEl) {
                creditsValueEl.textContent = credits;
            }
        }

        function resize() {
            width = window.innerWidth;
            height = window.innerHeight;
            canvas.width = width;
            canvas.height = height;
            ship.x = width / 2;
            ship.y = height * 0.85;
            ship.targetX = width / 2;
        }

        function createStars() {
            stars = [];
            for (let i = 0; i < STAR_COUNT; i++) {
                stars.push({
                    x: Math.random() * width,
                    y: Math.random() * height,
                    size: Math.random() * 2,
                    speed: Math.random() * 3 + 0.5,
                    opacity: Math.random()
                });
            }
        }

        function resetGame() {
            score = 0;
            ship.health = 100;
            asteroids = [];
            particles = [];
            bullets = [];
            updateUI();
        }

        function updateUI() {
            scoreEl.textContent = score.toString().padStart(4, '0');
            const healthPercent = (ship.health / ship.maxHealth) * 100;
            healthBar.style.width = Math.max(0, healthPercent) + '%';
            
            if (healthPercent < 30) {
                healthBar.style.background = 'linear-gradient(90deg, #e74c3c, #c0392b)';
            } else if (healthPercent < 60) {
                healthBar.style.background = 'linear-gradient(90deg, #f1c40f, #f39c12)';
            } else {
                healthBar.style.background = 'linear-gradient(90deg, #2ecc71, #27ae60)';
            }
        }

        class Asteroid {
            constructor() {
                this.isSpecial = Math.random() < SPECIAL_CHANCE;
                this.radius = Math.random() * 25 + 15;
                this.x = Math.random() * (width - this.radius * 2) + this.radius;
                this.y = -this.radius * 2;
                this.speed = Math.random() * 2 + 1.5;
                this.rotation = 0;
                this.rotationSpeed = (Math.random() - 0.5) * 0.05;
                this.vertices = [];
                const points = Math.floor(Math.random() * 5) + 7;
                for (let i = 0; i < points; i++) {
                    const angle = (i / points) * Math.PI * 2;
                    const offset = Math.random() * (this.radius * 0.4);
                    this.vertices.push({
                        x: Math.cos(angle) * (this.radius - offset),
                        y: Math.sin(angle) * (this.radius - offset)
                    });
                }
            }

            update() {
                this.y += this.speed;
                this.rotation += this.rotationSpeed;
                return this.y < height + this.radius;
            }

            draw() {
                ctx.save();
                ctx.translate(this.x, this.y);
                ctx.rotate(this.rotation);
                
                ctx.beginPath();
                ctx.moveTo(this.vertices[0].x, this.vertices[0].y);
                for (let i = 1; i < this.vertices.length; i++) {
                    ctx.lineTo(this.vertices[i].x, this.vertices[i].y);
                }
                ctx.closePath();
                
                if (this.isSpecial) {
                    ctx.strokeStyle = '#2eff8c';
                    ctx.shadowBlur = 10;
                    ctx.shadowColor = '#2eff8c';
                    ctx.fillStyle = '#0a2e1a';
                } else {
                    ctx.strokeStyle = '#888';
                    ctx.fillStyle = '#1a1a1a';
                }
                
                ctx.lineWidth = 2;
                ctx.fill();
                ctx.stroke();
                
                ctx.restore();
            }
        }

        class Bullet {
            constructor(startX, startY, targetX, targetY) {
                this.x = startX;
                this.y = startY;
                const angle = Math.atan2(targetY - startY, targetX - startX);
                this.vx = Math.cos(angle) * BULLET_SPEED;
                this.vy = Math.sin(angle) * BULLET_SPEED;
                this.angle = angle;
                this.length = 20;
                this.active = true;
            }

            update() {
                this.x += this.vx;
                this.y += this.vy;

                for (let i = asteroids.length - 1; i >= 0; i--) {
                    const ast = asteroids[i];
                    const dist = Math.sqrt((this.x - ast.x) ** 2 + (this.y - ast.y) ** 2);
                    if (dist < ast.radius + 5) {
                        if (ast.isSpecial) {
                            ship.health = Math.min(ship.maxHealth, ship.health + 20);
                            createExplosion(ast.x, ast.y, '#2eff8c');
                            playSpecialSound();
                        } else {
                            createExplosion(ast.x, ast.y, '#ff9d00');
                            playExplosionSound();
                        }
                        asteroids.splice(i, 1);
                        score += 100;
                        updateUI();
                        this.active = false;
                        return false;
                    }
                }

                return this.x > 0 && this.x < width && this.y > 0 && this.y < height;
            }

            draw() {
                ctx.save();
                ctx.translate(this.x, this.y);
                ctx.rotate(this.angle);
                ctx.beginPath();
                ctx.moveTo(-this.length, 0);
                ctx.lineTo(0, 0);
                ctx.strokeStyle = '#00f2ff';
                ctx.lineWidth = 4;
                ctx.lineCap = 'round';
                ctx.stroke();
                ctx.beginPath();
                ctx.moveTo(-this.length * 0.5, 0);
                ctx.lineTo(0, 0);
                ctx.strokeStyle = '#ffffff';
                ctx.lineWidth = 2;
                ctx.stroke();
                ctx.restore();
            }
        }

        class Particle {
            constructor(x, y, color, speedScale = 1) {
                this.x = x;
                this.y = y;
                this.color = color;
                this.vx = (Math.random() - 0.5) * 10 * speedScale;
                this.vy = (Math.random() - 0.5) * 10 * speedScale;
                this.alpha = 1;
                this.decay = Math.random() * 0.03 + 0.02;
                this.size = Math.random() * 3 + 1;
            }

            update() {
                this.x += this.vx;
                this.y += this.vy;
                this.alpha -= this.decay;
                return this.alpha > 0;
            }

            draw() {
                ctx.save();
                ctx.globalAlpha = this.alpha;
                ctx.fillStyle = this.color;
                ctx.beginPath();
                ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                ctx.fill();
                ctx.restore();
            }
        }

        function createExplosion(x, y, baseColor) {
            const colors = [baseColor, '#ffffff', '#555555'];
            for (let i = 0; i < 15; i++) {
                particles.push(new Particle(x, y, colors[Math.floor(Math.random() * colors.length)]));
            }
        }

        function createMuzzleFlash(x, y) {
            for (let i = 0; i < 5; i++) {
                const p = new Particle(x, y, '#00f2ff', 0.4);
                p.vy -= 4;
                particles.push(p);
            }
        }

        function handleInput(e) {
            if (!gameActive) return;
            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            const clientY = e.touches ? e.touches[0].clientY : e.clientY;
            ship.targetX = clientX;
            bullets.push(new Bullet(ship.x, ship.y - 30, clientX, clientY));
            createMuzzleFlash(ship.x, ship.y - 30);
            playShootSound();
        }

        function takeDamage(amount) {
            ship.health -= amount;
            gameContainer.classList.add('hit-effect');
            setTimeout(() => gameContainer.classList.remove('hit-effect'), 300);
            playDamageSound();
            updateUI();
            if (ship.health <= 0) gameOver();
        }

        function drawShip() {
            ship.x += (ship.targetX - ship.x) * 0.15;
            ctx.save();
            ctx.translate(ship.x, ship.y);
            const tilt = (ship.targetX - ship.x) * 0.015;
            ctx.rotate(tilt);
            const enginePulse = Math.sin(Date.now() * 0.02) * 8;
            ctx.fillStyle = '#ff4d4d';
            ctx.beginPath();
            ctx.moveTo(-8, 15);
            ctx.lineTo(0, 25 + enginePulse);
            ctx.lineTo(8, 15);
            ctx.fill();
            const grad = ctx.createLinearGradient(-20, 0, 20, 0);
            grad.addColorStop(0, '#444');
            grad.addColorStop(0.5, '#ddd');
            grad.addColorStop(1, '#444');
            ctx.fillStyle = grad;
            ctx.beginPath();
            ctx.moveTo(0, -35);
            ctx.lineTo(25, 15);
            ctx.lineTo(0, 5);
            ctx.lineTo(-25, 15);
            ctx.closePath();
            ctx.fill();
            ctx.fillStyle = '#00f2ff';
            ctx.globalAlpha = 0.7;
            ctx.beginPath();
            ctx.ellipse(0, -8, 6, 10, 0, 0, Math.PI * 2);
            ctx.fill();
            ctx.globalAlpha = 1;
            ctx.restore();
        }

        function gameLoop() {
            ctx.clearRect(0, 0, width, height);
            stars.forEach(star => {
                star.y += star.speed;
                if (star.y > height) star.y = 0;
                ctx.fillStyle = `rgba(255, 255, 255, ${star.opacity})`;
                ctx.fillRect(star.x, star.y, star.size, star.size);
            });

            if (gameActive) {
                if (Math.random() < ASTEROID_SPAWN_RATE) {
                    asteroids.push(new Asteroid());
                }
                asteroids = asteroids.filter(ast => {
                    const active = ast.update();
                    ast.draw();
                    const d = Math.sqrt((ship.x - ast.x)**2 + (ship.y - ast.y)**2);
                    if (d < ast.radius + 20) {
                        createExplosion(ast.x, ast.y, ast.isSpecial ? '#2eff8c' : '#ff9d00');
                        takeDamage(25);
                        return false;
                    }
                    return active;
                });
                bullets = bullets.filter(bullet => {
                    const active = bullet.update() && bullet.active;
                    if (active) bullet.draw();
                    return active;
                });
                particles = particles.filter(p => {
                    const active = p.update();
                    if (active) p.draw();
                    return active;
                });
                drawShip();
            }
            requestAnimationFrame(gameLoop);
        }

        function gameOver() {
            gameActive = false;
            playGameOverSound();
            createExplosion(ship.x, ship.y, '#ff4d4d');
            msgTitle.textContent = "SHIP CRITICALLY DAMAGED";
            
            // Save score
            saveScore(score);
            
            let gameOverText = `Commander, the hull has failed. Mission terminated.\n\nFinal Score: ${score.toLocaleString()}`;
            
            if (IS_LOGGED_IN) {
                // Show total score after saving
                setTimeout(() => {
                    const totalScoreDisplay = document.getElementById('total-score-display');
                    const totalScoreContainer = document.getElementById('total-score-container');
                    if (totalScoreDisplay && totalScoreContainer) {
                        totalScoreDisplay.textContent = userTotalScore.toLocaleString();
                        totalScoreContainer.style.display = 'block';
                    }
                    if (IS_CONTEST_ACTIVE) {
                        gameOverText += '\n\n🏆 Contest score saved!';
                    }
                    gameOverText += `\n\nTotal Score: ${userTotalScore.toLocaleString()}`;
                    msgText.textContent = gameOverText;
                }, 500);
            }
            
            msgText.textContent = gameOverText;
            startBtn.textContent = "Relaunch Shuttle";
            messageBox.classList.remove('hidden');
            
            // Hide total score box
            const totalScoreBox = document.getElementById('total-score-box');
            if (totalScoreBox) {
                totalScoreBox.style.display = 'none';
            }
            
            // Reset game started flag for next play
            gameStarted = false;
            creditsUsed = 0;
        }

        startBtn.addEventListener('click', async () => {
            if (!IS_LOGGED_IN) {
                alert('Please login to play Cosmos Captain.');
                window.location.href = 'index.php';
                return;
            }
            
            if (gameStarted) {
                // Game already started, just hide message box and continue
                messageBox.classList.add('hidden');
                resetGame();
                gameActive = true;
                return;
            }
            
            // Check if user has enough credits
            if (currentUserCredits < creditsRequired) {
                alert(`Insufficient credits! You need ${creditsRequired} credits to play.`);
                return;
            }
            
            // Show confirmation modal
            showCreditsConfirmation();
        });
        
        // Confirm payment button
        if (confirmPayBtn) {
            confirmPayBtn.addEventListener('click', async () => {
                hideCreditsConfirmation();
                
                // Deduct credits and start game
                const canStart = await startGameWithCredits();
                if (canStart) {
                    messageBox.classList.add('hidden');
                    resetGame();
                    gameActive = true;
                }
            });
        }
        
        // Cancel payment button
        if (cancelPayBtn) {
            cancelPayBtn.addEventListener('click', () => {
                hideCreditsConfirmation();
            });
        }
        
        // Close modal when clicking outside
        if (creditsModal) {
            creditsModal.addEventListener('click', (e) => {
                if (e.target === creditsModal) {
                    hideCreditsConfirmation();
                }
            });
        }
        
        // Handle visibility change - game continues when minimized
        document.addEventListener('visibilitychange', function() {
            isTabVisible = !document.hidden;
            // Game continues running even when tab is hidden
        });
        
        // Handle page focus/blur
        window.addEventListener('blur', function() {
            // Game continues
        });
        
        window.addEventListener('focus', function() {
            isTabVisible = true;
        });

        window.addEventListener('resize', resize);
        canvas.addEventListener('mousedown', handleInput);
        canvas.addEventListener('touchstart', (e) => {
            e.preventDefault();
            handleInput(e);
        }, { passive: false });

        init();
    </script>
</body>
</html>


