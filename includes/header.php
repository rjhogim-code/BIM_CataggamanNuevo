<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/flash.php';
require_once __DIR__ . '/icons.php';

$flashMessage = take_flash();

/** Initials for the avatar, e.g. "Barangay Secretary" -> "BS". */
$roleLabel = current_user_role();
$initials = '';
foreach (preg_split('/\s+/', trim($roleLabel)) ?: [] as $word) {
    if ($word === '') {
        continue;
    }
    $initials .= mb_strtoupper(mb_substr($word, 0, 1));
    if (mb_strlen($initials) >= 2) {
        break;
    }
}
$initials = $initials !== '' ? $initials : 'CN';

$navItems = [
    'dashboard'    => ['index.php',        'Dashboard',           'dashboard'],
    'residents'    => ['residents.php',    'Resident Records',    'residents'],
    'incidents'    => ['incidents.php',    'Incident Reports',    'incidents'],
    'certificates' => ['certificates.php', 'Generate Certificates', 'certificate'],
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="light">
<title><?= e($pageTitle) ?> | Barangay Cataggaman Nuevo</title>
<link rel="stylesheet" href="styles.css">
</head>
<body data-page="<?= e($page) ?>">
<a class="skip-link" href="#main-content">Skip to main content</a>

<aside class="sidebar" id="sidebar" aria-label="Main navigation">
<div class="brand">
<div class="brand-mark" aria-hidden="true">CN</div>
Barangay Cataggaman Nuevo
<span>Information Management System</span>
</div>
<nav aria-label="Workspace">
<div class="nav-title" id="nav-heading">Workspace</div>
<?php foreach ($navItems as $key => [$href, $label, $iconName]): ?>
<a class="nav<?= $page === $key ? ' active' : '' ?>" href="<?= $href ?>"<?= $page === $key ? ' aria-current="page"' : '' ?>><?= icon($iconName) ?><?= $label ?></a>
<?php endforeach; ?>
</nav>
</aside>
<button class="nav-backdrop" id="navBackdrop" type="button" tabindex="-1" aria-label="Close navigation menu"></button>

<div class="main">
<header class="top">
<div class="top-title">
<button class="btn ghost menu-btn" id="menu" type="button" aria-label="Open navigation menu" aria-controls="sidebar" aria-expanded="false"><?= icon('menu') ?></button>
<div><h1>Cataggaman Nuevo</h1><small>Barangay Information Management System</small></div>
</div>
<div class="top-tools">
<div class="clock" id="clock"></div>
<div class="user-panel">
<span class="user-avatar" aria-hidden="true"><?= e($initials) ?></span>
<span class="user-name" id="currentUser">Signed in as <?= e($roleLabel) ?></span>
</div>
<a class="btn logout" href="logout.php"><?= icon('logout') ?><span>Log out</span></a>
</div>
</header>

<main class="content" id="main-content" tabindex="-1">
<!-- Status region: announced to screen readers the moment a save succeeds or
     fails, without moving focus away from where the person is working. -->
<div id="flashRegion" role="status" aria-live="polite">
<?php if ($flashMessage): ?>
<div class="flash <?= e($flashMessage['type']) ?>">
<?= icon($flashMessage['type'] === 'success' ? 'check' : 'alert') ?>
<span><?= e($flashMessage['message']) ?></span>
<button class="flash-close" type="button" aria-label="Dismiss message">&times;</button>
</div>
<?php endif; ?>
</div>
