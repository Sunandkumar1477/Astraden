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

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prize Claim - <?php echo htmlspecialchars($game_display_name); ?></title>
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
            <h1>🏆 Prize Claim</h1>
            <div class="game-name"><?php echo htmlspecialchars($game_display_name); ?></div>
        </div>
        
        <?php if ($error && $session_id > 0): ?>
            <div class="error-message">
                <p><?php echo htmlspecialchars($error); ?></p>
                <a href="index.php" class="back-button">Back to Home</a>
            </div>
        <?php elseif (empty($session_data) && empty($available_sessions)): ?>
            <div class="error-message">
                <p>No prize claim sessions available at the moment.</p>
                <a href="index.php" class="back-button">Back to Home</a>
            </div>
        <?php elseif (!empty($available_sessions) && $session_id <= 0): ?>
            <div class="session-info">
                <h2>Select a Session</h2>
                <p style="color: rgba(0, 255, 255, 0.8); margin-bottom: 20px;">Choose a time-duration session to view prize claim leaderboard:</p>
            </div>
            
            <div class="leaderboard-container">
                <h2 class="leaderboard-title">Available Prize Sessions</h2>
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
    </div>
</body>
</html>

