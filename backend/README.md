# Blood Repository backend foundation

Laravel 13.40.0 with Sanctum 4.3.3. Verified locally using the workspace PHP 8.4.27 runtime. This milestone implements real cookie-session authentication, action-level permissions, scoped donor CRUD, retry-safe registration, optimistic correction checks and transactional audit records.

## Start here

From the workspace root in PowerShell:

    .\scripts\backend.ps1 setup
    .\scripts\backend.ps1 user
    .\scripts\backend.ps1 serve

If PowerShell blocks scripts under your current execution policy, run the same action in a process-scoped invocation:

    powershell -ExecutionPolicy Bypass -File scripts/backend.ps1 -Action setup

Setup creates a local .env only if missing, generates an application key only if blank, and runs migrations plus role seeds. It does not replace an existing database. The user command asks for a name, email, role and a hidden password; passwords are never command arguments or printed output. Choose Red Cross Administrator to work with donors, or System Administrator for audit-only access in this milestone. No default user or shared password is seeded.

Open http://127.0.0.1:8000/workspace to sign in. The public frontend prototype is at http://127.0.0.1:8000/. The original independent static demo can still run on port 8080 or VS Code Live Server.

The connected entry uses frontend/connected.html and src/connected.js. It requests real API data and does not import the fictional frontend model. Static Live Server cannot provide this backend; use port 8000 for actual sign-in and persisted records.

## Database status

The runnable development database is SQLite at database/database.sqlite. It is ignored by Git and contains only role seeds until you provision an account and add records. MySQL remains the intended production engine. It has NOT yet been tested against an approved MySQL service.

For a dedicated MySQL development database, update the ignored .env with DB_CONNECTION=mysql and your DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME and DB_PASSWORD. Use a restricted application account and a separate test database. Then run migrations and tests against that engine before claiming MySQL compatibility. Do not point tests at production.

The supplied .env.example documents local settings and MySQL variables. Production also requires approved hosting, HTTPS, SESSION_SECURE_COOKIE=true, APP_ENV=production, APP_DEBUG=false, protected secrets and an appropriate backup/recovery procedure. The first milestone is not production readiness.

## Runtime and dependencies

XAMPP was left unchanged. The verified portable runtime is in ../.tools/php84 and is ignored by Git. On another computer use PHP 8.3+ with curl, fileinfo, mbstring, OpenSSL, PDO MySQL, PDO SQLite, SQLite3 and ZIP extensions. Composer uses its lockfile; do not install with XAMPP PHP 8.2.

This machine had a pre-existing COMPOSER environment variable pointing at a system directory. For dependency commands from backend, override it only in the current shell:

    $env:COMPOSER = 'composer.json'
    & '..\.tools\php84\php.exe' 'C:\ProgramData\ComposerSetup\bin\composer.phar' install

Laravel Boost is development-only, installed because the generated backend AGENTS.md required its setup. No external MCP access was configured. Follow the generated backend guidance when editing PHP.

## API and security behavior

- Initialize /sanctum/csrf-cookie before login. The browser client sends the decoded XSRF-TOKEN cookie in X-XSRF-TOKEN for writes.
- Login, logout and current user: /api/v1/auth/login, /api/v1/auth/logout, /api/v1/me.
- Donors: GET/POST /api/v1/donors and GET/PATCH /api/v1/donors/{uuid}.
- Restricted activity: GET /api/v1/audit-logs.
- Minimal health: GET /api/v1/health.
- Donor creation requires a UUID Idempotency-Key header. Reusing the same actor/key and original input returns the existing record; changed input returns 409.
- Corrections require the current version and a reason. A stale version returns 409 rather than overwriting a newer record.
- Coordinators can access only records they created. This is a conservative proposed scope pending barangay access approval.
- Every protected request checks that the account is still active. Technical administrators do not automatically receive donor access.
- API errors return safe messages, field errors where appropriate and a request ID. Logs omit request bodies, donor values, credentials and SQL exception text.
- Donor changes and audit writes commit together. There are no delete endpoints. The audit API is read-only; production database privileges and external log retention still need design.

The API contract is docs/backend/openapi.yaml at the workspace root. The initial field dictionary, role matrix, assumptions and acceptance criteria are in docs/backend/FOUNDATION.md.

## Verification

From the workspace root:

    .\scripts\backend.ps1 test
    npm.cmd test

For the reproducible root workflow, install frontend development dependencies with `npm.cmd ci --ignore-scripts --no-audit --no-fund` and use `npm.cmd run test:all`. This entry checks JavaScript syntax, frontend behavior and the backend suite with explicit isolated test settings. See [root setup and Git instructions](../README.md).

The backend suite uses isolated SQLite databases. Tests cover authentication, logout, inactive accounts, throttling, record ownership, permissions, safe errors, pagination, validation, idempotency, stale corrections, audit rollback, audit redaction and relocated frontend assets. An additional real-HTTP integration test starts its own temporary server/database to check CSRF cookies, login, persistence, assets and logout; it stops that test server afterwards.

For PHP formatting without a Git repository, run Pint against the edited source paths. The generated --dirty instruction requires Git and is not available until version control is initialized.

## Remaining scope

Clinical eligibility and deferrals, donation history, inventory operations, public production snapshots, reports/exports, account-management UI, password reset delivery, SMS, scheduled backups and real recovery jobs are not implemented by this backend milestone. The frontend demo illustrates those later features. Only fictional data should be used until stakeholder fields, privacy, scope and retention are approved.

Recorded local baseline: 22 backend tests (141 assertions) and 33 frontend checks passed. The real-HTTP test requires normal Windows process-cleanup permissions; inside a restricted sandbox, run it with the appropriate approval so taskkill can stop its temporary server. A fresh run's output is the evidence for that run.
