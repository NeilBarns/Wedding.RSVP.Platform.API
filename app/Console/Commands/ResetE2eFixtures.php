<?php

namespace App\Console\Commands;

use App\Support\E2eDatabaseSafetyGuard;
use Database\Seeders\E2E\E2EFixtureBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

class ResetE2eFixtures extends Command
{
    protected $signature = 'e2e:reset {--json : Output only the machine-readable fixture summary}';

    protected $description = 'Safely rebuild the disposable E2E database and create deterministic fixtures';

    public function handle(E2EFixtureBuilder $fixtures): int
    {
        try {
            E2eDatabaseSafetyGuard::assertApplicationIsSafe(app());
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $exitCode = Artisan::call('migrate:fresh', ['--force' => true, '--no-interaction' => true]);
        if ($exitCode !== self::SUCCESS) {
            $this->error('The E2E database schema could not be rebuilt.');

            return self::FAILURE;
        }

        $summary = $fixtures->build();
        if ($this->option('json')) {
            $this->line((string) json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->info('Disposable E2E fixtures were reset successfully.');
            $this->line('Admin: '.$summary['admin']['email']);
            $this->line('Wedding: '.$summary['wedding']['status']);
            $this->line('Fresh invitation: '.$summary['invitations']['fresh']['url']);
            $this->line('Submitted invitation: '.$summary['invitations']['submitted']['url']);
            $this->line('Locked invitation: '.$summary['invitations']['locked']['url']);
        }

        return self::SUCCESS;
    }
}
