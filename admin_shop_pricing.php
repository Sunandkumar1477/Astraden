<?php
session_start();
require_once 'admin_check.php';
require_once 'connection.php';

$message = '';
$error = '';

// Create shop_pricing table if it doesn't exist
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_prices'])) {
        $price1_fluxon = intval($_POST['price1_fluxon'] ?? 5000);
        $price1_astrons = intval($_POST['price1_astrons'] ?? 10);
        $price2_fluxon = intval($_POST['price2_fluxon'] ?? 7500);
        $price2_astrons = intval($_POST['price2_astrons'] ?? 20);
        $price3_fluxon = intval($_POST['price3_fluxon'] ?? 10000);
        $price3_astrons = intval($_POST['price3_astrons'] ?? 30);
        
        // Update or insert prices
        $stmt1 = $conn->prepare("INSERT INTO shop_pricing (id, fluxon_amount, astrons_reward, claim_type, display_order) VALUES (1, ?, ?, 'Basic Claim', 1) ON DUPLICATE KEY UPDATE fluxon_amount = VALUES(fluxon_amount), astrons_reward = VALUES(astrons_reward)");
        $stmt1->bind_param("ii", $price1_fluxon, $price1_astrons);
        $stmt1->execute();
        $stmt1->close();
        
        $stmt2 = $conn->prepare("INSERT INTO shop_pricing (id, fluxon_amount, astrons_reward, claim_type, display_order) VALUES (2, ?, ?, 'Standard Claim', 2) ON DUPLICATE KEY UPDATE fluxon_amount = VALUES(fluxon_amount), astrons_reward = VALUES(astrons_reward)");
        $stmt2->bind_param("ii", $price2_fluxon, $price2_astrons);
        $stmt2->execute();
        $stmt2->close();
        
        $stmt3 = $conn->prepare("INSERT INTO shop_pricing (id, fluxon_amount, astrons_reward, claim_type, display_order) VALUES (3, ?, ?, 'Premium Claim', 3) ON DUPLICATE KEY UPDATE fluxon_amount = VALUES(fluxon_amount), astrons_reward = VALUES(astrons_reward)");
        $stmt3->bind_param("ii", $price3_fluxon, $price3_astrons);
        $stmt3->execute();
        $stmt3->close();
        
        $message = "Shop prices updated successfully!";
    }
}

// Get current prices
$prices = $conn->query("SELECT * FROM shop_pricing ORDER BY display_order ASC")->fetch_all(MYSQLI_ASSOC);

// Set defaults if no prices exist
$price1 = ['fluxon_amount' => 5000, 'astrons_reward' => 10];
$price2 = ['fluxon_amount' => 7500, 'astrons_reward' => 20];
$price3 = ['fluxon_amount' => 10000, 'astrons_reward' => 30];

if (count($prices) >= 1) $price1 = $prices[0];
if (count($prices) >= 2) $price2 = $prices[1];
if (count($prices) >= 3) $price3 = $prices[2];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop Pricing - Astraden Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0a0a0f;
            color: #fff;
            padding: 20px;
        }
        .space-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(ellipse at center, #1a1a2e 0%, #0a0a0f 100%);
            z-index: -1;
        }
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            height: 100vh;
            background: rgba(15, 15, 25, 0.95);
            border-right: 2px solid rgba(0, 255, 255, 0.3);
            padding: 20px;
            overflow-y: auto;
        }
        .sidebar-header h1 {
            color: var(--primary-cyan, #00ffff);
            font-size: 1.5rem;
            margin-bottom: 30px;
            text-align: center;
        }
        .menu-category {
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.75rem;
            text-transform: uppercase;
            margin: 20px 0 10px;
            letter-spacing: 1px;
        }
        .menu-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 5px;
            transition: all 0.3s;
        }
        .menu-item:hover, .menu-item.active {
            background: rgba(0, 255, 255, 0.1);
            color: var(--primary-cyan, #00ffff);
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .section-title {
            font-size: 1.8rem;
            color: var(--primary-cyan, #00ffff);
            margin-bottom: 20px;
            font-family: 'Orbitron', sans-serif;
        }
        .config-card {
            background: rgba(15, 15, 25, 0.8);
            border: 2px solid rgba(0, 255, 255, 0.3);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            color: var(--primary-cyan, #00ffff);
            font-weight: bold;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            background: rgba(0, 0, 0, 0.5);
            border: 2px solid rgba(0, 255, 255, 0.3);
            border-radius: 8px;
            color: #fff;
            font-size: 1rem;
        }
        .form-group input:focus {
            outline: none;
            border-color: var(--primary-cyan, #00ffff);
            box-shadow: 0 0 10px rgba(0, 255, 255, 0.3);
        }
        .price-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
            padding: 20px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 10px;
            border: 1px solid rgba(0, 255, 255, 0.2);
        }
        .btn-update {
            padding: 15px 30px;
            background: linear-gradient(135deg, rgba(0, 255, 255, 0.2), rgba(157, 78, 221, 0.2));
            border: 2px solid var(--primary-cyan, #00ffff);
            border-radius: 8px;
            color: var(--primary-cyan, #00ffff);
            font-weight: bold;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s;
        }
        .btn-update:hover {
            background: linear-gradient(135deg, rgba(0, 255, 255, 0.4), rgba(157, 78, 221, 0.4));
            box-shadow: 0 0 20px rgba(0, 255, 255, 0.5);
        }
        .msg {
            background: rgba(0, 255, 0, 0.2);
            border: 2px solid #00ff00;
            color: #00ff00;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .error-msg {
            background: rgba(255, 0, 0, 0.2);
            border: 2px solid #ff0000;
            color: #ff0000;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        :root {
            --primary-cyan: #00ffff;
            --primary-purple: #9d4edd;
        }
    </style>
</head>
<body>
    <div class="space-bg"></div>

    <nav class="sidebar">
        <div class="sidebar-header"><h1>Astraden</h1></div>
        <div class="sidebar-menu">
            <div class="menu-category">General</div>
            <a href="admin_dashboard.php" class="menu-item"><i class="fas fa-chart-line"></i> <span>Overview</span></a>
            <div class="menu-category">User Control</div>
            <a href="admin_view_all_users.php" class="menu-item"><i class="fas fa-users"></i> <span>User Directory</span></a>
            <a href="admin_password_reset_requests.php" class="menu-item"><i class="fas fa-key"></i> <span>Reset Requests</span></a>
            <div class="menu-category">Financials</div>
            <a href="admin_shop_pricing.php" class="menu-item active"><i class="fas fa-store"></i> <span>Shop Pricing</span></a>
            <div class="menu-category">Game Operations</div>
            <a href="admin_game_credits.php" class="menu-item"><i class="fas fa-gamepad"></i> <span>Play Costs</span></a>
            <a href="admin_game_leaderboard.php" class="menu-item"><i class="fas fa-ranking-star"></i> <span>Leaderboards</span></a>
        </div>
        <div class="sidebar-footer"><a href="admin_logout.php" class="logout-btn"><i class="fas fa-power-off"></i> <span>Exit</span></a></div>
    </nav>

    <main class="main-content">
        <h2 class="section-title"><i class="fas fa-store" style="margin-right:15px;"></i> SHOP PRICING</h2>

        <?php if($message): ?><div class="msg"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if($error): ?><div class="error-msg"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <div class="config-card">
            <form method="POST">
                <h3 style="color: var(--primary-cyan); margin-bottom: 20px;">Set Fluxon to Astrons Conversion Rates</h3>
                
                <div class="price-row">
                    <div class="form-group">
                        <label>Claim Type 1 - Fluxon Amount</label>
                        <input type="number" name="price1_fluxon" value="<?php echo $price1['fluxon_amount']; ?>" required min="1">
                    </div>
                    <div class="form-group">
                        <label>Claim Type 1 - Astrons Reward</label>
                        <input type="number" name="price1_astrons" value="<?php echo $price1['astrons_reward']; ?>" required min="1">
                    </div>
                </div>
                
                <div class="price-row">
                    <div class="form-group">
                        <label>Claim Type 2 - Fluxon Amount</label>
                        <input type="number" name="price2_fluxon" value="<?php echo $price2['fluxon_amount']; ?>" required min="1">
                    </div>
                    <div class="form-group">
                        <label>Claim Type 2 - Astrons Reward</label>
                        <input type="number" name="price2_astrons" value="<?php echo $price2['astrons_reward']; ?>" required min="1">
                    </div>
                </div>
                
                <div class="price-row">
                    <div class="form-group">
                        <label>Claim Type 3 - Fluxon Amount</label>
                        <input type="number" name="price3_fluxon" value="<?php echo $price3['fluxon_amount']; ?>" required min="1">
                    </div>
                    <div class="form-group">
                        <label>Claim Type 3 - Astrons Reward</label>
                        <input type="number" name="price3_astrons" value="<?php echo $price3['astrons_reward']; ?>" required min="1">
                    </div>
                </div>
                
                <button type="submit" name="update_prices" class="btn-update">UPDATE PRICES</button>
            </form>
        </div>
    </main>
</body>
</html>

