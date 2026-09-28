<?php

/**
 * Shared input/data validation for the Barangay Information Management System.
 *
 * Every validator returns an error message string when the value is rejected,
 * or null when it is acceptable. Pages collect those into a $errors array keyed
 * by field name, so the form can re-render with the message next to the field
 * that caused it.
 *
 * Rule of thumb used throughout: the browser attributes (required, type,
 * pattern, maxlength) are a convenience for the person typing; these functions
 * are the real check, because anything client-side can be bypassed.
 */

/** Read a POST field as a trimmed string without tripping undefined-key warnings. */
function post_str(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;

    return is_string($value) ? trim($value) : $default;
}

/** Read a GET field as a trimmed string. */
function get_str(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;

    return is_string($value) ? trim($value) : $default;
}

/* ---------------------------------------------------------------------------
 * Field validators
 * ------------------------------------------------------------------------ */

/**
 * A person's name: letters (including Ñ and accented characters), spaces, and
 * the punctuation that legitimately shows up in Filipino names — hyphen for
 * compound surnames, apostrophe for names like D'Souza, period for "Jr.".
 * Digits and symbols are rejected so a phone number can't be typed into a name
 * field by accident.
 */
function validate_name(string $value, string $label = 'Name', bool $required = true, int $max = 150): ?string
{
    if ($value === '') {
        return $required ? "$label is required." : null;
    }

    if (mb_strlen($value) < 2) {
        return "$label must be at least 2 characters.";
    }

    if (mb_strlen($value) > $max) {
        return "$label must be $max characters or fewer.";
    }

    if (!preg_match("/^[\p{L}][\p{L}\p{M}\s.'\-]*$/u", $value)) {
        return "$label may only contain letters, spaces, hyphens, apostrophes, and periods.";
    }

    return null;
}

/**
 * Email address. The "@" check the form promises is really
 * FILTER_VALIDATE_EMAIL, which additionally requires a local part, a domain,
 * and a dot in the domain — so "nino@localhost" and "nino@@x.com" are both
 * rejected, not just strings missing the "@".
 */
function validate_email(string $value, string $label = 'Email address', bool $required = false): ?string
{
    if ($value === '') {
        return $required ? "$label is required." : null;
    }

    if (mb_strlen($value) > 150) {
        return "$label must be 150 characters or fewer.";
    }

    if (!str_contains($value, '@')) {
        return "$label must contain an \"@\" sign — for example juan.delacruz@gmail.com.";
    }

    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
        return "Enter a complete $label, for example juan.delacruz@gmail.com.";
    }

    return null;
}

/**
 * Strip the punctuation people habitually type into a phone number so the
 * digits underneath can be checked and stored consistently.
 * "+63 917 123 4567" and "(078) 844-1234" both reduce to digits here.
 */
function normalize_contact(string $value): string
{
    $digits = preg_replace('/[\s\-().]/', '', $value) ?? '';

    // +63 / 63 country code becomes the local 0 prefix, so the stored format is
    // the one barangay staff actually read off a form: 09171234567.
    if (str_starts_with($digits, '+63')) {
        $digits = '0' . substr($digits, 3);
    } elseif (str_starts_with($digits, '63') && strlen($digits) === 12) {
        $digits = '0' . substr($digits, 2);
    }

    return $digits;
}

/**
 * Contact number: digits only once the usual separators are removed.
 * Accepts a PH mobile number (11 digits starting 09) or a landline (7-10
 * digits, e.g. the 078 Tuguegarao area code plus a 7-digit subscriber number).
 */
function validate_contact(string $value, string $label = 'Contact number', bool $required = false): ?string
{
    if ($value === '') {
        return $required ? "$label is required." : null;
    }

    $digits = normalize_contact($value);

    if ($digits === '' || !ctype_digit($digits)) {
        return "$label may only contain numbers — for example 09171234567.";
    }

    if (str_starts_with($digits, '09')) {
        if (strlen($digits) !== 11) {
            return "A mobile number must be 11 digits — for example 09171234567.";
        }

        return null;
    }

    if (strlen($digits) < 7 || strlen($digits) > 10) {
        return "Enter an 11-digit mobile number (09171234567) or a landline with its area code (0788441234).";
    }

    return null;
}

/** Age as a whole number inside a range a resident record can plausibly hold. */
function validate_age(string $value, string $label = 'Age', bool $required = false): ?string
{
    if ($value === '') {
        return $required ? "$label is required." : null;
    }

    if (!ctype_digit($value)) {
        return "$label must be a whole number — no letters or symbols.";
    }

    $age = (int) $value;
    if ($age < 0 || $age > 125) {
        return "$label must be between 0 and 125.";
    }

    return null;
}

/**
 * Dropdown values. A <select> restricts what a person can click, but the POST
 * body can still say anything, so the submitted value is checked against the
 * same list the page rendered before it reaches the database.
 */
function validate_choice(string $value, array $allowed, string $label = 'Selection', bool $required = true): ?string
{
    if ($value === '') {
        return $required ? "$label is required." : null;
    }

    if (!in_array($value, $allowed, true)) {
        return "$label must be one of: " . implode(', ', $allowed) . '.';
    }

    return null;
}

/** Free-text field with a length ceiling that matches the column width. */
function validate_text(string $value, string $label, bool $required = false, int $max = 255, int $min = 0): ?string
{
    if ($value === '') {
        return $required ? "$label is required." : null;
    }

    if (mb_strlen($value) < $min) {
        return "$label must be at least $min characters.";
    }

    if (mb_strlen($value) > $max) {
        return "$label must be $max characters or fewer.";
    }

    return null;
}

/**
 * A date or datetime-local value. Rejects anything strtotime can't read, plus
 * dates outside the window the field is allowed to cover — an incident can't
 * be reported before it happened, and a record can't be dated in the future.
 */
function validate_date(
    string $value,
    string $label = 'Date',
    bool $required = false,
    ?string $notBefore = null,
    ?string $notAfter = null
): ?string {
    if ($value === '') {
        return $required ? "$label is required." : null;
    }

    $timestamp = strtotime(str_replace('T', ' ', $value));
    if ($timestamp === false) {
        return "$label is not a valid date.";
    }

    if ($notBefore !== null && $timestamp < strtotime($notBefore)) {
        return "$label cannot be earlier than " . date('F j, Y', strtotime($notBefore)) . '.';
    }

    if ($notAfter !== null && $timestamp > strtotime($notAfter)) {
        return "$label cannot be later than " . date('F j, Y', strtotime($notAfter)) . '.';
    }

    return null;
}

/** Username: letters, numbers, dot, underscore, hyphen. No spaces. */
function validate_username(string $value, string $label = 'Username'): ?string
{
    if ($value === '') {
        return "$label is required.";
    }

    if (strlen($value) < 4 || strlen($value) > 50) {
        return "$label must be between 4 and 50 characters.";
    }

    if (!preg_match('/^[A-Za-z0-9._-]+$/', $value)) {
        return "$label may only contain letters, numbers, dots, underscores, and hyphens — no spaces.";
    }

    return null;
}

/**
 * Password strength. Kept deliberately modest (8 characters, one letter, one
 * number) so it is enforceable on shared barangay workstations without pushing
 * staff toward writing passwords on a sticky note.
 */
function validate_password(string $value, string $label = 'Password'): ?string
{
    if ($value === '') {
        return "$label is required.";
    }

    if (strlen($value) < 8) {
        return "$label must be at least 8 characters.";
    }

    if (strlen($value) > 72) {
        // bcrypt silently truncates beyond 72 bytes; reject rather than mislead.
        return "$label must be 72 characters or fewer.";
    }

    if (!preg_match('/[A-Za-z]/', $value) || !preg_match('/\d/', $value)) {
        return "$label must include at least one letter and one number.";
    }

    return null;
}

/** Drop null entries so a page can test `if ($errors)` directly. */
function collect_errors(array $candidates): array
{
    return array_filter($candidates, static fn ($message) => $message !== null);
}
