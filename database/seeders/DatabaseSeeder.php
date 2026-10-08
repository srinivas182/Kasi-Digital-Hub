<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Database\Seeders\ConsentDocumentSeeder;
use Modules\Core\Database\Seeders\GeographySeeder;

/**
 * Reference data every environment needs. Demo data lives in DemoSeeder and only
 * runs when demo mode is on.
 */
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([ConsentDocumentSeeder::class, GeographySeeder::class]);

        if (config('kasi.demo.enabled') === true) {
            $this->call(DemoSeeder::class);
        }
    }
}
