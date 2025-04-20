<?php
require_once 'config.php';

$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim(htmlspecialchars($_POST['username']));
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    
    // Validation
    if (empty($username)) $errors[] = "Username cannot be empty.";
    elseif (strlen($username) < 4) $errors[] = "Username must be at least 4 characters long.";
    
    if (empty($password)) $errors[] = "Password cannot be empty.";
    elseif (strlen($password) < 6) $errors[] = "Password must be at least 6 characters long.";
    elseif ($password !== $confirm_password) $errors[] = "Passwords do not match.";
    
    if (empty($email)) $errors[] = "Email cannot be empty.";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format.";
    
    if (empty($errors)) {
        try {
            $conn = getDBConnection();
            
            // Check if username or email already exists
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->rowCount() > 0) {
                $errors[] = "Username or email is already registered.";
            } else {
                // Insert new user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (username, password, email) VALUES (?, ?, ?)");
                $stmt->execute([$username, $hashed_password, $email]);
                
                $_SESSION['success'] = "Registration successful. Please log in.";
                header("Location: index.php");
                exit();
            }
        } catch(PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Registration</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="login-container">
        <?php if (!empty($errors)): ?>
            <div class="error-message">
                <?php foreach ($errors as $error): ?>
                    <p><?= $error ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <h2>User Registration</h2>
        <form action="register.php?<?= htmlspecialchars(SID) ?>" method="POST">
            <div class="input-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" 
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" 
                       placeholder="Enter a username (at least 4 characters)" required>
            </div>
            <div class="input-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" 
                       placeholder="Enter a password (at least 6 characters)" required>
            </div>
            <div class="input-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" 
                       placeholder="Re-enter your password" required>
            </div>
            <div class="input-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" 
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                       placeholder="Enter a valid email address" required>
            </div>
            <button class="btn" type="submit">Register</button>
            <button class="btn register-btn" type="button" onclick="location.href='index.php'">Back to Login</button>
        </form>
    </div>
</body>
</html>