<?php

require_once __DIR__ . '/includes/auth.php';
require_admin();
require_once __DIR__ . '/db/connection.php';

$userNotice = '';
$userNoticeIsError = false;
$currentDatabaseUserId = null;
$currentUsername = $_SESSION['username'] ?? '';
if ($currentUsername !== '') {
    $currentUserStatement = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $currentUserStatement->execute([$currentUsername]);
    $currentDatabaseUserId = $currentUserStatement->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formAction = $_POST['form_action'] ?? 'create';
    $userId = (int) ($_POST['user_id'] ?? 0);
    $newUsername = trim($_POST['username'] ?? '');
    $newPassword = $_POST['password'] ?? '';

    if ($formAction === 'delete') {
        if ($currentDatabaseUserId !== false && $currentDatabaseUserId !== null && $userId === (int) $currentDatabaseUserId) {
            $userNotice = 'You cannot delete the account currently signed in.';
            $userNoticeIsError = true;
        } else {
            $statement = $pdo->prepare('DELETE FROM users WHERE id = ?');
            $statement->execute([$userId]);
            $userNotice = 'User deleted.';
        }
    } elseif ($newUsername === '' || ($formAction === 'create' && $newPassword === '')) {
        $userNotice = $formAction === 'update' ? 'Enter a username.' : 'Enter both a username and password.';
        $userNoticeIsError = true;
    } else {
        try {
            if ($formAction === 'update') {
                if ($newPassword !== '') {
                    $statement = $pdo->prepare('UPDATE users SET username = ?, password = ? WHERE id = ?');
                    $statement->execute([$newUsername, password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
                } else {
                    $statement = $pdo->prepare('UPDATE users SET username = ? WHERE id = ?');
                    $statement->execute([$newUsername, $userId]);
                }
                $userNotice = 'User details updated.';
            } else {
                $statement = $pdo->prepare('INSERT INTO users (username, password) VALUES (:username, :password)');
                $statement->execute([
                    ':username' => $newUsername,
                    ':password' => password_hash($newPassword, PASSWORD_DEFAULT),
                ]);
                $userNotice = 'User created. They can now sign in to the dashboard.';
            }
        } catch (PDOException $exception) {
            $userNotice = 'Unable to save the user. Check that the username is unique and that the users table exists.';
            $userNoticeIsError = true;
        }
    }
}

$userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$users = $pdo->query('SELECT id, username, role, created_at FROM users ORDER BY created_at DESC')->fetchAll();
$editUser = null;
if (isset($_GET['edit'])) {
    $statement = $pdo->prepare('SELECT id, username FROM users WHERE id = ?');
    $statement->execute([(int) $_GET['edit']]);
    $editUser = $statement->fetch() ?: null;
}

$page = 'add-user';
$pageTitle = 'Add User';
require __DIR__ . '/includes/header.php';
?>
<div class="heading">
<div>
<h2><?= $editUser ? 'Edit user' : 'Add a new user' ?></h2>
<p><?= $editUser ? 'Update the username or set a new password.' : 'Create a secure account for someone who needs access to the dashboard.' ?></p>
</div>
</div>
<section class="panel user-create-panel">
<div class="user-create-intro">
<span class="user-create-icon" aria-hidden="true">+</span>
<div>
<h3><?= $editUser ? 'Update account' : 'Welcome to the team' ?></h3>
<p><?= $editUser ? 'Leave the password blank to keep the current password.' : 'Set up login details below. The password will be protected before it is saved.' ?></p>
</div>
</div>
<?php if ($userNotice !== ''): ?>
<p role="status" class="<?= $userNoticeIsError ? 'error' : 'success' ?>"><?= htmlspecialchars($userNotice, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<form class="user-create-form" method="post">
<input type="hidden" name="form_action" value="<?= $editUser ? 'update' : 'create' ?>">
<?php if ($editUser): ?><input type="hidden" name="user_id" value="<?= (int) $editUser['id'] ?>"><?php endif; ?>
<label for="new-username">Username
<input id="new-username" name="username" type="text" autocomplete="username" value="<?= htmlspecialchars($editUser['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
</label>
<label for="new-password">Password
<input id="new-password" name="password" type="password" autocomplete="new-password" <?= $editUser ? '' : 'required' ?>>
</label>
<button class="btn primary" type="submit"><?= $editUser ? 'Update user' : 'Create user' ?></button>
<?php if ($editUser): ?><a class="btn close-button" href="add_user.php">Cancel</a><?php endif; ?>
</form>
</section>
<section class="panel user-list-panel">
<div class="user-list-heading">
<div>
<h3>Registered users</h3>
<p>Accounts currently allowed to sign in.</p>
</div>
<strong class="user-count"><?= $userCount ?></strong>
</div>
<div class="table-wrap">
<table>
<thead>
<tr>
<th>Username</th>
<th>Role</th>
<th>Password</th>
<th>Created</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php if (!$users): ?>
<tr><td class="empty" colspan="5">No registered users yet.</td></tr>
<?php else: foreach ($users as $user): ?>
<tr>
<td><b><?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?></b></td>
<td><?= htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8') ?></td>
<td><span class="password-protected">Protected</span></td>
<td><?= htmlspecialchars($user['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
<td class="user-actions">
<a class="btn" href="add_user.php?edit=<?= (int) $user['id'] ?>">Edit</a>
<?php if ($currentDatabaseUserId === null || $currentDatabaseUserId === false || (int) $user['id'] !== (int) $currentDatabaseUserId): ?>
<form method="post" style="display:inline">
<input type="hidden" name="form_action" value="delete">
<input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
<button class="btn danger" type="submit" data-confirm="Delete this user account?">Delete</button>
</form>
<?php endif; ?>
</td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>