<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DeploymentCheck extends Command
{
    protected $signature = 'repository:deployment-check';

    protected $description = 'Read-only deployment configuration checks; never prints configuration values or secrets';

    public function handle(): int
    {
        $checks = [
            'Production environment selected' => app()->environment('production'),
            'Debug output disabled' => ! config('app.debug'),
            'HTTPS application URL' => str_starts_with((string) config('app.url'), 'https://'),
            'Secure HTTP-only session cookies' => config('session.secure') && config('session.http_only'),
            'Application key configured' => filled(config('app.key')),
            'Intended MySQL connection selected' => config('database.default') === 'mysql',
            'Storage and bootstrap cache writable' => is_writable(storage_path()) && is_writable(base_path('bootstrap/cache')),
        ];
        try {
            DB::select('SELECT 1');
            $checks['Database reachable'] = true;
            $ran = Schema::hasTable('migrations') ? DB::table('migrations')->pluck('migration')->all() : [];
            $files = array_map(fn ($file) => pathinfo($file, PATHINFO_FILENAME), glob(database_path('migrations/*.php')) ?: []);
            $checks['All application migrations applied'] = array_diff($files, $ran) === [];
        } catch (\Throwable) {
            $checks['Database reachable and migrations applied'] = false;
        }
        try {
            $last = Cache::get('repository.scheduler_heartbeat');
            $checks['Scheduler heartbeat within five minutes'] = is_numeric($last) && time() - (int) $last >= 0 && time() - (int) $last <= 300;
        } catch (\Throwable) {
            $checks['Scheduler heartbeat within five minutes'] = false;
        }
        $this->table(['Configuration check', 'Result'], collect($checks)->map(fn ($pass, $name) => [$name, $pass ? 'PASS' : 'NOT READY'])->values()->all());
        $this->line('Configuration checks only. Hosting, TLS, MySQL recovery, offsite storage, mail delivery, load and browser acceptance require separate evidence.');

        return in_array(false, array_values($checks), true) ? self::FAILURE : self::SUCCESS;
    }
}
