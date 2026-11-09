# Frontend Phase 2: connected staff workspace

Implemented on 6 November 2026. Here, “frontend Phase 2” means the second interface milestone following the initial demo and backend foundation. It is separate from the proposal's calendar phase named “Design”.

## Delivered

- An authenticated overview using existing API totals and recent records. Counts reflect the user's permitted scope and do not extrapolate from a visible page. No clinical or inventory statistics are invented.
- A donor registry with server search, blood-type filters, 10/25/50-row page sizes, filter chips, URL-based state and pagination recovery. Mobile record cards complement desktop tables.
- A donor profile with human-readable administrative state, timestamps in Philippine time, saved record version, full reference and a user-initiated copy action. Correction controls follow the resource's server-provided permission.
- Registration and correction dialogs with field feedback, a review step, explicit save, unsaved-entry discard confirmation, duplicate-submit prevention and retained drafts after service errors.
- Conflict recovery: preserve a rejected correction draft, retrieve the latest record, select saved or draft values for each field, and review again using the retrieved version. The interface never silently advances the version and retries an overwrite.
- Registration retries retain their idempotency key. An uncertain network result is not called a successful save, and writes are never retried automatically.
- Audit action shortcuts, exact action/reference/request search, pagination and an inspect dialog with actor, time, field names, reason and request reference. Hidden dialog content is cleared on close.
- Account details, permission refresh, a workspace guide, password visibility, useful sign-in errors, deep-link return after sign-in, sign-out and expired-session clearing.
- Fifteen-second request deadlines, route cancellation, stale-response suppression, offline/online feedback, loading/error/empty/denied states, visible feedback and reduced-motion support.

## Use it

For a complete local walkthrough, account setup, manual checklists, automated commands and troubleshooting, see [Local testing guide](TESTING.md).

From the workspace root, run:

    .\scripts\backend.ps1 serve

Open http://127.0.0.1:8000/workspace. If no local staff account exists, provision it first with `scripts/backend.ps1 user`. This connected entry requires Laravel; VS Code Live Server can serve the original demo but cannot provide the authenticated API.

## Screen tree

    Staff sign-in
    ├── Overview: accessible totals and recent records
    ├── Donor registry: search, filters and page size
    │   ├── Register → review → save
    │   └── Donor profile → correct → review → save
    │       └── Conflict → load latest → compare → review again
    ├── Audit trail → inspect activity
    ├── Workspace guide / account access
    └── Account details → refresh access / sign out

## Source and compatibility

`src/connected.js` remains the stable entry, delegating to `src/workspace.js`. Page presentation is in `src/workspace-ui.js`; donor editing helpers are in `src/workspace-forms.js`. `css/workspace.css` is scoped to the connected entry, preserving the existing demo styles and routes.

`src/api.js` keeps its existing methods and adds an optional request timeout. No backend endpoint, schema, permission, medical rule or database migration changed. The connected dependency graph is checked to ensure it does not import the fictional donor store. No donor records or drafts are stored in localStorage or sessionStorage.

## Acceptance and verification

All 33 frontend tests pass. The suite verifies the review step before any write, local field errors, preserved drafts, explicit conflict choices, unchanged-key registration retry, URL filters, permission refresh, expired-session clearing, old-response suppression, timestamps across Philippine midnight, safe output escaping, API deadlines, control asset paths and demo-store separation. JavaScript syntax checks pass. The Laravel asset feature test passes with 13 assertions, and the workspace entry plus connected assets return HTTP 200.

Run:

    node --test frontend/tests/*.test.mjs

JavaScript syntax checks:

    Get-ChildItem frontend/src -Filter '*.js' | ForEach-Object { node --check $_.FullName }

The existing Happy DOM development tool is used for DOM integration checks; no new runtime packages are required. DOM checks are not pixel rendering. Visual browser, screen-reader, touch and real-device review remain separate checks; formal accessibility conformance is not claimed.

## Deliberate boundary

This milestone does not implement clinical assessments, donation history, inventory, live public availability, reports, SMS, account editing or password delivery. Those interfaces remain illustrative in the separate prototype until their APIs and approved rules exist. The current connected backend uses the already documented local SQLite configuration; no MySQL result is implied.
