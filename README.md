# Blood Repository System

Davao del Sur Blood Repository and Donor Monitoring System: an independent interface demo and a Laravel-connected staff workspace. This is a development baseline, not a production release.

The connected workspace implements cookie-session authentication, server permissions, persistent donor profiles/search/corrections and restricted audit activity. Clinical records, explicit Red Cross assignments, donation history, inventory, alerts and recovery remain planned work; see [the improvement plan](docs/IMPROVEMENT-PLAN.md).

## Reproduce the development environment

Private repository: https://github.com/Kazieess/blood-repository-system. Authorized collaborators can clone it with:

```powershell
git clone https://github.com/Kazieess/blood-repository-system.git
cd blood-repository-system
```

GitHub authentication is required for private access. The default development branch is `develop`.

Use Git, Node.js and npm, Composer 2, and PHP with curl, fileinfo, mbstring, OpenSSL, PDO SQLite, SQLite3, PDO MySQL and ZIP. The recorded runtime baseline is **Node 26.8.2, npm 11.19.1 and PHP 8.4.27**. `.node-version` and CI record that Node version. PHP dependencies require PHP 8.3+; tests on other runtime versions are not implied by the baseline.

From the cloned project root, install the locked frontend test dependencies:

```powershell
npm.cmd ci --ignore-scripts --no-audit --no-fund
```

Use `npm ci`, not `npm update`, when reproducing a run. `package-lock.json` locks Happy DOM and its transitive dependencies. Happy DOM is development-only; the browser frontend has no npm runtime dependency.

Install the locked backend dependencies with a compatible PHP/Composer installation:

```powershell
$env:COMPOSER = 'composer.json'
Push-Location backend
composer install --prefer-dist --no-interaction
Pop-Location
```

If Composer uses XAMPP PHP 8.2 on this Windows machine, use the existing portable runtime explicitly from inside `backend`:

```powershell
Push-Location backend
$env:COMPOSER = 'composer.json'
& '..\.tools\php84\php.exe' 'C:\ProgramData\ComposerSetup\bin\composer.phar' install --prefer-dist --no-interaction
Pop-Location
```

Portable runtimes and `vendor` are deliberately not committed. A fresh clone on another computer needs its own compatible PHP installation; the `C:\ProgramData` Composer path is specific to this machine. See [backend setup](backend/README.md).

To prepare the local database and application key:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File '.\scripts\backend.ps1' -Action setup
```

This creates missing local configuration and applies migrations/role seeds. It does not reset an existing database. Accounts and passwords are not seeded. For browser testing, provision your own fictional account with `-Action user` and start the server with `-Action serve`.

| URL | Purpose |
| --- | --- |
| http://127.0.0.1:8000/workspace | Authenticated connected workspace |
| http://127.0.0.1:8000/ | Independent fictional public/staff demo |

## Run checks from the project root

```powershell
npm.cmd run check
npm.cmd test
npm.cmd run test:backend
```

Or run all three in sequence:

```powershell
npm.cmd run test:all
```

The test entry uses Node's current executable, expands test filenames without shell-specific globs, and propagates failures. Backend tests prefer the ignored Windows `.tools/php84/php.exe` when available, otherwise `php` on PATH. Set `PHP_BINARY` to an absolute compatible runtime path to override that selection.

Backend tests clear cached Laravel configuration and explicitly select isolated SQLite test settings. The HTTP test creates/removes its own temporary database/server. No running development server, local staff account or existing donor records are required. Follow the setup above first to provide an application key. The Windows HTTP test needs normal process-cleanup permissions; restricted sandboxes can prevent teardown.

Current recorded baseline: **33 frontend tests and 22 backend tests / 141 assertions**. Counts are descriptive, not enforced as a substitute for passing results. Manual visual/device testing is separate; see [the full testing guide](frontend/TESTING.md).

## Git workflow and private hosting

The initial development baseline uses `develop`. Start changes on a focused branch:

```powershell
git switch develop
git switch -c feat/donation-history
git status
```

Review and stage intended files, run checks, then commit with a conventional message such as `feat(donations): add persisted donation history`. Use pull requests into `develop` when the private remote is connected. Reserve `main` for reviewed release-ready work; this local baseline does not establish production readiness.

Git tracks source, tests, lockfiles, branding assets and Markdown project requirements. It ignores `.env`, database files, dependency folders, logs, keys, exports/backups, local tooling/editor state and original course PDF/Word reference binaries. Those files remain on disk. Git is not a backup of your database or recovery secrets.

The committed GitHub Actions workflow runs frontend checks on Linux/Windows and backend tests on Linux/PHP 8.4.27 with SQLite. Actions are pinned to verified commit IDs, permissions are read-only and checkout credentials are not persisted. CI activates only after publishing to GitHub; a committed workflow alone is not a successful cloud run. No deployment job is configured. Configure branch protection/rules on the remote when available; local branch conventions alone do not enforce protection.

To inspect the local history and remote configuration:

```powershell
git log --oneline -5
git remote -v
git status
```

Never commit actual donor data, credentials or copied development databases, even to the private repository. Review staged paths and diffs before committing. [GitHub workflow](.github/workflows/tests.yml) follows the upstream [checkout](https://github.com/actions/checkout), [Node setup](https://github.com/actions/setup-node) and [PHP setup](https://github.com/shivammathur/setup-php) documentation.
