<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Rebuilds the demo environment from scratch so every demo starts from the same story.
 *
 * Refuses to run unless demo mode is enabled, and never in production.
 */
final class DemoResetCommand extends Command
{
    protected $signature = 'kasi:demo:reset {--force : Skip the confirmation prompt}';

    protected $description = 'Wipe and re-seed the demo environment with the connected demo stories';

    public function handle(): int
    {
        if (config('kasi.demo.enabled') !== true || app()->isProduction()) {
            $this->components->error('Demo reset is only available when KASI_DEMO_MODE=true outside production.');

            return self::FAILURE;
        }

        if (! (bool) $this->option('force') && ! $this->confirm('This deletes ALL data in this environment and reloads demo data. Continue?')) {
            return self::SUCCESS;
        }

        $this->call('migrate:fresh', ['--force' => true]);
        $this->call('db:seed', ['--class' => 'Database\\Seeders\\DemoSeeder', '--force' => true]);

        $this->components->info('Demo environment reset.');

        return self::SUCCESS;
    }
}
