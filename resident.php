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
            trim($_POST['address'] ?? ''),
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

    header('Location: resident.php');
    exit;
}

$search = trim($_GET['q'] ?? '');
$gender = $_GET['gender'] ?? '';
$editId = $_GET['edit'] ?? null;
$showDialog = isset($_GET['new']) || $editId !== null;

$conditions = [];
$params = [];
if ($search !== '') {
    $like = "%$search%";
    $conditions[] = '(name LIKE ? OR zone LIKE ? OR gender LIKE ? OR civil LIKE ? OR contact LIKE ? OR voter LIKE ?)';
    array_push($params, $like, $like, $like, $like, $like, $like);
}
if ($gender !== '') {
    $conditions[] = 'gender = ?';
    $params[] = $gender;
}
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
$stmt = $pdo->prepare("SELECT * FROM residents $where ORDER BY created_at DESC");
$stmt->execute($params);
$residents = $stmt->fetchAll();

$editItem = null;
if ($editId !== null) {
    $stmt = $pdo->prepare('SELECT * FROM residents WHERE id = ?');
    $stmt->execute([$editId]);
    $editItem = $stmt->fetch() ?: null;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Resident Records</title>
<style>
:root{--pink:#db2777;--pale:#fdf2f8;--ink:#302831;--muted:#796f77;--line:#eee4e9}*{box-sizing:border-box}body{margin:0;background:#faf8f9;color:var(--ink);font:15px/1.5 system-ui, sans-serif}button,input,select{font:inherit}button{cursor:pointer}.wrap{max-width:1200px;margin:30px auto;padding:0 18px}.topbar{display:flex;justify-content:space-between;align-items:center;gap:15px;margin-bottom:18px}.btn{padding:9px 13px;border:1px solid var(--line);border-radius:8px;background:#fff;color:var(--ink);font-weight:650;text-decoration:none;display:inline-block}.primary{background:var(--pink);color:#fff;border-color:var(--pink)}.panel{background:white;border:1px solid var(--line);border-radius:13px;box-shadow:0 6px 22px #4320340a;padding:18px}.toolbar{display:flex;gap:10px;margin-bottom:15px}.toolbar input, .toolbar select, .field input, .field select{padding:9px 10px;border:1px solid #e7dce3;border-radius:8px;background:white;min-width:0}.toolbar input{flex:1}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;white-space:nowrap}th,td{text-align:left;padding:11px;border-bottom:1px solid #f0eaee;font-size:13px}th{font-size:11px;color:var(--muted);text-transform:uppercase}.danger{color:#b4234c}.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:13px}.field{display:flex;flex-direction:column;gap:5px}.field label{font-size:12px;font-weight:700}.full{grid-column:1/-1}.empty{text-align:center;color:#90858d;padding:26px}.modal{position:fixed;inset:0;background:rgba(33,16,27,.4);display:none;align-items:center;justify-content:center;padding:18px}.modal.show{display:flex}.modal-card{background:#fff;border-radius:14px;padding:22px;width:min(580px,100%);box-shadow:0 20px 70px #21101b44}.dialog-title{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}.dialog-title h3{margin:0}.form-actions{display:flex;justify-content:flex-end;gap:9px;margin-top:16px}
</style>
</head>
<body>
<div class="wrap">
  <div class="topbar">
    <div><h2 style="margin:0">Resident Records</h2><small style="color:var(--muted)">Separate resident management page</small></div>
    <a class="btn primary" href="resident.php?new=1">＋ Add New Resident</a>
  </div>
  <div class="panel">
    <form class="toolbar" method="get">
      <input name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, address, or zone…" oninput="this.form.requestSubmit()">
      <select name="gender" onchange="this.form.requestSubmit()">
        <option value="">All genders</option>
        <?php foreach (['Female', 'Male', 'Other'] as $opt): ?>
        <option <?= $gender === $opt ? 'selected' : '' ?>><?= $opt ?></option>
        <?php endforeach; ?>
      </select>
    </form>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Full name</th><th>Age</th><th>Gender</th><th>Civil status</th><th>Address / Zone</th><th>Contact</th><th>Voter</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if (!$residents): ?>
        <tr><td class="empty" colspan="8">No residents found.</td></tr>
        <?php else: foreach ($residents as $r): ?>
        <tr>
          <td><b><?= htmlspecialchars($r['name']) ?></b></td>
          <td><?= htmlspecialchars($r['age'] ?? '') ?: '&mdash;' ?></td>
          <td><?= htmlspecialchars($r['gender'] ?? '') ?: '&mdash;' ?></td>
          <td><?= htmlspecialchars($r['civil'] ?? '') ?: '&mdash;' ?></td>
          <td><?= htmlspecialchars($r['zone'] ?? '') ?: '&mdash;' ?></td>
          <td><?= htmlspecialchars($r['contact'] ?? '') ?: '&mdash;' ?></td>
          <td><?= htmlspecialchars($r['voter'] ?? '') ?: '&mdash;' ?></td>
          <td>
            <a class="btn" href="resident.php?edit=<?= (int) $r['id'] ?>">Edit</a>
            <form method="post" style="display:inline">
              <input type="hidden" name="form_action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <button class="btn danger" onclick="return confirm('Delete this resident record?')">Delete</button>
            </form>
          </td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<div class="modal<?= $showDialog ? ' show' : '' ?>" id="recordModal">
  <div class="modal-card">
    <form method="post">
      <input type="hidden" name="form_action" value="save">
      <?php if ($editItem): ?><input type="hidden" name="id" value="<?= (int) $editItem['id'] ?>"><?php endif; ?>
      <div class="dialog-title"><h3><?= $editItem ? 'Edit resident' : 'Add resident' ?></h3><a class="btn" href="resident.php">✕</a></div>
      <div class="form-grid">
        <div class="field full"><label>Full name *</label><input name="name" required value="<?= htmlspecialchars($editItem['name'] ?? '') ?>"></div>
        <div class="field"><label>Age</label><input name="age" type="number" min="0" max="125" value="<?= htmlspecialchars($editItem['age'] ?? '') ?>"></div>
        <div class="field"><label>Gender</label><select name="gender">
          <?php foreach (['Female', 'Male', 'Other'] as $opt): ?>
          <option <?= ($editItem['gender'] ?? 'Female') === $opt ? 'selected' : '' ?>><?= $opt ?></option>
          <?php endforeach; ?>
        </select></div>
        <div class="field"><label>Civil status</label><select name="civil">
          <?php foreach (['Single', 'Married', 'Widowed', 'Separated'] as $opt): ?>
          <option <?= ($editItem['civil'] ?? 'Single') === $opt ? 'selected' : '' ?>><?= $opt ?></option>
          <?php endforeach; ?>
        </select></div>
        <div class="field"><label>Voter status</label><select name="voter">
          <?php foreach (['Registered', 'Not registered'] as $opt): ?>
          <option <?= ($editItem['voter'] ?? 'Registered') === $opt ? 'selected' : '' ?>><?= $opt ?></option>
          <?php endforeach; ?>
        </select></div>
        <div class="field"><label>Address / Zone</label><input name="address" value="<?= htmlspecialchars($editItem['zone'] ?? '') ?>"></div>
        <div class="field"><label>Contact number</label><input name="contact" type="tel" value="<?= htmlspecialchars($editItem['contact'] ?? '') ?>"></div>
      </div>
      <div class="form-actions"><a class="btn" href="resident.php">Cancel</a><button type="submit" class="btn primary">Save record</button></div>
    </form>
  </div>
</div>
</body>
</html>
