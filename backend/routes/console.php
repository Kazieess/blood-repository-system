<?php

use App\Actions\BackupStore;
use App\Actions\StockEvaluator;
use App\Models\BackupRun;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('stock:evaluate', function (): void {
    app(StockEvaluator::class)->evaluate();
    $this->info('Stock conditions reconciled.');
})->purpose('Reconcile configured stock alerts, including time-driven expiry');

Artisan::command('repository:scheduler-heartbeat', function (): void {
    Cache::put('repository.scheduler_heartbeat', time(), 600);
})->purpose('Record a private scheduler heartbeat');
Schedule::command('repository:scheduler-heartbeat')->everyMinute()->withoutOverlapping();

Schedule::command('stock:evaluate')->everyMinute()->withoutOverlapping();

Artisan::command('repository:backup', function (): int {
    try {
        $run = app(BackupStore::class)->create(null, (string) Str::uuid(), 'Scheduled technical snapshot', (string) Str::uuid());
        $this->info('Encrypted snapshot completed: '.$run->id);

        return 0;
    } catch (Throwable $exception) {
        $this->error('Backup failed. Check configuration, adapter support and private run status.');

        return 1;
    }
})->purpose('Create an encrypted local SQLite snapshot');

Artisan::command('repository:restore-copy {backup}', function (): int {
    try {
        $result = app(BackupStore::class)->restoreCopy(BackupRun::findOrFail($this->argument('backup')), null, (string) Str::uuid());
        $this->info('Verified isolated copy: '.$result['copy_id']);

        return 0;
    } catch (Throwable $exception) {
        $this->error('Recovery verification failed. The running database was not replaced.');

        return 1;
    }
})->purpose('Restore and verify a new isolated copy without replacing the running database');

$backupAt = config('repository.backup_at');
if (is_string($backupAt) && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $backupAt)) {
    Schedule::command('repository:backup')->dailyAt($backupAt)->timezone('Asia/Manila')->withoutOverlapping();
}
