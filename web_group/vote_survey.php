<?php
require_once 'config.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?" . htmlspecialchars(SID));
    exit();
}

// Check if survey metadata ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: home.php?" . htmlspecialchars(SID));
    exit();
}

$survey_metadata_id = (int)$_GET['id'];
$error = '';
$success = '';
$survey_metadata = null;
$questions = [];
$user = null;

try {
    $conn = getDBConnection();
    
    // Get survey metadata
    $stmt = $conn->prepare("
        SELECT sm.*, u.username AS creator
        FROM surveys_metadata sm
        JOIN users u ON sm.user_id = u.user_id
        WHERE sm.survey_metadata_id = ?
    ");
    $stmt->execute([$survey_metadata_id]);
    $survey_metadata = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$survey_metadata) {
        throw new Exception("The survey does not exist or has been deleted.");
    }
    
    // Get all questions for the survey
    $stmt = $conn->prepare("
        SELECT s.*, u.username AS creator
        FROM surveys s
        JOIN users u ON s.user_id = u.user_id
        WHERE s.survey_metadata_id = ?
        ORDER BY s.survey_id
    ");
    $stmt->execute([$survey_metadata_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($questions)) {
        throw new Exception("This survey has no questions.");
    }
    
    // Fetch options for each question
    foreach ($questions as &$question) {
        $stmt = $conn->prepare("SELECT * FROM survey_options WHERE survey_id = ? ORDER BY option_id");
        $stmt->execute([$question['survey_id']]);
        $question['options'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($question['options'])) {
            throw new Exception("Question ID {$question['survey_id']} has no available options.");
        }
    }
    unset($question);
    
    // Check if the user has already voted for any question
    foreach ($questions as $question) {
        $stmt = $conn->prepare("SELECT 1 FROM survey_votes WHERE user_id = ? AND survey_id = ?");
        $stmt->execute([$_SESSION['user_id'], $question['survey_id']]);
        if ($stmt->fetch()) {
            echo "<script>
                alert('You have already participated in this survey and cannot vote again.');
                window.location.href = 'home.php';
            </script>";
            exit();
        }
    }
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $conn->beginTransaction();
        try {
            $stmt_insert = $conn->prepare("INSERT INTO survey_votes (user_id, survey_id, option_id, other_text) VALUES (?, ?, ?, ?)");
            
            foreach ($questions as $question) {
                $survey_id = $question['survey_id'];
                $is_multiple = $question['is_multiple'];
                
                // Check if options were selected for this question
                if (!isset($_POST['options'][$survey_id])) {
                    throw new Exception("Please select at least one option for question: " . htmlspecialchars($question['question']));
                }
                
                $selected_options = is_array($_POST['options'][$survey_id]) ? $_POST['options'][$survey_id] : [$_POST['options'][$survey_id]];
                
                // For single-choice questions, ensure only one option is selected
                if (!$is_multiple && count($selected_options) > 1) {
                    throw new Exception("Please select only one option for question: " . htmlspecialchars($question['question']));
                }
                
                // Find the "Other" option ID
                $other_option_id = null;
                foreach ($question['options'] as $option) {
                    if ($option['option_text'] === 'Other') {
                        $other_option_id = $option['option_id'];
                        break;
                    }
                }
                
                // Check if "Other" is selected and validate input
                $other_text = null;
                if ($other_option_id && in_array($other_option_id, $selected_options)) {
                    $other_text = trim($_POST['other_text'][$survey_id] ?? '');
                    if (empty($other_text)) {
                        throw new Exception("Please provide a response for the 'Other' option in question: " . htmlspecialchars($question['question']));
                    }
                }
                
                // Insert votes
                foreach ($selected_options as $option_id) {
                    $text_to_insert = ($option_id == $other_option_id) ? $other_text : null;
                    $stmt_insert->execute([$_SESSION['user_id'], $survey_id, $option_id, $text_to_insert]);
                }
            }
            
            $conn->commit();
            echo "<script>
                alert('Vote submitted successfully!');
                window.location.href = 'home.php';
            </script>";
            exit();
            
        } catch (Exception $e) {
            $conn->rollBack();
            $error = $e->getMessage();
        }
    }
    
    // Get current user information
    $stmt = $conn->prepare("SELECT username, email FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    error_log("Survey vote error: " . $e->getMessage());
} catch(Exception $e) {
    $error = $e->getMessage();
}

// Safely handle variables that may be null
$survey_title = isset($survey_metadata['title']) ? htmlspecialchars($survey_metadata['title']) : 'Unknown survey';
$creator_name = isset($survey_metadata['creator']) ? htmlspecialchars($survey_metadata['creator']) : 'Unknown user';
$username = isset($user['username']) ? htmlspecialchars($user['username']) : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vote - <?= $survey_title ?></title>
    <link rel="stylesheet" href="home.css">
    <link rel="stylesheet" href="vote.css">
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const questions = document.querySelectorAll('.question-container');
            
            questions.forEach(question => {
                const surveyId = question.dataset.surveyId;
                const options = question.querySelectorAll(`[name="options[${surveyId}][]"], [name="options[${surveyId}]"]`);
                let otherInput = null;
                
                options.forEach(option => {
                    if (option.nextElementSibling.textContent.trim() === 'Other') {
                        otherInput = option;
                    }
                });
                
                if (otherInput) {
                    const otherTextContainer = question.querySelector(`#other-text-container-${surveyId}`);
                    const otherTextInput = question.querySelector(`#other-text-${surveyId}`);
                    
                    const toggleOtherTextInput = () => {
                        otherTextContainer.style.display = otherInput.checked ? 'block' : 'none';
                        otherTextInput.required = otherInput.checked;
                    };
                    
                    toggleOtherTextInput();
                    
                    if (otherInput.type === 'checkbox') {
                        otherInput.addEventListener('change', toggleOtherTextInput);
                    } else {
                        options.forEach(opt => {
                            opt.addEventListener('change', toggleOtherTextInput);
                        });
                    }
                }
            });
        });
    </script>
</head>
<body>
    <div class="vote-app">
        <header class="app-header">
            <div class="header-left">
                <h1 class="logo">Voting System</h1>
                <nav class="main-nav">
                    <a href="home.php"><i class="icon-home"></i>Home</a>
                    <a href="create_poll.php"><i class="icon-create"></i>Create Poll</a>
                    <a href="create_survey.php"><i class="icon-create"></i>Create Survey</a>
                    <a href="results.php"><i class="icon-results"></i>Poll Results</a>
                </nav>
            </div>
            <div class="user-menu">
                <?php if ($username): ?>
                    <span class="username"><?= $username ?></span>
                <?php endif; ?>
                <a href="logout.php" class="logout-btn"><i class="icon-logout"></i>Logout</a>
            </div>
        </header>

        <main class="app-main">
            <section class="welcome-banner">
                <h2><?= $survey_title ?></h2>
                <p>Created by <?= $creator_name ?></p>
            </section>

            <div class="content-wrapper">
                <section class="vote-section" style="grid-column: 1 / -1;">
                    <?php if ($error): ?>
                        <div class="alert error"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert success"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($questions[0]['image_path'])): ?>
                    <div class="poll-image-container">
                        <img src="<?= htmlspecialchars($questions[0]['image_path']) ?>" 
                            alt="<?= $survey_title ?>" class="poll-image">
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($questions)): ?>
                        <form action="vote_survey.php?id=<?= $survey_metadata_id ?>&<?= htmlspecialchars(SID) ?>" method="POST" class="vote-form">
                            <?php foreach ($questions as $index => $question): ?>
                                <div class="question-container" data-survey-id="<?= $question['survey_id'] ?>">
                                    <h4>Question <?= $index + 1 ?>: <?= htmlspecialchars($question['question']) ?></h4>
                                    <div class="options-list">
                                        <?php foreach ($question['options'] as $option): ?>
                                            <div class="option-item">
                                                <?php if ($question['is_multiple']): ?>
                                                    <input type="checkbox" 
                                                           name="options[<?= $question['survey_id'] ?>][]" 
                                                           id="option_<?= $question['survey_id'] ?>_<?= $option['option_id'] ?>" 
                                                           value="<?= $option['option_id'] ?>">
                                                <?php else: ?>
                                                    <input type="radio" 
                                                           name="options[<?= $question['survey_id'] ?>]" 
                                                           id="option_<?= $question['survey_id'] ?>_<?= $option['option_id'] ?>" 
                                                           value="<?= $option['option_id'] ?>" 
                                                           required>
                                                <?php endif; ?>
                                                <label for="option_<?= $question['survey_id'] ?>_<?= $option['option_id'] ?>">
                                                    <?= htmlspecialchars($option['option_text']) ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                        <div class="other-text-container" id="other-text-container-<?= $question['survey_id'] ?>">
                                            <input type="text" 
                                                   id="other-text-<?= $question['survey_id'] ?>" 
                                                   name="other_text[<?= $question['survey_id'] ?>]" 
                                                   placeholder="Enter your response" 
                                                   class="form-control">
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <div class="form-group">
                                <button type="submit" class="vote-now-btn">Submit Vote</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="alert error">This survey has no questions.</div>
                    <?php endif; ?>
                </section>
            </div>
        </main>
    </div>
</body>
</html>