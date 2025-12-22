<?php
require_once 'connection.php';

// Set header for better display
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Favicon - Must be early in head for proper display -->
    <link rel="icon" type="image/svg+xml" href="Alogo.svg">
    <link rel="shortcut icon" type="image/svg+xml" href="Alogo.svg">
    <link rel="alternate icon" type="image/png" href="Alogo.svg">
    <link rel="apple-touch-icon" sizes="180x180" href="Alogo.svg">
    <link rel="icon" type="image/svg+xml" sizes="any" href="Alogo.svg">
    <title>Create Credit Packages Table - Games Hub</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #1a1a2e;
            color: #00ffff;
        }
        .success { color: #00ff00; }
        .error { color: #ff0000; }
        .info { color: #ffff00; }
        h1, h2 { color: #00ffff; }
        a { color: #00ffff; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <h1>🚀 Create Credit Packages Table</h1>
    
<?php
echo "<h2>Creating credit_packages table...</h2>";

// Create credit_packages table
$sql = "CREATE TABLE IF NOT EXISTS credit_packages (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    credit_amount INT(11) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    is_popular TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    display_order INT(11) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_display_order (display_order),
    UNIQUE KEY unique_credit_amount (credit_amount)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql) === TRUE) {
    echo "<p class='success'>✓ Table 'credit_packages' created successfully!</p>";
    
    // Insert default credit packages if table is empty
    $check_stmt = $conn->query("SELECT COUNT(*) as count FROM credit_packages");
    $check_result = $check_stmt->fetch_assoc();
    
    if ($check_result['count'] == 0) {
        echo "<h2>Inserting default credit packages...</h2>";
        
        $default_packages = [
            ['credit_amount' => 100, 'price' => 100.00, 'is_popular' => 0, 'display_order' => 1],
            ['credit_amount' => 150, 'price' => 150.00, 'is_popular' => 1, 'display_order' => 2]
        ];
        
        $insert_stmt = $conn->prepare("INSERT INTO credit_packages (credit_amount, price, is_popular, display_order) VALUES (?, ?, ?, ?)");
        
        foreach ($default_packages as $package) {
            $insert_stmt->bind_param("idii", 
                $package['credit_amount'], 
                $package['price'], 
                $package['is_popular'], 
                $package['display_order']
            );
            
            if ($insert_stmt->execute()) {
                echo "<p class='success'>✓ Inserted {$package['credit_amount']} Credits = ₹{$package['price']}/-</p>";
            } else {
                echo "<p class='error'>✗ Error inserting {$package['credit_amount']} Credits: " . $insert_stmt->error . "</p>";
            }
        }
        
        $insert_stmt->close();
    } else {
        echo "<p class='info'>ℹ Credit packages already exist in the database.</p>";
    }
    
    echo "<h2>✅ Setup Complete!</h2>";
    echo "<p><a href='admin_credit_pricing.php'>Go to Credit Pricing Management</a></p>";
    echo "<p><a href='admin_dashboard.php'>Go to Admin Dashboard</a></p>";
    
} else {
    echo "<p class='error'>✗ Error creating credit_packages table: " . $conn->error . "</p>";
}

$conn->close();
?>
</body>
</html>



