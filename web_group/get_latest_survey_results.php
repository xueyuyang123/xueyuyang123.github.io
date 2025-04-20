<?php
require_once 'config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$filter = $_GET['filter'] ?? 'all';

try {
    $conn = getDBConnection();
    $surveys = [];

    if ($filter === 'all') {
        // Fetch all surveys grouped by survey_metadata_id
        $stmt = $conn->prepare("
            SELECT sm.survey_metadata_id, sm.title AS question, sm.created_at,
                   MIN(s.image_path) AS image_path, 
                   COUNT(DISTINCT v.vote_id) AS total_votes, u.username AS creator
            FROM surveys_metadata sm
            JOIN surveys s ON sm.survey_metadata_id = s.survey_metadata_id
            LEFT JOIN survey_options so ON s.survey_id = so.survey_id
            LEFT JOIN survey_votes v ON so.option_id = v.option_id
            LEFT JOIN users u ON sm.user_id = u.user_id
            GROUP BY sm.survey_metadata_id
            ORDER BY sm.created_at DESC
        ");
        $stmt->execute();
        $surveys = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch all questions and their options for each survey
        foreach ($surveys as &$survey) {
            // Fetch questions
            $stmt = $conn->prepare("
                SELECT s.survey_id, s.question
                FROM surveys s
                WHERE s.survey_metadata_id = ?
                ORDER BY s.survey_id
            ");
            $stmt->execute([$survey['survey_metadata_id']]);
            $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $survey['questions'] = [];
            foreach ($questions as &$question) {
                // Fetch options for each question
                $stmt = $conn->prepare("
                    SELECT so.option_id, so.option_text, COUNT(v.vote_id) AS vote_count
                    FROM survey_options so
                    LEFT JOIN survey_votes v ON so.option_id = v.option_id
                    WHERE so.survey_id = ?
                    GROUP BY so.option_id
                    ORDER BY vote_count DESC
                ");
                $stmt->execute([$question['survey_id']]);
                $options = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Calculate percentages
                $question_total_votes = array_sum(array_column($options, 'vote_count'));
                foreach ($options as &$option) {
                    $option['percentage'] = $question_total_votes > 0 
                        ? ($option['vote_count'] / $question_total_votes) * 100 
                        : 0;
                }

                // Fetch "Other" responses
                $stmt = $conn->prepare("
                    SELECT v.other_text
                    FROM survey_votes v
                    JOIN survey_options so ON v.option_id = so.option_id
                    WHERE v.survey_id = ? AND so.option_text = 'Other' AND v.other_text IS NOT NULL
                ");
                $stmt->execute([$question['survey_id']]);
                $other_responses = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'other_text');

                $question['options'] = $options;
                $question['other_responses'] = $other_responses;
                $survey['questions'][] = $question;
            }
        }
    } elseif ($filter === 'created') {
        // Fetch surveys created by the user
        $stmt = $conn->prepare("
            SELECT sm.survey_metadata_id, sm.title AS question, sm.created_at,
                   MIN(s.image_path) AS image_path, 
                   COUNT(DISTINCT v.vote_id) AS total_votes, u.username AS creator
            FROM surveys_metadata sm
            JOIN surveys s ON sm.survey_metadata_id = s.survey_metadata_id
            LEFT JOIN survey_options so ON s.survey_id = so.survey_id
            LEFT JOIN survey_votes v ON so.option_id = v.option_id
            LEFT JOIN users u ON sm.user_id = u.user_id
            WHERE sm.user_id = ?
            GROUP BY sm.survey_metadata_id
            ORDER BY sm.created_at DESC
        ");
        $stmt->execute([$user_id]);
        $surveys = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($surveys as &$survey) {
            $stmt = $conn->prepare("
                SELECT s.survey_id, s.question
                FROM surveys s
                WHERE s.survey_metadata_id = ?
                ORDER BY s.survey_id
            ");
            $stmt->execute([$survey['survey_metadata_id']]);
            $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $survey['questions'] = [];
            foreach ($questions as &$question) {
                $stmt = $conn->prepare("
                    SELECT so.option_id, so.option_text, COUNT(v.vote_id) AS vote_count
                    FROM survey_options so
                    LEFT JOIN survey_votes v ON so.option_id = v.option_id
                    WHERE so.survey_id = ?
                    GROUP BY so.option_id
                    ORDER BY vote_count DESC
                ");
                $stmt->execute([$question['survey_id']]);
                $options = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $question_total_votes = array_sum(array_column($options, 'vote_count'));
                foreach ($options as &$option) {
                    $option['percentage'] = $question_total_votes > 0 
                        ? ($option['vote_count'] / $question_total_votes) * 100 
                        : 0;
                }

                $stmt = $conn->prepare("
                    SELECT v.other_text
                    FROM survey_votes v
                    JOIN survey_options so ON v.option_id = so.option_id
                    WHERE v.survey_id = ? AND so.option_text = 'Other' AND v.other_text IS NOT NULL
                ");
                $stmt->execute([$question['survey_id']]);
                $other_responses = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'other_text');

                $question['options'] = $options;
                $question['other_responses'] = $other_responses;
                $survey['questions'][] = $question;
            }
        }
    } elseif ($filter === 'voted') {
        // Only show surveys the user has actually voted in
        $stmt = $conn->prepare("
            SELECT DISTINCT
                sm.survey_metadata_id, 
                sm.title AS question, 
                sm.created_at,
                MIN(s.image_path) AS image_path, 
                (
                    SELECT COUNT(DISTINCT v2.vote_id)
                    FROM survey_votes v2
                    JOIN survey_options so2 ON v2.option_id = so2.option_id
                    JOIN surveys s2 ON so2.survey_id = s2.survey_id
                    WHERE s2.survey_metadata_id = sm.survey_metadata_id
                ) AS total_votes,
                u.username AS creator
            FROM survey_votes v
            JOIN survey_options so ON v.option_id = so.option_id
            JOIN surveys s ON so.survey_id = s.survey_id
            JOIN surveys_metadata sm ON s.survey_metadata_id = sm.survey_metadata_id
            JOIN users u ON sm.user_id = u.user_id
            WHERE v.user_id = ?
            GROUP BY sm.survey_metadata_id
            ORDER BY sm.created_at DESC
        ");
        $stmt->execute([$user_id]);
        $surveys = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($surveys as &$survey) {
            $stmt = $conn->prepare("
                SELECT s.survey_id, s.question
                FROM surveys s
                WHERE s.survey_metadata_id = ?
                ORDER BY s.survey_id
            ");
            $stmt->execute([$survey['survey_metadata_id']]);
            $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $survey['questions'] = [];
            foreach ($questions as &$question) {
                $stmt = $conn->prepare("
                    SELECT so.option_id, so.option_text, COUNT(v.vote_id) AS vote_count
                    FROM survey_options so
                    LEFT JOIN survey_votes v ON so.option_id = v.option_id
                    WHERE so.survey_id = ?
                    GROUP BY so.option_id
                    ORDER BY vote_count DESC
                ");
                $stmt->execute([$question['survey_id']]);
                $options = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $question_total_votes = array_sum(array_column($options, 'vote_count'));
                foreach ($options as &$option) {
                    $option['percentage'] = $question_total_votes > 0 
                        ? ($option['vote_count'] / $question_total_votes) * 100 
                        : 0;
                }

                $stmt = $conn->prepare("
                    SELECT v.other_text
                    FROM survey_votes v
                    JOIN survey_options so ON v.option_id = so.option_id
                    WHERE v.survey_id = ? AND so.option_text = 'Other' AND v.other_text IS NOT NULL
                ");
                $stmt->execute([$question['survey_id']]);
                $other_responses = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'other_text');

                $question['options'] = $options;
                $question['other_responses'] = $other_responses;
                $survey['questions'][] = $question;
            }
        }
    }

    echo json_encode(['success' => true, 'surveys' => $surveys]);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>