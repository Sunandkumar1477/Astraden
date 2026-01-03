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
        .bidding-card.expired { opacity: 0.7; border-color: rgba(255,255,255,0.2); }
        
        /* Professional status badges and messages */
        .status-badge { 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            gap: 8px; 
            padding: 12px 20px; 
            margin: 15px 0; 
            border-radius: 8px; 
            font-family: 'Orbitron', sans-serif; 
            font-weight: 700; 
            font-size: 0.9rem; 
            text-transform: uppercase; 
            letter-spacing: 1px;
        }
        .status-badge.completed { 
            background: linear-gradient(135deg, rgba(0, 200, 0, 0.2), rgba(0, 150, 0, 0.2)); 
            border: 2px solid #00cc00; 
            color: #00ff88; 
            box-shadow: 0 0 15px rgba(0, 255, 136, 0.3);
        }
        .status-badge.completed i { 
            font-size: 1.2rem; 
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }
        .winner-info { 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            gap: 8px; 
            padding: 10px; 
            margin: 10px 0; 
            background: rgba(255, 215, 0, 0.1); 
            border: 1px solid #FFD700; 
            border-radius: 8px; 
            color: #FFD700; 
            font-weight: 700;
        }
        .winner-info i { 
            font-size: 1.1rem; 
            color: #FFD700;
        }
        .completed-message, .expired-message, .not-started-message { 
            text-align: center; 
            padding: 15px; 
            margin: 15px 0; 
            border-radius: 8px; 
            font-size: 0.95rem;
        }
        .completed-message { 
            background: rgba(0, 200, 0, 0.1); 
            border: 1px solid #00cc00; 
            color: #00ff88;
        }
        .completed-message i { 
            display: block; 
            font-size: 2rem; 
            margin-bottom: 8px; 
            color: #00ff88;
        }
        .completed-message p { 
            margin: 0; 
            font-weight: 500;
        }
        .expired-message { 
            background: rgba(255, 170, 0, 0.1); 
            border: 1px solid #ffaa00; 
            color: #ffcc66;
        }
        .expired-message i { 
            display: block; 
            font-size: 2rem; 
            margin-bottom: 8px; 
            color: #ffaa00;
        }
        .expired-message p { 
            margin: 0; 
            font-weight: 500;
        }
        .not-started-message { 
            background: rgba(255, 170, 0, 0.1); 
            border: 1px solid #ffaa00; 
            color: #ffcc66;
        }
        .not-started-message i { 
            display: block; 
            font-size: 2rem; 
            margin-bottom: 8px; 
            color: #ffaa00;
        }
        .not-started-message p { 
            margin: 0; 
            font-weight: 500;
        }
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
            const grid = document.getElementById('biddingGrid');
            if (!grid) {
                console.error('Bidding grid element not found');
                return;
            }
            
            // Show loading state
            grid.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: rgba(255,255,255,0.5);"><i class="fas fa-spinner fa-spin" style="font-size: 2rem;"></i><p>Loading active biddings...</p></div>';
            
            fetch('bidding_api.php?action=get_active_biddings' + (window.location.search.includes('debug') ? '&debug=1' : ''))
                .then(r => {
                    console.log('Response status:', r.status);
                    if (!r.ok) {
                        throw new Error('Network response was not ok: ' + r.status);
                    }
                    return r.json();
                })
                .then(data => {
                    console.log('Bidding API Response:', data);
                    console.log('Items count:', data.items ? data.items.length : 0);
                    
                    if (!data.success) {
                        let errorMsg = '<div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #ff3333;">';
                        errorMsg += '<i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 10px;"></i><br>';
                        errorMsg += 'Error loading biddings: ' + (data.message || 'Unknown error');
                        errorMsg += '</div>';
                        grid.innerHTML = errorMsg;
                        console.error('Bidding API Error:', data);
                        if (data.debug) {
                            console.log('Debug Info:', data.debug);
                            // Show debug info on page if debug mode
                            if (window.location.search.includes('debug')) {
                                grid.innerHTML += '<div style="grid-column: 1 / -1; padding: 20px; background: rgba(255,255,0,0.1); border: 1px solid yellow; margin-top: 20px; font-size: 0.9rem;"><pre>' + JSON.stringify(data.debug, null, 2) + '</pre></div>';
                            }
                        }
                        return;
                    }
                    
                    if (!data.items || data.items.length === 0) {
                        let message = 'No active biddings at the moment. Check back later!';
                        if (data.debug) {
                            console.log('Debug Info:', data.debug);
                            if (data.debug.total_items > 0) {
                                message += '<br><small style="color: rgba(255,255,255,0.4);">Total items in database: ' + data.debug.total_items + '</small>';
                            }
                            if (data.debug.bidding_enabled === false) {
                                message += '<br><small style="color: #ffaa00;">Bidding system is disabled in admin settings.</small>';
                            }
                            // Show debug info on page if debug mode
                            if (window.location.search.includes('debug')) {
                                message += '<div style="margin-top: 20px; padding: 20px; background: rgba(255,255,0,0.1); border: 1px solid yellow; text-align: left; font-size: 0.9rem;"><strong>Debug Info:</strong><pre>' + JSON.stringify(data.debug, null, 2) + '</pre></div>';
                            }
                        }
                        grid.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: rgba(255,255,255,0.5);">' + message + '</div>';
                        return;
                    }
                        
                        grid.innerHTML = data.items.map(item => {
                            const endTime = new Date(item.end_time).getTime();
                            const startTime = item.start_time ? new Date(item.start_time).getTime() : 0;
                            const now = Date.now();
                            const expired = endTime < now;
                            const notStarted = startTime > 0 && startTime > now;
                            const isCompleted = item.is_completed == 1 || item.is_completed === true;
                            
                            return `
                                <div class="bidding-card ${expired || isCompleted ? 'expired' : 'active'}" data-id="${item.id}">
                                    <div class="bidding-title">${item.title}</div>
                                    <div class="prize-amount">Prize: ₹${parseFloat(item.prize_amount).toLocaleString('en-IN', {minimumFractionDigits: 2})}</div>
                                    <div class="current-bid">${isCompleted ? 'Final Bid' : 'Current Bid'}: <span class="current-bid-amount">${parseFloat(item.current_bid).toFixed(2)}</span> Astrons</div>
                                    ${isCompleted ? `
                                        <div class="status-badge completed">
                                            <i class="fas fa-check-circle"></i>
                                            <span>BIDDING CLOSED</span>
                                        </div>
                                        ${item.current_bidder_name ? `
                                            <div class="winner-info">
                                                <i class="fas fa-trophy"></i>
                                                <span>Winner: <strong>${item.current_bidder_name}</strong></span>
                                            </div>
                                        ` : ''}
                                    ` : notStarted ? `
                                        <div class="timer" data-start="${item.start_time}">Starts in: <span class="countdown-start"></span></div>
                                    ` : `
                                        <div class="timer" data-end="${item.end_time}">Time Left: <span class="countdown"></span></div>
                                    `}
                                    ${isCompleted ? `
                                        <div class="completed-message">
                                            <i class="fas fa-lock"></i>
                                            <p>This auction has been completed and closed.</p>
                                        </div>
                                    ` : expired && !isCompleted ? `
                                        <div class="expired-message">
                                            <i class="fas fa-hourglass-end"></i>
                                            <p>Bidding has ended. Awaiting completion.</p>
                                        </div>
                                    ` : notStarted ? `
                                        <div class="not-started-message">
                                            <i class="fas fa-clock"></i>
                                            <p>Bidding will start soon</p>
                                        </div>
                                        <input type="number" class="bid-input" placeholder="Bidding not started yet" disabled style="opacity: 0.5;">
                                        <button class="btn-bid" disabled style="opacity: 0.5; cursor: not-allowed;">Bidding Not Started</button>
                                    ` : `
                                        <input type="number" class="bid-input" placeholder="Min: ${(parseFloat(item.current_bid) + parseFloat(item.bid_increment)).toFixed(2)} Astrons" step="${item.bid_increment}" min="${parseFloat(item.current_bid) + parseFloat(item.bid_increment)}">
                                        <button class="btn-bid" onclick="placeBid(${item.id}, this)">Place Bid</button>
                                    `}
                                    <div class="recent-bids">Total Bids: ${item.total_bids}</div>
                                </div>
                            `;
                        }).join('');
                        
                        // Update countdowns
                        updateCountdowns();
                    }
                })
                .catch(err => {
                    console.error('Error loading biddings:', err);
                    const grid = document.getElementById('biddingGrid');
                    if (grid) {
                        let errorHtml = '<div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #ff3333;">';
                        errorHtml += '<i class="fas fa-exclamation-circle" style="font-size: 2rem; margin-bottom: 10px;"></i><br>';
                        errorHtml += '<strong>Error loading biddings</strong><br>';
                        errorHtml += '<small style="color: rgba(255,255,255,0.6);">' + err.message + '</small><br><br>';
                        errorHtml += '<button onclick="updateBiddings()" style="padding: 10px 20px; background: var(--primary-cyan); border: none; border-radius: 8px; color: white; cursor: pointer; font-weight: 700;">Retry</button>';
                        errorHtml += '</div>';
                        grid.innerHTML = errorHtml;
                    }
                });
        }
        
        function updateCountdowns() {
            // Update end countdowns
            document.querySelectorAll('.timer[data-end]').forEach(timer => {
                const endTime = new Date(timer.dataset.end).getTime();
                const now = Date.now();
                const diff = endTime - now;
                
                if (diff <= 0) {
                    const countdownEl = timer.querySelector('.countdown');
                    if (countdownEl) {
                        countdownEl.textContent = 'Ended';
                    }
                    timer.closest('.bidding-card').classList.add('expired');
                } else {
                    const hours = Math.floor(diff / 3600000);
                    const minutes = Math.floor((diff % 3600000) / 60000);
                    const seconds = Math.floor((diff % 60000) / 1000);
                    const countdownEl = timer.querySelector('.countdown');
                    if (countdownEl) {
                        countdownEl.textContent = `${hours}h ${minutes}m ${seconds}s`;
                    }
                }
            });
            
            // Update start countdowns
            document.querySelectorAll('.timer[data-start]').forEach(timer => {
                const startTime = new Date(timer.dataset.start).getTime();
                const now = Date.now();
                const diff = startTime - now;
                
                if (diff <= 0) {
                    // Bidding has started, reload to show active bidding
                    updateBiddings();
                } else {
                    const days = Math.floor(diff / 86400000);
                    const hours = Math.floor((diff % 86400000) / 3600000);
                    const minutes = Math.floor((diff % 3600000) / 60000);
                    const seconds = Math.floor((diff % 60000) / 1000);
                    const countdownEl = timer.querySelector('.countdown-start');
                    if (countdownEl) {
                        if (days > 0) {
                            countdownEl.textContent = `${days}d ${hours}h ${minutes}m`;
                        } else {
                            countdownEl.textContent = `${hours}h ${minutes}m ${seconds}s`;
                        }
                    }
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

