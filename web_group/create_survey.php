<?php
require_once 'config.php';

// Enable error reporting (development environment)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?" . htmlspecialchars(SID));
    exit();
}

// Get user information
try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT username, email FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception("Failed to retrieve user information.");
    }
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn = getDBConnection();
        $conn->beginTransaction();
        
        // Validate required fields
        if (empty($_POST['survey_title'])) {
            throw new Exception("Survey title cannot be empty.");
        }
        
        // Insert survey title into surveys_metadata table
        $stmt = $conn->prepare("INSERT INTO surveys_metadata (user_id, title) VALUES (?, ?)");
        $stmt->execute([$_SESSION['user_id'], $_POST['survey_title']]);
        $survey_metadata_id = $conn->lastInsertId();
        
        // Handle image upload
        $image_path = 'Uploads/image.png'; // Default image path
        if (isset($_FILES['survey_image']) && $_FILES['survey_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/Uploads/';
            if (!file_exists($upload_dir)) {
                if (!mkdir($upload_dir, 0755, true)) {
                    throw new Exception("Failed to create upload directory.");
                }
            }
            if (!is_writable($upload_dir)) {
                throw new Exception("The upload directory is not writable.");
            }
            
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $file_type = finfo_file($finfo, $_FILES['survey_image']['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($file_type, $allowed_types)) {
                throw new Exception("Only JPEG, PNG, or GIF images are allowed.");
            }
            
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
            $file_ext = strtolower(pathinfo($_FILES['survey_image']['name'], PATHINFO_EXTENSION));
            if (!in_array($file_ext, $allowed_ext)) {
                throw new Exception("Invalid file extension.");
            }
            
            $max_size = 2 * 1024 * 1024;
            if ($_FILES['survey_image']['size'] > $max_size) {
                throw new Exception("The image size cannot exceed 2MB.");
            }
            
            $file_name = uniqid('survey_') . '.' . $file_ext;
            $target_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['survey_image']['tmp_name'], $target_path)) {
                $image_path = 'Uploads/' . $file_name;
                chmod($target_path, 0644);
            } else {
                throw new Exception("File upload failed.");
            }
        }
        
        // Process questions
        if (empty($_POST['questions']) || !is_array($_POST['questions'])) {
            throw new Exception("At least one question is required.");
        }
        
        foreach ($_POST['questions'] as $questionData) {
            if (empty($questionData['text'])) {
                throw new Exception("Question text cannot be empty.");
            }
            
            // Insert survey question
            $stmt = $conn->prepare("INSERT INTO surveys (survey_metadata_id, user_id, question, is_multiple, image_path) VALUES (?, ?, ?, ?, ?)");
            $is_multiple = isset($questionData['is_multiple']) ? 1 : 0;
            $stmt->execute([
                $survey_metadata_id,
                $_SESSION['user_id'],
                $questionData['text'],
                $is_multiple,
                $image_path
            ]);
            $survey_id = $conn->lastInsertId();
            
            // Process options
            if (empty($questionData['options']) || count($questionData['options']) < 2) {
                throw new Exception("Each question must have at least 2 options.");
            }
            
            $stmt = $conn->prepare("INSERT INTO survey_options (survey_id, option_text) VALUES (?, ?)");
            foreach ($questionData['options'] as $option) {
                if (!empty(trim($option))) {
                    $stmt->execute([$survey_id, $option]);
                }
            }
            
            // Add "Other" option
            $stmt->execute([$survey_id, "Other"]);
        }
        
        $conn->commit();
        $success = "Survey '" . htmlspecialchars($_POST['survey_title']) . "' created successfully!";
        // Clear form data after successful submission
        $_POST = [];
        $initialQuestionCount = 1;
        
    } catch(PDOException $e) {
        if (isset($conn)) {
            $conn->rollBack();
        }
        $error = "Database error: " . $e->getMessage();
        error_log("Survey creation failed: " . $e->getMessage());
    } catch(Exception $e) {
        if (isset($conn)) {
            $conn->rollBack();
        }
        $error = $e->getMessage();
        error_log("Survey creation failed: " . $e->getMessage());
    }
}

// Calculate initial question count
$initialQuestionCount = 1;
if ($_POST && isset($_POST['questions']) && is_array($_POST['questions'])) {
    $initialQuestionCount = count($_POST['questions']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Survey - <?= htmlspecialchars($user['username'] ?? 'User') ?></title>
    <link rel="stylesheet" href="home.css">
    <link rel="stylesheet" href="create_poll&survey.css">
</head>
<body>
    <div class="vote-app">
        <header class="app-header">
            <div class="header-left">
                <h1 class="logo">Voting System</h1>
                <nav class="main-nav">
                    <a href="home.php"><i class="icon-home"></i>Home</a>
                    <a href="create_poll.php"><i class="icon-create"></i>Create Poll</a>
                    <a href="create_survey.php" class="active"><i class="icon-create"></i>Create Survey</a>
                    <a href="results.php"><i class="icon-results"></i>Results</a>
                </nav>
            </div>
            <div class="user-menu">
                <span class="username"><?= htmlspecialchars($user['username'] ?? 'User') ?></span>
                <a href="logout.php" class="logout-btn"><i class="icon-logout"></i>Logout</a>
            </div>
        </header>

        <main class="app-main">
            <section class="welcome-banner">
                <h2>Create a New Survey</h2>
                <p>Design your questions and options to gather opinions</p>
            </section>

            <div class="content-wrapper">
                <section class="vote-section" style="grid-column: 1 / -1;">
                    <?php if ($error): ?>
                        <div class="alert error">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert success"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>
                    
                    <form action="create_survey.php?<?= htmlspecialchars(SID) ?>" method="POST" class="poll-form" id="survey-form" enctype="multipart/form-data">
                        <input type="hidden" name="form_cleared" value="<?= $success ? '1' : '0' ?>">
                        <div class="form-group">
                            <label for="survey_title" class="required">Survey Title</label>
                            <input type="text" id="setTimeouturvey_title" name="survey_title" class="form-control" 
                                   value="<?= isset($_POST['survey_title']) ? htmlspecialchars($_POST['survey_title']) : '' ?>" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="survey_image">Survey Cover Image (Optional)</label>
                            <input type="file" id="survey_image" name="survey_image" class="form-control" accept="image/*">
                            <small class="form-text text-muted">Supports JPEG/PNG format, up to 2MB. Default image will be used if not uploaded.</small>
                        </div>
                        
                        <div id="questions-container">
                            <?php if (isset($_POST['questions']) && is_array($_POST['questions']) && !$success): ?>
                                <?php foreach ($_POST['questions'] as $qIndex => $question): ?>
                                    <div class="question-container" data-question-index="<?= $qIndex ?>">
                                        <div class="question-header">
                                            <h3>Question #<?= $qIndex + 1 ?></h3>
                                            <button type="button" class="btn-remove remove-question">Remove Question</button>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="question_<?= $qIndex ?>" class="required">Question Text</label>
                                            <input type="text" id="question_<?= $qIndex ?>" name="questions[<?= $qIndex ?>][text]" class="form-control" 
                                                   value="<?= htmlspecialchars($question['text'] ?? '') ?>" required>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>
                                                <input type="checkbox" name="questions[<?= $qIndex ?>][is_multiple]" 
                                                    <?= isset($question['is_multiple']) ? 'checked' : '' ?>>
                                                Allow Multiple Selections
                                            </label>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="required">Options (At least 2, up to 5, plus 'Other')</label>
                                            <div class="options-container">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <?php if (isset($question['options'][$i-1])): ?>
                                                        <div class="option-container">
                                                            <input type="text" name="questions[<?= $qIndex ?>][options][]" class="form-control" 
                                                                   value="<?= htmlspecialchars($question['options'][$i-1]) ?>">
                                                            <button type="button" class="btn-remove remove-option">Remove</button>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endfor; ?>
                                                
                                                <!-- Fixed "Other" option -->
                                                <div class="option-container other-option">
                                                    <input type="text" class="form-control" value="Other" disabled>
                                                    <small class="form-text text-muted">This option allows users to input their own response.</small>
                                                </div>
                                            </div>
                                            <button type="button" class="btn btn-add add-option">Add Option</button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <!-- Default first question -->
                                <div class="question-container" data-question-index="0">
                                    <div class="question-header">
                                        <h3>Question #1</h3>
                                        <button type="button" class="btn-remove remove-question">Remove Question</button>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="question_0" class="required">Question Text</label>
                                        <input type="text" id="question_0" name="questions[0][text]" class="form-control" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" name="questions[0][is_multiple]">
                                            Allow Multiple Selections
                                        </label>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="required">Options (At least 2, up to 5, plus 'Other')</label>
                                        <div class="options-container">
                                            <div class="option-container">
                                                <input type="text" name="questions[0][options][]" class="form-control" required>
                                            </div>
                                            <div class="option-container">
                                                <input type="text" name="questions[0][options][]" class="form-control" required>
                                            </div>
                                            
                                            <!-- Fixed "Other" option -->
                                            <div class="option-container other-option">
                                                <input type="text" class="form-control" value="Other" disabled>
                                                <small class="form-text text-muted">This option allows users to input their own response.</small>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-add add-option">Add Option</button>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <button type="button" id="add-question" class="btn btn-add">Add Another Question</button>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-submit">Create Survey</button>
                            <a href="home.php" class="btn btn-cancel">Cancel</a>
                        </div>
                    </form>
                </section>
            </div>
        </main>
    </div>
    
    <script>
        var initialQuestionCount = <?= $initialQuestionCount ?>;
    </script>
    
    <script src="create_survey.js"></script>
</body>
</html>