<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function require_admin(): void
{
    require_login();

    if (strcasecmp((string) ($_SESSION['role'] ?? ''), 'admin') !== 0) {
        http_response_code(403);
        exit('Administrator access required.');
    }
}

function current_user_role(): string
{
    return $_SESSION['role'] ?? 'Unknown user';
}
