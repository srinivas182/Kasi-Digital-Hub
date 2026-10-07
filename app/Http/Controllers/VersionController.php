<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Http\JsonResponse;

/**
 * Reports the running build so support and deployments can confirm what is live.
 */
final class VersionController
{
    public function __invoke(ModuleRegistry $registry): JsonResponse
    {
        return response()->json([
            'name' => config('kasi.brand.name'),
            'version' => config('kasi.version.number'),
            'commit' => config('kasi.version.commit'),
            'built_at' => config('kasi.version.built_at'),
            'modules' => array_values(array_map(
                static fn (ModuleManifest $m): array => ['name' => $m->name, 'version' => $m->version],
                $registry->enabled(),
            )),
        ]);
    }
}
