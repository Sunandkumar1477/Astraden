<?php
session_start();
require_once 'security_headers.php';
require_once 'check_user_session.php';
require_once 'connection.php';

// Set timezone to India (IST)
date_default_timezone_set('Asia/Kolkata');

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
    <link rel="stylesheet" href="css/bidding.css">
    <style>
        /* Hide content until fully loaded - no loading spinner visible */
        .container {
            display: none;
        }
        
        .container.loaded {
            display: block;
        }
        
        .bidding-grid {
            display: none;
        }
        
        .bidding-grid.loaded {
            display: grid;
        }
    </style>
</head>
<body>
    <div class="space-bg"></div>
    <div class="container" id="mainContainer">
        <a href="index.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Home</a>
        
        <div class="header">
            <h1><i class="fas fa-gavel"></i> LIVE BIDDING</h1>
        </div>
        
        <div class="balance-card">
            <h2>Your Astrons Balance</h2>
            <div class="amount" id="astronsBalance"><?php echo number_format($astrons_balance, 2); ?></div>
            <div class="rate-info">
                Rate: 1 Astron = <?php echo number_format($credits_per_astron, 2); ?> Credits
            </div>
            <a href="buy_astrons.php" class="buy-link">
                <i class="fas fa-shopping-cart"></i> Buy More Astrons with Credits
            </a>
        </div>
        
        <div class="bidding-grid" id="biddingGrid">
            <!-- Content will be loaded here without showing loading state -->
        </div>
    </div>
    
    <script>
        const astronsBalance = <?php echo $astrons_balance; ?>;
        const astronsPerCredit = <?php echo $astrons_per_credit; ?>;
        const currentUserId = <?php echo $user_id; ?>;
        
        // Show container only when content is ready
        function showContent() {
            const container = document.getElementById('mainContainer');
            const grid = document.getElementById('biddingGrid');
            if (container) container.classList.add('loaded');
            if (grid) grid.classList.add('loaded');
        }
        
        function updateBiddings() {
            const grid = document.getElementById('biddingGrid');
            if (!grid) {
                return;
            }
            
            // Remove debug parameter to save data
            fetch('bidding_api.php?action=get_active_biddings')
                .then(r => {
                    if (!r.ok) {
                        throw new Error('Network response was not ok: ' + r.status);
                    }
                    return r.json();
                })
                .then(data => {
                    
                    // Show container now that we have response (even if error)
                    showContent();
                    
                    if (!data.success) {
                        let errorHtml = '<div class="error-state active">';
                        errorHtml += '<i class="fas fa-exclamation-triangle"></i><br>';
                        errorHtml += '<strong style="font-size: 1.3rem; margin: 15px 0; display: block;">Error loading biddings</strong>';
                        errorHtml += '<p style="color: var(--text-secondary); margin: 10px 0;">' + (data.message || 'Unknown error') + '</p>';
                        errorHtml += '<button onclick="updateBiddings()" class="retry-btn">Retry</button>';
                        errorHtml += '</div>';
                        grid.innerHTML = errorHtml;
                        return;
                    }
                    
                    // Show container now that we have response
                    showContent();
                    
                    if (!data.items || data.items.length === 0) {
                        let message = '<div class="empty-state active">';
                        message += '<i class="fas fa-inbox"></i><br>';
                        message += '<strong style="font-size: 1.3rem; margin: 15px 0; display: block;">No active biddings at the moment</strong>';
                        message += '<p style="color: var(--text-secondary); font-size: 1.1rem;">Check back later for new bidding opportunities!</p>';
                        message += '</div>';
                        grid.innerHTML = message;
                        return;
                    }
                    
                    // Render bidding items (newest first - already sorted by API)
                    // Note: API returns times in IST, JavaScript Date will parse them as local time
                    // We need to treat them as IST
                    grid.innerHTML = data.items.map(item => {
                        try {
                            // Parse IST times (API sends in IST format: Y-m-d H:i:s)
                            // Create date assuming IST timezone
                            const parseISTTime = (timeStr) => {
                                if (!timeStr) return 0;
                                try {
                                    // Replace space with T for ISO format, add IST offset (+05:30)
                                    const istTime = timeStr.replace(' ', 'T') + '+05:30';
                                    const parsed = new Date(istTime).getTime();
                                    // Check if valid date
                                    if (isNaN(parsed)) {
                                        return 0;
                                    }
                                    return parsed;
                                } catch (e) {
                                    return 0;
                                }
                            };
                            
                            const endTime = parseISTTime(item.end_time);
                            const startTime = parseISTTime(item.start_time);
                            const now = Date.now();
                            const expired = endTime > 0 && endTime < now;
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
                                    <input type="number" class="bid-input" placeholder="Bidding not started yet" disabled>
                                    <button class="btn-bid" disabled>Bidding Not Started</button>
                                ` : expired ? `
                                    <div class="expired-message">
                                        <i class="fas fa-hourglass-end"></i>
                                        <p>Bidding has ended</p>
                                    </div>
                                    <button class="btn-bid" disabled>
                                        <i class="fas fa-lock"></i> Bidding Ended
                                    </button>
                                ` : `
                                    <div class="bid-amount-display">
                                        <div class="label">Bid Amount Per Click:</div>
                                        <div class="amount">${parseFloat(item.bid_increment).toFixed(2)} Astrons</div>
                                    </div>
                                    <button class="btn-bid" onclick="placeBid(${item.id}, this, ${item.bid_increment})" data-item-id="${item.id}" data-end-time="${item.end_time}">
                                        <i class="fas fa-gavel"></i> Place Bid (${parseFloat(item.bid_increment).toFixed(2)} Astrons)
                                    </button>
                                `}
                                <div class="recent-bids">
                                    <i class="fas fa-gavel"></i> Total Bids: ${item.total_bids}
                                </div>
                                ${item.top_bidders && item.top_bidders.length > 0 ? `
                                    <div class="top-bidders-section">
                                        <div class="top-bidders-title">
                                            <i class="fas fa-trophy"></i> Top 5 Bidders
                                        </div>
                                        <div class="top-bidders-list">
                                            ${item.top_bidders.map((bidder, idx) => `
                                                <div class="bidder-rank ${bidder.user_id == currentUserId ? 'your-bid' : ''}">
                                                    <span class="rank-number">${bidder.rank}</span>
                                                    <span class="bidder-name">${bidder.username || 'Unknown'}</span>
                                                    <span class="bidder-amount">${parseFloat(bidder.bid_amount).toFixed(2)} Astrons</span>
                                                </div>
                                            `).join('')}
                                        </div>
                                        ${item.user_rank && item.user_rank > 5 ? `
                                            <div class="your-rank">
                                                <i class="fas fa-user"></i> Your Rank: #${item.user_rank} (${parseFloat(item.user_bid_amount || 0).toFixed(2)} Astrons)
                                            </div>
                                        ` : ''}
                                    </div>
                                ` : ''}
                            </div>
                        `;
                        } catch (e) {
                            // If there's an error rendering an item, return empty string
                            return '';
                        }
                    }).filter(html => html !== '').join('');
                    
                    // Update countdowns
                    updateCountdowns();
                })
                .catch(err => {
                    // Show container even on error so user can see the error
                    showContent();
                    
                    const grid = document.getElementById('biddingGrid');
                    if (grid) {
                        let errorHtml = '<div class="error-state active">';
                        errorHtml += '<i class="fas fa-exclamation-circle"></i><br>';
                        errorHtml += '<strong style="font-size: 1.3rem; margin: 15px 0; display: block;">Error loading biddings</strong>';
                        errorHtml += '<p style="color: var(--text-secondary); margin: 10px 0;">' + err.message + '</p>';
                        errorHtml += '<button onclick="updateBiddings()" class="retry-btn">Retry</button>';
                        errorHtml += '</div>';
                        grid.innerHTML = errorHtml;
                    }
                });
        }
        
        function updateCountdowns() {
            try {
                // Helper function to parse IST time string
                const parseISTTime = (timeStr) => {
                    if (!timeStr) return 0;
                    try {
                        // Replace space with T for ISO format, add IST offset (+05:30)
                        const istTime = timeStr.replace(' ', 'T') + '+05:30';
                        const parsed = new Date(istTime).getTime();
                        // Check if valid date
                        if (isNaN(parsed)) {
                            return 0;
                        }
                        return parsed;
                    } catch (e) {
                        return 0;
                    }
                };
                
                // Update end countdowns
                document.querySelectorAll('.timer[data-end]').forEach(timer => {
                    try {
                        const endTime = parseISTTime(timer.dataset.end);
                        if (endTime === 0) return; // Skip invalid times
                        
                        const now = Date.now();
                        const diff = endTime - now;
                        
                        if (diff <= 0) {
                            const countdownEl = timer.querySelector('.countdown');
                            if (countdownEl) {
                                countdownEl.textContent = 'Ended';
                            }
                            const card = timer.closest('.bidding-card');
                            if (card) {
                                card.classList.add('expired');
                                // Disable bid button immediately when time expires
                                const bidBtn = card.querySelector('.btn-bid:not([disabled])');
                                if (bidBtn) {
                                    bidBtn.disabled = true;
                                    bidBtn.innerHTML = '<i class="fas fa-lock"></i> Bidding Ended';
                                    // Update card content to show expired message
                                    const bidDisplay = card.querySelector('.bid-amount-display');
                                    if (bidDisplay) {
                                        bidDisplay.outerHTML = '<div class="expired-message"><i class="fas fa-hourglass-end"></i><p>Bidding has ended</p></div>';
                                    }
                                }
                            }
                        } else {
                            const hours = Math.floor(diff / 3600000);
                            const minutes = Math.floor((diff % 3600000) / 60000);
                            const seconds = Math.floor((diff % 60000) / 1000);
                            const countdownEl = timer.querySelector('.countdown');
                            if (countdownEl) {
                                countdownEl.textContent = `${hours}h ${minutes}m ${seconds}s`;
                            }
                        }
                    } catch (e) {
                        // Skip this timer if there's an error
                    }
                });
                
                // Update start countdowns
                document.querySelectorAll('.timer[data-start]').forEach(timer => {
                    try {
                        const startTime = parseISTTime(timer.dataset.start);
                        if (startTime === 0) return; // Skip invalid times
                        
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
                    } catch (e) {
                        // Skip this timer if there's an error
                    }
                });
            } catch (e) {
                // Silently handle errors in countdown updates
            }
        }
        
        function placeBid(itemId, btn, bidAmount) {
            const card = btn.closest('.bidding-card');
            
            // Use the fixed bid amount per click (bid_increment)
            if (!bidAmount || bidAmount <= 0) {
                return;
            }
            
            // Check balance silently
            if (bidAmount > astronsBalance) {
                return;
            }
            
            // Disable button immediately to prevent double-clicking
            btn.disabled = true;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Placing...';
            
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
                    // Update immediately without alert
                    updateBiddings();
                    updateBalance();
                } else {
                    // Re-enable button on error
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            })
            .catch(err => {
                // Re-enable button on error
                btn.disabled = false;
                btn.innerHTML = originalText;
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
        
        // Initial load - wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                updateBiddings();
            });
        } else {
            updateBiddings();
        }
        
        // Update bidding items every 5 seconds (internal updates, no visible loading)
        let updateInterval = null;
        setTimeout(() => {
            updateInterval = setInterval(updateBiddings, 5000);
        }, 5000);
        
        // Update countdowns every second for live time updates
        setInterval(updateCountdowns, 1000);
        
        // Update balance every 10 seconds
        setInterval(updateBalance, 10000);
    </script>
</body>
</html>
