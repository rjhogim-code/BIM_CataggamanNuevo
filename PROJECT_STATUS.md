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
  `includes/header.php`/`footer.php` (shared layout), and PHP versions of every page —
  `login.php`, `logout.php`, `index.php`, `residents.php`, `incidents.php`,
  `certificates.php`, `resident.php`.
- Passwords are now hashed with `password_hash()`/`password_verify()` (bcrypt via
  `PASSWORD_DEFAULT`) instead of stored in plaintext.
- All CRUD (add/edit/delete residents, file/cycle/delete incidents, issue certificates)
  now goes through prepared statements against MySQL, with a POST/redirect/GET pattern to
  avoid resubmission on refresh.
- `resident.php` — a page that existed but wasn't linked from any nav — was kept as a
  working standalone view against the same `residents` table rather than deleted, since
  it wasn't clear it was dead.

### Stack vs. manuscript conflict — resolved
Chapter III of the actual thesis manuscript documents **Node.js + Express.js + MySQL** as
the stack, and §8.7 of the baseline doc frames Express.js as a deliberate strength versus
the other capstone team's "framework-less PHP." That conflict wasn't caught until after
the PHP conversion above was already done. Asked to choose, the decision was **keep PHP,
update the manuscript** instead of reverting the code. Still needed (manuscript-side,
outside this repo, not something Claude can edit directly):

> §1 Stack / §8.7 — replace "Node.js, Express.js" with the actual implemented stack (PHP +
> PDO/MySQL); drop or reframe the §8.7 comparison to San Jose's "framework-less PHP," since
> both capstone systems are now plain PHP.

### Interaction Capability work (baseline backlog item #9: desktop-first, keyboard-efficient staff UI)
- Search boxes on `residents.php`, `incidents.php`, and `resident.php` autofocus on page
  load so staff can start typing a lookup without clicking in first.
- Add/Edit dialogs autofocus their first meaningful field (Full name, Complainant) the
  instant the modal opens, and correctly yield focus to the dialog instead of the
  page's search box when both are present.
- Login page autofocuses the username field.
- Fixed a staleness bug found while wiring this up: dismissing a dialog with Escape (vs.
  the Cancel/Close links) left `?new=1`/`?edit=ID` in the URL, so refreshing the page
  silently reopened the dialog. `app.js` now strips the query string via
  `history.replaceState` when a dialog closes without navigating away.

### Validation done
- `php -l` on every PHP file touched (no syntax errors).
- Live smoke tests against a real local MySQL instance via `php -S` + `curl`: register →
  login → add/edit/delete a resident → file an incident (verified the auto-generated
  `CN-{year}-{seq}` case number) → cycle its status → issue a certificate (verified the DB
  insert and the auto-print trigger) → dashboard counts/recent-activity reflect all of the
  above → logout tears down the session and protected pages redirect to login.
- Re-verified after the autofocus/Escape changes: confirmed via `curl` that the autofocus
  attribute lands on the right element in each state (list page vs. dialog open).
- **Real in-browser QA (2026-09-28, via Playwright/Chromium)**: ran the same
  register → login → dashboard → add resident → file incident → certificates → logout
  flow through an actual rendered browser, with screenshots at each step. This caught a
  real bug the curl-level testing above had missed: **`index.php`'s three stat
  `<strong>`/`<small>` elements (`residentCount`, `incidentCount`, `activeCases`,
  `certificateCount`) never had their `id` attributes** — dropped during the original
  HTML→PHP conversion. Harmless today (nothing reads those ids client-side anymore, since
  the values are server-rendered directly), but it didn't match the original design and
  would bite anyone who later wires up JS expecting those hooks. Fixed by adding the ids
  back. Lesson: curl+grep on text content isn't a substitute for checking actual markup/
  DOM — confirmed the fix by re-running the same Playwright flow and checking
  `page.$('#residentCount')` resolves and is visible, not just that "0" appears somewhere
  in the HTML.
- Confirmed visually: native `<dialog>` modals render with the real centered/backdrop
  look (not just inline, which is what you'd get without the `showModal()` JS call),
  autofocus lands on the right field in both the plain list view and inside an open
  dialog, the incident case-number default (`CN-2026-001`) and date default populate
  correctly, and the certificate preview layout renders as an actual formal document.

## 🔜 What's left (ours — Interaction Capability / frontend)

1. **Accessibility pass** — skip link, `aria-live` on flash/status messages, explicit
   `:focus-visible` styling, and a color-contrast spot-check. None of this has been added
   yet; the browser QA above confirmed things *work*, not that they meet a11y/contrast
   bars. The sibling San Jose project has a version of this worth using as a reference.
2. No responsive/mobile audit is planned here — unlike the San Jose project, this
   system's target is **desktop-first** (fixed staff workstations), per the baseline
   doc's §8.4. Don't port over San Jose's mobile-first work; it doesn't apply to this
   team's design target.
3. No bilingual (English/Filipino) requirement has been named for this project in the
   baseline doc — not treating it as in-scope unless that changes.

## 🚫 Out of scope (students' responsibility per baseline doc §6)

Flagged, not implemented. Ordered by how much it actually blocks the manuscript's own
claims, not the baseline doc's original ranking:

1. **RBAC doesn't restrict anything yet, and the role model doesn't match the
   manuscript.** Chapter III specifies a fixed 4-value role (Captain/Secretary/Treasurer/
   Staff) as an access-control mechanism. In the current code, `login.php`'s register
   form only offers Captain/Secretary/"Other" (no Treasurer or Staff option at all), and
   picking "Other" lets someone type *any* string as their role — it's just a label, not
   an enum. No page currently checks role before showing data or actions: `require_login()`
   only checks that *a* session exists. Every signed-in user can see and do everything.
   This is a real functional-suitability + security gap the manuscript's RBAC claims
   depend on, not a cosmetic one.
2. **No payments/transactions table.** The Treasurer role's entire stated purpose —
   payment processing for business permits, clearances, Cedula — is completely unmodeled
   in `schema.sql`. Biggest completeness gap per the baseline doc's own §4.
3. **No `audit_logs` table.** Logins, edits, prints, and failed access attempts aren't
   recorded anywhere, despite being a named module (baseline §5) and an explicit security
   claim (§8.6, "audit logging with user+IP+timestamp").
4. **Certificates have no tracking number.** `schema.sql`'s `certificates` table has no
   unique identifier field, though the manuscript's Data Dictionary describes one.
5. `household_id`/households normalization — lower-stakes, optional per the baseline.
6. No automated tests (PHPUnit) for RBAC or certificate issuance.
7. Backup-restore has never actually been tested; single-server failure fallback is
   undocumented (ops/hardware, not code).
8. Physical LAN build-out (dedicated server, managed switch, static IPs) — hardware, not
   code.
9. Manuscript editorial fixes: the stack-section update noted above, plus three
   pre-existing "Cloud-Based" → on-premise stale-text corrections tracked before this
   session (§1.3, §3.1, §3.8).

**Already resolved, no longer an open item**: password hashing. Baseline backlog item #4
("verify password hashing algorithm in code") is done for this codebase — `login.php`
uses `password_hash($password, PASSWORD_DEFAULT)` (bcrypt) and `password_verify()`, not a
placeholder or a weaker scheme.

## How to test locally

```
mysql -u root < schema.sql
DB_USER=root DB_PASSWORD= php -S localhost:8000
```

Visit `http://localhost:8000/login.php`, register an account (pick a role), sign in. If
using XAMPP/MAMP instead, the defaults in `db/connection.php` (`localhost` / `root` / no
password / `barangay_information_system`) already match a typical local setup — just
import `schema.sql` and drop the folder into `htdocs`.

Note: the San Jose project's own "how to test locally" also defaults to port 8000 — if
both are running at once, start this one on a different port
(`php -S localhost:8100`, for example).
