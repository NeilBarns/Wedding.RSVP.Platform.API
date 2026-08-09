<?php

namespace Tests\Unit;

use App\Support\E2eDatabaseSafetyGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class E2eDatabaseSafetyGuardTest extends TestCase
{
    #[DataProvider('safeDatabases')]
    public function test_it_accepts_explicit_e2e_databases(string $database): void
    {
        E2eDatabaseSafetyGuard::assertSafe('e2e', 'mysql', $database);
        $this->addToAssertionCount(1);
    }

    public static function safeDatabases(): array
    {
        return [['wedding_rsvp_e2e'], ['myapp_E2E']];
    }

    #[DataProvider('unsafeConfigurations')]
    public function test_it_rejects_unsafe_configurations(string $environment, ?string $database): void
    {
        $this->expectException(RuntimeException::class);
        E2eDatabaseSafetyGuard::assertSafe($environment, 'mysql-primary', $database);
    }

    public static function unsafeConfigurations(): array
    {
        return [
            'wrong environment' => ['testing', 'wedding_rsvp_e2e'],
            'development database' => ['e2e', 'wedding_rsvp'],
            'PHPUnit database' => ['e2e', 'wedding_rsvp_testing'],
            'production database' => ['e2e', 'production'],
            'blank database' => ['e2e', ''],
            'null database' => ['e2e', null],
        ];
    }

    public function test_failure_identifies_connection_database_and_rule(): void
    {
        try {
            E2eDatabaseSafetyGuard::assertSafe('e2e', 'mysql-primary', 'wedding_rsvp');
            $this->fail('The unsafe database should have been rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('mysql-primary', $exception->getMessage());
            $this->assertStringContainsString('wedding_rsvp', $exception->getMessage());
            $this->assertStringContainsString('must contain "e2e"', $exception->getMessage());
        }
    }
}
