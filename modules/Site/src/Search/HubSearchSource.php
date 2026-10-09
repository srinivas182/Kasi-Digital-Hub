<?php

declare(strict_types=1);

namespace Modules\Site\Search;

use Modules\Core\Search\SearchDocument;
use Modules\Core\Search\SearchSource;
use Modules\Core\Structure\Models\Hub;

/** Hubs that are open or opening (public information only). */
final class HubSearchSource implements SearchSource
{
    public function type(): string
    {
        return 'hub';
    }

    public function all(): iterable
    {
        foreach (Hub::query()->with(['municipality.province', 'place'])->whereIn('status', ['live', 'planned'])->whereNotNull('slug')->cursor() as $hub) {
            yield $this->document($hub);
        }
    }

    public function find(string $ref): ?SearchDocument
    {
        $hub = Hub::query()->with(['municipality.province', 'place'])->whereKey($ref)->whereIn('status', ['live', 'planned'])->whereNotNull('slug')->first();

        return $hub === null ? null : $this->document($hub);
    }

    private function document(Hub $hub): SearchDocument
    {
        $where = implode(', ', array_filter([$hub->place?->name, $hub->municipality->name, $hub->municipality->province->name]));

        return new SearchDocument('hub', $hub->id, $hub->name, trim($where.'. '.($hub->description ?? '').' '.($hub->address ?? '')), '/hubs/'.$hub->slug, 'public', $hub->id);
    }
}
