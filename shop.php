<?php
session_start();
require_once 'connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get user's Fluxon (total score from games)
$fluxon_stmt = $conn->prepare("SELECT COALESCE(SUM(score), 0) as total_fluxon FROM game_leaderboard WHERE user_id = ? AND credits_used > 0");
$fluxon_stmt->bind_param("i", $user_id);
$fluxon_stmt->execute();
$fluxon_result = $fluxon_stmt->get_result();
$fluxon_data = $fluxon_result->fetch_assoc();
$total_fluxon = intval($fluxon_data['total_fluxon'] ?? 0);
$fluxon_stmt->close();

// Get shop prices from database
$create_table = $conn->query("
    CREATE TABLE IF NOT EXISTS shop_pricing (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        fluxon_amount INT(11) NOT NULL COMMENT 'Amount of Fluxon required',
        astrons_reward INT(11) NOT NULL COMMENT 'Astrons user gets',
        claim_type VARCHAR(50) NOT NULL COMMENT 'Type name',
        is_active TINYINT(1) DEFAULT 1,
        display_order INT(11) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_fluxon (fluxon_amount)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Get prices from database
$prices_result = $conn->query("SELECT * FROM shop_pricing WHERE is_active = 1 ORDER BY display_order ASC LIMIT 3");
$shop_prices = [];
if ($prices_result && $prices_result->num_rows > 0) {
    $shop_prices = $prices_result->fetch_all(MYSQLI_ASSOC);
} else {
    // Default prices if none set
    $shop_prices = [
        ['id' => 1, 'fluxon_amount' => 5000, 'astrons_reward' => 10, 'claim_type' => 'Basic Claim'],
        ['id' => 2, 'fluxon_amount' => 7500, 'astrons_reward' => 20, 'claim_type' => 'Standard Claim'],
        ['id' => 3, 'fluxon_amount' => 10000, 'astrons_reward' => 30, 'claim_type' => 'Premium Claim']
    ];
}

// Get user's Astrons
$credits_stmt = $conn->prepare("SELECT credits FROM user_profile WHERE user_id = ?");
$credits_stmt->bind_param("i", $user_id);
$credits_stmt->execute();
$credits_result = $credits_stmt->get_result();
$credits_data = $credits_result->fetch_assoc();
$user_astrons = intval($credits_data['credits'] ?? 0);
$credits_stmt->close();

// Handle purchase with discount system
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purchase_item'])) {
    $item_id = intval($_POST['item_id'] ?? 0);
    $item_cost = intval($_POST['item_cost'] ?? 0);
    $item_astrons = intval($_POST['item_astrons'] ?? 0);
    
    if ($item_id > 0 && $item_cost > 0 && $item_astrons > 0) {
        if ($total_fluxon >= $item_cost) {
            // Add Astrons based on discount rate
            $update_stmt = $conn->prepare("UPDATE user_profile SET credits = credits + ? WHERE user_id = ?");
            $update_stmt->bind_param("ii", $item_astrons, $user_id);
            
            if ($update_stmt->execute()) {
                $message = "Successfully claimed {$item_astrons} Astrons using {$item_cost} Fluxon!";
                // Refresh user data
                $credits_stmt = $conn->prepare("SELECT credits FROM user_profile WHERE user_id = ?");
                $credits_stmt->bind_param("i", $user_id);
                $credits_stmt->execute();
                $credits_result = $credits_stmt->get_result();
                $credits_data = $credits_result->fetch_assoc();
                $user_astrons = intval($credits_data['credits'] ?? 0);
                $credits_stmt->close();
                // Refresh Fluxon (it doesn't change, but refresh to be sure)
                $fluxon_stmt = $conn->prepare("SELECT COALESCE(SUM(score), 0) as total_fluxon FROM game_leaderboard WHERE user_id = ? AND credits_used > 0");
                $fluxon_stmt->bind_param("i", $user_id);
                $fluxon_stmt->execute();
                $fluxon_result = $fluxon_stmt->get_result();
                $fluxon_data = $fluxon_result->fetch_assoc();
                $total_fluxon = intval($fluxon_data['total_fluxon'] ?? 0);
                $fluxon_stmt->close();
            } else {
                $error = "Failed to process purchase. Please try again.";
            }
            $update_stmt->close();
        } else {
            $error = "Insufficient Fluxon! You need {$item_cost} Fluxon to claim this item.";
        }
    }
}

$conn->close();
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
            <h1>🛒 FLUXON SHOP</h1>
            <p>Convert your game scores into Astrons</p>
        </div>
        
        <div class="balance-section">
            <div class="balance-card fluxon">
                <div class="balance-label">Total Fluxon</div>
                <div class="balance-value" id="fluxonBalance"><?php echo number_format($total_fluxon); ?></div>
            </div>
            <div class="balance-card astrons">
                <div class="balance-label">Your Astrons</div>
                <div class="balance-value" id="astronsBalance"><?php echo number_format($user_astrons); ?></div>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="message">✓ <?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error">✗ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="shop-grid">
            <?php 
            $colors = ['#00ffff', '#9d4edd', '#FFD700'];
            $icons = ['⚡', '⚡⚡', '⚡⚡⚡'];
            $badges = ['BASIC', 'STANDARD', 'PREMIUM'];
            foreach ($shop_prices as $index => $price): 
                $fluxon = intval($price['fluxon_amount']);
                $astrons = intval($price['astrons_reward']);
                $type = htmlspecialchars($price['claim_type']);
                $color = $colors[$index] ?? '#00ffff';
                $icon = $icons[$index] ?? '⚡';
                $badge = $badges[$index] ?? 'CLAIM';
                $ratio = round($fluxon / $astrons, 0);
                $can_afford = $total_fluxon >= $fluxon;
            ?>
            <div class="shop-item" style="border-color: <?php echo $color; ?>;">
                <div class="item-badge" style="background: linear-gradient(135deg, <?php echo $color; ?>, <?php echo $index == 2 ? '#FFA500' : $color; ?>);">
                    <?php echo $badge; ?>
                </div>
                <div class="item-icon" style="color: <?php echo $color; ?>;"><?php echo $icon; ?></div>
                <div class="item-name" style="color: <?php echo $color; ?>;"><?php echo $type; ?></div>
                
                <div class="item-details">
                    <div class="detail-row">
                        <span class="detail-label">Cost:</span>
                        <span class="detail-value"><?php echo number_format($fluxon); ?> Fluxon</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Reward:</span>
                        <span class="detail-value"><?php echo $astrons; ?> Astrons</span>
                    </div>
                    <div class="conversion-rate">
                        <?php echo number_format($ratio); ?> Fluxon per Astron
                    </div>
                </div>
                
                <form method="POST" onsubmit="return confirm('Claim <?php echo $astrons; ?> Astrons for <?php echo number_format($fluxon); ?> Fluxon?');">
                    <input type="hidden" name="item_id" value="<?php echo $price['id']; ?>">
                    <input type="hidden" name="item_cost" value="<?php echo $fluxon; ?>">
                    <input type="hidden" name="item_astrons" value="<?php echo $astrons; ?>">
                    <button type="submit" name="purchase_item" class="purchase-btn" <?php echo !$can_afford ? 'disabled' : ''; ?>>
                        <?php echo $can_afford ? "Claim {$astrons} Astrons" : "Insufficient Fluxon"; ?>
                    </button>
                </form>
            </div>
            <?php endforeach; ?>
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

        // Auto-refresh balance after purchase
        <?php if ($message): ?>
        setTimeout(function() {
            location.reload();
        }, 2000);
        <?php endif; ?>
    </script>
</body>
</html>
