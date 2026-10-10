<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Database\Seeders\ConsentDocumentSeeder;
use Modules\Core\Database\Seeders\GeographySeeder;
use Modules\Start\Database\Seeders\StepSeeder;
use Modules\Work\Database\Seeders\OccupationSeeder;
use Modules\Work\Database\Seeders\SkillSynonymSeeder;

/**
 * Reference data every environment needs. Demo data lives in DemoSeeder and only
 * runs when demo mode is on.
 */
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([ConsentDocumentSeeder::class, GeographySeeder::class, OccupationSeeder::class, SkillSynonymSeeder::class, StepSeeder::class]);

        if (config('kasi.demo.enabled') === true) {
            $this->call(DemoSeeder::class);
        }
    }
}
