<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use LogicException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $connection = $app->make('db')->connection();

        if (! $app->environment('testing')
            || $connection->getDriverName() !== 'pgsql'
            || $connection->getDatabaseName() !== 'saas_test_testing') {
            throw new LogicException(
                'Tests require the dedicated PostgreSQL database saas_test_testing. ' .
                'Check .env.testing and clear cached configuration.'
            );
        }

        return $app;
    }
}
