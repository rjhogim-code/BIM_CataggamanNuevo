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
$certFurther = '';
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
    $certFurther = trim($_POST['certFurther'] ?? '');
    $certDate = $_POST['certDate'] ?? $certDate;

    if ($certLastName === '' || $certFirstName === '') {
      $error = 'Enter the resident last name and first name.';
    } else {
        $stmt = $pdo->prepare('INSERT INTO certificates (name, type, issue_date, recorded_by, created_at) VALUES (?,?,?,?,NOW())');
        $stmt->execute([$certName, $certType, $certDate, current_user_role()]);
        $printNow = true;
    }
}

$certTypes = ['Barangay Certification', 'Barangay Clearance', 'Certificate of Indigency', 'Certificate of Residency'];

// Signatory printed on every certificate (see the official letterhead template).
$signatory = 'WALDO L. ZINGAPAN';
$signatoryTitle = 'Punong Barangay';

// Printed names follow the template: FIRST MIDDLE LAST (the stored name stays "Last, First Middle").
$certDisplayName = trim(implode(' ', array_filter([$certFirstName, $certMiddleName, $certLastName], 'strlen')));
$certTimestamp = $certDate !== '' ? strtotime($certDate) : false;

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
<label>Certifies further <small>(optional)</small></label>
<textarea name="certFurther" id="certFurther" rows="3" placeholder="e.g. the above-named person is going to apply for electrical and water connection"><?= htmlspecialchars($certFurther) ?></textarea>
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
<article class="certificate" id="certificate">
<img class="cert-letterhead" src="certificate-letterhead.jpg" alt="">
<div class="cert-content">
<h2 class="cert-title" id="previewType"><?= htmlspecialchars(strtoupper($certType)) ?></h2>
<p class="cert-to">TO WHOM IT MAY CONCERN:</p>
<div class="cert-body">
<p><b><i>THIS IS TO CERTIFY</i></b> that <b class="cert-name" id="previewName"><?= $certDisplayName !== '' ? htmlspecialchars($certDisplayName) : '[RESIDENT NAME]' ?></b>, of legal age, is a bona fide resident of <span id="previewAddress"><?= $certAddress !== '' ? htmlspecialchars($certAddress) . ', ' : '' ?></span>Barangay Cataggaman Nuevo, Tuguegarao City, Cagayan.</p>
<p id="previewFurtherRow"<?= $certFurther === '' ? ' hidden' : '' ?>><b><i>CERTIFIES FURTHER</i></b> that <span id="previewFurther"><?= htmlspecialchars($certFurther) ?></span></p>
<p>This certification is issued upon the request of the above-named person for <i id="previewPurpose"><?= $certPurpose !== '' ? htmlspecialchars($certPurpose) : 'whatever legal purpose it may serve' ?></i>.</p>
<p>Issued this <?php if ($certTimestamp): ?><b><span id="previewDay"><?= date('j', $certTimestamp) ?></span><sup id="previewSuffix"><?= date('S', $certTimestamp) ?></sup></b> day of <b id="previewMonthYear"><?= strtoupper(date('F Y', $certTimestamp)) ?></b><?php else: ?><b><span id="previewDay"></span><sup id="previewSuffix"></sup></b> day of <b id="previewMonthYear"></b><?php endif; ?> at Barangay Cataggaman Nuevo, Tuguegarao City, Cagayan.</p>
</div>
<div class="cert-sign">
<b><?= htmlspecialchars($signatory) ?></b>
<i><?= htmlspecialchars($signatoryTitle) ?></i>
</div>
</div>
<div class="cert-foot" id="previewApplicant"<?= $certType === 'Barangay Clearance' ? '' : ' hidden' ?>>
<div class="cert-applicant"><i>Signature of Applicant</i></div>
<dl>
<dt>Res. Cert. No.</dt><dd></dd>
<dt>Date</dt><dd></dd>
<dt>Issued on</dt><dd></dd>
<dt>Issued at</dt><dd>Tuguegarao City, Cagayan</dd>
</dl>
</div>
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

function ordinalSuffix(day) {
  if (day % 100 >= 11 && day % 100 <= 13) return 'th';
  return { 1: 'st', 2: 'nd', 3: 'rd' }[day % 10] || 'th';
}

function updatePreview() {
  const value = id => document.querySelector('#' + id).value.trim();
  const type = document.querySelector('#certType').value;
  document.querySelector('#previewType').textContent = type.toUpperCase();
  document.querySelector('#previewApplicant').hidden = type !== 'Barangay Clearance';
  const fullName = [value('certFirstName'), value('certMiddleName'), value('certLastName')].filter(Boolean).join(' ');
  document.querySelector('#previewName').textContent = fullName || '[RESIDENT NAME]';
  document.querySelector('#previewAddress').textContent = value('certAddress') ? `${value('certAddress')}, ` : '';
  document.querySelector('#previewPurpose').textContent = value('certPurpose') || 'whatever legal purpose it may serve';
  document.querySelector('#previewFurther').textContent = value('certFurther');
  document.querySelector('#previewFurtherRow').hidden = !value('certFurther');
  const d = value('certDate');
  const date = d ? new Date(`${d}T12:00:00`) : null;
  document.querySelector('#previewDay').textContent = date ? date.getDate() : '';
  document.querySelector('#previewSuffix').textContent = date ? ordinalSuffix(date.getDate()) : '';
  document.querySelector('#previewMonthYear').textContent = date ? date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' }).toUpperCase() : '';
}

['certType', 'certLastName', 'certFirstName', 'certMiddleName', 'certAddress', 'certPurpose', 'certFurther', 'certDate'].forEach(id => document.querySelector('#' + id).addEventListener('input', updatePreview));

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
