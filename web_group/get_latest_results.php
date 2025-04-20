<?php
require_once 'config.php';

header('Content-Type: application/json');

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$filter = $_GET['filter'] ?? 'all';

try {
    $conn = getDBConnection();
    $polls = [];

    if ($filter === 'all') {
        // Query all votes
        $stmt = $conn->prepare("
            SELECT p.poll_id, p.question, p.image_path, COUNT(DISTINCT v.vote_id) AS total_votes, u.username AS creator
            FROM polls p
            LEFT JOIN poll_options po ON p.poll_id = po.poll_id
            LEFT JOIN poll_votes v ON po.option_id = v.option_id
            LEFT JOIN users u ON p.user_id = u.user_id
            GROUP BY p.poll_id
            ORDER BY p.created_at DESC
        ");
        $stmt->execute();
        $polls = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Retrieve the options and vote counts for each vote
        foreach ($polls as &$poll) {
            $stmt = $conn->prepare("
                SELECT po.option_id AS option_id, po.option_text AS option_text, COUNT(v.vote_id) AS vote_count
                FROM poll_options po
                LEFT JOIN poll_votes v ON po.option_id = v.option_id
                WHERE po.poll_id = ?
                GROUP BY po.option_id
                ORDER BY vote_count DESC
            ");
            $stmt->execute([$poll['poll_id']]);
            $poll['options'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate percentage
            foreach ($poll['options'] as &$option) {
                $option['percentage'] = $poll['total_votes'] > 0 
                    ? ($option['vote_count'] / $poll['total_votes']) * 100 
                    : 0;
            }
        }
    } elseif ($filter === 'created') {
        // Query the votes created by the user
        $stmt = $conn->prepare("
            SELECT p.poll_id, p.question, p.image_path, COUNT(DISTINCT v.vote_id) AS total_votes, u.username AS creator
            FROM polls p
            LEFT JOIN poll_options po ON p.poll_id = po.poll_id
            LEFT JOIN poll_votes v ON po.option_id = v.option_id
            LEFT JOIN users u ON p.user_id = u.user_id
            WHERE p.user_id = ?
            GROUP BY p.poll_id
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$user_id]);
        $polls = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($polls as &$poll) {
            $stmt = $conn->prepare("
                SELECT po.option_id AS option_id, po.option_text AS option_text, COUNT(v.vote_id) AS vote_count
                FROM poll_options po
                LEFT JOIN poll_votes v ON po.option_id = v.option_id
                WHERE po.poll_id = ?
                GROUP BY po.option_id
                ORDER BY vote_count DESC
            ");
            $stmt->execute([$poll['poll_id']]);
            $poll['options'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($poll['options'] as &$option) {
                $option['percentage'] = $poll['total_votes'] > 0 
                    ? ($option['vote_count'] / $poll['total_votes']) * 100 
                    : 0;
            }
        }
    } elseif ($filter === 'voted') {
        // Only show polls the user has actually voted in
        $stmt = $conn->prepare("
            SELECT DISTINCT 
                p.poll_id, 
                p.question, 
                p.image_path,
                u.username AS creator,
                (
                    SELECT COUNT(DISTINCT v2.vote_id)
                    FROM poll_votes v2
                    JOIN poll_options po2 ON v2.option_id = po2.option_id
                    WHERE po2.poll_id = p.poll_id
                ) AS total_votes
            FROM poll_votes v
            JOIN poll_options po ON v.option_id = po.option_id
            JOIN polls p ON po.poll_id = p.poll_id
            JOIN users u ON p.user_id = u.user_id
            WHERE v.user_id = ?
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$user_id]);
        $polls = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch options for each poll
        foreach ($polls as &$poll) {
            $stmt = $conn->prepare("
                SELECT po.option_id, po.option_text, COUNT(v.vote_id) AS vote_count
                FROM poll_options po
                LEFT JOIN poll_votes v ON po.option_id = v.option_id
                WHERE po.poll_id = ?
                GROUP BY po.option_id
                ORDER BY vote_count DESC
            ");
            $stmt->execute([$poll['poll_id']]);
            $poll['options'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($poll['options'] as &$option) {
                $option['percentage'] = $poll['total_votes'] > 0 
                    ? ($option['vote_count'] / $poll['total_votes']) * 100 
                    : 0;
            }
        }
    }

    echo json_encode(['success' => true, 'polls' => $polls]);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>