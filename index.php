<?php

require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/db/connection.php';

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
$pageTitle = 'DASHBOARD';
require __DIR__ . '/includes/header.php';
require_once __DIR__ . '/db/connection.php';
?>
<div class="heading">
<div>
<h2><strong>DASHBOARD</strong></h2>
<p>Overview of your barangay records.</p>
</div>
</div>
<div class="cards">
<article class="card stat">
<label>Total Residents</label>
<strong id="residentCount"><?= $residentCount ?></strong>
<small>Registered residents</small>
</article>
<article class="card stat">
<label>Total Incidents</label>
<strong id="incidentCount"><?= $incidentCount ?></strong>
<small id="activeCases"><?= $activeCases ?> active cases</small>
</article>
<article class="card stat">
<label>Certificates Issued Today</label>
<strong id="certificateCount"><?= $certificateToday ?></strong>
<small>Issued today</small>
</article>
</div>
<section class="panel">
<h3>Quick actions</h3>
<div class="quick">
<a href="residents.php">
<b>Add a resident</b>
<small>Create a resident record</small>
</a>
<a href="certificates.php">
<b>Issue a certificate</b>
<small>Prepare and print a certificate</small>
</a>
</div>
</section>
<section class="panel">
<h3>Recent activity</h3>
<div id="activity">
<?php if (!$activity): ?>
<div class="empty">No activity recorded yet.</div>
<?php else: foreach ($activity as $item): ?>
<div class="activity"><?= htmlspecialchars($item['text']) ?><small><?= date('n/j/Y, g:i A', strtotime($item['created_at'])) ?></small></div>
<?php endforeach; endif; ?>
</div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
