<?php
require_once 'includes/functions.php';
require_once 'config/db.php'
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Shop Billing System</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <!-- Shop icon SVG - represents the hardware/shop theme -->
        <div class="shop-icon">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                <path d="M9 22V12h6v10"/>
            </svg>
        </div>
        <!-- Shop name displayed prominently -->
        <h1>National Cement Sanitary &amp; Hardware</h1>
        <!-- Subtitle indicating the system purpose -->
        <p class="sub">Shop Management &amp; Billing System</p>

        <!-- Display error message if login failed -->
        <?php if ($error): ?>
            <div class="login-error"><?= e($error) ?></div>
        <?php endif; ?>

        <!-- Login form that submits to this same page -->
        <form method="POST">
            <!-- Username input field -->
            <div class="field">
                <label>Username</label>
                <input type="text" name="username" autofocus required autocomplete="username">
            </div>
            <!-- Password input field -->
            <div class="field">
                <label>Password</label>
                <input type="password" name="password" required autocomplete="current-password">
            </div>
            <!-- Submit button -->
            <button type="submit" class="btn-login">Login</button>
        </form>
    </div>
</div>
</body>
</html>