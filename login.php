<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/db/connection.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$loginMessage = '';
$registerMessage = '';
$showRegister = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formAction = $_POST['form_action'] ?? '';

    if ($formAction === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $account = $stmt->fetch();

        if (!$account || !password_verify($password, $account['password'])) {
            $loginMessage = 'Username or password is incorrect. Please register or try again.';
        } else {
            $_SESSION['user_id'] = $account['id'];
            $_SESSION['username'] = $account['username'];
            $_SESSION['role'] = $account['role'];
            header('Location: index.php');
            exit;
        }
    } elseif ($formAction === 'register') {
        $showRegister = true;

        $username = trim($_POST['register_username'] ?? '');
        $password = $_POST['register_password'] ?? '';
        $roleChoice = $_POST['register_role'] ?? '';
        $customRole = trim($_POST['custom_role'] ?? '');

        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$username]);

        if ($username === '') {
            $registerMessage = 'Please enter a username.';
        } elseif ($stmt->fetch()) {
            $registerMessage = 'That username is already registered.';
        } elseif ($roleChoice === 'Other' && $customRole === '') {
            $registerMessage = 'Please specify your user identifier.';
        } elseif (strlen($password) < 6) {
            $registerMessage = 'Password must be at least 6 characters.';
        } else {
            $role = $roleChoice === 'Other' ? $customRole : $roleChoice;
            $stmt = $pdo->prepare('INSERT INTO users (username, password, role, created_at) VALUES (?, ?, ?, NOW())');
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role]);
            $showRegister = false;
            $loginMessage = 'Account registered. Sign in with your new credentials.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sign in | Barangay Cataggaman Nuevo</title>
<link rel="stylesheet" href="styles.css">
</head>
<body class="login-page" data-page="login">
<main class="login-shell">
<section class="login-welcome">
<div class="login-logo">CN</div>
<p>BARANGAY INFORMATION MANAGEMENT SYSTEM</p>
<h1>Welcome to Cataggaman Nuevo.</h1>
<p>Keep resident records, incident reports, and certificates organized in one calm workspace.</p>
</section>
<section class="login-card" id="loginCard" <?= $showRegister ? 'hidden' : '' ?>>
<h2>Sign in</h2>
<p>Enter your account details to continue.</p>
<form class="login-form" method="post">
<input type="hidden" name="form_action" value="login">
<label for="username">Username
<input id="username" name="username" autocomplete="username" placeholder="Enter your username" required <?= $showRegister ? '' : 'autofocus' ?>>
</label>
<label for="password">Password
<input id="password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" required>
</label>
<p class="login-message" id="loginMessage" <?= $loginMessage === '' ? 'hidden' : '' ?>><?= htmlspecialchars($loginMessage) ?></p>
<button class="btn primary" type="submit">Sign in to dashboard</button>
</form>
<button class="login-switch" id="showRegister" type="button">Need an account? Register here</button>
</section>
<section class="login-card" id="registerCard" <?= $showRegister ? '' : 'hidden' ?>>
<h2>Register account</h2>
<p>Create your account before signing in.</p>
<form class="login-form" method="post">
<input type="hidden" name="form_action" value="register">
<label for="registerUsername">Username
<input id="registerUsername" name="register_username" autocomplete="username" placeholder="Choose a username" required <?= $showRegister ? 'autofocus' : '' ?>>
</label>
<label for="registerPassword">Password
<input id="registerPassword" name="register_password" type="password" autocomplete="new-password" placeholder="Create a password" minlength="6" required>
</label>
<label for="registerRole">Account identifier
<select id="registerRole" name="register_role">
<option value="Captain">Barangay Captain</option>
<option value="Secretary">Barangay Secretary</option>
<option value="Other">Other user</option>
</select>
</label>
<label id="customRoleField" for="customRole" hidden>Specify your role
<input id="customRole" name="custom_role" placeholder="e.g. Barangay Treasurer">
</label>
<p class="login-message" id="registerMessage" <?= $registerMessage === '' ? 'hidden' : '' ?>><?= htmlspecialchars($registerMessage) ?></p>
<button class="btn primary" type="submit">Create account</button>
</form>
<button class="login-switch" id="showLogin" type="button">Already registered? Sign in</button>
</section>
</main>
<script>
document.querySelector('#showRegister').onclick = () => { document.querySelector('#loginCard').hidden = true; document.querySelector('#registerCard').hidden = false; };
document.querySelector('#showLogin').onclick = () => { document.querySelector('#registerCard').hidden = true; document.querySelector('#loginCard').hidden = false; };
document.querySelector('#registerRole').onchange = event => { document.querySelector('#customRoleField').hidden = event.target.value !== 'Other'; };
</script>
</body>
</html>
