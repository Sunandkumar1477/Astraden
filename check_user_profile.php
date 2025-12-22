<?php
session_start();
require_once 'connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['has_profile' => false]);
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if user has created a profile
$stmt = $conn->prepare("SELECT id FROM user_profile WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$has_profile = $result->num_rows > 0;
$stmt->close();
$conn->close();

echo json_encode(['has_profile' => $has_profile]);
?>

