<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Platform;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Identity\Models\User;
use Modules\Core\Search\SearchService;
use Throwable;

/**
 * One search box across the platform; results are grouped by type and limited to what the
 * person may see. If the search service is down the page says so and the rest keeps working.
 */
final class SearchController
{
    public function __invoke(Request $request, SearchService $search): Response
    {
        $query = trim(mb_substr((string) $request->query('q', ''), 0, 100));
        $user = $request->user();
        $results = [];
        $unavailable = false;

        if (mb_strlen($query) >= 2) {
            try {
                $results = $search->search($query, $user instanceof User ? $user : null);
            } catch (Throwable $e) {
                Log::warning('Search unavailable', ['error' => $e->getMessage()]);
                $unavailable = true;
            }
        }

        return Inertia::render('Core/Search', [
            'q' => $query,
            'unavailable' => $unavailable,
            'groups' => collect($results)->groupBy('type')->map(static fn ($items, $type): array => [
                'type' => $type,
                'items' => $items->map(static fn (array $r): array => ['ref' => $r['ref'], 'title' => $r['title'], 'body' => mb_strimwidth($r['body'], 0, 160, '...'), 'url' => $r['url']])->values()->all(),
            ])->values(),
        ]);
    }
}
