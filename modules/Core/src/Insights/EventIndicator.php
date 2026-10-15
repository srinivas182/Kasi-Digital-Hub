<?php

declare(strict_types=1);

namespace Modules\Core\Insights;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * An indicator counted from platform events: events by name in the period, optionally only those whose
 * (small, non-sensitive) payload passes a check, counting events, distinct people, or summing a payload amount.
 * Payload checks run in PHP so JSON behaves the same on MySQL and SQLite.
 */
final readonly class EventIndicator implements Indicator
{
    /**
     * @param  list<string>  $events
     * @param  'events'|'people'|'sum'  $measure
     * @param  (Closure(array<string, mixed>): bool)|null  $where
     */
    public function __construct(
        private string $key,
        private array $events,
        private string $measure = 'events',
        private ?Closure $where = null,
        private ?string $sumField = null,
        private string $unit = 'count',
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function unit(): string
    {
        return $this->unit;
    }

    public function byHub(?array $hubIds, CarbonImmutable $from, CarbonImmutable $to, ?callable $person = null): array
    {
        $query = DB::table('platform_events')->whereIn('name', $this->events)->whereBetween('occurred_at', [$from->utc(), $to->utc()]);
        if ($hubIds !== null) {
            $query->whereIn('hub_id', $hubIds);
        }
        $totals = [];
        $people = [];
        foreach ($query->orderBy('occurred_at')->cursor() as $row) {
            if ($person !== null && ($row->user_id === null || ! $person((string) $row->user_id))) {
                continue;
            }
            $payload = (array) json_decode((string) ($row->payload ?? '{}'), true);
            if ($this->where !== null && ! ($this->where)($payload)) {
                continue;
            }
            $hub = (string) ($row->hub_id ?? '');
            if ($this->measure === 'people') {
                $people[$hub][(string) $row->user_id] = true;
            } else {
                $totals[$hub] = ($totals[$hub] ?? 0) + ($this->measure === 'sum' ? (int) ($payload[$this->sumField] ?? 0) : 1);
            }
        }

        return $this->measure === 'people' ? array_map('count', $people) : $totals;
    }
}
