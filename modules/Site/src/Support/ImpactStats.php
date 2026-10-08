<?php

declare(strict_types=1);

namespace Modules\Site\Support;

use Illuminate\Support\Facades\Cache;
use Modules\Core\Documents\Models\Document;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\Hub;
use Modules\Core\Structure\Models\Organisation;

/**
 * Headline numbers for the public website, cached for an hour.
 * From Sprint 20 these come from the impact data built on the platform event log.
 */
final class ImpactStats
{
    /**
     * @return array{people: int, hubs: int, organisations: int, verifiedDocuments: int}
     */
    public static function headline(): array
    {
        /** @var array{people: int, hubs: int, organisations: int, verifiedDocuments: int} */
        return Cache::remember('kasi:site:impact', now()->addHour(), static fn (): array => [
            'people' => User::query()->where('status', User::STATUS_ACTIVE)->count(),
            'hubs' => Hub::query()->where('status', 'live')->count(),
            'organisations' => Organisation::query()->where('verification_status', 'verified')->whereIn('type', ['employer', 'training_provider', 'partner', 'funder'])->count(),
            'verifiedDocuments' => Document::query()->where('status', Document::VERIFIED)->count(),
        ]);
    }
}
