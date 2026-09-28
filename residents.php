<?php

require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/db/connection.php';
require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/includes/flash.php';

/** Values a dropdown is allowed to submit. Checked server-side, not just rendered. */
const GENDERS = ['Female', 'Male', 'Other'];
const CIVIL_STATUSES = ['Single', 'Married', 'Widowed', 'Separated'];
const VOTER_STATUSES = ['Registered', 'Not registered'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('residents.php');
    $formAction = post_str('form_action');

    if ($formAction === 'save') {
        $id = post_str('id');
        $input = [
            'name'    => post_str('name'),
            'age'     => post_str('age'),
            'gender'  => post_str('gender'),
            'civil'   => post_str('civil'),
            'voter'   => post_str('voter'),
            'zone'    => post_str('zone'),
            'contact' => post_str('contact'),
            'email'   => post_str('email'),
        ];

        $errors = collect_errors([
            'name'    => validate_name($input['name'], 'Full name'),
            'age'     => validate_age($input['age']),
            'gender'  => validate_choice($input['gender'], GENDERS, 'Gender'),
            'civil'   => validate_choice($input['civil'], CIVIL_STATUSES, 'Civil status'),
            'voter'   => validate_choice($input['voter'], VOTER_STATUSES, 'Voter status'),
            'zone'    => validate_text($input['zone'], 'Zone / Sitio', false, 150),
            'contact' => validate_contact($input['contact']),
            'email'   => validate_email($input['email']),
        ]);

        // A duplicate full name in the same zone is almost always the same
        // person entered twice, which is the most common data-quality problem
        // in a barangay directory. Blocked here rather than cleaned up later.
        if (!isset($errors['name'])) {
            $duplicate = $pdo->prepare(
                "SELECT id FROM residents WHERE name = ? AND COALESCE(zone, '') = ? AND id <> ? LIMIT 1"
            );
            $duplicate->execute([$input['name'], $input['zone'], $id === '' ? 0 : (int) $id]);
            if ($duplicate->fetch()) {
                $errors['name'] = 'A resident with this name is already recorded in that zone.';
            }
        }

        if ($errors) {
            flash_form($errors, $input);
            flash_error('That resident record could not be saved. Please correct the highlighted fields.');
            header('Location: residents.php?' . ($id !== '' ? 'edit=' . (int) $id : 'new=1'));
            exit;
        }

        // Store the contact in one consistent shape so searching and exporting
        // don't have to cope with "+63 917 123 4567" and "0917-123-4567" both
        // meaning the same number.
        $values = [
            $input['name'],
            $input['age'] !== '' ? (int) $input['age'] : null,
            $input['gender'],
            $input['civil'],
            $input['voter'],
            $input['zone'] !== '' ? $input['zone'] : null,
            $input['contact'] !== '' ? normalize_contact($input['contact']) : null,
            $input['email'] !== '' ? $input['email'] : null,
        ];

        try {
            $pdo->beginTransaction();

            if ($id !== '') {
                $stmt = $pdo->prepare(
                    'UPDATE residents SET name=?, age=?, gender=?, civil=?, voter=?, zone=?, contact=?, email=? WHERE id=?'
                );
                $stmt->execute([...$values, (int) $id]);
                $message = 'Resident record updated.';
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO residents (name, age, gender, civil, voter, zone, contact, email, recorded_by, created_at)
                     VALUES (?,?,?,?,?,?,?,?,?,NOW())'
                );
                $stmt->execute([...$values, current_user_role()]);
                $message = 'Resident record added.';
            }

            $pdo->commit();
            flash_success($message);
        } catch (PDOException $e) {
            $pdo->rollBack();
            flash_error('The record could not be saved because of a database error. Nothing was changed.');
        }
    } elseif ($formAction === 'delete') {
        $id = (int) post_str('id');

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('DELETE FROM residents WHERE id = ?');
            $stmt->execute([$id]);
            $pdo->commit();

            if ($stmt->rowCount() > 0) {
                flash_success('Resident record deleted.');
            } else {
                flash_error('That resident record no longer exists.');
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            flash_error('The record could not be deleted because of a database error.');
        }
    }

    header('Location: residents.php');
    exit;
}

/* --- Read side ---------------------------------------------------------- */

$search = get_str('q');
$gender = get_str('gender');
$voter = get_str('voter');
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;
$showDialog = isset($_GET['new']) || $editId !== null;

// A filter value that isn't one we offer is ignored rather than passed to SQL.
if (!in_array($gender, GENDERS, true)) {
    $gender = '';
}
if (!in_array($voter, VOTER_STATUSES, true)) {
    $voter = '';
}

$conditions = [];
$params = [];

if ($search !== '') {
    $like = "%$search%";
    $conditions[] = '(name LIKE ? OR zone LIKE ? OR contact LIKE ? OR email LIKE ?)';
    array_push($params, $like, $like, $like, $like);
}
if ($gender !== '') {
    $conditions[] = 'gender = ?';
    $params[] = $gender;
}
if ($voter !== '') {
    $conditions[] = 'voter = ?';
    $params[] = $voter;
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

    if (!$editItem) {
        flash_error('That resident record could not be found.');
        header('Location: residents.php');
        exit;
    }
}

$errors = take_errors();
$input = take_input();
$isFiltered = $search !== '' || $gender !== '' || $voter !== '';

$page = 'residents';
$pageTitle = 'Residents';
require __DIR__ . '/includes/header.php';
?>
<div class="heading">
<div>
<h2>Resident Records</h2>
<p>Manage the barangay resident directory.</p>
</div>
<a class="btn primary" href="residents.php?new=1"><?= icon('plus') ?>Add New Resident</a>
</div>

<section class="panel">
<h2 class="sr-only">Search and filter residents</h2>
<form class="toolbar" method="get" role="search">
<div class="search">
<?= icon('search') ?>
<input id="residentSearch" name="q" value="<?= e($search) ?>" type="search"
       placeholder="Search by name, zone, contact, or email..."
       aria-label="Search residents" data-autosubmit <?= $showDialog ? '' : 'autofocus' ?>>
</div>
<select name="gender" aria-label="Filter by gender" onchange="this.form.requestSubmit()">
<option value="">All genders</option>
<?php foreach (GENDERS as $opt): ?>
<option <?= $gender === $opt ? 'selected' : '' ?>><?= $opt ?></option>
<?php endforeach; ?>
</select>
<select name="voter" aria-label="Filter by voter status" onchange="this.form.requestSubmit()">
<option value="">All voter statuses</option>
<?php foreach (VOTER_STATUSES as $opt): ?>
<option <?= $voter === $opt ? 'selected' : '' ?>><?= $opt ?></option>
<?php endforeach; ?>
</select>
<?php if ($isFiltered): ?>
<a class="btn" href="residents.php">Clear</a>
<?php endif; ?>
<noscript><button class="btn">Search</button></noscript>
</form>

<p class="result-count" role="status">
<?= count($residents) ?> <?= count($residents) === 1 ? 'resident' : 'residents' ?><?= $isFiltered ? ' matched' : ' on file' ?>.
</p>

<div class="table-wrap">
<table>
<caption class="sr-only">Resident records</caption>
<thead>
<tr>
<th scope="col">Full name</th>
<th scope="col">Age</th>
<th scope="col">Gender</th>
<th scope="col">Civil status</th>
<th scope="col">Zone / Sitio</th>
<th scope="col">Contact</th>
<th scope="col">Email</th>
<th scope="col">Voter</th>
<th scope="col">Actions</th>
</tr>
</thead>
<tbody>
<?php if (!$residents): ?>
<tr><td class="empty" colspan="9">
<?= icon('inbox') ?>
<?= $isFiltered ? 'No residents match your search.' : 'No residents recorded yet. Use “Add New Resident” to create the first record.' ?>
</td></tr>
<?php else: foreach ($residents as $item): ?>
<tr>
<td data-label="Full name"><b><?= e($item['name']) ?></b></td>
<td data-label="Age"><?= $item['age'] !== null ? e((string) $item['age']) : '&mdash;' ?></td>
<td data-label="Gender"><?= e($item['gender']) ?: '&mdash;' ?></td>
<td data-label="Civil status"><?= e($item['civil']) ?: '&mdash;' ?></td>
<td data-label="Zone / Sitio"><?= e($item['zone']) ?: '&mdash;' ?></td>
<td data-label="Contact" class="nowrap"><?= e($item['contact']) ?: '&mdash;' ?></td>
<td data-label="Email"><?= e($item['email'] ?? '') ?: '&mdash;' ?></td>
<td data-label="Voter"><?= e($item['voter']) ?: '&mdash;' ?></td>
<td data-label="Actions">
<div class="cell-actions">
<a class="btn small" href="residents.php?edit=<?= (int) $item['id'] ?>" aria-label="Edit <?= e($item['name']) ?>"><?= icon('edit') ?>Edit</a>
<form method="post">
<?= csrf_field() ?>
<input type="hidden" name="form_action" value="delete">
<input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
<button class="btn small danger" aria-label="Delete <?= e($item['name']) ?>"
        data-confirm="Delete the record for <?= e($item['name']) ?>? This cannot be undone."><?= icon('trash') ?>Delete</button>
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
<h3 id="recordDialogTitle"><?= $editItem ? 'Edit resident' : 'Add resident' ?></h3>
<a class="btn ghost" href="residents.php" aria-label="Close dialog">&times;</a>
</div>

<?php if ($errors): ?>
<div class="error-summary" role="alert">
<strong>Please fix <?= count($errors) === 1 ? 'this field' : 'these ' . count($errors) . ' fields' ?>:</strong>
<ul><?php foreach ($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="form-grid">
<div class="field full">
<label for="f-name">Full name <span class="req" aria-hidden="true">*</span></label>
<input id="f-name" name="name" required autofocus maxlength="150" data-validate="name"
       autocomplete="off" value="<?= e(old($input, $editItem, 'name')) ?>"<?= field_attrs($errors, 'name') ?>>
<?= field_error($errors, 'name') ?>
</div>

<div class="field">
<label for="f-age">Age</label>
<input id="f-age" name="age" type="number" inputmode="numeric" min="0" max="125" data-validate="age"
       value="<?= e(old($input, $editItem, 'age')) ?>"<?= field_attrs($errors, 'age') ?>>
<?= field_error($errors, 'age') ?>
</div>

<div class="field">
<label for="f-gender">Gender</label>
<select id="f-gender" name="gender"<?= field_attrs($errors, 'gender') ?>>
<?php $selected = old($input, $editItem, 'gender', 'Female'); ?>
<?php foreach (GENDERS as $opt): ?>
<option <?= $selected === $opt ? 'selected' : '' ?>><?= $opt ?></option>
<?php endforeach; ?>
</select>
<?= field_error($errors, 'gender') ?>
</div>

<div class="field">
<label for="f-civil">Civil status</label>
<select id="f-civil" name="civil"<?= field_attrs($errors, 'civil') ?>>
<?php $selected = old($input, $editItem, 'civil', 'Single'); ?>
<?php foreach (CIVIL_STATUSES as $opt): ?>
<option <?= $selected === $opt ? 'selected' : '' ?>><?= $opt ?></option>
<?php endforeach; ?>
</select>
<?= field_error($errors, 'civil') ?>
</div>

<div class="field">
<label for="f-voter">Voter status</label>
<select id="f-voter" name="voter"<?= field_attrs($errors, 'voter') ?>>
<?php $selected = old($input, $editItem, 'voter', 'Registered'); ?>
<?php foreach (VOTER_STATUSES as $opt): ?>
<option <?= $selected === $opt ? 'selected' : '' ?>><?= $opt ?></option>
<?php endforeach; ?>
</select>
<?= field_error($errors, 'voter') ?>
</div>

<div class="field">
<label for="f-zone">Zone / Sitio</label>
<input id="f-zone" name="zone" maxlength="150" value="<?= e(old($input, $editItem, 'zone')) ?>"<?= field_attrs($errors, 'zone') ?>>
<?= field_error($errors, 'zone') ?>
</div>

<div class="field">
<label for="f-contact">Contact number</label>
<input id="f-contact" name="contact" type="tel" inputmode="tel" maxlength="20" data-validate="contact"
       placeholder="09171234567" value="<?= e(old($input, $editItem, 'contact')) ?>"<?= field_attrs($errors, 'contact') ?>>
<?= field_error($errors, 'contact') ?>
<p class="field-hint">Numbers only. Mobile (11 digits) or landline with area code.</p>
</div>

<div class="field">
<label for="f-email">Email address</label>
<input id="f-email" name="email" type="email" maxlength="150" data-validate="email"
       placeholder="juan.delacruz@gmail.com" value="<?= e(old($input, $editItem, 'email')) ?>"<?= field_attrs($errors, 'email') ?>>
<?= field_error($errors, 'email') ?>
<p class="field-hint">Must include an “@” and a domain.</p>
</div>

<div class="field">
<label for="f-recorded">Recorded by</label>
<input id="f-recorded" value="<?= e($editItem['recorded_by'] ?? current_user_role()) ?>" readonly tabindex="-1">
</div>
</div>

<div class="form-actions">
<a class="btn" href="residents.php">Cancel</a>
<button class="btn primary"><?= $editItem ? 'Save changes' : 'Save resident' ?></button>
</div>
</form>
</dialog>
<?php require __DIR__ . '/includes/footer.php'; ?>
