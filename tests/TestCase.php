<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        // Fail before RefreshDatabase can migrate anything if an operator left
        // production configuration cached or supplied a database URL override.
        if ($app->configurationIsCached() || ! $app->environment('testing') ||
            $app['config']->get('database.default') !== 'sqlite' ||
            $app['config']->get('database.connections.sqlite.database') !== ':memory:' ||
            $app['config']->get('database.connections.sqlite.url')) {
            throw new \LogicException('Tests require uncached testing configuration and in-memory SQLite without DB_URL. Run php artisan config:clear first.');
        }

        return $app;
    }
}
