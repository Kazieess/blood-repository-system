<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $driver = config('database.default');
        if (! $app->environment('testing') || ! in_array($driver, ['sqlite', 'mysql'], true) || filled(config('database.connections.'.$driver.'.url'))) {
            throw new \RuntimeException('Tests require explicit isolated database settings without a database URL.');
        }
        if ($driver === 'sqlite' && config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('SQLite tests require an in-memory database before migrations. Use the documented test runner.');
        }
        if ($driver === 'mysql') {
            if (getenv('REPOSITORY_MYSQL_TEST') !== 'isolated' || config('database.connections.mysql.database') !== 'blood_repository_ci') {
                throw new \RuntimeException('MySQL tests require the explicitly isolated blood_repository_ci database.');
            }
        }

        return $app;
    }
}
