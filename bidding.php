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

// Get user Astrons balance
$user_astrons = $conn->query("SELECT astrons_balance FROM user_astrons WHERE user_id = $user_id")->fetch_assoc();
$astrons_balance = $user_astrons ? floatval($user_astrons['astrons_balance']) : 0;

// Get Astrons per credit and calculate credits per astron for display
$settings = $conn->query("SELECT astrons_per_credit FROM bidding_settings LIMIT 1")->fetch_assoc();
$astrons_per_credit = $settings ? floatval($settings['astrons_per_credit']) : 1.00;
// Calculate credits per astron (inverse) for display
$credits_per_astron = $astrons_per_credit > 0 ? (1.0 / $astrons_per_credit) : 1.00;

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Bidding - Space Games Hub</title>
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
        .bidding-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
        .bidding-card { background: var(--card-bg); border: 1px solid rgba(0, 255, 255, 0.3); border-radius: 15px; padding: 20px; position: relative; }
        .bidding-card.active { border-color: var(--primary-cyan); box-shadow: 0 0 20px rgba(0, 255, 255, 0.3); }
        .bidding-card.expired { opacity: 0.6; }
        .bidding-title { font-family: 'Orbitron', sans-serif; color: var(--primary-cyan); font-size: 1.2rem; margin-bottom: 10px; }
        .prize-amount { color: #FFD700; font-size: 1.5rem; font-weight: 900; margin: 10px 0; }
        .current-bid { color: var(--primary-purple); font-size: 1.2rem; margin: 10px 0; }
        .bid-input { width: 100%; padding: 10px; background: rgba(0,0,0,0.5); border: 1px solid var(--primary-cyan); border-radius: 8px; color: white; margin: 10px 0; }
        .btn-bid { width: 100%; padding: 12px; background: linear-gradient(135deg, var(--primary-cyan), var(--primary-purple)); border: none; border-radius: 8px; color: white; font-family: 'Orbitron', sans-serif; font-weight: 700; cursor: pointer; }
        .btn-bid:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0, 255, 255, 0.4); }
        .timer { color: #ff3333; font-weight: 700; margin: 10px 0; }
        .recent-bids { margin-top: 15px; font-size: 0.9rem; color: rgba(255,255,255,0.6); }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 10px 20px; background: rgba(0,255,255,0.1); border: 1px solid var(--primary-cyan); border-radius: 8px; color: var(--primary-cyan); text-decoration: none; }
    </style>
</head>
<body>
    <div class="space-bg"></div>
    <a href="index.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Home</a>
    
    <div class="header">
        <h1><i class="fas fa-gavel"></i> LIVE BIDDING</h1>
    </div>
    
    <div class="balance-card">
        <h2>Your Astrons Balance</h2>
        <div class="amount" id="astronsBalance"><?php echo number_format($astrons_balance, 2); ?></div>
        <div style="color: rgba(255,255,255,0.6); font-size: 0.9rem; margin-top: 10px;">
            Rate: 1 Astron = <?php echo number_format($credits_per_astron, 2); ?> Credits
        </div>
        <a href="buy_astrons.php" style="color: var(--primary-cyan); text-decoration: none; margin-top: 10px; display: inline-block; font-weight: 700;">Buy More Astrons with Credits</a>
    </div>
    
    <div class="bidding-grid" id="biddingGrid">
        <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: rgba(255,255,255,0.5);">
            <i class="fas fa-spinner fa-spin" style="font-size: 2rem;"></i>
            <p>Loading active biddings...</p>
        </div>
    </div>
    
    <script>
        const astronsBalance = <?php echo $astrons_balance; ?>;
        const astronsPerCredit = <?php echo $astrons_per_credit; ?>;
        
        function updateBiddings() {
            fetch('bidding_api.php?action=get_active_biddings')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const grid = document.getElementById('biddingGrid');
                        if (data.items.length === 0) {
                            grid.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: rgba(255,255,255,0.5);">No active biddings at the moment.</div>';
                            return;
                        }
                        
                        grid.innerHTML = data.items.map(item => {
                            const endTime = new Date(item.end_time).getTime();
                            const now = Date.now();
                            const expired = endTime < now;
                            
                            return `
                                <div class="bidding-card ${expired ? 'expired' : 'active'}" data-id="${item.id}">
                                    <div class="bidding-title">${item.title}</div>
                                    <div class="prize-amount">Prize: ₹${parseFloat(item.prize_amount).toLocaleString('en-IN', {minimumFractionDigits: 2})}</div>
                                    <div class="current-bid">Current Bid: <span class="current-bid-amount">${parseFloat(item.current_bid).toFixed(2)}</span> Astrons</div>
                                    <div class="timer" data-end="${item.end_time}">Time Left: <span class="countdown"></span></div>
                                    ${!expired ? `
                                        <input type="number" class="bid-input" placeholder="Min: ${(parseFloat(item.current_bid) + parseFloat(item.bid_increment)).toFixed(2)} Astrons" step="${item.bid_increment}" min="${parseFloat(item.current_bid) + parseFloat(item.bid_increment)}">
                                        <button class="btn-bid" onclick="placeBid(${item.id}, this)">Place Bid</button>
                                    ` : '<div style="color: #ff3333; text-align: center; margin-top: 10px;">Bidding Ended</div>'}
                                    <div class="recent-bids">Total Bids: ${item.total_bids}</div>
                                </div>
                            `;
                        }).join('');
                        
                        // Update countdowns
                        updateCountdowns();
                    }
                })
                .catch(err => console.error('Error:', err));
        }
        
        function updateCountdowns() {
            document.querySelectorAll('.timer').forEach(timer => {
                const endTime = new Date(timer.dataset.end).getTime();
                const now = Date.now();
                const diff = endTime - now;
                
                if (diff <= 0) {
                    timer.querySelector('.countdown').textContent = 'Ended';
                    timer.closest('.bidding-card').classList.add('expired');
                } else {
                    const hours = Math.floor(diff / 3600000);
                    const minutes = Math.floor((diff % 3600000) / 60000);
                    const seconds = Math.floor((diff % 60000) / 1000);
                    timer.querySelector('.countdown').textContent = `${hours}h ${minutes}m ${seconds}s`;
                }
            });
        }
        
        function placeBid(itemId, btn) {
            const card = btn.closest('.bidding-card');
            const input = card.querySelector('.bid-input');
            const bidAmount = parseFloat(input.value);
            
            if (!bidAmount || bidAmount <= 0) {
                alert('Please enter a valid bid amount');
                return;
            }
            
            if (bidAmount > astronsBalance) {
                alert('Insufficient Astrons balance');
                return;
            }
            
            btn.disabled = true;
            btn.textContent = 'Placing Bid...';
            
            const formData = new FormData();
            formData.append('bidding_id', itemId);
            formData.append('bid_amount', bidAmount);
            
            fetch('bidding_api.php?action=place_bid', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Bid placed successfully!');
                    input.value = '';
                    updateBiddings();
                    updateBalance();
                } else {
                    alert(data.message || 'Failed to place bid');
                }
                btn.disabled = false;
                btn.textContent = 'Place Bid';
            })
            .catch(err => {
                alert('Error placing bid');
                btn.disabled = false;
                btn.textContent = 'Place Bid';
            });
        }
        
        function updateBalance() {
            fetch('bidding_api.php?action=get_user_astrons')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('astronsBalance').textContent = parseFloat(data.balance).toFixed(2);
                    }
                });
        }
        
        // Initial load
        updateBiddings();
        
        // Update every 5 seconds
        setInterval(updateBiddings, 5000);
        setInterval(updateCountdowns, 1000);
        setInterval(updateBalance, 10000);
    </script>
</body>
</html>

