<?php
require_once __DIR__ . '/auth.php';
require_login();
?>
<!doctype html>
<html lang="en" dir="ltr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($pageTitle) ?> | Barangay Cataggaman Nuevo</title>
<link rel="stylesheet" href="styles.css">
</head>
<body data-page="<?= htmlspecialchars($page) ?>">
<aside class="sidebar" id="sidebar">
<div class="brand"><div class="brand-mark"><img src="Cat.jpg" alt="Barangay Cataggaman Nuevo logo"></div>Barangay Cataggaman Nuevo<span>Information Management System</span></div>
<div class="nav-title">Workspace</div>
<a class="nav<?= $page === 'dashboard' ? ' active' : '' ?>" href="index.php">Dashboard</a>
<a class="nav<?= $page === 'residents' ? ' active' : '' ?>" href="residents.php">Resident Records</a>
<a class="nav<?= $page === 'incidents' ? ' active' : '' ?>" href="incidents.php">Incident Reports</a>
<a class="nav<?= $page === 'certificates' ? ' active' : '' ?>" href="certificates.php">Generate Certificates</a>
<?php if (strcasecmp(current_user_role(), 'admin') === 0): ?>
<div class="nav-divider" aria-hidden="true"></div>
<a class="nav nav-add-user<?= $page === 'add-user' ? ' active' : '' ?>" href="add_user.php"><span class="nav-add-icon" aria-hidden="true">+</span><span>Add user</span><span class="nav-add-arrow" aria-hidden="true">&rarr;</span></a>
<?php endif; ?>
</aside>
<main class="main">
<header class="top">
<div class="top-title">
<button class="btn mobile" id="menu" aria-label="Open menu">Menu</button>
<div><h1>Cataggaman Nuevo</h1><small>Barangay Information Management System</small></div>
</div>
<div class="top-tools">
<div class="clock" id="clock"></div>
<div class="user-panel"><span class="user-avatar"><img src="Cat.jpg" alt=""></span><span id="currentUser" aria-label="Signed-in user"><?= htmlspecialchars(current_user_role()) ?></span></div>
<a class="btn logout" href="logout.php">Log out</a>
</div>
</header>
<div class="content">
