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

    /**
     * Counted by the database (S22): one grouped query per indicator, using the (name, hub_id, occurred_at) index.
     * Only indicators that check payload values or sum an amount read their (few) matching events in PHP, so JSON
     * behaves the same on MySQL and SQLite.
     */
    public function byHub(?array $hubIds, CarbonImmutable $from, CarbonImmutable $to, ?AgeRange $ages = null): array
    {
        $query = DB::table('platform_events')->whereIn('platform_events.name', $this->events)->whereBetween('platform_events.occurred_at', [$from->utc(), $to->utc()]);
        if ($hubIds !== null) {
            $query->whereIn('platform_events.hub_id', $hubIds);
        }
        if ($ages !== null) {
            [$oldest, $youngest] = $ages->birthBetween();
            // Compare as dates: a stored time part must not push someone out on their birthday.
            $query->join('users', 'users.id', '=', 'platform_events.user_id')->whereDate('users.date_of_birth', '>=', $oldest)->whereDate('users.date_of_birth', '<=', $youngest);
        }

        if ($this->where === null && $this->measure !== 'sum') {
            $count = $this->measure === 'people' ? 'COUNT(DISTINCT platform_events.user_id)' : 'COUNT(*)';

            return array_map('intval', $query->groupBy('platform_events.hub_id')->selectRaw("COALESCE(platform_events.hub_id, '') as hub, {$count} as n")->pluck('n', 'hub')->all());
        }

        $totals = [];
        $people = [];
        foreach ($query->select(['platform_events.hub_id', 'platform_events.user_id', 'platform_events.payload'])->orderBy('platform_events.occurred_at')->cursor() as $row) {
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
