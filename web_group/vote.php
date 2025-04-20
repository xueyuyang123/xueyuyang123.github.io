<?php
require_once 'config.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?" . htmlspecialchars(SID));
    exit();
}

// Check if poll ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: home.php?" . htmlspecialchars(SID));
    exit();
}

$poll_id = (int)$_GET['id'];
$error = '';
$success = '';
$poll = null;
$options = [];
$user = null;

try {
    $conn = getDBConnection();
    
    // Get poll information
    $stmt = $conn->prepare("SELECT p.*, u.username AS creator 
                            FROM polls p 
                            JOIN users u ON p.user_id = u.user_id 
                            WHERE p.poll_id = ?");
    $stmt->execute([$poll_id]);
    $poll = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$poll) {
        throw new Exception("The poll does not exist or has been deleted.");
    }
    
    // Check if the user has already voted
    $stmt = $conn->prepare("SELECT 1 FROM poll_votes WHERE user_id = ? AND poll_id = ?");
    $stmt->execute([$_SESSION['user_id'], $poll_id]);
    $has_voted = (bool)$stmt->fetch();

    // If the user has already voted, show an alert and redirect
    if ($has_voted) {
        echo "<script>
            alert('You have already participated in this poll and cannot enter again.');
            window.location.href = 'home.php';
        </script>";
        exit();
    }
    
    // Get poll options
    $stmt = $conn->prepare("SELECT * FROM poll_options WHERE poll_id = ?");
    $stmt->execute([$poll_id]);
    $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($options)) {
        throw new Exception("This poll has no available options.");
    }
    
    // Submit vote
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['options'])) {
            throw new Exception("Please select at least one option.");
        }

        $selected_options = is_array($_POST['options']) ? $_POST['options'] : [$_POST['options']];

        $conn->beginTransaction();
        try {
            $stmt_insert = $conn->prepare("INSERT INTO poll_votes (user_id, poll_id, option_id) VALUES (?, ?, ?)");
            foreach ($selected_options as $option_id) {
                $stmt_insert->execute([$_SESSION['user_id'], $poll_id, $option_id]);
            }

            $conn->commit();
            echo "<script>
                alert('Vote submitted successfully!');
                window.location.href = 'home.php';
            </script>";
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
    error_log("Vote error: " . $e->getMessage());
} catch(Exception $e) {
    $error = $e->getMessage();
}

// Safely handle variables that may be null
$poll_question = isset($poll['question']) ? htmlspecialchars($poll['question']) : 'Unknown question';
$creator_name = isset($poll['creator']) ? htmlspecialchars($poll['creator']) : 'Unknown user';
$username = isset($user['username']) ? htmlspecialchars($user['username']) : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vote - <?= $poll_question ?></title>
    <link rel="stylesheet" href="home.css">
    <link rel="stylesheet" href="vote.css">
</head>
<body>
    <div class="vote-app">
        <header class="app-header">
            <div class="header-left">
                <h1 class="logo">Voting System</h1>
                <nav class="main-nav">
                    <a href="home.php"><i class="icon-home"></i>Home</a>
                    <a href="create_poll.php"><i class="icon-create"></i>Create Poll</a>
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
                <h2><?= $poll_question ?></h2>
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
                    
                    <?php if (isset($poll['image_path'])): ?>
                    <div class="poll-image-container">
                        <img src="<?= !empty($poll['image_path']) ? htmlspecialchars($poll['image_path']) : 'image_file/image.png' ?>" 
                            alt="<?= $poll_question ?>" class="poll-image">
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($options)): ?>
                        <form action="vote.php?id=<?= $poll_id ?>&<?= htmlspecialchars(SID) ?>" method="POST" class="vote-form">
                            <div class="options-list">
                                <?php foreach ($options as $option): ?>
                                <div class="option-item">
                                    <?php if ($poll['is_multiple']): ?>
                                        <input type="checkbox" name="options[]" id="option_<?= $option['option_id'] ?>" value="<?= $option['option_id'] ?>">
                                    <?php else: ?>
                                        <input type="radio" name="options" id="option_<?= $option['option_id'] ?>" value="<?= $option['option_id'] ?>" required>
                                    <?php endif; ?>
                                    <label for="option_<?= $option['option_id'] ?>"><?= htmlspecialchars($option['option_text']) ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="vote-now-btn">Submit Vote</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="alert error">This poll has no available options.</div>
                    <?php endif; ?>
                </section>
            </div>
        </main>
    </div>
</body>
</html>