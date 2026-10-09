<?php

declare(strict_types=1);

namespace Modules\Work\Console;

use Illuminate\Console\Command;
use Modules\Work\Models\OfoOccupation;

/**
 * Loads the official OFO list (CSV with columns: code, title, major_group). Existing starter
 * occupations with the same title receive the official code; new ones are added.
 */
final class ImportOfoCommand extends Command
{
    protected $signature = 'kasi:work:import-ofo {file : CSV with code,title,major_group}';

    protected $description = 'Import the official Organising Framework for Occupations (OFO) list';

    public function handle(): int
    {
        $path = (string) $this->argument('file');
        $file = is_file($path) ? fopen($path, 'rb') : false;
        if ($file === false) {
            $this->error("Cannot read {$path}");

            return self::FAILURE;
        }

        fgetcsv($file, escape: '\\');
        $count = 0;
        while (($row = fgetcsv($file, escape: '\\')) !== false) {
            [$code, $title, $group] = array_map(static fn (?string $v): string => trim((string) $v), array_pad($row, 3, ''));
            if ($code === '' || $title === '') {
                continue;
            }
            $match = OfoOccupation::query()->where('code', $code)->first()
                ?? OfoOccupation::query()->whereNull('code')->where('title', $title)->first()
                ?? new OfoOccupation;
            $match->fill(['code' => $code, 'title' => $title, 'major_group' => (int) ($group !== '' ? $group : substr($code, 0, 1)), 'official' => true])->save();
            $count++;
        }
        fclose($file);
        $this->info("Occupations imported: {$count}");

        return self::SUCCESS;
    }
}
