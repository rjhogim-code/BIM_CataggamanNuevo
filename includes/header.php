<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($pageTitle) ?> | Barangay Cataggaman Nuevo</title>
<link rel="stylesheet" href="styles.css">
</head>
<body data-page="<?= htmlspecialchars($page) ?>">
<aside class="sidebar" id="sidebar">
<div class="brand"><div class="brand-mark">CN</div>Barangay Cataggaman Nuevo<span>Information Management System</span></div>
<div class="nav-title">Workspace</div>
<a class="nav<?= $page === 'dashboard' ? ' active' : '' ?>" href="index.php">Dashboard</a>
<a class="nav<?= $page === 'residents' ? ' active' : '' ?>" href="residents.php">Resident Records</a>
<a class="nav<?= $page === 'incidents' ? ' active' : '' ?>" href="incidents.php">Incident Reports</a>
<a class="nav<?= $page === 'certificates' ? ' active' : '' ?>" href="certificates.php">Generate Certificates</a>
</aside>
<main class="main">
<header class="top">
<div class="top-title">
<button class="btn mobile" id="menu" aria-label="Open menu">Menu</button>
<div><h1>Cataggaman Nuevo</h1><small>Barangay Information Management System</small></div>
</div>
<div class="top-tools">
<div class="clock" id="clock"></div>
<div class="user-panel"><span class="user-avatar">CN</span><span id="currentUser" aria-label="Signed-in user"><?= htmlspecialchars(current_user_role()) ?></span></div>
<a class="btn logout" href="logout.php">Log out</a>
</div>
</header>
<div class="content">
