<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        // Test-only migrations live outside database/migrations so they never
        // touch dev/prod. Registering with the migrator before RefreshDatabase
        // runs migrate:fresh ensures the schema is in place for tests.
        $app['migrator']->path(__DIR__.'/Database/migrations');

        return $app;
    }
}
