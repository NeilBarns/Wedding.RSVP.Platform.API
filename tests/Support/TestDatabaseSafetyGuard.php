<?php

namespace Tests\Support;

use Illuminate\Foundation\Application;
use RuntimeException;

class TestDatabaseSafetyGuard
{
    public static function assertApplicationIsSafe(Application $app): void
    {
        $connection = (string) $app['config']->get('database.default', '');
        $driver = (string) $app['config']->get("database.connections.$connection.driver", '');
        $database = $app['config']->get("database.connections.$connection.database");

        self::assertSafe(
            $app->environment(),
            $connection,
            $driver,
            is_string($database) ? $database : null,
        );
    }

    public static function assertSafe(
        string $environment,
        string $connection,
        string $driver,
        ?string $database,
    ): void {
        if ($environment !== 'testing') {
            throw new RuntimeException('Refusing to run tests because APP_ENV is not "testing".');
        }

        $detected = trim((string) $database);
        $isSqliteMemory = strtolower($driver) === 'sqlite' && $detected === ':memory:';
        $hasTestMarker = str_contains(strtolower($detected), 'test');

        if ($detected === '' || (! $isSqliteMemory && ! $hasTestMarker)) {
            $display = $detected === '' ? '(blank)' : $detected;

            throw new RuntimeException(
                "Unsafe test database configuration detected. Connection \"$connection\" is configured to use \"$display\", "
                .'which does not appear to be a testing database. Database names or SQLite paths must contain "test", '
                .'or SQLite must use ":memory:". Refusing to continue.'
            );
        }
    }
}
