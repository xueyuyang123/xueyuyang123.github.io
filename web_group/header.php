<div class="vote-app">
    <!-- Top Navigation Bar -->
    <header class="app-header">
        <div class="header-left">
            <h1 class="logo">Voting System</h1>
            <nav class="main-nav">
                <a href="home.php"><i class="icon-home"></i>Home</a>
                <a href="create_poll.php"><i class="icon-create"></i>Create Poll</a>
                <a href="vote.php"><i class="icon-vote"></i>Participate in Poll</a>
                <a href="#"><i class="icon-results"></i>Poll Results</a>
            </nav>
        </div>
        <div class="user-menu">
            <span class="username"><?= htmlspecialchars($_SESSION['username'] ?? '') ?></span>
            <a href="logout.php" class="logout-btn"><i class="icon-logout"></i>Logout</a>
        </div>
    </header>
</div>