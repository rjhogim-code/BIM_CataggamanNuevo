<?php

require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/db/connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formAction = $_POST['form_action'] ?? '';

    if ($formAction === 'save') {
        $id = $_POST['id'] ?? '';
        $values = [
            trim($_POST['name'] ?? ''),
            $_POST['age'] !== '' ? (int) $_POST['age'] : null,
            $_POST['gender'] ?? null,
            $_POST['civil'] ?? null,
            $_POST['voter'] ?? null,
            trim($_POST['zone'] ?? ''),
            trim($_POST['contact'] ?? ''),
        ];

        if ($id !== '') {
            $stmt = $pdo->prepare('UPDATE residents SET name=?, age=?, gender=?, civil=?, voter=?, zone=?, contact=? WHERE id=?');
            $stmt->execute([...$values, $id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO residents (name, age, gender, civil, voter, zone, contact, recorded_by, created_at) VALUES (?,?,?,?,?,?,?,?,NOW())');
            $stmt->execute([...$values, current_user_role()]);
        }
    } elseif ($formAction === 'delete') {
        $stmt = $pdo->prepare('DELETE FROM residents WHERE id = ?');
        $stmt->execute([$_POST['id'] ?? '']);
    }

    header('Location: residents.php');
    exit;
}

$search = trim($_GET['q'] ?? '');
$editId = $_GET['edit'] ?? null;
$showDialog = isset($_GET['new']) || $editId !== null;

if ($search !== '') {
    $like = "%$search%";
    $stmt = $pdo->prepare('SELECT * FROM residents WHERE name LIKE ? OR zone LIKE ? OR gender LIKE ? OR civil LIKE ? OR contact LIKE ? OR voter LIKE ? ORDER BY created_at DESC');
    $stmt->execute([$like, $like, $like, $like, $like, $like]);
    $residents = $stmt->fetchAll();
} else {
    $residents = $pdo->query('SELECT * FROM residents ORDER BY created_at DESC')->fetchAll();
}

$editItem = null;
if ($editId !== null) {
    $stmt = $pdo->prepare('SELECT * FROM residents WHERE id = ?');
    $stmt->execute([$editId]);
    $editItem = $stmt->fetch() ?: null;
}

$page = 'residents';
$pageTitle = 'Residents';
require __DIR__ . '/includes/header.php';
?>
<div class="heading">
<div>
<h2>Resident Records</h2>
<p>Manage the barangay resident directory.</p>
</div>
<a class="btn primary" href="residents.php?new=1">Add New Resident</a>
</div>
<section class="panel">
<form class="toolbar" method="get">
<input id="residentSearch" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name or zone..." aria-label="Search residents" oninput="this.form.requestSubmit()" <?= $showDialog ? '' : 'autofocus' ?>>
</form>
<div class="table-wrap">
<table>
<thead>
<tr>
<th>Full name</th>
<th>Age</th>
<th>Gender</th>
<th>Civil status</th>
<th>Zone / Sitio</th>
<th>Contact</th>
<th>Voter</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php if (!$residents): ?>
<tr><td class="empty" colspan="8">No residents found.</td></tr>
<?php else: foreach ($residents as $item): ?>
<tr>
<td><b><?= htmlspecialchars($item['name']) ?></b></td>
<td><?= htmlspecialchars($item['age'] ?? '') ?: '&mdash;' ?></td>
<td><?= htmlspecialchars($item['gender'] ?? '') ?: '&mdash;' ?></td>
<td><?= htmlspecialchars($item['civil'] ?? '') ?: '&mdash;' ?></td>
<td><?= htmlspecialchars($item['zone'] ?? '') ?: '&mdash;' ?></td>
<td><?= htmlspecialchars($item['contact'] ?? '') ?: '&mdash;' ?></td>
<td><?= htmlspecialchars($item['voter'] ?? '') ?: '&mdash;' ?></td>
<td>
<a class="btn" href="residents.php?edit=<?= (int) $item['id'] ?>">Edit</a>
<form method="post" style="display:inline">
<input type="hidden" name="form_action" value="delete">
<input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
<button class="btn danger" data-confirm="Delete this resident record?">Delete</button>
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
<h3><?= $editItem ? 'Edit resident' : 'Add resident' ?></h3>
<a class="btn" href="residents.php">Close</a>
</div>
<div class="form-grid">
<div class="field full">
<label>Full name *</label>
<input name="name" required autofocus value="<?= htmlspecialchars($editItem['name'] ?? '') ?>">
</div>
<div class="field">
<label>Age</label>
<input name="age" type="number" min="0" max="125" value="<?= htmlspecialchars($editItem['age'] ?? '') ?>">
</div>
<div class="field">
<label>Gender</label>
<select name="gender">
<?php foreach (['Female', 'Male', 'Other'] as $opt): ?>
<option <?= ($editItem['gender'] ?? 'Female') === $opt ? 'selected' : '' ?>><?= $opt ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="field">
<label>Civil status</label>
<select name="civil">
<?php foreach (['Single', 'Married', 'Widowed', 'Separated'] as $opt): ?>
<option <?= ($editItem['civil'] ?? 'Single') === $opt ? 'selected' : '' ?>><?= $opt ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="field">
<label>Voter status</label>
<select name="voter">
<?php foreach (['Registered', 'Not registered'] as $opt): ?>
<option <?= ($editItem['voter'] ?? 'Registered') === $opt ? 'selected' : '' ?>><?= $opt ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="field">
<label>Zone / Sitio</label>
<input name="zone" value="<?= htmlspecialchars($editItem['zone'] ?? '') ?>">
</div>
<div class="field">
<label>Contact number</label>
<input name="contact" type="tel" value="<?= htmlspecialchars($editItem['contact'] ?? '') ?>">
</div>
<div class="field">
<label>Recorded by</label>
<input value="<?= htmlspecialchars($editItem['recorded_by'] ?? current_user_role()) ?>" readonly>
</div>
</div>
<div class="form-actions">
<a class="btn" href="residents.php">Cancel</a>
<button class="btn primary">Save resident</button>
</div>
</form>
</dialog>
<?php require __DIR__ . '/includes/footer.php'; ?>
