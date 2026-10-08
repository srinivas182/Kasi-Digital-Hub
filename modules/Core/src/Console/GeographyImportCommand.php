<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use Illuminate\Console\Command;
use Modules\Core\Structure\Geography;

/**
 * Load the official municipal list (Municipal Demarcation Board / Stats SA) from CSV.
 *
 *   php artisan kasi:geography:import storage/app/mdb-municipalities-2021.csv --source="MDB 2021"
 *
 * Columns: code,name,category,province_code,district_code,latitude,longitude
 * (category: metro | district | local; district_code empty for metros and districts).
 * Existing rows are updated by code, so the import can be re-run safely.
 */
final class GeographyImportCommand extends Command
{
    protected $signature = 'kasi:geography:import {file} {--source= : Dataset name and version, e.g. "MDB 2021"}';

    protected $description = 'Import or update municipalities from the official CSV';

    public function handle(Geography $geography): int
    {
        $file = (string) $this->argument('file');
        $source = (string) ($this->option('source') ?: basename($file));

        if (! is_readable($file)) {
            $this->components->error("Cannot read {$file}.");

            return self::FAILURE;
        }

        $result = $geography->importCsv($file, $source);

        $this->components->info("Imported {$result['imported']} municipalities from {$source}.");
        foreach ($result['errors'] as $error) {
            $this->components->warn($error);
        }

        return $result['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
