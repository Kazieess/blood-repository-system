# Frontend verification

Review date: 18 November 2026.

## Connected improvement checks

- 59 backend tests pass with 510 assertions, including the real-HTTP session/CSRF flow, assigned access, donation counts, controlled stock/expiry, recipient alerts, restricted encrypted clinical history and actual SQLite recovery/tamper checks.
- 45 frontend tests pass. The new connected operations scenario covers review-before-write, uncertain retries, unsaved entries, Philippine time conversion, stock verification, assignment grants, catalog/results, independent review, alert acknowledgement, isolated restore confirmation and clearing on sign-out.
- Regression checks also cover late private responses after sign-out/navigation, public-count expiry during refresh, publication retry conflicts, revoked administrator/password sessions and rejection of file-backed SQLite test targets.
- New screens and modules use the existing same-origin authenticated workspace and contain no demo-store imports.
- Native/browser automation hosts were unavailable; pixel/device/screen-reader review remains outstanding. MySQL and production recovery are not verified.

## Historical foundation and Phase 2 checks

- All JavaScript modules pass `node --check`.
- 37 automated checks pass using `node --test frontend/tests/*.test.mjs`.
- Nine behavior tests cover role restrictions, input validation, donor corrections, explicit assessments, donation-history reconciliation, duplicate prevention, inventory verification and terminal transitions, public snapshot separation, report date filters, CSV safety and escaped markup.
- Eight contrast tests verify selected text pairs at 4.5:1 or better.
- One demo DOM integration test exercises the actual event handlers and rendered pages from public home through donor workflows, inventory, reports, notification simulation, user administration, recovery, settings, role denial, error retry and exit.
- A separate connected-workspace DOM test covers sign-in, server-backed overview, review-before-save, stale-version comparison, retained drafts, unchanged-key registration retry, URL filters, audit inspection, permission refresh, expired sessions and logout.
- Five API-client checks cover CSRF cookies, structured validation, safe service errors, request deadlines and navigation cancellation. Seven workspace-view checks cover server totals, permission-aware states, filtering and pagination, safe output and Philippine-time rollover. Two asset checks cover relocated stylesheets and the connected dependency boundary.
- JavaScript syntax checks pass. The staff entry and all new connected stylesheets/modules return HTTP 200 from Laravel.
- The existing Laravel asset feature test passes with 13 assertions. No PHP or database change was required for Phase 2.

Happy DOM 21.0.4 is now a locked root development dependency installed with `npm.cmd ci --ignore-scripts --no-audit --no-fund`. It is not a runtime dependency or part of the served frontend. The root `npm.cmd run test:all` entry and GitHub workflow provide repeatable checks; a remote CI run must be verified separately after publication.

## Manual review still needed

DOM tests do not render pixels. A visual browser review at 360px, 768px, 1024px and 1440px, 200% zoom, keyboard-only operation, dialog focus restoration, screen-reader announcements, touch use and slow connections remains necessary. CSS provides responsive behavior; that is not equivalent to evidence from each target device. Do not claim a pixel-perfect or formally accessibility-certified result.

Suggested review: public hero and navigation; locator normal/stale/unavailable; demo sign-in; all permitted staff routes; donor/stock dialogs; wide-table scrolling; mobile More navigation; report CSV download; browser back/forward; missing records; native invalid-field feedback; reduced motion. For the connected workspace, review mobile record cards, two-step dialogs, conflict choices, discard confirmation, account access refresh and long names/references.

## Intentional limits

The static `index.html` remains a fictional demo whose records reset on reload. The separate Laravel `/workspace` entry uses real cookie authentication, server authorization, persisted donor profiles and restricted audit access. Its frontend scope is documented in `PHASE-2.md`. Recorded clinical information, inventory, shortage jobs and local SQLite isolated recovery are now connected as described in IMPROVEMENTS.md. External SMS, official public stock and production recovery remain separate work. Passing DOM tests does not establish production readiness.

## Backend foundation verification

The backend-connected workspace is available at http://127.0.0.1:8000/workspace after local account provisioning. It does not load the demo store. See backend/README.md and docs/backend/FOUNDATION.md.

Earlier checks for the backend foundation (before this frontend milestone):

- 22 Laravel tests pass with 141 assertions, including actual HTTP cookies, CSRF protection, login/logout, persistence, permissions, idempotency, concurrency checks and asset delivery.
- 19 frontend checks pass, including the new connected-client DOM workflow for API errors, validation, successful saves, stale-version conflicts and logout.
- JavaScript syntax checks pass. CSS, connected modules, staff entry and health return HTTP 200.
- Database verification is SQLite only. No MySQL result or production readiness is claimed.
- On Windows, the real-HTTP test needs permission to clean up its temporary process tree; the sandbox's taskkill restriction can hang cleanup. It was run successfully outside the sandbox. The HTTP test uses its own temporary database and stops its own server.

The two existing stylesheet files now live in css/, and both static and backend-served entries reference that location.
