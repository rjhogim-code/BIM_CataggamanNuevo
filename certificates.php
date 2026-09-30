<?php

require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/db/connection.php';

$residents = $pdo->query('SELECT id, name, last_name, first_name, middle_name, zone FROM residents ORDER BY last_name, first_name')->fetchAll();

$certType = 'Barangay Clearance';
$certResident = '';
$certLastName = '';
$certFirstName = '';
$certMiddleName = '';
$certName = '';
$certAddress = '';
$certPurpose = '';
$certDate = date('Y-m-d');
$printNow = false;
$error = '';
$puroks = ['Purok 1', 'Purok 2', 'Purok 3', 'Purok 4', 'Purok 5', 'Purok 6', 'Purok 7', 'Purok 8'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $certType = $_POST['certType'] ?? $certType;
    $certResident = $_POST['certResident'] ?? '';
    $certLastName = trim($_POST['certLastName'] ?? '');
    $certFirstName = trim($_POST['certFirstName'] ?? '');
    $certMiddleName = trim($_POST['certMiddleName'] ?? '');
    $certName = trim($certLastName . ', ' . $certFirstName . ($certMiddleName !== '' ? ' ' . $certMiddleName : ''));
    $certAddress = trim($_POST['certAddress'] ?? '');
    $certPurpose = trim($_POST['certPurpose'] ?? '');
    $certDate = $_POST['certDate'] ?? $certDate;

    if ($certLastName === '' || $certFirstName === '') {
      $error = 'Enter the resident last name and first name.';
    } else {
        $stmt = $pdo->prepare('INSERT INTO certificates (name, type, issue_date, recorded_by, created_at) VALUES (?,?,?,?,NOW())');
        $stmt->execute([$certName, $certType, $certDate, current_user_role()]);
        $printNow = true;
    }
}

$certTypes = ['Barangay Clearance', 'Certificate of Indigency', 'Certificate of Residency'];

$page = 'certificates';
$pageTitle = 'Certificates';
require __DIR__ . '/includes/header.php';
?>
<div class="heading">
<div>
<h2>Generate Certificates</h2>
<p>Prepare, preview, and print an official certificate.</p>
</div>
</div>
<div class="cert-layout">
<section class="panel no-print">
<h3>Certificate details</h3>
<?php if ($error): ?><p class="login-message" style="margin:0 0 16px"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post" id="certForm">
<div class="form-grid">
<div class="field full">
<label>Certificate type</label>
<select name="certType" id="certType">
<?php foreach ($certTypes as $opt): ?>
<option <?= $certType === $opt ? 'selected' : '' ?>><?= $opt ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="field full">
<label>Resident</label>
<select name="certResident" id="certResident" autofocus>
<option value="">Manual entry</option>
<?php foreach ($residents as $r): ?>
<option value="<?= (int) $r['id'] ?>" <?= (string) $certResident === (string) $r['id'] ? 'selected' : '' ?>><?= htmlspecialchars($r['name']) ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="field">
<label>Last name *</label>
<input name="certLastName" id="certLastName" dir="ltr" required value="<?= htmlspecialchars($certLastName) ?>">
</div>
<div class="field">
<label>First name *</label>
<input name="certFirstName" id="certFirstName" dir="ltr" required value="<?= htmlspecialchars($certFirstName) ?>">
</div>
<div class="field">
<label>Middle name</label>
<input name="certMiddleName" id="certMiddleName" dir="ltr" value="<?= htmlspecialchars($certMiddleName) ?>">
</div>
<div class="field">
<label>Zone / Sitio</label>
<select name="certAddress" id="certAddress">
<option value="">Select Purok</option>
<?php foreach ($puroks as $purok): ?>
<option value="<?= htmlspecialchars($purok) ?>" <?= $certAddress === $purok ? 'selected' : '' ?>><?= htmlspecialchars($purok) ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="field">
<label>Purpose</label>
<input name="certPurpose" id="certPurpose" placeholder="Purpose of request" value="<?= htmlspecialchars($certPurpose) ?>">
</div>
<div class="field full">
<label>Date issued</label>
<input name="certDate" id="certDate" type="date" value="<?= htmlspecialchars($certDate) ?>">
</div>
</div>
<div class="form-actions">
<button class="btn primary" id="printCert">Print Certificate</button>
</div>
</form>
</section>
<article class="certificate">
<div class="seal"><img src="Cat.jpg" alt="Barangay Cataggaman Nuevo logo"></div>
<div class="official">REPUBLIC OF THE PHILIPPINES<br>PROVINCE OF CAGAYAN<br>CITY OF TUGUEGARAO<br>
<b>BARANGAY CATAGGAMAN NUEVO</b>
</div>
<h2>BARANGAY</h2>
<h3 id="previewType"><?= htmlspecialchars(strtoupper($certType)) ?></h3>
<p class="cert-body">TO WHOM IT MAY CONCERN:<br>
<br>This is to certify that <span class="cert-name" id="previewName"><?= $certName !== '' ? htmlspecialchars($certName) : '[RESIDENT NAME]' ?></span>, of legal age, is a bona fide resident of <span id="previewAddress"><?= $certAddress !== '' ? htmlspecialchars($certAddress) : '[ZONE / SITIO]' ?></span>, Barangay Cataggaman Nuevo, Tuguegarao City, Cagayan.<br>
<br>This certificate is issued upon request for <span id="previewPurpose"><?= $certPurpose !== '' ? htmlspecialchars($certPurpose) : '[PURPOSE]' ?></span> and for whatever lawful purpose it may serve.<br>
<br>Issued this <span id="previewDate"><?= $certDate ? date('F j, Y', strtotime($certDate)) : '' ?></span> at Barangay Cataggaman Nuevo, Tuguegarao City, Cagayan.</p>
<div class="signature">
<b>BARANGAY CAPTAIN</b>
<br>Barangay Captain</div>
</article>
</div>
<script>
const residentsData = <?= json_encode(array_column($residents, null, 'id')) ?>;

document.querySelector('#certResident').addEventListener('change', event => {
  const r = residentsData[event.target.value];
  if (r) {
    document.querySelector('#certLastName').value = r.last_name || '';
    document.querySelector('#certFirstName').value = r.first_name || '';
    document.querySelector('#certMiddleName').value = r.middle_name || '';
    document.querySelector('#certAddress').value = r.zone || '';
  }
  updatePreview();
});

function updatePreview() {
  document.querySelector('#previewType').textContent = document.querySelector('#certType').value.toUpperCase();
  const lastName = document.querySelector('#certLastName').value.trim();
  const firstName = document.querySelector('#certFirstName').value.trim();
  const middleName = document.querySelector('#certMiddleName').value.trim();
  const fullName = [lastName ? `${lastName},` : '', firstName, middleName].filter(Boolean).join(' ');
  document.querySelector('#previewName').textContent = fullName || '[RESIDENT NAME]';
  document.querySelector('#previewAddress').textContent = document.querySelector('#certAddress').value || '[ZONE / SITIO]';
  document.querySelector('#previewPurpose').textContent = document.querySelector('#certPurpose').value || '[PURPOSE]';
  const d = document.querySelector('#certDate').value;
  document.querySelector('#previewDate').textContent = d ? new Date(`${d}T12:00:00`).toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' }) : '';
}

['certType', 'certLastName', 'certFirstName', 'certMiddleName', 'certAddress', 'certPurpose', 'certDate'].forEach(id => document.querySelector('#' + id).addEventListener('input', updatePreview));

document.querySelector('#printCert').addEventListener('click', event => {
  if (!document.querySelector('#certLastName').value.trim() || !document.querySelector('#certFirstName').value.trim()) {
    event.preventDefault();
    alert('Select or enter a resident first.');
  }
});
<?php if ($printNow): ?>
window.addEventListener('load', () => window.print());
<?php endif; ?>
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
