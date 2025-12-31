<?php
session_start();
// Security headers and performance optimizations
require_once 'security_headers.php';
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
        
        .hud-left {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .hud-right {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            text-align: right;
        }
        
        #exit-btn-container {
            pointer-events: auto;
            z-index: 1000;
        }
        
        /* Desktop: Exit button on the side */
        @media (min-width: 769px) {
            .hud-left {
                flex-direction: row;
                align-items: flex-start;
                gap: 15px;
            }
            
            #exit-btn-container {
                margin-top: 0;
            }
        }

        .stat-box {
            background: rgba(0, 0, 0, 0.6);
            border: 1px solid var(--primary-glow);
            padding: 10px 20px;
            border-radius: 8px;
            backdrop-filter: blur(5px);
            box-shadow: 0 0 15px rgba(0, 242, 255, 0.2);
            min-width: 120px;
            position: relative;
            z-index: 10;
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

        #game-status-overlay.hidden {
            display: none !important;
            pointer-events: none;
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
        
        #credits-confirmation-modal {
            display: none;
        }
        
        #credits-confirmation-modal:not(.hidden) {
            display: flex !important;
        }
        
        #exit-confirm-modal {
            display: none;
        }
        
        #exit-confirm-modal:not(.hidden) {
            display: flex !important;
        }
        
        #credits-confirmation-modal button,
        #exit-confirm-modal button {
            pointer-events: auto !important;
            position: relative;
            z-index: 10002;
        }
        
        #confirm-pay-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 0 20px var(--primary-glow);
        }
        
        #cancel-pay-btn:hover {
            background: rgba(255, 77, 77, 0.4) !important;
            transform: scale(1.05);
            box-shadow: 0 0 15px rgba(255, 77, 77, 0.5);
        }
        
        #confirm-pay-btn:active, #cancel-pay-btn:active {
            transform: scale(0.98);
        }
        
        #exit-yes-btn:hover {
            background: rgba(255, 77, 77, 0.4) !important;
            transform: scale(1.05);
            box-shadow: 0 0 15px rgba(255, 77, 77, 0.5);
        }
        
        #exit-no-btn:hover {
            background: rgba(0, 242, 255, 0.4) !important;
            transform: scale(1.05);
            box-shadow: 0 0 15px var(--primary-glow);
        }
        
        #exit-yes-btn:active, #exit-no-btn:active {
            transform: scale(0.98);
        }
        
        #exit-game-btn:hover {
            background: rgba(255, 77, 77, 0.4) !important;
            transform: scale(1.05);
            box-shadow: 0 0 15px rgba(255, 77, 77, 0.5);
        }
        
        #exit-game-btn:active {
            transform: scale(0.98);
        }

        @keyframes damage-flash {
            0% { background-color: rgba(255, 0, 0, 0.3); }
            100% { background-color: transparent; }
        }

        .hit-effect {
            animation: damage-flash 0.3s forwards;
        }
        
        /* Mobile responsive styles */
        @media (max-width: 768px) {
            .stat-box {
                padding: 8px 15px;
                min-width: 100px;
                font-size: 0.9rem;
            }
            
            .score-label {
                font-size: 9px;
            }
            
            .score-value {
                font-size: 20px;
            }
            
            #message-box {
                width: 90%;
                max-width: 350px;
                padding: 20px;
            }
            
            #message-box h1 {
                font-size: 24px;
            }
            
            #message-box p {
                font-size: 14px;
            }
            
            #score-details {
                padding: 12px;
            }
            
            #final-score-display {
                font-size: 1.3rem !important;
            }
            
            #back-to-menu-btn {
                width: 100%;
                padding: 14px 20px;
                font-size: 14px;
            }
            
            /* Mobile: Exit button in first box line, score boxes in column below */
            .hud-top {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .hud-left {
                width: 100%;
                flex-direction: row;
                align-items: center;
                gap: 10px;
                justify-content: space-between;
            }
            
            .hud-left .stat-box {
                flex: 1;
            }
            
            #exit-btn-container {
                flex-shrink: 0;
                margin-top: 0;
            }
            
            .hud-right {
                width: auto;
                align-items: flex-end;
                margin-top: 10px;
            }
            
            .hud-right .stat-box {
                width: auto;
                min-width: 120px;
                margin-top: 10px;
            }
            
            .hud-right .stat-box:first-child {
                margin-top: 0;
            }
            
            #exit-game-btn {
                padding: 8px 15px;
                font-size: 12px;
                white-space: nowrap;
            }
        }
    </style>
</head>
<body>

    <div id="game-container">
        <canvas id="gameCanvas"></canvas>

        <div id="ui-layer">
            <div class="hud-top">
                <div class="hud-left">
                    <div class="stat-box">
                        <div class="score-label">Hull Integrity</div>
                        <div class="health-container">
                            <div id="health-bar"></div>
                        </div>
                    </div>
                    <!-- Exit button - in same line on mobile, on side for desktop -->
                    <div id="exit-btn-container">
                        <button id="exit-game-btn" style="display: none; background: rgba(255, 51, 51, 0.2); border: 2px solid #ff3333; color: #ff3333; padding: 12px 24px; font-size: 14px; font-weight: bold; border-radius: 8px; cursor: pointer; transition: all 0.3s; text-transform: uppercase; font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; box-shadow: 0 0 15px rgba(255, 51, 51, 0.3);">
                            ⛔ EXIT GAME
                        </button>
                    </div>
                </div>
                <div class="hud-right">
                    <div class="stat-box">
                        <div class="score-label">Mission Score</div>
                        <div id="score" class="score-value">0000</div>
                    </div>
                    <div class="stat-box" style="margin-top: 10px; display: none;" id="total-score-box">
                        <div class="score-label">Total Score</div>
                        <div id="total-score" class="score-value" style="color: #00ff00;">0</div>
                    </div>
                    <!-- Credits display hidden as per user request -->
                    <div class="stat-box" style="margin-top: 10px; display: none;" id="credits-display-box">
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

            <!-- Game Status Overlay (Pre-game) -->
            <div id="game-status-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: radial-gradient(circle at center, rgba(10, 20, 30, 0.95) 0%, rgba(0, 5, 10, 0.98) 100%); z-index: 1000; display: flex; flex-direction: column; pointer-events: auto; align-items: center; justify-content: center; color: #00ffff; text-align: center; padding: 40px 20px; overflow-y: auto;">
                <div class="status-title" style="font-size: clamp(2rem, 5vw, 3.5rem); font-weight: 900; margin-bottom: 30px; text-transform: uppercase; letter-spacing: 5px; color: var(--primary-glow); text-shadow: 0 0 20px rgba(0, 242, 255, 0.4), 2px 2px 0px #000; font-family: 'Orbitron', monospace;">🚀 COSMOS CAPTAIN</div>
                
                <?php if ($is_contest_active): ?>
                    <div class="contest-badge" style="background: linear-gradient(135deg, #FFD700, #ff8c00); color: #000; padding: 10px 18px; border-radius: 8px; font-weight: bold; font-size: 0.85rem; margin: 0 0 15px 0; text-transform: uppercase; box-shadow: 0 2px 10px rgba(255, 215, 0, 0.3); width: calc(100% - 30px); max-width: 500px; box-sizing: border-box; text-align: center;">
                        🏆 <?php echo $game_mode === 'money' ? 'CASH CONTEST' : 'CREDIT CONTEST'; ?> 🏆
                    </div>
                    <div class="prize-pool" style="background: rgba(255, 215, 0, 0.1); border: 2px solid #FFD700; border-radius: 10px; padding: 15px 18px; margin: 0 0 15px 0; width: calc(100% - 30px); max-width: 500px; box-shadow: 0 2px 10px rgba(255, 215, 0, 0.2); text-align: left; box-sizing: border-box;">
                        <div style="color: #FFD700; font-weight: bold; margin-bottom: 12px; text-transform: uppercase; font-size: 0.9rem; text-align: left;">Mission Rewards</div>
                        <?php 
                        $unit = $game_mode === 'money' ? '₹' : '';
                        $suffix = $game_mode === 'money' ? '' : ' Credits';
                        ?>
                        <div class="prize-item" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid rgba(255, 215, 0, 0.2); font-size: 0.85rem; text-align: left;">
                            <span>🥇 1st Rank:</span> <span style="color: #FFD700; font-weight: bold;"><?php echo $unit . number_format($prizes['1st']) . $suffix; ?></span>
                        </div>
                        <div class="prize-item" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid rgba(255, 215, 0, 0.2); font-size: 0.85rem; text-align: left;">
                            <span>🥈 2nd Rank:</span> <span style="color: #FFD700; font-weight: bold;"><?php echo $unit . number_format($prizes['2nd']) . $suffix; ?></span>
                        </div>
                        <div class="prize-item" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; font-size: 0.85rem; text-align: left;">
                            <span>🥉 3rd Rank:</span> <span style="color: #FFD700; font-weight: bold;"><?php echo $unit . number_format($prizes['3rd']) . $suffix; ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <div id="timer-display" class="timer-display" style="font-size: clamp(3rem, 8vw, 5rem); font-weight: 900; color: #00ffff; margin: 20px 0; font-family: 'Orbitron', monospace; text-shadow: 0 0 30px rgba(0, 255, 255, 0.5);">Loading...</div>
                <div id="status-message" class="status-message" style="font-size: 1.2rem; color: rgba(0, 255, 255, 0.9); margin: 15px 0; max-width: 600px; line-height: 1.5;"></div>
                <div class="credits-info" style="font-size: 0.95rem; padding: 12px 18px; margin: 0 0 15px 0; width: calc(100% - 30px); max-width: 500px; text-align: left; background: rgba(255, 215, 0, 0.1); border: 1px solid rgba(255, 215, 0, 0.3); border-radius: 10px; box-shadow: 0 2px 10px rgba(255, 215, 0, 0.2); box-sizing: border-box;">
                    Your Credits: <span id="user-credits-display" style="color: <?php echo htmlspecialchars($credits_color); ?>; font-weight: bold;"><?php echo number_format($user_credits); ?></span>
                </div>

                <div class="game-btn-container" style="display: flex; flex-direction: column; align-items: flex-start; justify-content: flex-start; gap: 12px; margin: 0; width: calc(100% - 30px); max-width: 500px; box-sizing: border-box;">
                    <button id="start-game-btn" class="start-game-btn" style="display: none; width: 100%; max-width: none; padding: 14px 20px; font-size: 0.95rem; font-weight: 700; margin: 0; text-align: left; justify-content: flex-start; border-radius: 10px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3); box-sizing: border-box; border: 2px solid; transition: all 0.3s ease; background: linear-gradient(135deg, #00ffff, #9d4edd); color: white; border: none; cursor: pointer; text-transform: uppercase; letter-spacing: 1px; font-family: 'Orbitron', sans-serif;">START MISSION</button>
                    <a href="index.php" class="game-btn btn-home" style="width: 100%; max-width: none; padding: 14px 20px; font-size: 0.95rem; font-weight: 700; margin: 0; text-align: left; justify-content: flex-start; border-radius: 10px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3); box-sizing: border-box; border: 2px solid rgba(157, 78, 221, 0.5); background: rgba(157, 78, 221, 0.1); color: #9d4edd; text-decoration: none; display: flex; align-items: center; gap: 10px; text-transform: uppercase; letter-spacing: 1px; transition: all 0.3s ease;">🏠 BACK TO HOME</a>
                </div>
            </div>
            
            <!-- Game Over message box -->
            <div id="message-box" style="display: none;">
                <h1 id="msg-title">GAME OVER</h1>
                <p id="msg-text">Commander, the hull has failed. Mission terminated.</p>
                <div id="score-details" style="margin: 20px 0; padding: 15px; background: rgba(0, 255, 255, 0.1); border: 1px solid var(--primary-glow); border-radius: 8px;">
                    <div style="margin-bottom: 10px;">
                        <span style="color: var(--primary-glow); font-size: 0.9rem;">Final Score:</span>
                        <span id="final-score-display" style="color: #fff; font-size: 1.5rem; font-weight: bold; margin-left: 10px;">0</span>
                    </div>
                    <p id="total-score-container" style="display: none; color: #00ff00; font-weight: bold; margin-top: 10px; font-size: 0.9rem;">
                        Total Score: <span id="total-score-display">0</span>
                    </p>
                </div>
                <a id="prize-claim-link" href="#" style="display: none; background: linear-gradient(135deg, #FFD700, #ff8c00); color: #000; text-decoration: none; border: none; padding: 12px 30px; font-size: 16px; font-weight: bold; border-radius: 5px; cursor: pointer; transition: all 0.2s; text-transform: uppercase; margin-top: 10px; text-align: center; box-shadow: 0 0 20px rgba(255, 215, 0, 0.5);">
                    🏆 VIEW PRIZE CLAIM
                </a>
                <button id="back-to-menu-btn" style="background: linear-gradient(135deg, #00ffff, #9d4edd); color: white; border: none; padding: 12px 30px; font-size: 16px; font-weight: bold; border-radius: 5px; cursor: pointer; transition: all 0.2s; text-transform: uppercase; margin-top: 10px;">
                    🏠 BACK TO MENU
                </button>
                <button id="start-btn" style="display: none;">Engage Engines</button>
                <div class="instructions" style="display: none;">
                    Click/Tap to Fire Bullets<br>
                    Green Asteroids = +20 Health<br>
                    Collision = -25 Health
                </div>
            </div>
            
            <!-- Exit Confirmation Modal -->
            
        </div>
    </div>
    
    <!-- Logged Out From Another Device Modal -->
    <div id="another-device-logout-modal" class="hidden" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.95); z-index: 10005; display: none; align-items: center; justify-content: center; pointer-events: auto;">
        <div style="background: rgba(10, 20, 30, 0.95); border: 2px solid #FFD700; border-radius: 15px; padding: 30px; max-width: 450px; width: 90%; text-align: center; box-shadow: 0 0 30px rgba(255, 215, 0, 0.5); pointer-events: auto; position: relative; z-index: 10006;">
            <div style="margin-bottom: 20px;">
                <div style="font-size: 3rem; margin-bottom: 15px;">🔐</div>
                <h2 style="color: #FFD700; margin: 0 0 20px 0; font-size: 1.5rem; letter-spacing: 2px; text-shadow: 0 0 10px #FFD700;">Session Terminated</h2>
            </div>
            <div style="margin-bottom: 25px;">
                <p style="color: #fff; margin-bottom: 15px; font-size: 1rem; line-height: 1.6;">
                    Your account has been accessed from another device. For security reasons, your session on this device has been automatically terminated.
                </p>
                <p style="color: rgba(255, 255, 255, 0.8); margin: 0; font-size: 0.9rem; line-height: 1.5;">
                    If this was not you, please secure your account immediately.
                </p>
            </div>
            <button id="another-device-ok-btn" style="background: linear-gradient(135deg, #FFD700, #ff8c00); color: #000; border: none; padding: 12px 30px; font-size: 16px; font-weight: bold; border-radius: 8px; cursor: pointer; transition: all 0.3s; text-transform: uppercase; pointer-events: auto; z-index: 10007; width: 100%;">
                Return to Home
            </button>
        </div>
    </div>
    
    <!-- Exit Confirmation Modal - Outside ui-layer for proper pointer events -->
    <div id="exit-confirm-modal" class="hidden" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.85); z-index: 10001; display: none; align-items: center; justify-content: center; pointer-events: auto;">
        <div style="background: rgba(10, 20, 30, 0.95); border: 2px solid #00ffff; border-radius: 15px; padding: 30px; max-width: 400px; width: 90%; text-align: center; box-shadow: 0 0 30px rgba(0, 255, 255, 0.3); pointer-events: auto; position: relative; z-index: 10002;">
            <div style="margin-bottom: 20px;">
                <h2 style="color: #ff3333; margin: 0 0 20px 0; font-size: 1.5rem; letter-spacing: 2px; text-shadow: 0 0 10px #ff3333;">⚠️ SYSTEM ALERT</h2>
            </div>
            <div style="margin-bottom: 25px;">
                <p style="color: #fff; margin-bottom: 10px; font-size: 1rem; line-height: 1.5;">ARE YOU SURE YOU WANT TO CLOSE THIS GAME?</p>
                <p style="color: #ffaa00; margin: 0; font-size: 0.9rem; font-weight: bold;">Your game will end and your score will be saved automatically.</p>
            </div>
            <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                <button id="exit-no-btn" style="background: rgba(0, 242, 255, 0.2); color: var(--primary-glow); border: 2px solid var(--primary-glow); padding: 12px 24px; font-size: 14px; font-weight: bold; border-radius: 8px; cursor: pointer; transition: all 0.3s; text-transform: uppercase; pointer-events: auto; z-index: 10003; min-width: 150px;">
                    NO, CONTINUE PLAYING
                </button>
                <button id="exit-yes-btn" style="background: rgba(255, 51, 51, 0.2); color: #ff3333; border: 2px solid #ff3333; padding: 12px 24px; font-size: 14px; font-weight: bold; border-radius: 8px; cursor: pointer; transition: all 0.3s; text-transform: uppercase; pointer-events: auto; z-index: 10003; min-width: 150px;">
                    YES, SAVE & EXIT
                </button>
            </div>
        </div>
    </div>
    
    <!-- Score Saved Modal - Shows after score is saved -->
    <div id="score-saved-modal" class="hidden" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.85); z-index: 10002; display: none; align-items: center; justify-content: center; pointer-events: auto;">
        <div style="background: rgba(10, 20, 30, 0.95); border: 2px solid #00ffff; border-radius: 15px; padding: 30px; max-width: 400px; width: 90%; text-align: center; box-shadow: 0 0 30px rgba(0, 255, 255, 0.3); pointer-events: auto; position: relative; z-index: 10003;">
            <h2 style="color: #00ffff; margin-bottom: 20px; font-size: 24px; text-shadow: 0 0 10px #00ffff;">✅ Score Saved!</h2>
            <div style="margin-bottom: 20px;">
                <p style="color: #fff; margin-bottom: 15px; font-size: 16px;">Mission Score:</p>
                <div style="font-size: 32px; color: #00ffff; font-weight: bold; margin-bottom: 15px;">
                    <span id="saved-mission-score">0</span>
                </div>
                <p style="color: #00ff00; margin-top: 15px; font-size: 14px; font-weight: bold;">
                    Total Cosmos Captain Score: <span id="saved-total-score">0</span>
                </p>
            </div>
            <button id="score-saved-ok-btn" style="background: var(--primary-glow); color: black; border: none; padding: 12px 30px; font-size: 16px; font-weight: bold; border-radius: 5px; cursor: pointer; transition: all 0.2s; text-transform: uppercase; pointer-events: auto; z-index: 10004; width: 100%;">
                OK
            </button>
        </div>
    </div>
    
    <!-- Credits Confirmation Modal - Outside ui-layer for proper pointer events -->
    <div id="credits-confirmation-modal" class="hidden" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.85); z-index: 10000; display: none; align-items: center; justify-content: center; pointer-events: auto;">
        <div style="background: rgba(10, 10, 20, 0.95); border: 2px solid var(--primary-glow); border-radius: 15px; padding: 30px; max-width: 400px; width: 90%; text-align: center; box-shadow: 0 0 30px rgba(0, 242, 255, 0.5); pointer-events: auto; position: relative; z-index: 10001;">
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
                <button id="confirm-pay-btn" style="background: var(--primary-glow); color: black; border: none; padding: 12px 30px; font-size: 16px; font-weight: bold; border-radius: 5px; cursor: pointer; transition: all 0.2s; text-transform: uppercase; pointer-events: auto; z-index: 10002;">
                    Pay & Start
                </button>
                <button id="cancel-pay-btn" style="background: rgba(255, 77, 77, 0.2); color: #ff4d4d; border: 2px solid #ff4d4d; padding: 12px 30px; font-size: 16px; font-weight: bold; border-radius: 5px; cursor: pointer; transition: all 0.2s; text-transform: uppercase; pointer-events: auto; z-index: 10002;">
                    Cancel
                </button>
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
        let isTimeDurationSession = false; // Track if current session is time-duration (not always_available)
        let sessionCheckInterval = null;
        let sessionCheckActive = false;
        
        const canvas = document.getElementById('gameCanvas');
        const ctx = canvas.getContext('2d');
        const scoreEl = document.getElementById('score');
        const healthBar = document.getElementById('health-bar');
        const startBtn = document.getElementById('start-btn');
        const startGameBtn = document.getElementById('start-game-btn');
        const messageBox = document.getElementById('message-box');
        const gameStatusOverlay = document.getElementById('game-status-overlay');
        const msgTitle = document.getElementById('msg-title');
        const msgText = document.getElementById('msg-text');
        const gameContainer = document.getElementById('game-container');
        const timerDisplay = document.getElementById('timer-display');
        const statusMessage = document.getElementById('status-message');
        const creditsModal = document.getElementById('credits-confirmation-modal');
        const confirmPayBtn = document.getElementById('confirm-pay-btn');
        const cancelPayBtn = document.getElementById('cancel-pay-btn');
        const creditsToPayEl = document.getElementById('credits-to-pay');
        const currentCreditsDisplay = document.getElementById('current-credits-display');
        const exitBtn = document.getElementById('exit-game-btn');
        const exitModal = document.getElementById('exit-confirm-modal');
        const exitYesBtn = document.getElementById('exit-yes-btn');
        const exitNoBtn = document.getElementById('exit-no-btn');
        const backToMenuBtn = document.getElementById('back-to-menu-btn');
        const scoreSavedModal = document.getElementById('score-saved-modal');
        const scoreSavedOkBtn = document.getElementById('score-saved-ok-btn');
        const savedMissionScoreEl = document.getElementById('saved-mission-score');
        const savedTotalScoreEl = document.getElementById('saved-total-score');
        const anotherDeviceModal = document.getElementById('another-device-logout-modal');
        const anotherDeviceOkBtn = document.getElementById('another-device-ok-btn');

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
        // Check localStorage for mute state (default: false = unmuted)
        let soundEnabled = localStorage.getItem('gameSoundMuted') !== 'true';
        
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
                // Sound error - silent fail
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
            
            // Show button immediately if user is logged in (before async calls complete)
            if (IS_LOGGED_IN && startGameBtn) {
                // Clear loading text immediately
                if (timerDisplay) {
                    timerDisplay.textContent = '';
                    timerDisplay.style.display = 'none';
                }
                // Force show button immediately with default credits
                startGameBtn.style.display = 'flex';
                startGameBtn.style.visibility = 'visible';
                startGameBtn.style.opacity = '1';
                if (currentUserCredits >= creditsRequired) {
                    startGameBtn.innerHTML = `🚀 START MISSION - ⚡ ${creditsRequired} Credits`;
                    startGameBtn.disabled = false;
                    startGameBtn.style.cursor = 'pointer';
                } else {
                    startGameBtn.innerHTML = `🔒 LOCKED - ⚡ ${creditsRequired} Credits Required`;
                    startGameBtn.disabled = true;
                    startGameBtn.style.opacity = '0.6';
                    startGameBtn.style.cursor = 'not-allowed';
                }
                // Also call showGameReady to update status message
                showGameReady();
            }
            
            // Initialize game integration
            if (!gameInitialized) {
                initGameIntegration();
                gameInitialized = true;
            }
            
            requestAnimationFrame(gameLoop);
        }
        
        // Initialize game integration (credits, sessions)
        function initGameIntegration() {
            // Fetch user total score
            fetchUserTotalScore();
            
            // Fetch credits required
            fetch(`get_game_credits.php?game=${GAME_NAME}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.credits_per_chance) {
                        creditsRequired = data.credits_per_chance;
                        // Show button immediately if user is logged in
                        if (IS_LOGGED_IN) {
                            showGameReady();
                        }
                    }
                })
                .catch(error => {
                    // Show button even if credits fetch fails (if user is logged in)
                    if (IS_LOGGED_IN) {
                        showGameReady();
                    }
                });
            
            // Check game status and get session
            checkGameStatus();
            
            // Fallback: Show button after a short delay if user is logged in
            // This ensures the button appears even if status check is slow
            if (IS_LOGGED_IN) {
                setTimeout(() => {
                    if (startGameBtn) {
                        const isHidden = startGameBtn.style.display === 'none' || 
                                        startGameBtn.style.display === '' ||
                                        window.getComputedStyle(startGameBtn).display === 'none';
                        if (isHidden) {
                            showGameReady();
                        }
                    }
                }, 500);
                
                // Additional fallback after 2 seconds
                setTimeout(() => {
                    if (startGameBtn && IS_LOGGED_IN) {
                        showGameReady();
                    }
                }, 2000);
            }
        }
        
        // Check game status
        function checkGameStatus() {
            fetch(`game_api.php?action=check_status&game_name=${GAME_NAME}`)
                .then(response => response.json())
                .then(data => {
                    
                    // Always update credits if available
                    if (data.user_credits !== undefined) {
                        currentUserCredits = data.user_credits || USER_CREDITS;
                        updateCreditsDisplay(currentUserCredits);
                    }
                    
                    // If session exists (even if not active), use it
                    if (data.success && data.session && data.session.id) {
                        currentSessionId = data.session.id;
                        
                        // Check if this is a time-duration session (not always_available)
                        isTimeDurationSession = data.session && !data.session.always_available;
                        
                        // Apply different styling for time-duration sessions
                        applyTimeDurationStyling(isTimeDurationSession);
                        
                        // Update credits required from session if available
                        if (data.session.credits_required) {
                            creditsRequired = data.session.credits_required;
                        }
                    }
                    
                    // Always show button if user is logged in, regardless of active status
                    if (IS_LOGGED_IN) {
                        showGameReady();
                        if (data.message && statusMessage) {
                            statusMessage.textContent = data.message;
                        }
                    } else {
                        if (data.message) {
                            showNoSession(data.message);
                        } else {
                            showNoSession('Game is not currently available.');
                        }
                    }
                })
                .catch(error => {
                    // Even on error, show button if user is logged in
                    if (IS_LOGGED_IN) {
                        showGameReady();
                        if (statusMessage) {
                            statusMessage.textContent = 'Mission ready! Use credits to start.';
                        }
                    } else {
                        showNoSession('Error loading game status.');
                    }
                });
        }
        
        // Show game ready state
        function showGameReady() {
            // Always clear loading text
            if (timerDisplay) {
                timerDisplay.textContent = '';
                timerDisplay.style.display = 'none';
            }
            
            const requiredCredits = creditsRequired || 30;
            
            if (statusMessage) {
                if (IS_LOGGED_IN) {
                    if (IS_CONTEST_ACTIVE) {
                        statusMessage.textContent = `🏆 Contest is LIVE! Play with ${requiredCredits} credits and reach the top 3 to win prizes!`;
                    } else {
                        statusMessage.textContent = `Mission ready! Use ${requiredCredits} credits to start.`;
                    }
                } else {
                    statusMessage.textContent = `Login or Register to start mission (${requiredCredits} credits required).`;
                }
            }
            
            // Always show button if user is logged in
            if (startGameBtn && IS_LOGGED_IN) {
                startGameBtn.style.display = 'flex';
                startGameBtn.style.visibility = 'visible';
                
                if (currentUserCredits >= requiredCredits) {
                    startGameBtn.innerHTML = `🚀 START MISSION - ⚡ ${requiredCredits} Credits`;
                    startGameBtn.disabled = false;
                    startGameBtn.style.opacity = '1';
                    startGameBtn.style.cursor = 'pointer';
                } else {
                    startGameBtn.innerHTML = `🔒 LOCKED - ⚡ ${requiredCredits} Credits Required`;
                    startGameBtn.disabled = true;
                    startGameBtn.style.opacity = '0.6';
                    startGameBtn.style.cursor = 'not-allowed';
                }
            } else if (startGameBtn && !IS_LOGGED_IN) {
                startGameBtn.style.display = 'none';
            }
        }
        
        // Show no session message
        function showNoSession(message = '') {
            if (timerDisplay) timerDisplay.textContent = '';
            if (statusMessage) {
                statusMessage.textContent = message || 'Game is not currently available.';
            }
            if (startGameBtn) {
                startGameBtn.style.display = 'none';
            }
        }
        
        // Show credits confirmation modal
        function showCreditsConfirmation() {
            if (creditsToPayEl) {
                creditsToPayEl.textContent = creditsRequired;
            }
            if (currentCreditsDisplay) {
                currentCreditsDisplay.textContent = currentUserCredits;
            }
            // Hide game status overlay when showing credits modal
            if (gameStatusOverlay) {
                gameStatusOverlay.classList.add('hidden');
            }
            if (creditsModal) {
                creditsModal.classList.remove('hidden');
                creditsModal.style.display = 'flex';
                creditsModal.style.pointerEvents = 'auto';
                // Ensure buttons are clickable
                if (confirmPayBtn) {
                    confirmPayBtn.style.pointerEvents = 'auto';
                    confirmPayBtn.style.cursor = 'pointer';
                }
                if (cancelPayBtn) {
                    cancelPayBtn.style.pointerEvents = 'auto';
                    cancelPayBtn.style.cursor = 'pointer';
                }
            }
        }
        
        // Hide credits confirmation modal
        function hideCreditsConfirmation() {
            if (creditsModal) {
                creditsModal.classList.add('hidden');
                creditsModal.style.display = 'none';
            }
        }
        
        // Show game status overlay (for when user cancels payment)
        function showGameStatusOverlay() {
            if (gameStatusOverlay) {
                gameStatusOverlay.classList.remove('hidden');
            }
        }
        
        // Show exit confirmation modal
        function showExitConfirmation() {
            if (exitModal) {
                exitModal.classList.remove('hidden');
                exitModal.style.display = 'flex';
            }
        }
        
        // Hide exit confirmation modal
        function hideExitConfirmation() {
            if (exitModal) {
                exitModal.classList.add('hidden');
                exitModal.style.display = 'none';
            }
        }
        
        // Fetch user total score for Cosmos Captain game only
        function fetchUserTotalScore() {
            return fetch(`game_api.php?action=get_user_score&game_name=${GAME_NAME}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        userTotalScore = data.user_total_points || 0;
                        updateTotalScoreDisplay();
                        return userTotalScore;
                    }
                    return 0;
                })
                .catch(error => {
                    return 0;
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
            
            // If no session ID, try to get one first
            if (!currentSessionId) {
                // Try to get session from API
                return fetch(`game_api.php?action=check_status&game_name=${GAME_NAME}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.session && data.session.id) {
                            currentSessionId = data.session.id;
                            if (data.session.credits_required) {
                                creditsRequired = data.session.credits_required;
                            }
                            // Now proceed with starting the game
                            return proceedWithGameStart();
                        } else {
                            alert('No active game session. Please contact admin or try again later.');
                            return false;
                        }
                    })
                    .catch(error => {
                        alert('Failed to get game session. Please try again.');
                        return false;
                    });
            }
            
            return proceedWithGameStart();
        }
        
        // Proceed with game start after session is confirmed
        function proceedWithGameStart() {
            // Deduct credits
            const formData = new FormData();
            formData.append('game_name', GAME_NAME);
            formData.append('session_id', currentSessionId);
            
            return fetch('game_api.php?action=deduct_credits', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Use creditsRequired (which we already know) instead of data.credits_used
                    creditsUsed = creditsRequired;
                    currentUserCredits = data.credits_remaining || (currentUserCredits - creditsRequired);
                    updateCreditsDisplay(currentUserCredits);
                    gameStarted = true;
                    // Update credits display in overlay
                    const userCreditsDisplay = document.getElementById('user-credits-display');
                    if (userCreditsDisplay) {
                        userCreditsDisplay.textContent = currentUserCredits.toLocaleString();
                    }
                    // Show total score box
                    const totalScoreBox = document.getElementById('total-score-box');
                    if (totalScoreBox) {
                        totalScoreBox.style.display = 'block';
                    }
                    // Show exit button
                    if (exitBtn) {
                        exitBtn.style.display = 'block';
                    }
                    // Initialize exit prevention
                    preventExitDuringGame();
                    // Start session validity checking
                    if (IS_LOGGED_IN && !sessionCheckInterval) {
                        sessionCheckInterval = setInterval(checkSessionValidity, 5000); // Check every 5 seconds
                        sessionCheckActive = true;
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
                alert('Failed to start game. Please try again.');
                return false;
            });
        }
        
        // Save score when game ends
        function saveScore(score) {
            if (!IS_LOGGED_IN) {
                return Promise.resolve();
            }
            
            if (!gameStarted || creditsUsed === 0 || !currentSessionId) {
                return Promise.resolve();
            }
            
            const finalScore = Math.floor(score || 0);
            
            const formData = new FormData();
            formData.append('score', finalScore);
            formData.append('session_id', currentSessionId);
            formData.append('credits_used', creditsUsed);
            formData.append('game_name', GAME_NAME);
            formData.append('is_demo', 'false');
            
            return fetch('game_api.php?action=save_score', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update total score from response - use game-specific total_score (Cosmos Captain only)
                    if (data.total_score !== undefined) {
                        userTotalScore = data.total_score;
                        updateTotalScoreDisplay();
                        return data.total_score;
                    } else {
                        // If not in response, fetch it
                        return fetchUserTotalScore();
                    }
                } else {
                    return 0;
                }
            })
            .catch(error => {
                return 0;
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
        
        // Apply different styling for time-duration sessions
        function applyTimeDurationStyling(isTimeDuration) {
            const scoreElement = document.getElementById('score');
            const scoreLabel = scoreElement ? scoreElement.parentElement.previousElementSibling : null;
            
            if (isTimeDuration) {
                // Time-duration session: Gold/Yellow styling
                if (scoreElement) {
                    scoreElement.style.color = '#FFD700';
                    scoreElement.style.textShadow = '0 0 15px rgba(255, 215, 0, 0.8), 0 0 30px rgba(255, 215, 0, 0.5)';
                    scoreElement.style.fontWeight = '900';
                }
                if (scoreLabel) {
                    scoreLabel.style.color = '#FFD700';
                    scoreLabel.style.textShadow = '0 0 10px rgba(255, 215, 0, 0.6)';
                }
            } else {
                // Normal session: Default cyan styling
                if (scoreElement) {
                    scoreElement.style.color = '';
                    scoreElement.style.textShadow = '';
                    scoreElement.style.fontWeight = '';
                }
                if (scoreLabel) {
                    scoreLabel.style.color = '';
                    scoreLabel.style.textShadow = '';
                }
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

        // Check session validity periodically
        function checkSessionValidity() {
            if (!IS_LOGGED_IN || !gameStarted || !gameActive) return;
            
            fetch('game_api.php?action=check_session_validity')
                .then(response => response.json())
                .then(data => {
                    if (data.success && !data.valid) {
                        // Session invalid - user logged in elsewhere
                        handleAnotherDeviceLogout();
                    }
                })
                .catch(error => {
                    // Session check error - silent fail
                });
        }
        
        // Handle logout from another device
        function handleAnotherDeviceLogout() {
            // Stop game
            gameActive = false;
            gameStarted = false;
            
            // Stop session checking
            if (sessionCheckInterval) {
                clearInterval(sessionCheckInterval);
                sessionCheckInterval = null;
            }
            
            // Save current score if game was active
            if (score > 0 && creditsUsed > 0 && currentSessionId) {
                saveScore(score).catch(() => {});
            }
            
            // Show logout modal
            if (anotherDeviceModal) {
                anotherDeviceModal.classList.remove('hidden');
                anotherDeviceModal.style.display = 'flex';
            }
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

        // Function to show score saved modal
        function showScoreSavedModal(missionScore, totalScore) {
            if (scoreSavedModal && savedMissionScoreEl && savedTotalScoreEl) {
                savedMissionScoreEl.textContent = missionScore.toLocaleString();
                savedTotalScoreEl.textContent = totalScore.toLocaleString();
                scoreSavedModal.classList.remove('hidden');
                scoreSavedModal.style.display = 'flex';
            }
        }
        
        // Function to hide score saved modal
        function hideScoreSavedModal() {
            if (scoreSavedModal) {
                scoreSavedModal.classList.add('hidden');
                scoreSavedModal.style.display = 'none';
            }
        }
        
        // Function to save score and show modal
        async function saveScoreAndShowModal() {
            if (!gameStarted || creditsUsed === 0 || !currentSessionId || score <= 0) {
                return;
            }
            
            try {
                const savedTotalScore = await saveScore(score);
                if (savedTotalScore !== undefined && savedTotalScore > 0) {
                    userTotalScore = savedTotalScore;
                    updateTotalScoreDisplay();
                    // Show score saved modal
                    showScoreSavedModal(score, savedTotalScore);
                } else {
                    // Fetch total score if not returned
                    const fetchedTotal = await fetchUserTotalScore();
                    showScoreSavedModal(score, fetchedTotal || userTotalScore);
                }
            } catch (error) {
                // Error saving score - silent fail
            }
        }
        
        async function gameOver() {
            gameActive = false;
            playGameOverSound();
            createExplosion(ship.x, ship.y, '#ff4d4d');
            msgTitle.textContent = "GAME OVER";
            
            // Save score and show modal when game ends
            if (gameStarted && creditsUsed > 0 && currentSessionId && score > 0) {
                await saveScoreAndShowModal();
            }
            
            // Update final score display
            const finalScoreDisplay = document.getElementById('final-score-display');
            if (finalScoreDisplay) {
                finalScoreDisplay.textContent = score.toLocaleString();
                // Apply time-duration styling to final score if in time-duration session
                if (isTimeDurationSession) {
                    finalScoreDisplay.style.color = '#FFD700';
                    finalScoreDisplay.style.textShadow = '0 0 15px rgba(255, 215, 0, 0.8)';
                }
            }
            
            // Update message text
            msgText.textContent = "Commander, the hull has failed. Mission terminated.";
            
            if (IS_LOGGED_IN) {
                // Show total score after saving
                setTimeout(() => {
                    const totalScoreDisplay = document.getElementById('total-score-display');
                    const totalScoreContainer = document.getElementById('total-score-container');
                    if (totalScoreDisplay && totalScoreContainer) {
                        totalScoreDisplay.textContent = userTotalScore.toLocaleString();
                        totalScoreContainer.style.display = 'block';
                    }
                }, 500);
            }
            
            // Show prize claim link if in time-duration session
            const prizeClaimLink = document.getElementById('prize-claim-link');
            if (prizeClaimLink && isTimeDurationSession && currentSessionId) {
                prizeClaimLink.href = `prize_claim.php?game=${GAME_NAME}&session_id=${currentSessionId}`;
                prizeClaimLink.style.display = 'block';
            }
            
            // Show back to menu button and hide start button
            const backToMenuBtn = document.getElementById('back-to-menu-btn');
            if (backToMenuBtn) {
                backToMenuBtn.style.display = 'block';
            }
            if (startBtn) {
                startBtn.style.display = 'none';
            }
            
            messageBox.style.display = 'block';
            
            // Hide total score box in HUD
            const totalScoreBox = document.getElementById('total-score-box');
            if (totalScoreBox) {
                totalScoreBox.style.display = 'none';
            }
            
            // Hide exit button
            if (exitBtn) {
                exitBtn.style.display = 'none';
            }
            
            // Reset game started flag for next play
            gameStarted = false;
            creditsUsed = 0;
        }

        // Initialize start game button (pre-game overlay)
        if (startGameBtn) {
            startGameBtn.addEventListener('click', async (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                if (!IS_LOGGED_IN) {
                    alert('Please login to play Cosmos Captain.');
                    window.location.href = 'index.php';
                    return;
                }
                
                if (gameStarted) {
                    return;
                }
                
                // Check if user has enough credits
                if (currentUserCredits < creditsRequired) {
                    alert(`Insufficient credits! You need ${creditsRequired} credits to play.`);
                    return;
                }
                
                // Show credits payment modal instead of browser confirm
                showCreditsConfirmation();
            });
        }
        
        // Back to menu button handler
        if (backToMenuBtn) {
            backToMenuBtn.addEventListener('click', async (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                // Save score and show modal when back to menu is clicked
                if (gameStarted && creditsUsed > 0 && currentSessionId && score > 0) {
                    gameActive = false;
                    await saveScoreAndShowModal();
                } else {
                    // If no score to save, just redirect
                    window.location.href = 'index.php';
                }
            });
        }
        
        // Score saved modal OK button handler
        if (scoreSavedOkBtn) {
            scoreSavedOkBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                hideScoreSavedModal();
                
                // Reset game state
                gameStarted = false;
                creditsUsed = 0;
                score = 0;
                gameActive = false;
                
                // Show credits payment modal to play again
                if (IS_LOGGED_IN && currentUserCredits >= creditsRequired) {
                    showCreditsConfirmation();
                } else {
                    // If not enough credits, redirect to index
                    window.location.href = 'index.php';
                }
            });
        }
        
        // Close score saved modal when clicking outside
        if (scoreSavedModal) {
            scoreSavedModal.addEventListener('click', (e) => {
                if (e.target === scoreSavedModal) {
                    hideScoreSavedModal();
                    // Reset game state
                    gameStarted = false;
                    creditsUsed = 0;
                    score = 0;
                    gameActive = false;
                    // Show credits payment modal
                    if (IS_LOGGED_IN && currentUserCredits >= creditsRequired) {
                        showCreditsConfirmation();
                    } else {
                        window.location.href = 'index.php';
                    }
                }
            });
        }
        
        // Another device logout modal OK button
        if (anotherDeviceOkBtn) {
            anotherDeviceOkBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                // Redirect to index page
                window.location.href = 'index.php?logout=another_device';
            });
        }
        
        // Close another device modal when clicking outside (shouldn't happen, but just in case)
        if (anotherDeviceModal) {
            anotherDeviceModal.addEventListener('click', (e) => {
                if (e.target === anotherDeviceModal) {
                    window.location.href = 'index.php?logout=another_device';
                }
            });
        }
        
        // Initialize start button (for game over restart - hidden by default)
        if (startBtn) {
            startBtn.addEventListener('click', async (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                if (!IS_LOGGED_IN) {
                    alert('Please login to play Cosmos Captain.');
                    window.location.href = 'index.php';
                    return;
                }
                
                if (gameStarted) {
                    // Game already started, just hide message box and continue
                    messageBox.style.display = 'none';
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
        }
        
        // Confirm payment button - use multiple event types for better compatibility
        if (confirmPayBtn) {
            // Click handler
            confirmPayBtn.addEventListener('click', async (e) => {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                hideCreditsConfirmation();
                
                // Deduct credits and start game
                const canStart = await startGameWithCredits();
                if (canStart) {
                    // Hide game status overlay and message box
                    if (gameStatusOverlay) {
                        gameStatusOverlay.classList.add('hidden');
                    }
                    if (messageBox) {
                        messageBox.classList.add('hidden');
                    }
                    resetGame();
                    gameActive = true;
                }
            });
            
            // Touch handler for mobile
            confirmPayBtn.addEventListener('touchstart', async (e) => {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                hideCreditsConfirmation();
                
                // Deduct credits and start game
                const canStart = await startGameWithCredits();
                if (canStart) {
                    // Hide game status overlay and message box
                    if (gameStatusOverlay) {
                        gameStatusOverlay.classList.add('hidden');
                    }
                    if (messageBox) {
                        messageBox.classList.add('hidden');
                    }
                    resetGame();
                    gameActive = true;
                }
            }, { passive: false });
        }
        
        // Cancel payment button - use multiple event types for better compatibility
        if (cancelPayBtn) {
            // Click handler
            cancelPayBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                hideCreditsConfirmation();
                // Show game status overlay again
                showGameStatusOverlay();
            });
            
            // Touch handler for mobile
            cancelPayBtn.addEventListener('touchstart', (e) => {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                hideCreditsConfirmation();
                // Show game status overlay again
                showGameStatusOverlay();
            }, { passive: false });
        }
        
        // Close modal when clicking outside
        if (creditsModal) {
            creditsModal.addEventListener('click', (e) => {
                if (e.target === creditsModal) {
                    hideCreditsConfirmation();
                }
            });
        }
        
        // Exit button handler
        if (exitBtn) {
            exitBtn.addEventListener('click', async (e) => {
                e.preventDefault();
                e.stopPropagation();
                if (gameActive && gameStarted) {
                    // Save score and show modal when exit button is clicked
                    if (creditsUsed > 0 && currentSessionId && score > 0) {
                        gameActive = false;
                        await saveScoreAndShowModal();
                    } else {
                        // If no score, just show exit confirmation
                        showExitConfirmation();
                    }
                }
            });
        }
        
        // Initialize exit prevention when game starts
        function initExitPrevention() {
            if (gameActive && gameStarted) {
                preventExitDuringGame();
            }
        }
        
        // Function to show exit confirmation
        function showExitConfirmation() {
            if (exitModal) {
                exitModal.classList.remove('hidden');
                exitModal.style.display = 'flex';
            }
        }
        
        // Function to hide exit confirmation
        function hideExitConfirmation() {
            if (exitModal) {
                exitModal.classList.add('hidden');
                exitModal.style.display = 'none';
            }
        }
        
        // Prevention of accidental exit during gameplay
        function preventExitDuringGame() {
            // Push a dummy state to history to catch the back button
            window.history.pushState({ inGame: true }, "");
        }
        
        // Exit confirmation handlers
        if (exitYesBtn) {
            exitYesBtn.addEventListener('click', async (e) => {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                hideExitConfirmation();
                
                // Set game as not active
                gameActive = false;
                
                // Hide exit button
                if (exitBtn) {
                    exitBtn.style.display = 'none';
                }
                
                // Save score and show modal when user clicks "YES, SAVE & EXIT"
                if (creditsUsed > 0 && currentSessionId && score > 0) {
                    await saveScoreAndShowModal();
                } else {
                    // If no score to save, just redirect
                    window.location.replace('index.php');
                }
            });
        }
        
        if (exitNoBtn) {
            exitNoBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                hideExitConfirmation();
                // Re-enable exit prevention
                if (gameActive && gameStarted) {
                    preventExitDuringGame();
                }
            });
        }
        
        // Close exit modal when clicking outside
        if (exitModal) {
            exitModal.addEventListener('click', (e) => {
                if (e.target === exitModal) {
                    hideExitConfirmation();
                    // Re-enable exit prevention
                    if (gameActive && gameStarted) {
                        preventExitDuringGame();
                    }
                }
            });
        }
        
        // Handle browser back button
        window.addEventListener('popstate', async function(event) {
            // Save score and show modal when back button is pressed
            if (gameActive && gameStarted && creditsUsed > 0 && currentSessionId && score > 0) {
                gameActive = false;
                await saveScoreAndShowModal();
            } else if (gameActive && gameStarted) {
                showExitConfirmation();
            }
        });
        
        // Handle beforeunload (browser close/refresh)
        // Do NOT save score automatically - user must click "YES, SAVE & EXIT"
        window.addEventListener('beforeunload', function(e) {
            // Just prevent default to show browser warning, but don't save score
            if (gameActive && gameStarted && creditsUsed > 0) {
                // Browser will show its own confirmation dialog
                // Score will only be saved if user clicks "YES, SAVE & EXIT" button
            }
        });
        
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
        
        // Ensure button shows immediately when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                if (IS_LOGGED_IN && startGameBtn) {
                    setTimeout(() => {
                        showGameReady();
                    }, 100);
                }
            });
        } else {
            // DOM already loaded
            if (IS_LOGGED_IN && startGameBtn) {
                setTimeout(() => {
                    showGameReady();
                }, 100);
            }
        }

        init();
    </script>
</body>
</html>



