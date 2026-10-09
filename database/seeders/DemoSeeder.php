<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\Modules\ModuleRegistry;
use Illuminate\Database\Seeder;
use Modules\Core\Search\SearchService;

/**
 * Orchestrates demo data for the whole platform.
 *
 * Each module lists its demo seeders in `module.json` (`demo_seeders`). They run
 * in module dependency order so the connected demo stories (the same people
 * moving across portals) build up correctly.
 */
final class DemoSeeder extends Seeder
{
    public function run(ModuleRegistry $registry): void
    {
        foreach ($registry->enabled() as $module) {
            foreach ($module->demoSeeders as $seeder) {
                $this->call($seeder);
            }
        }

        // Make the demo content findable straight away.
        app(SearchService::class)->reindex();
    }
}
