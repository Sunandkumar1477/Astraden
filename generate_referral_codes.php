<?php
// Script to generate referral codes for existing users who don't have one
require_once 'connection.php';

function generateReferralCode($conn) {
    $max_attempts = 100;
    $attempts = 0;
    
    while ($attempts < $max_attempts) {
        // Generate random 4-digit alphanumeric code
        $code = strtoupper(substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 4));
        
        // Check if code already exists
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE referral_code = ?");
        $check_stmt->bind_param("s", $code);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows == 0) {
            $check_stmt->close();
            return $code;
        }
        
        $check_stmt->close();
        $attempts++;
    }
    
    // Fallback: use timestamp-based code if random fails
    return strtoupper(substr(md5(uniqid(rand(), true)), 0, 4));
}

// Get all users without referral codes
$stmt = $conn->query("SELECT id, username FROM users WHERE referral_code IS NULL OR referral_code = ''");
$users = $stmt->fetch_all(MYSQLI_ASSOC);

$updated = 0;
$errors = 0;

foreach ($users as $user) {
    $code = generateReferralCode($conn);
    $update_stmt = $conn->prepare("UPDATE users SET referral_code = ? WHERE id = ?");
    $update_stmt->bind_param("si", $code, $user['id']);
    
    if ($update_stmt->execute()) {
        $updated++;
        echo "✓ Generated code {$code} for user {$user['username']} (ID: {$user['id']})<br>";
    } else {
        $errors++;
        echo "✗ Failed to generate code for user {$user['username']} (ID: {$user['id']})<br>";
    }
    $update_stmt->close();
}

echo "<br><strong>Completed:</strong> {$updated} users updated, {$errors} errors.<br>";
$conn->close();
?>



