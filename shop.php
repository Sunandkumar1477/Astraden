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

// Ensure shop_pricing table exists
$conn->query("
    CREATE TABLE IF NOT EXISTS shop_pricing (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        fluxon_amount INT(11) NOT NULL,
        astrons_reward INT(11) NOT NULL,
        claim_type VARCHAR(50) NOT NULL,
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
        ['id' => 1, 'fluxon_amount' => 5000, 'astrons_reward' => 10, 'claim_type' => 'Basic Access'],
        ['id' => 2, 'fluxon_amount' => 7500, 'astrons_reward' => 20, 'claim_type' => 'Standard Pack'],
        ['id' => 3, 'fluxon_amount' => 10000, 'astrons_reward' => 30, 'claim_type' => 'Elite Reserve']
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

// Handle purchase
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purchase_item'])) {
    $item_id = intval($_POST['item_id'] ?? 0);
    $item_cost = intval($_POST['item_cost'] ?? 0);
    $item_astrons = intval($_POST['item_astrons'] ?? 0);
    
    if ($item_id > 0 && $item_cost > 0 && $item_astrons > 0) {
        if ($total_fluxon >= $item_cost) {
            $update_stmt = $conn->prepare("UPDATE user_profile SET credits = credits + ? WHERE user_id = ?");
            $update_stmt->bind_param("ii", $item_astrons, $user_id);
            
            if ($update_stmt->execute()) {
                $message = "Nexus Link Established! Claimed {$item_astrons} Astrons.";
                header('refresh:2;url=aetheric_mandala.php');
            } else {
                $error = "Transmission Failure. Try again.";
            }
            $update_stmt->close();
        } else {
            $error = "Insufficient Fluxon Energy!";
        }
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Fluxon Shop - Space Hub</title>
    <link rel="icon" type="image/svg+xml" href="Alogo.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --cyan: #00ffff;
            --purple: #9d4edd;
            --gold: #FFD700;
            --bg-dark: #050508;
            --card-bg: rgba(15, 15, 25, 0.85);
            --neon-glow: 0 0 15px rgba(0, 255, 255, 0.4);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: 'Rajdhani', sans-serif;
            background: var(--bg-dark);
            color: #fff;
            min-height: 100vh;
            padding-bottom: 50px;
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(157, 78, 221, 0.1) 0%, transparent 40%),
                radial-gradient(circle at 90% 80%, rgba(0, 255, 255, 0.08) 0%, transparent 40%);
            background-attachment: fixed;
        }

        /* --- NAVIGATION --- */
        .nav-bar {
            max-width: 1000px;
            margin: 15px auto;
            padding: 0 20px;
            display: flex;
            justify-content: flex-start;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--cyan);
            border-radius: 50px;
            color: var(--cyan);
            text-decoration: none;
            font-weight: 700;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: 0.3s ease;
        }

        .back-btn:hover {
            background: rgba(0, 255, 255, 0.15);
            box-shadow: var(--neon-glow);
        }

        /* --- HEADER --- */
        .shop-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .shop-header {
            text-align: center;
            margin: 20px 0 40px;
        }

        .shop-header h1 {
            font-family: 'Orbitron', sans-serif;
            font-size: clamp(1.8rem, 8vw, 3rem);
            color: var(--cyan);
            text-transform: uppercase;
            letter-spacing: 4px;
            text-shadow: 0 0 20px rgba(0, 255, 255, 0.5);
            margin-bottom: 10px;
        }

        .shop-header p {
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.6);
            letter-spacing: 2px;
        }

        /* --- BALANCE DASHBOARD --- */
        .user-balance {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 40px;
        }

        .balance-card {
            background: var(--card-bg);
            border: 1px solid rgba(0, 255, 255, 0.2);
            padding: 20px;
            border-radius: 16px;
            text-align: center;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(10px);
        }

        .balance-card.fluxon-card { border-color: var(--purple); }
        .balance-card.astron-card { border-color: var(--gold); }

        .balance-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 8px;
            color: rgba(255, 255, 255, 0.7);
        }

        .balance-value {
            font-family: 'Orbitron', sans-serif;
            font-size: clamp(1.2rem, 5vw, 2.2rem);
            font-weight: 900;
        }

        .fluxon-card .balance-value { color: var(--purple); }
        .astron-card .balance-value { color: var(--gold); }

        /* --- SHOP GRID --- */
        .shop-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
        }

        .shop-card {
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .shop-card:hover {
            transform: translateY(-10px);
            border-color: var(--cyan);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.6);
        }

        .item-visual {
            font-size: 3.5rem;
            margin-bottom: 15px;
            filter: drop-shadow(0 0 10px rgba(255, 255, 255, 0.2));
        }

        .item-name {
            font-family: 'Orbitron', sans-serif;
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: #fff;
        }

        .conversion-pill {
            display: inline-block;
            background: rgba(255, 255, 255, 0.05);
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 0.8rem;
            color: var(--cyan);
            margin-bottom: 20px;
            border: 1px solid rgba(0, 255, 255, 0.2);
        }

        .reward-area {
            background: rgba(0, 0, 0, 0.4);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            border: 1px solid rgba(255, 215, 0, 0.1);
        }

        .reward-label { font-size: 0.7rem; text-transform: uppercase; color: rgba(255, 255, 255, 0.5); }
        .reward-value { font-family: 'Orbitron', sans-serif; font-size: 2rem; color: var(--gold); font-weight: 800; }

        .cost-label { color: rgba(255, 255, 255, 0.7); font-size: 0.9rem; margin-bottom: 15px; font-weight: 600; }
        .cost-value { color: var(--purple); font-weight: 800; }

        .purchase-btn {
            width: 100%;
            padding: 15px;
            border-radius: 10px;
            border: none;
            font-family: 'Orbitron', sans-serif;
            font-weight: 700;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            transition: 0.3s;
            background: linear-gradient(90deg, #1e293b, #0f172a);
            color: rgba(255, 255, 255, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .purchase-btn.active {
            background: linear-gradient(90deg, var(--cyan), var(--purple));
            color: #fff;
            border: none;
            box-shadow: 0 5px 15px rgba(0, 255, 255, 0.2);
        }

        .purchase-btn.active:hover {
            transform: scale(1.02);
            box-shadow: var(--neon-glow);
        }

        /* --- ALERTS --- */
        .alert {
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 30px;
            text-align: center;
            font-weight: 700;
            animation: slideDown 0.5s ease;
        }
        .alert-success { background: rgba(16, 185, 129, 0.2); border: 1px solid #10b981; color: #10b981; }
        .alert-error { background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #ef4444; }

        @keyframes slideDown {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* --- MOBILE OPTIMIZATION --- */
        @media (max-width: 650px) {
            .nav-bar { justify-content: center; }
            .user-balance { grid-template-columns: 1fr; gap: 10px; }
            .shop-header h1 { font-size: 2.2rem; }
            .shop-card { padding: 20px; }
            .reward-value { font-size: 1.6rem; }
        }
    </style>
</head>
<body class="no-select">
    <div class="nav-bar">
        <a href="index.php" class="back-btn"><i class="fas fa-arrow-left"></i> Games Hub</a>
    </div>
    
    <div class="shop-container">
        <div class="shop-header">
            <h1>Aether Shop</h1>
            <p>Convert Game Energy into Stellar Credits</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <div class="user-balance">
            <div class="balance-card fluxon-card">
                <div class="balance-label">Total Fluxon Energy</div>
                <div class="balance-value"><?php echo number_format($total_fluxon); ?></div>
            </div>
            <div class="balance-card astron-card">
                <div class="balance-label">Available Astrons</div>
                <div class="balance-value"><?php echo number_format($user_astrons); ?></div>
            </div>
        </div>
        
        <div class="shop-grid">
            <?php 
            $icons = ['<i class="fas fa-bolt" style="color:#00ffff"></i>', 
                      '<i class="fas fa-satellite-dish" style="color:#9d4edd"></i>', 
                      '<i class="fas fa-meteor" style="color:#FFD700"></i>'];
            
            foreach ($shop_prices as $index => $price): 
                $fluxon = intval($price['fluxon_amount']);
                $astrons = intval($price['astrons_reward']);
                $can_afford = ($total_fluxon >= $fluxon);
            ?>
            <div class="shop-card" style="<?php echo $index === 2 ? 'border-color: var(--gold); background: rgba(255,215,0,0.03);' : ''; ?>">
                <div>
                    <div class="item-visual"><?php echo $icons[$index] ?? $icons[0]; ?></div>
                    <div class="item-name"><?php echo htmlspecialchars($price['claim_type']); ?></div>
                    <div class="conversion-pill">Rate: 1 Astron / <?php echo number_format($fluxon / $astrons); ?> Fluxon</div>
                    
                    <div class="reward-area">
                        <div class="reward-label">Payload Amount</div>
                        <div class="reward-value"><?php echo $astrons; ?> <small style="font-size:0.8rem">Astrons</small></div>
                    </div>
                </div>

                <div>
                    <div class="cost-label">Required Energy: <span class="cost-value"><?php echo number_format($fluxon); ?> Fluxon</span></div>
                    
                    <form method="POST" onsubmit="return confirm('Initiate sync for <?php echo $astrons; ?> Astrons?');">
                        <input type="hidden" name="item_id" value="<?php echo $price['id']; ?>">
                        <input type="hidden" name="item_cost" value="<?php echo $fluxon; ?>">
                        <input type="hidden" name="item_astrons" value="<?php echo $astrons; ?>">
                        <button type="submit" name="purchase_item" 
                                class="purchase-btn <?php echo $can_afford ? 'active' : ''; ?>" 
                                <?php echo !$can_afford ? 'disabled' : ''; ?>>
                            <?php echo $can_afford ? 'Claim Reward' : 'Insufficient Energy'; ?>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>