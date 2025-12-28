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
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="Alogo.svg">
    <link rel="shortcut icon" type="image/svg+xml" href="Alogo.svg">
    <link rel="alternate icon" type="image/png" href="Alogo.svg">
    <link rel="apple-touch-icon" sizes="180x180" href="Alogo.svg">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .shop-container {
            max-width: 1200px;
            margin: 100px auto 50px;
            padding: 20px;
        }
        .shop-header {
            text-align: center;
            margin-bottom: 40px;
        }
        .shop-header h1 {
            font-family: 'Orbitron', sans-serif;
            font-size: 2.5rem;
            color: var(--primary-cyan);
            text-shadow: 0 0 20px rgba(0, 255, 255, 0.5);
            margin-bottom: 10px;
        }
        .user-balance {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        .balance-card {
            background: rgba(0, 0, 0, 0.5);
            border: 2px solid var(--primary-cyan);
            border-radius: 15px;
            padding: 20px 30px;
            text-align: center;
            min-width: 200px;
        }
        .balance-label {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 10px;
        }
        .balance-value {
            font-size: 2rem;
            font-weight: bold;
            color: #FFD700;
            font-family: 'Orbitron', sans-serif;
        }
        .shop-items {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        .shop-item {
            background: rgba(15, 15, 25, 0.9);
            border: 2px solid var(--primary-purple);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
        }
        .shop-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(157, 78, 221, 0.5);
            border-color: var(--primary-cyan);
        }
        .item-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        .item-name {
            font-family: 'Orbitron', sans-serif;
            font-size: 1.3rem;
            color: var(--primary-cyan);
            margin-bottom: 10px;
        }
        .item-description {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 20px;
            min-height: 40px;
        }
        .item-cost {
            font-size: 1.1rem;
            color: #FFD700;
            font-weight: bold;
            margin-bottom: 15px;
        }
        .purchase-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, rgba(0, 255, 255, 0.2), rgba(157, 78, 221, 0.2));
            border: 2px solid var(--primary-cyan);
            border-radius: 8px;
            color: var(--primary-cyan);
            font-family: 'Orbitron', sans-serif;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .purchase-btn:hover:not(:disabled) {
            background: linear-gradient(135deg, rgba(0, 255, 255, 0.4), rgba(157, 78, 221, 0.4));
            box-shadow: 0 0 20px rgba(0, 255, 255, 0.5);
        }
        .purchase-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .message {
            background: rgba(0, 255, 0, 0.2);
            border: 2px solid #00ff00;
            color: #00ff00;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        .error {
            background: rgba(255, 0, 0, 0.2);
            border: 2px solid #ff0000;
            color: #ff0000;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        .back-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            padding: 10px 20px;
            background: rgba(0, 0, 0, 0.5);
            border: 2px solid var(--primary-cyan);
            border-radius: 8px;
            color: var(--primary-cyan);
            text-decoration: none;
            font-family: 'Orbitron', sans-serif;
            transition: all 0.3s ease;
        }
        .back-btn:hover {
            background: rgba(0, 255, 255, 0.2);
            box-shadow: 0 0 15px rgba(0, 255, 255, 0.5);
        }
    </style>
</head>
<body class="no-select" oncontextmenu="return false;">
    <a href="index.php" class="back-btn">← Back to Games</a>
    
    <div class="shop-container">
        <div class="shop-header">
            <h1>🛒 FLUXON SHOP</h1>
            <p style="color: rgba(255, 255, 255, 0.7);">Claim Astrons using your Fluxon (Game Score)</p>
            <p style="color: rgba(0, 255, 255, 0.7); font-size: 0.9rem; margin-top: 10px;">Your total Fluxon from all games: <strong style="color: #FFD700;"><?php echo number_format($total_fluxon); ?></strong></p>
        </div>
        
        <div class="user-balance">
            <div class="balance-card" style="border-color: #00ff00;">
                <div class="balance-label">Your Total Fluxon</div>
                <div class="balance-value" id="fluxonBalance" style="color: #00ff00;"><?php echo number_format($total_fluxon); ?></div>
                <div style="font-size: 0.75rem; color: rgba(255, 255, 255, 0.6); margin-top: 5px;">Available to Claim</div>
            </div>
            <div class="balance-card">
                <div class="balance-label">Your Astrons</div>
                <div class="balance-value" id="astronsBalance"><?php echo number_format($user_astrons); ?></div>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="shop-items">
            <?php 
            $colors = ['var(--primary-cyan)', '#9d4edd', '#FFD700'];
            $icons = ['⚡', '⚡⚡', '⚡⚡⚡'];
            $shadows = ['', '', 'box-shadow: 0 0 20px rgba(255, 215, 0, 0.3);'];
            foreach ($shop_prices as $index => $price): 
                $fluxon = intval($price['fluxon_amount']);
                $astrons = intval($price['astrons_reward']);
                $type = htmlspecialchars($price['claim_type']);
                $color = $colors[$index] ?? '#00ffff';
                $icon = $icons[$index] ?? '⚡';
                $shadow = $shadows[$index] ?? '';
                $ratio = $fluxon / $astrons;
            ?>
            <div class="shop-item" style="border-color: <?php echo $color; ?>; <?php echo $shadow; ?>">
                <div class="item-icon"><?php echo $icon; ?></div>
                <div class="item-name" style="color: <?php echo $color; ?>;"><?php echo $type; ?></div>
                <div class="item-description">Conversion rate: <?php echo number_format($ratio); ?> Fluxon per Astron</div>
                <div style="margin: 15px 0; padding: 10px; background: rgba(<?php echo $index == 0 ? '0, 255, 255' : ($index == 1 ? '157, 78, 221' : '255, 215, 0'); ?>, 0.1); border-radius: 8px;">
                    <div style="font-size: 0.85rem; color: rgba(255, 255, 255, 0.7); margin-bottom: 5px;">Conversion Rate:</div>
                    <div style="font-size: 1.1rem; color: #FFD700; font-weight: bold;"><?php echo number_format($fluxon); ?> Fluxon = <?php echo $astrons; ?> Astrons</div>
                </div>
                <div class="item-cost">Cost: <?php echo number_format($fluxon); ?> Fluxon</div>
                <div style="color: #00ff00; font-weight: bold; margin-bottom: 15px;">You Get: <?php echo $astrons; ?> Astrons</div>
                <form method="POST" onsubmit="return confirm('Claim <?php echo $astrons; ?> Astrons for <?php echo number_format($fluxon); ?> Fluxon?');">
                    <input type="hidden" name="item_id" value="<?php echo $price['id']; ?>">
                    <input type="hidden" name="item_cost" value="<?php echo $fluxon; ?>">
                    <input type="hidden" name="item_astrons" value="<?php echo $astrons; ?>">
                    <button type="submit" name="purchase_item" class="purchase-btn" style="border-color: <?php echo $color; ?>; color: <?php echo $color; ?>; <?php echo $index == 2 ? 'background: linear-gradient(135deg, rgba(255, 215, 0, 0.2), rgba(255, 165, 0, 0.2));' : ''; ?>" <?php echo $total_fluxon < $fluxon ? 'disabled' : ''; ?>>
                        <?php echo $total_fluxon < $fluxon ? 'Insufficient Fluxon' : "Claim {$astrons} Astrons"; ?>
                    </button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <script>
        // Auto-refresh balance after purchase
        <?php if ($message): ?>
        setTimeout(function() {
            location.reload();
        }, 2000);
        <?php endif; ?>
    </script>
</body>
</html>

