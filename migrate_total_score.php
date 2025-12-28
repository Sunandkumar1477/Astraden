<?php
/**
 * Migration Script: Initialize total_score in users table
 * 
 * This script:
 * 1. Adds total_score column to users table if it doesn't exist
 * 2. Initializes total_score for all existing users based on game_leaderboard
 * 3. Creates index for better performance
 * 
 * Run this script once after adding the total_score column
 */

require_once 'connection.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Total Score Migration</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .success { color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0; }
        .error { color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0; }
        .info { color: blue; padding: 10px; background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 5px; margin: 10px 0; }
        h1 { color: #333; }
    </style>
</head>
<body>
    <h1>Total Score Migration Script</h1>";

try {
    // Step 1: Check if total_score column exists
    $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'total_score'");
    $column_exists = ($check_column && $check_column->num_rows > 0);
    if ($check_column) $check_column->close();
    
    if (!$column_exists) {
        echo "<div class='info'>Adding total_score column to users table...</div>";
        
        // Add total_score column
        $alter_sql = "ALTER TABLE users ADD COLUMN total_score BIGINT(20) NOT NULL DEFAULT 0 COMMENT 'Total score (Fluxon) - sum of all scores from game_leaderboard'";
        if ($conn->query($alter_sql)) {
            echo "<div class='success'>✓ Column 'total_score' added successfully!</div>";
        } else {
            throw new Exception("Failed to add column: " . $conn->error);
        }
        
        // Create index
        $index_sql = "CREATE INDEX idx_total_score ON users(total_score DESC)";
        if ($conn->query($index_sql)) {
            echo "<div class='success'>✓ Index created successfully!</div>";
        } else {
            echo "<div class='info'>Index may already exist (this is okay)</div>";
        }
    } else {
        echo "<div class='info'>Column 'total_score' already exists. Proceeding with data migration...</div>";
    }
    
    // Step 2: Initialize total_score for all users
    echo "<div class='info'>Initializing total_score for all users...</div>";
    
    $update_sql = "UPDATE users u
                   SET total_score = COALESCE((
                       SELECT SUM(score) 
                       FROM game_leaderboard gl 
                       WHERE gl.user_id = u.id
                   ), 0)";
    
    if ($conn->query($update_sql)) {
        $affected = $conn->affected_rows;
        echo "<div class='success'>✓ Successfully updated total_score for {$affected} users!</div>";
    } else {
        throw new Exception("Failed to update total_score: " . $conn->error);
    }
    
    // Step 3: Verify the migration
    echo "<div class='info'>Verifying migration...</div>";
    
    $verify_sql = "SELECT 
                    COUNT(*) as total_users,
                    SUM(CASE WHEN total_score > 0 THEN 1 ELSE 0 END) as users_with_score,
                    SUM(total_score) as total_scores_sum
                   FROM users";
    
    $verify_result = $conn->query($verify_sql);
    if ($verify_result) {
        $verify_data = $verify_result->fetch_assoc();
        echo "<div class='success'>";
        echo "✓ Verification Results:<br>";
        echo "- Total users: " . $verify_data['total_users'] . "<br>";
        echo "- Users with score > 0: " . $verify_data['users_with_score'] . "<br>";
        echo "- Sum of all total_scores: " . number_format($verify_data['total_scores_sum']) . "<br>";
        echo "</div>";
    }
    
    // Step 4: Compare with game_leaderboard sum
    $compare_sql = "SELECT 
                    (SELECT SUM(total_score) FROM users) as users_sum,
                    (SELECT SUM(score) FROM game_leaderboard) as leaderboard_sum";
    $compare_result = $conn->query($compare_sql);
    if ($compare_result) {
        $compare_data = $compare_result->fetch_assoc();
        $users_sum = intval($compare_data['users_sum']);
        $leaderboard_sum = intval($compare_data['leaderboard_sum']);
        
        if ($users_sum == $leaderboard_sum) {
            echo "<div class='success'>✓ Data integrity verified! Users total matches game_leaderboard sum.</div>";
        } else {
            echo "<div class='error'>⚠ Warning: Sum mismatch detected. Users sum: {$users_sum}, Leaderboard sum: {$leaderboard_sum}</div>";
            echo "<div class='info'>This might be normal if there are negative scores (shop purchases).</div>";
        }
    }
    
    echo "<div class='success'><strong>Migration completed successfully!</strong></div>";
    echo "<div class='info'>You can now use users.total_score instead of calculating SUM() from game_leaderboard.</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</body></html>";

$conn->close();
?>

