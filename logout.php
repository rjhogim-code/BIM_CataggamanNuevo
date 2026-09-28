<?php

require_once __DIR__ . '/includes/auth.php';

$_SESSION = [];

// Expire the session cookie as well, not just the server-side data. Without
// this the browser keeps sending a dead session id after logout, which on a
// shared barangay workstation is exactly the state you don't want left behind.
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

header('Location: login.php');
exit;
