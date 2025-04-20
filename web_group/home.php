<?php
require_once 'config.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?" . htmlspecialchars(SID));
    exit();
}

try {
    $conn = getDBConnection();
    // Get user info
    $stmt = $conn->prepare("SELECT username, email FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$success = isset($_GET['success']) ? urldecode($_GET['success']) : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voting Center - <?= htmlspecialchars($user['username']) ?></title>
    <link rel="stylesheet" href="home.css">
    <script>
        async function updatePollsAndSurveys() {
            try {
                // Fetch polls
                const pollsResponse = await fetch('get_latest_polls.php');
                if (!pollsResponse.ok) {
                    throw new Error(`HTTP error fetching polls! status: ${pollsResponse.status}`);
                }
                const pollsData = await pollsResponse.json();
                if (pollsData.error) {
                    console.error('Polls error:', pollsData.error);
                    return;
                }

                // Fetch surveys
                const surveysResponse = await fetch('get_latest_surveys.php');
                if (!surveysResponse.ok) {
                    throw new Error(`HTTP error fetching surveys! status: ${surveysResponse.status}`);
                }
                const surveysData = await surveysResponse.json();
                if (surveysData.error) {
                    console.error('Surveys error:', surveysData.error);
                    return;
                }

                // Combine polls and surveys
                const polls = pollsData.polls.map(poll => ({ ...poll, type: 'poll' }));
                const surveys = surveysData.surveys.map(survey => ({ ...survey, type: 'survey', question: survey.title }));
                const combinedItems = [...polls, ...surveys].sort((a, b) => new Date(b.created_at) - new Date(a.created_at));

                // Update the page
                const pollsContainer = document.getElementById('polls-container');
                let html = '';
                combinedItems.forEach(item => {
                    const voteLink = item.type === 'poll' ? `vote.php?id=${item.poll_id}` : `vote_survey.php?id=${item.survey_metadata_id}`;
                    html += `
                        <div class="vote-card">
                            <div class="vote-type-label ${item.type}">${item.type.charAt(0).toUpperCase() + item.type.slice(1)}</div>
                            <div class="vote-image">
                                <img src="${item.image_path}" alt="${item.question}">
                            </div>
                            <div class="vote-content">
                                <h4>${item.question}</h4>
                                <p>Created by ${item.creator}</p>
                                <a href="${voteLink}" class="vote-now-btn">Vote Now</a>
                            </div>
                        </div>
                    `;
                });
                pollsContainer.innerHTML = html || '<p>No polls or surveys available.</p>';
            } catch (error) {
                console.error('Failed to load polls or surveys:', error);
                const pollsContainer = document.getElementById('polls-container');
                pollsContainer.innerHTML = '<p>Failed to load content. Please try again later.</p>';
            }
        }

        // Update polls and surveys every 5 seconds
        setInterval(updatePollsAndSurveys, 5000);

        // Update polls and surveys once when the page loads
        window.onload = updatePollsAndSurveys;
    </script>
</head>
<body>
    <div class="vote-app">
        <header class="app-header">
            <div class="header-left">
                <h1 class="logo">Voting System</h1>
                <nav class="main-nav">
                    <a href="home.php?<?= htmlspecialchars(SID) ?>" class="active"><i class="icon-home"></i>Home</a>
                    <a href="create_poll.php?<?= htmlspecialchars(SID) ?>"><i class="icon-create"></i>Create Poll</a>
                    <a href="create_survey.php?<?= htmlspecialchars(SID) ?>"><i class="icon-create"></i>Create Survey</a>
                    <a href="results.php"><i class="icon-results"></i>Results</a>
                </nav>
            </div>
            <div class="user-menu">
                <span class="username"><?= htmlspecialchars($user['username']) ?></span>
                <a href="logout.php?<?= htmlspecialchars(SID) ?>" class="logout-btn"><i class="icon-logout"></i>Logout</a>
            </div>
        </header>

        <main class="app-main">
            <section class="welcome-banner">
                <h2>Welcome, <?= htmlspecialchars($user['username']) ?>!</h2>
                <p>Participate in the latest polls and surveys to share your opinion</p>
            </section>

            <div class="content-wrapper">
                <?php if ($success): ?>
                    <div class="alert success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                
                <section class="profile-section">
                    <div class="section-header">
                        <h3><i class="icon-profile"></i>My Profile</h3>
                    </div>
                    <div class="profile-details">
                        <div class="detail-row">
                            <span class="detail-label">Username</span>
                            <span class="detail-value"><?= htmlspecialchars($user['username']) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Email</span>
                            <span class="detail-value"><?= htmlspecialchars($user['email']) ?></span>
                        </div>
                    </div>
                </section>

                <section class="vote-section">
                    <div id="polls-container">
                        <div class="loading-spinner">
                            <div class="spinner"></div>
                            <p>Loading polls and surveys...</p>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>
</body>
</html>