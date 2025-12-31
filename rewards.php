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

// Get user's credits
$credits_stmt = $conn->prepare("SELECT credits FROM user_profile WHERE user_id = ?");
$credits_stmt->bind_param("i", $user_id);
$credits_stmt->execute();
$credits_result = $credits_stmt->get_result();
$user_profile = $credits_result->fetch_assoc();
$user_credits = intval($user_profile['credits'] ?? 0);
$credits_stmt->close();

// Get all active rewards and coupons
$rewards = $conn->query("
    SELECT r.*, 
           CASE WHEN r.is_sold = 1 THEN 1 
                WHEN r.expire_date IS NOT NULL AND r.expire_date < NOW() THEN 1 
                ELSE 0 END as is_unavailable
    FROM rewards r 
    WHERE r.is_active = 1 
    ORDER BY r.gift_type DESC, r.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rewards & Coupons - Space Games Hub</title>
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

        .header { text-align: center; margin-bottom: 40px; padding: 30px 0; }
        .header h1 { font-family: 'Orbitron', sans-serif; font-size: 2.5rem; color: var(--primary-cyan); margin-bottom: 10px; text-shadow: 0 0 20px rgba(0, 255, 255, 0.5); }
        .header p { color: rgba(255, 255, 255, 0.7); font-size: 1.1rem; }

        .credits-display { text-align: center; margin-bottom: 30px; padding: 20px; background: var(--card-bg); border: 2px solid var(--primary-cyan); border-radius: 15px; max-width: 400px; margin-left: auto; margin-right: auto; }
        .credits-display .label { color: rgba(255, 255, 255, 0.7); font-size: 0.9rem; margin-bottom: 5px; }
        .credits-display .value { font-family: 'Orbitron', sans-serif; font-size: 2rem; color: #ffd700; font-weight: 700; }

        .rewards-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 25px; max-width: 1400px; margin: 0 auto; }
        .reward-card { background: var(--card-bg); border: 2px solid rgba(0, 255, 255, 0.3); border-radius: 20px; padding: 25px; transition: all 0.3s ease; position: relative; overflow: hidden; }
        .reward-card:hover { transform: translateY(-5px); border-color: var(--primary-cyan); box-shadow: 0 10px 30px rgba(0, 255, 255, 0.3); }
        .reward-card.sold-out { opacity: 0.6; border-color: rgba(255, 0, 110, 0.5); }
        .reward-card.sold-out:hover { transform: none; }

        .reward-badge { position: absolute; top: 15px; right: 15px; padding: 6px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; font-family: 'Orbitron', sans-serif; }
        .badge-reward { background: rgba(0, 255, 255, 0.2); color: var(--primary-cyan); border: 1px solid var(--primary-cyan); }
        .badge-coupon { background: rgba(251, 191, 36, 0.2); color: #fbbf24; border: 1px solid #fbbf24; }
        .badge-sold { background: rgba(255, 0, 110, 0.2); color: #ff006e; border: 1px solid #ff006e; }

        .reward-icon { font-size: 3rem; text-align: center; margin-bottom: 15px; }
        .reward-name { font-family: 'Orbitron', sans-serif; font-size: 1.3rem; color: var(--primary-cyan); margin-bottom: 10px; text-align: center; }
        .reward-type { color: rgba(255, 255, 255, 0.6); font-size: 0.9rem; margin-bottom: 15px; text-align: center; }
        .reward-details { color: rgba(255, 255, 255, 0.8); margin-bottom: 15px; line-height: 1.6; min-height: 60px; }
        .reward-duration { color: var(--primary-purple); font-size: 0.9rem; margin-bottom: 10px; }
        .reward-cost { font-family: 'Orbitron', sans-serif; font-size: 1.5rem; color: #ffd700; text-align: center; margin: 15px 0; }
        .coupon-code { background: rgba(251, 191, 36, 0.1); border: 1px dashed #fbbf24; border-radius: 8px; padding: 10px; text-align: center; font-family: 'Courier New', monospace; color: #fbbf24; font-weight: 700; margin: 10px 0; }
        .expire-date { color: rgba(255, 255, 255, 0.5); font-size: 0.85rem; text-align: center; margin-top: 10px; }

        .purchase-btn { width: 100%; padding: 12px; background: linear-gradient(135deg, var(--primary-cyan), var(--primary-purple)); border: none; border-radius: 10px; color: white; font-family: 'Orbitron', sans-serif; font-weight: 700; font-size: 1rem; cursor: pointer; transition: all 0.3s ease; margin-top: 15px; }
        .purchase-btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0, 255, 255, 0.4); }
        .purchase-btn:disabled { background: rgba(255, 255, 255, 0.1); color: rgba(255, 255, 255, 0.3); cursor: not-allowed; transform: none; }

        .message { padding: 15px; border-radius: 10px; margin-bottom: 25px; text-align: center; font-weight: 700; }
        .msg-success { background: rgba(0, 255, 204, 0.1); border: 1px solid #00ffcc; color: #00ffcc; }
        .msg-error { background: rgba(255, 0, 110, 0.1); border: 1px solid #ff006e; color: #ff006e; }

        .back-btn { display: inline-block; margin-bottom: 20px; padding: 10px 20px; background: rgba(0, 255, 255, 0.1); border: 1px solid var(--primary-cyan); border-radius: 8px; color: var(--primary-cyan); text-decoration: none; font-family: 'Orbitron', sans-serif; font-weight: 700; transition: all 0.3s ease; }
        .back-btn:hover { background: var(--primary-cyan); color: black; }

        @media (max-width: 768px) {
            .rewards-grid { grid-template-columns: 1fr; }
            .header h1 { font-size: 1.8rem; }
        }
    </style>
</head>
<body>
    <div class="space-bg"></div>

    <a href="index.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Home</a>

    <div class="header">
        <h1><i class="fas fa-gift"></i> REWARDS & COUPONS</h1>
        <p>Purchase amazing rewards and exclusive coupons with your credits!</p>
    </div>

    <div class="credits-display">
        <div class="label">Your Credits</div>
        <div class="value" id="userCredits"><?php echo number_format($user_credits); ?> ⚡</div>
    </div>

    <?php if(isset($_GET['success'])): ?>
        <div class="message msg-success">✓ Purchase successful! Check your rewards.</div>
    <?php endif; ?>
    <?php if(isset($_GET['error'])): ?>
        <div class="message msg-error">✗ <?php echo htmlspecialchars($_GET['error']); ?></div>
    <?php endif; ?>

    <div class="rewards-grid">
        <?php if(empty($rewards)): ?>
            <div style="grid-column: 1 / -1; text-align: center; padding: 60px; color: rgba(255, 255, 255, 0.5);">
                <i class="fas fa-gift" style="font-size: 4rem; margin-bottom: 20px; opacity: 0.3;"></i>
                <p style="font-size: 1.2rem;">No rewards or coupons available at the moment.</p>
                <p style="margin-top: 10px;">Check back later for exciting offers!</p>
            </div>
        <?php else: ?>
            <?php foreach($rewards as $reward): 
                $is_unavailable = $reward['is_unavailable'] || ($reward['expire_date'] && strtotime($reward['expire_date']) < time());
                $can_purchase = !$is_unavailable && $user_credits >= $reward['credits_cost'];
            ?>
            <div class="reward-card <?php echo $is_unavailable ? 'sold-out' : ''; ?>">
                <span class="reward-badge <?php echo $is_unavailable ? 'badge-sold' : ($reward['gift_type'] === 'coupon' ? 'badge-coupon' : 'badge-reward'); ?>">
                    <?php echo $is_unavailable ? 'SOLD OUT' : strtoupper($reward['gift_type']); ?>
                </span>

                <div class="reward-icon">
                    <?php echo $reward['gift_type'] === 'coupon' ? '🎫' : '🎁'; ?>
                </div>

                <div class="reward-name"><?php echo htmlspecialchars($reward['gift_name']); ?></div>
                <div class="reward-type"><?php echo ucfirst($reward['gift_type']); ?></div>

                <?php if($reward['time_duration']): ?>
                    <div class="reward-duration"><i class="fas fa-clock"></i> <?php echo htmlspecialchars($reward['time_duration']); ?></div>
                <?php endif; ?>

                <?php if($reward['coupon_details']): ?>
                    <div class="reward-details"><?php echo nl2br(htmlspecialchars($reward['coupon_details'])); ?></div>
                <?php endif; ?>

                <?php if($reward['coupon_code']): ?>
                    <div class="coupon-code"><?php echo htmlspecialchars($reward['coupon_code']); ?></div>
                <?php endif; ?>

                <?php if($reward['expire_date']): ?>
                    <div class="expire-date">Expires: <?php echo date('d M Y H:i', strtotime($reward['expire_date'])); ?></div>
                <?php endif; ?>

                <div class="reward-cost"><?php echo number_format($reward['credits_cost']); ?> ⚡ Credits</div>

                <button class="purchase-btn" 
                        onclick="purchaseReward(<?php echo $reward['id']; ?>, <?php echo $reward['credits_cost']; ?>, <?php echo $is_unavailable ? 'true' : 'false'; ?>)"
                        <?php echo !$can_purchase ? 'disabled' : ''; ?>>
                    <?php if($is_unavailable): ?>
                        SOLD OUT
                    <?php elseif($user_credits < $reward['credits_cost']): ?>
                        INSUFFICIENT CREDITS
                    <?php else: ?>
                        PURCHASE NOW
                    <?php endif; ?>
                </button>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
        function purchaseReward(rewardId, creditsCost, isSoldOut) {
            if (isSoldOut) {
                alert('This reward/coupon is no longer available.');
                return;
            }

            if (!confirm(`Purchase this reward for ${creditsCost.toLocaleString()} credits?`)) {
                return;
            }

            // Disable button
            const btn = event.target;
            btn.disabled = true;
            btn.textContent = 'Processing...';

            fetch('purchase_reward.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    reward_id: rewardId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Purchase successful! ' + (data.message || ''));
                    window.location.href = 'rewards.php?success=1';
                } else {
                    alert('Error: ' + (data.message || 'Purchase failed'));
                    btn.disabled = false;
                    btn.textContent = 'PURCHASE NOW';
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
                btn.disabled = false;
                btn.textContent = 'PURCHASE NOW';
            });
        }
    </script>
</body>
</html>

