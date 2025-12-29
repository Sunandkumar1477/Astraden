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

// Fetch game settings
$game_stmt = $conn->prepare("SELECT is_contest_active, is_claim_active, game_mode, contest_first_prize, contest_second_prize, contest_third_prize FROM games WHERE game_name = 'earth-defender'");
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
    <!-- Favicon - Must be early in head for proper display -->
    <link rel="icon" type="image/svg+xml" href="Alogo.svg">
    <link rel="shortcut icon" type="image/svg+xml" href="Alogo.svg">
    <link rel="alternate icon" type="image/png" href="Alogo.svg">
    <link rel="apple-touch-icon" sizes="180x180" href="Alogo.svg">
    <link rel="icon" type="image/svg+xml" sizes="any" href="Alogo.svg">
    <title>Realistic Earth Defense</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            overflow: hidden;
            background-color: #000;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            user-select: none;
            -webkit-user-select: none;
            touch-action: none; /* Critical for mobile game controls */
            /* Custom Sci-Fi Gun Cursor (Visible on Desktop) */
            cursor: url('data:image/svg+xml;utf8,<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg"><g stroke="%2300ffff" stroke-width="2"><circle cx="16" cy="16" r="12" fill="none"/><path d="M16 4 V10 M16 22 V28 M4 16 H10 M22 16 H28"/><circle cx="16" cy="16" r="2" fill="%23ff3333" stroke="none"/></g></svg>') 16 16, crosshair;
        }

        #canvas-container {
            width: 100vw;
            height: 100vh;
            position: absolute;
            top: 0;
            left: 0;
            z-index: 1;
        }

        #ui-layer {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 2;
            pointer-events: none; /* Let clicks pass through to canvas */
        }

        #contest-timer {
            position: absolute;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            color: #00ffff;
            text-shadow: 0 0 10px #00ffff;
            font-size: 20px;
            font-weight: bold;
            background: rgba(0, 20, 40, 0.8);
            padding: 12px 25px;
            border: 2px solid #00aaaa;
            border-radius: 8px;
            pointer-events: none;
            z-index: 10;
            display: none;
            font-family: 'Orbitron', monospace;
        }

        /* Mobile Timer Display (Compact, near exit button) */
        #contest-timer-mobile {
            position: absolute;
            top: 50px;
            right: 10px;
            color: #00ffff;
            text-shadow: 0 0 8px rgba(0, 255, 255, 0.6);
            font-size: 14px;
            font-weight: 700;
            background: rgba(0, 20, 40, 0.75);
            padding: 6px 12px;
            border: 1px solid rgba(0, 170, 170, 0.4);
            border-radius: 6px;
            pointer-events: none;
            z-index: 1001;
            display: none;
            font-family: 'Orbitron', monospace;
            white-space: nowrap;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(3px);
        }

        #hud {
            position: absolute;
            top: 20px;
            left: 20px;
            color: #00ffff;
            text-shadow: 0 0 10px rgba(0, 255, 255, 0.5);
            font-size: 18px;
            display: flex;
            flex-direction: column;
            gap: 15px;
            pointer-events: none;
            z-index: 100;
        }

        .hud-item {
            background: rgba(0, 20, 40, 0.85);
            padding: 12px 25px;
            border: 1px solid rgba(0, 170, 170, 0.5);
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(5px);
        }

        /* Mobile Optimized HUD (Compact for gameplay) */
        @media (max-width: 768px) {
            /* Hide desktop timer on mobile */
            #contest-timer {
                display: none !important;
            }

            /* Show mobile timer below exit button */
            #contest-timer-mobile {
                display: block !important;
            }

            #hud {
                top: 10px;
                left: 10px;
                gap: 8px;
                width: auto;
            }

            .hud-item {
                padding: 6px 12px;
                font-size: 14px;
                gap: 8px;
                border-radius: 6px;
                background: rgba(0, 10, 20, 0.6);
            }

            /* Hide non-essential items on mobile during gameplay to maximize space */
            #hud-total-score-item,
            .hud-item:nth-last-child(1) { /* This is the control info item */
                display: none !important;
            }

            .health-bar-container {
                width: 100px;
                height: 8px;
            }

            #exit-game-btn-hud {
                top: 10px;
                right: 10px;
                padding: 6px 12px;
                font-size: 12px;
                border-radius: 6px;
            }

            #bomb-container {
                bottom: 20px;
                gap: 8px;
            }

            #bomb-btn {
                padding: 10px 20px;
                font-size: 1rem;
                max-width: 200px;
            }

            #bomb-count-display {
                font-size: 0.9rem;
                padding: 4px 12px;
            }
        }

        /* Desktop: Hide mobile timer, show desktop timer */
        @media (min-width: 769px) {
            #contest-timer-mobile {
                display: none !important;
            }
        }

        @media (min-width: 769px) {
            #hud {
                top: 40px;
                left: 40px;
                gap: 20px;
            }
            
            .hud-item {
                padding: 15px 30px;
                font-size: 20px;
            }
            
            #exit-game-btn-hud {
                top: 40px;
                right: 40px;
                padding: 15px 30px;
                font-size: 16px;
            }
        }

        #exit-game-btn-hud {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255, 51, 51, 0.15);
            border: 2px solid #ff3333;
            color: #ff3333;
            padding: 10px 20px;
            border-radius: 8px;
            font-family: 'Orbitron', sans-serif;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            z-index: 1001;
            transition: all 0.3s;
            pointer-events: auto;
            display: none; /* Hidden until game starts */
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 0 10px rgba(255, 51, 51, 0.2);
        }

        #exit-game-btn-hud:hover {
            background: rgba(255, 51, 51, 0.3);
            color: white;
            box-shadow: 0 0 20px rgba(255, 51, 51, 0.5);
            transform: scale(1.05);
        }

        /* Bomb UI Specifics */
        #bomb-container {
            position: absolute;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
            pointer-events: auto;
            width: 90%;
            max-width: 350px;
            z-index: 100;
        }

        #bomb-btn {
            background: linear-gradient(135deg, #ff3333, #880000);
            border: 2px solid #ff0000;
            color: white;
            padding: 15px 30px;
            width: 100%;
            font-size: 1.2rem;
            font-weight: 800;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 2px;
            border-radius: 12px;
            box-shadow: 0 0 20px rgba(255, 0, 0, 0.4);
            transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            font-family: 'Orbitron', sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }

        #bomb-btn:hover {
            transform: translateY(-5px) scale(1.03);
            box-shadow: 0 10px 30px rgba(255, 0, 0, 0.6);
            filter: brightness(1.2);
        }

        #bomb-btn:active {
            transform: translateY(2px) scale(0.95);
            background: #ff0000;
        }

        #bomb-count-display {
            color: #ffaa00;
            font-size: 1.1rem;
            font-weight: bold;
            text-shadow: 0 0 10px rgba(255, 170, 0, 0.5);
            background: rgba(0, 0, 0, 0.7);
            padding: 8px 20px;
            border-radius: 20px;
            border: 1px solid rgba(255, 170, 0, 0.3);
            font-family: 'Orbitron', sans-serif;
        }

        @media (min-width: 769px) {
            #bomb-container {
                bottom: 50px;
                max-width: 400px;
            }
            
            #bomb-btn {
                padding: 20px 40px;
                font-size: 1.4rem;
            }
        }

        #game-over {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            color: white;
            display: none;
            flex-direction: column;
            align-items: center;
            background: rgba(10, 0, 0, 0.98);
            padding: 50px;
            border: 3px solid #ff3333;
            border-radius: 20px;
            pointer-events: auto;
            cursor: default;
            width: 95%;
            max-width: 500px;
            box-shadow: 0 0 80px rgba(255, 51, 51, 0.5);
            font-family: 'Orbitron', sans-serif;
            z-index: 2000;
        }

        @media (min-width: 769px) {
            #game-over {
                padding: 70px;
                max-width: 600px;
            }
        }

        #game-over h1 { 
            margin: 0 0 15px 0; 
            color: #ff3333; 
            font-size: 2.2rem; 
            letter-spacing: 3px;
            text-shadow: 0 0 20px rgba(255, 51, 51, 0.5);
        }

        .final-stats {
            background: rgba(255, 255, 255, 0.05);
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .final-stats p { 
            font-size: 1.1rem; 
            color: #ccc; 
            margin: 0;
        }
        
        .health-bar-container {
            width: 150px;
            height: 10px;
            background: #333;
            border-radius: 5px;
            overflow: hidden;
        }

        #health-fill {
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, #00ff00, #ffff00);
            transition: width 0.3s ease;
        }
        
        #message-area {
            position: absolute;
            top: 30%;
            width: 100%;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            color: #ffff00;
            text-shadow: 0 0 20px black;
            opacity: 0;
            transition: opacity 0.5s;
            pointer-events: none;
            padding: 0 20px;
        }
        
        /* Game Timer and Status */
        #game-status-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at center, rgba(10, 20, 30, 0.95) 0%, rgba(0, 5, 10, 0.98) 100%);
            z-index: 1000;
            display: flex;
            flex-direction: column;
            pointer-events: auto;
            align-items: center;
            justify-content: center;
            color: #00ffff;
            text-align: center;
            padding: 40px 20px;
            overflow-y: auto;
        }

        #game-status-overlay.hidden {
            display: none !important;
            pointer-events: none;
        }

        .status-title {
            font-size: clamp(2rem, 5vw, 3.5rem);
            font-weight: 900;
            margin-bottom: 30px;
            text-transform: uppercase;
            letter-spacing: 5px;
            color: #ff3333;
            text-shadow: 0 0 20px rgba(255, 51, 51, 0.4), 2px 2px 0px #000;
            font-family: 'Orbitron', sans-serif;
        }

        .prize-pool {
            background: rgba(0, 255, 255, 0.05);
            border: 1px solid rgba(0, 255, 255, 0.2);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.5);
        }

        .prize-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(0, 255, 255, 0.1);
            font-size: 1.1rem;
        }

        .prize-item:last-child {
            border-bottom: none;
        }

        .timer-display {
            font-size: clamp(3rem, 8vw, 5rem);
            font-weight: 900;
            color: #00ffff;
            margin: 20px 0;
            font-family: 'Orbitron', monospace;
            text-shadow: 0 0 30px rgba(0, 255, 255, 0.5);
        }

        .status-message {
            font-size: 1.2rem;
            color: rgba(0, 255, 255, 0.9);
            margin: 15px 0;
            max-width: 600px;
            line-height: 1.5;
        }

        .credits-info {
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 30px;
            background: rgba(255, 215, 0, 0.1);
            padding: 10px 25px;
            border-radius: 30px;
            border: 1px solid rgba(255, 215, 0, 0.3);
        }

        .game-btn, .start-game-btn, .demo-game-btn, .instructions-toggle-btn {
            padding: 16px 32px;
            font-size: 1.1rem;
            font-weight: 700;
            border-radius: 12px;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 2px;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            font-family: 'Orbitron', sans-serif;
            border: 2px solid transparent;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            width: 100%;
            max-width: 380px;
            text-decoration: none;
            outline: none;
            box-sizing: border-box;
            user-select: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            margin: 0;
        }

        .game-btn:hover, .start-game-btn:hover, .demo-game-btn:hover, .instructions-toggle-btn:hover {
            transform: translateY(-5px) scale(1.03);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.6);
            filter: brightness(1.2);
        }

        .game-btn:active, .start-game-btn:active, .demo-game-btn:active, .instructions-toggle-btn:active {
            transform: translateY(2px) scale(0.98);
        }

        @media (min-width: 769px) {
            .game-btn-container {
                flex-direction: row;
                flex-wrap: wrap;
                justify-content: center;
                max-width: 900px;
                gap: 25px;
                margin: 40px auto;
            }
            
            .game-btn, .start-game-btn, .demo-game-btn, .instructions-toggle-btn {
                flex: 0 1 340px; /* Force uniform sizing on desktop */
            }
            
            .prize-pool {
                display: grid;
                grid-template-columns: 1fr;
                max-width: 700px;
                gap: 0;
            }
            
            .status-title {
                margin-bottom: 40px;
            }
        }

        .start-game-btn {
            background: linear-gradient(135deg, #00ffff, #9d4edd);
            border: none;
            color: white;
            box-shadow: 0 0 30px rgba(0, 255, 255, 0.3);
        }

        .start-game-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }

        .demo-game-btn {
            background: rgba(255, 255, 255, 0.08);
            border: 2px solid rgba(255, 255, 255, 0.4);
            color: white;
            padding: 16px 32px;
            font-size: 1.1rem;
            font-weight: 700;
            border-radius: 12px;
            cursor: pointer !important;
            text-transform: uppercase;
            letter-spacing: 2px;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            pointer-events: auto !important;
            z-index: 1002 !important;
            position: relative !important;
            display: flex !important;
            align-items: center;
            justify-content: center;
            visibility: visible !important;
            width: 100%;
            max-width: 380px;
            font-family: 'Orbitron', sans-serif;
            box-sizing: border-box;
        }

        .demo-game-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: #fff;
            transform: translateY(-5px) scale(1.03);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.6);
        }
        .demo-game-btn:active {
            transform: scale(0.98);
        }

        /* Custom Modal Styles */
        .custom-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 10000;
            backdrop-filter: blur(5px);
            font-family: 'Orbitron', sans-serif;
            pointer-events: auto; /* Enable clicks on the modal */
        }

        .modal-content {
            background: rgba(10, 20, 30, 0.95);
            border: 2px solid #00ffff;
            border-radius: 15px;
            width: 90%;
            max-width: 400px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 0 30px rgba(0, 255, 255, 0.3);
            animation: modal-appear 0.3s ease-out;
        }

        @keyframes modal-appear {
            from { transform: scale(0.8); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .modal-header h2 {
            color: #ff3333;
            margin: 0 0 20px 0;
            font-size: 1.5rem;
            letter-spacing: 2px;
        }

        .modal-body p {
            color: #fff;
            margin-bottom: 10px;
            font-size: 1rem;
            line-height: 1.4;
        }

        .modal-warning {
            color: #ffaa00 !important;
            font-weight: bold;
            font-size: 0.9rem !important;
        }

        .modal-footer {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 25px;
            width: 100%;
            align-items: center;
        }

        .modal-btn {
            padding: 14px 28px;
            border-radius: 10px;
            font-family: 'Orbitron', sans-serif;
            font-weight: 700;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border: none;
            letter-spacing: 1.5px;
            width: 100%;
            max-width: 320px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-btn.primary {
            background: linear-gradient(135deg, #ff3333, #aa0000);
            color: white;
            box-shadow: 0 0 20px rgba(255, 51, 51, 0.3);
        }

        .modal-btn.secondary {
            background: rgba(0, 255, 255, 0.08);
            color: #00ffff;
            border: 2px solid #00ffff;
        }

        .modal-btn:hover {
            transform: translateY(-4px) scale(1.03);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.5);
            filter: brightness(1.2);
        }

        .modal-btn:active {
            transform: translateY(0);
        }

        /* Contest & Claim Styles */
        .contest-badge {
            background: linear-gradient(135deg, #FFD700, #ff8c00);
            color: #000;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.9rem;
            margin-bottom: 15px;
            text-transform: uppercase;
            box-shadow: 0 0 15px rgba(255, 215, 0, 0.5);
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); box-shadow: 0 0 15px rgba(255, 215, 0, 0.5); }
            50% { transform: scale(1.05); box-shadow: 0 0 25px rgba(255, 215, 0, 0.8); }
            100% { transform: scale(1); box-shadow: 0 0 15px rgba(255, 215, 0, 0.5); }
        }
        .prize-pool {
            background: rgba(255, 215, 0, 0.1);
            border: 1px solid #FFD700;
            border-radius: 10px;
            padding: 15px;
            margin: 15px 0;
            width: 100%;
            max-width: 300px;
        }
        .prize-item {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
            font-size: 0.9rem;
        }
        .claim-section {
            background: rgba(0, 255, 0, 0.1);
            border: 2px solid #00ff00;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            width: 100%;
            max-width: 400px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }
        .claim-btn {
            background: linear-gradient(135deg, #00ff00, #008800);
            color: #000;
            border: none;
            padding: 14px 28px;
            font-weight: 700;
            border-radius: 10px;
            cursor: pointer;
            text-transform: uppercase;
            font-family: 'Orbitron', sans-serif;
            letter-spacing: 1.5px;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            width: 100%;
            max-width: 320px;
        }
        .claim-btn:hover:not(:disabled) {
            transform: translateY(-4px) scale(1.03);
            box-shadow: 0 10px 25px rgba(0, 255, 0, 0.4);
            filter: brightness(1.2);
        }
        .claim-btn:disabled {
            background: #555;
            cursor: not-allowed;
        }
        
        @media (max-width: 768px) {
            /* Align overlay content to left */
            #game-status-overlay {
                align-items: flex-start !important;
                text-align: left !important;
                padding: 20px 15px !important;
                justify-content: flex-start !important;
                box-sizing: border-box;
            }
            
            /* Contest Badge - Left Aligned Box */
            .contest-badge {
                background: linear-gradient(135deg, #FFD700, #ff8c00);
                color: #000;
                padding: 10px 18px;
                border-radius: 8px;
                font-weight: bold;
                font-size: 0.85rem;
                margin: 0 0 15px 0;
                text-transform: uppercase;
                box-shadow: 0 2px 10px rgba(255, 215, 0, 0.3);
                width: calc(100% - 30px);
                max-width: none;
                box-sizing: border-box;
                text-align: center;
            }
            
            .timer-display {
                font-size: 2rem;
                margin: 15px 0;
                text-align: left;
                width: 100%;
                box-sizing: border-box;
            }
            
            .status-title {
                font-size: 1.5rem;
                text-align: left;
                margin: 0 0 20px 0;
                width: 100%;
                box-sizing: border-box;
            }
            
            /* Hide status message on mobile */
            .status-message {
                display: none !important;
            }
            
            /* Mission Rewards Section - Left Aligned Box */
            .prize-pool {
                background: rgba(255, 215, 0, 0.1);
                border: 2px solid #FFD700;
                border-radius: 10px;
                padding: 15px 18px;
                margin: 0 0 15px 0;
                width: calc(100% - 30px);
                max-width: none;
                box-shadow: 0 2px 10px rgba(255, 215, 0, 0.2);
                text-align: left;
                box-sizing: border-box;
            }
            
            .prize-pool > div:first-child {
                color: #FFD700;
                font-weight: bold;
                margin-bottom: 12px;
                text-transform: uppercase;
                font-size: 0.9rem;
                text-align: left;
            }
            
            .prize-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 8px 0;
                border-bottom: 1px solid rgba(255, 215, 0, 0.2);
                font-size: 0.85rem;
                text-align: left;
            }
            
            .prize-item:last-child {
                border-bottom: none;
            }
            
            /* Credits Info - Left Aligned Box */
            .credits-info {
                font-size: 0.95rem;
                padding: 12px 18px;
                margin: 0 0 15px 0;
                width: calc(100% - 30px);
                max-width: none;
                text-align: left;
                background: rgba(255, 215, 0, 0.1);
                border: 2px solid rgba(255, 215, 0, 0.3);
                border-radius: 10px;
                box-shadow: 0 2px 10px rgba(255, 215, 0, 0.2);
                box-sizing: border-box;
            }
            
            /* Game Buttons Container - Left Aligned */
            .game-btn-container {
                display: flex;
                flex-direction: column;
                align-items: flex-start;
                justify-content: flex-start;
                gap: 12px;
                margin: 0;
                width: calc(100% - 30px);
                max-width: none;
                box-sizing: border-box;
            }
            
            /* All Buttons - Consistent Box Styling */
            .game-btn, 
            .start-game-btn, 
            .demo-game-btn, 
            .instructions-toggle-btn {
                width: 100%;
                max-width: none;
                padding: 14px 20px;
                font-size: 0.95rem;
                font-weight: 700;
                margin: 0;
                text-align: left;
                justify-content: flex-start;
                border-radius: 10px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
                box-sizing: border-box;
                border: 2px solid;
                transition: all 0.3s ease;
                display: flex;
                align-items: center;
                gap: 10px;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            
            .game-btn:hover, 
            .start-game-btn:hover, 
            .demo-game-btn:hover, 
            .instructions-toggle-btn:hover {
                transform: translateX(3px) scale(1.01);
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
            }
            
            .game-btn:active, 
            .start-game-btn:active, 
            .demo-game-btn:active, 
            .instructions-toggle-btn:active {
                transform: translateX(0) scale(0.99);
            }
            
            /* Game Guide Button - Consistent Box */
            .instructions-toggle-btn {
                background: rgba(0, 255, 255, 0.1);
                border: 2px solid #00ffff;
                color: #00ffff;
                padding: 14px 20px;
                font-size: 0.95rem;
            }
            
            .instructions-toggle-btn:hover {
                background: rgba(0, 255, 255, 0.2);
                box-shadow: 0 4px 15px rgba(0, 255, 255, 0.4);
            }
            
            /* Play Demo Button - Consistent Box */
            .demo-game-btn {
                background: rgba(255, 255, 255, 0.1);
                border: 2px solid rgba(255, 255, 255, 0.5);
                color: white;
                padding: 14px 20px;
                font-size: 0.95rem;
            }
            
            .demo-game-btn:hover {
                background: rgba(255, 255, 255, 0.2);
                border-color: rgba(255, 255, 255, 0.8);
                box-shadow: 0 4px 15px rgba(255, 255, 255, 0.3);
            }
            
            /* Back to Home Button - Consistent Box */
            .btn-home {
                background: rgba(157, 78, 221, 0.1);
                border: 2px solid rgba(157, 78, 221, 0.5);
                color: #9d4edd;
                padding: 14px 20px;
                font-size: 0.95rem;
            }
            
            .btn-home:hover {
                background: rgba(157, 78, 221, 0.2);
                border-color: #9d4edd;
                box-shadow: 0 4px 15px rgba(157, 78, 221, 0.4);
            }
            
            /* Claim Section - Left Aligned Box */
            .claim-section {
                background: rgba(0, 255, 0, 0.1);
                border: 2px solid #00ff00;
                border-radius: 10px;
                padding: 18px;
                margin: 15px 0 0 0;
                width: calc(100% - 30px);
                max-width: none;
                text-align: left;
                box-shadow: 0 2px 10px rgba(0, 255, 0, 0.2);
                box-sizing: border-box;
            }
            
            .claim-btn {
                width: 100%;
                max-width: none;
                padding: 14px 20px;
                font-size: 0.95rem;
                margin: 0;
                border-radius: 10px;
                box-sizing: border-box;
                border: 2px solid #00ff00;
            }
        }
        
        /* Instructions Panel Styles */
        .instructions-toggle-btn {
            background: rgba(0, 255, 255, 0.08);
            border: 2px solid #00ffff;
            color: #00ffff;
            padding: 14px 28px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            border-radius: 10px;
            pointer-events: auto !important;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            width: 100%;
            max-width: 340px;
            box-shadow: 0 0 20px rgba(0, 255, 255, 0.2);
            font-family: 'Orbitron', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            box-sizing: border-box;
        }
        
        .instructions-toggle-btn:hover {
            background: rgba(0, 255, 255, 0.2);
            box-shadow: 0 0 30px rgba(0, 255, 255, 0.4);
            transform: translateY(-4px) scale(1.02);
        }
        
        .instructions-toggle-btn:active {
            transform: scale(0.98);
        }
        
        .instructions-panel {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.95);
            z-index: 3000 !important;
            display: none;
            overflow-y: auto;
            pointer-events: auto !important;
        }
        
        .instructions-panel.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .instructions-content {
            background: rgba(10, 10, 15, 0.99);
            border: 2px solid #00ffff;
            border-radius: 20px;
            max-width: 800px;
            width: 95%;
            max-height: 85vh;
            overflow-y: auto;
            padding: 40px;
            margin: 20px;
            box-shadow: 0 0 50px rgba(0, 255, 255, 0.4);
            position: relative;
            scrollbar-width: thin;
            scrollbar-color: #00ffff rgba(0,0,0,0.5);
        }

        @media (min-width: 769px) {
            .instructions-content {
                padding: 60px;
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 40px;
                align-content: start;
            }
            
            .instructions-header {
                grid-column: 1 / -1;
            }
            
            .instruction-section:last-child {
                grid-column: 1 / -1;
            }
        }
        
        .instructions-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(0, 255, 255, 0.3);
        }
        
        .instructions-header h2 {
            color: #00ffff;
            font-size: 1.3rem;
            margin: 0;
            text-shadow: 0 0 10px rgba(0, 255, 255, 0.5);
        }
        
        .close-instructions {
            background: rgba(255, 0, 0, 0.3);
            border: 1px solid #ff3333;
            color: #ff3333;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            font-size: 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            line-height: 1;
        }
        
        .close-instructions:hover {
            background: rgba(255, 0, 0, 0.5);
            transform: scale(1.1);
        }
        
        .instructions-body {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.85rem;
            line-height: 1.6;
        }
        
        .instruction-section {
            margin-bottom: 20px;
        }
        
        .instruction-section h3 {
            color: #00ffff;
            font-size: 1rem;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .important-tip {
            background: rgba(255, 165, 0, 0.15);
            border-left: 3px solid #ffaa00;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            font-size: 0.8rem;
        }
        
        .important-tip strong {
            color: #ffaa00;
        }
        
        .points-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .points-list li {
            margin-bottom: 8px;
            padding-left: 5px;
            font-size: 0.8rem;
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }
        
        .asteroid-icon {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            flex-shrink: 0;
            margin-top: 3px;
        }
        
        .asteroid-icon.normal {
            background: #888888;
            box-shadow: 0 0 5px rgba(136, 136, 136, 0.8);
        }
        
        .asteroid-icon.green {
            background: #00ff00;
            box-shadow: 0 0 8px rgba(0, 255, 0, 0.8);
        }
        
        .points {
            color: #ffaa00;
            font-weight: bold;
        }
        
        .objects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        
        .object-item {
            background: rgba(0, 255, 255, 0.1);
            border: 1px solid rgba(0, 255, 255, 0.3);
            padding: 10px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .object-item strong {
            display: block;
            color: #00ffff;
            font-size: 0.85rem;
        }
        
        .object-item small {
            display: block;
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.7rem;
            margin-top: 2px;
        }
        
        @media (max-width: 768px) {
            .instructions-content {
                padding: 15px;
                margin: 10px;
            }
            
            .instructions-header h2 {
                font-size: 1.1rem;
            }
            
            .instructions-body {
                font-size: 0.75rem;
            }
            
            .points-list li {
                font-size: 0.75rem;
            }
            
            /* Hide status message on mobile */
            .status-message {
                display: none !important;
            }
        }
    </style>
</head>
<body>

    <div id="canvas-container"></div>

    <!-- Game Status Overlay (Timer, Credits Check) -->
    <div id="game-status-overlay">
        <div class="status-title">🛡️ Earth Defender</div>
        
        <?php if ($is_contest_active): ?>
            <div class="contest-badge">🏆 <?php echo $game_mode === 'money' ? 'CASH CONTEST' : 'CREDIT CONTEST'; ?> 🏆</div>
            <div class="prize-pool">
                <div style="color: #FFD700; font-weight: bold; margin-bottom: 10px; text-transform: uppercase;">Mission Rewards</div>
                <?php 
                $unit = $game_mode === 'money' ? '₹' : '';
                $suffix = $game_mode === 'money' ? '' : ' Credits';
                ?>
                <div class="prize-item"><span>🥇 1st Rank:</span> <span style="color: #FFD700; font-weight: bold;"><?php echo $unit . number_format($prizes['1st']) . $suffix; ?></span></div>
                <div class="prize-item"><span>🥈 2nd Rank:</span> <span style="color: #FFD700; font-weight: bold;"><?php echo $unit . number_format($prizes['2nd']) . $suffix; ?></span></div>
                <div class="prize-item"><span>🥉 3rd Rank:</span> <span style="color: #FFD700; font-weight: bold;"><?php echo $unit . number_format($prizes['3rd']) . $suffix; ?></span></div>
            </div>
        <?php endif; ?>

        <div id="timer-display" class="timer-display">Loading...</div>
        <div id="status-message" class="status-message"></div>
        <div class="credits-info">
            Your Credits: <span id="user-credits-display" style="color: <?php echo htmlspecialchars($credits_color); ?>; font-weight: bold;"><?php echo number_format($user_credits); ?></span>
        </div>

        <div class="game-btn-container">
            <button id="instructions-btn" class="instructions-toggle-btn" onclick="window.toggleInstructions(event); return false;">📖 GAME GUIDE</button>
            <button id="start-game-btn" class="start-game-btn" style="display: none;"></button>
            <a href="index.php" class="game-btn btn-home">🏠 BACK TO HOME</a>
        </div>

        <?php if ($is_claim_active && $is_logged_in): ?>
            <div class="claim-section">
                <div style="color: #00ff00; font-weight: bold; text-transform: uppercase; margin-bottom: 10px;">🎁 Claim Your Prize</div>
                <p style="font-size: 0.85rem; margin-bottom: 15px; color: #ccc;">If you ranked in the top 3, claim your credits now!</p>
                <button id="claim-prize-btn" class="claim-btn">Claim Prize</button>
                <div id="claim-message" style="font-size: 0.85rem; margin-top: 10px; display: none;"></div>
            </div>
        <?php endif; ?>
    </div>

    <div id="ui-layer">
        <div id="contest-timer"></div>
        <div id="contest-timer-mobile"></div>
        <div id="hud">
            <div class="hud-item">
                <span>HP</span>
                <div class="health-bar-container">
                    <div id="health-fill"></div>
                </div>
            </div>
            <div class="hud-item">
                <span>SCORE: <span id="score">0</span></span>
                <span id="demo-indicator" style="display: none; color: #ffaa00; font-size: 0.8rem; margin-left: 10px;">[DEMO]</span>
            </div>
            <div class="hud-item" id="hud-total-score-item" style="display: none; color: #00ff00;">
                <span>TOTAL: <span id="hud-total-score">0</span></span>
            </div>
            <div class="hud-item" style="font-size: 12px; opacity: 0.8; flex-direction: column; align-items: flex-start;">
                <div>• Drag to Rotate</div>
                <div>• Tap Asteroids to Shoot</div>
            </div>
        </div>

        <button id="exit-game-btn-hud">EXIT GAME</button>

        <div id="message-area"></div>

        <div id="bomb-container" style="display: flex; flex-direction: column; align-items: center; gap: 10px;">
            <div id="bomb-count-display" style="margin-bottom: 5px;">BOMBS: <span id="bomb-count">3</span></div>
            <button id="bomb-btn">DETONATE (TAP)</button>
        </div>
        
        <div id="game-over">
            <h1>CRITICAL FAILURE</h1>
            <p>Earth has been compromised.</p>
            <div class="final-stats" style="margin-bottom: 20px;">
                <p>Final Score: <span id="final-score" style="color: #ff3333; font-weight: bold;">0</span></p>
                <p id="total-score-container" style="display: none; color: #00ff00; font-weight: bold; margin-top: 5px;">Total Score: <span id="total-score">0</span></p>
            </div>
            <div class="game-btn-container">
                <button class="game-btn btn-primary" onclick="location.reload()">🎮 REBOOT SYSTEM</button>
                <a href="index.php" class="game-btn btn-home">🏠 BACK TO HOME</a>
            </div>
        </div>

        <!-- Custom Exit Confirmation Modal -->
        <div id="exit-confirm-modal" class="custom-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>⚠️ SYSTEM ALERT</h2>
                </div>
                <div class="modal-body">
                    <p>ARE YOU SURE YOU WANT TO CLOSE THIS GAME?</p>
                    <p class="modal-warning">Your game will end and your score will be saved automatically.</p>
                </div>
                <div class="modal-footer">
                    <button id="exit-no-btn" class="modal-btn secondary">NO, CONTINUE PLAYING</button>
                    <button id="exit-yes-btn" class="modal-btn primary">YES, SAVE & EXIT</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Game Instructions Panel - Outside ui-layer so it can be clicked -->
    <div id="instructions-panel" class="instructions-panel">
        <div class="instructions-content">
            <div class="instructions-header">
                <h2>🛡️ EARTH DEFENDER - GAME GUIDE</h2>
                <button class="close-instructions" onclick="window.toggleInstructions(); return false;">×</button>
            </div>
            <div class="instructions-body">
                <div class="instruction-section">
                    <h3>🎮 THIS IS A 3D GAME</h3>
                    <p class="important-tip">⚠️ <strong>IMPORTANT:</strong> Rotate the view! If you're shooting from front view, asteroids from other angles cannot be blasted most times. Always rotate to see all asteroids!</p>
                </div>
                
                <div class="instruction-section">
                    <h3>🎯 HOW TO GET POINTS</h3>
                    <ul class="points-list">
                        <li>
                            <span class="asteroid-icon normal">●</span>
                            <strong>Normal Asteroid (Gray):</strong> Shoot = <span class="points">+100 Points</span>
                        </li>
                        <li>
                            <span class="asteroid-icon green">●</span>
                            <strong>Green Asteroid:</strong> Shoot = <span class="points">+50 Points</span> + Health +15 + Bomb (if health full)
                        </li>
                        <li>
                            <strong>Bomb Detonation:</strong> Destroys all asteroids = <span class="points">+50 Points</span> per asteroid destroyed
                        </li>
                    </ul>
                </div>
                
                <div class="instruction-section">
                    <h3>💣 HOW TO GET DETONATE BOMB</h3>
                    <ul class="points-list">
                        <li>Start with <strong>3 Bombs</strong></li>
                        <li>Collect <span class="asteroid-icon green">●</span> <strong>Green Asteroid</strong> when health is 100% = <strong>+1 Bomb</strong></li>
                        <li>Reach 100% health by collecting green asteroids = <strong>+1 Bomb</strong></li>
                        <li>Tap <strong>"DETONATE"</strong> button to use bomb (destroys all asteroids)</li>
                    </ul>
                </div>
                
                <div class="instruction-section">
                    <h3>❤️ HOW TO INCREASE HEALTH</h3>
                    <ul class="points-list">
                        <li>Shoot <span class="asteroid-icon green">●</span> <strong>Green Asteroids</strong> = <strong>+15 Health</strong></li>
                        <li>Maximum Health = <strong>100 HP</strong></li>
                        <li>Normal asteroids hitting Earth = <strong>-10 Health</strong></li>
                    </ul>
                </div>
                
                <div class="instruction-section">
                    <h3>🎲 GAME OBJECTS</h3>
                    <div class="objects-grid">
                        <div class="object-item">
                            <span class="asteroid-icon normal">●</span>
                            <div>
                                <strong>Normal Asteroid</strong>
                                <small>Gray color</small>
                            </div>
                        </div>
                        <div class="object-item">
                            <span class="asteroid-icon green">●</span>
                            <div>
                                <strong>Green Asteroid</strong>
                                <small>Bonus item</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="instruction-section">
                    <h3>💀 GAME OVER</h3>
                    <ul class="points-list">
                        <li>Health reaches <strong>0 HP</strong> = Game Over</li>
                        <li>Contest time ends = Game Over (score saved)</li>
                        <li>Final Score = Total points earned</li>
                    </ul>
                </div>
                
                <div class="instruction-section">
                    <h3>🎮 CONTROLS</h3>
                    <ul class="points-list">
                        <li><strong>Drag/Touch:</strong> Rotate camera view (3D)</li>
                        <li><strong>Tap/Click:</strong> Shoot at asteroids</li>
                        <li><strong>Bomb Button:</strong> Detonate all asteroids</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Import Three.js from CDN -->
    <script type="importmap">
        {
            "imports": {
                "three": "https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js",
                "three/addons/": "https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/"
            }
        }
    </script>

    <!-- Non-module script for demo button and instructions - must be outside module scope -->
    <script>
        // Global demo button handler - works immediately
        window.handleDemoClick = function() {
            console.log('Demo button clicked from global handler');
            // This will be called from the module script
            if (window.startDemoGameFromModule) {
                window.startDemoGameFromModule();
            } else {
                console.log('Waiting for module to load...');
                setTimeout(window.handleDemoClick, 100);
            }
        };
        
        // Global instructions toggle handler - works immediately
        window.toggleInstructions = function(e) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            console.log('Toggle instructions called');
            const panel = document.getElementById('instructions-panel');
            if (panel) {
                // Ensure panel is above everything
                panel.style.zIndex = '3000';
                panel.style.pointerEvents = 'auto';
                
                // Toggle show class
                if (panel.classList.contains('show')) {
                    // Close instructions
                    panel.classList.remove('show');
                    document.body.style.overflow = '';
                } else {
                    // Open instructions
                    panel.classList.add('show');
                    document.body.style.overflow = 'hidden';
                }
            } else {
                console.error('Instructions panel not found');
            }
            return false;
        };
    </script>

    <script type="module">
        import * as THREE from 'three';
        import { OrbitControls } from 'three/addons/controls/OrbitControls.js';

        // --- Game State ---
        const state = {
            score: 0,
            health: 100,
            bombs: 3,
            isPlaying: false, // Start as false until game session is active
            earthRadius: 5,
            gameSessionId: null,
            creditsUsed: 0,
            gameStarted: false,
            isDemoMode: false, // Track if playing in demo mode
            isContestMode: false, // Track if contest mode is active
            gameMode: 'money' // Track game mode (money or credits)
        };

        // --- Game Session Management ---
        let gameSession = null;
        let countdownInterval = null;
        
        // Contest timer (shows remaining time until closing)
        let contestTimerInterval = null;
        
        // Track if game ended due to time
        let gameEndedByTime = false;
        
        function hideContestTimers() {
            document.getElementById('contest-timer').style.display = 'none';
            const mobileTimer = document.getElementById('contest-timer-mobile');
            if (mobileTimer) mobileTimer.style.display = 'none';
        }

        function updateContestTimer() {
            // Hide contest timer in demo mode - demo has no time limits
            if (state.isDemoMode) {
                hideContestTimers();
                return;
            }
            
            if (!gameSession || !gameSession.end_timestamp) {
                hideContestTimers();
                return;
            }
            
            const now = Math.floor(Date.now() / 1000);
            const endTime = gameSession.end_timestamp;
            const remaining = endTime - now;
            
            const timerElement = document.getElementById('contest-timer');
            const mobileTimerElement = document.getElementById('contest-timer-mobile');
            
            if (remaining <= 0) {
                const endText = 'Contest Ended';
                timerElement.textContent = endText;
                timerElement.style.color = '#ff3333';
                if (mobileTimerElement) {
                    mobileTimerElement.textContent = endText;
                    mobileTimerElement.style.color = '#ff3333';
                }
                clearInterval(contestTimerInterval);
                
                // If game is currently playing (and NOT in demo mode), end it automatically
                // Demo mode has no time limits, so never auto-end demo games
                if (state.isPlaying && !gameEndedByTime && !state.isDemoMode) {
                    gameEndedByTime = true;
                    gameOver(true); // Pass true to indicate time's up
                }
                return;
            }
            
            const hours = Math.floor(remaining / 3600);
            const minutes = Math.floor((remaining % 3600) / 60);
            const seconds = remaining % 60;
            
            // Desktop timer format
            let timerText = '';
            if (hours > 0) {
                timerText = `${hours} hr`;
            } else if (minutes > 0) {
                timerText = `${minutes} min ${seconds} sec`;
            } else {
                timerText = `${seconds} sec`;
            }
            
            timerElement.textContent = `⏰ Contest Ends In: ${timerText}`;
            timerElement.style.display = 'block';
            timerElement.style.color = '#00ffff';
            
            // Mobile timer format (compact)
            if (mobileTimerElement) {
                let mobileText = '';
                if (hours > 0) {
                    mobileText = `${hours}h ${minutes}m`;
                } else if (minutes > 0) {
                    mobileText = `${minutes}m ${seconds}s`;
                } else {
                    mobileText = `${seconds}s`;
                }
                mobileTimerElement.textContent = `⏰ ${mobileText}`;
                mobileTimerElement.style.display = 'block';
                mobileTimerElement.style.color = '#00ffff';
            }
        }
        
        // Ensure demo button is always visible and enabled on page load
        function ensureDemoButtonReady() {
            const demoBtn = document.getElementById('demo-game-btn');
            if (demoBtn) {
                demoBtn.style.display = 'block';
                demoBtn.disabled = false;
                demoBtn.style.pointerEvents = 'auto';
                demoBtn.style.cursor = 'pointer';
            }
        }
        
        // Run immediately and on DOM ready
        ensureDemoButtonReady();
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', ensureDemoButtonReady);
        } else {
            ensureDemoButtonReady();
        }
        
        // Check game status
        async function checkGameStatus() {
            // Always ensure demo button is visible - demo has no restrictions, play anytime
            const demoBtn = document.getElementById('demo-game-btn');
            if (demoBtn) {
                demoBtn.style.display = 'block';
                demoBtn.style.visibility = 'visible';
                demoBtn.disabled = false;
                demoBtn.style.pointerEvents = 'auto';
                demoBtn.style.cursor = 'pointer';
            }
            
            try {
                // First, get credits from games table (admin-set value)
                let creditsFromGames = 30; // Default fallback
                try {
                    const creditsResponse = await fetch('get_game_credits.php?game=earth-defender');
                    const creditsData = await creditsResponse.json();
                    if (creditsData.success && creditsData.credits_per_chance) {
                        creditsFromGames = creditsData.credits_per_chance;
                    }
                } catch (e) {
                    console.error('Error fetching game credits:', e);
                }
                
                const response = await fetch('game_api.php?action=check_status&game=earth-defender');
                const data = await response.json();

                // Update total points in HUD if available
                if (data.success && data.user_total_points !== undefined) {
                    const hudTotalScore = document.getElementById('hud-total-score');
                    const hudTotalScoreItem = document.getElementById('hud-total-score-item');
                    if (hudTotalScore && hudTotalScoreItem) {
                        hudTotalScore.textContent = data.user_total_points.toLocaleString();
                        hudTotalScoreItem.style.display = 'flex';
                    }
                }
                
                // Override credits_required with admin-set value from games table
                if (data.success && data.session) {
                    data.session.credits_required = creditsFromGames;
                }
                
                if (data.success && data.session) {
                    gameSession = data.session;
                    state.gameSessionId = gameSession.id || null;
                    state.isContestMode = data.is_contest_active || false;
                    state.gameMode = data.game_mode || 'money';
                    
                    // Start contest timer
                    if (contestTimerInterval) {
                        clearInterval(contestTimerInterval);
                    }
                    updateContestTimer();
                    contestTimerInterval = setInterval(updateContestTimer, 1000);
                    
                    if (data.is_active) {
                        // Game is active - show start button
                        showGameReady();
                    } else if (data.session && data.session.time_until_start > 0) {
                        // Game not active but scheduled - show countdown
                        showCountdown(data.session.time_until_start);
                    } else {
                        // No active session - show message with next session date if available
                        showNoSession(data.next_session_date, data.is_contest_active);
                        hideContestTimers();
                    }
                } else {
                    // No session - but check if contest is active
                    state.isContestMode = data.is_contest_active || false;
                    state.gameMode = data.game_mode || 'money';
                    showNoSession(data.next_session_date, data.is_contest_active);
                    hideContestTimers();
                }
            } catch (error) {
                console.error('Error checking game status:', error);
                showNoSession(null);
                hideContestTimers();
                // Ensure demo button is always visible even on error
                if (demoBtn) {
                    demoBtn.style.display = 'block';
                    demoBtn.style.visibility = 'visible';
                    demoBtn.disabled = false;
                    demoBtn.style.pointerEvents = 'auto';
                }
            }
        }
        
        function showGameReady() {
            const overlay = document.getElementById('game-status-overlay');
            const timerDisplay = document.getElementById('timer-display');
            const statusMessage = document.getElementById('status-message');
            const startBtn = document.getElementById('start-game-btn');
            const demoBtn = document.getElementById('demo-game-btn');
            
            timerDisplay.textContent = '';
            const creditsRequired = gameSession.credits_required || 30;
            
            if (state.isContestMode) {
                statusMessage.textContent = `🏆 Contest is LIVE! Play with ${creditsRequired} credits and reach the top 3 to win prizes!`;
                startBtn.style.display = 'flex';
                startBtn.innerHTML = `START MISSION &nbsp; <i class="fas fa-coins" style="color: #000;"></i> ${creditsRequired}`;
                startBtn.style.background = 'linear-gradient(135deg, #FFD700, #ff8c00)';
                startBtn.style.color = '#000';
            } else {
                statusMessage.textContent = `Mission ready! Use ${creditsRequired} credits to start.`;
                startBtn.style.display = 'flex';
                startBtn.innerHTML = `START MISSION &nbsp; <i class="fas fa-coins" style="color: #FFD700;"></i> ${creditsRequired}`;
                startBtn.style.background = ''; // Reset to CSS default
                startBtn.style.color = '';
            }
            startBtn.disabled = false;
            
            // Always show demo button - demo can be played ANYTIME, no restrictions
            if (demoBtn) {
                demoBtn.style.display = 'flex';
                demoBtn.disabled = false;
            }
            
            // Check if user is logged in
            const isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
            const userCredits = <?php echo $user_credits; ?>;
            
            if (!isLoggedIn) {
                // User not logged in - hide start button, show demo only
                startBtn.style.display = 'none';
                if (state.isContestMode) {
                    statusMessage.textContent = `🏆 A contest is active! Login or Register to participate (${creditsRequired} credits required).`;
                } else {
                    statusMessage.textContent = `Login or Register to start mission (${creditsRequired} credits required).`;
                }
            } else if (userCredits < creditsRequired) {
                startBtn.disabled = true;
                startBtn.innerHTML = `LOCKED &nbsp; <i class="fas fa-coins" style="color: #FFD700;"></i> ${creditsRequired}`;
                statusMessage.textContent = `Add ${creditsRequired} credits to start mission.`;
            }
        }
        
        function showCountdown(secondsUntilStart) {
            const overlay = document.getElementById('game-status-overlay');
            const timerDisplay = document.getElementById('timer-display');
            const statusMessage = document.getElementById('status-message');
            const startBtn = document.getElementById('start-game-btn');
            const demoBtn = document.getElementById('demo-game-btn');
            
            startBtn.style.display = 'none';
            // Always show demo button - demo can be played ANYTIME, no restrictions
            if (demoBtn) {
                demoBtn.style.display = 'block';
                demoBtn.disabled = false;
            }
            
            function updateCountdown() {
                const now = Math.floor(Date.now() / 1000);
                const startTime = gameSession.start_timestamp;
                const remaining = startTime - now;
                
                if (remaining <= 0) {
                    clearInterval(countdownInterval);
                    showGameReady();
                    return;
                }
                
                const hours = Math.floor(remaining / 3600);
                const minutes = Math.floor((remaining % 3600) / 60);
                const seconds = remaining % 60;
                
                timerDisplay.textContent = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
                
                // Show contest message if contest is active
                if (state.isContestMode) {
                    statusMessage.textContent = `🏆 Contest is active! Mission will start soon.`;
                } else {
                    statusMessage.textContent = 'Mission will start soon.';
                }
            }
            
            updateCountdown();
            countdownInterval = setInterval(updateCountdown, 1000);
        }
        
        function showNoSession(nextSessionDate = null, isContestActive = false) {
            const overlay = document.getElementById('game-status-overlay');
            const timerDisplay = document.getElementById('timer-display');
            const statusMessage = document.getElementById('status-message');
            const startBtn = document.getElementById('start-game-btn');
            const demoBtn = document.getElementById('demo-game-btn');
            
            const isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
            
            timerDisplay.textContent = '';
            
            // Hide play button when no session is active
            startBtn.style.display = 'none';
            
            // Clear status message
            statusMessage.textContent = '';
            startBtn.style.display = 'none';
            // Demo can be played ANYTIME - always visible and enabled
            if (demoBtn) {
                demoBtn.style.display = 'block';
                demoBtn.disabled = false;
            }
        }
        
        // Start game button handler (with credits)
        document.getElementById('start-game-btn').addEventListener('click', async function() {
            if (state.gameStarted) return;
            
            const isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
            
            if (!isLoggedIn) {
                alert('Please login to play for real. Demo mode is always available!');
                window.location.href = 'index.php';
                return;
            }
            
            if (!gameSession) {
                alert('No active game session. Please try demo mode.');
                return;
            }
            
            // Double-check session is active before allowing credit deduction
            const now = Math.floor(Date.now() / 1000);
            const sessionStart = gameSession.start_timestamp;
            const sessionEnd = gameSession.end_timestamp;
            
            if (!sessionStart || !sessionEnd || now < sessionStart || now > sessionEnd) {
                alert('Game session is not currently active. Credits will not be deducted. Please wait for the scheduled time or try demo mode.');
                return;
            }
            
            const creditsRequired = gameSession.credits_required || 30;
            const userCredits = <?php echo $user_credits; ?>;
            
            if (userCredits < creditsRequired) {
                alert(`Insufficient credits! You need ${creditsRequired} credits to play. Try demo mode instead.`);
                return;
            }
            
            // Confirm before deducting credits
            const confirmMsg = state.isContestMode 
                ? `Join the contest? This will deduct ${creditsRequired} credits. Your high score will be recorded for prizes!`
                : `This will deduct ${creditsRequired} credits from your account. Continue?`;

            if (!confirm(confirmMsg)) {
                return;
            }
            
            try {
                // Deduct credits
                const formData = new FormData();
                formData.append('session_id', gameSession.id);
                formData.append('game_name', 'earth-defender');
                
                const response = await fetch('game_api.php?action=deduct_credits', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    gameEndedByTime = false; // Reset time's up flag
                    state.gameStarted = true;
                    state.isDemoMode = false;
                    state.creditsUsed = creditsRequired;
                    state.gameSessionId = data.session_id || gameSession.id;
                    state.isPlaying = true;
                    // Show EXIT button
                    const exitBtnHud = document.getElementById('exit-game-btn-hud');
                    if (exitBtnHud) exitBtnHud.style.display = 'block';
                    
                    preventExitDuringGame();
                    document.getElementById('game-status-overlay').classList.add('hidden');
                    // Update credits display
                    document.getElementById('user-credits-display').textContent = data.credits_remaining.toLocaleString();
                } else {
                    // Show specific error message from server
                    if (data.message) {
                        alert(data.message);
                    } else {
                        alert('Failed to start game. Please try again.');
                    }
                }
            } catch (error) {
                console.error('Error starting game:', error);
                alert('An error occurred. Please try again.');
            }
        });
        
        // Demo game button handler - Simple and direct approach
        function startDemoGame() {
            console.log('=== startDemoGame called ===');
            
            if (state.gameStarted) {
                console.log('Game already started');
                return;
            }
            
            // Set game state
            gameEndedByTime = false;
            state.gameStarted = true;
            state.isDemoMode = true;
            state.creditsUsed = 0;
            state.gameSessionId = null;
            state.isPlaying = true;
            // Show EXIT button
            const exitBtnHud = document.getElementById('exit-game-btn-hud');
            if (exitBtnHud) exitBtnHud.style.display = 'block';
            
            preventExitDuringGame();
            state.score = 0;
            state.health = 100;
            state.bombs = 3;
            
            console.log('Game state set:', {
                gameStarted: state.gameStarted,
                isPlaying: state.isPlaying,
                isDemoMode: state.isDemoMode
            });
            
            // Hide overlay with multiple methods - CRITICAL
            const overlay = document.getElementById('game-status-overlay');
            if (overlay) {
                overlay.classList.add('hidden');
                overlay.style.display = 'none';
                overlay.style.visibility = 'hidden';
                overlay.style.pointerEvents = 'none';
                overlay.style.opacity = '0';
                overlay.style.zIndex = '-1';
                overlay.style.position = 'absolute';
                console.log('Overlay hidden');
            } else {
                console.error('Overlay element not found!');
            }
            
            // Hide contest timer
            const contestTimer = document.getElementById('contest-timer');
            if (contestTimer) {
                contestTimer.style.display = 'none';
            }
            
            // Stop contest timer interval
            if (typeof contestTimerInterval !== 'undefined' && contestTimerInterval) {
                clearInterval(contestTimerInterval);
                contestTimerInterval = null;
            }
            
            // Show demo indicator
            const demoIndicator = document.getElementById('demo-indicator');
            if (demoIndicator) {
                demoIndicator.style.display = 'inline';
            }
            
            // Show message
            setTimeout(function() {
                if (typeof showMessage === 'function') {
                    showMessage("DEMO MODE - Score won't be saved - No time limits!");
                }
            }, 100);
            
            // Update HUD
            setTimeout(function() {
                if (typeof updateHUD === 'function') {
                    updateHUD();
                }
            }, 100);
            
            console.log('=== Demo game started! isPlaying:', state.isPlaying, '===');
        }
        
        // Make startDemoGame globally available
        window.startDemoGame = startDemoGame;
        window.startDemoGameFromModule = startDemoGame;
        
        // Attach demo button handler - multiple methods to ensure it works
        function attachDemoButton() {
            const demoBtn = document.getElementById('demo-game-btn');
            if (!demoBtn) {
                console.log('Demo button not found, retrying...');
                setTimeout(attachDemoButton, 50);
                return;
            }
            
            // Remove any existing handlers first
            const newBtn = demoBtn.cloneNode(true);
            demoBtn.parentNode.replaceChild(newBtn, demoBtn);
            
            // Use onclick for maximum compatibility
            newBtn.onclick = function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('Demo button clicked via onclick');
                startDemoGame();
                return false;
            };
            
            // Also add event listener for click
            newBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('Demo button clicked via addEventListener');
                startDemoGame();
                return false;
            }, false);
            
            // Also add event listener for touch
            newBtn.addEventListener('touchend', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('Demo button touched');
                startDemoGame();
                return false;
            }, { passive: false });
            
            console.log('Demo button handler attached successfully');
        }
        
        // Attach immediately and on DOM ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                attachDemoButton();
            });
        } else {
            attachDemoButton();
        }
        
        // Also try after delays to catch any late loading
        setTimeout(attachDemoButton, 100);
        setTimeout(attachDemoButton, 500);
        setTimeout(attachDemoButton, 1000);
        
        // Initialize
        checkGameStatus();

        // Claim Prize Button Handler
        const claimBtn = document.getElementById('claim-prize-btn');
        if (claimBtn) {
            claimBtn.addEventListener('click', async function() {
                claimBtn.disabled = true;
                const msgEl = document.getElementById('claim-message');
                msgEl.style.display = 'block';
                msgEl.style.color = '#00ffff';
                msgEl.textContent = 'Processing claim...';

                try {
                    const response = await fetch('game_api.php?action=claim_contest_prize&game=earth-defender');
                    const data = await response.json();
                    
                    if (data.success) {
                        msgEl.style.color = '#00ff00';
                        msgEl.textContent = data.message;
                        // Update credits display if possible
                        const creditsDisplay = document.getElementById('user-credits-display');
                        if (creditsDisplay && data.credits_added) {
                            const current = parseInt(creditsDisplay.textContent.replace(/,/g, ''));
                            creditsDisplay.textContent = (current + data.credits_added).toLocaleString();
                        }
                    } else {
                        msgEl.style.color = '#ff3333';
                        msgEl.textContent = data.message;
                        claimBtn.disabled = false;
                    }
                } catch (error) {
                    msgEl.style.color = '#ff3333';
                    msgEl.textContent = 'An error occurred. Please try again later.';
                    claimBtn.disabled = false;
                }
            });
        }

        // --- Sound System ---
        const SoundManager = {
            audioContext: null,
            masterVolume: 0.5, // Master volume control (0.0 to 1.0)
            enabled: true,
            
            init() {
                try {
                    this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
                } catch (e) {
                    console.warn('Web Audio API not supported:', e);
                    this.enabled = false;
                }
            },
            
            // Generate a tone with frequency, duration, and type
            playTone(frequency, duration, type = 'sine', volume = 0.3) {
                if (!this.enabled || !this.audioContext) return;
                
                try {
                    const oscillator = this.audioContext.createOscillator();
                    const gainNode = this.audioContext.createGain();
                    
                    oscillator.connect(gainNode);
                    gainNode.connect(this.audioContext.destination);
                    
                    oscillator.type = type;
                    oscillator.frequency.value = frequency;
                    
                    gainNode.gain.setValueAtTime(0, this.audioContext.currentTime);
                    gainNode.gain.linearRampToValueAtTime(volume * this.masterVolume, this.audioContext.currentTime + 0.01);
                    gainNode.gain.exponentialRampToValueAtTime(0.01, this.audioContext.currentTime + duration);
                    
                    oscillator.start(this.audioContext.currentTime);
                    oscillator.stop(this.audioContext.currentTime + duration);
                } catch (e) {
                    console.warn('Sound playback error:', e);
                }
            },
            
            // Laser/Shooting sound
            playShoot() {
                if (!this.enabled) return;
                this.playTone(800, 0.1, 'square', 0.2);
                // Add a quick high-pitched tail
                setTimeout(() => {
                    this.playTone(1200, 0.05, 'sine', 0.15);
                }, 50);
            },
            
            // Explosion sound
            playExplosion() {
                if (!this.enabled) return;
                // Low rumble
                this.playTone(80, 0.3, 'sawtooth', 0.4);
                // Mid explosion
                setTimeout(() => {
                    this.playTone(200, 0.2, 'square', 0.3);
                }, 50);
                // High crack
                setTimeout(() => {
                    this.playTone(600, 0.15, 'sine', 0.2);
                }, 100);
            },
            
            // Bomb/Shockwave sound
            playBomb() {
                if (!this.enabled) return;
                // Deep powerful explosion
                this.playTone(60, 0.5, 'sawtooth', 0.6);
                setTimeout(() => {
                    this.playTone(150, 0.4, 'square', 0.5);
                }, 100);
                setTimeout(() => {
                    this.playTone(300, 0.3, 'sine', 0.3);
                }, 200);
            },
            
            // Earth hit/damage sound
            playEarthHit() {
                if (!this.enabled) return;
                // Warning tone
                this.playTone(200, 0.2, 'square', 0.5);
                // Low impact
                setTimeout(() => {
                    this.playTone(100, 0.3, 'sawtooth', 0.4);
                }, 50);
            },
            
            // Bonus collection sound
            playBonus() {
                if (!this.enabled) return;
                // Upward arpeggio
                this.playTone(400, 0.1, 'sine', 0.3);
                setTimeout(() => {
                    this.playTone(600, 0.1, 'sine', 0.3);
                }, 100);
                setTimeout(() => {
                    this.playTone(800, 0.15, 'sine', 0.3);
                }, 200);
            },
            
            // Game over sound
            playGameOver() {
                if (!this.enabled) return;
                // Sad descending tones
                this.playTone(300, 0.3, 'sine', 0.4);
                setTimeout(() => {
                    this.playTone(200, 0.3, 'sine', 0.4);
                }, 300);
                setTimeout(() => {
                    this.playTone(150, 0.5, 'sawtooth', 0.5);
                }, 600);
            },
            
            // Power-up/Bomb earned sound
            playPowerUp() {
                if (!this.enabled) return;
                // Triumphant ascending tones
                this.playTone(500, 0.15, 'sine', 0.3);
                setTimeout(() => {
                    this.playTone(700, 0.15, 'sine', 0.3);
                }, 150);
                setTimeout(() => {
                    this.playTone(900, 0.2, 'sine', 0.35);
                }, 300);
            }
        };
        
        // Initialize sound system
        SoundManager.init();
        
        // Enable sound on user interaction (required by browsers)
        let soundInitialized = false;
        function initSoundOnInteraction() {
            if (!soundInitialized && SoundManager.audioContext && SoundManager.audioContext.state === 'suspended') {
                SoundManager.audioContext.resume().then(() => {
                    console.log('Sound system activated');
                });
                soundInitialized = true;
            }
        }
        
        // Initialize on any user interaction
        document.addEventListener('click', initSoundOnInteraction, { once: true });
        document.addEventListener('touchstart', initSoundOnInteraction, { once: true });
        document.addEventListener('keydown', initSoundOnInteraction, { once: true });

        // --- Scene Setup ---
        const container = document.getElementById('canvas-container');
        const scene = new THREE.Scene();
        
        const camera = new THREE.PerspectiveCamera(45, window.innerWidth / window.innerHeight, 0.1, 1000);
        camera.position.set(0, 0, 25); // Initial zoom

        const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
        renderer.setSize(window.innerWidth, window.innerHeight);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2)); // Limit pixel ratio for mobile performance
        renderer.toneMapping = THREE.ACESFilmicToneMapping;
        renderer.toneMappingExposure = 1.0;
        container.appendChild(renderer.domElement);

        const controls = new OrbitControls(camera, renderer.domElement);
        controls.enableDamping = true;
        controls.dampingFactor = 0.05;
        controls.minDistance = 8;
        controls.maxDistance = 50;
        controls.enablePan = false; // Disable panning to simplify mobile controls
        controls.rotateSpeed = 0.7; // Slower rotation feels weightier on mobile

        // --- Lighting ---
        const ambientLight = new THREE.AmbientLight(0x404040, 0.5); 
        scene.add(ambientLight);

        const sunLight = new THREE.DirectionalLight(0xffffff, 2.5);
        sunLight.position.set(50, 30, 50);
        scene.add(sunLight);

        // --- Texture Loader ---
        const textureLoader = new THREE.TextureLoader();
        const earthMap = textureLoader.load('https://raw.githubusercontent.com/mrdoob/three.js/master/examples/textures/planets/earth_atmos_2048.jpg');
        const earthSpecular = textureLoader.load('https://raw.githubusercontent.com/mrdoob/three.js/master/examples/textures/planets/earth_specular_2048.jpg');
        const earthNormal = textureLoader.load('https://raw.githubusercontent.com/mrdoob/three.js/master/examples/textures/planets/earth_normal_2048.jpg');
        const cloudMap = textureLoader.load('https://raw.githubusercontent.com/mrdoob/three.js/master/examples/textures/planets/earth_clouds_1024.png');

        // --- Earth Group ---
        const earthGroup = new THREE.Group();
        scene.add(earthGroup);

        // 1. Earth Surface
        const earthGeometry = new THREE.SphereGeometry(state.earthRadius, 64, 64);
        const earthMaterial = new THREE.MeshPhongMaterial({
            map: earthMap,
            specularMap: earthSpecular,
            normalMap: earthNormal,
            specular: new THREE.Color(0x333333),
            shininess: 15
        });
        const earthMesh = new THREE.Mesh(earthGeometry, earthMaterial);
        earthGroup.add(earthMesh);

        // 2. Cloud Layer
        const cloudGeometry = new THREE.SphereGeometry(state.earthRadius + 0.05, 64, 64);
        const cloudMaterial = new THREE.MeshLambertMaterial({
            map: cloudMap,
            transparent: true,
            opacity: 0.9,
            blending: THREE.AdditiveBlending,
            side: THREE.DoubleSide
        });
        const cloudMesh = new THREE.Mesh(cloudGeometry, cloudMaterial);
        earthGroup.add(cloudMesh);

        // 3. Atmosphere Glow
        const vertexShader = `
            varying vec3 vNormal;
            void main() {
                vNormal = normalize(normalMatrix * normal);
                gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
            }
        `;
        const fragmentShader = `
            varying vec3 vNormal;
            void main() {
                float intensity = pow(0.65 - dot(vNormal, vec3(0, 0, 1.0)), 4.0);
                gl_FragColor = vec4(0.3, 0.6, 1.0, 1.0) * intensity;
            }
        `;
        const atmosphereGeometry = new THREE.SphereGeometry(state.earthRadius + 1.2, 64, 64);
        const atmosphereMaterial = new THREE.ShaderMaterial({
            vertexShader: vertexShader,
            fragmentShader: fragmentShader,
            blending: THREE.AdditiveBlending,
            side: THREE.BackSide,
            transparent: true
        });
        const atmosphereMesh = new THREE.Mesh(atmosphereGeometry, atmosphereMaterial);
        earthGroup.add(atmosphereMesh);

        // --- Starfield ---
        const starGeometry = new THREE.BufferGeometry();
        const starCount = 3000; // Reduced slightly for mobile performance
        const starPos = new Float32Array(starCount * 3);
        for(let i=0; i<starCount * 3; i++) {
            starPos[i] = (Math.random() - 0.5) * 600; 
        }
        starGeometry.setAttribute('position', new THREE.BufferAttribute(starPos, 3));
        const starMaterial = new THREE.PointsMaterial({color: 0xffffff, size: 0.2, transparent: true, opacity: 0.8});
        const stars = new THREE.Points(starGeometry, starMaterial);
        scene.add(stars);


        // --- Game Logic Managers ---

        // ASTEROIDS
        const asteroids = [];
        const asteroidGeometry = new THREE.IcosahedronGeometry(0.5, 1);
        
        function createAsteroid() {
            if(!state.isPlaying) return;

            const isBonus = Math.random() < 0.2;

            const material = isBonus 
                ? new THREE.MeshStandardMaterial({ 
                    color: 0x00ff00, 
                    emissive: 0x004400, 
                    emissiveIntensity: 0.8,
                    roughness: 0.2, 
                    flatShading: true 
                  })
                : new THREE.MeshStandardMaterial({
                    color: 0x888888,
                    roughness: 0.8,
                    flatShading: true
                  });
            
            const mesh = new THREE.Mesh(asteroidGeometry, material);
            
            const distance = 40 + Math.random() * 20;
            const theta = Math.random() * Math.PI * 2;
            const phi = Math.acos((Math.random() * 2) - 1);
            
            mesh.position.set(
                distance * Math.sin(phi) * Math.cos(theta),
                distance * Math.sin(phi) * Math.sin(theta),
                distance * Math.cos(phi)
            );

            const s = 0.5 + Math.random() * 1.5;
            mesh.scale.set(s,s,s);
            mesh.rotation.set(Math.random()*Math.PI, Math.random()*Math.PI, Math.random()*Math.PI);

            mesh.userData = {
                type: isBonus ? 'bonus' : 'normal',
                velocity: mesh.position.clone().normalize().negate().multiplyScalar(0.05 + (Math.random() * 0.05)),
                rotSpeed: { x: Math.random()*0.05, y: Math.random()*0.05 }
            };

            scene.add(mesh);
            asteroids.push(mesh);
        }

        // PROJECTILES
        const projectiles = [];
        const projectileGeometry = new THREE.SphereGeometry(0.1, 8, 8);
        const projectileMaterial = new THREE.MeshBasicMaterial({ color: 0x00ffff });

        function shoot(targetPoint) {
            if(!state.isPlaying) return;
            
            const startPos = targetPoint.clone().normalize().multiplyScalar(state.earthRadius);
            const mesh = new THREE.Mesh(projectileGeometry, projectileMaterial);
            mesh.position.copy(startPos);
            
            const velocity = targetPoint.clone().sub(startPos).normalize().multiplyScalar(1.5); 

            mesh.userData = { velocity: velocity, life: 100 };
            scene.add(mesh);
            projectiles.push(mesh);
            
            // Play shooting sound
            SoundManager.playShoot();
        }

        // SHOCKWAVE (BOMB EFFECT)
        const shockwaves = [];
        const shockwaveGeo = new THREE.SphereGeometry(1, 32, 32);
        
        function createShockwave() {
            const mat = new THREE.MeshBasicMaterial({ 
                color: 0xff3333, 
                transparent: true, 
                opacity: 0.5,
                side: THREE.BackSide,
                wireframe: false
            });
            const mesh = new THREE.Mesh(shockwaveGeo, mat);
            mesh.position.set(0,0,0);
            mesh.scale.set(state.earthRadius, state.earthRadius, state.earthRadius);
            
            mesh.userData = { expansion: 1.5, life: 1.0 };
            
            scene.add(mesh);
            shockwaves.push(mesh);
        }

        // EXPLOSIONS
        const particles = [];
        function createExplosion(position, color = 0xffaa00, playSound = true) {
            const particleCount = 10; // Reduced count for mobile
            const mat = new THREE.MeshBasicMaterial({ color: color });
            
            // Play explosion sound
            if (playSound) {
                SoundManager.playExplosion();
            }
            
            for(let i=0; i<particleCount; i++) {
                const mesh = new THREE.Mesh(new THREE.BoxGeometry(0.1, 0.1, 0.1), mat);
                mesh.position.copy(position);
                
                mesh.userData = {
                    velocity: new THREE.Vector3(
                        (Math.random()-0.5), (Math.random()-0.5), (Math.random()-0.5)
                    ).normalize().multiplyScalar(Math.random() * 0.3),
                    life: 1.0 
                };
                
                scene.add(mesh);
                particles.push(mesh);
            }
        }

        // BOMB LOGIC
        function useBomb() {
            if (!state.isPlaying || state.bombs <= 0) return;

            state.bombs--;
            updateHUD();
            createShockwave();
            
            // Play bomb sound
            SoundManager.playBomb();

            // Destroy all asteroids
            for (let i = asteroids.length - 1; i >= 0; i--) {
                const a = asteroids[i];
                const isBonus = a.userData.type === 'bonus';
                
                // Don't play individual explosion sounds for bomb (too many sounds)
                createExplosion(a.position, isBonus ? 0x00ff00 : 0xffaa00, false);
                scene.remove(a);
                
                if (isBonus) {
                    state.score = (state.score || 0) + 50;
                    if (state.health < 100) {
                        state.health = Math.min(100, state.health + 15);
                        if (state.health === 100) {
                            state.bombs++;
                            showMessage("MAX INTEGRITY! +1 BOMB");
                            SoundManager.playPowerUp();
                        }
                    } else {
                        state.bombs++;
                        showMessage("BONUS BOMB EARNED!");
                        SoundManager.playPowerUp();
                    }
                } else {
                    state.score = (state.score || 0) + 50;
                }
            }
            asteroids.length = 0; 
            updateHUD();
        }

        // --- Unified Input System (Mobile & Desktop) ---
        const raycaster = new THREE.Raycaster();
        const mouse = new THREE.Vector2();
        
        // Track pointer for distinguishing tap vs drag
        let pointerDownX = 0;
        let pointerDownY = 0;
        let isPointerDown = false;

        function handlePointerDown(x, y) {
            pointerDownX = x;
            pointerDownY = y;
            isPointerDown = true;
        }

        function handlePointerUp(x, y) {
            if (!state.isPlaying || !isPointerDown) return;
            isPointerDown = false;

            // Calculate distance moved
            const moveX = Math.abs(x - pointerDownX);
            const moveY = Math.abs(y - pointerDownY);

            // If moved less than 10 pixels, consider it a TAP/CLICK (Shoot)
            // If moved more, it was a camera rotation (ignore shoot)
            if (moveX < 10 && moveY < 10) {
                
                // Convert to Normalized Device Coordinates
                mouse.x = (x / window.innerWidth) * 2 - 1;
                mouse.y = -(y / window.innerHeight) * 2 + 1;

                raycaster.setFromCamera(mouse, camera);

                // Check for Asteroid hit (generous hitbox)
                raycaster.params.Points.threshold = 1; // Increase threshold if needed
                const intersects = raycaster.intersectObjects(asteroids);

                if (intersects.length > 0) {
                    // Shoot directly at clicked asteroid
                    shoot(intersects[0].object.position);
                } else {
                    // Shoot into space at cursor direction
                    const target = new THREE.Vector3();
                    raycaster.ray.at(40, target); // Shoot 40 units away
                    shoot(target);
                }
            }
        }

        // Touch Events
        window.addEventListener('touchstart', (e) => {
            if (e.target.id === 'bomb-btn') return; // Let button handle itself
            if (e.touches.length > 1) return; // Ignore multitouch gestures
            handlePointerDown(e.touches[0].clientX, e.touches[0].clientY);
        }, { passive: false });

        window.addEventListener('touchend', (e) => {
            if (e.target.id === 'bomb-btn') return;
            // e.changedTouches gives the position of the finger that left
            if (e.changedTouches.length > 0) {
                handlePointerUp(e.changedTouches[0].clientX, e.changedTouches[0].clientY);
            }
        });

        // Mouse Events (Fallback for PC)
        window.addEventListener('mousedown', (e) => {
            if (e.target.id === 'bomb-btn') return;
            handlePointerDown(e.clientX, e.clientY);
        });

        window.addEventListener('mouseup', (e) => {
             if (e.target.id === 'bomb-btn') return;
             handlePointerUp(e.clientX, e.clientY);
        });

        // Button Event
        const bombBtn = document.getElementById('bomb-btn');
        bombBtn.addEventListener('click', (e) => {
            e.preventDefault(); // Prevent ghost clicks
            useBomb();
        });
        bombBtn.addEventListener('touchstart', (e) => {
            e.preventDefault(); 
            e.stopPropagation(); // Stop map drag
            useBomb();
        }, { passive: false });


        // Key Listeners
        window.addEventListener('keydown', (e) => {
            if (e.code === 'Space') {
                useBomb();
            }
        });

        window.addEventListener('resize', () => {
            camera.aspect = window.innerWidth / window.innerHeight;
            camera.updateProjectionMatrix();
            renderer.setSize(window.innerWidth, window.innerHeight);
        });

        // --- Main Loop ---
        const clock = new THREE.Clock();
        let spawnTimer = 0;
        let messageTimer = 0;
        
        // Background execution support - ensure game continues when tab is hidden
        let isTabVisible = true;
        let backgroundGameLoop = null;
        let lastBackgroundUpdate = Date.now();
        
        // Handle Page Visibility API - detect when tab goes to background
        document.addEventListener('visibilitychange', function() {
            isTabVisible = !document.hidden;
            
            if (!state.isPlaying) return;
            
            if (document.hidden) {
                // Tab is now hidden - start background game loop
                console.log('Tab hidden - switching to background mode');
                lastBackgroundUpdate = Date.now();
                
                if (!backgroundGameLoop) {
                    backgroundGameLoop = setInterval(function() {
                        if (!state.isPlaying || !document.hidden) {
                            if (backgroundGameLoop) {
                                clearInterval(backgroundGameLoop);
                                backgroundGameLoop = null;
                            }
                            return;
                        }
                        
                        // Calculate delta time for background updates
                        const now = Date.now();
                        const delta = (now - lastBackgroundUpdate) / 1000; // Convert to seconds
                        lastBackgroundUpdate = now;
                        
                        // Run game logic updates (but don't render)
                        updateGameLogic(delta);
                    }, 16); // ~60fps update rate
                }
            } else {
                // Tab is now visible - stop background loop, resume normal animation
                console.log('Tab visible - resuming normal mode');
                if (backgroundGameLoop) {
                    clearInterval(backgroundGameLoop);
                    backgroundGameLoop = null;
                }
                // Reset clock to prevent huge delta jump
                clock.getDelta();
            }
        });
        
        // Also handle window blur/focus events as fallback
        window.addEventListener('blur', function() {
            if (!state.isPlaying) return;
            isTabVisible = false;
            if (!backgroundGameLoop && document.hidden) {
                lastBackgroundUpdate = Date.now();
                backgroundGameLoop = setInterval(function() {
                    if (!state.isPlaying || !document.hidden) {
                        if (backgroundGameLoop) {
                            clearInterval(backgroundGameLoop);
                            backgroundGameLoop = null;
                        }
                        return;
                    }
                    const now = Date.now();
                    const delta = (now - lastBackgroundUpdate) / 1000;
                    lastBackgroundUpdate = now;
                    updateGameLogic(delta);
                }, 16);
            }
        });
        
        window.addEventListener('focus', function() {
            isTabVisible = true;
            if (backgroundGameLoop) {
                clearInterval(backgroundGameLoop);
                backgroundGameLoop = null;
            }
            clock.getDelta(); // Reset clock
        });
        
        // Extract game logic into separate function so it can run in background
        // delta is in seconds, velocities are per-frame (assuming 60fps)
        function updateGameLogic(delta) {
            if (!state.isPlaying) return;
            
            // Check if session has ended (only for real games, NOT demo mode)
            if (!state.isDemoMode && gameSession && gameSession.end_timestamp) {
                const now = Math.floor(Date.now() / 1000);
                if (now > gameSession.end_timestamp && !gameEndedByTime) {
                    gameEndedByTime = true;
                    gameOver(true);
                    return;
                }
            }
            
            // Convert delta to frame-equivalent (assuming 60fps baseline)
            // At 60fps, delta ≈ 0.0167 seconds per frame
            const frameDelta = delta * 60;
            
            // Update Earth rotation (even in background)
            earthMesh.rotation.y += 0.0005 * frameDelta;
            cloudMesh.rotation.y += 0.0007 * frameDelta;
            
            // Spawn Asteroids
            spawnTimer += delta;
            const difficulty = Math.min(2.0, 0.5 + (state.score / 500));
            if (spawnTimer > (2.0 / difficulty)) {
                createAsteroid();
                spawnTimer = 0;
            }
            
            // Handle UI Message fade out (frame-based timer)
            if (messageTimer > 0) {
                messageTimer -= frameDelta;
                if (messageTimer <= 0) {
                    messageTimer = 0;
                    const msgEl = document.getElementById('message-area');
                    if (msgEl) msgEl.style.opacity = 0;
                }
            }
            
            // Update Shockwaves
            for (let i = shockwaves.length - 1; i >= 0; i--) {
                const s = shockwaves[i];
                s.scale.multiplyScalar(Math.pow(1.05, frameDelta));
                s.material.opacity -= 0.02 * frameDelta;
                
                if (s.material.opacity <= 0) {
                    scene.remove(s);
                    shockwaves.splice(i, 1);
                }
            }
            
            // Update Asteroids (velocities are per-frame, so multiply by frameDelta)
            for (let i = asteroids.length - 1; i >= 0; i--) {
                const a = asteroids[i];
                a.position.add(a.userData.velocity.clone().multiplyScalar(frameDelta));
                a.rotation.x += a.userData.rotSpeed.x * frameDelta;
                a.rotation.y += a.userData.rotSpeed.y * frameDelta;
                
                // Impact Check
                if (a.position.length() < state.earthRadius + 0.5) {
                    const isBonus = a.userData.type === 'bonus';
                    createExplosion(a.position, isBonus ? 0x00ff00 : 0xffaa00);
                    scene.remove(a);
                    asteroids.splice(i, 1);
                    
                    if (!isBonus) {
                        SoundManager.playEarthHit();
                    } else {
                        SoundManager.playBonus();
                    }
                    
                    state.health -= 10;
                    updateHUD();
                    
                    if(state.health <= 0) {
                        gameOver();
                        return;
                    }
                }
            }
            
            // Update Projectiles (velocities are per-frame)
            for (let i = projectiles.length - 1; i >= 0; i--) {
                const p = projectiles[i];
                p.position.add(p.userData.velocity.clone().multiplyScalar(frameDelta));
                p.userData.life -= frameDelta;
                
                let hit = false;
                for (let j = asteroids.length - 1; j >= 0; j--) {
                    const a = asteroids[j];
                    if (p.position.distanceTo(a.position) < 1.0) {
                        const isBonus = a.userData.type === 'bonus';
                        createExplosion(a.position, isBonus ? 0x00ff00 : 0xffaa00);
                        
                        if (isBonus) {
                            state.score = (state.score || 0) + 50;
                            SoundManager.playBonus();
                            if (state.health < 100) {
                                state.health = Math.min(100, state.health + 15);
                                if (state.health === 100) {
                                    state.bombs++;
                                    showMessage("MAX INTEGRITY! +1 BOMB");
                                    SoundManager.playPowerUp();
                                }
                            } else {
                                state.bombs++;
                                showMessage("BONUS BOMB EARNED!");
                                SoundManager.playPowerUp();
                            }
                        } else {
                            state.score = (state.score || 0) + 100;
                        }
                        
                        scene.remove(a);
                        asteroids.splice(j, 1);
                        updateHUD();
                        hit = true;
                        break;
                    }
                }
                
                if (hit || p.userData.life <= 0) {
                    scene.remove(p);
                    projectiles.splice(i, 1);
                }
            }
            
            // Update Particles
            for (let i = particles.length - 1; i >= 0; i--) {
                const p = particles[i];
                p.position.add(p.userData.velocity.clone().multiplyScalar(frameDelta));
                p.material.opacity = p.userData.life;
                p.material.transparent = true;
                p.userData.life -= 0.05 * frameDelta;
                p.scale.multiplyScalar(Math.pow(0.95, frameDelta));
                
                if(p.userData.life <= 0) {
                    scene.remove(p);
                    particles.splice(i, 1);
                }
            }
        }

        function showMessage(text) {
            const el = document.getElementById('message-area');
            el.innerText = text;
            el.style.opacity = 1;
            messageTimer = 100; // frames
        }

        function animate() {
            requestAnimationFrame(animate);

            const delta = clock.getDelta();
            
            // Clamp delta to prevent huge jumps when tab becomes visible again
            // This prevents game from jumping forward if tab was hidden for a long time
            const clampedDelta = Math.min(delta, 0.1); // Max 100ms per frame
            
            if (state.isPlaying) {
                // Only update controls when tab is visible (controls need user interaction)
                if (isTabVisible && !document.hidden) {
                    controls.update();
                }
                
                // Always run game logic (works in both foreground and background)
                // This ensures asteroids continue moving, health decreases, etc. even when tab is hidden
                updateGameLogic(clampedDelta);
            }

            // Only render when tab is visible (saves GPU resources when hidden)
            // Game logic continues in background, but we don't waste resources rendering
            if (isTabVisible && !document.hidden) {
                renderer.render(scene, camera);
            }
        }

        function updateHUD() {
            // Ensure score is a number before displaying
            const displayScore = typeof state.score === 'number' ? Math.floor(state.score) : 0;
            document.getElementById('score').innerText = displayScore;
            document.getElementById('bomb-count').innerText = state.bombs;
            
            // Disable button visual if no bombs
            const btn = document.getElementById('bomb-btn');
            if(state.bombs <= 0) {
                btn.style.opacity = 0.5;
                btn.style.borderColor = '#555';
                btn.style.color = '#555';
            } else {
                btn.style.opacity = 1;
                btn.style.borderColor = '#ff3333';
                btn.style.color = '#ff3333';
            }

            const hpBar = document.getElementById('health-fill');
            hpBar.style.width = state.health + '%';
            
            if(state.health > 60) hpBar.style.background = 'linear-gradient(90deg, #00ff00, #ffff00)';
            else if(state.health > 30) hpBar.style.background = 'linear-gradient(90deg, #ffff00, #ff6600)';
            else hpBar.style.background = 'linear-gradient(90deg, #ff6600, #ff0000)';
        }

        async function gameOver(isTimeUp = false) {
            // Stop the game immediately
            state.isPlaying = false;
            
            // Hide EXIT button
            const exitBtnHud = document.getElementById('exit-game-btn-hud');
            if (exitBtnHud) exitBtnHud.style.display = 'none';
            
            // Play game over sound
            SoundManager.playGameOver();
            
            // Stop background game loop if running
            if (backgroundGameLoop) {
                clearInterval(backgroundGameLoop);
                backgroundGameLoop = null;
            }
            
            // Stop contest timer updates
            if (contestTimerInterval) {
                clearInterval(contestTimerInterval);
                contestTimerInterval = null;
            }
            
            const gameOverDiv = document.getElementById('game-over');
            gameOverDiv.style.display = 'block';
            document.getElementById('final-score').innerText = state.score;
            
            // Get game over title
            const gameOverTitle = gameOverDiv.querySelector('h1');
            const gameOverSubtitle = gameOverDiv.querySelector('p');
            
            // Clear any existing messages
            const existingMessages = gameOverDiv.querySelectorAll('.time-up-msg, .demo-msg, .saved-msg');
            existingMessages.forEach(msg => msg.remove());
            
            // Show time's up message if applicable
            if (isTimeUp) {
                const timeUpMsg = document.createElement('p');
                timeUpMsg.className = 'time-up-msg';
                timeUpMsg.style.color = '#ff3333';
                timeUpMsg.style.fontWeight = 'bold';
                timeUpMsg.style.fontSize = '24px';
                timeUpMsg.style.marginBottom = '15px';
                timeUpMsg.style.textShadow = '0 0 10px #ff3333';
                timeUpMsg.textContent = '⏰ TIME\'S UP!';
                gameOverDiv.insertBefore(timeUpMsg, gameOverSubtitle);
                
                // Update game over title and subtitle
                if (gameOverTitle) {
                    gameOverTitle.textContent = 'CONTEST ENDED';
                    gameOverTitle.style.color = '#ff3333';
                }
                if (gameOverSubtitle) {
                    gameOverSubtitle.textContent = 'The contest time has expired. Your score has been recorded.';
                    gameOverSubtitle.style.color = '#ffaa00';
                }
            } else {
                // Reset to default if not time's up
                if (gameOverTitle) {
                    gameOverTitle.textContent = 'CRITICAL FAILURE';
                    gameOverTitle.style.color = '#ff3333';
                }
                if (gameOverSubtitle) {
                    gameOverSubtitle.textContent = 'Earth has been compromised.';
                    gameOverSubtitle.style.color = '#ccc';
                }
            }
            
            // Show demo mode message if applicable
            if (state.isDemoMode) {
                const demoMsg = document.createElement('p');
                demoMsg.className = 'demo-msg';
                demoMsg.style.color = '#ffaa00';
                demoMsg.style.fontWeight = 'bold';
                demoMsg.style.marginTop = '10px';
                demoMsg.textContent = 'DEMO MODE - Score not saved to leaderboard';
                const btnContainer = gameOverDiv.querySelector('.game-btn-container');
                if (btnContainer) {
                    gameOverDiv.insertBefore(demoMsg, btnContainer);
                } else {
                    gameOverDiv.appendChild(demoMsg);
                }
            }
            
            // Save score to database (only if credits were used, NOT in demo mode)
            // Also save if time's up (even if score is 0, as long as game was started with credits)
            if (state.gameStarted && !state.isDemoMode && state.creditsUsed > 0 && state.gameSessionId) {
                const finalScore = Math.floor(state.score || 0);
                console.log("Submitting final score:", finalScore, "for session:", state.gameSessionId);
                
                try {
                    const formData = new FormData();
                    formData.append('score', finalScore);
                    formData.append('session_id', state.gameSessionId);
                    formData.append('credits_used', state.creditsUsed);
                    formData.append('game_name', 'earth-defender');
                    formData.append('is_demo', 'false'); // Explicitly mark as not demo
                    
                    const response = await fetch('game_api.php?action=save_score', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    console.log("Score save response:", data);
                    
                    if (data.success) {
                        // Display total score if returned
                        if (data.total_score !== undefined) {
                            const totalScoreContainer = document.getElementById('total-score-container');
                            const totalScoreSpan = document.getElementById('total-score');
                            if (totalScoreContainer && totalScoreSpan) {
                                totalScoreSpan.textContent = data.total_score.toLocaleString();
                                totalScoreContainer.style.display = 'block';
                            }
                            // Also update HUD total score
                            const hudTotalScore = document.getElementById('hud-total-score');
                            if (hudTotalScore) {
                                hudTotalScore.textContent = data.total_score.toLocaleString();
                            }
                        }

                        // Score saved successfully
                        if (isTimeUp) {
                            const savedMsg = document.createElement('p');
                            savedMsg.style.color = '#00ff00';
                            savedMsg.style.fontWeight = 'bold';
                            savedMsg.style.marginTop = '10px';
                            savedMsg.textContent = '✓ Score saved successfully!';
                            const btnContainer = gameOverDiv.querySelector('.game-btn-container');
                            if (btnContainer) {
                                gameOverDiv.insertBefore(savedMsg, btnContainer);
                            } else {
                                gameOverDiv.appendChild(savedMsg);
                            }
                        }
                    }
                } catch (error) {
                    console.error('Error saving score:', error);
                }
            }
        }

        // Cleanup and Exit Prevention
        window.addEventListener('beforeunload', function(e) {
            // Cleanup intervals
            if (contestTimerInterval) clearInterval(contestTimerInterval);
            if (backgroundGameLoop) clearInterval(backgroundGameLoop);

            if (state.isPlaying) {
                // Standard way to trigger browser confirmation dialog
                e.preventDefault();
                e.returnValue = '';
                return '';
            }
        });

        // Initialize visibility state
        isTabVisible = !document.hidden;

        // Function to show custom exit confirmation
        function showExitConfirmation() {
            console.log("Showing exit confirmation...");
            const modal = document.getElementById('exit-confirm-modal');
            const yesBtn = document.getElementById('exit-yes-btn');
            const noBtn = document.getElementById('exit-no-btn');
            
            if (modal) {
                modal.style.display = 'flex';
                
                // Handle No (Continue Playing)
                noBtn.onclick = function(e) {
                    console.log("No clicked");
                    e.preventDefault();
                    e.stopPropagation();
                    modal.style.display = 'none';
                    if (window.history.state && window.history.state.inGame) {
                        // Already in game state
                    } else {
                        preventExitDuringGame();
                    }
                };
                
                // Handle Yes (Save & Exit)
                yesBtn.onclick = async function(e) {
                    console.log("Yes clicked");
                    e.preventDefault();
                    e.stopPropagation();
                    modal.style.display = 'none';
                    state.isPlaying = false;
                    
                    // Hide EXIT button
                    const exitBtnHud = document.getElementById('exit-game-btn-hud');
                    if (exitBtnHud) exitBtnHud.style.display = 'none';
                    
                    await gameOver();
                    window.location.replace('index.php');
                };
            }
        }

        // Attach to HUD exit button
        document.getElementById('exit-game-btn-hud').addEventListener('click', function(e) {
            if (state.isPlaying) {
                showExitConfirmation();
            }
        });

        // Prevention of accidental exit during gameplay
        function preventExitDuringGame() {
            // Push a dummy state to history to catch the back button
            window.history.pushState({ inGame: true }, "");
        }

        window.addEventListener('popstate', async function(event) {
            if (state.isPlaying) {
                showExitConfirmation();
            }
        });

        // Start
        updateHUD();
        animate();

    </script>
</body>
</html>