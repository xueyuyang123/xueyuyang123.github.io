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

// 引入 HTML 表單
include 'login.html';