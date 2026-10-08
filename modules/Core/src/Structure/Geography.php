<?php

declare(strict_types=1);

namespace Modules\Core\Structure;

use Illuminate\Support\Facades\DB;
use Modules\Core\Structure\Models\Municipality;
use Modules\Core\Structure\Models\Province;

/**
 * South African geography: provinces, municipalities (official codes) and distances.
 */
final class Geography
{
    /** @var array<string, string> */
    public const PROVINCES = [
        'EC' => 'Eastern Cape',
        'FS' => 'Free State',
        'GP' => 'Gauteng',
        'KZN' => 'KwaZulu-Natal',
        'LP' => 'Limpopo',
        'MP' => 'Mpumalanga',
        'NC' => 'Northern Cape',
        'NW' => 'North West',
        'WC' => 'Western Cape',
    ];

    public function seedProvinces(): void
    {
        foreach (self::PROVINCES as $code => $name) {
            Province::query()->updateOrCreate(['code' => $code], ['name' => $name]);
        }
    }

    /**
     * Import municipalities from CSV (code,name,category,province_code,district_code,latitude,longitude).
     *
     * @return array{imported: int, errors: list<string>}
     */
    public function importCsv(string $file, string $source): array
    {
        $this->seedProvinces();
        $provinces = Province::query()->pluck('id', 'code');
        $handle = fopen($file, 'rb');
        $errors = [];
        $rows = [];

        if ($handle === false) {
            return ['imported' => 0, 'errors' => ["Cannot open {$file}."]];
        }

        $header = fgetcsv($handle, escape: '\\');
        $line = 1;

        while (($data = fgetcsv($handle, escape: '\\')) !== false) {
            $line++;
            if ($header === false || count($data) !== count($header)) {
                $errors[] = "Line {$line}: wrong number of columns.";

                continue;
            }

            $header = array_map(static fn (?string $column): string => (string) $column, $header);

            /** @var array{code: string, name: string, category: string, province_code: string, district_code: string, latitude: string, longitude: string} $row */
            $row = array_combine($header, $data);

            if (! in_array($row['category'], [Municipality::METRO, Municipality::DISTRICT, Municipality::LOCAL], true) || ! isset($provinces[$row['province_code']])) {
                $errors[] = "Line {$line}: invalid category or province for [{$row['code']}].";

                continue;
            }

            $rows[] = $row;
        }

        fclose($handle);

        DB::transaction(function () use ($rows, $provinces, $source): void {
            // Districts first, so local municipalities can point at them.
            usort($rows, static fn (array $a, array $b): int => ($a['category'] === Municipality::DISTRICT ? 0 : 1) <=> ($b['category'] === Municipality::DISTRICT ? 0 : 1));

            foreach ($rows as $row) {
                Municipality::query()->updateOrCreate(['code' => $row['code']], [
                    'name' => $row['name'],
                    'category' => $row['category'],
                    'province_id' => $provinces[$row['province_code']],
                    'district_id' => $row['district_code'] !== '' ? Municipality::query()->where('code', $row['district_code'])->value('id') : null,
                    'latitude' => $row['latitude'] !== '' ? $row['latitude'] : null,
                    'longitude' => $row['longitude'] !== '' ? $row['longitude'] : null,
                    'source' => $source,
                ]);
            }
        });

        return ['imported' => count($rows), 'errors' => $errors];
    }

    /** Great-circle distance in kilometres (used for "jobs near me" and nearest hubs). */
    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
