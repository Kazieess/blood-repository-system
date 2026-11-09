param([ValidateSet('setup', 'serve', 'test', 'user')][string]$Action = 'serve')
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$runtimePath = Join-Path $projectRoot '.tools/php84/php.exe'
if (-not (Test-Path -LiteralPath $runtimePath)) {
    $runtimeCommand = Get-Command php -ErrorAction SilentlyContinue
    if (-not $runtimeCommand) { throw 'PHP 8.3+ is required. See backend/README.md.' }
    $runtimePath = $runtimeCommand.Source
}
$runtimeVersion = & $runtimePath -r 'echo PHP_VERSION_ID;'
if ([int]$runtimeVersion -lt 80300) { throw 'Laravel 13 requires PHP 8.3+. Use the workspace portable runtime or install a supported PHP version.' }
$backendPath = Join-Path $projectRoot 'backend'
Push-Location -LiteralPath $backendPath
try {
    switch ($Action) {
        'setup' {
            if (-not (Test-Path -LiteralPath 'vendor/autoload.php')) { throw 'Install locked Composer dependencies first; see backend/README.md.' }
            if (-not (Test-Path -LiteralPath '.env')) { Copy-Item -LiteralPath '.env.example' -Destination '.env' }
            if (Select-String -LiteralPath '.env' -Pattern '^APP_ENV=production$' -Quiet) { throw 'This convenience setup is for development, not production.' }
            if (-not (Select-String -LiteralPath '.env' -Pattern '^APP_KEY=.+$' -Quiet)) { & $runtimePath artisan key:generate; if ($LASTEXITCODE) { throw 'Key generation failed.' } }
            if ((Select-String -LiteralPath '.env' -Pattern '^DB_CONNECTION=sqlite$' -Quiet) -and -not (Test-Path -LiteralPath 'database/database.sqlite')) { New-Item -ItemType File -Path 'database/database.sqlite' | Out-Null }
            & $runtimePath artisan migrate --seed --no-interaction
            if ($LASTEXITCODE) { throw 'Database setup failed.' }
        }
        'serve' { & $runtimePath artisan serve --host=127.0.0.1 --port=8000 }
        'test' { & $runtimePath artisan test; if ($LASTEXITCODE) { throw 'Backend tests failed.' } }
        'user' { & $runtimePath artisan app:create-user; if ($LASTEXITCODE) { throw 'Account creation failed.' } }
    }
} finally { Pop-Location }
