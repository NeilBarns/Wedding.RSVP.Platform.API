<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabaseSafetyGuard;

class TestDatabaseSafetyGuardTest extends TestCase
{
    #[DataProvider('safeConfigurations')]
    public function test_it_accepts_explicit_testing_databases(string $driver, string $database): void
    {
        TestDatabaseSafetyGuard::assertSafe('testing', 'test-connection', $driver, $database);

        $this->addToAssertionCount(1);
    }

    public static function safeConfigurations(): array
    {
        return [
            'testing suffix' => ['mysql', 'wedding_rsvp_testing'],
            'test suffix' => ['mysql', 'wedding_rsvp_test'],
            'mixed case marker' => ['pgsql', 'Wedding_RSVP_TeStInG'],
            'SQLite memory' => ['sqlite', ':memory:'],
            'test-specific SQLite file' => ['sqlite', '/tmp/wedding_test.sqlite'],
        ];
    }

    #[DataProvider('unsafeDatabases')]
    public function test_it_rejects_unsafe_database_names(string $driver, ?string $database): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsafe test database configuration detected');

        TestDatabaseSafetyGuard::assertSafe('testing', 'test-connection', $driver, $database);
    }

    public static function unsafeDatabases(): array
    {
        return [
            'development database' => ['mysql', 'wedding_rsvp'],
            'blank database' => ['mysql', ''],
            'null database' => ['mysql', null],
            'production-like database' => ['pgsql', 'production'],
            'ambiguous SQLite path' => ['sqlite', '/var/data/wedding.sqlite'],
        ];
    }

    public function test_it_rejects_a_non_testing_application_environment(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Refusing to run tests because APP_ENV is not "testing".');

        TestDatabaseSafetyGuard::assertSafe('local', 'mysql', 'mysql', 'wedding_rsvp_testing');
    }

    public function test_failure_identifies_connection_database_and_safety_rule(): void
    {
        try {
            TestDatabaseSafetyGuard::assertSafe('testing', 'mysql-primary', 'mysql', 'wedding_rsvp');
            $this->fail('The unsafe database should have been rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('mysql-primary', $exception->getMessage());
            $this->assertStringContainsString('wedding_rsvp', $exception->getMessage());
            $this->assertStringContainsString('must contain "test"', $exception->getMessage());
        }
    }
}
