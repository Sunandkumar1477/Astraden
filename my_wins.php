<?php
session_start();
require_once 'security_headers.php';
require_once 'check_user_session.php';
require_once 'connection.php';

$user_id = intval($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) {
    header('Location: index.php');
    exit;
}

// Get user wins
$wins = $conn->query("SELECT uw.*, bi.title, bi.description 
    FROM user_wins uw 
    JOIN bidding_items bi ON uw.bidding_item_id = bi.id 
    WHERE uw.user_id = $user_id 
    ORDER BY uw.win_date DESC")->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Wins - Space Games Hub</title>
    <link rel="icon" type="image/svg+xml" href="Alogo.svg">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-cyan: #00ffff;
            --primary-purple: #9d4edd;
            --dark-bg: #05050a;
            --card-bg: rgba(15, 15, 25, 0.95);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Rajdhani', sans-serif; background: var(--dark-bg); color: white; min-height: 100vh; padding: 20px; }
        .space-bg { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: radial-gradient(circle at 10% 20%, #1a1a2e 0%, #05050a 100%); z-index: -1; }
        .header { text-align: center; margin-bottom: 30px; padding: 20px 0; }
        .header h1 { font-family: 'Orbitron', sans-serif; color: var(--primary-cyan); font-size: 2rem; }
        .wins-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
        .win-card { background: var(--card-bg); border: 2px solid #FFD700; border-radius: 15px; padding: 20px; }
        .win-card.claimed { border-color: #00ff00; opacity: 0.7; }
        .win-title { font-family: 'Orbitron', sans-serif; color: var(--primary-cyan); font-size: 1.2rem; margin-bottom: 10px; }
        .win-amount { color: #FFD700; font-size: 1.5rem; font-weight: 900; margin: 10px 0; }
        .win-date { color: rgba(255,255,255,0.6); font-size: 0.9rem; margin: 10px 0; }
        .btn-claim { padding: 10px 20px; background: linear-gradient(135deg, #00ff00, #00cc00); border: none; border-radius: 8px; color: white; font-family: 'Orbitron', sans-serif; font-weight: 700; cursor: pointer; }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 10px 20px; background: rgba(0,255,255,0.1); border: 1px solid var(--primary-cyan); border-radius: 8px; color: var(--primary-cyan); text-decoration: none; }
    </style>
</head>
<body>
    <div class="space-bg"></div>
    <a href="index.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Home</a>
    
    <div class="header">
        <h1><i class="fas fa-trophy"></i> MY WINS</h1>
    </div>
    
    <div class="wins-grid">
        <?php if(empty($wins)): ?>
            <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: rgba(255,255,255,0.5);">
                <i class="fas fa-trophy" style="font-size: 3rem; opacity: 0.3; margin-bottom: 20px;"></i>
                <p>No wins yet. Start bidding to win prizes!</p>
            </div>
        <?php else: ?>
            <?php foreach($wins as $win): ?>
            <div class="win-card <?php echo $win['is_claimed'] ? 'claimed' : ''; ?>">
                <div class="win-title"><?php echo htmlspecialchars($win['title']); ?></div>
                <div class="win-amount">₹<?php echo number_format($win['win_amount'], 2); ?></div>
                <div class="win-date">Won on: <?php echo date('M d, Y H:i', strtotime($win['win_date'])); ?></div>
                <?php if(!$win['is_claimed']): ?>
                    <button class="btn-claim" onclick="claimWin(<?php echo $win['id']; ?>)">Claim Prize</button>
                <?php else: ?>
                    <div style="color: #00ff00; margin-top: 10px;">✓ Claimed on <?php echo date('M d, Y', strtotime($win['claimed_at'])); ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <script>
        function claimWin(winId) {
            if (!confirm('Claim this prize? You will receive ₹' + document.querySelector(`[onclick="claimWin(${winId})"]`).closest('.win-card').querySelector('.win-amount').textContent.replace('₹', ''))) {
                return;
            }
            
            fetch('bidding_api.php?action=claim_win', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'win_id=' + winId
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Prize claimed successfully!');
                    location.reload();
                } else {
                    alert(data.message || 'Failed to claim prize');
                }
            });
        }
    </script>
</body>
</html>

