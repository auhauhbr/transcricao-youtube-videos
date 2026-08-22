<?php

namespace Tests;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        $expected = [
            'environment' => 'testing',
            'database connection' => 'sqlite',
            'database name' => ':memory:',
            'cache store' => 'array',
            'queue connection' => 'sync',
            'session driver' => 'array',
        ];
        $actual = [
            'environment' => $app->environment(),
            'database connection' => $app['config']->get('database.default'),
            'database name' => $app['config']->get('database.connections.sqlite.database'),
            'cache store' => $app['config']->get('cache.default'),
            'queue connection' => $app['config']->get('queue.default'),
            'session driver' => $app['config']->get('session.driver'),
        ];

        foreach ($expected as $setting => $expectedValue) {
            if ($actual[$setting] !== $expectedValue) {
                throw new RuntimeException(sprintf(
                    'Unsafe test environment: expected %s [%s], got [%s].',
                    $setting,
                    $expectedValue,
                    is_scalar($actual[$setting]) ? (string) $actual[$setting] : get_debug_type($actual[$setting]),
                ));
            }
        }

        return $app;
    }
}
