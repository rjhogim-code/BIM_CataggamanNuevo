<?php

require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/db/connection.php';
require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/includes/flash.php';

const STATUSES = ['Pending', 'Under Investigation', 'Resolved'];

/** Common barangay case types, offered as suggestions but not enforced. */
const INCIDENT_TYPES = [
    'Noise Complaint', 'Boundary Dispute', 'Theft', 'Physical Injury',
    'Verbal Altercation', 'Property Damage', 'Domestic Dispute', 'Other',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('incidents.php');
    $formAction = post_str('form_action');

    if ($formAction === 'save') {
        $id = post_str('id');
        $input = [
            'caseNo'      => post_str('caseNo'),
            'complainant' => post_str('complainant'),
            'respondent'  => post_str('respondent'),
            'type'        => post_str('type'),
            'date'        => post_str('date'),
            'status'      => post_str('status'),
            'description' => post_str('description'),
        ];

        $errors = collect_errors([
            'caseNo'      => validate_text($input['caseNo'], 'Case number', true, 50, 3),
            'complainant' => validate_name($input['complainant'], 'Complainant'),
            'respondent'  => validate_name($input['respondent'], 'Respondent', false),
            'type'        => validate_text($input['type'], 'Incident type', true, 100, 3),
            // An incident cannot be reported before it happens, and a barangay
            // record predating the system is almost always a typo in the year.
            'date'        => validate_date($input['date'], 'Incident date', false, '2000-01-01', 'now'),
            'status'      => validate_choice($input['status'], STATUSES, 'Status'),
            'description' => validate_text($input['description'], 'Description', false, 5000),
        ]);

        // Case numbers are the reference staff quote on paper, so they have to
        // be unique. The database enforces this too; checking here means the
        // person gets a readable message instead of a constraint error.
        if (!isset($errors['caseNo'])) {
            $duplicate = $pdo->prepare('SELECT id FROM incidents WHERE case_no = ? AND id <> ? LIMIT 1');
            $duplicate->execute([$input['caseNo'], $id === '' ? 0 : (int) $id]);
            if ($duplicate->fetch()) {
                $errors['caseNo'] = 'Case number ' . $input['caseNo'] . ' is already used by another report.';
            }
        }

        if ($errors) {
            flash_form($errors, $input);
            flash_error('That incident report could not be saved. Please correct the highlighted fields.');
            header('Location: incidents.php?' . ($id !== '' ? 'edit=' . (int) $id : 'new=1'));
            exit;
        }

        $date = $input['date'] !== ''
            ? date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $input['date'])))
            : null;

        $values = [
            $input['caseNo'],
            $input['complainant'],
            $input['respondent'] !== '' ? $input['respondent'] : null,
            $input['type'],
            $date,
            $input['status'],
            $input['description'] !== '' ? $input['description'] : null,
        ];

        try {
            $pdo->beginTransaction();

            if ($id !== '') {
                $stmt = $pdo->prepare(
                    'UPDATE incidents SET case_no=?, complainant=?, respondent=?, type=?, incident_date=?, status=?, description=? WHERE id=?'
                );
                $stmt->execute([...$values, (int) $id]);
                $message = 'Incident report ' . $input['caseNo'] . ' updated.';
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO incidents (case_no, complainant, respondent, type, incident_date, status, description, recorded_by, created_at)
                     VALUES (?,?,?,?,?,?,?,?,NOW())'
                );
                $stmt->execute([...$values, current_user_role()]);
                $message = 'Incident report ' . $input['caseNo'] . ' filed.';
            }

            $pdo->commit();
            flash_success($message);
        } catch (PDOException $e) {
            $pdo->rollBack();
            // 23000 is the integrity-constraint class — here, the unique index
            // on case_no losing a race with another workstation.
            if ($e->getCode() === '23000') {
                flash_form(['caseNo' => 'Case number ' . $input['caseNo'] . ' was just used by another report.'], $input);
                flash_error('That case number was taken while you were filling in the form. Please use a different one.');
                header('Location: incidents.php?' . ($id !== '' ? 'edit=' . (int) $id : 'new=1'));
                exit;
            }
            flash_error('The report could not be saved because of a database error. Nothing was changed.');
        }
    } elseif ($formAction === 'delete') {
        $id = (int) post_str('id');

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('DELETE FROM incidents WHERE id = ?');
            $stmt->execute([$id]);
            $pdo->commit();

            if ($stmt->rowCount() > 0) {
                flash_success('Incident report deleted.');
            } else {
                flash_error('That incident report no longer exists.');
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            flash_error('The report could not be deleted because of a database error.');
        }
    } elseif ($formAction === 'cycle') {
        $id = (int) post_str('id');
        $next = ['Pending' => 'Under Investigation', 'Under Investigation' => 'Resolved', 'Resolved' => 'Pending'];

        try {
            // Read and write inside one transaction so two workstations
            // advancing the same case can't both read "Pending" and skip a step.
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('SELECT case_no, status FROM incidents WHERE id = ? FOR UPDATE');
            $stmt->execute([$id]);
            $current = $stmt->fetch();

            if (!$current) {
                $pdo->rollBack();
                flash_error('That incident report no longer exists.');
            } else {
                $newStatus = $next[$current['status']] ?? 'Pending';
                $stmt = $pdo->prepare('UPDATE incidents SET status = ? WHERE id = ?');
                $stmt->execute([$newStatus, $id]);
                $pdo->commit();
                flash_success($current['case_no'] . ' is now “' . $newStatus . '”.');
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            flash_error('The status could not be updated because of a database error.');
        }
    }

    header('Location: incidents.php');
    exit;
}

/* --- Read side ---------------------------------------------------------- */

$search = get_str('q');
$status = get_str('status');
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;
$showDialog = isset($_GET['new']) || $editId !== null;

if (!in_array($status, STATUSES, true)) {
    $status = '';
}

$conditions = [];
$params = [];

if ($search !== '') {
    $like = "%$search%";
    $conditions[] = '(case_no LIKE ? OR complainant LIKE ? OR respondent LIKE ? OR type LIKE ? OR description LIKE ?)';
    array_push($params, $like, $like, $like, $like, $like);
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

    if (!$editItem) {
        flash_error('That incident report could not be found.');
        header('Location: incidents.php');
        exit;
    }
}

$errors = take_errors();
$input = take_input();
$isFiltered = $search !== '' || $status !== '';

/**
 * Next case number for a new report: CN-{year}-{sequence}, where the sequence
 * continues from the highest number already issued this year rather than from
 * the total row count — deleting a report must not hand its number to the next
 * one filed.
 */
$defaultCaseNo = '';
$defaultDate = '';
if ($showDialog && !$editItem) {
    $year = date('Y');
    $stmt = $pdo->prepare(
        "SELECT MAX(CAST(SUBSTRING_INDEX(case_no, '-', -1) AS UNSIGNED))
         FROM incidents WHERE case_no LIKE ?"
    );
    $stmt->execute(["CN-$year-%"]);
    $highest = (int) $stmt->fetchColumn();
    $defaultCaseNo = "CN-$year-" . str_pad((string) ($highest + 1), 3, '0', STR_PAD_LEFT);
    $defaultDate = date('Y-m-d\TH:i');
}

$formDate = old($input, null, 'date');
if ($formDate === '') {
    if ($editItem && $editItem['incident_date']) {
        $formDate = date('Y-m-d\TH:i', strtotime($editItem['incident_date']));
    } else {
        $formDate = $defaultDate;
    }
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
<a class="btn primary" href="incidents.php?new=1"><?= icon('plus') ?>File Incident Report</a>
</div>

<section class="panel">
<h2 class="sr-only">Search and filter incident reports</h2>
<form class="toolbar" method="get" role="search">
<div class="search">
<?= icon('search') ?>
<input id="incidentSearch" name="q" value="<?= e($search) ?>" type="search"
       placeholder="Search cases, people, or incident type..."
       aria-label="Search incidents" data-autosubmit <?= $showDialog ? '' : 'autofocus' ?>>
</div>
<select id="incidentFilter" name="status" aria-label="Filter by status" onchange="this.form.requestSubmit()">
<option value="">All statuses</option>
<?php foreach (STATUSES as $opt): ?>
<option <?= $status === $opt ? 'selected' : '' ?>><?= $opt ?></option>
<?php endforeach; ?>
</select>
<?php if ($isFiltered): ?>
<a class="btn" href="incidents.php">Clear</a>
<?php endif; ?>
<noscript><button class="btn">Search</button></noscript>
</form>

<p class="result-count" role="status">
<?= count($incidents) ?> <?= count($incidents) === 1 ? 'report' : 'reports' ?><?= $isFiltered ? ' matched' : ' on file' ?>.
</p>

<div class="table-wrap">
<table>
<caption class="sr-only">Incident reports</caption>
<thead>
<tr>
<th scope="col">Case no.</th>
<th scope="col">Complainant</th>
<th scope="col">Respondent</th>
<th scope="col">Type</th>
<th scope="col">Date</th>
<th scope="col">Status</th>
<th scope="col">Actions</th>
</tr>
</thead>
<tbody>
<?php if (!$incidents): ?>
<tr><td class="empty" colspan="7">
<?= icon('inbox') ?>
<?= $isFiltered ? 'No reports match your search.' : 'No incident reports filed yet.' ?>
</td></tr>
<?php else: foreach ($incidents as $item):
    $badgeClass = $item['status'] === 'Resolved'
        ? 'resolved'
        : ($item['status'] === 'Under Investigation' ? 'investigation' : '');
?>
<tr>
<td data-label="Case no." class="nowrap"><b><?= e($item['case_no']) ?></b></td>
<td data-label="Complainant"><?= e($item['complainant']) ?></td>
<td data-label="Respondent"><?= e($item['respondent'] ?? '') ?: '&mdash;' ?></td>
<td data-label="Type"><?= e($item['type']) ?></td>
<td data-label="Date" class="nowrap"><?= $item['incident_date'] ? date('n/j/Y, g:i A', strtotime($item['incident_date'])) : '&mdash;' ?></td>
<td data-label="Status"><span class="badge <?= $badgeClass ?>"><?= e($item['status']) ?></span></td>
<td data-label="Actions">
<div class="cell-actions">
<form method="post">
<?= csrf_field() ?>
<input type="hidden" name="form_action" value="cycle">
<input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
<button class="btn small" aria-label="Update status of <?= e($item['case_no']) ?>"><?= icon('refresh') ?>Update status</button>
</form>
<a class="btn small" href="incidents.php?edit=<?= (int) $item['id'] ?>" aria-label="Edit <?= e($item['case_no']) ?>"><?= icon('edit') ?>Edit</a>
<form method="post">
<?= csrf_field() ?>
<input type="hidden" name="form_action" value="delete">
<input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
<button class="btn small danger" aria-label="Delete <?= e($item['case_no']) ?>"
        data-confirm="Delete incident report <?= e($item['case_no']) ?>? This cannot be undone."><?= icon('trash') ?>Delete</button>
</form>
</div>
</td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>
</section>

<dialog class="modal" id="recordDialog" aria-labelledby="recordDialogTitle" <?= $showDialog ? 'data-autoopen="1"' : '' ?>>
<form method="post" novalidate>
<?= csrf_field() ?>
<input type="hidden" name="form_action" value="save">
<?php if ($editItem): ?><input type="hidden" name="id" value="<?= (int) $editItem['id'] ?>"><?php endif; ?>

<div class="dialog-title">
<h3 id="recordDialogTitle"><?= $editItem ? 'Edit incident report' : 'File incident report' ?></h3>
<a class="btn ghost" href="incidents.php" aria-label="Close dialog">&times;</a>
</div>

<?php if ($errors): ?>
<div class="error-summary" role="alert">
<strong>Please fix <?= count($errors) === 1 ? 'this field' : 'these ' . count($errors) . ' fields' ?>:</strong>
<ul><?php foreach ($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="form-grid">
<div class="field">
<label for="f-caseno">Case no. <span class="req" aria-hidden="true">*</span></label>
<input id="f-caseno" name="caseNo" required maxlength="50"
       value="<?= e(old($input, $editItem, 'caseNo', $editItem['case_no'] ?? $defaultCaseNo)) ?>"<?= field_attrs($errors, 'caseNo') ?>>
<?= field_error($errors, 'caseNo') ?>
</div>

<div class="field">
<label for="f-type">Incident type <span class="req" aria-hidden="true">*</span></label>
<input id="f-type" name="type" required maxlength="100" list="incidentTypes"
       value="<?= e(old($input, $editItem, 'type')) ?>"<?= field_attrs($errors, 'type') ?>>
<datalist id="incidentTypes">
<?php foreach (INCIDENT_TYPES as $opt): ?><option value="<?= $opt ?>"><?php endforeach; ?>
</datalist>
<?= field_error($errors, 'type') ?>
</div>

<div class="field">
<label for="f-complainant">Complainant <span class="req" aria-hidden="true">*</span></label>
<input id="f-complainant" name="complainant" required autofocus maxlength="150" data-validate="name"
       autocomplete="off" value="<?= e(old($input, $editItem, 'complainant')) ?>"<?= field_attrs($errors, 'complainant') ?>>
<?= field_error($errors, 'complainant') ?>
</div>

<div class="field">
<label for="f-respondent">Respondent</label>
<input id="f-respondent" name="respondent" maxlength="150" data-validate="name"
       autocomplete="off" value="<?= e(old($input, $editItem, 'respondent')) ?>"<?= field_attrs($errors, 'respondent') ?>>
<?= field_error($errors, 'respondent') ?>
</div>

<div class="field">
<label for="f-date">Date and time of incident</label>
<input id="f-date" name="date" type="datetime-local" max="<?= date('Y-m-d\TH:i') ?>"
       value="<?= e($formDate) ?>"<?= field_attrs($errors, 'date') ?>>
<?= field_error($errors, 'date') ?>
<p class="field-hint">Cannot be a future date.</p>
</div>

<div class="field">
<label for="f-status">Status</label>
<select id="f-status" name="status"<?= field_attrs($errors, 'status') ?>>
<?php $selected = old($input, $editItem, 'status', 'Pending'); ?>
<?php foreach (STATUSES as $opt): ?>
<option <?= $selected === $opt ? 'selected' : '' ?>><?= $opt ?></option>
<?php endforeach; ?>
</select>
<?= field_error($errors, 'status') ?>
</div>

<div class="field">
<label for="f-recorded">Recorded by</label>
<input id="f-recorded" value="<?= e($editItem['recorded_by'] ?? current_user_role()) ?>" readonly tabindex="-1">
</div>

<div class="field full">
<label for="f-description">Description</label>
<textarea id="f-description" name="description" maxlength="5000"
          placeholder="What happened, where, and who was involved."<?= field_attrs($errors, 'description') ?>><?= e(old($input, $editItem, 'description')) ?></textarea>
<?= field_error($errors, 'description') ?>
</div>
</div>

<div class="form-actions">
<a class="btn" href="incidents.php">Cancel</a>
<button class="btn primary"><?= $editItem ? 'Save changes' : 'Save report' ?></button>
</div>
</form>
</dialog>
<?php require __DIR__ . '/includes/footer.php'; ?>
