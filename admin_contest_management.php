<?php
session_start();
require_once 'admin_check.php';
require_once 'connection.php';

$message = '';
$error = '';

// Auto-add missing columns and table
$conn->query("ALTER TABLE games ADD COLUMN IF NOT EXISTS contest_credits_required INT(11) DEFAULT 30");
$conn->query("ALTER TABLE contest_scores ADD COLUMN IF NOT EXISTS game_mode ENUM('money', 'credits') DEFAULT 'money'");
$conn->query("ALTER TABLE game_leaderboard ADD COLUMN IF NOT EXISTS game_mode ENUM('money', 'credits') DEFAULT 'money'");
$conn->query("CREATE TABLE IF NOT EXISTS contest_history (
    id INT(11) NOT NULL AUTO_INCREMENT,
    game_name VARCHAR(50) NOT NULL,
    game_mode ENUM('money', 'credits') NOT NULL,
    prize1 INT(11) NOT NULL,
    prize2 INT(11) NOT NULL,
    prize3 INT(11) NOT NULL,
    entry_fee INT(11) NOT NULL,
    status ENUM('active', 'completed') DEFAULT 'completed',
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Handle Contest Toggle & Mode Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_contest'])) {
        $game_name = $_POST['game_name'];
        $is_contest_active = isset($_POST['is_contest_active']) ? 1 : 0;
        $is_claim_active = isset($_POST['is_claim_active']) ? 1 : 0;
        $game_mode = $_POST['game_mode']; 
        $prize1 = intval($_POST['prize1']);
        $prize2 = intval($_POST['prize2']);
        $prize3 = intval($_POST['prize3']);
        $entry_fee = intval($_POST['entry_fee']);

        // Check if status is changing
        $current = $conn->query("SELECT is_contest_active FROM games WHERE game_name = '$game_name'")->fetch_assoc();
        
        $stmt = $conn->prepare("UPDATE games SET is_contest_active = ?, is_claim_active = ?, game_mode = ?, contest_first_prize = ?, contest_second_prize = ?, contest_third_prize = ?, contest_credits_required = ? WHERE game_name = ?");
        $stmt->bind_param("iisiiiis", $is_contest_active, $is_claim_active, $game_mode, $prize1, $prize2, $prize3, $entry_fee, $game_name);
        
        if ($stmt->execute()) {
            if ($is_contest_active && (!$current || !$current['is_contest_active'])) {
                // Starting new contest
                $hist = $conn->prepare("INSERT INTO contest_history (game_name, game_mode, prize1, prize2, prize3, entry_fee, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
                $hist->bind_param("ssiiii", $game_name, $game_mode, $prize1, $prize2, $prize3, $entry_fee);
                $hist->execute();
            } else if (!$is_contest_active && $current['is_contest_active']) {
                // Ending contest
                $conn->query("UPDATE contest_history SET status = 'completed', ended_at = NOW() WHERE game_name = '$game_name' AND status = 'active'");
            }

            $message = "Mission parameters deployed successfully!";
            $admin_id = $_SESSION['admin_id'];
            $admin_user = $_SESSION['admin_username'];
            $desc = "Updated contest for $game_name. Mode: $game_mode, Entry: $entry_fee";
            $conn->query("INSERT INTO admin_logs (admin_id, admin_username, action, description, ip_address) VALUES ($admin_id, '$admin_user', 'contest_update', '$desc', '{$_SERVER['REMOTE_ADDR']}')");
        } else {
            $error = "Failed to update mission data.";
        }
    }

    if (isset($_POST['clear_scores'])) {
        $game_name = $_POST['game_name'];
        if ($conn->query("DELETE FROM contest_scores WHERE game_name = '$game_name'")) {
            $message = "Mission combat data purged.";
        } else {
            $error = "Failed to purge data.";
        }
    }
}

$game_name = 'earth-defender';
$game_settings = $conn->query("SELECT * FROM games WHERE game_name = '$game_name'")->fetch_assoc();

// Unified Scoring Logic: Sum of scores from game_leaderboard (following Admin Leaderboard logic)
$scores_sql = "
    SELECT 
        u.username, 
        SUM(gl.score) as score, 
        MAX(gl.game_mode) as game_mode, 
        MAX(gl.played_at) as updated_at
    FROM game_leaderboard gl
    JOIN users u ON gl.user_id = u.id
    WHERE gl.game_name = '$game_name' AND gl.credits_used > 0
    GROUP BY u.id, u.username
    ORDER BY score DESC
    LIMIT 20
";
$scores = $conn->query($scores_sql)->fetch_all(MYSQLI_ASSOC);

$history = $conn->query("SELECT * FROM contest_history ORDER BY started_at DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contest Control - Astraden Admin</title>
    <!-- Favicon - Must be early in head for proper display -->
    <link rel="icon" type="image/svg+xml" href="logo.svg">
    <link rel="shortcut icon" type="image/svg+xml" href="logo.svg">
    <link rel="alternate icon" type="image/png" href="logo.svg">
    <link rel="apple-touch-icon" sizes="180x180" href="logo.svg">
    <link rel="icon" type="image/svg+xml" sizes="any" href="logo.svg">
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
        .sidebar-menu { flex: 1; overflow-y: auto; padding: 20px 0; scroll-behavior: smooth; }
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

        .config-card { background: var(--card-bg); border: 1px solid rgba(0, 255, 255, 0.2); border-radius: 20px; padding: 35px; margin-bottom: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .config-card h3 { font-family: 'Orbitron', sans-serif; font-size: 1.1rem; color: var(--primary-cyan); margin-bottom: 25px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px; }

        .toggle-group { display: flex; align-items: center; gap: 20px; margin-bottom: 25px; background: rgba(255,255,255,0.03); padding: 15px; border-radius: 12px; }
        .switch { position: relative; display: inline-block; width: 60px; height: 30px; flex-shrink: 0; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #333; transition: .4s; border-radius: 34px; border: 1px solid rgba(255,255,255,0.1); }
        .slider:before { position: absolute; content: ""; height: 22px; width: 22px; left: 4px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: var(--primary-cyan); }
        input:checked + .slider:before { transform: translateX(30px); }

        .mode-selector { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 30px; }
        .mode-option { 
            background: rgba(0,0,0,0.5); border: 2px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 20px; text-align: center; cursor: pointer; transition: 0.3s;
            display: flex; flex-direction: column; align-items: center; gap: 10px;
        }
        .mode-option i { font-size: 1.5rem; color: rgba(255,255,255,0.3); }
        .mode-option span { font-family: 'Orbitron'; font-size: 0.8rem; font-weight: bold; color: rgba(255,255,255,0.5); }
        .mode-option.active { border-color: var(--primary-cyan); background: rgba(0,255,255,0.05); }
        .mode-option.active i { color: var(--primary-cyan); text-shadow: 0 0 10px var(--primary-cyan); }
        .mode-option.active span { color: white; }

        .prize-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 30px; }
        .form-group label { display: block; color: var(--primary-purple); font-weight: 700; font-size: 0.8rem; margin-bottom: 8px; }
        .form-group input { width: 100%; padding: 12px; background: rgba(0,0,0,0.5); border: 1px solid rgba(0,255,255,0.3); border-radius: 8px; color: white; font-family:'Orbitron'; font-weight:bold; outline: none; text-align: center; }

        .btn-save { background: linear-gradient(135deg, var(--primary-cyan), var(--primary-purple)); border: none; color: white; padding: 15px; border-radius: 10px; font-family: 'Orbitron', sans-serif; font-weight: 900; width: 100%; cursor: pointer; transition: 0.3s; }
        .btn-clear { background: rgba(255, 0, 110, 0.1); border: 1px solid #ff006e; color: #ff006e; padding: 12px; border-radius: 10px; font-family: 'Orbitron', sans-serif; font-size: 0.75rem; font-weight: 700; width: 100%; cursor: pointer; margin-top: 15px; }

        .table-card { background: var(--card-bg); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 15px; overflow: hidden; margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
        th { color: var(--primary-purple); font-family: 'Orbitron', sans-serif; font-size: 0.7rem; text-transform: uppercase; background: rgba(0,0,0,0.2); }
        
        .rank-circle { width: 30px; height: 30px; border: 1px solid var(--primary-cyan); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary-cyan); font-weight: bold; font-family: 'Orbitron'; font-size: 0.8rem; }

        .msg { padding: 15px; border-radius: 10px; margin-bottom: 25px; background: rgba(0, 255, 204, 0.1); border: 1px solid #00ffcc; color: #00ffcc; font-weight: bold; }
    </style>
</head>
<body>
    <div class="space-bg"></div>

    <nav class="sidebar">
        <div class="sidebar-header"><h1>Astraden</h1></div>
        <div class="sidebar-menu" id="sidebarMenu">
            <div class="menu-category">General</div>
            <a href="admin_dashboard.php" class="menu-item"><i class="fas fa-chart-line ic-overview"></i> <span>Overview</span></a>
            <div class="menu-category">User Control</div>
            <a href="admin_view_all_users.php" class="menu-item"><i class="fas fa-users ic-users"></i> <span>User Directory</span></a>
            <a href="admin_password_reset_requests.php" class="menu-item"><i class="fas fa-key ic-reset"></i> <span>Reset Requests</span></a>
            <div class="menu-category">Financials</div>
            <a href="admin_transaction_codes.php" class="menu-item"><i class="fas fa-qrcode ic-verify"></i> <span>Verify Payments</span></a>
            <a href="admin_user_credits.php" class="menu-item"><i class="fas fa-coins ic-credits"></i> <span>Manual Credits</span></a>
            <a href="admin_credit_pricing.php" class="menu-item"><i class="fas fa-tags ic-pricing"></i> <span>Pricing Plans</span></a>
            <a href="admin_credit_timing.php" class="menu-item"><i class="fas fa-clock ic-timing"></i> <span>Purchase Timing</span></a>
            <a href="admin_credit_sale_limit.php" class="menu-item"><i class="fas fa-gauge-high ic-limits"></i> <span>Sale Limits</span></a>
            <div class="menu-category">Game Operations</div>
            <a href="admin_game_timing.php" class="menu-item"><i class="fas fa-calendar-check ic-sessions"></i> <span>Game Sessions</span></a>
            <a href="admin_contest_management.php" class="menu-item active"><i class="fas fa-trophy ic-contest"></i> <span>Contest Control</span></a>
            <a href="admin_game_credits.php" class="menu-item"><i class="fas fa-gamepad ic-costs"></i> <span>Play Costs</span></a>
            <a href="admin_game_prizes.php" class="menu-item"><i class="fas fa-award ic-prizes"></i> <span>Prize Setup</span></a>
            <a href="admin_game_leaderboard.php" class="menu-item"><i class="fas fa-ranking-star ic-leaderboard"></i> <span>Leaderboards</span></a>
        </div>
        <div class="sidebar-footer"><a href="admin_logout.php" class="logout-btn"><i class="fas fa-power-off"></i> <span>Exit</span></a></div>
    </nav>

    <main class="main-content">
        <h2 class="section-title"><i class="fas fa-trophy ic-contest" style="margin-right:15px;"></i> CONTEST CONTROL</h2>

        <?php if($message): ?><div class="msg"><?php echo $message; ?></div><?php endif; ?>

        <div class="config-card">
            <form method="POST">
                <input type="hidden" name="game_name" value="earth-defender">
                <h3>System Configuration</h3>
                
                <div class="toggle-group">
                    <label class="switch">
                        <input type="checkbox" name="is_contest_active" <?php echo $game_settings['is_contest_active'] ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                    <div>
                        <strong style="display:block;color:var(--color-contest);">CONTEST MODE</strong>
                        <small style="color:rgba(255,255,255,0.4);">Enables score tracking and mission deployment.</small>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:10px;"><label>ACTIVE MISSION REWARD MODE</label></div>
                <div class="mode-selector">
                    <input type="hidden" name="game_mode" id="game_mode_input" value="<?php echo $game_settings['game_mode'] ?: 'money'; ?>">
                    <div class="mode-option <?php echo ($game_settings['game_mode'] ?: 'money') === 'money' ? 'active' : ''; ?>" onclick="setMode('money')">
                        <i class="fas fa-indian-rupee-sign"></i>
                        <span>PRIZE MONEY</span>
                    </div>
                    <div class="mode-option <?php echo $game_settings['game_mode'] === 'credits' ? 'active' : ''; ?>" onclick="setMode('credits')">
                        <i class="fas fa-coins"></i>
                        <span>CREDIT WINNING</span>
                    </div>
                </div>

                <div class="prize-grid">
                    <div class="form-group"><label id="prize1_label">1ST RANK REWARD</label><input type="number" name="prize1" value="<?php echo $game_settings['contest_first_prize']; ?>"></div>
                    <div class="form-group"><label id="prize2_label">2ND RANK REWARD</label><input type="number" name="prize2" value="<?php echo $game_settings['contest_second_prize']; ?>"></div>
                    <div class="form-group"><label id="prize3_label">3RD RANK REWARD</label><input type="number" name="prize3" value="<?php echo $game_settings['contest_third_prize']; ?>"></div>
                    <div class="form-group"><label>PARTICIPATION FEE (⚡)</label><input type="number" name="entry_fee" value="<?php echo $game_settings['contest_credits_required'] ?: 30; ?>"></div>
                </div>

                <div class="toggle-group">
                    <label class="switch">
                        <input type="checkbox" name="is_claim_active" <?php echo $game_settings['is_claim_active'] ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                    <div>
                        <strong style="display:block;color:var(--color-verify);">PRIZE CLAIMING</strong>
                        <small style="color:rgba(255,255,255,0.4);">Allows top winners to claim their mission rewards.</small>
                    </div>
                </div>

                <button type="submit" name="update_contest" class="btn-save">DEPLOY MISSION SETTINGS</button>
                <button type="submit" name="clear_scores" class="btn-clear" onclick="return confirm('Purge current leaderboard?');">WIPE LIVE DATA</button>
            </form>
        </div>

        <div class="table-card">
            <h3 style="padding:20px;font-family:'Orbitron';color:var(--primary-cyan);font-size:0.9rem;">MISSION HISTORY (LOGS)</h3>
            <table>
                <thead>
                    <tr><th>Mode</th><th>Rewards (1/2/3)</th><th>Entry</th><th>Status</th><th>Started At</th></tr>
                </thead>
                <tbody>
                    <?php if(empty($history)): ?>
                        <tr><td colspan="5" style="text-align:center;padding:40px;color:rgba(255,255,255,0.2);">NO MISSION HISTORY FOUND</td></tr>
                    <?php endif;
                    foreach($history as $h): ?>
                    <tr>
                        <td style="text-transform:uppercase;color:var(--primary-cyan);font-weight:bold;"><?php echo $h['game_mode']; ?></td>
                        <td><?php echo $h['prize1'].'/'.$h['prize2'].'/'.$h['prize3']; ?></td>
                        <td style="color:#FFD700;font-weight:bold;"><?php echo $h['entry_fee']; ?> ⚡</td>
                        <td><span style="color:<?php echo $h['status']==='active'?'#00ffcc':'rgba(255,255,255,0.2)'; ?>;font-size:0.7rem;font-weight:bold;"><?php echo strtoupper($h['status']); ?></span></td>
                        <td style="font-size:0.8rem;color:rgba(255,255,255,0.4);"><?php echo date('M d, H:i', strtotime($h['started_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="table-card">
            <h3 style="padding:20px;font-family:'Orbitron';color:var(--primary-cyan);font-size:0.9rem;">LIVE RANKINGS (EARTH DEFENDER)</h3>
            <table>
                <thead>
                    <tr><th>Rank</th><th>Player Identity</th><th>Combat Score</th><th>Mode</th><th>Timestamp</th></tr>
                </thead>
                <tbody>
                    <?php if(empty($scores)): ?>
                        <tr><td colspan="5" style="text-align:center;padding:40px;color:rgba(255,255,255,0.2);">NO COMBAT DATA RECORDED</td></tr>
                    <?php endif;
                    $rank = 1; foreach($scores as $s): ?>
                    <tr>
                        <td><div class="rank-circle"><?php echo $rank++; ?></div></td>
                        <td style="color:var(--primary-cyan);font-weight:bold;"><?php echo htmlspecialchars($s['username']); ?></td>
                        <td style="font-family:'Orbitron';color:var(--color-credits);"><?php echo number_format($s['score']); ?></td>
                        <td>
                            <span style="font-size:0.7rem;padding:3px 8px;border-radius:4px;background:rgba(0,255,255,0.1);color:var(--primary-cyan);font-weight:bold;text-transform:uppercase;">
                                <?php echo ($s['game_mode'] ?? 'money') === 'money' ? '💰 Money' : '⚡ Credits'; ?>
                            </span>
                        </td>
                        <td style="font-size:0.8rem;color:rgba(255,255,255,0.4);"><?php echo date('M d, H:i', strtotime($s['updated_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        function setMode(mode) {
            document.getElementById('game_mode_input').value = mode;
            document.querySelectorAll('.mode-option').forEach(opt => opt.classList.remove('active'));
            // Use currentTarget if triggered by click, or find by mode if triggered by code
            const target = event ? event.currentTarget : document.querySelector(`.mode-option i.fa-${mode==='money'?'indian-rupee-sign':'coins'}`).parentElement;
            if(target) target.classList.add('active');
            
            const suffix = mode === 'money' ? ' (INR)' : ' (CREDITS)';
            document.getElementById('prize1_label').textContent = '1ST RANK REWARD' + suffix;
            document.getElementById('prize2_label').textContent = '2ND RANK REWARD' + suffix;
            document.getElementById('prize3_label').textContent = '3RD RANK REWARD' + suffix;
        }

        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('sidebarMenu');
            const savedScroll = localStorage.getItem('sidebar_scroll');
            if (savedScroll) sidebar.scrollTop = savedScroll;
            sidebar.addEventListener('scroll', () => { localStorage.setItem('sidebar_scroll', sidebar.scrollTop); });
            
            const activeItem = sidebar.querySelector('.menu-item.active');
            if (activeItem) activeItem.scrollIntoView({ block: 'center' });

            const currentMode = document.getElementById('game_mode_input').value;
            setMode(currentMode);
        });
    </script>
</body>
</html>
