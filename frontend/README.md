# Blood Repository frontend

A complete Phase 1 frontend demonstration for the proposed Davao del Sur Blood Repository and Donor Monitoring System. Includes a public hero landing page, view-only locator, and a separate staff workspace. All records are fictional. This is not a deployed or authenticated operational system.

## Run locally

From the workspace root in PowerShell:

    php -S 127.0.0.1:8080 -t frontend

Open http://127.0.0.1:8080. The home page now opens by default. Stop the server with Ctrl+C. VS Code Live Server also works. Use HTTP rather than opening index.html as a file, because the app uses native JavaScript modules.

No runtime dependencies, package installation, build step, external fonts, or external APIs are needed. PHP only serves static files.

## Screens

| Public experience | Route |
| --- | --- |
| Hero landing page | #/home |
| Sample availability and condition previews | #/availability |
| Help and frequently asked questions | #/help |
| Prototype privacy explanation | #/privacy |
| Staff sign-in design and role preview | #/login |
| Password recovery design | #/recovery |

| Staff workspace | Route |
| --- | --- |
| Overview | #/overview |
| Donor search, filters and pagination | #/donors |
| Donor profile, edit and assessment | #/donors/DDS-0001 |
| Donation history and entry | #/donors/DDS-0001?tab=history |
| Inventory search, filters and pagination | #/inventory |
| Unit detail, verification and stock history | #/inventory/BAG-0001 |
| Reports, date filters, chart and CSV download | #/reports |
| Sample notification queue and simulated results | #/notifications |
| Demo users and proposed role boundaries | #/users |
| Session activity log and CSV download | #/audit |
| Backup metadata, recovery checklist and health view | #/backups |
| Proposed workspace configuration | #/settings |

Staff routes require choosing a demo role. This is navigation simulation, not authentication. Red Cross Administrator exposes operational pages; System Administrator exposes users, backups, audit and settings. Report Viewer is limited to reports. Switch role using the role label in the desktop toolbar or More on mobile.

## Connected workflows

- Register a fictional donor, correct details with a reason, record a staff assessment, or add a historical donation. Counts and history update together. Duplicate same-day donation entries are rejected.
- Add a unique sample blood unit. It begins pending verification. Record a manual verification, quarantine, release, or disposal with a reason. Terminal stock actions cannot repeat.
- Publish a sample public snapshot explicitly. Only counts of available, verified, unexpired units are included. The snapshot stays separate until another publication.
- Generate inventory, donor, donation or expiry reports with date filters. Download a CSV of all matching rows, not just the visible page.
- Compose a sample notification, inspect its content, requeue the seeded failure, and record a simulated delivery. No SMS is sent.
- Add or manage demo users, save proposed settings, and rehearse backup/recovery record screens. No accounts, backups, restores or schedules are actually created.
- Inspect the session activity trail. It is not durable or tamper-resistant auditing.

The operational fixture date is fixed at 6 November 2026 so expiry and report examples remain reproducible. Actual activity timestamps use Asia/Manila. Proposed settings do not schedule jobs or establish approved business rules.

## Verification

Dependency-free model and contrast checks:

    node --test frontend/tests/model.test.mjs frontend/tests/contrast.test.mjs

DOM integration tests (development tool only, committed dependency lockfile; run from the project root):

    npm.cmd ci --ignore-scripts --no-audit --no-fund
    npm.cmd test

Use `npm.cmd run test:all` for JavaScript syntax, all frontend tests and the backend suite. See the [root setup guide](../README.md) for fresh-clone prerequisites and the Git workflow.

JavaScript syntax checks:

    Get-ChildItem frontend/src -Filter '*.js' | ForEach-Object { node --check $_.FullName }

The DOM integration test exercises route changes, filters, paging, form validation, donor history, inventory transitions, report generation, notifications, demo administration, recovery metadata and permission denial. Contrast tests check eight text/background pairs. See QA.md for results and remaining verification limits.

## Structure

- index.html: public/staff shells, navigation, dialogs and notifications.
- css/styles.css: shared tokens, controls and original staff layouts.
- css/experience.css: hero, public pages, expanded operational layouts and responsive navigation.
- src/app.js: stable entry point.
- src/main.js: routing, event wiring, workflow coordination and status feedback.
- src/model.js: shared in-memory state, validation, calculations, mutations and permissions.
- src/public.js and public-data.js: public pages and explicit public view fields.
- src/views.js: overview and donor screens.
- src/operations.js: inventory, reports, notifications and administrative screens.
- src/forms.js: reusable workflow dialogs.
- src/components.js and ui.js: reusable presentation helpers, escaping and icons.
- src/data.js: original sample fixtures and blood-type options.
- tests/: model, contrast and DOM integration checks.

Existing images are preserved. Nothing sends donor information, stores records in localStorage, contacts an SMS service, or connects to a database. Downloaded CSV files remain on the user's computer. All demo changes and the selected role reset on reload.


## Backend-connected workspace

Real authentication and persisted donor records are now available at http://127.0.0.1:8000/workspace after backend setup. See backend/README.md. The static index.html remains a separate fictional demonstration. Its stylesheets are in css/. The connected entry uses src/connected.js and src/api.js and never imports the demo data model.


## Frontend Phase 2

The connected workspace now includes an API-backed overview, improved donor registry and profiles, review-before-save forms, explicit conflict comparison, audit details, account access refresh, and mobile layouts. See PHASE-2.md for the screen tree, behavior, source files and verification. No database migration is required for this frontend upgrade.
