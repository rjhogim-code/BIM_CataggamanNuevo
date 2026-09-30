<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/db/connection.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$loginMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $defaultAdminUsername = 'admin';
    $defaultAdminPassword = 'admin123';

    if ($username === $defaultAdminUsername && $password === $defaultAdminPassword) {
        $_SESSION['user_id'] = 1;
        $_SESSION['username'] = 'admin';
        $_SESSION['role'] = 'admin';

        header('Location: index.php');
        exit;
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $account = $stmt->fetch();

    if (!$account || !password_verify($password, $account['password'])) {
        $loginMessage = 'Username or password is incorrect.';
    } else {
        $_SESSION['user_id'] = $account['id'];
        $_SESSION['username'] = $account['username'];
        $_SESSION['role'] = $account['role'];

        header('Location: index.php');
        exit;
    }
}
?>
<!doctype html>
<html lang="en" dir="ltr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sign in | Barangay Cataggaman Nuevo</title>
<link rel="stylesheet" href="styles.css">
</head>
<body class="login-page" data-page="login">
<main class="login-shell">
<section class="login-welcome">
<div class="login-logo"><img src="Cat.jpg" alt="Barangay Cataggaman Nuevo logo"></div>
<p>BARANGAY INFORMATION MANAGEMENT SYSTEM</p>
<h1>Welcome to Cataggaman Nuevo.</h1>
<p>Keep resident records, incident reports, and certificates organized in one calm workspace.</p>
</section>

<section class="login-card" id="loginCard">
<h2>Sign in</h2>
<p>Enter your account details to continue.</p>
<form class="login-form" method="post">
<input type="hidden" name="form_action" value="login">
<label for="username">Username
<input id="username" name="username" autocomplete="username" placeholder="Enter your username" value="admin" required autofocus>
</label>
<label for="password">Password
<input id="password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" value="admin123" required>
</label>
<p class="login-message" id="loginMessage" <?= $loginMessage === '' ? 'hidden' : '' ?>><?= htmlspecialchars($loginMessage) ?></p>
<button class="btn primary" type="submit">Sign in to dashboard</button>
</form>
</section>
</main>
</body>
</html>