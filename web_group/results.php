<?php
require_once 'config.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?" . htmlspecialchars(SID));
    exit();
}

// Get the current user's information
try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT username, email FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results - <?= htmlspecialchars($user['username']) ?></title>
    <link rel="stylesheet" href="home.css">
    <link rel="stylesheet" href="results.css">
    <script>
        async function updateResults(filter = 'all') {
            try {
                // Show loading spinner
                document.getElementById('results-container').innerHTML = `
                    <div class="loading-spinner">
                        <div class="spinner"></div>
                        <p>Loading results...</p>
                    </div>
                `;

                // Fetch poll results
                const pollsResponse = await fetch(`get_latest_results.php?filter=${filter}`);
                if (!pollsResponse.ok) throw new Error(`HTTP error fetching polls! status: ${pollsResponse.status}`);
                const pollsData = await pollsResponse.json();
                if (pollsData.error) {
                    console.error('Polls error:', pollsData.error);
                    return;
                }

                // Fetch survey results
                const surveysResponse = await fetch(`get_latest_survey_results.php?filter=${filter}`);
                if (!surveysResponse.ok) throw new Error(`HTTP error fetching surveys! status: ${surveysResponse.status}`);
                const surveysData = await surveysResponse.json();
                if (surveysData.error) {
                    console.error('Surveys error:', surveysData.error);
                    return;
                }

                // Combine and sort results
                const polls = pollsData.polls.map(poll => ({ ...poll, type: 'poll' }));
                const surveys = surveysData.surveys.map(survey => ({ ...survey, type: 'survey' }));
                const combinedItems = [...polls, ...surveys].sort((a, b) => new Date(b.created_at) - new Date(a.created_at));

                // Render results
                const resultsContainer = document.getElementById('results-container');
                if (combinedItems.length === 0) {
                    resultsContainer.innerHTML = `
                        <div class="no-results">
                            <p>No poll or survey results found</p>
                        </div>
                    `;
                    return;
                }

                resultsContainer.innerHTML = combinedItems.map(item => renderPollResult(item, filter)).join('');

                // Add click events
                document.querySelectorAll('.poll-result').forEach(item => {
                    item.addEventListener('click', function() {
                        const details = this.querySelector('.poll-details');
                        details.classList.toggle('expanded');
                        
                        // Smooth scroll to expanded content
                        if (details.classList.contains('expanded')) {
                            details.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        }
                    });
                });

                // Animate progress bars
                animateProgressBars();

            } catch (error) {
                console.error('Failed to load results:', error);
                document.getElementById('results-container').innerHTML = `
                    <div class="alert error">
                        <p>Failed to load: ${error.message}</p>
                    </div>
                `;
            }
        }

        function renderPollResult(item, filter) {
            const imagePath = item.image_path || 'uploads/default.png';

            if (item.type === 'poll') {
                const options = item.options || [];
                const otherResponses = item.other_responses || [];

                return `
                    <div class="poll-result">
                        <div class="vote-type-label ${item.type}">${item.type.charAt(0).toUpperCase() + item.type.slice(1)}</div>
                        <div class="poll-image">
                            <img src="${imagePath}" alt="${item.question}">
                        </div>

                        <div class="poll-header">
                            <h1>${item.question}</h1>
                        </div>

                        <div class="poll-summary">
                            <div class="poll-meta">
                                <div class="meta-item">
                                    <i class="icon-user"></i>
                                    <span class="meta-text"><b>Created By: </b>${item.creator}</span>
                                </div>
                                <div class="meta-divider"></div>
                                <div class="meta-item">
                                    <i class="icon-chart"></i>
                                    <span class="meta-text"><b>Total Votes: </b>${item.total_votes || 0} votes</span>
                                </div>
                            </div>
                        </div>

                        <div class="poll-details">
                            ${options.map(option => `
                                <div class="option-result">
                                    <div class="option-text">${option.option_text}</div>
                                    <div class="progress-container">
                                        <div class="progress-bar" 
                                             style="width: ${option.percentage || 0}%">
                                        </div>
                                    </div>
                                    <div class="vote-percentage">
                                        <span>${option.vote_count || 0} votes</span>
                                        <span>${option.percentage ? option.percentage.toFixed(1) : 0}%</span>
                                    </div>
                                </div>
                            `).join('')}
                            ${otherResponses.length > 0 ? `
                                <div class="other-responses">
                                    <h5>Other Responses:</h5>
                                    <ul>
                                        ${otherResponses.map(response => `
                                            <li>${response}</li>
                                        `).join('')}
                                    </ul>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `;
            } else if (item.type === 'survey') {
                const questions = item.questions || [];

                return `
                    <div class="poll-result">
                        <div class="vote-type-label ${item.type}">${item.type.charAt(0).toUpperCase() + item.type.slice(1)}</div>
                        <div class="poll-image">
                            <img src="${imagePath}" alt="${item.question}">
                        </div>

                        <div class="poll-header">
                            <h1>${item.question}</h1>
                        </div>

                        <div class="poll-summary">
                            <div class="poll-meta">
                                <div class="meta-item">
                                    <i class="icon-user"></i>
                                    <span class="meta-text"><b>Created By: </b>${item.creator}</span>
                                </div>
                                <div class="meta-divider"></div>
                                <div class="meta-item">
                                    <i class="icon-chart"></i>
                                    <span class="meta-text"><b>Total Votes: </b>${item.total_votes || 0} votes</span>
                                </div>
                            </div>
                        </div>

                        <div class="poll-details">
                            <div class="survey-questions-container">
                                ${questions.map((question, index) => `
                                    <div class="question-section">
                                        <h4>Question ${index + 1}: ${question.question}</h4>
                                        ${question.options.map(option => `
                                            <div class="option-result">
                                                <div class="option-text">${option.option_text}</div>
                                                <div class="progress-container">
                                                    <div class="progress-bar" 
                                                         style="width: ${option.percentage || 0}%">
                                                    </div>
                                                </div>
                                                <div class="vote-percentage">
                                                    <span>${option.vote_count || 0} votes</span>
                                                    <span>${option.percentage ? option.percentage.toFixed(1) : 0}%</span>
                                                </div>
                                            </div>
                                        `).join('')}
                                        ${question.other_responses.length > 0 ? `
                                            <div class="other-responses">
                                                <h5>Other Responses:</h5>
                                                <ul>
                                                    ${question.other_responses.map(response => `
                                                        <li>${response}</li>
                                                    `).join('')}
                                                </ul>
                                            </div>
                                        ` : ''}
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    </div>
                `;
            }
        }

        function animateProgressBars() {
            document.querySelectorAll('.progress-bar').forEach(bar => {
                const targetWidth = bar.style.width;
                bar.style.width = '0';
                setTimeout(() => bar.style.width = targetWidth, 50);
            });
        }

        // Update results every 5 seconds
        setInterval(() => {
            const filter = document.getElementById('poll-filter').value;
            updateResults(filter);
        }, 5000);

        // Initial load
        window.onload = () => {
            const filter = document.getElementById('poll-filter').value;
            updateResults(filter);
            
            document.getElementById('poll-filter').addEventListener('change', function() {
                updateResults(this.value);
            });
        };
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
                    <a href="results.php" class="active"><i class="icon-results"></i>Results</a>
                </nav>
            </div>
            <div class="user-menu">
                <span class="username"><?= htmlspecialchars($user['username']) ?></span>
                <a href="logout.php" class="logout-btn"><i class="icon-logout"></i>Logout</a>
            </div>
        </header>

        <main class="app-main">
            <section class="welcome-banner">
                <h2>Results</h2>
                <p>View all the polls and surveys you created or participated in</p>
            </section>

            <div class="content-wrapper">
                <section class="results-section" style="grid-column: 1 / -1;">
                    <div class="section-header">
                        <h3><i class="icon-results"></i>Results</h3>
                        <div class="poll-filter">
                            <select id="poll-filter">
                                <option value="all">All Polls and Surveys</option>
                                <option value="created">Polls and Surveys I Created</option>
                                <option value="voted">Polls and Surveys I Participated In</option>
                            </select>
                        </div>
                    </div>
                    
                    <div id="results-container">
                        <div class="loading-spinner">
                            <div class="spinner"></div>
                            <p>Loading results...</p>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>
</body>
</html>