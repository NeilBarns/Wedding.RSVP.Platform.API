<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\InitialOwnerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class InitialOwnerSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_both_owners_idempotently_from_configuration(): void
    {
        Config::set('initial_owners.neil.email', 'neil@example.test');
        Config::set('initial_owners.neil.password', 'neil-test-password');
        Config::set('initial_owners.hazel.email', 'hazel@example.test');
        Config::set('initial_owners.hazel.password', 'hazel-test-password');

        $this->seed(InitialOwnerSeeder::class);
        $this->seed(InitialOwnerSeeder::class);

        $this->assertDatabaseCount('users', 2);

        $neil = User::query()->where('email', 'neil@example.test')->sole();
        $hazel = User::query()->where('email', 'hazel@example.test')->sole();

        $this->assertSame('Neil Barnedo', $neil->name);
        $this->assertSame('Hazel Elago', $hazel->name);
        $this->assertTrue($neil->isOwner());
        $this->assertTrue($hazel->isOwner());
        $this->assertTrue(Hash::check('neil-test-password', $neil->password));
        $this->assertTrue(Hash::check('hazel-test-password', $hazel->password));
    }

    public function test_missing_or_blank_credentials_are_skipped(): void
    {
        Log::spy();
        Config::set('initial_owners.neil.email', '');
        Config::set('initial_owners.neil.password', '');
        Config::set('initial_owners.hazel.email', null);
        Config::set('initial_owners.hazel.password', null);

        $this->seed(InitialOwnerSeeder::class);

        $this->assertDatabaseCount('users', 0);
        Log::shouldHaveReceived('warning')->twice();
    }
}
