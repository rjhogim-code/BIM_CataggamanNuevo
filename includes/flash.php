<?php

/**
 * Flash messages, form-state carry-over, and CSRF tokens.
 *
 * Every write in this system follows POST -> redirect -> GET so a refresh can't
 * resubmit. That pattern loses whatever the person typed, which is fine when
 * the save succeeded and useless when it failed. These helpers park the
 * rejected input and its error messages in the session for exactly one request,
 * so the redirect can land back on the dialog with the fields still filled in
 * and the reason shown next to the offending one.
 */

require_once __DIR__ . '/auth.php';

/* ---------------------------------------------------------------------------
 * Flash banner (one-off success / error message shown after a redirect)
 * ------------------------------------------------------------------------ */

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_success(string $message): void
{
    flash('success', $message);
}

function flash_error(string $message): void
{
    flash('error', $message);
}

/** Read and clear the pending flash message. */
function take_flash(): ?array
{
    $value = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return $value;
}

/* ---------------------------------------------------------------------------
 * Rejected form state
 * ------------------------------------------------------------------------ */

/**
 * Park per-field errors plus the submitted values so the next GET can rebuild
 * the form exactly as the person left it.
 */
function flash_form(array $errors, array $input): void
{
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_input'] = $input;
}

/** Read and clear the per-field error messages. */
function take_errors(): array
{
    $value = $_SESSION['form_errors'] ?? [];
    unset($_SESSION['form_errors']);

    return $value;
}

/** Read and clear the values the person had typed. */
function take_input(): array
{
    $value = $_SESSION['form_input'] ?? [];
    unset($_SESSION['form_input']);

    return $value;
}

/**
 * Pick the value a field should show: what was rejected a moment ago, else the
 * record being edited, else a default.
 */
function old(array $input, ?array $record, string $key, $default = ''): string
{
    if (array_key_exists($key, $input)) {
        return (string) $input[$key];
    }

    if ($record !== null && array_key_exists($key, $record) && $record[$key] !== null) {
        return (string) $record[$key];
    }

    return (string) $default;
}

/* ---------------------------------------------------------------------------
 * CSRF
 * ------------------------------------------------------------------------ */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/** Hidden input to drop inside every form that writes something. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

/**
 * Reject a write whose token doesn't match the session. Redirects back to the
 * given page with an explanation rather than failing silently, because the most
 * common cause is a genuinely expired session rather than an attack.
 */
function require_csrf(string $redirectTo): void
{
    $submitted = $_POST['csrf_token'] ?? '';

    if (!is_string($submitted) || !hash_equals(csrf_token(), $submitted)) {
        flash_error('Your session expired before that form was submitted. Please sign in again and retry.');
        header("Location: $redirectTo");
        exit;
    }
}

/* ---------------------------------------------------------------------------
 * Output helpers
 * ------------------------------------------------------------------------ */

/** Escape for HTML text and attribute contexts. */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** Render the inline error under a field, if that field has one. */
function field_error(array $errors, string $key): string
{
    if (!isset($errors[$key])) {
        return '';
    }

    // A span, not a p: this is rendered both inside a <div class="field"> and
    // inside a <label>, and a <p> is not valid content for a label.
    return '<span class="field-error" id="' . e($key) . '-error">' . e($errors[$key]) . '</span>';
}

/**
 * Attributes that tie an input to its error message for screen readers, and
 * let CSS colour the invalid field.
 */
function field_attrs(array $errors, string $key): string
{
    if (!isset($errors[$key])) {
        return '';
    }

    return ' aria-invalid="true" aria-describedby="' . e($key) . '-error"';
}
