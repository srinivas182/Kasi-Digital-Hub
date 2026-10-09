<?php

declare(strict_types=1);

namespace Modules\Work\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Work\Models\OfoOccupation;

/**
 * Starter occupations (titles and OFO major groups) so employers can choose an occupation from
 * day one. Official OFO codes are added with `php artisan kasi:work:import-ofo <csv>`.
 */
final class OccupationSeeder extends Seeder
{
    public function run(): void
    {
        if (OfoOccupation::query()->exists()) {
            return;
        }

        $file = fopen(dirname(__DIR__, 3).'/database/data/ofo-starter.csv', 'rb');
        if ($file === false) {
            return;
        }
        fgetcsv($file, escape: '\\');
        while (($row = fgetcsv($file, escape: '\\')) !== false) {
            OfoOccupation::query()->create(['code' => $row[0] !== '' ? $row[0] : null, 'title' => (string) $row[1], 'major_group' => (int) $row[2], 'official' => false]);
        }
        fclose($file);
    }
}
