# Local testing guide

Use this guide to test the currently implemented frontend and backend foundation. Start with sections 1–3, follow the manual checklists, then run the automated checks. Check a box only after observing the expected result.

## 1. Know which version you are testing

| Version | URL after starting Laravel | What it tests |
| --- | --- | --- |
| Connected staff workspace, frontend Phase 2 | http://127.0.0.1:8000/workspace | Real login, server permissions, saved donor records, corrections and audit activity |
| Connected public availability | http://127.0.0.1:8000/availability | Explicitly published aggregate snapshots, freshness and withdrawal; no private donor data |
| Password assistance | http://127.0.0.1:8000/recovery | Disabled-mail guidance and reset-link consumption; use `/recovery#change` while signed in to change your password |
| Public and staff demonstration | http://127.0.0.1:8000/ | Hero, locator examples and simulated operational screens using fictional in-memory records |
| Minimal backend health response | http://127.0.0.1:8000/api/v1/health | Application/database health endpoint; not a complete monitoring system |

The demo's role selector does not sign you into the connected workspace. Demo changes reset on reload; connected saves persist. Assigned donation history, inventory, in-app alerts, restricted clinical records and local SQLite recovery are now connected. Follow [the improvements walkthrough](IMPROVEMENTS.md) for their setup and checks. Connected traceability, reports, publication and staff-account checks are also in that walkthrough. Public availability, reports and external SMS in the independent demo remain illustrations.

Use fictional test data. Local browser testing writes to your configured development database; automated backend tests use isolated SQLite databases. Do not use a production database for these steps. A separate isolated MySQL 8.4.13 CI suite has passed; production environment validation remains separate work.

## 2. Open the project folder

Open a PowerShell terminal in VS Code, or use your existing PowerShell window. Enter:

```powershell
cd "C:\Users\Carlze Alberca\Documents\IT SIA - 1st Sem - 2026 - 2027"
```

The commands below use a process-scoped execution-policy override because this computer previously blocked `.ps1` scripts. It does not permanently change your PowerShell execution policy. An organization-enforced policy may still take precedence.

## 3. Prepare and start the connected workspace

### Setup, if not already completed

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File ".\scripts\backend.ps1" -Action setup
```

Expected: migrations and role seeds complete without an error. Setup creates a missing development `.env` and application key; it does not reset the database. If setup has already succeeded, you can skip it. Dependencies must already be installed; see [backend setup](../backend/README.md) if they are missing.

### Create your own fictional staff account

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File ".\scripts\backend.ps1" -Action user
```

Answer the interactive prompts. A useful first account is:

| Prompt | Example / instruction |
| --- | --- |
| Staff display name | `QA Red Cross Admin` |
| Email | `qa.rcadmin@example.test` |
| Role | Choose `Red Cross Administrator` |
| Password | Choose your own: at least 12 characters, uppercase and lowercase letters, a number and a symbol |
| Confirm password | Enter the same password again |

Password entry is hidden. No default login or shared password is provided. Do not paste a password into a command or this document. Skip account creation if you already have a working account; an existing email cannot be registered again.

### Start the server

For an already configured system, the shortest Windows command from the project root is:

```powershell
.\start.cmd
```

The shortcut uses the same local server wrapper below. Use `.\start.cmd -Port 8001` if you deliberately need a different available port. Keep one server running; an occupied port is reported rather than silently switching URLs. Restart after editing `.env`. No setup, migration, or account recreation is needed for an ordinary restart.

The full equivalent command remains:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File ".\scripts\backend.ps1" -Action serve
```

Keep this terminal open. Open **http://127.0.0.1:8000/workspace** in your browser. Use `127.0.0.1` consistently instead of switching between it and `localhost`, which have separate cookie contexts. Stop the server with **Ctrl+C** when finished.

## 4. Connected workspace checklist

Run these checks as the Red Cross Administrator account unless a different role is specified. Use a unique fictional record name per test run, such as `QA Donor Run 001`. Search before creating a record. A matching name is not a guaranteed duplicate-person check.

### Sign-in, overview and session

- [ ] Open `/workspace` signed out. Expected: staff sign-in, with no private donor records visible.
- [ ] Enter an incorrect password once. Expected: useful failure feedback, retained email and cleared password; no authenticated workspace. Avoid repeated attempts, which can trigger login throttling.
- [ ] Use Show/Hide password. Expected: visibility toggles without changing the entered value.
- [ ] Sign in correctly. Expected: overview, your account identity and permitted navigation.
- [ ] Check overview totals against the registry/audit lists. Expected: totals represent the permitted server scope, not just the recent records displayed.
- [ ] Open your account details and refresh access. Expected: your role is shown and permissions are fetched again.
- [ ] Sign out, then attempt `http://127.0.0.1:8000/workspace#/donors`. Expected: sign-in instead of private records. After signing in, the requested donor page opens.

### Register, review and persist a donor

Example values: `QA Donor Run 001`, `Digos City`, recorded blood type `O+`.

- [ ] Open Donor registry and select Register donor. Submit blank or whitespace-only required values. Expected: validation feedback; no saved record.
- [ ] Enter the example values and select Review registration. Expected: a summary appears before any save.
- [ ] Select Edit entries. Expected: the entries remain available for correction.
- [ ] Review again and select Register donor. Expected: success only after the server responds, followed by the saved donor profile.
- [ ] Check the profile. Expected: reference, recorded blood type, municipality, version and Philippine-time timestamps. “For record review” is an administrative state, not medical clearance.
- [ ] Copy the reference. Expected: a clipboard success message or instructions to copy the displayed reference manually if clipboard access is unavailable.
- [ ] Reload the page and sign in again if needed. Expected: the saved record still exists with the same reference.

### Search, filters and pagination

- [ ] Search for your unique fictional name. Expected: matching accessible records, with the search in the URL.
- [ ] Filter by its recorded blood type, then by a different type. Expected: matching results first, then no match for that record.
- [ ] Search for a unique nonexistent name. Expected: a no-results message with a way to reset filters.
- [ ] Change rows per page to 10, 25 and 50 and submit the filters. Expected: URL and results reflect the selection.
- [ ] If there are multiple pages, move between them. Expected: search, blood type and page size remain selected. Mark this check blocked if too few records exist; do not invent a pass.
- [ ] Use browser Back/Forward. Expected: the URL's filter state and matching screen return.
- [ ] Open `http://127.0.0.1:8000/workspace#/donors?page=999999`. Expected: recovery to a valid page is offered rather than a misleading empty-registry claim.

### Correct a record and protect unsaved entries

- [ ] Open your fictional donor and start a correction. Change the municipality and enter a correction reason of at least three characters, such as `QA correction of municipality`.
- [ ] Review the correction. Expected: changed values, reason and record version are displayed before saving.
- [ ] Save. Expected: the new value persists and the record version increments.
- [ ] Start another correction, change a field and select Cancel or press Escape. Expected: discard confirmation.
- [ ] Select Keep editing. Expected: your draft remains. Cancel again and select Discard entries. Expected: the saved record is unchanged.
- [ ] Try correcting without a reason. Expected: validation feedback and no write.

### Two-tab concurrent correction

Use the same signed-in browser session. Tabs share login state, but each form retains the version it originally opened.

1. In tab A, open a fictional donor and start a correction. Change its municipality, enter a reason and leave the form open.
2. In tab B, open the same donor, correct its name, enter a reason and save.
3. Return to tab A, review and attempt to save.
4. Expected: a newer-version conflict; tab A's draft is preserved and does not overwrite tab B silently.
5. Select Load latest record. Compare saved and draft values for every field.
6. Choose the values you intend to keep, apply the comparison choices and review again.
7. Save. Expected: the chosen values persist using the latest version. Verify that tab B's change is retained if you selected its saved value.

### Audit trail

- [ ] Open Audit trail. Expected: persisted activity for permitted accounts.
- [ ] Find your donor registration and correction using the full donor reference or the `donor.created` / `donor.corrected` action shortcut. Search uses exact action codes, references or request IDs.
- [ ] Inspect a correction. Expected: actor, time, reference, reason, changed field names and request reference. Donor field values and passwords are excluded; do not put sensitive details in the reason.
- [ ] Close the dialog, search for an unmatched reference, then reset. Expected: meaningful empty state and restored activity list.

### Failed connection and expired session

- [ ] Open browser developer tools, Network, and select Offline. Navigate or refresh a workspace page. Expected: offline/error feedback, with no invented successful response. Return to Online afterward and retry.
- [ ] On a fictional registration form, enter a draft, review it, go offline and attempt a save. Expected: no success claim; entries remain in the dialog. Restore connectivity and retry the unchanged entry. Expected: one saved registration. If the original request may have reached the server, check the registry before starting another registration.
- [ ] To test session invalidation, leave the donor registry open in tab A and sign out in tab B. Navigate or refresh in tab A. Expected: the next protected request returns to sign-in and clears private page/dialog content. Clearing is request-driven, not a claim of immediate cross-tab synchronization.

Requests have a 15-second deadline. The automated suite covers an uncertain registration response and reuse of its original idempotency key; simply going offline does not reproduce every possible interrupted-response condition. No write is automatically retried.

## 5. Role-access checks

Create additional fictional accounts using the same `-Action user` command, each with a unique email. Sign out before changing accounts; use separate browser profiles/private windows if simultaneous independent sessions are needed. Tabs in one profile share the same login.

| Role | Expected donor access | Create / correct | Audit |
| --- | --- | --- | --- |
| Red Cross Administrator | Assigned records | Yes / assigned | Yes |
| Red Cross Personnel | Assigned records | Yes / assigned | Denied |
| Barangay Health Coordinator | Assigned records | Yes / assigned | Denied |
| Report Viewer | Denied | Denied | Denied |
| System Administrator | Denied | Denied | Yes |

- [ ] Verify each account's visible navigation and Workspace guide agree with this matrix.
- [ ] For a denied feature, enter its URL directly: `/workspace#/donors` or `/workspace#/audit`. Expected: access denied, not a hidden-link-only restriction.
- [ ] Create a donor as a coordinator. Expected: the coordinator can find and correct that record.
- [ ] Copy an unassigned donor profile URL, then open it as the coordinator. Expected: not found (404) without disclosing that out-of-scope record.
- [ ] As Report Viewer, verify inventory/expiry reports work while donor details and donation reports remain denied.

Red Cross Administrators manage explicit assignments through a reference-only queue. Existing records begin assigned to their creator; new records receive the creator assignment under the provisioned create permission. Clinical actions require separate grants. System Administrator has audit and technical backups without operational donor access. See [the current matrix](../docs/backend/OPERATIONS.md).

## 6. Public and staff demo checklist

Open **http://127.0.0.1:8000/**. This is a separate prototype, including when reached from the connected sign-in page. Staff demo routes use the role-preview selector rather than real credentials.

- [ ] Hero: branding, navigation and calls to action lead to working routes.
- [ ] Availability locator: normal, stale and unavailable previews; refresh timestamp and availability disclaimers; no private donor fields.
- [ ] Help, privacy, sign-in design and recovery design: readable content and working navigation; no claim that password recovery sends email.
- [ ] Choose a demo operational role: overview, donor search, profile, correction, assessment and donation history respond to fictional input.
- [ ] Inventory: add a unique fictional unit, verify it, exercise stock transitions with reasons, and check that terminal actions cannot repeat.
- [ ] Publish a sample public snapshot: only the intended aggregate counts appear in the locator; no donor names become public.
- [ ] Reports: date/type filters and CSV download reflect matching sample records. Keep downloaded files local.
- [ ] Notifications: compose, inspect and simulate outcomes; no real SMS is sent.
- [ ] Choose the System Administrator demo role: exercise user, audit, backup/recovery metadata and settings screens. No real account, backup or restore is created by this demo.
- [ ] Try a route the selected demo role cannot access. Expected: denial state.
- [ ] Reload. Expected: fictional changes reset; real connected records are unaffected.

If testing the static demo alone without Laravel, run this from the project root and use http://127.0.0.1:8080/:

```powershell
& ".\.tools\php84\php.exe" -S 127.0.0.1:8080 -t frontend
```

Keep that terminal open and stop with Ctrl+C. Port 8080 and VS Code Live Server cannot supply the connected authentication/API.

## 7. Responsive, accessibility and browser checks

Test both versions. Record the browser and device used; automated DOM tests do not render pixels or establish accessibility conformance.

- [ ] Review widths 360px, 768px, 1024px and 1440px. Expected: readable content, reachable navigation and controls, mobile record cards in the connected workspace, and no page-wide overflow hiding actions.
- [ ] Check 200% browser zoom and a long fictional name/reference. Expected: wrapping and reachable controls.
- [ ] Navigate with Tab, Shift+Tab, Enter and Escape. Expected: visible focus, usable forms and dialogs, meaningful focus after navigation and dialog dismissal.
- [ ] Check required fields and error feedback with a screen reader. Expected: labels, errors, loading and save status are understandable.
- [ ] Enable reduced motion. Expected: decorative motion is reduced without removing functionality.
- [ ] Try touch input and a throttled connection. Expected: usable targets, clear pending states and no duplicate submission while a save is pending.
- [ ] Inspect the browser Console and Network. Expected: no unexplained JavaScript errors, missing CSS/modules or private responses from public endpoints. Do not share cookies, passwords or full private request/response bodies in screenshots.

## 8. Automated tests

Open a **second terminal** in the project root. The manually started server can remain running; the real-HTTP integration test manages its own temporary server/database.

### Frontend

The recorded test baseline is Node.js 26.8.2 with npm 11.19.1; `.node-version` records the CI version. The root test tooling requires Node.js 22+. Check your installed versions:

```powershell
node --version
npm.cmd --version
```

From the project root, install the committed dependency lockfile (including Happy DOM 21.0.4):

```powershell
npm.cmd ci --ignore-scripts --no-audit --no-fund
```

Run all frontend tests:

```powershell
npm.cmd test
```

Expected current baseline: **51 passing tests**, zero failures and zero skipped tests. These cover the demo model/workflow, selected contrast pairs, connected DOM workflows, API-client behavior, workspace views and asset references. The connected DOM scenario simulates HTTP responses; it does not prove a live browser-to-database workflow. Use the manual connected checklist and backend HTTP suite for that boundary.

Syntax-only checks:

```powershell
Get-ChildItem frontend/src -Filter '*.js' | ForEach-Object { node --check $_.FullName }
```

Expected: no syntax errors. These checks do not replace behavior or visual checks.

### Backend

The new cross-platform entry also supports `npm.cmd run test:backend`, or `npm.cmd run test:all` for syntax plus both suites. See the [root Git/setup guide](../README.md) for fresh-clone installation and runtime selection. Tests explicitly use isolated SQLite settings; a running development server is not required.

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File ".\scripts\backend.ps1" -Action test
```

Expected recorded connected baseline: **63 passing tests with 557 assertions**; test counts may change as features are added. Check the actual run's result rather than assuming this baseline proves a fresh pass.

The suite covers real authentication and authorization, validation, persistence, idempotency, stale corrections, transactional audit behavior, safe errors, asset delivery and an actual HTTP session/CSRF flow. It uses isolated SQLite data, not the manually populated development records. It does not establish MySQL compatibility.

The Windows HTTP test must be able to stop its own temporary process tree. A restricted automation sandbox can prevent cleanup. If cleanup hangs, preserve the output and report that restriction; do not count an interrupted run as passing or terminate unrelated PHP processes.

## 9. Troubleshooting

| Symptom | What to check |
| --- | --- |
| Script is not recognized | Run the section 2 `cd` command. Confirm `.\scripts\backend.ps1` exists in this folder. |
| Running scripts is disabled | Use the process-scoped `powershell -NoProfile -ExecutionPolicy Bypass -File ...` commands above. |
| Browser cannot connect | Leave the server terminal open and check startup output. Verify port 8000. |
| Port 8000 is occupied | If your existing project server is running, use it. Otherwise identify the owner; stop only a server you own before restarting. |
| Login does not work | Create a local account with `-Action user`; demo roles are not credentials. Use the same hostname and port throughout. |
| PHP version error | The script prefers `.tools/php84/php.exe` and otherwise needs PHP 8.3+. XAMPP PHP 8.2 cannot run this Laravel backend. |
| Missing vendor/autoload.php | Install the locked backend dependencies using the portable runtime instructions in `backend/README.md`. |
| Missing tables/key | Complete local setup, then retry. Preserve existing development data. |
| Missing Happy DOM module | Run `npm.cmd ci --ignore-scripts --no-audit --no-fund` from the project root. Tests no longer depend on `tmp/frontend-qa`. |
| PHPUnit cannot open vendor/phpunit | Use the wrapper's `-Action test`; it changes into `backend` before invoking Artisan. |
| CSS looks missing/outdated | Open the correct URL, hard-refresh, then check Network for `/css/styles.css`, `/css/experience.css` and, on the workspace, `/css/workspace.css`. |
| CSRF/session expired | Sign in again on the same hostname. If persistent, record the safe request reference and check local session/cookie configuration. |
| Old test record is still present | Connected records persist. Use a new run label. There is no donor-delete endpoint; do not use `migrate:fresh` to clear an existing database. |

## 10. Record a test run

Copy this block into your own run notes. Mark unavailable cases **blocked** or **not run**, with the reason.

```text
Date/time:
Tester:
Browser/version/device:
Version tested: connected / demo / both
Role(s):
Fictional run label:
Frontend automated result:
Backend automated result:
Manual checks passed:
Failed check and expected/actual behavior:
Steps to reproduce:
Safe request reference, if shown:
Screenshot filename, if useful:
Blocked or not-run checks:
Follow-up needed:
```

Also complete the [connected improvements walkthrough](IMPROVEMENTS.md): assignments, donation counts and source links, stock transitions/expiry, recipient alerts, clinical recording/review, isolated SQLite recovery, aggregate reports/publication and staff/password administration. Passing these checks validates the current local milestone. It does not establish clinical interpretation, security/load acceptance, production MySQL configuration, production backup restoration or release readiness. Browser/device and screen-reader checks need observed results; DOM tests alone do not establish them.

Related references: [Phase 2 scope](PHASE-2.md), [verification notes](QA.md), [frontend routes](README.md), [backend setup](../backend/README.md), [permission matrix and acceptance criteria](../docs/backend/FOUNDATION.md).

Verified on 18 November 2026: [GitHub Actions run 44144278513](https://github.com/Kazieess/blood-repository-system/actions/runs/44144278513) passed frontend checks on Windows/Linux (45 tests each), SQLite backend checks (59 tests, 510 assertions) and isolated MySQL 8.4.13 checks (55 tests, 444 assertions). SQLite-specific backup and HTTP tests run in the SQLite job. This is automated compatibility evidence, not production recovery or browser acceptance.
