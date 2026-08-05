<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class InitialOwnerSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('initial_owners') as $key => $owner) {
            if (blank($owner['email']) || blank($owner['password'])) {
                Log::warning("Initial owner [{$key}] was not seeded because credentials are missing.");

                continue;
            }

            User::query()->updateOrCreate(
                ['email' => $owner['email']],
                [
                    'name' => $owner['name'],
                    'password' => $owner['password'],
                    'role' => User::ROLE_OWNER,
                ],
            );
        }
    }
}
