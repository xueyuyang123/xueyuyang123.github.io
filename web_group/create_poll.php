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
        $question = trim($_POST['question'] ?? '');
        if (empty($question)) {
            throw new Exception("The poll question cannot be empty.");
        }
        
        // Collect and validate options
        $options = [];
        for ($i = 1; $i <= 5; $i++) {
            $option = trim($_POST["option$i"] ?? '');
            if (!empty($option)) {
                $options[] = $option;
            }
        }
        if (count($options) < 2) {
            throw new Exception("At least two non-empty options are required.");
        }
        
        // Handle image upload
        $image_path = 'uploads/image.png'; // Default image path
        if (isset($_FILES['poll_image']) && $_FILES['poll_image']['error'] === UPLOAD_ERR_OK) {
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
            $file_type = finfo_file($finfo, $_FILES['poll_image']['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($file_type, $allowed_types)) {
                throw new Exception("Only JPEG, PNG, or GIF images are allowed.");
            }
            
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
            $file_ext = strtolower(pathinfo($_FILES['poll_image']['name'], PATHINFO_EXTENSION));
            if (!in_array($file_ext, $allowed_ext)) {
                throw new Exception("Invalid file extension.");
            }
            
            $max_size = 2 * 1024 * 1024;
            if ($_FILES['poll_image']['size'] > $max_size) {
                throw new Exception("The image size cannot exceed 2MB.");
            }
            
            $file_name = uniqid('poll_') . '.' . $file_ext;
            $target_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['poll_image']['tmp_name'], $target_path)) {
                $image_path = 'Uploads/' . $file_name;
                chmod($target_path, 0644);
            } else {
                throw new Exception("File upload failed.");
            }
        }
        
        // Insert poll data
        $stmt = $conn->prepare("INSERT INTO polls (user_id, question, is_multiple, image_path) VALUES (?, ?, ?, ?)");
        $is_multiple = isset($_POST['is_multiple']) ? 1 : 0;
        $stmt->execute([
            $_SESSION['user_id'],
            $question,
            $is_multiple,
            $image_path
        ]);
        $poll_id = $conn->lastInsertId();
        
        // Insert options
        $stmt = $conn->prepare("INSERT INTO poll_options (poll_id, option_text) VALUES (?, ?)");
        foreach ($options as $option) {
            $stmt->execute([$poll_id, $option]);
            error_log("Inserted option for poll_id $poll_id: $option");
        }
        
        // Verify options were inserted
        $stmt = $conn->prepare("SELECT COUNT(*) FROM poll_options WHERE poll_id = ?");
        $stmt->execute([$poll_id]);
        $option_count = $stmt->fetchColumn();
        if ($option_count < 2) {
            throw new Exception("Failed to insert options.");
        }
        
        $conn->commit();
        $success = "Poll created successfully!";
        $_POST = array();
        
    } catch(PDOException $e) {
        if (isset($conn)) {
            $conn->rollBack();
        }
        $error = "Database error: " . $e->getMessage();
        error_log("Poll creation failed: " . $e->getMessage());
    } catch(Exception $e) {
        if (isset($conn)) {
            $conn->rollBack();
        }
        $error = $e->getMessage();
        error_log("Poll creation failed: " . $e->getMessage());
    }
}

// Calculate the number of existing options
$initialOptionCount = 2;
if ($_POST) {
    $filledOptions = 0;
    for ($i = 1; $i <= 5; $i++) {
        if (!empty(trim($_POST["option$i"] ?? ''))) {
            $filledOptions++;
        }
    }
    $initialOptionCount = $filledOptions;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Poll - <?= htmlspecialchars($user['username'] ?? 'User') ?></title>
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
                    <a href="create_poll.php" class="active"><i clas    s="icon-create"></i>Create Poll</a>
                    <a href="create_survey.php"><i class="icon-create"></i>Create Survey</a>
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
                <h2>Create a New Poll</h2>
                <p>Design your question and options to gather opinions</p>
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
                    
                    <form action="create_poll.php?<?= htmlspecialchars(SID) ?>" method="POST" class="poll-form" id="poll-form" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="question" class="required">Poll Question</label>
                            <input type="text" id="question" name="question" class="form-control" 
                                   value="<?= isset($_POST['question']) ? htmlspecialchars($_POST['question']) : '' ?>" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="poll_image">Poll Cover Image (Optional)</label>
                            <input type="file" id="poll_image" name="poll_image" class="form-control" accept="image/*">
                            <small class="form-text text-muted">Supports JPEG/PNG format, up to 2MB. Default image will be used if not uploaded.</small>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="is_multiple" id="is_multiple" 
                                    <?= isset($_POST['is_multiple']) ? 'checked' : '' ?>>
                                Allow Multiple Selections
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label class="required">Poll Options (At least 2)</label>
                            <div id="options-container">
                                <div class="option-container">
                                    <input type="text" name="option1" class="form-control" placeholder="Option 1" 
                                           value="<?= isset($_POST['option1']) ? htmlspecialchars($_POST['option1']) : '' ?>" 
                                           required>
                                </div>
                                <div class="option-container">
                                    <input type="text" name="option2" class="form-control" placeholder="Option 2" 
                                           value="<?= isset($_POST['option2']) ? htmlspecialchars($_POST['option2']) : '' ?>" 
                                           required>
                                </div>
                                <?php for ($i = 3; $i <= 5; $i++): ?>
                                    <?php 
                                    $optionValue = $_POST["option$i"] ?? '';
                                    if (!empty(trim($optionValue))): ?>
                                        <div class="option-container">
                                            <input type="text" name="option<?= $i ?>" class="form-control" 
                                                   value="<?= htmlspecialchars($optionValue) ?>">
                                            <button type="button" class="btn-remove remove-option">Remove</button>
                                        </div>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </div>
                            <button type="button" id="add-option" class="btn btn-add">Add Option</button>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-submit">Create Poll</button>
                            <a href="home.php" class="btn btn-cancel">Cancel</a>
                        </div>
                    </form>
                </section>
            </div>
        </main>
    </div>
    
    <script>
        var initialOptionCount = <?= $initialOptionCount ?>;
    </script>
    
    <script src="create_poll.js"></script>
</body>
</html>