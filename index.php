<?php

require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/db/connection.php';
require_once __DIR__ . '/includes/flash.php';

$residentCount = (int) $pdo->query('SELECT COUNT(*) FROM residents')->fetchColumn();
$incidentCount = (int) $pdo->query('SELECT COUNT(*) FROM incidents')->fetchColumn();
$activeCases = (int) $pdo->query("SELECT COUNT(*) FROM incidents WHERE status <> 'Resolved'")->fetchColumn();
$certificateToday = (int) $pdo->query('SELECT COUNT(*) FROM certificates WHERE issue_date = CURDATE()')->fetchColumn();

$activity = $pdo->query("
    (SELECT CONCAT('Resident record added: ', name) AS text, created_at FROM residents)
    UNION ALL
    (SELECT CONCAT('Incident reported: ', case_no, ' \xC2\xB7 ', type) AS text, created_at FROM incidents)
    UNION ALL
    (SELECT CONCAT(type, ' issued to ', name) AS text, created_at FROM certificates)
    ORDER BY created_at DESC
    LIMIT 6
")->fetchAll();

$page = 'dashboard';
$pageTitle = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>
<div class="heading">
<div>
<h2>Dashboard</h2>
<p>Overview of your barangay records.</p>
</div>
</div>

<div class="cards">
<article class="card stat">
<div class="stat-icon"><?= icon('residents') ?></div>
<div class="stat-body">
<label id="residentCountLabel">Total Residents</label>
<strong id="residentCount" aria-labelledby="residentCountLabel"><?= $residentCount ?></strong>
<small>Registered residents</small>
</div>
</article>

<article class="card stat">
<div class="stat-icon"><?= icon('incidents') ?></div>
<div class="stat-body">
<label id="incidentCountLabel">Total Incidents</label>
<strong id="incidentCount" aria-labelledby="incidentCountLabel"><?= $incidentCount ?></strong>
<small id="activeCases"><?= $activeCases ?> active <?= $activeCases === 1 ? 'case' : 'cases' ?></small>
</div>
</article>

<article class="card stat">
<div class="stat-icon"><?= icon('certificate') ?></div>
<div class="stat-body">
<label id="certificateCountLabel">Certificates Issued Today</label>
<strong id="certificateCount" aria-labelledby="certificateCountLabel"><?= $certificateToday ?></strong>
<small>Issued today</small>
</div>
</article>
</div>

<section class="panel">
<h3>Quick actions</h3>
<div class="quick">
<a href="residents.php?new=1">
<span class="stat-icon" aria-hidden="true"><?= icon('residents') ?></span>
<span><b>Add a resident</b><small>Create a resident record</small></span>
</a>
<a href="incidents.php?new=1">
<span class="stat-icon" aria-hidden="true"><?= icon('incidents') ?></span>
<span><b>File an incident</b><small>Record a new report</small></span>
</a>
<a href="certificates.php">
<span class="stat-icon" aria-hidden="true"><?= icon('certificate') ?></span>
<span><b>Issue a certificate</b><small>Prepare and print a certificate</small></span>
</a>
</div>
</section>

<section class="panel">
<h3>Recent activity</h3>
<div id="activity">
<?php if (!$activity): ?>
<div class="empty"><?= icon('inbox') ?>No activity recorded yet.</div>
<?php else: foreach ($activity as $item): ?>
<div class="activity">
<span class="activity-dot" aria-hidden="true"></span>
<span><?= e($item['text']) ?><small><?= date('n/j/Y, g:i A', strtotime($item['created_at'])) ?></small></span>
</div>
<?php endforeach; endif; ?>
</div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
