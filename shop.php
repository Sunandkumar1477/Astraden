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

// Get user's Astrons
$credits_stmt = $conn->prepare("SELECT credits FROM user_profile WHERE user_id = ?");
$credits_stmt->bind_param("i", $user_id);
$credits_stmt->execute();
$credits_result = $credits_stmt->get_result();
$credits_data = $credits_result->fetch_assoc();
$user_astrons = intval($credits_data['credits'] ?? 0);
$credits_stmt->close();

// Handle purchase
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purchase_item'])) {
    $item_id = intval($_POST['item_id'] ?? 0);
    $item_cost = intval($_POST['item_cost'] ?? 0);
    
    if ($item_id > 0 && $item_cost > 0) {
        if ($total_fluxon >= $item_cost) {
            // Deduct Fluxon and add Astrons
            // For now, we'll add Astrons equal to the Fluxon cost (1:1 conversion)
            $add_astrons = $item_cost;
            
            $update_stmt = $conn->prepare("UPDATE user_profile SET credits = credits + ? WHERE user_id = ?");
            $update_stmt->bind_param("ii", $add_astrons, $user_id);
            
            if ($update_stmt->execute()) {
                $message = "Successfully claimed {$add_astrons} Astrons using {$item_cost} Fluxon!";
                // Refresh user data
                $credits_stmt = $conn->prepare("SELECT credits FROM user_profile WHERE user_id = ?");
                $credits_stmt->bind_param("i", $user_id);
                $credits_stmt->execute();
                $credits_result = $credits_stmt->get_result();
                $credits_data = $credits_result->fetch_assoc();
                $user_astrons = intval($credits_data['credits'] ?? 0);
                $credits_stmt->close();
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
            <p style="color: rgba(255, 255, 255, 0.7);">Claim Astrons using your Fluxon</p>
        </div>
        
        <div class="user-balance">
            <div class="balance-card">
                <div class="balance-label">Your Fluxon</div>
                <div class="balance-value" id="fluxonBalance"><?php echo number_format($total_fluxon); ?></div>
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
            <!-- Item 1: 100 Astrons -->
            <div class="shop-item">
                <div class="item-icon">⚡</div>
                <div class="item-name">100 Astrons</div>
                <div class="item-description">Claim 100 Astrons using your Fluxon</div>
                <div class="item-cost">Cost: 100 Fluxon</div>
                <form method="POST" onsubmit="return confirm('Claim 100 Astrons for 100 Fluxon?');">
                    <input type="hidden" name="item_id" value="1">
                    <input type="hidden" name="item_cost" value="100">
                    <button type="submit" name="purchase_item" class="purchase-btn" <?php echo $total_fluxon < 100 ? 'disabled' : ''; ?>>
                        <?php echo $total_fluxon < 100 ? 'Insufficient Fluxon' : 'Claim Now'; ?>
                    </button>
                </form>
            </div>
            
            <!-- Item 2: 500 Astrons -->
            <div class="shop-item">
                <div class="item-icon">⚡⚡</div>
                <div class="item-name">500 Astrons</div>
                <div class="item-description">Claim 500 Astrons using your Fluxon</div>
                <div class="item-cost">Cost: 500 Fluxon</div>
                <form method="POST" onsubmit="return confirm('Claim 500 Astrons for 500 Fluxon?');">
                    <input type="hidden" name="item_id" value="2">
                    <input type="hidden" name="item_cost" value="500">
                    <button type="submit" name="purchase_item" class="purchase-btn" <?php echo $total_fluxon < 500 ? 'disabled' : ''; ?>>
                        <?php echo $total_fluxon < 500 ? 'Insufficient Fluxon' : 'Claim Now'; ?>
                    </button>
                </form>
            </div>
            
            <!-- Item 3: 1000 Astrons -->
            <div class="shop-item">
                <div class="item-icon">⚡⚡⚡</div>
                <div class="item-name">1000 Astrons</div>
                <div class="item-description">Claim 1000 Astrons using your Fluxon</div>
                <div class="item-cost">Cost: 1000 Fluxon</div>
                <form method="POST" onsubmit="return confirm('Claim 1000 Astrons for 1000 Fluxon?');">
                    <input type="hidden" name="item_id" value="3">
                    <input type="hidden" name="item_cost" value="1000">
                    <button type="submit" name="purchase_item" class="purchase-btn" <?php echo $total_fluxon < 1000 ? 'disabled' : ''; ?>>
                        <?php echo $total_fluxon < 1000 ? 'Insufficient Fluxon' : 'Claim Now'; ?>
                    </button>
                </form>
            </div>
            
            <!-- Item 4: 5000 Astrons -->
            <div class="shop-item">
                <div class="item-icon">⚡⚡⚡⚡</div>
                <div class="item-name">5000 Astrons</div>
                <div class="item-description">Claim 5000 Astrons using your Fluxon</div>
                <div class="item-cost">Cost: 5000 Fluxon</div>
                <form method="POST" onsubmit="return confirm('Claim 5000 Astrons for 5000 Fluxon?');">
                    <input type="hidden" name="item_id" value="4">
                    <input type="hidden" name="item_cost" value="5000">
                    <button type="submit" name="purchase_item" class="purchase-btn" <?php echo $total_fluxon < 5000 ? 'disabled' : ''; ?>>
                        <?php echo $total_fluxon < 5000 ? 'Insufficient Fluxon' : 'Claim Now'; ?>
                    </button>
                </form>
            </div>
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

