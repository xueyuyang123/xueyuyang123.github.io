<?php
require_once 'config.php';

// Handle login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $username = trim(htmlspecialchars($_POST['username']));
    $password = $_POST['password'];
    
    $errors = [];
    
    if (empty($username)) $errors[] = "Username cannot be empty.";
    if (empty($password)) $errors[] = "Password cannot be empty.";
    
    if (empty($errors)) {
        try {
            $conn = getDBConnection();
            $stmt = $conn->prepare("SELECT user_id, username, password FROM users WHERE username = ?");
            $stmt->execute([$username]);
            
            if ($stmt->rowCount() == 1) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    header("Location: home.php");
                    exit();
                }
            }
            
            $errors[] = "Invalid username or password.";
        } catch(PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

$success = $_SESSION['success'] ?? '';
unset($_SESSION['success']);
$errors = $errors ?? [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login Page</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="login-container">
        <?php if (!empty($success)): ?>
            <div class="success-message"><?= $success ?></div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="error-message">
                <?php foreach ($errors as $error): ?>
                    <p><?= $error ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <h2>Login</h2>
        <form action="index.php?<?= htmlspecialchars(SID) ?>" method="POST">
            <input type="hidden" name="login" value="1">
            <div class="input-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" 
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" 
                    placeholder="Enter your username" required>
            </div>
            <div class="input-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" 
                       placeholder="Enter your password" required>
            </div>
            <button class="btn" type="submit">Login</button>
            <button class="btn register-btn" type="button" onclick="location.href='register.php'">Register a New Account</button>
        </form>
    </div>
</body>
</html>