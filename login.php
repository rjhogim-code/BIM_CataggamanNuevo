<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/db/connection.php';
require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/includes/flash.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

/**
 * Roles offered at registration.
 *
 * NOTE for the backend/RBAC work: the manuscript specifies a fixed four-value
 * role (Captain / Secretary / Treasurer / Staff). This list is still the older
 * three-option version, and "Other" lets a person type any label, so role is
 * currently descriptive rather than an access-control mechanism. Validation
 * below confines the value to this list and constrains the free-text option,
 * but turning role into a real enum and enforcing it per page is the students'
 * RBAC task — see PROJECT_STATUS.md.
 */
const ROLE_OPTIONS = ['Captain', 'Secretary', 'Other'];

$loginMessage = '';
$registerErrors = [];
$showRegister = false;
$registerInput = ['register_username' => '', 'register_role' => 'Captain', 'custom_role' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('login.php');
    $formAction = post_str('form_action');

    if ($formAction === 'login') {
        $username = post_str('username');
        $password = $_POST['password'] ?? '';

        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $account = $stmt->fetch();

        if (!$account || !password_verify($password, $account['password'])) {
            // Deliberately one message for both cases: saying which half was
            // wrong would confirm to a stranger that a username exists.
            $loginMessage = 'Username or password is incorrect. Please try again or register an account.';
        } else {
            // A fresh session id on sign-in, so a session fixed before login
            // can't be reused afterwards.
            session_regenerate_id(true);
            $_SESSION['user_id'] = $account['id'];
            $_SESSION['username'] = $account['username'];
            $_SESSION['role'] = $account['role'];
            flash_success('Signed in as ' . $account['role'] . '.');
            header('Location: index.php');
            exit;
        }
    } elseif ($formAction === 'register') {
        $showRegister = true;

        $registerInput = [
            'register_username' => post_str('register_username'),
            'register_role'     => post_str('register_role'),
            'custom_role'       => post_str('custom_role'),
        ];
        $password = $_POST['register_password'] ?? '';

        $registerErrors = collect_errors([
            'register_username' => validate_username($registerInput['register_username']),
            'register_password' => validate_password($password),
            'register_role'     => validate_choice($registerInput['register_role'], ROLE_OPTIONS, 'Account identifier'),
        ]);

        // The free-text role is still a stored value, so it gets the same name
        // treatment as any other label: letters and basic punctuation only.
        if ($registerInput['register_role'] === 'Other') {
            $customError = validate_name($registerInput['custom_role'], 'Role', true, 100);
            if ($customError !== null) {
                $registerErrors['custom_role'] = $customError;
            }
        }

        if (!isset($registerErrors['register_username'])) {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
            $stmt->execute([$registerInput['register_username']]);
            if ($stmt->fetch()) {
                $registerErrors['register_username'] = 'That username is already registered.';
            }
        }

        if (!$registerErrors) {
            $role = $registerInput['register_role'] === 'Other'
                ? $registerInput['custom_role']
                : $registerInput['register_role'];

            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare('INSERT INTO users (username, password, role, created_at) VALUES (?, ?, ?, NOW())');
                $stmt->execute([
                    $registerInput['register_username'],
                    password_hash($password, PASSWORD_DEFAULT),
                    $role,
                ]);
                $pdo->commit();

                $showRegister = false;
                $registerInput = ['register_username' => '', 'register_role' => 'Captain', 'custom_role' => ''];
                $loginMessage = 'Account registered. Sign in with your new credentials.';
            } catch (PDOException $e) {
                $pdo->rollBack();
                $registerErrors['register_username'] = 'The account could not be created because of a database error.';
            }
        }
    }
}

$isOther = $registerInput['register_role'] === 'Other';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="light">
<title>Sign in | Barangay Cataggaman Nuevo</title>
<link rel="stylesheet" href="styles.css">
</head>
<body class="login-page" data-page="login">
<main class="login-shell">

<section class="login-welcome">
<div class="login-logo" aria-hidden="true">CN</div>
<p class="eyebrow">BARANGAY INFORMATION MANAGEMENT SYSTEM</p>
<h1>Welcome to Cataggaman Nuevo.</h1>
<p>Keep resident records, incident reports, and certificates organized in one calm workspace.</p>
</section>

<section class="login-card" id="loginCard" <?= $showRegister ? 'hidden' : '' ?>>
<h2>Sign in</h2>
<p>Enter your account details to continue.</p>
<form class="login-form" method="post" novalidate>
<?= csrf_field() ?>
<input type="hidden" name="form_action" value="login">

<label for="username">Username
<input id="username" name="username" autocomplete="username" placeholder="Enter your username"
       required <?= $showRegister ? '' : 'autofocus' ?>>
</label>

<label for="password">Password
<input id="password" name="password" type="password" autocomplete="current-password"
       placeholder="Enter your password" required>
</label>

<?php if ($loginMessage !== ''): ?>
<p class="login-message" id="loginMessage" role="alert"><?= e($loginMessage) ?></p>
<?php endif; ?>

<button class="btn primary" type="submit">Sign in to dashboard</button>
</form>
<button class="login-switch" id="showRegister" type="button">Need an account? Register here</button>
</section>

<section class="login-card" id="registerCard" <?= $showRegister ? '' : 'hidden' ?>>
<h2>Register account</h2>
<p>Create your account before signing in.</p>
<form class="login-form" method="post" novalidate>
<?= csrf_field() ?>
<input type="hidden" name="form_action" value="register">

<label for="registerUsername">Username
<input id="registerUsername" name="register_username" autocomplete="username" placeholder="Choose a username"
       data-validate="username" required <?= $showRegister ? 'autofocus' : '' ?>
       value="<?= e($registerInput['register_username']) ?>"<?= field_attrs($registerErrors, 'register_username') ?>>
<?= field_error($registerErrors, 'register_username') ?>
<span class="login-hint">4-50 characters. Letters, numbers, dots, underscores, hyphens.</span>
</label>

<label for="registerPassword">Password
<input id="registerPassword" name="register_password" type="password" autocomplete="new-password"
       placeholder="Create a password" data-validate="password" minlength="8" required<?= field_attrs($registerErrors, 'register_password') ?>>
<?= field_error($registerErrors, 'register_password') ?>
<span class="login-hint">At least 8 characters, including one letter and one number.</span>
</label>

<label for="registerRole">Account identifier
<select id="registerRole" name="register_role"<?= field_attrs($registerErrors, 'register_role') ?>>
<option value="Captain" <?= $registerInput['register_role'] === 'Captain' ? 'selected' : '' ?>>Barangay Captain</option>
<option value="Secretary" <?= $registerInput['register_role'] === 'Secretary' ? 'selected' : '' ?>>Barangay Secretary</option>
<option value="Other" <?= $isOther ? 'selected' : '' ?>>Other user</option>
</select>
<?= field_error($registerErrors, 'register_role') ?>
</label>

<label id="customRoleField" for="customRole" <?= $isOther ? '' : 'hidden' ?>>Specify your role
<input id="customRole" name="custom_role" placeholder="e.g. Barangay Treasurer" maxlength="100"
       value="<?= e($registerInput['custom_role']) ?>"<?= field_attrs($registerErrors, 'custom_role') ?>>
<?= field_error($registerErrors, 'custom_role') ?>
</label>

<button class="btn primary" type="submit">Create account</button>
</form>
<button class="login-switch" id="showLogin" type="button">Already registered? Sign in</button>
</section>

</main>
<script src="app.js"></script>
<script>
const loginCard = document.querySelector('#loginCard');
const registerCard = document.querySelector('#registerCard');

document.querySelector('#showRegister').onclick = () => {
  loginCard.hidden = true;
  registerCard.hidden = false;
  document.querySelector('#registerUsername').focus();
};

document.querySelector('#showLogin').onclick = () => {
  registerCard.hidden = true;
  loginCard.hidden = false;
  document.querySelector('#username').focus();
};

document.querySelector('#registerRole').onchange = event => {
  const other = event.target.value === 'Other';
  document.querySelector('#customRoleField').hidden = !other;
  if (other) document.querySelector('#customRole').focus();
};
</script>
</body>
</html>
