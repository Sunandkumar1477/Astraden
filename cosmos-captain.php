<?php
session_start();
// Allow demo game without login - don't require check_user_session.php
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
                    <?php if ($is_logged_in): ?>
                    <div class="stat-box" style="margin-top: 10px;">
                        <div class="score-label">Credits</div>
                        <div id="credits-display" class="score-value" style="color: <?php echo $credits_color; ?>;">
                            ⚡ <span id="credits-value"><?php echo $user_credits; ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
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
        
        const canvas = document.getElementById('gameCanvas');
        const ctx = canvas.getContext('2d');
        const scoreEl = document.getElementById('score');
        const healthBar = document.getElementById('health-bar');
        const startBtn = document.getElementById('start-btn');
        const messageBox = document.getElementById('message-box');
        const msgTitle = document.getElementById('msg-title');
        const msgText = document.getElementById('msg-text');
        const gameContainer = document.getElementById('game-container');

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
                // Demo mode - allow playing without credits
                return;
            }
            
            // Check game status and get session
            fetch(`game_api.php?action=check_status&game_name=${GAME_NAME}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.is_active) {
                        currentSessionId = data.session.id;
                        updateCreditsDisplay(data.user_credits || USER_CREDITS);
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
        
        // Start game (deduct credits)
        function startGameWithCredits() {
            if (!IS_LOGGED_IN) {
                // Demo mode
                gameStarted = true;
                return Promise.resolve(true);
            }
            
            if (gameStarted) return Promise.resolve(true);
            
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
                    updateCreditsDisplay(data.remaining_credits);
                    gameStarted = true;
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
            // Only save if game was started with credits (not demo mode)
            if (!IS_LOGGED_IN) {
                // Demo mode - show score but don't save
                return;
            }
            
            if (!gameStarted || creditsUsed === 0 || !currentSessionId) {
                // No credits used or no session, don't save
                return;
            }
            
            const finalScore = Math.floor(score || 0);
            console.log("Submitting final score:", finalScore, "for session:", currentSessionId);
            
            const formData = new FormData();
            formData.append('score', finalScore);
            formData.append('session_id', currentSessionId);
            formData.append('credits_used', creditsUsed);
            formData.append('game_name', GAME_NAME);
            formData.append('is_demo', 'false'); // Explicitly mark as not demo
            
            fetch('game_api.php?action=save_score', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log("Score save response:", data);
                if (data.success) {
                    console.log('Score saved:', finalScore);
                    if (data.is_contest) {
                        console.log('Contest score saved');
                    }
                    if (data.total_score !== undefined) {
                        console.log('Total score for this game:', data.total_score);
                    }
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
                        } else {
                            createExplosion(ast.x, ast.y, '#ff9d00');
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
        }

        function takeDamage(amount) {
            ship.health -= amount;
            gameContainer.classList.add('hit-effect');
            setTimeout(() => gameContainer.classList.remove('hit-effect'), 300);
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
            createExplosion(ship.x, ship.y, '#ff4d4d');
            msgTitle.textContent = "SHIP CRITICALLY DAMAGED";
            
            // Save score
            saveScore(score);
            
            if (!IS_LOGGED_IN) {
                msgText.textContent = `Commander, the hull has failed. Mission terminated.\n\nFinal Score: ${score}\n\nLogin to save your scores!`;
            } else {
                msgText.textContent = `Commander, the hull has failed. Mission terminated.\n\nFinal Score: ${score}`;
                if (IS_CONTEST_ACTIVE) {
                    msgText.textContent += '\n\n🏆 Contest score saved!';
                }
            }
            
            startBtn.textContent = "Relaunch Shuttle";
            messageBox.classList.remove('hidden');
            
            // Reset game started flag for next play
            gameStarted = false;
            creditsUsed = 0;
        }

        startBtn.addEventListener('click', async () => {
            // Check if we need to deduct credits
            if (IS_LOGGED_IN && !gameStarted) {
                const canStart = await startGameWithCredits();
                if (!canStart) {
                    return; // Don't start if credits deduction failed
                }
            } else if (!IS_LOGGED_IN) {
                gameStarted = true; // Demo mode
            }
            
            messageBox.classList.add('hidden');
            resetGame();
            gameActive = true;
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


