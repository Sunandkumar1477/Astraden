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

// Get user credits
$user_credits = $conn->query("SELECT credits FROM user_profile WHERE user_id = $user_id")->fetch_assoc()['credits'] ?? 0;

// Get Astrons per credit
$settings = $conn->query("SELECT astrons_per_credit FROM bidding_settings LIMIT 1")->fetch_assoc();
$astrons_per_credit = $settings ? floatval($settings['astrons_per_credit']) : 1.00;

// Get user Astrons balance
$user_astrons = $conn->query("SELECT astrons_balance FROM user_astrons WHERE user_id = $user_id")->fetch_assoc();
$astrons_balance = $user_astrons ? floatval($user_astrons['astrons_balance']) : 0;

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buy Astrons - Space Games Hub</title>
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
        .balance-card { background: var(--card-bg); border: 2px solid var(--primary-cyan); border-radius: 15px; padding: 20px; margin-bottom: 30px; text-align: center; }
        .balance-card h2 { color: var(--primary-purple); margin-bottom: 10px; }
        .balance-card .amount { font-size: 2rem; font-weight: 900; color: #FFD700; }
        .purchase-card { background: var(--card-bg); border: 1px solid rgba(0, 255, 255, 0.3); border-radius: 15px; padding: 30px; max-width: 500px; margin: 0 auto; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; color: var(--primary-purple); font-weight: 700; margin-bottom: 8px; }
        .form-group input { width: 100%; padding: 12px; background: rgba(0,0,0,0.5); border: 1px solid var(--primary-cyan); border-radius: 8px; color: white; }
        .info-text { color: rgba(255,255,255,0.6); font-size: 0.9rem; margin-top: 5px; }
        .btn-buy { width: 100%; padding: 15px; background: linear-gradient(135deg, var(--primary-cyan), var(--primary-purple)); border: none; border-radius: 8px; color: white; font-family: 'Orbitron', sans-serif; font-weight: 900; cursor: pointer; font-size: 1.1rem; }
        .btn-buy:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0, 255, 255, 0.4); }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 10px 20px; background: rgba(0,255,255,0.1); border: 1px solid var(--primary-cyan); border-radius: 8px; color: var(--primary-cyan); text-decoration: none; }
    </style>
</head>
<body>
    <div class="space-bg"></div>
    <a href="bidding.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Bidding</a>
    
    <div class="header">
        <h1><i class="fas fa-coins"></i> BUY ASTRONS</h1>
    </div>
    
    <div class="balance-card">
        <h2>Your Credits</h2>
        <div class="amount" id="creditsBalance"><?php echo number_format($user_credits); ?></div>
    </div>
    
    <div class="balance-card">
        <h2>Your Astrons Balance</h2>
        <div class="amount" id="astronsBalance"><?php echo number_format($astrons_balance, 2); ?></div>
    </div>
    
    <div class="purchase-card">
        <h2 style="color: var(--primary-cyan); margin-bottom: 20px; text-align: center;">Purchase Astrons</h2>
        <div class="form-group">
            <label>Credits to Spend</label>
            <input type="number" id="creditsInput" min="1" step="1" value="10" oninput="updateAstrons()">
            <div class="info-text">Rate: <?php echo $astrons_per_credit; ?> Astrons = 1 Credit</div>
        </div>
        <div class="form-group">
            <label>You Will Receive</label>
            <input type="text" id="astronsOutput" readonly style="background: rgba(0,255,255,0.1);">
        </div>
        <button class="btn-buy" onclick="buyAstrons()">Buy Astrons</button>
    </div>
    
    <script>
        const astronsPerCredit = <?php echo $astrons_per_credit; ?>;
        const userCredits = <?php echo $user_credits; ?>;
        
        function updateAstrons() {
            const credits = parseInt(document.getElementById('creditsInput').value) || 0;
            const astrons = (credits * astronsPerCredit).toFixed(2);
            document.getElementById('astronsOutput').value = astrons + ' Astrons';
        }
        
        function buyAstrons() {
            const credits = parseInt(document.getElementById('creditsInput').value) || 0;
            
            if (credits <= 0) {
                alert('Please enter a valid amount');
                return;
            }
            
            if (credits > userCredits) {
                alert('Insufficient credits');
                return;
            }
            
            if (!confirm(`Buy ${(credits * astronsPerCredit).toFixed(2)} Astrons for ${credits} Credits?`)) {
                return;
            }
            
            fetch('bidding_api.php?action=buy_astrons', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'credits=' + credits
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Astrons purchased successfully!');
                    location.reload();
                } else {
                    alert(data.message || 'Failed to purchase Astrons');
                }
            });
        }
        
        updateAstrons();
    </script>
</body>
</html>

