param(
    [ValidateSet('setup', 'serve', 'test', 'user', 'schedule')][string]$Action = 'serve',
    [ValidateRange(1, 65535)][int]$Port = 8000
)
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$runtimePath = Join-Path $projectRoot '.tools/php84/php.exe'
if (-not (Test-Path -LiteralPath $runtimePath)) {
    $runtimeCommand = Get-Command php -ErrorAction SilentlyContinue
    if (-not $runtimeCommand) { throw 'PHP 8.3+ is required. See backend/README.md.' }
    $runtimePath = $runtimeCommand.Source
}
$runtimeDetails = @(& $runtimePath -r 'echo PHP_VERSION_ID, PHP_EOL; echo implode(PHP_EOL, get_loaded_extensions());')
if ($LASTEXITCODE -ne 0 -or $runtimeDetails.Count -eq 0) { throw 'PHP could not start. Check the configured runtime.' }
$runtimeVersion = $runtimeDetails[0]
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
        'serve' {
            if (-not (Test-Path -LiteralPath 'vendor/autoload.php')) { throw 'Install locked Composer dependencies first; see backend/README.md.' }
            if (-not (Test-Path -LiteralPath '.env')) { throw 'Run the setup action once before starting the system; see frontend/TESTING.md.' }
            $listeners = [Net.NetworkInformation.IPGlobalProperties]::GetIPGlobalProperties().GetActiveTcpListeners()
            if ($listeners | Where-Object { $_.Port -eq $Port }) { throw "Port $Port is already in use. If your project server is running, open http://127.0.0.1:$Port/workspace. Otherwise choose -Port 8001; do not stop unrelated processes." }
            $serverOptions = @()
            $opcacheAvailable = $runtimeDetails -contains 'Zend OPcache'
            $opcachePath = Join-Path (Split-Path -Parent $runtimePath) 'ext/php_opcache.dll'
            if (-not $opcacheAvailable -and (Test-Path -LiteralPath $opcachePath)) {
                $serverOptions += @('-d', "zend_extension=$opcachePath")
                $opcacheAvailable = $true
            }
            if ($opcacheAvailable) {
                $serverOptions += @('-d', 'opcache.enable_cli=1', '-d', 'opcache.validate_timestamps=1', '-d', 'opcache.revalidate_freq=0')
            }
            Write-Host "Starting local server at http://127.0.0.1:$Port"
            Write-Host "Staff workspace: http://127.0.0.1:$Port/workspace"
            Write-Host 'Keep this terminal open. Press Ctrl+C to stop. Restart after changing .env.'
            & $runtimePath @serverOptions -S "127.0.0.1:$Port" -t (Join-Path $backendPath 'public') (Join-Path $backendPath 'server.php')
            if ($LASTEXITCODE -ne 0 -and $LASTEXITCODE -ne -1073741510) { throw 'The local server stopped with an error. Check the message above.' }
        }
        'schedule' { & $runtimePath artisan schedule:work --no-interaction }
        'test' { & $runtimePath artisan test; if ($LASTEXITCODE) { throw 'Backend tests failed.' } }
        'user' { & $runtimePath artisan app:create-user; if ($LASTEXITCODE) { throw 'Account creation failed.' } }
    }
} finally { Pop-Location }
