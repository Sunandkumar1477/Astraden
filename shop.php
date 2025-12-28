<?php
// Start output buffering at the very beginning for AJAX requests
if (ob_get_level() == 0) {
    ob_start();
}

// Detect AJAX requests early (before session/connection)
$is_ajax_early = (
    (!empty($_POST['ajax']) && $_POST['ajax'] == '1') ||
    (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') ||
    (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
);

// Suppress any warnings/notices that might output HTML
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
require_once 'connection.php';

// Use the early AJAX detection
$is_ajax = $is_ajax_early;

// Helper function to send JSON response and exit
function sendJsonResponse($success, $message, $data = []) {
    // Clean all output buffers
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    
    // Only set headers if they haven't been sent
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        header('X-Content-Type-Options: nosniff');
        header('Pragma: no-cache');
    }
    
    // Build response
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    // Merge additional data
    if (!empty($data)) {
        $response = array_merge($response, $data);
    }
    
    // Output JSON and exit
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    if ($is_ajax) {
        sendJsonResponse(false, 'Please login first');
    }
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get user's Fluxon (total score) from users table
// This is now stored directly in users.total_score for better performance
try {
    // Check if total_score column exists, if not, add it
    $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'total_score'");
    if ($check_column && $check_column->num_rows == 0) {
        $conn->query("ALTER TABLE users ADD COLUMN total_score BIGINT(20) NOT NULL DEFAULT 0 COMMENT 'Total score (Fluxon)'");
        // Initialize from game_leaderboard
        $conn->query("UPDATE users u SET total_score = COALESCE((SELECT SUM(score) FROM game_leaderboard gl WHERE gl.user_id = u.id), 0)");
    }
    if ($check_column) $check_column->close();
    
    if (!$fluxon_stmt = $conn->prepare("SELECT total_score FROM users WHERE id = ?")) {
        throw new Exception("Database error: " . $conn->error);
    }
    $fluxon_stmt->bind_param("i", $user_id);
    if (!$fluxon_stmt->execute()) {
        throw new Exception("Database error: " . $fluxon_stmt->error);
    }
    $fluxon_result = $fluxon_stmt->get_result();
    $fluxon_data = $fluxon_result->fetch_assoc();
    $total_fluxon = intval($fluxon_data['total_score'] ?? 0);
    if ($total_fluxon < 0) $total_fluxon = 0;
    $fluxon_stmt->close();
} catch (Exception $e) {
    if ($is_ajax) {
        sendJsonResponse(false, "Error loading balance: " . $e->getMessage());
    }
    $total_fluxon = 0;
}

// Shop pricing table is managed by admin only
// No trading functionality on this page

// Get user's Astrons - create user_profile if it doesn't exist
try {
    if (!$credits_stmt = $conn->prepare("SELECT credits FROM user_profile WHERE user_id = ?")) {
        throw new Exception("Database error: " . $conn->error);
    }
    $credits_stmt->bind_param("i", $user_id);
    if (!$credits_stmt->execute()) {
        throw new Exception("Database error: " . $credits_stmt->error);
    }
    $credits_result = $credits_stmt->get_result();
    $credits_data = $credits_result->fetch_assoc();
    
    if ($credits_data === null) {
        // Create user_profile if it doesn't exist
        $create_profile = $conn->prepare("INSERT INTO user_profile (user_id, credits) VALUES (?, 0) ON DUPLICATE KEY UPDATE credits = credits");
        if ($create_profile) {
            $create_profile->bind_param("i", $user_id);
            $create_profile->execute();
            $create_profile->close();
        }
        $user_astrons = 0;
    } else {
        $user_astrons = intval($credits_data['credits'] ?? 0);
    }
    $credits_stmt->close();
} catch (Exception $e) {
    if ($is_ajax) {
        sendJsonResponse(false, "Error loading credits: " . $e->getMessage());
    }
    $user_astrons = 0;
}

// No trading functionality - removed
$message = '';
$error = '';

// If not AJAX, continue with normal page rendering
if (!$is_ajax) {
    ob_end_flush();
}

// Don't close connection here - it might be needed by included files or AJAX requests
// Connection will be closed automatically at script end
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop - Astra Den</title>
    <link rel="icon" type="image/svg+xml" href="Alogo.svg">
    <link rel="shortcut icon" type="image/svg+xml" href="Alogo.svg">
    <link rel="alternate icon" type="image/png" href="Alogo.svg">
    <link rel="apple-touch-icon" sizes="180x180" href="Alogo.svg">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/index.css">
    <style>
        :root {
            --primary-cyan: #00ffff;
            --primary-purple: #9d4edd;
            --primary-pink: #ff006e;
            --dark-bg: #0a0a0f;
            --card-bg: rgba(15, 15, 25, 0.85);
        }

        .shop-page {
            min-height: 100vh;
            position: relative;
            padding: 120px 20px 50px;
            z-index: 1;
        }

        .shop-header {
            text-align: center;
            margin-bottom: 50px;
            position: relative;
            z-index: 2;
        }

        .shop-header h1 {
            font-family: 'Orbitron', sans-serif;
            font-size: clamp(2rem, 5vw, 3.5rem);
            color: var(--primary-cyan);
            text-shadow: 0 0 30px rgba(0, 255, 255, 0.8);
            margin-bottom: 15px;
            letter-spacing: 3px;
            font-weight: 900;
        }

        .shop-header p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 1.1rem;
            margin-top: 10px;
        }

        .balance-section {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-bottom: 50px;
            flex-wrap: wrap;
            position: relative;
            z-index: 2;
        }

        .balance-card {
            background: var(--card-bg);
            border: 2px solid var(--primary-cyan);
            border-radius: 20px;
            padding: 25px 40px;
            text-align: center;
            min-width: 250px;
            box-shadow: 0 0 30px rgba(0, 255, 255, 0.3);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .balance-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(0, 255, 255, 0.1), transparent);
            animation: rotate 10s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .balance-card.fluxon {
            border-color: #00ff00;
            box-shadow: 0 0 30px rgba(0, 255, 0, 0.3);
        }

        .balance-card.astrons {
            border-color: #FFD700;
            box-shadow: 0 0 30px rgba(255, 215, 0, 0.3);
        }

        .balance-card.animating {
            animation: pulse 0.5s ease-in-out;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .balance-label {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.6);
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 600;
        }

        .balance-value {
            font-size: 2.5rem;
            font-weight: 900;
            font-family: 'Orbitron', sans-serif;
            position: relative;
            z-index: 1;
        }

        .balance-card.fluxon .balance-value {
            color: #00ff00;
            text-shadow: 0 0 20px rgba(0, 255, 0, 0.8);
        }

        .balance-card.astrons .balance-value {
            color: #FFD700;
            text-shadow: 0 0 20px rgba(255, 215, 0, 0.8);
        }

        .shop-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 30px;
            position: relative;
            z-index: 2;
        }

        .shop-item {
            background: var(--card-bg);
            border: 2px solid var(--primary-purple);
            border-radius: 25px;
            padding: 35px;
            text-align: center;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(10px);
        }

        .shop-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(0, 255, 255, 0.05), rgba(157, 78, 221, 0.05));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .shop-item:hover {
            transform: translateY(-10px) scale(1.02);
            border-color: var(--primary-cyan);
            box-shadow: 0 15px 50px rgba(0, 255, 255, 0.4);
        }

        .shop-item:hover::before {
            opacity: 1;
        }

        .item-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: linear-gradient(135deg, var(--primary-cyan), var(--primary-purple));
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            font-family: 'Orbitron', sans-serif;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .item-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            filter: drop-shadow(0 0 20px currentColor);
            position: relative;
            z-index: 1;
        }

        .item-name {
            font-family: 'Orbitron', sans-serif;
            font-size: 1.5rem;
            color: var(--primary-cyan);
            margin-bottom: 15px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
            position: relative;
            z-index: 1;
        }

        .item-details {
            background: rgba(0, 0, 0, 0.4);
            border-radius: 15px;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid rgba(0, 255, 255, 0.2);
            position: relative;
            z-index: 1;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            font-size: 0.95rem;
        }

        .detail-row:last-child {
            margin-bottom: 0;
        }

        .detail-label {
            color: rgba(255, 255, 255, 0.6);
        }

        .detail-value {
            color: #FFD700;
            font-weight: 700;
            font-family: 'Orbitron', sans-serif;
        }

        .conversion-rate {
            text-align: center;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.85rem;
            margin-top: 10px;
            font-style: italic;
        }

        .purchase-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, var(--primary-cyan), var(--primary-purple));
            border: none;
            border-radius: 12px;
            color: white;
            font-family: 'Orbitron', sans-serif;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
            z-index: 1;
            margin-top: 20px;
        }

        .purchase-btn:hover:not(:disabled) {
            transform: scale(1.05);
            box-shadow: 0 0 30px rgba(0, 255, 255, 0.6);
        }

        .purchase-btn:active:not(:disabled) {
            transform: scale(0.98);
        }

        .purchase-btn:disabled {
            background: rgba(100, 100, 100, 0.3);
            color: rgba(255, 255, 255, 0.4);
            cursor: not-allowed;
            border: 2px solid rgba(255, 255, 255, 0.1);
        }

        .back-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            padding: 12px 25px;
            background: var(--card-bg);
            border: 2px solid var(--primary-cyan);
            border-radius: 10px;
            color: var(--primary-cyan);
            text-decoration: none;
            font-family: 'Orbitron', sans-serif;
            font-weight: 700;
            transition: all 0.3s ease;
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .back-btn:hover {
            background: rgba(0, 255, 255, 0.1);
            box-shadow: 0 0 20px rgba(0, 255, 255, 0.5);
            transform: translateX(-3px);
        }

        .message {
            max-width: 600px;
            margin: 0 auto 30px;
            background: rgba(0, 255, 0, 0.1);
            border: 2px solid #00ff00;
            color: #00ff00;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            font-weight: 600;
            box-shadow: 0 0 20px rgba(0, 255, 0, 0.3);
            position: relative;
            z-index: 2;
        }

        .error {
            max-width: 600px;
            margin: 0 auto 30px;
            background: rgba(255, 0, 0, 0.1);
            border: 2px solid #ff0000;
            color: #ff0000;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            font-weight: 600;
            box-shadow: 0 0 20px rgba(255, 0, 0, 0.3);
            position: relative;
            z-index: 2;
        }

        @media (max-width: 768px) {
            .shop-page {
                padding: 100px 15px 30px;
            }

            .balance-section {
                gap: 20px;
            }

            .balance-card {
                min-width: 100%;
                padding: 20px 30px;
            }

            .shop-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .back-btn {
                top: 15px;
                left: 15px;
                padding: 10px 20px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body class="no-select" oncontextmenu="return false;">
    <!-- Space Background -->
    <div id="space-background"></div>
    
    <a href="index.php" class="back-btn">
        <span>←</span>
        <span>Back</span>
    </a>
    
    <div class="shop-page">
        <div class="shop-header">
            <h1>💰 SHOP</h1>
            <p>View your balance</p>
        </div>
        
        <div class="balance-section">
            <div class="balance-card fluxon" id="fluxonCard">
                <div class="balance-label">Total Fluxon</div>
                <div class="balance-value" id="fluxonBalance" data-value="<?php echo $total_fluxon; ?>"><?php echo number_format($total_fluxon); ?></div>
            </div>
            <div class="balance-card astrons" id="astronsCard">
                <div class="balance-label">Your Astrons</div>
                <div class="balance-value" id="astronsBalance" data-value="<?php echo $user_astrons; ?>"><?php echo number_format($user_astrons); ?></div>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="message">✓ <?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error">✗ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div style="text-align: center; margin: 40px 0 30px; padding: 30px; background: rgba(0, 255, 255, 0.1); border: 2px solid var(--primary-cyan); border-radius: 15px; max-width: 800px; margin-left: auto; margin-right: auto;">
            <h2 style="color: var(--primary-cyan); margin-bottom: 15px; font-family: 'Orbitron', sans-serif;">💰 Your Balance</h2>
            <p style="color: rgba(255, 255, 255, 0.8); font-size: 1.1rem;">
                You have <strong style="color: #00ff00;"><?php echo number_format($total_fluxon); ?> Fluxon</strong> 
                and <strong style="color: #FFD700;"><?php echo number_format($user_astrons); ?> Astrons</strong>
            </p>
        </div>
    </div>
    
    <script>
        // Initialize space background
        const spaceBg = document.getElementById('space-background');
        if (spaceBg && typeof createSpaceBackground === 'function') {
            createSpaceBackground();
        } else {
            // Fallback if function doesn't exist
            for (let i = 0; i < 100; i++) {
                const star = document.createElement('div');
                star.className = 'star';
                star.style.left = Math.random() * 100 + '%';
                star.style.top = Math.random() * 100 + '%';
                star.style.animationDelay = Math.random() * 3 + 's';
                spaceBg.appendChild(star);
            }
        }

        // Track processing state to prevent multiple simultaneous requests
        const processingState = new Set();
        
        // Animate value function
        function animateValue(element, start, end, duration, callback) {
            const startTime = performance.now();
            const startValue = start;
            const endValue = end;
            
            function update(currentTime) {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                
                // Easing function
                const easeProgress = 1 - Math.pow(1 - progress, 3);
                
                const current = Math.round(startValue + (endValue - startValue) * easeProgress);
                element.textContent = current.toLocaleString();
                
                if (progress < 1) {
                    requestAnimationFrame(update);
                } else {
                    element.textContent = endValue.toLocaleString();
                    if (callback) callback();
                }
            }
            
            requestAnimationFrame(update);
        }

        // Trading functionality removed
    </script>
</body>
</html>
