<?php
// Start output buffering at the very beginning for AJAX requests
if (ob_get_level() == 0) {
    ob_start();
}

// Detect AJAX requests early (before session/connection)
$is_ajax_early = (
    (!empty($_POST['ajax']) && $_POST['ajax'] == '1') ||
    (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') ||
    (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
);

// Suppress any warnings/notices that might output HTML
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
require_once 'connection.php';

// Use the early AJAX detection
$is_ajax = $is_ajax_early;

// Helper function to send JSON response and exit
function sendJsonResponse($success, $message, $data = []) {
    // Clean all output buffers
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    
    // Only set headers if they haven't been sent
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        header('X-Content-Type-Options: nosniff');
        header('Pragma: no-cache');
    }
    
    // Build response
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    // Merge additional data
    if (!empty($data)) {
        $response = array_merge($response, $data);
    }
    
    // Output JSON and exit
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    if ($is_ajax) {
        sendJsonResponse(false, 'Please login first');
    }
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get user's Fluxon (total score) from users table
// This is now stored directly in users.total_score for better performance
try {
    // Check if total_score column exists, if not, add it
    $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'total_score'");
    if ($check_column && $check_column->num_rows == 0) {
        $conn->query("ALTER TABLE users ADD COLUMN total_score BIGINT(20) NOT NULL DEFAULT 0 COMMENT 'Total score (Fluxon)'");
        // Initialize from game_leaderboard
        $conn->query("UPDATE users u SET total_score = COALESCE((SELECT SUM(score) FROM game_leaderboard gl WHERE gl.user_id = u.id), 0)");
    }
    if ($check_column) $check_column->close();
    
    if (!$fluxon_stmt = $conn->prepare("SELECT total_score FROM users WHERE id = ?")) {
        throw new Exception("Database error: " . $conn->error);
    }
    $fluxon_stmt->bind_param("i", $user_id);
    if (!$fluxon_stmt->execute()) {
        throw new Exception("Database error: " . $fluxon_stmt->error);
    }
    $fluxon_result = $fluxon_stmt->get_result();
    $fluxon_data = $fluxon_result->fetch_assoc();
    $total_fluxon = intval($fluxon_data['total_score'] ?? 0);
    if ($total_fluxon < 0) $total_fluxon = 0;
    $fluxon_stmt->close();
} catch (Exception $e) {
    if ($is_ajax) {
        sendJsonResponse(false, "Error loading balance: " . $e->getMessage());
    }
    $total_fluxon = 0;
}

// Get shop prices from database
$create_table = $conn->query("
    CREATE TABLE IF NOT EXISTS shop_pricing (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        fluxon_amount INT(11) NOT NULL COMMENT 'Amount of Fluxon required',
        astrons_reward INT(11) NOT NULL COMMENT 'Astrons user gets',
        astrons_cost INT(11) NOT NULL DEFAULT 0 COMMENT 'Amount of Astrons required for reverse trade',
        fluxon_reward INT(11) NOT NULL DEFAULT 0 COMMENT 'Fluxon user gets from reverse trade',
        claim_type VARCHAR(50) NOT NULL COMMENT 'Type name',
        is_active TINYINT(1) DEFAULT 1,
        display_order INT(11) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_fluxon (fluxon_amount)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Add reverse trading columns if they don't exist
$check_reverse = $conn->query("SHOW COLUMNS FROM shop_pricing LIKE 'astrons_cost'");
if ($check_reverse && $check_reverse->num_rows == 0) {
    $conn->query("ALTER TABLE shop_pricing 
                  ADD COLUMN astrons_cost INT(11) NOT NULL DEFAULT 0 COMMENT 'Amount of Astrons required for reverse trade' AFTER astrons_reward,
                  ADD COLUMN fluxon_reward INT(11) NOT NULL DEFAULT 0 COMMENT 'Fluxon user gets from reverse trade' AFTER astrons_cost");
}
if ($check_reverse) $check_reverse->close();

// Get prices from database
$prices_result = $conn->query("SELECT * FROM shop_pricing WHERE is_active = 1 ORDER BY display_order ASC LIMIT 3");
$shop_prices = [];
if ($prices_result && $prices_result->num_rows > 0) {
    $shop_prices = $prices_result->fetch_all(MYSQLI_ASSOC);
} else {
    // Default prices if none set
    $shop_prices = [
        ['id' => 1, 'fluxon_amount' => 5000, 'astrons_reward' => 10, 'claim_type' => 'Basic Claim'],
        ['id' => 2, 'fluxon_amount' => 7500, 'astrons_reward' => 20, 'claim_type' => 'Standard Claim'],
        ['id' => 3, 'fluxon_amount' => 10000, 'astrons_reward' => 30, 'claim_type' => 'Premium Claim']
    ];
}

// Get user's Astrons
try {
    if (!$credits_stmt = $conn->prepare("SELECT credits FROM user_profile WHERE user_id = ?")) {
        throw new Exception("Database error: " . $conn->error);
    }
    $credits_stmt->bind_param("i", $user_id);
    if (!$credits_stmt->execute()) {
        throw new Exception("Database error: " . $credits_stmt->error);
    }
    $credits_result = $credits_stmt->get_result();
    $credits_data = $credits_result->fetch_assoc();
    $user_astrons = intval($credits_data['credits'] ?? 0);
    $credits_stmt->close();
} catch (Exception $e) {
    if ($is_ajax) {
        sendJsonResponse(false, "Error loading credits: " . $e->getMessage());
    }
    $user_astrons = 0;
}

// Handle purchase
$message = '';
$error = '';
$purchase_success = false;
$new_fluxon = $total_fluxon;
$new_astrons = $user_astrons;

// Handle forward trading (Fluxon → Astrons)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purchase_item'])) {
    // Wrap entire purchase handling in try-catch for AJAX requests
    try {
        $item_id = intval($_POST['item_id'] ?? 0);
        $item_cost = intval($_POST['item_cost'] ?? 0);
        $item_astrons = intval($_POST['item_astrons'] ?? 0);
        
        if ($item_id > 0 && $item_cost > 0 && $item_astrons > 0) {
            // CRITICAL: Verify the item exists in database and prices match admin settings
            if (!$verify_item_stmt = $conn->prepare("SELECT id, fluxon_amount, astrons_reward, is_active FROM shop_pricing WHERE id = ? AND is_active = 1")) {
                throw new Exception("Database error: " . $conn->error);
            }
            $verify_item_stmt->bind_param("i", $item_id);
            if (!$verify_item_stmt->execute()) {
                throw new Exception("Database error: " . $verify_item_stmt->error);
            }
            $verify_item_result = $verify_item_stmt->get_result();
        
        if ($verify_item_result->num_rows === 0) {
            $verify_item_stmt->close();
            if ($is_ajax) {
                sendJsonResponse(false, "Invalid offer or offer is no longer available.");
            }
            $error = "Invalid offer or offer is no longer available.";
        } else {
            $item_data = $verify_item_result->fetch_assoc();
            $db_fluxon = intval($item_data['fluxon_amount']);
            $db_astrons = intval($item_data['astrons_reward']);
            $verify_item_stmt->close();
            
            // Verify prices match database (prevent price manipulation)
            if ($item_cost !== $db_fluxon || $item_astrons !== $db_astrons) {
                if ($is_ajax) {
                    sendJsonResponse(false, "Price mismatch detected. Please refresh the page and try again.");
                }
                $error = "Price mismatch detected. Please refresh the page and try again.";
            } else {
                // Use verified database values
                $item_cost = $db_fluxon;
                $item_astrons = $db_astrons;
                
                // Re-check Fluxon balance before transaction (from users table)
                if (!$fluxon_check_stmt = $conn->prepare("SELECT total_score FROM users WHERE id = ?")) {
                    throw new Exception("Database error: " . $conn->error);
                }
                $fluxon_check_stmt->bind_param("i", $user_id);
                if (!$fluxon_check_stmt->execute()) {
                    throw new Exception("Database error: " . $fluxon_check_stmt->error);
                }
                $fluxon_check_result = $fluxon_check_stmt->get_result();
                $fluxon_check_data = $fluxon_check_result->fetch_assoc();
                $current_fluxon = intval($fluxon_check_data['total_score'] ?? 0);
                if ($current_fluxon < 0) $current_fluxon = 0;
                $fluxon_check_stmt->close();
                
                if ($current_fluxon >= $item_cost) {
                    // Start transaction for atomic operation
                    $conn->begin_transaction();
                    
                    try {
                        // Step 1: Ensure user_profile row exists
                        $check_profile = $conn->prepare("SELECT id, credits FROM user_profile WHERE user_id = ?");
                        $check_profile->bind_param("i", $user_id);
                        $check_profile->execute();
                        $profile_result = $check_profile->get_result();
                        $profile_exists = ($profile_result->num_rows > 0);
                        $previous_astrons = 0;
                        if ($profile_exists) {
                            $profile_data = $profile_result->fetch_assoc();
                            $previous_astrons = intval($profile_data['credits'] ?? 0);
                        }
                        $check_profile->close();
                        
                        if (!$profile_exists) {
                            $create_profile = $conn->prepare("INSERT INTO user_profile (user_id, credits) VALUES (?, 0)");
                            $create_profile->bind_param("i", $user_id);
                            if (!$create_profile->execute()) {
                                throw new Exception("Failed to create user profile: " . $create_profile->error);
                            }
                            $create_profile->close();
                        }
                        
                        // Step 2: Add Astrons to user profile
                        $update_stmt = $conn->prepare("UPDATE user_profile SET credits = credits + ? WHERE user_id = ?");
                        $update_stmt->bind_param("ii", $item_astrons, $user_id);
                        
                        if (!$update_stmt->execute()) {
                            throw new Exception("Failed to update Astrons: " . $update_stmt->error);
                        }
                        $update_stmt->close();
                        
                        // Step 3: Deduct Fluxon by inserting negative score entry
                        $negative_score = -$item_cost;
                        
                        // Check which timestamp columns exist
                        $check_created = $conn->query("SHOW COLUMNS FROM game_leaderboard LIKE 'created_at'");
                        $check_played = $conn->query("SHOW COLUMNS FROM game_leaderboard LIKE 'played_at'");
                        $has_created_at = ($check_created && $check_created->num_rows > 0);
                        $has_played_at = ($check_played && $check_played->num_rows > 0);
                        if ($check_created) $check_created->close();
                        if ($check_played) $check_played->close();
                        
                        // Build INSERT query based on available columns
                        if ($has_created_at && $has_played_at) {
                            $deduct_stmt = $conn->prepare("INSERT INTO game_leaderboard (user_id, game_name, score, credits_used, game_mode, created_at, played_at) VALUES (?, 'shop-purchase', ?, 0, 'credits', NOW(), NOW())");
                        } else if ($has_played_at) {
                            $deduct_stmt = $conn->prepare("INSERT INTO game_leaderboard (user_id, game_name, score, credits_used, game_mode, played_at) VALUES (?, 'shop-purchase', ?, 0, 'credits', NOW())");
                        } else if ($has_created_at) {
                            $deduct_stmt = $conn->prepare("INSERT INTO game_leaderboard (user_id, game_name, score, credits_used, game_mode, created_at) VALUES (?, 'shop-purchase', ?, 0, 'credits', NOW())");
                        } else {
                            $deduct_stmt = $conn->prepare("INSERT INTO game_leaderboard (user_id, game_name, score, credits_used, game_mode) VALUES (?, 'shop-purchase', ?, 0, 'credits')");
                        }
                        
                        $deduct_stmt->bind_param("ii", $user_id, $negative_score);
                        
                        if (!$deduct_stmt->execute()) {
                            throw new Exception("Failed to deduct Fluxon: " . $deduct_stmt->error);
                        }
                        $deduct_stmt->close();
                        
                        // Step 4: Update total_score in users table (subtract the cost)
                        $update_total_stmt = $conn->prepare("UPDATE users SET total_score = total_score + ? WHERE id = ?");
                        $update_total_stmt->bind_param("ii", $negative_score, $user_id);
                        if (!$update_total_stmt->execute()) {
                            throw new Exception("Failed to update total score: " . $update_total_stmt->error);
                        }
                        $update_total_stmt->close();
                        
                        // Step 5: Get updated total Fluxon from users table
                        $fluxon_recalc_stmt = $conn->prepare("SELECT total_score FROM users WHERE id = ?");
                        $fluxon_recalc_stmt->bind_param("i", $user_id);
                        $fluxon_recalc_stmt->execute();
                        $fluxon_recalc_result = $fluxon_recalc_stmt->get_result();
                        $fluxon_recalc_data = $fluxon_recalc_result->fetch_assoc();
                        $new_fluxon = intval($fluxon_recalc_data['total_score'] ?? 0);
                        if ($new_fluxon < 0) $new_fluxon = 0;
                        $fluxon_recalc_stmt->close();
                        
                        // Step 6: Re-fetch Astrons to get final value
                        $final_astrons_stmt = $conn->prepare("SELECT credits FROM user_profile WHERE user_id = ?");
                        $final_astrons_stmt->bind_param("i", $user_id);
                        $final_astrons_stmt->execute();
                        $final_astrons_result = $final_astrons_stmt->get_result();
                        $final_astrons_data = $final_astrons_result->fetch_assoc();
                        $final_astrons = intval($final_astrons_data['credits'] ?? 0);
                        $final_astrons_stmt->close();
                        
                        // Commit transaction
                        $conn->commit();
                        
                        // Update variables
                        $total_fluxon = $new_fluxon;
                        $user_astrons = $final_astrons;
                        $purchase_success = true;
                        $message = "Nexus Link Established! Claimed {$item_astrons} Astrons.";
                        
                        // If AJAX request, return JSON response
                        if ($is_ajax) {
                            sendJsonResponse(true, $message, [
                                'new_fluxon' => $new_fluxon,
                                'new_astrons' => $final_astrons,
                                'fluxon_deducted' => $item_cost,
                                'astrons_added' => $item_astrons,
                                'previous_fluxon' => $current_fluxon,
                                'previous_astrons' => $previous_astrons
                            ]);
                        }
                        
                    } catch (Exception $e) {
                        $conn->rollback();
                        $error = "Transaction failed: " . $e->getMessage();
                        
                        if ($is_ajax) {
                            sendJsonResponse(false, $error);
                        }
                    }
                } else {
                    $error = "Insufficient Fluxon! You need {$item_cost} Fluxon to claim this item.";
                    
                    if ($is_ajax) {
                        sendJsonResponse(false, $error);
                    }
                }
            }
        }
        } else {
            $error = "Invalid purchase data.";
            
            if ($is_ajax) {
                sendJsonResponse(false, $error);
            }
        }
    } catch (Exception $e) {
        // Catch any unexpected errors and return JSON for AJAX requests
        if ($is_ajax) {
            sendJsonResponse(false, "An error occurred: " . $e->getMessage());
        } else {
            $error = "An error occurred: " . $e->getMessage();
        }
    } catch (Error $e) {
        // Catch fatal errors (PHP 7+)
        if ($is_ajax) {
            sendJsonResponse(false, "A system error occurred. Please try again later.");
        } else {
            $error = "A system error occurred. Please try again later.";
        }
    }
}

// Handle reverse trading (Astrons → Fluxon)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['trade_astrons'])) {
    try {
        $item_id = intval($_POST['item_id'] ?? 0);
        $item_astrons_cost = intval($_POST['item_astrons_cost'] ?? 0);
        $item_fluxon_reward = intval($_POST['item_fluxon_reward'] ?? 0);
        
        if ($item_id > 0 && $item_astrons_cost > 0 && $item_fluxon_reward > 0) {
            // Verify the item exists and prices match
            if (!$verify_item_stmt = $conn->prepare("SELECT id, astrons_cost, fluxon_reward, is_active FROM shop_pricing WHERE id = ? AND is_active = 1")) {
                throw new Exception("Database error: " . $conn->error);
            }
            $verify_item_stmt->bind_param("i", $item_id);
            if (!$verify_item_stmt->execute()) {
                throw new Exception("Database error: " . $verify_item_stmt->error);
            }
            $verify_item_result = $verify_item_stmt->get_result();
            
            if ($verify_item_result->num_rows === 0) {
                $verify_item_stmt->close();
                if ($is_ajax) {
                    sendJsonResponse(false, "Invalid offer or offer is no longer available.");
                }
                $error = "Invalid offer or offer is no longer available.";
            } else {
                $item_data = $verify_item_result->fetch_assoc();
                $db_astrons_cost = intval($item_data['astrons_cost'] ?? 0);
                $db_fluxon_reward = intval($item_data['fluxon_reward'] ?? 0);
                $verify_item_stmt->close();
                
                // Verify prices match
                if ($item_astrons_cost !== $db_astrons_cost || $item_fluxon_reward !== $db_fluxon_reward) {
                    if ($is_ajax) {
                        sendJsonResponse(false, "Price mismatch detected. Please refresh the page and try again.");
                    }
                    $error = "Price mismatch detected. Please refresh the page and try again.";
                } else {
                    // Use verified values
                    $item_astrons_cost = $db_astrons_cost;
                    $item_fluxon_reward = $db_fluxon_reward;
                    
                    // Check Astrons balance
                    if (!$astrons_check_stmt = $conn->prepare("SELECT credits FROM user_profile WHERE user_id = ?")) {
                        throw new Exception("Database error: " . $conn->error);
                    }
                    $astrons_check_stmt->bind_param("i", $user_id);
                    if (!$astrons_check_stmt->execute()) {
                        throw new Exception("Database error: " . $astrons_check_stmt->error);
                    }
                    $astrons_check_result = $astrons_check_stmt->get_result();
                    $astrons_check_data = $astrons_check_result->fetch_assoc();
                    $current_astrons = intval($astrons_check_data['credits'] ?? 0);
                    $astrons_check_stmt->close();
                    
                    if ($current_astrons >= $item_astrons_cost) {
                        $conn->begin_transaction();
                        
                        try {
                            // Step 1: Deduct Astrons
                            $deduct_astrons_stmt = $conn->prepare("UPDATE user_profile SET credits = credits - ? WHERE user_id = ?");
                            $deduct_astrons_stmt->bind_param("ii", $item_astrons_cost, $user_id);
                            if (!$deduct_astrons_stmt->execute()) {
                                throw new Exception("Failed to deduct Astrons: " . $deduct_astrons_stmt->error);
                            }
                            $deduct_astrons_stmt->close();
                            
                            // Step 2: Add Fluxon by inserting positive score entry
                            $check_created = $conn->query("SHOW COLUMNS FROM game_leaderboard LIKE 'created_at'");
                            $check_played = $conn->query("SHOW COLUMNS FROM game_leaderboard LIKE 'played_at'");
                            $has_created_at = ($check_created && $check_created->num_rows > 0);
                            $has_played_at = ($check_played && $check_played->num_rows > 0);
                            if ($check_created) $check_created->close();
                            if ($check_played) $check_played->close();
                            
                            if ($has_created_at && $has_played_at) {
                                $add_fluxon_stmt = $conn->prepare("INSERT INTO game_leaderboard (user_id, game_name, score, credits_used, game_mode, created_at, played_at) VALUES (?, 'shop-trade', ?, 0, 'credits', NOW(), NOW())");
                            } else if ($has_played_at) {
                                $add_fluxon_stmt = $conn->prepare("INSERT INTO game_leaderboard (user_id, game_name, score, credits_used, game_mode, played_at) VALUES (?, 'shop-trade', ?, 0, 'credits', NOW())");
                            } else if ($has_created_at) {
                                $add_fluxon_stmt = $conn->prepare("INSERT INTO game_leaderboard (user_id, game_name, score, credits_used, game_mode, created_at) VALUES (?, 'shop-trade', ?, 0, 'credits', NOW())");
                            } else {
                                $add_fluxon_stmt = $conn->prepare("INSERT INTO game_leaderboard (user_id, game_name, score, credits_used, game_mode) VALUES (?, 'shop-trade', ?, 0, 'credits')");
                            }
                            
                            $add_fluxon_stmt->bind_param("ii", $user_id, $item_fluxon_reward);
                            if (!$add_fluxon_stmt->execute()) {
                                throw new Exception("Failed to add Fluxon: " . $add_fluxon_stmt->error);
                            }
                            $add_fluxon_stmt->close();
                            
                            // Step 3: Update total_score in users table
                            $update_total_stmt = $conn->prepare("UPDATE users SET total_score = total_score + ? WHERE id = ?");
                            $update_total_stmt->bind_param("ii", $item_fluxon_reward, $user_id);
                            if (!$update_total_stmt->execute()) {
                                throw new Exception("Failed to update total score: " . $update_total_stmt->error);
                            }
                            $update_total_stmt->close();
                            
                            // Step 4: Get updated values
                            $final_fluxon_stmt = $conn->prepare("SELECT total_score FROM users WHERE id = ?");
                            $final_fluxon_stmt->bind_param("i", $user_id);
                            $final_fluxon_stmt->execute();
                            $final_fluxon_result = $final_fluxon_stmt->get_result();
                            $final_fluxon_data = $final_fluxon_result->fetch_assoc();
                            $new_fluxon = intval($final_fluxon_data['total_score'] ?? 0);
                            if ($new_fluxon < 0) $new_fluxon = 0;
                            $final_fluxon_stmt->close();
                            
                            $final_astrons_stmt = $conn->prepare("SELECT credits FROM user_profile WHERE user_id = ?");
                            $final_astrons_stmt->bind_param("i", $user_id);
                            $final_astrons_stmt->execute();
                            $final_astrons_result = $final_astrons_stmt->get_result();
                            $final_astrons_data = $final_astrons_result->fetch_assoc();
                            $new_astrons = intval($final_astrons_data['credits'] ?? 0);
                            $final_astrons_stmt->close();
                            
                            $conn->commit();
                            
                            $total_fluxon = $new_fluxon;
                            $user_astrons = $new_astrons;
                            $purchase_success = true;
                            $message = "Trade successful! Received {$item_fluxon_reward} Fluxon.";
                            
                            if ($is_ajax) {
                                sendJsonResponse(true, $message, [
                                    'new_fluxon' => $new_fluxon,
                                    'new_astrons' => $new_astrons,
                                    'fluxon_added' => $item_fluxon_reward,
                                    'astrons_deducted' => $item_astrons_cost,
                                    'previous_fluxon' => $total_fluxon,
                                    'previous_astrons' => $current_astrons
                                ]);
                            }
                            
                        } catch (Exception $e) {
                            $conn->rollback();
                            $error = "Transaction failed: " . $e->getMessage();
                            if ($is_ajax) {
                                sendJsonResponse(false, $error);
                            }
                        }
                    } else {
                        $error = "Insufficient Astrons! You need {$item_astrons_cost} Astrons for this trade.";
                        if ($is_ajax) {
                            sendJsonResponse(false, $error);
                        }
                    }
                }
            }
        } else {
            $error = "Invalid trade data.";
            if ($is_ajax) {
                sendJsonResponse(false, $error);
            }
        }
    } catch (Exception $e) {
        if ($is_ajax) {
            sendJsonResponse(false, "An error occurred: " . $e->getMessage());
        } else {
            $error = "An error occurred: " . $e->getMessage();
        }
    } catch (Error $e) {
        if ($is_ajax) {
            sendJsonResponse(false, "A system error occurred. Please try again later.");
        } else {
            $error = "A system error occurred. Please try again later.";
        }
    }
}

// If AJAX request was handled, we should have exited by now
// But if we reach here for AJAX, something went wrong
if ($is_ajax) {
    sendJsonResponse(false, "Request was not properly processed.");
}

// If not AJAX, continue with normal page rendering
if (!$is_ajax) {
    ob_end_flush();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop - Astra Den</title>
    <link rel="icon" type="image/svg+xml" href="Alogo.svg">
    <link rel="shortcut icon" type="image/svg+xml" href="Alogo.svg">
    <link rel="alternate icon" type="image/png" href="Alogo.svg">
    <link rel="apple-touch-icon" sizes="180x180" href="Alogo.svg">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/index.css">
    <style>
        :root {
            --primary-cyan: #00ffff;
            --primary-purple: #9d4edd;
            --primary-pink: #ff006e;
            --dark-bg: #0a0a0f;
            --card-bg: rgba(15, 15, 25, 0.85);
        }

        .shop-page {
            min-height: 100vh;
            position: relative;
            padding: 120px 20px 50px;
            z-index: 1;
        }

        .shop-header {
            text-align: center;
            margin-bottom: 50px;
            position: relative;
            z-index: 2;
        }

        .shop-header h1 {
            font-family: 'Orbitron', sans-serif;
            font-size: clamp(2rem, 5vw, 3.5rem);
            color: var(--primary-cyan);
            text-shadow: 0 0 30px rgba(0, 255, 255, 0.8);
            margin-bottom: 15px;
            letter-spacing: 3px;
            font-weight: 900;
        }

        .shop-header p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 1.1rem;
            margin-top: 10px;
        }

        .balance-section {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-bottom: 50px;
            flex-wrap: wrap;
            position: relative;
            z-index: 2;
        }

        .balance-card {
            background: var(--card-bg);
            border: 2px solid var(--primary-cyan);
            border-radius: 20px;
            padding: 25px 40px;
            text-align: center;
            min-width: 250px;
            box-shadow: 0 0 30px rgba(0, 255, 255, 0.3);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .balance-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(0, 255, 255, 0.1), transparent);
            animation: rotate 10s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .balance-card.fluxon {
            border-color: #00ff00;
            box-shadow: 0 0 30px rgba(0, 255, 0, 0.3);
        }

        .balance-card.astrons {
            border-color: #FFD700;
            box-shadow: 0 0 30px rgba(255, 215, 0, 0.3);
        }

        .balance-card.animating {
            animation: pulse 0.5s ease-in-out;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .balance-label {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.6);
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 600;
        }

        .balance-value {
            font-size: 2.5rem;
            font-weight: 900;
            font-family: 'Orbitron', sans-serif;
            position: relative;
            z-index: 1;
        }

        .balance-card.fluxon .balance-value {
            color: #00ff00;
            text-shadow: 0 0 20px rgba(0, 255, 0, 0.8);
        }

        .balance-card.astrons .balance-value {
            color: #FFD700;
            text-shadow: 0 0 20px rgba(255, 215, 0, 0.8);
        }

        .shop-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 30px;
            position: relative;
            z-index: 2;
        }

        .shop-item {
            background: var(--card-bg);
            border: 2px solid var(--primary-purple);
            border-radius: 25px;
            padding: 35px;
            text-align: center;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(10px);
        }

        .shop-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(0, 255, 255, 0.05), rgba(157, 78, 221, 0.05));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .shop-item:hover {
            transform: translateY(-10px) scale(1.02);
            border-color: var(--primary-cyan);
            box-shadow: 0 15px 50px rgba(0, 255, 255, 0.4);
        }

        .shop-item:hover::before {
            opacity: 1;
        }

        .item-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: linear-gradient(135deg, var(--primary-cyan), var(--primary-purple));
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            font-family: 'Orbitron', sans-serif;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .item-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            filter: drop-shadow(0 0 20px currentColor);
            position: relative;
            z-index: 1;
        }

        .item-name {
            font-family: 'Orbitron', sans-serif;
            font-size: 1.5rem;
            color: var(--primary-cyan);
            margin-bottom: 15px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
            position: relative;
            z-index: 1;
        }

        .item-details {
            background: rgba(0, 0, 0, 0.4);
            border-radius: 15px;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid rgba(0, 255, 255, 0.2);
            position: relative;
            z-index: 1;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            font-size: 0.95rem;
        }

        .detail-row:last-child {
            margin-bottom: 0;
        }

        .detail-label {
            color: rgba(255, 255, 255, 0.6);
        }

        .detail-value {
            color: #FFD700;
            font-weight: 700;
            font-family: 'Orbitron', sans-serif;
        }

        .conversion-rate {
            text-align: center;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.85rem;
            margin-top: 10px;
            font-style: italic;
        }

        .purchase-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, var(--primary-cyan), var(--primary-purple));
            border: none;
            border-radius: 12px;
            color: white;
            font-family: 'Orbitron', sans-serif;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
            z-index: 1;
            margin-top: 20px;
        }

        .purchase-btn:hover:not(:disabled) {
            transform: scale(1.05);
            box-shadow: 0 0 30px rgba(0, 255, 255, 0.6);
        }

        .purchase-btn:active:not(:disabled) {
            transform: scale(0.98);
        }

        .purchase-btn:disabled {
            background: rgba(100, 100, 100, 0.3);
            color: rgba(255, 255, 255, 0.4);
            cursor: not-allowed;
            border: 2px solid rgba(255, 255, 255, 0.1);
        }

        .back-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            padding: 12px 25px;
            background: var(--card-bg);
            border: 2px solid var(--primary-cyan);
            border-radius: 10px;
            color: var(--primary-cyan);
            text-decoration: none;
            font-family: 'Orbitron', sans-serif;
            font-weight: 700;
            transition: all 0.3s ease;
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .back-btn:hover {
            background: rgba(0, 255, 255, 0.1);
            box-shadow: 0 0 20px rgba(0, 255, 255, 0.5);
            transform: translateX(-3px);
        }

        .message {
            max-width: 600px;
            margin: 0 auto 30px;
            background: rgba(0, 255, 0, 0.1);
            border: 2px solid #00ff00;
            color: #00ff00;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            font-weight: 600;
            box-shadow: 0 0 20px rgba(0, 255, 0, 0.3);
            position: relative;
            z-index: 2;
        }

        .error {
            max-width: 600px;
            margin: 0 auto 30px;
            background: rgba(255, 0, 0, 0.1);
            border: 2px solid #ff0000;
            color: #ff0000;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            font-weight: 600;
            box-shadow: 0 0 20px rgba(255, 0, 0, 0.3);
            position: relative;
            z-index: 2;
        }

        @media (max-width: 768px) {
            .shop-page {
                padding: 100px 15px 30px;
            }

            .balance-section {
                gap: 20px;
            }

            .balance-card {
                min-width: 100%;
                padding: 20px 30px;
            }

            .shop-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .back-btn {
                top: 15px;
                left: 15px;
                padding: 10px 20px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body class="no-select" oncontextmenu="return false;">
    <!-- Space Background -->
    <div id="space-background"></div>
    
    <a href="index.php" class="back-btn">
        <span>←</span>
        <span>Back</span>
    </a>
    
    <div class="shop-page">
        <div class="shop-header">
            <h1>🛒 TRADING HUB</h1>
            <p>Trade Fluxon ↔ Astrons at admin-set rates</p>
        </div>
        
        <div class="balance-section">
            <div class="balance-card fluxon" id="fluxonCard">
                <div class="balance-label">Total Fluxon</div>
                <div class="balance-value" id="fluxonBalance" data-value="<?php echo $total_fluxon; ?>"><?php echo number_format($total_fluxon); ?></div>
            </div>
            <div class="balance-card astrons" id="astronsCard">
                <div class="balance-label">Your Astrons</div>
                <div class="balance-value" id="astronsBalance" data-value="<?php echo $user_astrons; ?>"><?php echo number_format($user_astrons); ?></div>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="message">✓ <?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error">✗ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <h2 style="text-align: center; color: var(--primary-cyan); margin: 40px 0 20px; font-family: 'Orbitron', sans-serif;">Forward Trading: Fluxon → Astrons</h2>
        <div class="shop-grid">
            <?php 
            $colors = ['#00ffff', '#9d4edd', '#FFD700'];
            $icons = ['⚡', '⚡⚡', '⚡⚡⚡'];
            $badges = ['BASIC', 'STANDARD', 'PREMIUM'];
            foreach ($shop_prices as $index => $price): 
                $fluxon = intval($price['fluxon_amount']);
                $astrons = intval($price['astrons_reward']);
                $type = htmlspecialchars($price['claim_type']);
                $color = $colors[$index] ?? '#00ffff';
                $icon = $icons[$index] ?? '⚡';
                $badge = $badges[$index] ?? 'CLAIM';
                $ratio = round($fluxon / $astrons, 0);
                $can_afford = $total_fluxon >= $fluxon;
            ?>
            <div class="shop-item" style="border-color: <?php echo $color; ?>;">
                <div class="item-badge" style="background: linear-gradient(135deg, <?php echo $color; ?>, <?php echo $index == 2 ? '#FFA500' : $color; ?>);">
                    <?php echo $badge; ?>
                </div>
                <div class="item-icon" style="color: <?php echo $color; ?>;"><?php echo $icon; ?></div>
                <div class="item-name" style="color: <?php echo $color; ?>;"><?php echo $type; ?></div>
                
                <div class="item-details">
                    <div class="detail-row">
                        <span class="detail-label">Cost:</span>
                        <span class="detail-value"><?php echo number_format($fluxon); ?> Fluxon</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Reward:</span>
                        <span class="detail-value"><?php echo $astrons; ?> Astrons</span>
                    </div>
                    <div class="conversion-rate">
                        <?php echo number_format($ratio); ?> Fluxon per Astron
                    </div>
                </div>
                
                <form method="POST" id="purchase-form-<?php echo $price['id']; ?>" onsubmit="return handlePurchase(event, <?php echo $price['id']; ?>, <?php echo $fluxon; ?>, <?php echo $astrons; ?>);">
                    <input type="hidden" name="item_id" value="<?php echo $price['id']; ?>">
                    <input type="hidden" name="item_cost" value="<?php echo $fluxon; ?>">
                    <input type="hidden" name="item_astrons" value="<?php echo $astrons; ?>">
                    <button type="submit" name="purchase_item" id="purchase-btn-<?php echo $price['id']; ?>" class="purchase-btn" <?php echo !$can_afford ? 'disabled' : ''; ?>>
                        <?php echo $can_afford ? "Claim {$astrons} Astrons" : "Insufficient Fluxon"; ?>
                    </button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
        
        <h2 style="text-align: center; color: var(--primary-purple); margin: 60px 0 20px; font-family: 'Orbitron', sans-serif;">Reverse Trading: Astrons → Fluxon</h2>
        <div class="shop-grid">
            <?php 
            foreach ($shop_prices as $index => $price): 
                $astrons_cost = intval($price['astrons_cost'] ?? 0);
                $fluxon_reward = intval($price['fluxon_reward'] ?? 0);
                $type = htmlspecialchars($price['claim_type']);
                $color = $colors[$index] ?? '#00ffff';
                $icon = $icons[$index] ?? '⚡';
                $badge = $badges[$index] ?? 'TRADE';
                
                if ($astrons_cost > 0 && $fluxon_reward > 0) {
                    $reverse_ratio = round($fluxon_reward / $astrons_cost, 0);
                    $can_afford_reverse = $user_astrons >= $astrons_cost;
            ?>
            <div class="shop-item" style="border-color: <?php echo $color; ?>;">
                <div class="item-badge" style="background: linear-gradient(135deg, <?php echo $color; ?>, <?php echo $index == 2 ? '#FFA500' : $color; ?>);">
                    <?php echo $badge; ?>
                </div>
                <div class="item-icon" style="color: <?php echo $color; ?>;"><?php echo $icon; ?></div>
                <div class="item-name" style="color: <?php echo $color; ?>;"><?php echo $type; ?></div>
                
                <div class="item-details">
                    <div class="detail-row">
                        <span class="detail-label">Cost:</span>
                        <span class="detail-value"><?php echo number_format($astrons_cost); ?> Astrons</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Reward:</span>
                        <span class="detail-value"><?php echo number_format($fluxon_reward); ?> Fluxon</span>
                    </div>
                    <div class="conversion-rate">
                        <?php echo number_format($reverse_ratio); ?> Fluxon per Astron
                    </div>
                </div>
                
                <form method="POST" id="trade-form-<?php echo $price['id']; ?>" onsubmit="return handleTrade(event, <?php echo $price['id']; ?>, <?php echo $astrons_cost; ?>, <?php echo $fluxon_reward; ?>);">
                    <input type="hidden" name="item_id" value="<?php echo $price['id']; ?>">
                    <input type="hidden" name="item_astrons_cost" value="<?php echo $astrons_cost; ?>">
                    <input type="hidden" name="item_fluxon_reward" value="<?php echo $fluxon_reward; ?>">
                    <button type="submit" name="trade_astrons" id="trade-btn-<?php echo $price['id']; ?>" class="purchase-btn" style="background: linear-gradient(135deg, var(--primary-purple), #ff006e);" <?php echo !$can_afford_reverse ? 'disabled' : ''; ?>>
                        <?php echo $can_afford_reverse ? "Get {$fluxon_reward} Fluxon" : "Insufficient Astrons"; ?>
                    </button>
                </form>
            </div>
            <?php 
                }
            endforeach; ?>
        </div>
    </div>
    
    <script>
        // Initialize space background
        const spaceBg = document.getElementById('space-background');
        if (spaceBg && typeof createSpaceBackground === 'function') {
            createSpaceBackground();
        } else {
            // Fallback if function doesn't exist
            for (let i = 0; i < 100; i++) {
                const star = document.createElement('div');
                star.className = 'star';
                star.style.left = Math.random() * 100 + '%';
                star.style.top = Math.random() * 100 + '%';
                star.style.animationDelay = Math.random() * 3 + 's';
                spaceBg.appendChild(star);
            }
        }

        // Track processing state to prevent multiple simultaneous requests
        const processingState = new Set();
        
        // Animate value function
        function animateValue(element, start, end, duration, callback) {
            const startTime = performance.now();
            const startValue = start;
            const endValue = end;
            
            function update(currentTime) {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                
                // Easing function
                const easeProgress = 1 - Math.pow(1 - progress, 3);
                
                const current = Math.round(startValue + (endValue - startValue) * easeProgress);
                element.textContent = current.toLocaleString();
                
                if (progress < 1) {
                    requestAnimationFrame(update);
                } else {
                    element.textContent = endValue.toLocaleString();
                    if (callback) callback();
                }
            }
            
            requestAnimationFrame(update);
        }

        // Update purchase buttons based on Fluxon balance
        function updatePurchaseButtons(currentFluxon) {
            document.querySelectorAll('[id^="purchase-form-"]').forEach(form => {
                const formId = form.id;
                const itemId = formId.replace('purchase-form-', '');
                const button = document.getElementById('purchase-btn-' + itemId);
                const costInput = form.querySelector('input[name="item_cost"]');
                
                if (button && costInput) {
                    const cost = parseInt(costInput.value);
                    if (currentFluxon >= cost && !processingState.has(itemId)) {
                        button.disabled = false;
                        const astrons = form.querySelector('input[name="item_astrons"]').value;
                        button.textContent = `Claim ${astrons} Astrons`;
                    } else if (currentFluxon < cost) {
                        button.disabled = true;
                        button.textContent = 'Insufficient Fluxon';
                    }
                }
            });
        }

        // Handle purchase with AJAX - SINGLE CLICK ONLY
        async function handlePurchase(event, itemId, fluxonCost, astronsReward) {
            // Prevent default form submission
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            // Check if already processing this item
            if (processingState.has(itemId)) {
                console.log('Purchase already in progress for item:', itemId);
                return false;
            }
            
            const form = document.getElementById('purchase-form-' + itemId);
            const button = document.getElementById('purchase-btn-' + itemId);
            const fluxonElement = document.getElementById('fluxonBalance');
            const astronsElement = document.getElementById('astronsBalance');
            const fluxonCard = document.getElementById('fluxonCard');
            const astronsCard = document.getElementById('astronsCard');
            
            if (!form || !button || !fluxonElement || !astronsElement || !fluxonCard || !astronsCard) {
                console.error('Missing DOM elements for purchase handler.');
                return false;
            }
            
            // Check if button is already disabled/processing
            if (button.disabled && (button.textContent === 'Processing...' || button.textContent.includes('Processing'))) {
                console.log('Button already processing, preventing duplicate click.');
                return false;
            }
            
            // Get current values
            const currentFluxon = parseInt(fluxonElement.getAttribute('data-value')) || parseInt(fluxonElement.textContent.replace(/,/g, '')) || 0;
            const currentAstrons = parseInt(astronsElement.getAttribute('data-value')) || parseInt(astronsElement.textContent.replace(/,/g, '')) || 0;
            
            // Validate sufficient Fluxon
            if (currentFluxon < fluxonCost) {
                alert('Insufficient Fluxon Energy!');
                return false;
            }
            
            // Confirm purchase
            if (!confirm(`Claim ${astronsReward} Astrons for ${fluxonCost.toLocaleString()} Fluxon?`)) {
                return false;
            }
            
            // Mark as processing
            processingState.add(itemId);
            
            // Disable button immediately
            button.disabled = true;
            button.textContent = 'Processing...';
            
            // Create form data
            const formData = new FormData(form);
            formData.append('ajax', '1');
            
            try {
                const response = await fetch('shop.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: formData
                });
                
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                
                const contentType = response.headers.get('Content-Type') || '';
                let data;
                
                if (!contentType.includes('application/json')) {
                    // Try to parse as JSON anyway (in case header is wrong)
                    const responseText = await response.text();
                    console.error('Expected JSON but got:', contentType);
                    console.error('Response text:', responseText.substring(0, 200));
                    
                    try {
                        data = JSON.parse(responseText);
                    } catch (e) {
                        // If parsing fails, show user-friendly error
                        throw new Error('Server error: Unable to process your request. Please try again later.');
                    }
                } else {
                    data = await response.json();
                }
                
                if (data.success) {
                    const actualNewFluxon = parseInt(data.new_fluxon) || 0;
                    const actualNewAstrons = parseInt(data.new_astrons) || 0;
                    
                    console.log('Purchase successful:', {
                        previousFluxon: currentFluxon,
                        newFluxon: actualNewFluxon,
                        fluxonDeducted: data.fluxon_deducted,
                        previousAstrons: currentAstrons,
                        newAstrons: actualNewAstrons,
                        astronsAdded: data.astrons_added
                    });
                    
                    // Add pulse animation
                    fluxonCard.classList.add('animating');
                    astronsCard.classList.add('animating');
                    
                    // Animate Fluxon decrease
                    animateValue(fluxonElement, currentFluxon, actualNewFluxon, 1200, function() {
                        fluxonElement.setAttribute('data-value', actualNewFluxon);
                        fluxonElement.textContent = actualNewFluxon.toLocaleString();
                        fluxonCard.classList.remove('animating');
                        
                        // Animate Astrons increase
                        animateValue(astronsElement, currentAstrons, actualNewAstrons, 1200, function() {
                            astronsElement.setAttribute('data-value', actualNewAstrons);
                            astronsElement.textContent = actualNewAstrons.toLocaleString();
                            astronsCard.classList.remove('animating');
                            
                            // Show success message
                            const alertDiv = document.createElement('div');
                            alertDiv.className = 'message';
                            alertDiv.innerHTML = '✓ ' + data.message;
                            const header = document.querySelector('.shop-header');
                            const container = document.querySelector('.shop-page');
                            if (header && container) {
                                container.insertBefore(alertDiv, header.nextSibling);
                                setTimeout(function() {
                                    alertDiv.style.opacity = '0';
                                    alertDiv.style.transition = 'opacity 0.5s';
                                    setTimeout(() => alertDiv.remove(), 500);
                                }, 3000);
                            }
                            
                            // Update all purchase buttons
                            updatePurchaseButtons(actualNewFluxon);
                            
                            // Remove from processing state and re-enable button
                            processingState.delete(itemId);
                            button.disabled = false;
                            const astrons = form.querySelector('input[name="item_astrons"]').value;
                            button.textContent = `Claim ${astrons} Astrons`;
                        });
                    });
                } else {
                    // Show error message in alert
                    const errorMsg = data.message || 'Purchase failed. Please try again.';
                    alert('Error: ' + errorMsg);
                    
                    // Also show error message on page
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'error';
                    alertDiv.style.cssText = 'max-width: 600px; margin: 20px auto; padding: 15px; background: rgba(255, 0, 0, 0.1); border: 2px solid #ff0000; color: #ff0000; border-radius: 10px; text-align: center;';
                    alertDiv.innerHTML = '✗ ' + errorMsg;
                    const header = document.querySelector('.shop-header');
                    const container = document.querySelector('.shop-page');
                    if (header && container) {
                        container.insertBefore(alertDiv, header.nextSibling);
                        setTimeout(function() {
                            alertDiv.style.opacity = '0';
                            alertDiv.style.transition = 'opacity 0.5s';
                            setTimeout(() => alertDiv.remove(), 500);
                        }, 5000);
                    }
                    
                    // Remove from processing state and re-enable button
                    processingState.delete(itemId);
                    button.disabled = false;
                    const astrons = form.querySelector('input[name="item_astrons"]').value;
                    button.textContent = `Claim ${astrons} Astrons`;
                }
            } catch (error) {
                console.error('Error during purchase:', error);
                
                // Show user-friendly error message
                const errorMessage = error.message || 'An unexpected error occurred. Please try again.';
                alert('Error: ' + errorMessage);
                
                // Also show error message on page
                const alertDiv = document.createElement('div');
                alertDiv.className = 'error';
                alertDiv.style.cssText = 'max-width: 600px; margin: 20px auto; padding: 15px; background: rgba(255, 0, 0, 0.1); border: 2px solid #ff0000; color: #ff0000; border-radius: 10px; text-align: center;';
                alertDiv.innerHTML = '✗ ' + errorMessage;
                const header = document.querySelector('.shop-header');
                const container = document.querySelector('.shop-page');
                if (header && container) {
                    container.insertBefore(alertDiv, header.nextSibling);
                    setTimeout(function() {
                        alertDiv.style.opacity = '0';
                        alertDiv.style.transition = 'opacity 0.5s';
                        setTimeout(() => alertDiv.remove(), 500);
                    }, 5000);
                }
                
                // Remove from processing state and re-enable button
                processingState.delete(itemId);
                button.disabled = false;
                const astrons = form.querySelector('input[name="item_astrons"]').value;
                button.textContent = `Claim ${astrons} Astrons`;
            }
            
            return false; // Prevent form submission
        }

        // Handle reverse trade (Astrons → Fluxon) with AJAX
        async function handleTrade(event, itemId, astronsCost, fluxonReward) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            if (processingState.has('trade-' + itemId)) {
                console.log('Trade already in progress for item:', itemId);
                return false;
            }
            
            const form = document.getElementById('trade-form-' + itemId);
            const button = document.getElementById('trade-btn-' + itemId);
            const fluxonElement = document.getElementById('fluxonBalance');
            const astronsElement = document.getElementById('astronsBalance');
            const fluxonCard = document.getElementById('fluxonCard');
            const astronsCard = document.getElementById('astronsCard');
            
            if (!form || !button || !fluxonElement || !astronsElement || !fluxonCard || !astronsCard) {
                console.error('Missing DOM elements for trade handler.');
                return false;
            }
            
            if (button.disabled && (button.textContent === 'Processing...' || button.textContent.includes('Processing'))) {
                return false;
            }
            
            const currentFluxon = parseInt(fluxonElement.getAttribute('data-value')) || parseInt(fluxonElement.textContent.replace(/,/g, '')) || 0;
            const currentAstrons = parseInt(astronsElement.getAttribute('data-value')) || parseInt(astronsElement.textContent.replace(/,/g, '')) || 0;
            
            if (currentAstrons < astronsCost) {
                alert('Insufficient Astrons!');
                return false;
            }
            
            if (!confirm(`Trade ${astronsCost} Astrons for ${fluxonReward.toLocaleString()} Fluxon?`)) {
                return false;
            }
            
            processingState.add('trade-' + itemId);
            button.disabled = true;
            button.textContent = 'Processing...';
            
            const formData = new FormData(form);
            formData.append('ajax', '1');
            
            try {
                const response = await fetch('shop.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: formData
                });
                
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                
                const contentType = response.headers.get('Content-Type') || '';
                let data;
                
                if (!contentType.includes('application/json')) {
                    const responseText = await response.text();
                    try {
                        data = JSON.parse(responseText);
                    } catch (e) {
                        throw new Error('Server error: Unable to process your request. Please try again later.');
                    }
                } else {
                    data = await response.json();
                }
                
                if (data.success) {
                    const actualNewFluxon = parseInt(data.new_fluxon) || 0;
                    const actualNewAstrons = parseInt(data.new_astrons) || 0;
                    
                    fluxonCard.classList.add('animating');
                    astronsCard.classList.add('animating');
                    
                    animateValue(fluxonElement, currentFluxon, actualNewFluxon, 1200, function() {
                        fluxonElement.setAttribute('data-value', actualNewFluxon);
                        fluxonElement.textContent = actualNewFluxon.toLocaleString();
                        fluxonCard.classList.remove('animating');
                        
                        animateValue(astronsElement, currentAstrons, actualNewAstrons, 1200, function() {
                            astronsElement.setAttribute('data-value', actualNewAstrons);
                            astronsElement.textContent = actualNewAstrons.toLocaleString();
                            astronsCard.classList.remove('animating');
                            
                            const alertDiv = document.createElement('div');
                            alertDiv.className = 'message';
                            alertDiv.innerHTML = '✓ ' + data.message;
                            const header = document.querySelector('.shop-header');
                            const container = document.querySelector('.shop-page');
                            if (header && container) {
                                container.insertBefore(alertDiv, header.nextSibling);
                                setTimeout(function() {
                                    alertDiv.style.opacity = '0';
                                    alertDiv.style.transition = 'opacity 0.5s';
                                    setTimeout(() => alertDiv.remove(), 500);
                                }, 3000);
                            }
                            
                            updatePurchaseButtons(actualNewFluxon);
                            processingState.delete('trade-' + itemId);
                            button.disabled = false;
                            button.textContent = `Get ${fluxonReward} Fluxon`;
                        });
                    });
                } else {
                    alert('Error: ' + (data.message || 'Trade failed'));
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'error';
                    alertDiv.style.cssText = 'max-width: 600px; margin: 20px auto; padding: 15px; background: rgba(255, 0, 0, 0.1); border: 2px solid #ff0000; color: #ff0000; border-radius: 10px; text-align: center;';
                    alertDiv.innerHTML = '✗ ' + (data.message || 'Trade failed');
                    const header = document.querySelector('.shop-header');
                    const container = document.querySelector('.shop-page');
                    if (header && container) {
                        container.insertBefore(alertDiv, header.nextSibling);
                        setTimeout(function() {
                            alertDiv.style.opacity = '0';
                            alertDiv.style.transition = 'opacity 0.5s';
                            setTimeout(() => alertDiv.remove(), 500);
                        }, 5000);
                    }
                    
                    processingState.delete('trade-' + itemId);
                    button.disabled = false;
                    button.textContent = `Get ${fluxonReward} Fluxon`;
                }
            } catch (error) {
                console.error('Error during trade:', error);
                alert('Error: ' + (error.message || 'An unexpected error occurred. Please try again.'));
                
                processingState.delete('trade-' + itemId);
                button.disabled = false;
                button.textContent = `Get ${fluxonReward} Fluxon`;
            }
            
            return false;
        }
    </script>
</body>
</html>
