<?php
session_start();
require_once 'admin_check.php';
require_once 'connection.php';

$message = '';
$error = '';

// Ensure contest_credits_required column exists
try {
    $check_col = $conn->query("SHOW COLUMNS FROM games LIKE 'contest_credits_required'");
    if ($check_col->num_rows == 0) {
        $conn->query("ALTER TABLE games ADD COLUMN contest_credits_required INT(11) DEFAULT 30");
    }
} catch (Exception $e) {
    // Column might already exist
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_credits'])) {
    $game_name = trim($_POST['game_name'] ?? '');
    $credits_per_chance = intval($_POST['credits_per_chance'] ?? 30);
    $contest_credits_required = intval($_POST['contest_credits_required'] ?? 30);
    
    if (!empty($game_name)) {
        $display_name = $game_name === 'earth-defender' ? 'Earth Defender' : ucfirst(str_replace('-', ' ', $game_name));
        $stmt = $conn->prepare("INSERT INTO games (game_name, display_name, credits_per_chance, contest_credits_required) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE credits_per_chance = ?, contest_credits_required = ?, updated_at = NOW()");
        $stmt->bind_param("ssiiii", $game_name, $display_name, $credits_per_chance, $contest_credits_required, $credits_per_chance, $contest_credits_required);
        
        if ($stmt->execute()) {
            $desc = "Updated play costs for {$display_name}: Normal={$credits_per_chance} ⚡, Contest={$contest_credits_required} ⚡";
            $conn->query("INSERT INTO admin_logs (admin_id, admin_username, action, description, ip_address) VALUES ({$_SESSION['admin_id']}, '{$_SESSION['admin_username']}', 'update_game_credits', '$desc', '{$_SERVER['REMOTE_ADDR']}')");
            $message = "Play costs updated for {$display_name}!";
        }
        $stmt->close();
    }
}

// Ensure contest_credits_required is included in the query
$games = $conn->query("SELECT game_name, display_name, credits_per_chance, COALESCE(contest_credits_required, 30) as contest_credits_required, is_contest_active, updated_at FROM games ORDER BY display_name ASC")->fetch_all(MYSQLI_ASSOC);
$available_games = ['earth-defender' => '🛡️ Earth Defender'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Play Costs - Astraden Admin</title>
    <!-- Favicon - Must be early in head for proper display -->
    <link rel="icon" type="image/svg+xml" href="Alogo.svg">
    <link rel="shortcut icon" type="image/svg+xml" href="Alogo.svg">
    <link rel="alternate icon" type="image/png" href="Alogo.svg">
    <link rel="apple-touch-icon" sizes="180x180" href="Alogo.svg">
    <link rel="icon" type="image/svg+xml" sizes="any" href="Alogo.svg">
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
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; color: var(--primary-purple); font-weight: 700; font-size: 0.8rem; margin-bottom: 8px; }
        .form-group input, .form-group select { width: 100%; padding: 12px; background: rgba(0,0,0,0.5); border: 1px solid rgba(0,255,255,0.3); border-radius: 8px; color: white; outline: none; }

        .btn-update { background: linear-gradient(135deg, var(--primary-cyan), var(--primary-purple)); border: none; color: white; padding: 15px; border-radius: 10px; font-family: 'Orbitron', sans-serif; font-weight: 900; width: 100%; cursor: pointer; }

        .table-card { background: var(--card-bg); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 15px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
        th { color: var(--primary-purple); font-family: 'Orbitron', sans-serif; font-size: 0.7rem; text-transform: uppercase; background: rgba(0,0,0,0.2); }
        
        .cost-badge { background: linear-gradient(135deg, #FFD700, #FFA500); color: black; padding: 4px 10px; border-radius: 20px; font-family: 'Orbitron'; font-size: 0.75rem; font-weight: 900; }

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
            <a href="admin_shop_pricing.php" class="menu-item"><i class="fas fa-store ic-shop"></i> <span>Shop Pricing</span></a>
            <div class="menu-category">Game Operations</div>
            <a href="admin_game_credits.php" class="menu-item active"><i class="fas fa-gamepad ic-costs"></i> <span>Play Costs</span></a>
            <a href="admin_game_leaderboard.php" class="menu-item"><i class="fas fa-ranking-star ic-leaderboard"></i> <span>Leaderboards</span></a>
        </div>
        <div class="sidebar-footer"><a href="admin_logout.php" class="logout-btn"><i class="fas fa-power-off"></i> <span>Authorization Exit</span></a></div>
    </nav>

    <main class="main-content">
        <h2 class="section-title"><i class="fas fa-gamepad ic-costs" style="margin-right:15px;"></i> PLAY COSTS</h2>

        <?php if($message): ?><div class="msg"><?php echo $message; ?></div><?php endif; ?>

        <div class="config-card">
            <form method="POST">
                <div class="form-group">
                    <label>SELECT OPERATION TARGET</label>
                    <select name="game_name" id="game-select" onchange="loadGameCosts()">
                        <?php foreach($available_games as $k=>$v): ?><option value="<?php echo $k; ?>"><?php echo $v; ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label>NORMAL PLAY COST (Astrons)</label>
                        <input type="number" name="credits_per_chance" id="normal-cost" value="30" min="1" max="1000" required>
                        <small style="color: rgba(255,255,255,0.5); font-size: 0.75rem; display: block; margin-top: 5px;">Used when contest is not active</small>
                    </div>
                    <div class="form-group">
                        <label>CONTEST PLAY COST (Astrons)</label>
                        <input type="number" name="contest_credits_required" id="contest-cost" value="30" min="1" max="1000" required>
                        <small style="color: rgba(255,255,255,0.5); font-size: 0.75rem; display: block; margin-top: 5px;">Used when contest is active</small>
                    </div>
                </div>
                <button type="submit" name="update_credits" class="btn-update">SYNC MISSION COSTS</button>
            </form>
        </div>

        <div class="table-card">
            <table>
                <thead><tr><th>Target Module</th><th>Normal Cost</th><th>Contest Cost</th><th>Operation Status</th><th>Last Updated</th></tr></thead>
                <tbody>
                    <?php foreach($games as $g): 
                        $normal_cost = $g['credits_per_chance'] ?? 30;
                        $contest_cost = $g['contest_credits_required'] ?? 30;
                        $is_contest = isset($g['is_contest_active']) ? intval($g['is_contest_active']) : 0;
                    ?>
                    <tr>
                        <td><strong style="color:var(--primary-cyan);"><?php echo $g['display_name']; ?></strong></td>
                        <td><span class="cost-badge" style="background: linear-gradient(135deg, #00ffff, #0099cc);"><?php echo $normal_cost; ?> ⚡</span></td>
                        <td><span class="cost-badge" style="background: linear-gradient(135deg, #FFD700, #FFA500);"><?php echo $contest_cost; ?> ⚡</span></td>
                        <td>
                            <?php if ($is_contest): ?>
                                <span style="color:#FFD700;font-weight:bold;font-size:0.75rem;">CONTEST ACTIVE</span>
                            <?php else: ?>
                                <span style="color:#00ffcc;font-weight:bold;font-size:0.75rem;">NORMAL MODE</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:0.8rem;color:rgba(255,255,255,0.4);"><?php echo isset($g['updated_at']) ? date('M d, H:i', strtotime($g['updated_at'])) : 'N/A'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <script>
            const gamesData = <?php echo json_encode($games); ?>;
            
            function loadGameCosts() {
                const gameSelect = document.getElementById('game-select');
                const gameName = gameSelect.value;
                
                // Find the game in the games array
                const game = gamesData.find(g => g.game_name === gameName);
                
                if (game) {
                    document.getElementById('normal-cost').value = game.credits_per_chance || 30;
                    document.getElementById('contest-cost').value = game.contest_credits_required || 30;
                } else {
                    // Default values if game not found
                    document.getElementById('normal-cost').value = 30;
                    document.getElementById('contest-cost').value = 30;
                }
            }
            
            // Load costs on page load and when game selection changes
            document.addEventListener('DOMContentLoaded', function() {
                loadGameCosts();
            });
        </script>
    </main>
</body>
</html>
