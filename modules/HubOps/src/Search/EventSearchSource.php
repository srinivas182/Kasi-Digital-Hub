<?php

declare(strict_types=1);

namespace Modules\HubOps\Search;

use App\Support\Format\SaFormat;
use Modules\Core\Search\SearchDocument;
use Modules\Core\Search\SearchSource;
use Modules\HubOps\Models\HubEvent;

/** Upcoming hub events. Audience maps to search visibility (public / members / learners). */
final class EventSearchSource implements SearchSource
{
    public function type(): string
    {
        return 'event';
    }

    public function all(): iterable
    {
        foreach (HubEvent::query()->with('hub:id,name')->where('status', 'scheduled')->where('ends_at', '>', now())->cursor() as $event) {
            yield $this->document($event);
        }
    }

    public function find(string $ref): ?SearchDocument
    {
        $event = HubEvent::query()->with('hub:id,name')->whereKey($ref)->where('status', 'scheduled')->where('ends_at', '>', now())->first();

        return $event === null ? null : $this->document($event);
    }

    private function document(HubEvent $e): SearchDocument
    {
        $when = SaFormat::dateTime($e->starts_at);

        return new SearchDocument('event', $e->id, $e->title, trim("{$e->hub->name}, {$when}. ".($e->description ?? '')), '/events/'.$e->id, $e->audience, $e->hub_id);
    }
}
