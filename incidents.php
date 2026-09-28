<?php

require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/db/connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formAction = $_POST['form_action'] ?? '';

    if ($formAction === 'save') {
        $id = $_POST['id'] ?? '';
        $rawDate = $_POST['date'] ?? '';
        $date = $rawDate !== '' ? date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $rawDate))) : null;
        $values = [
            trim($_POST['caseNo'] ?? ''),
            trim($_POST['complainant'] ?? ''),
            trim($_POST['respondent'] ?? ''),
            trim($_POST['type'] ?? ''),
            $date,
            $_POST['status'] ?? 'Pending',
            trim($_POST['description'] ?? ''),
        ];

        if ($id !== '') {
            $stmt = $pdo->prepare('UPDATE incidents SET case_no=?, complainant=?, respondent=?, type=?, incident_date=?, status=?, description=? WHERE id=?');
            $stmt->execute([...$values, $id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO incidents (case_no, complainant, respondent, type, incident_date, status, description, recorded_by, created_at) VALUES (?,?,?,?,?,?,?,?,NOW())');
            $stmt->execute([...$values, current_user_role()]);
        }
    } elseif ($formAction === 'delete') {
        $stmt = $pdo->prepare('DELETE FROM incidents WHERE id = ?');
        $stmt->execute([$_POST['id'] ?? '']);
    } elseif ($formAction === 'cycle') {
        $cycle = ['Pending' => 'Under Investigation', 'Under Investigation' => 'Resolved', 'Resolved' => 'Pending'];
        $stmt = $pdo->prepare('SELECT status FROM incidents WHERE id = ?');
        $stmt->execute([$_POST['id'] ?? '']);
        $current = $stmt->fetchColumn();
        $next = $cycle[$current] ?? 'Pending';
        $stmt = $pdo->prepare('UPDATE incidents SET status = ? WHERE id = ?');
        $stmt->execute([$next, $_POST['id'] ?? '']);
    }

    header('Location: incidents.php');
    exit;
}

$search = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$editId = $_GET['edit'] ?? null;
$showDialog = isset($_GET['new']) || $editId !== null;

$conditions = [];
$params = [];
if ($search !== '') {
    $like = "%$search%";
    $conditions[] = '(case_no LIKE ? OR complainant LIKE ? OR respondent LIKE ? OR type LIKE ? OR status LIKE ? OR description LIKE ?)';
    array_push($params, $like, $like, $like, $like, $like, $like);
}
if ($status !== '') {
    $conditions[] = 'status = ?';
    $params[] = $status;
}
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
$stmt = $pdo->prepare("SELECT * FROM incidents $where ORDER BY created_at DESC");
$stmt->execute($params);
$incidents = $stmt->fetchAll();

$editItem = null;
if ($editId !== null) {
    $stmt = $pdo->prepare('SELECT * FROM incidents WHERE id = ?');
    $stmt->execute([$editId]);
    $editItem = $stmt->fetch() ?: null;
}

$defaultCaseNo = '';
$defaultDate = '';
if ($showDialog && !$editItem) {
    $incidentCount = (int) $pdo->query('SELECT COUNT(*) FROM incidents')->fetchColumn();
    $defaultCaseNo = 'CN-' . date('Y') . '-' . str_pad((string) ($incidentCount + 1), 3, '0', STR_PAD_LEFT);
    $defaultDate = date('Y-m-d\TH:i');
}

$formDate = '';
if ($editItem && $editItem['incident_date']) {
    $formDate = date('Y-m-d\TH:i', strtotime($editItem['incident_date']));
} elseif ($defaultDate) {
    $formDate = $defaultDate;
}

$page = 'incidents';
$pageTitle = 'Incidents';
require __DIR__ . '/includes/header.php';
?>
<div class="heading">
<div>
<h2>Incident Reports</h2>
<p>Record and follow up reported incidents.</p>
</div>
<a class="btn primary" href="incidents.php?new=1">File Incident Report</a>
</div>
<section class="panel">
<form class="toolbar" method="get">
<input id="incidentSearch" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search cases, people, or incident type..." aria-label="Search incidents" oninput="this.form.requestSubmit()" <?= $showDialog ? '' : 'autofocus' ?>>
<select id="incidentFilter" name="status" aria-label="Filter by status" onchange="this.form.requestSubmit()">
<option value="">All statuses</option>
<?php foreach (['Pending', 'Under Investigation', 'Resolved'] as $opt): ?>
<option <?= $status === $opt ? 'selected' : '' ?>><?= $opt ?></option>
<?php endforeach; ?>
</select>
</form>
<div class="table-wrap">
<table>
<thead>
<tr>
<th>Case no.</th>
<th>Complainant</th>
<th>Respondent</th>
<th>Type</th>
<th>Date</th>
<th>Status</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php if (!$incidents): ?>
<tr><td class="empty" colspan="7">No incident reports found.</td></tr>
<?php else: foreach ($incidents as $item):
    $badgeClass = $item['status'] === 'Resolved' ? 'resolved' : ($item['status'] === 'Under Investigation' ? 'investigation' : '');
?>
<tr>
<td><b><?= htmlspecialchars($item['case_no']) ?></b></td>
<td><?= htmlspecialchars($item['complainant']) ?></td>
<td><?= $item['respondent'] ? htmlspecialchars($item['respondent']) : '&mdash;' ?></td>
<td><?= htmlspecialchars($item['type']) ?></td>
<td><?= $item['incident_date'] ? date('n/j/Y, g:i A', strtotime($item['incident_date'])) : '&mdash;' ?></td>
<td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($item['status']) ?></span></td>
<td>
<form method="post" style="display:inline">
<input type="hidden" name="form_action" value="cycle">
<input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
<button class="btn">Update status</button>
</form>
<a class="btn" href="incidents.php?edit=<?= (int) $item['id'] ?>">Edit</a>
<form method="post" style="display:inline">
<input type="hidden" name="form_action" value="delete">
<input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
<button class="btn danger" data-confirm="Delete this incident report?">Delete</button>
</form>
</td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>
</section>

<dialog class="modal" id="recordDialog" <?= $showDialog ? 'data-autoopen="1"' : '' ?>>
<form method="post">
<input type="hidden" name="form_action" value="save">
<?php if ($editItem): ?><input type="hidden" name="id" value="<?= (int) $editItem['id'] ?>"><?php endif; ?>
<div class="dialog-title">
<h3><?= $editItem ? 'Edit incident report' : 'File incident report' ?></h3>
<a class="btn" href="incidents.php">Close</a>
</div>
<div class="form-grid">
<div class="field">
<label>Case no. *</label>
<input name="caseNo" required value="<?= htmlspecialchars($editItem['case_no'] ?? $defaultCaseNo) ?>">
</div>
<div class="field">
<label>Incident type *</label>
<input name="type" required value="<?= htmlspecialchars($editItem['type'] ?? '') ?>">
</div>
<div class="field">
<label>Complainant *</label>
<input name="complainant" required autofocus value="<?= htmlspecialchars($editItem['complainant'] ?? '') ?>">
</div>
<div class="field">
<label>Respondent</label>
<input name="respondent" value="<?= htmlspecialchars($editItem['respondent'] ?? '') ?>">
</div>
<div class="field">
<label>Date</label>
<input name="date" type="datetime-local" value="<?= htmlspecialchars($formDate) ?>">
</div>
<div class="field">
<label>Status</label>
<select name="status">
<?php foreach (['Pending', 'Under Investigation', 'Resolved'] as $opt): ?>
<option <?= ($editItem['status'] ?? 'Pending') === $opt ? 'selected' : '' ?>><?= $opt ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="field">
<label>Recorded by</label>
<input value="<?= htmlspecialchars($editItem['recorded_by'] ?? current_user_role()) ?>" readonly>
</div>
<div class="field full">
<label>Description</label>
<textarea name="description"><?= htmlspecialchars($editItem['description'] ?? '') ?></textarea>
</div>
</div>
<div class="form-actions">
<a class="btn" href="incidents.php">Cancel</a>
<button class="btn primary">Save report</button>
</div>
</form>
</dialog>
<?php require __DIR__ . '/includes/footer.php'; ?>
