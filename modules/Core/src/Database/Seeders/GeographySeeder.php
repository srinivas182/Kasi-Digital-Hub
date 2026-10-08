<?php

declare(strict_types=1);

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Structure\Geography;

/**
 * Reference data for every environment: all 9 provinces, the 8 metros and the pilot
 * municipalities (Limpopo, Gauteng, KwaZulu-Natal).
 *
 * The pilot list is curated by MCS with approximate centre points. Before the national
 * roll-out, load the full official list with:
 *   php artisan kasi:geography:import <MDB csv> --source="MDB <year>"
 */
final class GeographySeeder extends Seeder
{
    public const SOURCE = 'MCS curated pilot list 2026-10 (approximate centres - verify against MDB)';

    public function run(Geography $geography): void
    {
        $geography->seedProvinces();
        $geography->importCsv(dirname(__DIR__, 3).'/database/geography/pilot-municipalities.csv', self::SOURCE);
    }
}
