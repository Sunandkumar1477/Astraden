<?php
session_start();
require_once 'connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['has_profile' => false, 'profile_photo' => null]);
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if user has created a profile and get profile_photo
$stmt = $conn->prepare("SELECT id, profile_photo FROM user_profile WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$has_profile = $result->num_rows > 0;
$profile_photo = null;

if ($has_profile) {
    $profile = $result->fetch_assoc();
    $profile_photo = $profile['profile_photo'] ?? null;
}

$stmt->close();
$conn->close();

echo json_encode([
    'has_profile' => $has_profile,
    'profile_photo' => $profile_photo
]);
?>
