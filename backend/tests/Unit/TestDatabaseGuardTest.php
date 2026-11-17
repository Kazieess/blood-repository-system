<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class TestDatabaseGuardTest extends TestCase
{
    public function test_direct_phpunit_rejects_file_database_before_migrations(): void
    {
        $backend = dirname(__DIR__, 2);
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'repository-guard-'.bin2hex(random_bytes(8)).'.sqlite';
        $process = new Process([PHP_BINARY, 'vendor/bin/phpunit', '--configuration=phpunit.xml', '--filter=test_unauthenticated_requests_are_safe_json', '--no-progress'], $backend, [
            'APP_ENV' => 'testing', 'APP_CONFIG_CACHE' => $path.'.config',
            'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $path, 'DB_URL' => '',
        ]);
        $process->setTimeout(30);
        $process->run();
        $this->assertFalse($process->isSuccessful());
        $this->assertStringContainsString('SQLite tests require an in-memory database before migrations', $process->getOutput().$process->getErrorOutput());
        $this->assertFileDoesNotExist($path);
    }
}
