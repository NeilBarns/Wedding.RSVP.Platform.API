<?php

namespace App\Support;

use Illuminate\Foundation\Application;
use RuntimeException;

class E2eDatabaseSafetyGuard
{
    public static function assertApplicationIsSafe(Application $app): void
    {
        $connection = (string) $app['config']->get('database.default', '');
        $database = $app['config']->get("database.connections.$connection.database");

        self::assertSafe(
            $app->environment(),
            $connection,
            is_string($database) ? $database : null,
        );
    }

    public static function assertSafe(string $environment, string $connection, ?string $database): void
    {
        if ($environment !== 'e2e') {
            throw new RuntimeException('Refusing to reset E2E fixtures because APP_ENV is not "e2e".');
        }

        $detected = trim((string) $database);
        if ($detected === '' || ! str_contains(strtolower($detected), 'e2e')) {
            $display = $detected === '' ? '(blank)' : $detected;

            throw new RuntimeException(
                "Unsafe E2E database configuration detected. Connection \"$connection\" is configured to use \"$display\". "
                .'The database name must contain "e2e". Refusing to reset fixtures.'
            );
        }
    }
}
