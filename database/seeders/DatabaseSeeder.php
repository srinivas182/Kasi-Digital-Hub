<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Base seeder: reference data every environment needs (added from S3).
 * Demo data lives in DemoSeeder and only runs in demo environments.
 */
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (config('kasi.demo.enabled') === true) {
            $this->call(DemoSeeder::class);
        }
    }
}
