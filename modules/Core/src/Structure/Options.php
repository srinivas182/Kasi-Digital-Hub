<?php

declare(strict_types=1);

namespace Modules\Core\Structure;

use Modules\Core\Structure\Models\Hub;
use Modules\Core\Structure\Models\Municipality;
use Modules\Core\Structure\Models\Province;

/**
 * Lists for "nearest hub" and location pickers. Cities only (metros and local municipalities).
 */
final class Options
{
    /**
     * @return list<array{id: string, name: string, city: string, province: string}>
     */
    public static function hubs(): array
    {
        return array_values(Hub::query()
            ->where('status', 'live')
            ->with('municipality.province')
            ->get()
            ->sortBy(fn (Hub $hub): string => $hub->municipality->province->name.$hub->municipality->name.$hub->name)
            ->map(fn (Hub $hub): array => [
                'id' => $hub->id,
                'name' => $hub->name,
                'city' => $hub->municipality->name,
                'province' => $hub->municipality->province->name,
            ])
            ->all());
    }

    /**
     * @return list<array{id: int, name: string, cities: list<array{id: int, name: string}>}>
     */
    public static function locations(): array
    {
        return array_values(Province::query()->orderBy('name')->get()->map(fn (Province $province): array => [
            'id' => $province->id,
            'name' => $province->name,
            'cities' => array_values($province->municipalities()
                ->where('category', '!=', Municipality::DISTRICT)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Municipality $m): array => ['id' => $m->id, 'name' => $m->name])
                ->all()),
        ])->all());
    }
}
