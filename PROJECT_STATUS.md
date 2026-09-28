# Project Status — Barangay Cataggaman Nuevo BIM

This repo is the implementation for the BSIT capstone thesis *Design and Development of
a Secure Client-Server Network for Barangay Information in Cataggaman Nuevo, Tuguegarao
City* (students Roxas, Martin, Cayao; adviser Nino "Nenji" Duque). A separate ISO/IEC
25010:2023 baseline doc (not stored in this repo — pasted into sessions as needed) audits
the manuscript against the code and splits ownership: **backend logic, RBAC, payments
modeling, and audit-log structure are the students'** to author; **frontend, UI/design,
and Interaction Capability are Nenji + Claude's**.

Last updated: 2026-09-28

## ✅ What happened

### Full Node.js → PHP conversion
The system was previously an Express.js static-file server with almost no real backend —
all resident/incident/certificate data lived in browser `localStorage`, and login was a
`sessionStorage` flag anyone could fake from devtools. It's now a procedural PHP + MySQL
app with real server-side persistence and session-based auth:

- **Removed**: `server.js`, `package.json`/`package-lock.json`, `db/database.js`, and the
  old static `.html` pages.
- **Added**: `schema.sql` (`users`, `residents`, `incidents`, `certificates` tables),
  `db/connection.php` (PDO, env-configurable), `includes/auth.php` (session guard),
  `includes/header.php`/`footer.php` (shared layout), and PHP versions of every page.
- Passwords are hashed with `password_hash()`/`password_verify()` (bcrypt via
  `PASSWORD_DEFAULT`) instead of stored in plaintext.
- All CRUD goes through prepared statements against MySQL, with POST/redirect/GET to
  avoid resubmission on refresh.

### Stack vs. manuscript conflict — resolved
Chapter III of the actual thesis manuscript documents **Node.js + Express.js + MySQL** as
the stack, and §8.7 of the baseline doc frames Express.js as a deliberate strength versus
the other capstone team's "framework-less PHP." That conflict wasn't caught until after
the PHP conversion was already done. Asked to choose, the decision was **keep PHP, update
the manuscript** instead of reverting the code. Still needed (manuscript-side, outside
this repo, not something Claude can edit directly):

> §1 Stack / §8.7 — replace "Node.js, Express.js" with the actual implemented stack (PHP +
> PDO/MySQL); drop or reframe the §8.7 comparison to San Jose's "framework-less PHP," since
> both capstone systems are now plain PHP.

### Input & data validation layer (2026-09-28)
Before this, the only validation anywhere was `required` attributes and a length check on
the registration password. Dropdown values, phone numbers, ages, and dates went from
`$_POST` straight into the database, and a failed save silently redirected with no
explanation and threw away whatever had been typed.

- **`includes/validation.php`** — one shared set of validators, each returning an error
  message or `null`: name, email, contact number, age, choice/enum, free text, date,
  username, password. Pages compose them with `collect_errors()`.
  - **Email** must contain `@` *and* pass `FILTER_VALIDATE_EMAIL`, so `nino@localhost`
    and `nino@@x.com` are rejected too, not just strings missing the sign.
  - **Contact number** is digits only once spaces, dashes, dots, and parens are stripped.
    Accepts an 11-digit PH mobile (`09XXXXXXXXX`) or a 7–10 digit landline with area
    code. `+63`/`63` prefixes normalize to the local `0` form, and the **normalized**
    value is what gets stored, so the column doesn't end up holding four spellings of
    the same number.
  - **Names** allow letters (including Ñ and accents), spaces, hyphens, apostrophes, and
    periods — so "Peña", "D'Souza", and "Jr." work, but digits don't.
  - **Dropdowns are checked server-side against the same list the page rendered.** A
    `<select>` restricts clicking, not POSTing; a forged `gender=NotAGender` or
    `register_role=SuperAdmin` is now rejected instead of stored.
  - **Dates** are range-checked: an incident can't be dated in the future, a certificate
    can't be issued for a future day or back-dated more than a week.
- **`includes/flash.php`** — flash banners, per-field errors, and the rejected input all
  survive the redirect in the session, so a failed save re-opens the dialog with the
  fields still filled in and the reason shown next to the offending one. Also holds the
  CSRF helpers and `e()`/`field_error()`/`field_attrs()` output helpers.
- **Client-side mirror** in `app.js` runs the same rules on blur so staff see the problem
  while typing. It is explicitly a convenience layer — the server check is the one that
  protects the data and runs regardless.
- **Duplicate guards**: same resident name in the same zone is rejected (the most common
  barangay data-quality problem), as is a reused incident case number.

### Transaction handling (2026-09-28)
- Every write is wrapped in `beginTransaction()` / `commit()` / `rollBack()`, so a failed
  save leaves nothing half-written and reports "nothing was changed" rather than failing
  silently.
- **Incident status cycling** now reads and writes inside one transaction with
  `SELECT ... FOR UPDATE`. Two workstations advancing the same case could previously both
  read "Pending" and skip a step.
- **Certificate issuance was the one write that didn't redirect** — it rendered the
  printable page directly from the POST, so refreshing the printed page recorded the
  certificate a second time. It now stores the issued details in the session, redirects
  to `?issued=1`, renders from there and prints. Refreshing shows an empty form.
- **Case numbers** now continue from the highest sequence already issued this year
  (`MAX(...)` on the numeric suffix) rather than from the total row count. Deleting a
  report used to hand its number to the next one filed.
- **CSRF tokens** on every form that writes. A mismatch redirects with "your session
  expired" rather than failing silently, because an expired session is the likeliest
  cause. `session_regenerate_id(true)` on sign-in; `logout.php` now also expires the
  session cookie instead of only clearing server-side data.

### Responsive / mobile pass (2026-09-28) — reverses an earlier decision
The previous version of this file recorded "no responsive/mobile audit is planned here —
this system is desktop-first per baseline §8.4." **Nenji has since asked for
responsiveness explicitly, so that call is reversed.** Desktop remains the primary target
(fixed staff workstations), but every screen now reflows properly down to 360px.

- Breakpoints at 1080 / 900 / 760 / 420px.
- **Tables become one card per record below 760px**, each cell labelled from its
  `data-label` attribute, instead of forcing a horizontal scroll through nine columns.
  They revert to real tables on desktop.
- The sidebar becomes a proper off-canvas drawer: backdrop, Escape to close, focus moves
  into the drawer on open and back to the menu button on close, `aria-expanded` kept in
  sync, body scroll locked while open, and auto-reset if the viewport grows back.
- Verified with an automated sweep: **no horizontal page overflow on any page at 1440,
  1024, 820, 760, 480, or 360px.**

### Accessibility pass (2026-09-28) — closes backlog item #1
- Skip link as the first tab stop on every page.
- `role="status" aria-live="polite"` region for flash messages; `role="alert"` on
  validation summaries; `aria-invalid` + `aria-describedby` tying each bad field to its
  message.
- One consistent `:focus-visible` ring everywhere (pink, two-tone so it reads on both
  white and pink backgrounds).
- `aria-current="page"` on the active nav item; `<caption class="sr-only">` on tables;
  `scope="col"` on headers; `aria-label` on row action buttons so "Edit" announces as
  "Edit Juan Dela Cruz".
- `prefers-reduced-motion` honoured.
- **Contrast fixed.** The old `--pink:#ec4899` on white is **3.5:1 — it failed WCAG AA**
  for normal text, and it was the colour of every primary button. Buttons and pink text
  now use `#db2777` (**4.6:1, passes AA**) and `#be185d` (6.0:1); `#ec4899` is kept as an
  accent only. `.empty` (#90858d, 3.5:1) and `.nav-title` (#a1969e, 2.9:1) also failed
  and now use `--muted` (4.8:1). The system is still unmistakably pink — just legible.

### UI/UX and design pass (2026-09-28)
- `styles.css` rewritten as a documented design system: a full 50–900 pink scale with the
  contrast maths written next to it, plus spacing, radius, shadow, and font tokens.
  Readable formatting with section headings, since students have to defend this code.
- Inline SVG icon set (`includes/icons.php`) — inline rather than an icon font or CDN so
  the system stays self-contained on a barangay LAN with no internet.
- System font stack for the same reason: no webfont request that can hang offline.
- Stat cards with pink accent icons, redesigned nav with a pink active bar, pill user
  chip, hover states on table rows, dismissible flash banners, empty states with an icon
  and a next step ("Use 'Add New Resident' to create the first record"), result counts
  ("3 residents matched"), and a Clear button when filters are active.
- Search boxes are debounced (350ms) instead of submitting on every keystroke, and the
  caret is restored to the end of the restored term.
- Certificate preview printed **"BARANGAY" above "BARANGAY CLEARANCE"** — the static word
  only ever made sense for two of the three types. The type alone is now the title.
- Residents page gained a voter-status filter alongside gender.

### `resident.php` folded into `residents.php`
`resident.php` was a second, standalone copy of the resident directory — its own inline
stylesheet, its own copy of the insert/update/delete logic, its own markup, and not
linked from any nav. Two copies of one feature meant every fix had to be made twice, and
this one kept missing them: it never got CSRF, server-side validation, or the responsive
table. Its one unique feature (the gender filter) now lives on `residents.php`, so
**`resident.php` is now a 301 redirect** that forwards `?new`/`?edit`/`?q`/`?gender`
through. The URL still works for any bookmark or manuscript screenshot; there is just no
longer a second implementation to keep in sync. Reversible in git if that's the wrong
call.

### Schema changes — a migration is required for existing databases
`schema.sql` uses `CREATE TABLE IF NOT EXISTS`, so re-running it will **not** update a
database that already exists. Run **`db/migration_2026_09_28.sql`** once instead:

```
mysql -u root barangay_information_system < db/migration_2026_09_28.sql
```

It adds: `residents.email`; indexes on `residents.name`, `residents.zone`, and
`incidents.status`; and a **unique key on `incidents.case_no`** (two reports sharing
CN-2026-001 makes the number useless as a reference). If that last one fails with
"Duplicate entry", the existing data already has repeats — the file includes the query to
find them. A fresh install from `schema.sql` already has all of this.

### Validation performed
- `php -l` on all 14 PHP files — clean, no warnings or deprecations in the server log
  under PHP 8.5.
- **43 server-side tests** against a real local MySQL instance covering every validation
  path: weak/short credentials, forged enum values (`register_role=SuperAdmin`,
  `gender=NotAGender`, `certType=Fake Certificate`), letters in a phone number, an email
  with no `@`, an out-of-range age, a future incident date, duplicate residents and
  duplicate case numbers, CSRF rejection (and confirmation the forged write did *not*
  land), contact normalization, rejected input being preserved, certificate
  redirect-then-print, refresh-does-not-reprint, the `resident.php` 301, and logout
  teardown. All 43 pass on a clean database.
- **36 real-browser tests** (Playwright/Chromium) covering the responsive sweep at six
  widths, the mobile drawer's full keyboard/ARIA behaviour, the table→cards reflow,
  dialog focus and Escape-strips-query-string, live client-side validation appearing and
  clearing, the print stylesheet, the measured button contrast ratio, and a console-error
  check. All 36 pass.
- **A real bug the server-side tests missed**: at tablet widths (760–1080px) the whole
  page scrolled sideways by up to 269px. Cause was subtle — `.sr-only` uses
  `position:absolute` with auto offsets, so the "Edit *name*" spans inside the wide table
  sat at their static position *outside* the viewport and, having no positioned ancestor,
  resolved against the viewport and escaped the table wrapper's clipping. Fixed twice
  over: `.table-wrap` is now `position:relative` (contains them), and the action buttons
  use `aria-label` instead of nested spans. This is the same lesson as the earlier
  missing-`id` bug — markup-level and curl-level checks don't catch layout.

## 🔜 What's left (ours — Interaction Capability / frontend)

1. No bilingual (English/Filipino) requirement has been named for this project in the
   baseline doc — not treating it as in-scope unless that changes.
2. Optional polish, not blocking: a dark mode (deliberately skipped — it risks the print
   stylesheet and adds review surface for no stated requirement), and table sorting by
   column.

## 🚫 Out of scope (students' responsibility per baseline doc §6)

Flagged, not implemented — these are the students' own thesis work to author, and doing
them for the team would undermine the capstone. Ordered by how much each blocks the
manuscript's own claims.

1. **RBAC doesn't restrict anything yet, and the role model doesn't match the
   manuscript.** Chapter III specifies a fixed 4-value role (Captain/Secretary/Treasurer/
   Staff) as an access-control mechanism. `login.php`'s register form still offers only
   Captain/Secretary/"Other", and "Other" lets someone type any string as their role. No
   page checks role before showing data or actions — `require_login()` only checks that
   *a* session exists, so every signed-in user can see and do everything.
   *Partially mitigated this session:* the submitted role is now validated against the
   offered list, and the free-text option is constrained to a name-shaped string, so role
   is at least no longer arbitrary attacker-controlled text. Turning it into a real enum
   and enforcing it per page is still the students' task. A pointer to this sits in a
   comment above `ROLE_OPTIONS` in `login.php`.
2. **No payments/transactions table.** The Treasurer role's entire stated purpose —
   payment processing for business permits, clearances, Cedula — is unmodeled in
   `schema.sql`. Biggest completeness gap per baseline §4.
3. **No `audit_logs` table.** Logins, edits, prints, and failed access attempts aren't
   recorded anywhere, despite being a named module (baseline §5) and an explicit security
   claim (§8.6, "audit logging with user+IP+timestamp"). Note that the new
   `flash_error()` call sites are natural hook points when this is built.
4. **Certificates don't store what was issued.** Two related gaps, one item:
   - The `certificates` table has no tracking number, though the manuscript's Data
     Dictionary describes one.
   - The form collects **Zone/Sitio and Purpose but never saves them** — only name, type,
     and date are written. A reissued or audited certificate can't reproduce its own
     text. Suggested shape when the students take this on:
     `ALTER TABLE certificates ADD COLUMN tracking_no VARCHAR(50) NULL UNIQUE,
     ADD COLUMN address VARCHAR(150) NULL, ADD COLUMN purpose VARCHAR(200) NULL;`
     Both fields are already validated and available in `certificates.php`; only the
     schema and the INSERT need to change.
5. **No login rate limiting.** Repeated failed sign-ins are unlimited. Belongs with the
   audit-log work (#3), since both need a record of failed attempts.
6. `household_id`/households normalization — lower-stakes, optional per the baseline.
7. No automated tests (PHPUnit) for RBAC or certificate issuance. The two suites written
   this session are external scripts, not committed test files.
8. Backup-restore has never actually been tested; single-server failure fallback is
   undocumented (ops/hardware, not code).
9. Physical LAN build-out (dedicated server, managed switch, static IPs) — hardware.
10. Manuscript editorial fixes: the stack-section update noted above, plus three
    pre-existing "Cloud-Based" → on-premise stale-text corrections (§1.3, §3.1, §3.8).

**Already resolved, no longer open**: password hashing (baseline backlog #4) — `login.php`
uses bcrypt via `password_hash()`/`password_verify()`, and registration now also enforces
a minimum strength (8+ characters, at least one letter and one number).

## How to test locally

```
mysql -u root < schema.sql
DB_USER=root DB_PASSWORD= php -S localhost:8000
```

If you already have a database from before 2026-09-28, also run the migration:

```
mysql -u root barangay_information_system < db/migration_2026_09_28.sql
```

Visit `http://localhost:8000/login.php`, register an account, sign in. Registration now
requires a username of 4+ characters and a password of 8+ characters containing a letter
and a number.

If using XAMPP/MAMP instead, the defaults in `db/connection.php` (`localhost` / `root` /
no password / `barangay_information_system`) already match a typical local setup — import
`schema.sql` and drop the folder into `htdocs`.

Note: the San Jose project's own "how to test locally" also defaults to port 8000 — if
both are running at once, start this one on a different port
(`php -S localhost:8100`, for example).
