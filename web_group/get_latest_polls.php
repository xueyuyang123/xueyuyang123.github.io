<?php
require_once 'config.php';

header('Content-Type: application/json');

try {
    $conn = getDBConnection();
    
    // Fetch the latest polls with creator information
    $stmt = $conn->prepare("
        SELECT p.poll_id, p.question, p.image_path, p.created_at, u.username AS creator
        FROM polls p
        JOIN users u ON p.user_id = u.user_id
        ORDER BY p.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $polls = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['polls' => $polls]);
} catch(PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    http_response_code(500);
}
?>