<?php

require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/db/connection.php';
require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/includes/flash.php';

const CERT_TYPES = ['Barangay Clearance', 'Certificate of Indigency', 'Certificate of Residency'];

$residents = $pdo->query('SELECT id, name, zone FROM residents ORDER BY name')->fetchAll();

$errors = [];
$printNow = false;

$values = [
    'certType'     => CERT_TYPES[0],
    'certResident' => '',
    'certName'     => '',
    'certAddress'  => '',
    'certPurpose'  => '',
    'certDate'     => date('Y-m-d'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('certificates.php');

    $values = [
        'certType'     => post_str('certType'),
        'certResident' => post_str('certResident'),
        'certName'     => post_str('certName'),
        'certAddress'  => post_str('certAddress'),
        'certPurpose'  => post_str('certPurpose'),
        'certDate'     => post_str('certDate'),
    ];

    $errors = collect_errors([
        'certType'    => validate_choice($values['certType'], CERT_TYPES, 'Certificate type'),
        'certName'    => validate_name($values['certName'], 'Full name'),
        'certAddress' => validate_text($values['certAddress'], 'Zone / Sitio', true, 150),
        'certPurpose' => validate_text($values['certPurpose'], 'Purpose', true, 200, 3),
        // A certificate is a dated legal document: it cannot be issued for a
        // day that hasn't happened yet, and back-dating beyond a week is far
        // more likely to be a typo than an intentional correction.
        'certDate'    => validate_date($values['certDate'], 'Date issued', true, '-7 days', 'today'),
    ]);

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                'INSERT INTO certificates (name, type, issue_date, recorded_by, created_at) VALUES (?,?,?,?,NOW())'
            );
            $stmt->execute([
                $values['certName'],
                $values['certType'],
                date('Y-m-d', strtotime($values['certDate'])),
                current_user_role(),
            ]);
            $pdo->commit();

            // Hand the issued details to the next GET and redirect, so that
            // refreshing the printed page cannot record the same certificate a
            // second time. Every other write in this system already follows
            // POST -> redirect -> GET; this one used to be the exception.
            $_SESSION['issued_certificate'] = $values;
            flash_success('“' . $values['certType'] . '” recorded for ' . $values['certName'] . '. Sending it to the printer.');
            header('Location: certificates.php?issued=1');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors['certName'] = 'The certificate could not be recorded because of a database error.';
        }
    }
} elseif (isset($_GET['issued']) && !empty($_SESSION['issued_certificate'])) {
    // Arrived here from the redirect above: restore what was issued so the
    // preview shows the real certificate, then print it. Consuming the session
    // value means a later refresh just shows an empty form.
    $values = $_SESSION['issued_certificate'];
    unset($_SESSION['issued_certificate']);
    $printNow = true;
}

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

<?php if ($errors): ?>
<div class="error-summary" role="alert">
<strong>Please fix <?= count($errors) === 1 ? 'this field' : 'these ' . count($errors) . ' fields' ?>:</strong>
<ul><?php foreach ($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<form method="post" id="certForm" novalidate>
<?= csrf_field() ?>
<div class="form-grid">
<div class="field full">
<label for="certType">Certificate type</label>
<select name="certType" id="certType"<?= field_attrs($errors, 'certType') ?>>
<?php foreach (CERT_TYPES as $opt): ?>
<option <?= $values['certType'] === $opt ? 'selected' : '' ?>><?= $opt ?></option>
<?php endforeach; ?>
</select>
<?= field_error($errors, 'certType') ?>
</div>

<div class="field full">
<label for="certResident">Resident</label>
<select name="certResident" id="certResident" autofocus>
<option value="">Manual entry</option>
<?php foreach ($residents as $r): ?>
<option value="<?= (int) $r['id'] ?>" <?= $values['certResident'] === (string) $r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
<?php endforeach; ?>
</select>
<p class="field-hint">Choosing a resident fills in the name and zone below.</p>
</div>

<div class="field full">
<label for="certName">Full name <span class="req" aria-hidden="true">*</span></label>
<input name="certName" id="certName" required maxlength="150" data-validate="name"
       placeholder="Resident full name" value="<?= e($values['certName']) ?>"<?= field_attrs($errors, 'certName') ?>>
<?= field_error($errors, 'certName') ?>
</div>

<div class="field">
<label for="certAddress">Zone / Sitio <span class="req" aria-hidden="true">*</span></label>
<input name="certAddress" id="certAddress" required maxlength="150"
       placeholder="Zone or sitio" value="<?= e($values['certAddress']) ?>"<?= field_attrs($errors, 'certAddress') ?>>
<?= field_error($errors, 'certAddress') ?>
</div>

<div class="field">
<label for="certPurpose">Purpose <span class="req" aria-hidden="true">*</span></label>
<input name="certPurpose" id="certPurpose" required maxlength="200"
       placeholder="e.g. Employment requirement" value="<?= e($values['certPurpose']) ?>"<?= field_attrs($errors, 'certPurpose') ?>>
<?= field_error($errors, 'certPurpose') ?>
</div>

<div class="field full">
<label for="certDate">Date issued <span class="req" aria-hidden="true">*</span></label>
<input name="certDate" id="certDate" type="date" required
       max="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d', strtotime('-7 days')) ?>"
       value="<?= e($values['certDate']) ?>"<?= field_attrs($errors, 'certDate') ?>>
<?= field_error($errors, 'certDate') ?>
</div>
</div>

<div class="form-actions">
<button class="btn primary" id="printCert"><?= icon('printer') ?>Record &amp; Print Certificate</button>
</div>
</form>
</section>

<article class="certificate">
<div class="seal" aria-hidden="true">CN</div>
<div class="official">REPUBLIC OF THE PHILIPPINES<br>PROVINCE OF CAGAYAN<br>CITY OF TUGUEGARAO<br>
<b>BARANGAY CATAGGAMAN NUEVO</b>
</div>
<h2 id="previewType"><?= e(strtoupper($values['certType'])) ?></h2>
<p class="cert-body">TO WHOM IT MAY CONCERN:<br>
<br>This is to certify that <span class="cert-name" id="previewName"><?= $values['certName'] !== '' ? e($values['certName']) : '[RESIDENT NAME]' ?></span>, of legal age, is a bona fide resident of <span id="previewAddress"><?= $values['certAddress'] !== '' ? e($values['certAddress']) : '[ZONE / SITIO]' ?></span>, Barangay Cataggaman Nuevo, Tuguegarao City, Cagayan.<br>
<br>This certificate is issued upon request for <span id="previewPurpose"><?= $values['certPurpose'] !== '' ? e($values['certPurpose']) : '[PURPOSE]' ?></span> and for whatever lawful purpose it may serve.<br>
<br>Issued this <span id="previewDate"><?= $values['certDate'] ? date('F j, Y', strtotime($values['certDate'])) : '' ?></span> at Barangay Cataggaman Nuevo, Tuguegarao City, Cagayan.</p>
<div class="signature">
<b>BARANGAY CAPTAIN</b>
<br>Barangay Captain</div>
</article>
</div>

<script>
const residentsData = <?= json_encode(array_column($residents, null, 'id'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

const $ = id => document.querySelector('#' + id);

$('certResident').addEventListener('change', event => {
  const resident = residentsData[event.target.value];
  if (resident) {
    $('certName').value = resident.name;
    $('certAddress').value = resident.zone || '';
  }
  updatePreview();
});

function updatePreview() {
  $('previewType').textContent = $('certType').value.toUpperCase();
  $('previewName').textContent = $('certName').value.trim() || '[RESIDENT NAME]';
  $('previewAddress').textContent = $('certAddress').value.trim() || '[ZONE / SITIO]';
  $('previewPurpose').textContent = $('certPurpose').value.trim() || '[PURPOSE]';

  const date = $('certDate').value;
  $('previewDate').textContent = date
    ? new Date(`${date}T12:00:00`).toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' })
    : '';
}

['certType', 'certName', 'certAddress', 'certPurpose', 'certDate']
  .forEach(id => $(id).addEventListener('input', updatePreview));

<?php if ($printNow): ?>
// The certificate was recorded; hand it straight to the printer.
window.addEventListener('load', () => window.print());
<?php endif; ?>
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
