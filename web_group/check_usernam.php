<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_GET['username'])) {
    echo json_encode(['error' => 'Username parameter is missing']);
    exit();
}

$username = trim($_GET['username']);

try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    
    echo json_encode([
        'available' => $stmt->rowCount() === 0,
        'username' => $username
    ]);
} catch(PDOException $e) {
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>