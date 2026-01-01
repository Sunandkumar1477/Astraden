<?php
session_start();
require_once 'connection.php';

// Set timezone to India (IST)
date_default_timezone_set('Asia/Kolkata');

$game_name = $_GET['game'] ?? 'all';
$session_id = intval($_GET['session_id'] ?? 0);

// Get game display name
$game_display_names = [
    'earth-defender' => '🛡️ Earth Defender',
    'cosmos-captain' => '🚀 Cosmos Captain',
    'all' => 'All Games'
];
$game_display_name = $game_display_names[$game_name] ?? ucfirst(str_replace('-', ' ', $game_name));

// Get session details
$session_data = null;
$leaderboard = [];
$error = '';
$available_sessions = [];

// If no session_id provided, get list of available time-duration sessions
if ($session_id <= 0) {
    $sessions_stmt = $conn->prepare("
        SELECT gs.*, g.game_name as game_display_name
        FROM game_sessions gs
        LEFT JOIN games g ON gs.game_name = g.game_name
        WHERE gs.is_active = 1 
        AND gs.always_available = 0
        AND gs.duration_minutes > 0
        ORDER BY gs.session_date DESC, gs.session_time DESC
        LIMIT 20
    ");
    $sessions_stmt->execute();
    $sessions_result = $sessions_stmt->get_result();
    $available_sessions = $sessions_result->fetch_all(MYSQLI_ASSOC);
    $sessions_stmt->close();
}

if ($session_id > 0) {
    // If game_name is 'all', don't filter by game_name
    if ($game_name === 'all') {
        $session_stmt = $conn->prepare("SELECT * FROM game_sessions WHERE id = ?");
        $session_stmt->bind_param("i", $session_id);
    } else {
        $session_stmt = $conn->prepare("SELECT * FROM game_sessions WHERE id = ? AND game_name = ?");
        $session_stmt->bind_param("is", $session_id, $game_name);
    }
    $session_stmt->execute();
    $session_result = $session_stmt->get_result();
    
    if ($session_result->num_rows > 0) {
        $session_data = $session_result->fetch_assoc();
        
        // Get top 10 scores for this session
        $leaderboard_stmt = $conn->prepare("
            SELECT 
                gl.score,
                gl.played_at,
                u.id as user_id,
                u.username,
                up.full_name,
                up.credits_color
            FROM game_leaderboard gl
            JOIN users u ON gl.user_id = u.id
            LEFT JOIN user_profile up ON u.id = up.user_id
            WHERE gl.session_id = ? 
            AND gl.game_name = ?
            AND gl.credits_used > 0
            ORDER BY gl.score DESC
            LIMIT 10
        ");
        // If game_name is 'all', don't filter by game_name
        if ($game_name === 'all') {
            $leaderboard_stmt = $conn->prepare("
                SELECT 
                    gl.score,
                    gl.played_at,
                    gl.game_name,
                    u.id as user_id,
                    u.username,
                    up.full_name,
                    up.credits_color
                FROM game_leaderboard gl
                JOIN users u ON gl.user_id = u.id
                LEFT JOIN user_profile up ON u.id = up.user_id
                WHERE gl.session_id = ? 
                AND gl.credits_used > 0
                ORDER BY gl.score DESC
                LIMIT 10
            ");
            $leaderboard_stmt->bind_param("i", $session_id);
        } else {
            $leaderboard_stmt->bind_param("is", $session_id, $game_name);
        }
        $leaderboard_stmt->execute();
        $leaderboard_result = $leaderboard_stmt->get_result();
        $leaderboard = $leaderboard_result->fetch_all(MYSQLI_ASSOC);
        
        // Add rank numbers
        $ranked_leaderboard = [];
        $current_rank = 1;
        $prev_score = null;
        foreach ($leaderboard as $index => $entry) {
            $entry['score'] = intval($entry['score']);
            if ($prev_score !== null && $entry['score'] < $prev_score) {
                $current_rank = $index + 1;
            }
            $entry['rank'] = $current_rank;
            $ranked_leaderboard[] = $entry;
            $prev_score = $entry['score'];
        }
        $leaderboard = $ranked_leaderboard;
        $leaderboard_stmt->close();
    } else {
        $error = 'Session not found';
    }
    $session_stmt->close();
} else {
    // No session_id provided - show list of available sessions
    $error = '';
}

// Format session date/time if available
$session_start_display = '';
$session_end_display = '';
if ($session_data) {
    $session_date = $session_data['session_date'];
    $session_time = $session_data['session_time'];
    $duration_minutes = $session_data['duration_minutes'];
    $session_start_dt = new DateTime($session_date . ' ' . $session_time, new DateTimeZone('Asia/Kolkata'));
    $session_end_dt = clone $session_start_dt;
    $session_end_dt->modify('+' . $duration_minutes . ' minutes');
    
    $session_start_display = $session_start_dt->format('d M Y, h:i A') . ' IST';
    $session_end_display = $session_end_dt->format('d M Y, h:i A') . ' IST';
}

// Get all active rewards
$rewards = $conn->query("
    SELECT r.*, 
           CASE WHEN r.is_sold = 1 THEN 1 
                WHEN r.expire_date IS NOT NULL AND r.expire_date < NOW() THEN 1 
                WHEN r.gift_type = 'coupon' AND r.showcase_date IS NOT NULL AND r.showcase_date > NOW() THEN 1
                WHEN r.gift_type = 'coupon' AND r.showcase_date IS NOT NULL AND r.display_days > 0 AND DATE_ADD(r.showcase_date, INTERVAL r.display_days DAY) < NOW() THEN 1
                ELSE 0 END as is_unavailable
    FROM rewards r 
    WHERE r.is_active = 1 
    AND (r.gift_type = 'reward' OR (r.gift_type = 'coupon' AND r.showcase_date IS NOT NULL AND r.showcase_date <= NOW() AND (r.display_days = 0 OR DATE_ADD(r.showcase_date, INTERVAL r.display_days DAY) >= NOW())))
    ORDER BY r.gift_type DESC, r.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reward Claim - <?php echo htmlspecialchars($game_display_name); ?></title>
    <link rel="icon" type="image/svg+xml" href="Alogo.svg">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #0a0a1a 0%, #1a1a2e 100%);
            color: #fff;
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
        }
        
        .header h1 {
            font-size: 2.5rem;
            color: #00ffff;
            text-shadow: 0 0 20px rgba(0, 255, 255, 0.5);
            margin-bottom: 10px;
        }
        
        .header .game-name {
            font-size: 1.5rem;
            color: #9d4edd;
            margin-bottom: 20px;
        }
        
        .session-info {
            background: rgba(0, 255, 255, 0.1);
            border: 2px solid #00ffff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .session-info h2 {
            color: #00ffff;
            margin-bottom: 15px;
            font-size: 1.3rem;
        }
        
        .session-info p {
            color: #ccc;
            margin: 5px 0;
            font-size: 1rem;
        }
        
        .session-info .time-range {
            color: #ffd700;
            font-weight: bold;
            margin-top: 10px;
        }
        
        .leaderboard-container {
            background: rgba(0, 0, 0, 0.6);
            border: 2px solid #00ffff;
            border-radius: 15px;
            padding: 20px;
            backdrop-filter: blur(10px);
        }
        
        .leaderboard-title {
            text-align: center;
            font-size: 1.8rem;
            color: #00ffff;
            margin-bottom: 20px;
            text-shadow: 0 0 15px rgba(0, 255, 255, 0.5);
        }
        
        .leaderboard-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .leaderboard-table thead {
            background: rgba(0, 255, 255, 0.2);
        }
        
        .leaderboard-table th {
            padding: 15px;
            text-align: left;
            color: #00ffff;
            font-weight: bold;
            border-bottom: 2px solid #00ffff;
        }
        
        .leaderboard-table td {
            padding: 15px;
            border-bottom: 1px solid rgba(0, 255, 255, 0.2);
        }
        
        .leaderboard-table tbody tr:hover {
            background: rgba(0, 255, 255, 0.1);
        }
        
        .rank {
            font-weight: bold;
            font-size: 1.2rem;
            color: #ffd700;
            text-align: center;
            width: 60px;
        }
        
        .rank-1 { color: #ffd700; }
        .rank-2 { color: #c0c0c0; }
        .rank-3 { color: #cd7f32; }
        
        .player-name {
            font-weight: bold;
            color: #fff;
        }
        
        .player-name .full-name {
            color: #00ffff;
            margin-right: 8px;
        }
        
        .player-name .username {
            color: #999;
            font-size: 0.9rem;
        }
        
        .score {
            font-size: 1.3rem;
            font-weight: bold;
            color: #00ff00;
            text-align: right;
        }
        
        .error-message {
            background: rgba(255, 77, 77, 0.2);
            border: 2px solid #ff4d4d;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            color: #ff4d4d;
            font-size: 1.2rem;
        }
        
        .back-button {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 30px;
            background: linear-gradient(135deg, #00ffff, #9d4edd);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .back-button:hover {
            transform: scale(1.05);
            box-shadow: 0 0 20px rgba(0, 255, 255, 0.5);
        }
        
        .empty-message {
            text-align: center;
            padding: 40px;
            color: #999;
            font-size: 1.1rem;
        }
        
        @media (max-width: 768px) {
            .header h1 {
                font-size: 1.8rem;
            }
            
            .header .game-name {
                font-size: 1.2rem;
            }
            
            .leaderboard-table {
                font-size: 0.9rem;
            }
            
            .leaderboard-table th,
            .leaderboard-table td {
                padding: 10px 8px;
            }
            
            .rank {
                font-size: 1rem;
            }
            
            .score {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏆 Reward Claim</h1>
            <div class="game-name"><?php echo htmlspecialchars($game_display_name); ?></div>
        </div>
        
        <?php if ($error && $session_id > 0): ?>
            <div class="error-message">
                <p><?php echo htmlspecialchars($error); ?></p>
                <a href="index.php" class="back-button">Back to Home</a>
            </div>
        <?php elseif (empty($session_data) && empty($available_sessions)): ?>
            <div class="error-message">
                <p>No reward claim sessions available at the moment.</p>
                <a href="index.php" class="back-button">Back to Home</a>
            </div>
        <?php elseif (!empty($available_sessions) && $session_id <= 0): ?>
            <div class="session-info">
                <h2>Select a Session</h2>
                <p style="color: rgba(0, 255, 255, 0.8); margin-bottom: 20px;">Choose a time-duration session to view reward claim leaderboard:</p>
            </div>
            
            <div class="leaderboard-container">
                <h2 class="leaderboard-title">Available Reward Sessions</h2>
                <div style="display: grid; gap: 15px;">
                    <?php foreach ($available_sessions as $session): 
                        $session_date = $session['session_date'];
                        $session_time = $session['session_time'];
                        $duration_minutes = $session['duration_minutes'];
                        $session_start_dt = new DateTime($session_date . ' ' . $session_time, new DateTimeZone('Asia/Kolkata'));
                        $session_end_dt = clone $session_start_dt;
                        $session_end_dt->modify('+' . $duration_minutes . ' minutes');
                        
                        $game_display = $game_display_names[$session['game_name']] ?? ucfirst(str_replace('-', ' ', $session['game_name']));
                    ?>
                        <a href="prize_claim.php?game=<?php echo htmlspecialchars($session['game_name']); ?>&session_id=<?php echo $session['id']; ?>" 
                           style="display: block; background: rgba(0, 255, 255, 0.1); border: 2px solid #00ffff; border-radius: 10px; padding: 20px; text-decoration: none; color: #fff; transition: all 0.3s;">
                            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                                <div>
                                    <h3 style="color: #00ffff; margin-bottom: 8px; font-size: 1.2rem;"><?php echo htmlspecialchars($game_display); ?></h3>
                                    <p style="color: #ccc; font-size: 0.9rem; margin: 3px 0;">
                                        <strong>Start:</strong> <?php echo $session_start_dt->format('d M Y, h:i A'); ?> IST
                                    </p>
                                    <p style="color: #ccc; font-size: 0.9rem; margin: 3px 0;">
                                        <strong>End:</strong> <?php echo $session_end_dt->format('d M Y, h:i A'); ?> IST
                                    </p>
                                    <p style="color: #ffd700; font-weight: bold; margin-top: 8px;">
                                        Duration: <?php echo $duration_minutes; ?> minutes
                                    </p>
                                </div>
                                <div style="color: #00ffff; font-size: 1.5rem;">→</div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <a href="index.php" class="back-button">Back to Home</a>
            </div>
        <?php elseif ($session_data): ?>
            <div class="session-info">
                <h2>Session Details</h2>
                <p><strong>Start:</strong> <?php echo htmlspecialchars($session_start_display); ?></p>
                <p><strong>End:</strong> <?php echo htmlspecialchars($session_end_display); ?></p>
                <p class="time-range">Duration: <?php echo $duration_minutes; ?> minutes</p>
            </div>
            
            <div class="leaderboard-container">
                <h2 class="leaderboard-title">Top 10 Scores</h2>
                
                <?php if (empty($leaderboard)): ?>
                    <div class="empty-message">
                        No scores recorded for this session yet.
                    </div>
                <?php else: ?>
                    <table class="leaderboard-table">
                        <thead>
                            <tr>
                                <th class="rank">Rank</th>
                                <th>Player Name</th>
                                <th style="text-align: right;">Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($leaderboard as $entry): ?>
                                <tr>
                                    <td class="rank rank-<?php echo $entry['rank']; ?>">
                                        <?php 
                                        if ($entry['rank'] == 1) echo '🥇';
                                        elseif ($entry['rank'] == 2) echo '🥈';
                                        elseif ($entry['rank'] == 3) echo '🥉';
                                        else echo '#' . $entry['rank'];
                                        ?>
                                    </td>
                                    <td class="player-name">
                                        <?php if (!empty($entry['full_name'])): ?>
                                            <span class="full-name"><?php echo htmlspecialchars($entry['full_name']); ?></span>
                                        <?php endif; ?>
                                        <span class="username">(@<?php echo htmlspecialchars($entry['username']); ?>)</span>
                                    </td>
                                    <td class="score"><?php echo number_format($entry['score']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <a href="index.php" class="back-button">Back to Home</a>
            </div>
        <?php endif; ?>
        
        <!-- Rewards Section -->
        <div class="leaderboard-container" style="margin-top: 40px;">
            <h2 class="leaderboard-title">🎁 Available Rewards</h2>
            
            <?php if(empty($rewards)): ?>
                <div class="empty-message">
                    No rewards available at the moment. Check back later!
                </div>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; margin-top: 20px;">
                    <?php foreach($rewards as $reward): 
                        $is_unavailable = $reward['is_unavailable'] || ($reward['expire_date'] && strtotime($reward['expire_date']) < time());
                    ?>
                    <div style="background: rgba(0, 255, 255, 0.1); border: 2px solid <?php echo $is_unavailable ? 'rgba(255, 0, 110, 0.5)' : ($reward['gift_type'] === 'coupon' ? '#fbbf24' : '#00ffff'); ?>; border-radius: 15px; padding: 20px; position: relative;">
                        <?php if($is_unavailable): ?>
                            <div style="position: absolute; top: 10px; right: 10px; background: rgba(255, 0, 110, 0.2); color: #ff006e; padding: 5px 10px; border-radius: 5px; font-size: 0.75rem; font-weight: bold;">SOLD OUT</div>
                        <?php else: ?>
                            <div style="position: absolute; top: 10px; right: 10px; background: rgba(0, 255, 255, 0.2); color: #00ffff; padding: 5px 10px; border-radius: 5px; font-size: 0.75rem; font-weight: bold;"><?php echo strtoupper($reward['gift_type']); ?></div>
                        <?php endif; ?>
                        
                        <div style="text-align: center; font-size: 3rem; margin-bottom: 15px;">
                            <?php echo $reward['gift_type'] === 'coupon' ? '🎫' : '🎁'; ?>
                        </div>
                        
                        <h3 style="color: #00ffff; text-align: center; margin-bottom: 10px; font-size: 1.2rem;">
                            <?php echo htmlspecialchars($reward['gift_name']); ?>
                        </h3>
                        
                        <div style="text-align: center; color: rgba(255, 255, 255, 0.6); margin-bottom: 10px; font-size: 0.9rem;">
                            <?php echo ucfirst($reward['gift_type']); ?>
                        </div>
                        
                        <?php if($reward['gift_type'] === 'coupon' && $reward['about_coupon']): ?>
                            <div style="color: #fbbf24; margin-bottom: 10px; font-size: 0.9rem; text-align: center; font-weight: 600;">
                                <?php echo nl2br(htmlspecialchars($reward['about_coupon'])); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if($reward['coupon_details']): ?>
                            <div style="color: rgba(255, 255, 255, 0.8); margin-bottom: 10px; font-size: 0.85rem; line-height: 1.4;">
                                <?php echo nl2br(htmlspecialchars($reward['coupon_details'])); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if($reward['time_duration']): ?>
                            <div style="color: #9d4edd; margin-bottom: 10px; font-size: 0.9rem;">
                                <i class="fas fa-clock"></i> <?php echo htmlspecialchars($reward['time_duration']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if($reward['gift_type'] === 'coupon' && $reward['showcase_date']): ?>
                            <div style="color: rgba(0, 255, 255, 0.7); font-size: 0.8rem; margin-bottom: 5px;">
                                <i class="fas fa-calendar-alt"></i> Showcase: <?php echo date('d M Y H:i', strtotime($reward['showcase_date'])); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if($reward['expire_date']): ?>
                            <div style="color: rgba(255, 255, 255, 0.5); font-size: 0.8rem; margin-bottom: 10px;">
                                <i class="fas fa-hourglass-end"></i> Expires: <?php echo date('d M Y H:i', strtotime($reward['expire_date'])); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div style="text-align: center; font-size: 1.5rem; color: #ffd700; font-weight: bold; margin: 15px 0;">
                            <?php echo number_format($reward['credits_cost']); ?> ⚡ Credits
                        </div>
                        
                        <a href="rewards.php" style="display: block; text-align: center; padding: 12px; background: linear-gradient(135deg, #00ffff, #9d4edd); color: white; text-decoration: none; border-radius: 8px; font-weight: bold; transition: all 0.3s; margin-top: 10px;"
                           onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 5px 15px rgba(0, 255, 255, 0.4)';"
                           onmouseout="this.style.transform=''; this.style.boxShadow='';">
                            <?php if($is_unavailable): ?>
                                SOLD OUT
                            <?php else: ?>
                                VIEW & PURCHASE
                            <?php endif; ?>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

