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
        astrons_cost INT(11) NOT NULL DEFAULT 0 COMMENT 'Amount of Astrons required for reverse trade',
        fluxon_reward INT(11) NOT NULL DEFAULT 0 COMMENT 'Fluxon user gets from reverse trade',
        claim_type VARCHAR(50) NOT NULL COMMENT 'Type name',
        is_active TINYINT(1) DEFAULT 1,
        display_order INT(11) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_fluxon (fluxon_amount)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Add reverse trading columns if they don't exist
$check_reverse = $conn->query("SHOW COLUMNS FROM shop_pricing LIKE 'astrons_cost'");
if ($check_reverse && $check_reverse->num_rows == 0) {
    $conn->query("ALTER TABLE shop_pricing 
                  ADD COLUMN astrons_cost INT(11) NOT NULL DEFAULT 0 COMMENT 'Amount of Astrons required for reverse trade' AFTER astrons_reward,
                  ADD COLUMN fluxon_reward INT(11) NOT NULL DEFAULT 0 COMMENT 'Fluxon user gets from reverse trade' AFTER astrons_cost");
}
if ($check_reverse) $check_reverse->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_prices'])) {
        // Forward trading (Fluxon to Astrons)
        $price1_fluxon = intval($_POST['price1_fluxon'] ?? 5000);
        $price1_astrons = intval($_POST['price1_astrons'] ?? 10);
        $price2_fluxon = intval($_POST['price2_fluxon'] ?? 7500);
        $price2_astrons = intval($_POST['price2_astrons'] ?? 20);
        $price3_fluxon = intval($_POST['price3_fluxon'] ?? 10000);
        $price3_astrons = intval($_POST['price3_astrons'] ?? 30);
        
        // Reverse trading (Astrons to Fluxon)
        $price1_astrons_cost = intval($_POST['price1_astrons_cost'] ?? 10);
        $price1_fluxon_reward = intval($_POST['price1_fluxon_reward'] ?? 5000);
        $price2_astrons_cost = intval($_POST['price2_astrons_cost'] ?? 20);
        $price2_fluxon_reward = intval($_POST['price2_fluxon_reward'] ?? 7500);
        $price3_astrons_cost = intval($_POST['price3_astrons_cost'] ?? 30);
        $price3_fluxon_reward = intval($_POST['price3_fluxon_reward'] ?? 10000);
        
        // Update or insert prices (forward trading)
        $stmt1 = $conn->prepare("INSERT INTO shop_pricing (id, fluxon_amount, astrons_reward, astrons_cost, fluxon_reward, claim_type, display_order) VALUES (1, ?, ?, ?, ?, 'Basic Claim', 1) ON DUPLICATE KEY UPDATE fluxon_amount = VALUES(fluxon_amount), astrons_reward = VALUES(astrons_reward), astrons_cost = VALUES(astrons_cost), fluxon_reward = VALUES(fluxon_reward)");
        $stmt1->bind_param("iiii", $price1_fluxon, $price1_astrons, $price1_astrons_cost, $price1_fluxon_reward);
        $stmt1->execute();
        $stmt1->close();
        
        $stmt2 = $conn->prepare("INSERT INTO shop_pricing (id, fluxon_amount, astrons_reward, astrons_cost, fluxon_reward, claim_type, display_order) VALUES (2, ?, ?, ?, ?, 'Standard Claim', 2) ON DUPLICATE KEY UPDATE fluxon_amount = VALUES(fluxon_amount), astrons_reward = VALUES(astrons_reward), astrons_cost = VALUES(astrons_cost), fluxon_reward = VALUES(fluxon_reward)");
        $stmt2->bind_param("iiii", $price2_fluxon, $price2_astrons, $price2_astrons_cost, $price2_fluxon_reward);
        $stmt2->execute();
        $stmt2->close();
        
        $stmt3 = $conn->prepare("INSERT INTO shop_pricing (id, fluxon_amount, astrons_reward, astrons_cost, fluxon_reward, claim_type, display_order) VALUES (3, ?, ?, ?, ?, 'Premium Claim', 3) ON DUPLICATE KEY UPDATE fluxon_amount = VALUES(fluxon_amount), astrons_reward = VALUES(astrons_reward), astrons_cost = VALUES(astrons_cost), fluxon_reward = VALUES(fluxon_reward)");
        $stmt3->bind_param("iiii", $price3_fluxon, $price3_astrons, $price3_astrons_cost, $price3_fluxon_reward);
        $stmt3->execute();
        $stmt3->close();
        
        $message = "Shop prices updated successfully!";
    }
}

// Get current prices
$prices = $conn->query("SELECT * FROM shop_pricing ORDER BY display_order ASC")->fetch_all(MYSQLI_ASSOC);

// Set defaults if no prices exist
$price1 = ['fluxon_amount' => 5000, 'astrons_reward' => 10, 'astrons_cost' => 10, 'fluxon_reward' => 5000];
$price2 = ['fluxon_amount' => 7500, 'astrons_reward' => 20, 'astrons_cost' => 20, 'fluxon_reward' => 7500];
$price3 = ['fluxon_amount' => 10000, 'astrons_reward' => 30, 'astrons_cost' => 30, 'fluxon_reward' => 10000];

if (count($prices) >= 1) $price1 = array_merge($price1, $prices[0]);
if (count($prices) >= 2) $price2 = array_merge($price2, $prices[1]);
if (count($prices) >= 3) $price3 = array_merge($price3, $prices[2]);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop Pricing - Astraden Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-cyan: #00ffff;
            --primary-purple: #9d4edd;
            --sidebar-width: 280px;
            --dark-bg: #05050a;
            --card-bg: rgba(15, 15, 25, 0.95);
            
            /* Icon Colors */
            --color-overview: #00ffff;
            --color-users: #4cc9f0;
            --color-reset: #f72585;
            --color-shop: #f97316;
            --color-costs: #ec4899;
            --color-leaderboard: #10b981;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Rajdhani', sans-serif; background: var(--dark-bg); color: white; min-height: 100vh; display: flex; }
        .space-bg { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: radial-gradient(circle at 10% 20%, #1a1a2e 0%, #05050a 100%); z-index: -1; }

        .sidebar { width: var(--sidebar-width); background: var(--card-bg); border-right: 1px solid rgba(0, 255, 255, 0.2); height: 100vh; position: fixed; left: 0; top: 0; display: flex; flex-direction: column; z-index: 1001; }
        .sidebar-header { padding: 30px 20px; text-align: center; border-bottom: 1px solid rgba(0, 255, 255, 0.1); }
        .sidebar-header h1 { font-family: 'Orbitron', sans-serif; font-size: 1.4rem; color: var(--primary-cyan); text-transform: uppercase; }
        .sidebar-menu { flex: 1; overflow-y: auto; padding: 20px 0; }
        .menu-category { padding: 15px 25px 10px; font-family: 'Orbitron', sans-serif; font-size: 0.7rem; color: rgba(255,255,255,0.4); text-transform: uppercase; letter-spacing: 2px; font-weight: 900; }
        .menu-item { padding: 12px 25px; display: flex; align-items: center; gap: 15px; text-decoration: none; color: rgba(255, 255, 255, 0.7); font-weight: 500; transition: 0.3s; border-left: 3px solid transparent; }
        .menu-item i { width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; border-radius: 6px; background: rgba(255,255,255,0.05); }
        
        .ic-overview { color: var(--color-overview); text-shadow: 0 0 10px var(--color-overview); }
        .ic-users { color: var(--color-users); text-shadow: 0 0 10px var(--color-users); }
        .ic-reset { color: var(--color-reset); text-shadow: 0 0 10px var(--color-reset); }
        .ic-shop { color: var(--color-shop); text-shadow: 0 0 10px var(--color-shop); }
        .ic-costs { color: var(--color-costs); text-shadow: 0 0 10px var(--color-costs); }
        .ic-leaderboard { color: var(--color-leaderboard); text-shadow: 0 0 10px var(--color-leaderboard); }

        .menu-item:hover, .menu-item.active { background: rgba(255, 255, 255, 0.05); color: white; border-left-color: var(--primary-cyan); }
        .sidebar-footer { padding: 20px; border-top: 1px solid rgba(0, 255, 255, 0.1); }
        .logout-btn { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 12px; background: rgba(255, 0, 110, 0.1); border: 1px solid #ff006e; color: #ff006e; text-decoration: none; border-radius: 8px; font-family: 'Orbitron', sans-serif; font-size: 0.8rem; font-weight: 700; }

        .main-content { margin-left: var(--sidebar-width); flex: 1; padding: 40px; }
        .section-title { font-family: 'Orbitron', sans-serif; color: var(--primary-cyan); margin-bottom: 30px; letter-spacing: 3px; }

        .config-card { background: var(--card-bg); border: 1px solid rgba(0, 255, 255, 0.2); border-radius: 20px; padding: 35px; margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; color: var(--primary-purple); font-weight: 700; font-size: 0.8rem; margin-bottom: 8px; }
        .form-group input, .form-group select { width: 100%; padding: 12px; background: rgba(0,0,0,0.5); border: 1px solid rgba(0,255,255,0.3); border-radius: 8px; color: white; outline: none; }

        .btn-update { background: linear-gradient(135deg, var(--primary-cyan), var(--primary-purple)); border: none; color: white; padding: 15px; border-radius: 10px; font-family: 'Orbitron', sans-serif; font-weight: 900; width: 100%; cursor: pointer; transition: all 0.3s; }
        .btn-update:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(0, 255, 255, 0.4); }

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

        .msg { padding: 15px; border-radius: 10px; margin-bottom: 25px; background: rgba(0, 255, 204, 0.1); border: 1px solid #00ffcc; color: #00ffcc; font-weight: bold; }
        .error-msg { padding: 15px; border-radius: 10px; margin-bottom: 25px; background: rgba(255, 0, 0, 0.1); border: 1px solid #ff0000; color: #ff0000; font-weight: bold; }
    </style>
</head>
<body>
    <div class="space-bg"></div>

    <nav class="sidebar">
        <div class="sidebar-header"><h1>Astraden</h1></div>
        <div class="sidebar-menu">
            <div class="menu-category">General</div>
            <a href="admin_dashboard.php" class="menu-item"><i class="fas fa-chart-line ic-overview"></i> <span>Overview</span></a>
            <div class="menu-category">User Control</div>
            <a href="admin_view_all_users.php" class="menu-item"><i class="fas fa-users ic-users"></i> <span>User Directory</span></a>
            <a href="admin_password_reset_requests.php" class="menu-item"><i class="fas fa-key ic-reset"></i> <span>Reset Requests</span></a>
            <div class="menu-category">Financials</div>
            <a href="admin_shop_pricing.php" class="menu-item active"><i class="fas fa-store ic-shop"></i> <span>Shop Pricing</span></a>
            <div class="menu-category">Game Operations</div>
            <a href="admin_game_credits.php" class="menu-item"><i class="fas fa-gamepad ic-costs"></i> <span>Play Costs</span></a>
            <a href="admin_game_leaderboard.php" class="menu-item"><i class="fas fa-ranking-star ic-leaderboard"></i> <span>Leaderboards</span></a>
        </div>
        <div class="sidebar-footer"><a href="admin_logout.php" class="logout-btn"><i class="fas fa-power-off"></i> <span>Authorization Exit</span></a></div>
    </nav>

    <main class="main-content">
        <h2 class="section-title"><i class="fas fa-store ic-shop" style="margin-right:15px;"></i> SHOP PRICING</h2>

        <?php if($message): ?><div class="msg"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if($error): ?><div class="error-msg"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <div class="config-card">
            <form method="POST">
                <h3 style="color: var(--primary-cyan); margin-bottom: 20px;">Set Fluxon ↔ Astrons Trading Rates</h3>
                
                <h4 style="color: var(--primary-purple); margin: 30px 0 15px; border-bottom: 2px solid rgba(157, 78, 221, 0.3); padding-bottom: 10px;">Forward Trading: Fluxon → Astrons</h4>
                
                <div class="price-row">
                    <div class="form-group">
                        <label>Type 1 - Fluxon Cost</label>
                        <input type="number" name="price1_fluxon" value="<?php echo $price1['fluxon_amount']; ?>" required min="1">
                    </div>
                    <div class="form-group">
                        <label>Type 1 - Astrons Reward</label>
                        <input type="number" name="price1_astrons" value="<?php echo $price1['astrons_reward']; ?>" required min="1">
                    </div>
                </div>
                
                <div class="price-row">
                    <div class="form-group">
                        <label>Type 2 - Fluxon Cost</label>
                        <input type="number" name="price2_fluxon" value="<?php echo $price2['fluxon_amount']; ?>" required min="1">
                    </div>
                    <div class="form-group">
                        <label>Type 2 - Astrons Reward</label>
                        <input type="number" name="price2_astrons" value="<?php echo $price2['astrons_reward']; ?>" required min="1">
                    </div>
                </div>
                
                <div class="price-row">
                    <div class="form-group">
                        <label>Type 3 - Fluxon Cost</label>
                        <input type="number" name="price3_fluxon" value="<?php echo $price3['fluxon_amount']; ?>" required min="1">
                    </div>
                    <div class="form-group">
                        <label>Type 3 - Astrons Reward</label>
                        <input type="number" name="price3_astrons" value="<?php echo $price3['astrons_reward']; ?>" required min="1">
                    </div>
                </div>
                
                <h4 style="color: var(--primary-purple); margin: 30px 0 15px; border-bottom: 2px solid rgba(157, 78, 221, 0.3); padding-bottom: 10px;">Reverse Trading: Astrons → Fluxon</h4>
                
                <div class="price-row">
                    <div class="form-group">
                        <label>Type 1 - Astrons Cost</label>
                        <input type="number" name="price1_astrons_cost" value="<?php echo $price1['astrons_cost'] ?? 10; ?>" required min="1">
                    </div>
                    <div class="form-group">
                        <label>Type 1 - Fluxon Reward</label>
                        <input type="number" name="price1_fluxon_reward" value="<?php echo $price1['fluxon_reward'] ?? 5000; ?>" required min="1">
                    </div>
                </div>
                
                <div class="price-row">
                    <div class="form-group">
                        <label>Type 2 - Astrons Cost</label>
                        <input type="number" name="price2_astrons_cost" value="<?php echo $price2['astrons_cost'] ?? 20; ?>" required min="1">
                    </div>
                    <div class="form-group">
                        <label>Type 2 - Fluxon Reward</label>
                        <input type="number" name="price2_fluxon_reward" value="<?php echo $price2['fluxon_reward'] ?? 7500; ?>" required min="1">
                    </div>
                </div>
                
                <div class="price-row">
                    <div class="form-group">
                        <label>Type 3 - Astrons Cost</label>
                        <input type="number" name="price3_astrons_cost" value="<?php echo $price3['astrons_cost'] ?? 30; ?>" required min="1">
                    </div>
                    <div class="form-group">
                        <label>Type 3 - Fluxon Reward</label>
                        <input type="number" name="price3_fluxon_reward" value="<?php echo $price3['fluxon_reward'] ?? 10000; ?>" required min="1">
                    </div>
                </div>
                
                <button type="submit" name="update_prices" class="btn-update">UPDATE PRICES</button>
            </form>
        </div>
    </main>
</body>
</html>

