<?php
session_start();
require_once 'connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get user's Fluxon (total score from games, including shop deductions)
// Shop deductions are negative scores, so we sum all scores (positive and negative)
$fluxon_stmt = $conn->prepare("SELECT COALESCE(SUM(score), 0) as total_fluxon FROM game_leaderboard WHERE user_id = ?");
$fluxon_stmt->bind_param("i", $user_id);
$fluxon_stmt->execute();
$fluxon_result = $fluxon_stmt->get_result();
$fluxon_data = $fluxon_result->fetch_assoc();
$total_fluxon = intval($fluxon_data['total_fluxon'] ?? 0);
// Ensure Fluxon is never negative
if ($total_fluxon < 0) $total_fluxon = 0;
$fluxon_stmt->close();

// Ensure shop_pricing table exists
$conn->query("
    CREATE TABLE IF NOT EXISTS shop_pricing (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        fluxon_amount INT(11) NOT NULL,
        astrons_reward INT(11) NOT NULL,
        claim_type VARCHAR(50) NOT NULL,
        is_active TINYINT(1) DEFAULT 1,
        display_order INT(11) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_fluxon (fluxon_amount)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Get prices from database
$prices_result = $conn->query("SELECT * FROM shop_pricing WHERE is_active = 1 ORDER BY display_order ASC LIMIT 3");
$shop_prices = [];
if ($prices_result && $prices_result->num_rows > 0) {
    $shop_prices = $prices_result->fetch_all(MYSQLI_ASSOC);
} else {
    // Default prices if none set
    $shop_prices = [
        ['id' => 1, 'fluxon_amount' => 5000, 'astrons_reward' => 10, 'claim_type' => 'Basic Access'],
        ['id' => 2, 'fluxon_amount' => 7500, 'astrons_reward' => 20, 'claim_type' => 'Standard Pack'],
        ['id' => 3, 'fluxon_amount' => 10000, 'astrons_reward' => 30, 'claim_type' => 'Elite Reserve']
    ];
}

// Get user's Astrons
$credits_stmt = $conn->prepare("SELECT credits FROM user_profile WHERE user_id = ?");
$credits_stmt->bind_param("i", $user_id);
$credits_stmt->execute();
$credits_result = $credits_stmt->get_result();
$credits_data = $credits_result->fetch_assoc();
$user_astrons = intval($credits_data['credits'] ?? 0);
$credits_stmt->close();

// Handle purchase - Check if it's an AJAX request
// Check multiple ways to detect AJAX requests
$is_ajax = (
    (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') ||
    (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
    (!empty($_GET['ajax']) && $_GET['ajax'] == '1')
);
$message = '';
$error = '';
$purchase_success = false;
$new_fluxon = $total_fluxon;
$new_astrons = $user_astrons;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purchase_item'])) {
    $item_id = intval($_POST['item_id'] ?? 0);
    $item_cost = intval($_POST['item_cost'] ?? 0);
    $item_astrons = intval($_POST['item_astrons'] ?? 0);
    
    if ($item_id > 0 && $item_cost > 0 && $item_astrons > 0) {
        // Re-check Fluxon balance before transaction (in case it changed)
        $fluxon_check_stmt = $conn->prepare("SELECT COALESCE(SUM(score), 0) as total_fluxon FROM game_leaderboard WHERE user_id = ?");
        $fluxon_check_stmt->bind_param("i", $user_id);
        $fluxon_check_stmt->execute();
        $fluxon_check_result = $fluxon_check_stmt->get_result();
        $fluxon_check_data = $fluxon_check_result->fetch_assoc();
        $current_fluxon = intval($fluxon_check_data['total_fluxon'] ?? 0);
        if ($current_fluxon < 0) $current_fluxon = 0;
        $fluxon_check_stmt->close();
        
        if ($current_fluxon >= $item_cost) {
            // Start transaction for atomic operation
            $conn->begin_transaction();
            
            try {
                // Step 1: Add Astrons to user profile (credits column)
                $update_stmt = $conn->prepare("UPDATE user_profile SET credits = credits + ? WHERE user_id = ?");
                $update_stmt->bind_param("ii", $item_astrons, $user_id);
                
                if (!$update_stmt->execute()) {
                    throw new Exception("Failed to update Astrons");
                }
                
                $affected_rows = $update_stmt->affected_rows;
                $update_stmt->close();
                
                // Verify Astrons were added
                $verify_astrons_stmt = $conn->prepare("SELECT credits FROM user_profile WHERE user_id = ?");
                $verify_astrons_stmt->bind_param("i", $user_id);
                $verify_astrons_stmt->execute();
                $verify_astrons_result = $verify_astrons_stmt->get_result();
                $verify_astrons_data = $verify_astrons_result->fetch_assoc();
                $new_astrons = intval($verify_astrons_data['credits'] ?? 0);
                $verify_astrons_stmt->close();
                
                // Step 2: Deduct Fluxon by inserting a negative score entry
                // This represents the Fluxon spent (negative score will reduce total)
                $deduct_stmt = $conn->prepare("INSERT INTO game_leaderboard (user_id, game_name, score, credits_used, game_mode, created_at) VALUES (?, 'shop-purchase', ?, 0, 'credits', NOW())");
                $negative_score = -$item_cost; // Negative score to deduct Fluxon
                $deduct_stmt->bind_param("ii", $user_id, $negative_score);
                
                if (!$deduct_stmt->execute()) {
                    throw new Exception("Failed to deduct Fluxon");
                }
                
                $deduct_stmt->close();
                
                // Step 3: Recalculate and verify total Fluxon after deduction
                $fluxon_recalc_stmt = $conn->prepare("SELECT COALESCE(SUM(score), 0) as total_fluxon FROM game_leaderboard WHERE user_id = ?");
                $fluxon_recalc_stmt->bind_param("i", $user_id);
                $fluxon_recalc_stmt->execute();
                $fluxon_recalc_result = $fluxon_recalc_stmt->get_result();
                $fluxon_recalc_data = $fluxon_recalc_result->fetch_assoc();
                $new_fluxon = intval($fluxon_recalc_data['total_fluxon'] ?? 0);
                if ($new_fluxon < 0) $new_fluxon = 0;
                $fluxon_recalc_stmt->close();
                
                // Verify the deduction worked correctly
                $expected_fluxon = $current_fluxon - $item_cost;
                if ($new_fluxon != $expected_fluxon && $new_fluxon != max(0, $expected_fluxon)) {
                    throw new Exception("Fluxon deduction verification failed");
                }
                
                // Commit transaction - all operations successful
                $conn->commit();
                
                // Update variables for display
                $total_fluxon = $new_fluxon;
                $user_astrons = $new_astrons;
                $purchase_success = true;
                $message = "Nexus Link Established! Claimed {$item_astrons} Astrons.";
                
                // If AJAX request, return JSON response
                if ($is_ajax) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'message' => $message,
                        'new_fluxon' => $new_fluxon,
                        'new_astrons' => $new_astrons,
                        'fluxon_deducted' => $item_cost,
                        'astrons_added' => $item_astrons
                    ]);
                    $conn->close();
                    exit;
                }
                
            } catch (Exception $e) {
                // Rollback on any error
                $conn->rollback();
                $error = "Transmission Failure: " . $e->getMessage() . ". Please try again.";
                error_log("Shop purchase error for user {$user_id}: " . $e->getMessage());
                
                // If AJAX request, return JSON error
                if ($is_ajax) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'message' => $error
                    ]);
                    $conn->close();
                    exit;
                }
            }
        } else {
            $error = "Insufficient Fluxon Energy! You have " . number_format($current_fluxon) . " Fluxon, but need " . number_format($item_cost) . ".";
            
            // If AJAX request, return JSON error
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => $error
                ]);
                $conn->close();
                exit;
            }
        }
    } else {
        $error = "Invalid purchase request. Please try again.";
        
        // If AJAX request, return JSON error
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $error
            ]);
            $conn->close();
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Fluxon Shop - Space Hub</title>
    <link rel="icon" type="image/svg+xml" href="Alogo.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --cyan: #00ffff;
            --purple: #9d4edd;
            --gold: #FFD700;
            --bg-dark: #050508;
            --card-bg: rgba(15, 15, 25, 0.85);
            --neon-glow: 0 0 15px rgba(0, 255, 255, 0.4);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: 'Rajdhani', sans-serif;
            background: var(--bg-dark);
            color: #fff;
            min-height: 100vh;
            padding-bottom: 50px;
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(157, 78, 221, 0.1) 0%, transparent 40%),
                radial-gradient(circle at 90% 80%, rgba(0, 255, 255, 0.08) 0%, transparent 40%);
            background-attachment: fixed;
        }

        /* --- NAVIGATION --- */
        .nav-bar {
            max-width: 1000px;
            margin: 15px auto;
            padding: 0 20px;
            display: flex;
            justify-content: flex-start;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--cyan);
            border-radius: 50px;
            color: var(--cyan);
            text-decoration: none;
            font-weight: 700;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: 0.3s ease;
        }

        .back-btn:hover {
            background: rgba(0, 255, 255, 0.15);
            box-shadow: var(--neon-glow);
        }

        /* --- HEADER --- */
        .shop-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .shop-header {
            text-align: center;
            margin: 20px 0 40px;
        }

        .shop-header h1 {
            font-family: 'Orbitron', sans-serif;
            font-size: clamp(1.8rem, 8vw, 3rem);
            color: var(--cyan);
            text-transform: uppercase;
            letter-spacing: 4px;
            text-shadow: 0 0 20px rgba(0, 255, 255, 0.5);
            margin-bottom: 10px;
        }

        .shop-header p {
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.6);
            letter-spacing: 2px;
        }

        /* --- BALANCE DASHBOARD --- */
        .user-balance {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 40px;
        }

        .balance-card {
            background: var(--card-bg);
            border: 1px solid rgba(0, 255, 255, 0.2);
            padding: 20px;
            border-radius: 16px;
            text-align: center;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(10px);
        }

        .balance-card.fluxon-card { border-color: var(--purple); }
        .balance-card.astron-card { border-color: var(--gold); }

        .balance-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 8px;
            color: rgba(255, 255, 255, 0.7);
        }

        .balance-value {
            font-family: 'Orbitron', sans-serif;
            font-size: clamp(1.2rem, 5vw, 2.2rem);
            font-weight: 900;
        }

        .fluxon-card .balance-value { color: var(--purple); }
        .astron-card .balance-value { color: var(--gold); }

        /* --- SHOP GRID --- */
        .shop-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
        }

        .shop-card {
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .shop-card:hover {
            transform: translateY(-10px);
            border-color: var(--cyan);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.6);
        }

        .item-visual {
            font-size: 3.5rem;
            margin-bottom: 15px;
            filter: drop-shadow(0 0 10px rgba(255, 255, 255, 0.2));
        }

        .item-name {
            font-family: 'Orbitron', sans-serif;
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: #fff;
        }

        .conversion-pill {
            display: inline-block;
            background: rgba(255, 255, 255, 0.05);
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 0.8rem;
            color: var(--cyan);
            margin-bottom: 20px;
            border: 1px solid rgba(0, 255, 255, 0.2);
        }

        .reward-area {
            background: rgba(0, 0, 0, 0.4);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            border: 1px solid rgba(255, 215, 0, 0.1);
        }

        .reward-label { font-size: 0.7rem; text-transform: uppercase; color: rgba(255, 255, 255, 0.5); }
        .reward-value { font-family: 'Orbitron', sans-serif; font-size: 2rem; color: var(--gold); font-weight: 800; }

        .cost-label { color: rgba(255, 255, 255, 0.7); font-size: 0.9rem; margin-bottom: 15px; font-weight: 600; }
        .cost-value { color: var(--purple); font-weight: 800; }

        .purchase-btn {
            width: 100%;
            padding: 15px;
            border-radius: 10px;
            border: none;
            font-family: 'Orbitron', sans-serif;
            font-weight: 700;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            transition: 0.3s;
            background: linear-gradient(90deg, #1e293b, #0f172a);
            color: rgba(255, 255, 255, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .purchase-btn.active {
            background: linear-gradient(90deg, var(--cyan), var(--purple));
            color: #fff;
            border: none;
            box-shadow: 0 5px 15px rgba(0, 255, 255, 0.2);
        }

        .purchase-btn.active:hover {
            transform: scale(1.02);
            box-shadow: var(--neon-glow);
        }

        /* --- ALERTS --- */
        .alert {
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 30px;
            text-align: center;
            font-weight: 700;
            animation: slideDown 0.5s ease;
        }
        .alert-success { background: rgba(16, 185, 129, 0.2); border: 1px solid #10b981; color: #10b981; }
        .alert-error { background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #ef4444; }

        @keyframes slideDown {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* --- COMPREHENSIVE MOBILE MEDIA QUERIES --- */
        
        /* Extra Large Devices (Large Desktops) - 1200px and up */
        @media (min-width: 1200px) {
            .shop-container {
                max-width: 1200px;
            }
            .shop-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 30px;
            }
        }

        /* Large Devices (Desktops) - 992px to 1199px */
        @media (max-width: 1199px) and (min-width: 992px) {
            .shop-container {
                max-width: 960px;
            }
            .shop-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 25px;
            }
        }

        /* Medium Devices (Tablets) - 768px to 991px */
        @media (max-width: 991px) and (min-width: 768px) {
            .shop-container {
                max-width: 100%;
                padding: 0 30px;
            }
            .nav-bar {
                padding: 0 30px;
            }
            .shop-header {
                margin: 30px 0 50px;
            }
            .shop-header h1 {
                font-size: 2.5rem;
                letter-spacing: 3px;
            }
            .shop-header p {
                font-size: 1.1rem;
            }
            .user-balance {
                grid-template-columns: 1fr 1fr;
                gap: 20px;
                margin-bottom: 50px;
            }
            .balance-card {
                padding: 25px;
            }
            .balance-label {
                font-size: 0.8rem;
            }
            .balance-value {
                font-size: 2rem;
            }
            .shop-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 25px;
            }
            .shop-card {
                padding: 25px;
            }
            .item-visual {
                font-size: 3rem;
            }
            .item-name {
                font-size: 1.3rem;
            }
            .reward-area {
                padding: 18px;
            }
            .reward-value {
                font-size: 1.8rem;
            }
            .purchase-btn {
                padding: 14px;
                font-size: 0.95rem;
            }
        }

        /* Small Devices (Large Phones) - 650px to 767px */
        @media (max-width: 767px) and (min-width: 651px) {
            .shop-container {
                max-width: 100%;
                padding: 0 25px;
            }
            .nav-bar {
                padding: 0 25px;
                margin: 20px auto;
            }
            .back-btn {
                padding: 12px 22px;
                font-size: 0.9rem;
            }
            .shop-header {
                margin: 25px 0 45px;
            }
            .shop-header h1 {
                font-size: 2.3rem;
                letter-spacing: 3px;
                margin-bottom: 12px;
            }
            .shop-header p {
                font-size: 1rem;
                letter-spacing: 1.5px;
            }
            .user-balance {
                grid-template-columns: 1fr 1fr;
                gap: 15px;
                margin-bottom: 45px;
            }
            .balance-card {
                padding: 22px;
                border-radius: 14px;
            }
            .balance-label {
                font-size: 0.75rem;
                margin-bottom: 10px;
            }
            .balance-value {
                font-size: 1.8rem;
            }
            .shop-grid {
                grid-template-columns: 1fr;
                gap: 22px;
            }
            .shop-card {
                padding: 28px;
                border-radius: 18px;
            }
            .item-visual {
                font-size: 3.2rem;
                margin-bottom: 18px;
            }
            .item-name {
                font-size: 1.35rem;
                margin-bottom: 12px;
            }
            .conversion-pill {
                padding: 7px 18px;
                font-size: 0.85rem;
                margin-bottom: 22px;
            }
            .reward-area {
                padding: 20px;
                margin-bottom: 22px;
            }
            .reward-label {
                font-size: 0.75rem;
            }
            .reward-value {
                font-size: 1.9rem;
            }
            .cost-label {
                font-size: 0.95rem;
                margin-bottom: 18px;
            }
            .purchase-btn {
                padding: 16px;
                font-size: 0.95rem;
            }
            .alert {
                padding: 18px;
                margin-bottom: 35px;
            }
        }

        /* Extra Small Devices (Phones) - 481px to 650px */
        @media (max-width: 650px) and (min-width: 481px) {
            .shop-container {
                max-width: 100%;
                padding: 0 20px;
            }
            .nav-bar {
                padding: 0 20px;
                margin: 15px auto;
                justify-content: center;
            }
            .back-btn {
                padding: 10px 20px;
                font-size: 0.85rem;
                gap: 6px;
            }
            .shop-header {
                margin: 20px 0 35px;
            }
            .shop-header h1 {
                font-size: 2rem;
                letter-spacing: 2px;
                margin-bottom: 10px;
            }
            .shop-header p {
                font-size: 0.95rem;
                letter-spacing: 1px;
            }
            .user-balance {
                grid-template-columns: 1fr;
                gap: 12px;
                margin-bottom: 35px;
            }
            .balance-card {
                padding: 20px;
                border-radius: 14px;
            }
            .balance-label {
                font-size: 0.7rem;
                margin-bottom: 8px;
            }
            .balance-value {
                font-size: 1.7rem;
            }
            .shop-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            .shop-card {
                padding: 25px;
                border-radius: 16px;
            }
            .item-visual {
                font-size: 3rem;
                margin-bottom: 15px;
            }
            .item-name {
                font-size: 1.25rem;
                margin-bottom: 10px;
            }
            .conversion-pill {
                padding: 6px 16px;
                font-size: 0.8rem;
                margin-bottom: 20px;
            }
            .reward-area {
                padding: 18px;
                margin-bottom: 20px;
            }
            .reward-label {
                font-size: 0.7rem;
            }
            .reward-value {
                font-size: 1.7rem;
            }
            .reward-value small {
                font-size: 0.75rem;
            }
            .cost-label {
                font-size: 0.9rem;
                margin-bottom: 15px;
            }
            .purchase-btn {
                padding: 15px;
                font-size: 0.9rem;
            }
            .alert {
                padding: 15px;
                margin-bottom: 30px;
                font-size: 0.95rem;
            }
        }

        /* Very Small Devices (Small Phones) - 320px to 480px */
        @media (max-width: 480px) {
            body {
                padding-bottom: 30px;
            }
            .shop-container {
                max-width: 100%;
                padding: 0 15px;
            }
            .nav-bar {
                padding: 0 15px;
                margin: 12px auto;
                justify-content: center;
            }
            .back-btn {
                padding: 9px 18px;
                font-size: 0.8rem;
                gap: 5px;
                letter-spacing: 0.5px;
            }
            .back-btn i {
                font-size: 0.85rem;
            }
            .shop-header {
                margin: 15px 0 30px;
            }
            .shop-header h1 {
                font-size: 1.75rem;
                letter-spacing: 1.5px;
                margin-bottom: 8px;
                line-height: 1.2;
            }
            .shop-header p {
                font-size: 0.85rem;
                letter-spacing: 0.5px;
                padding: 0 10px;
            }
            .user-balance {
                grid-template-columns: 1fr;
                gap: 10px;
                margin-bottom: 30px;
            }
            .balance-card {
                padding: 18px 15px;
                border-radius: 12px;
            }
            .balance-label {
                font-size: 0.65rem;
                margin-bottom: 6px;
                letter-spacing: 1.5px;
            }
            .balance-value {
                font-size: 1.5rem;
                word-break: break-word;
            }
            .shop-grid {
                grid-template-columns: 1fr;
                gap: 18px;
            }
            .shop-card {
                padding: 20px 18px;
                border-radius: 14px;
            }
            .item-visual {
                font-size: 2.5rem;
                margin-bottom: 12px;
            }
            .item-visual i {
                font-size: 2.5rem;
            }
            .item-name {
                font-size: 1.1rem;
                margin-bottom: 8px;
                line-height: 1.3;
            }
            .conversion-pill {
                padding: 5px 14px;
                font-size: 0.75rem;
                margin-bottom: 18px;
                line-height: 1.4;
            }
            .reward-area {
                padding: 15px;
                margin-bottom: 18px;
                border-radius: 10px;
            }
            .reward-label {
                font-size: 0.65rem;
                margin-bottom: 5px;
            }
            .reward-value {
                font-size: 1.5rem;
                line-height: 1.2;
            }
            .reward-value small {
                font-size: 0.7rem;
            }
            .cost-label {
                font-size: 0.85rem;
                margin-bottom: 12px;
                line-height: 1.4;
            }
            .cost-value {
                display: block;
                margin-top: 3px;
                font-size: 1rem;
            }
            .purchase-btn {
                padding: 14px;
                font-size: 0.85rem;
                letter-spacing: 0.5px;
                border-radius: 8px;
            }
            .alert {
                padding: 12px 15px;
                margin-bottom: 25px;
                border-radius: 10px;
                font-size: 0.85rem;
            }
            .alert i {
                font-size: 0.9rem;
                margin-right: 6px;
            }
        }

        /* Ultra Small Devices (Very Small Phones) - 320px and below */
        @media (max-width: 320px) {
            .shop-container {
                padding: 0 12px;
            }
            .nav-bar {
                padding: 0 12px;
                margin: 10px auto;
            }
            .back-btn {
                padding: 8px 15px;
                font-size: 0.75rem;
            }
            .shop-header h1 {
                font-size: 1.5rem;
                letter-spacing: 1px;
            }
            .shop-header p {
                font-size: 0.8rem;
            }
            .balance-card {
                padding: 15px 12px;
            }
            .balance-label {
                font-size: 0.6rem;
            }
            .balance-value {
                font-size: 1.3rem;
            }
            .shop-card {
                padding: 18px 15px;
            }
            .item-visual {
                font-size: 2.2rem;
            }
            .item-name {
                font-size: 1rem;
            }
            .conversion-pill {
                font-size: 0.7rem;
                padding: 4px 12px;
            }
            .reward-value {
                font-size: 1.3rem;
            }
            .purchase-btn {
                padding: 12px;
                font-size: 0.8rem;
            }
        }

        /* Landscape Orientation for Mobile */
        @media (max-width: 900px) and (orientation: landscape) {
            .shop-header {
                margin: 15px 0 25px;
            }
            .shop-header h1 {
                font-size: 2rem;
            }
            .user-balance {
                grid-template-columns: 1fr 1fr;
                gap: 15px;
                margin-bottom: 30px;
            }
            .shop-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
            }
        }

        /* Touch Device Optimizations */
        @media (hover: none) and (pointer: coarse) {
            .back-btn {
                min-height: 44px;
                min-width: 44px;
            }
            .purchase-btn {
                min-height: 48px;
            }
            .shop-card {
                -webkit-tap-highlight-color: rgba(0, 255, 255, 0.1);
            }
        }

        /* High DPI Displays */
        @media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
            .balance-card,
            .shop-card {
                border-width: 0.5px;
            }
        }
    </style>
</head>
<body class="no-select">
    <div class="nav-bar">
        <a href="index.php" class="back-btn"><i class="fas fa-arrow-left"></i> Games Hub</a>
    </div>
    
    <div class="shop-container">
        <div class="shop-header">
            <h1>Aether Shop</h1>
            <p>Convert Game Energy into Stellar Credits</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <div class="user-balance">
            <div class="balance-card fluxon-card" id="fluxonCard">
                <div class="balance-label">Total Fluxon Energy</div>
                <div class="balance-value" id="fluxonBalance" data-value="<?php echo $total_fluxon; ?>"><?php echo number_format($total_fluxon); ?></div>
            </div>
            <div class="balance-card astron-card" id="astronsCard">
                <div class="balance-label">Available Astrons</div>
                <div class="balance-value" id="astronsBalance" data-value="<?php echo $user_astrons; ?>"><?php echo number_format($user_astrons); ?></div>
            </div>
        </div>
        
        <div class="shop-grid">
            <?php 
            $icons = ['<i class="fas fa-bolt" style="color:#00ffff"></i>', 
                      '<i class="fas fa-satellite-dish" style="color:#9d4edd"></i>', 
                      '<i class="fas fa-meteor" style="color:#FFD700"></i>'];
            
            foreach ($shop_prices as $index => $price): 
                $fluxon = intval($price['fluxon_amount']);
                $astrons = intval($price['astrons_reward']);
                $can_afford = ($total_fluxon >= $fluxon);
            ?>
            <div class="shop-card" style="<?php echo $index === 2 ? 'border-color: var(--gold); background: rgba(255,215,0,0.03);' : ''; ?>">
                <div>
                    <div class="item-visual"><?php echo $icons[$index] ?? $icons[0]; ?></div>
                    <div class="item-name"><?php echo htmlspecialchars($price['claim_type']); ?></div>
                    <div class="conversion-pill">Rate: 1 Astron / <?php echo number_format($fluxon / $astrons); ?> Fluxon</div>
                    
                    <div class="reward-area">
                        <div class="reward-label">Payload Amount</div>
                        <div class="reward-value"><?php echo $astrons; ?> <small style="font-size:0.8rem">Astrons</small></div>
                    </div>
                </div>

                <div>
                    <div class="cost-label">Required Energy: <span class="cost-value"><?php echo number_format($fluxon); ?> Fluxon</span></div>
                    
                    <form method="POST" id="purchase-form-<?php echo $price['id']; ?>" onsubmit="handlePurchase(event, <?php echo $price['id']; ?>, <?php echo $fluxon; ?>, <?php echo $astrons; ?>); return false;">
                        <input type="hidden" name="item_id" value="<?php echo $price['id']; ?>">
                        <input type="hidden" name="item_cost" value="<?php echo $fluxon; ?>">
                        <input type="hidden" name="item_astrons" value="<?php echo $astrons; ?>">
                        <button type="submit" name="purchase_item" 
                                class="purchase-btn <?php echo $can_afford ? 'active' : ''; ?>" 
                                <?php echo !$can_afford ? 'disabled' : ''; ?>
                                id="purchase-btn-<?php echo $price['id']; ?>">
                            <?php echo $can_afford ? 'Claim Reward' : 'Insufficient Energy'; ?>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <script>
        // Animation function for number counting
        function animateValue(element, start, end, duration, callback) {
            const startTime = performance.now();
            const isDecrease = start > end;
            
            function update(currentTime) {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                
                // Easing function for smooth animation
                const easeOutCubic = 1 - Math.pow(1 - progress, 3);
                const current = Math.floor(start + (end - start) * easeOutCubic);
                
                element.textContent = current.toLocaleString();
                
                if (progress < 1) {
                    requestAnimationFrame(update);
                } else {
                    element.textContent = end.toLocaleString();
                    if (callback) callback();
                }
            }
            
            requestAnimationFrame(update);
        }
        
        // Handle purchase with visual effects
        async function handlePurchase(event, itemId, fluxonCost, astronsReward) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            const form = document.getElementById('purchase-form-' + itemId);
            const button = document.getElementById('purchase-btn-' + itemId);
            const fluxonElement = document.getElementById('fluxonBalance');
            const astronsElement = document.getElementById('astronsBalance');
            const fluxonCard = document.getElementById('fluxonCard');
            const astronsCard = document.getElementById('astronsCard');
            
            // Validate elements exist
            if (!form || !button || !fluxonElement || !astronsElement) {
                console.error('Required elements not found');
                alert('Error: Page elements not found. Please refresh the page.');
                return false;
            }
            
            // Check if already processing
            if (button.disabled && button.textContent === 'Processing...') {
                return false;
            }
            
            // Disable button during processing
            button.disabled = true;
            button.textContent = 'Processing...';
            
            // Get current values
            const currentFluxon = parseInt(fluxonElement.textContent.replace(/,/g, '')) || 0;
            const currentAstrons = parseInt(astronsElement.textContent.replace(/,/g, '')) || 0;
            
            // Validate sufficient Fluxon
            if (currentFluxon < fluxonCost) {
                button.disabled = false;
                button.textContent = 'Claim Reward';
                alert('Insufficient Fluxon Energy!');
                return false;
            }
            
            // Calculate new values
            const newFluxon = Math.max(0, currentFluxon - fluxonCost);
            const newAstrons = currentAstrons + astronsReward;
            
            // Create form data
            const formData = new FormData(form);
            
            try {
                // Submit the form with AJAX header
                const response = await fetch('shop.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });
                
                // Check if response is OK
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                
                // Get response text first to check content type
                const responseText = await response.text();
                
                // Debug logging (remove in production if needed)
                console.log('Response status:', response.status);
                console.log('Response text length:', responseText.length);
                console.log('Response preview:', responseText.substring(0, 200));
                
                // Try to parse as JSON
                let data;
                try {
                    data = JSON.parse(responseText);
                    console.log('Parsed JSON data:', data);
                } catch (parseError) {
                    console.error('JSON parse error:', parseError);
                    console.error('Response text:', responseText);
                    // If not JSON, check if it's HTML (error page)
                    if (responseText.includes('<!DOCTYPE') || responseText.includes('<html')) {
                        throw new Error('Server returned HTML instead of JSON. The request may not have been recognized as AJAX.');
                    }
                    throw new Error('Invalid JSON response from server: ' + parseError.message);
                }
                
                // Check if request was successful
                if (data.success) {
                    // Get actual database values from response
                    const actualNewFluxon = data.new_fluxon || newFluxon;
                    const actualNewAstrons = data.new_astrons || newAstrons;
                    
                    // Add pulse animation to cards
                    const fluxonCard = document.getElementById('fluxonCard');
                    const astronsCard = document.getElementById('astronsCard');
                    fluxonCard.classList.add('animating');
                    astronsCard.classList.add('animating');
                    
                    // Animate Fluxon decrease with actual database value
                    animateValue(fluxonElement, currentFluxon, actualNewFluxon, 1200, function() {
                        // Update data-value attribute
                        fluxonElement.setAttribute('data-value', actualNewFluxon);
                        fluxonCard.classList.remove('animating');
                        
                        // Animate Astrons increase with actual database value
                        animateValue(astronsElement, currentAstrons, actualNewAstrons, 1200, function() {
                            // Update data-value attribute
                            astronsElement.setAttribute('data-value', actualNewAstrons);
                            astronsCard.classList.remove('animating');
                            
                            // Show success message
                            const alertDiv = document.createElement('div');
                            alertDiv.className = 'alert alert-success';
                            alertDiv.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;
                            alertDiv.style.animation = 'slideDown 0.5s ease';
                            const header = document.querySelector('.shop-header');
                            const container = document.querySelector('.shop-container');
                            container.insertBefore(alertDiv, header.nextSibling);
                            
                            // Remove alert after 3 seconds
                            setTimeout(function() {
                                alertDiv.style.opacity = '0';
                                alertDiv.style.transition = 'opacity 0.5s';
                                setTimeout(function() {
                                    alertDiv.remove();
                                }, 500);
                            }, 3000);
                            
                            // Update button state
                            button.disabled = false;
                            button.textContent = 'Claim Reward';
                            
                            // Update all purchase buttons based on new Fluxon balance
                            updatePurchaseButtons(actualNewFluxon);
                        });
                    });
                } else {
                    // Show error message
                    const errorMsg = data.message || 'Transmission Failure. Please try again.';
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-error';
                    alertDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + errorMsg;
                    alertDiv.style.animation = 'slideDown 0.5s ease';
                    const header = document.querySelector('.shop-header');
                    const container = document.querySelector('.shop-container');
                    if (header && container) {
                        container.insertBefore(alertDiv, header.nextSibling);
                    } else {
                        document.body.insertBefore(alertDiv, document.body.firstChild);
                    }
                    
                    button.disabled = false;
                    button.textContent = 'Claim Reward';
                    
                    setTimeout(function() {
                        alertDiv.remove();
                    }, 3000);
                }
            } catch (error) {
                console.error('Error:', error);
                button.disabled = false;
                button.textContent = 'Claim Reward';
                
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-error';
                alertDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + (error.message || 'An error occurred. Please try again.');
                alertDiv.style.animation = 'slideDown 0.5s ease';
                const header = document.querySelector('.shop-header');
                const container = document.querySelector('.shop-container');
                if (header && container) {
                    container.insertBefore(alertDiv, header.nextSibling);
                } else {
                    document.body.insertBefore(alertDiv, document.body.firstChild);
                }
                
                setTimeout(function() {
                    alertDiv.remove();
                }, 3000);
            }
        }
        
        // Function to update purchase buttons based on available Fluxon
        function updatePurchaseButtons(availableFluxon) {
            document.querySelectorAll('.shop-card').forEach(function(card) {
                const costElement = card.querySelector('.cost-value');
                if (costElement) {
                    const costText = costElement.textContent.replace(/,/g, '').replace(' Fluxon', '').trim();
                    const cost = parseInt(costText) || 0;
                    const canAfford = availableFluxon >= cost;
                    const button = card.querySelector('.purchase-btn');
                    if (button) {
                        if (canAfford) {
                            button.disabled = false;
                            button.classList.add('active');
                            button.textContent = 'Claim Reward';
                        } else {
                            button.disabled = true;
                            button.classList.remove('active');
                            button.textContent = 'Insufficient Energy';
                        }
                    }
                }
            });
        }
        
        // Add pulse animation for balance cards when values change
        const style = document.createElement('style');
        style.textContent = `
            @keyframes pulse {
                0%, 100% { transform: scale(1); box-shadow: 0 0 20px rgba(0, 255, 255, 0.3); }
                50% { transform: scale(1.05); box-shadow: 0 0 30px rgba(0, 255, 255, 0.6); }
            }
            .balance-card.animating {
                animation: pulse 0.6s ease;
            }
            .fluxon-card.animating {
                box-shadow: 0 0 30px rgba(157, 78, 221, 0.6) !important;
            }
            .astron-card.animating {
                box-shadow: 0 0 30px rgba(255, 215, 0, 0.6) !important;
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>