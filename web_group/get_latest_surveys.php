<?php
require_once 'config.php';

header('Content-Type: application/json');

try {
    $conn = getDBConnection();
    
    // Fetch the latest surveys with their metadata
    $stmt = $conn->prepare("
        SELECT sm.survey_metadata_id, sm.title, s.survey_id, s.question, s.image_path, s.created_at, u.username AS creator
        FROM surveys_metadata sm
        JOIN surveys s ON sm.survey_metadata_id = s.survey_metadata_id
        JOIN users u ON sm.user_id = u.user_id
        ORDER BY s.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $surveys = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group surveys by survey_metadata_id to avoid duplicate titles
    $survey_groups = [];
    foreach ($surveys as $survey) {
        $metadata_id = $survey['survey_metadata_id'];
        if (!isset($survey_groups[$metadata_id])) {
            $survey_groups[$metadata_id] = [
                'survey_metadata_id' => $survey['survey_metadata_id'],
                'title' => $survey['title'],
                'creator' => $survey['creator'],
                'created_at' => $survey['created_at'],
                'image_path' => $survey['image_path'] ?: 'Uploads/image.png',
                'questions' => []
            ];
        }
        $survey_groups[$metadata_id]['questions'][] = [
            'survey_id' => $survey['survey_id'],
            'question' => $survey['question']
        ];
    }
    
    // Convert to indexed array
    $surveys = array_values($survey_groups);
    
    echo json_encode(['surveys' => $surveys]);
    
} catch(PDOException $e) {
    error_log("Failed to fetch surveys: " . $e->getMessage());
    echo json_encode(['error' => 'Failed to fetch surveys']);
}
?>