<?php
require_once 'admin_check.php';
require_once 'connection.php';

$message = '';
$error = '';

$table_exists = false;
$check_table = $conn->query("SHOW TABLES LIKE 'credit_packages'");
if ($check_table && $check_table->num_rows > 0) $table_exists = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $table_exists) {
    $action = $_POST['action'] ?? '';
    $package_id = intval($_POST['package_id'] ?? 0);
    $credit_amount = intval($_POST['credit_amount'] ?? 0);
    $price = floatval($_POST['price'] ?? 0);
    $is_popular = isset($_POST['is_popular']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $display_order = intval($_POST['display_order'] ?? 0);
    
    if ($action === 'add' || $action === 'edit') {
        if ($credit_amount > 0 && $price > 0) {
            if ($action === 'add') {
                $stmt = $conn->prepare("INSERT INTO credit_packages (credit_amount, price, is_popular, is_active, display_order) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("idiii", $credit_amount, $price, $is_popular, $is_active, $display_order);
                if ($stmt->execute()) $message = "Package added.";
            } else {
                $stmt = $conn->prepare("UPDATE credit_packages SET credit_amount=?, price=?, is_popular=?, is_active=?, display_order=? WHERE id=?");
                $stmt->bind_param("idiiii", $credit_amount, $price, $is_popular, $is_active, $display_order, $package_id);
                if ($stmt->execute()) $message = "Package updated.";
            }
        }
    } else if ($action === 'delete') {
        $conn->query("DELETE FROM credit_packages WHERE id = $package_id");
        $message = "Package removed.";
    }
}

$packages = $table_exists ? $conn->query("SELECT * FROM credit_packages ORDER BY display_order ASC, credit_amount ASC")->fetch_all(MYSQLI_ASSOC) : [];
$edit_p = null;
if (isset($_GET['edit'])) {
    $edit_stmt = $conn->prepare("SELECT * FROM credit_packages WHERE id = ?");
    $edit_stmt->bind_param("i", $_GET['edit']);
    $edit_stmt->execute();
    $edit_p = $edit_stmt->get_result()->fetch_assoc();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pricing Plans - Astraden Admin</title>
    <link rel="icon" type="image/svg+xml" href="logo.svg">
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
            --color-verify: #4ade80;
            --color-credits: #ffd700;
            --color-pricing: #f97316;
            --color-timing: #a855f7;
            --color-limits: #ef4444;
            --color-sessions: #3b82f6;
            --color-contest: #fbbf24;
            --color-costs: #ec4899;
            --color-prizes: #8b5cf6;
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
        .ic-verify { color: var(--color-verify); text-shadow: 0 0 10px var(--color-verify); }
        .ic-credits { color: var(--color-credits); text-shadow: 0 0 10px var(--color-credits); }
        .ic-pricing { color: var(--color-pricing); text-shadow: 0 0 10px var(--color-pricing); }
        .ic-timing { color: var(--color-timing); text-shadow: 0 0 10px var(--color-timing); }
        .ic-limits { color: var(--color-limits); text-shadow: 0 0 10px var(--color-limits); }
        .ic-sessions { color: var(--color-sessions); text-shadow: 0 0 10px var(--color-sessions); }
        .ic-contest { color: var(--color-contest); text-shadow: 0 0 10px var(--color-contest); }
        .ic-costs { color: var(--color-costs); text-shadow: 0 0 10px var(--color-costs); }
        .ic-prizes { color: var(--color-prizes); text-shadow: 0 0 10px var(--color-prizes); }
        .ic-leaderboard { color: var(--color-leaderboard); text-shadow: 0 0 10px var(--color-leaderboard); }

        .menu-item:hover, .menu-item.active { background: rgba(255, 255, 255, 0.05); color: white; border-left-color: var(--primary-cyan); }
        .sidebar-footer { padding: 20px; border-top: 1px solid rgba(0, 255, 255, 0.1); }
        .logout-btn { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 12px; background: rgba(255, 0, 110, 0.1); border: 1px solid #ff006e; color: #ff006e; text-decoration: none; border-radius: 8px; font-family: 'Orbitron', sans-serif; font-size: 0.8rem; font-weight: 700; }

        .main-content { margin-left: var(--sidebar-width); flex: 1; padding: 40px; }
        .section-title { font-family: 'Orbitron', sans-serif; color: var(--primary-cyan); margin-bottom: 30px; letter-spacing: 3px; }

        .config-card { background: var(--card-bg); border: 1px solid rgba(0, 255, 255, 0.2); border-radius: 20px; padding: 35px; margin-bottom: 30px; }
        .form-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 20px; }
        .form-group label { display: block; color: var(--primary-purple); font-weight: 700; font-size: 0.8rem; margin-bottom: 8px; }
        .form-group input { width: 100%; padding: 12px; background: rgba(0,0,0,0.5); border: 1px solid rgba(0,255,255,0.3); border-radius: 8px; color: white; outline: none; font-family: 'Rajdhani'; font-weight:bold; }

        .btn-deploy { background: linear-gradient(135deg, var(--primary-cyan), var(--primary-purple)); border: none; color: white; padding: 15px; border-radius: 10px; font-family: 'Orbitron', sans-serif; font-weight: 900; width: 100%; cursor: pointer; }

        .table-card { background: var(--card-bg); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 15px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
        th { color: var(--primary-purple); font-family: 'Orbitron', sans-serif; font-size: 0.7rem; text-transform: uppercase; background: rgba(0,0,0,0.2); }
        
        .pop-badge { background: #ff006e; color: white; padding: 2px 6px; border-radius: 3px; font-size: 0.6rem; font-weight: 900; margin-left: 10px; vertical-align: middle; }

        .msg { padding: 15px; border-radius: 10px; margin-bottom: 25px; background: rgba(0, 255, 204, 0.1); border: 1px solid #00ffcc; color: #00ffcc; font-weight: bold; }
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
            <a href="admin_transaction_codes.php" class="menu-item"><i class="fas fa-qrcode ic-verify"></i> <span>Verify Payments</span></a>
            <a href="admin_user_credits.php" class="menu-item"><i class="fas fa-coins ic-credits"></i> <span>Manual Credits</span></a>
            <a href="admin_credit_pricing.php" class="menu-item active"><i class="fas fa-tags ic-pricing"></i> <span>Pricing Plans</span></a>
            <a href="admin_credit_timing.php" class="menu-item"><i class="fas fa-clock ic-timing"></i> <span>Purchase Timing</span></a>
            <a href="admin_credit_sale_limit.php" class="menu-item"><i class="fas fa-gauge-high ic-limits"></i> <span>Sale Limits</span></a>
            <div class="menu-category">Game Operations</div>
            <a href="admin_game_timing.php" class="menu-item"><i class="fas fa-calendar-check ic-sessions"></i> <span>Game Sessions</span></a>
            <a href="admin_contest_management.php" class="menu-item"><i class="fas fa-trophy ic-contest"></i> <span>Contest Control</span></a>
            <a href="admin_game_credits.php" class="menu-item"><i class="fas fa-gamepad ic-costs"></i> <span>Play Costs</span></a>
            <a href="admin_game_prizes.php" class="menu-item"><i class="fas fa-award ic-prizes"></i> <span>Prize Setup</span></a>
            <a href="admin_game_leaderboard.php" class="menu-item"><i class="fas fa-ranking-star ic-leaderboard"></i> <span>Leaderboards</span></a>
        </div>
        <div class="sidebar-footer"><a href="admin_logout.php" class="logout-btn"><i class="fas fa-power-off"></i> <span>Authorization Exit</span></a></div>
    </nav>

    <main class="main-content">
        <h2 class="section-title"><i class="fas fa-tags ic-pricing" style="margin-right:15px;"></i> PRICING PLANS</h2>

        <?php if($message): ?><div class="msg"><?php echo $message; ?></div><?php endif; ?>

        <div class="config-card">
            <form method="POST">
                <input type="hidden" name="action" value="<?php echo $edit_p ? 'edit' : 'add'; ?>">
                <?php if($edit_p): ?><input type="hidden" name="package_id" value="<?php echo $edit_p['id']; ?>"><?php endif; ?>
                <div class="form-grid">
                    <div class="form-group"><label>CREDIT OUTPUT</label><input type="number" name="credit_amount" value="<?php echo $edit_p['credit_amount'] ?? ''; ?>" required></div>
                    <div class="form-group"><label>PRICE (INR)</label><input type="number" name="price" value="<?php echo $edit_p['price'] ?? ''; ?>" required></div>
                    <div class="form-group"><label>SORT ORDER</label><input type="number" name="display_order" value="<?php echo $edit_p['display_order'] ?? '0'; ?>"></div>
                </div>
                <div style="margin-bottom:20px;display:flex;gap:30px;">
                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer;"><input type="checkbox" name="is_popular" <?php echo ($edit_p['is_popular']??0)?'checked':''; ?>> <span>POPULAR TAG</span></label>
                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer;"><input type="checkbox" name="is_active" <?php echo ($edit_p['is_active']??1)?'checked':''; ?>> <span>ACTIVE STATUS</span></label>
                </div>
                <button type="submit" class="btn-deploy"><?php echo $edit_p ? 'UPDATE PACKAGE' : 'CREATE NEW PACKAGE'; ?></button>
            </form>
        </div>

        <div class="table-card">
            <table>
                <thead><tr><th>Credit Amount</th><th>Market Price</th><th>Order</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach($packages as $p): ?>
                    <tr>
                        <td><strong style="color:var(--primary-cyan);"><?php echo number_format($p['credit_amount']); ?></strong> <?php if($p['is_popular']): ?><span class="pop-badge">POPULAR</span><?php endif; ?></td>
                        <td style="color:var(--color-credits);font-family:'Orbitron';">₹<?php echo number_format($p['price']); ?></td>
                        <td><?php echo $p['display_order']; ?></td>
                        <td>
                            <a href="?edit=<?php echo $p['id']; ?>" style="color:var(--primary-cyan);text-decoration:none;font-weight:bold;margin-right:15px;">EDIT</a>
                            <a href="#" onclick="if(confirm('Delete?')) { document.getElementById('del_id').value='<?php echo $p['id']; ?>'; document.getElementById('del_form').submit(); }" style="color:#ff006e;text-decoration:none;font-weight:bold;">REMOVE</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
    <form id="del_form" method="POST" style="display:none;"><input type="hidden" name="action" value="delete"><input type="hidden" name="package_id" id="del_id"></form>
</body>
</html>
