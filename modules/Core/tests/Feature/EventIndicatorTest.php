<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Identity\Models\User;
use Modules\Core\Insights\AgeRange;
use Modules\Core\Insights\EventIndicator;
use Modules\Core\Tests\Structure;

/* S22: database-side counting must match a plain count exactly - events, distinct people, age ranges, payload checks. */
it('counts in the database exactly like a plain count', function (): void {
    Structure::seed($this);
    $hubs = [Structure::hub('LP-GIY-TSU')->id, null];
    $people = collect(range(1, 30))->map(fn (int $i) => User::factory()->create(['date_of_birth' => now()->subYears(16 + ($i % 25))]));
    foreach (range(1, 200) as $i) {
        $person = $people[$i % 30];
        DB::table('platform_events')->insert(['id' => (string) Str::ulid(), 'name' => $i % 3 === 0 ? 'x.other' : 'x.thing', 'user_id' => $person->id, 'hub_id' => $hubs[$i % 2],
            'payload' => json_encode(['ok' => $i % 4 === 0, 'amount' => $i]), 'occurred_at' => now()->subDays($i % 20)]);
    }
    [$from, $to] = [CarbonImmutable::now()->subDays(30), CarbonImmutable::now()];
    $ages = new AgeRange(18, 24);
    $inRange = static function (string $userId): bool {
        $age = CarbonImmutable::parse((string) DB::table('users')->where('id', $userId)->value('date_of_birth'))->age;

        return $age >= 18 && $age <= 24;
    };
    $rows = DB::table('platform_events')->where('name', 'x.thing')->get();
    $plain = static function (callable $keep, string $measure) use ($rows): array {
        $out = [];
        foreach ($rows->filter($keep)->groupBy(static fn ($r) => (string) ($r->hub_id ?? '')) as $hub => $group) {
            $out[$hub] = $measure === 'people' ? $group->pluck('user_id')->unique()->count() : $group->count();
        }
        ksort($out);

        return $out;
    };
    $sorted = static function (array $a): array {
        ksort($a);

        return $a;
    };

    expect($sorted((new EventIndicator('t', ['x.thing']))->byHub(null, $from, $to)))->toBe($plain(fn () => true, 'events'))
        ->and($sorted((new EventIndicator('t', ['x.thing'], 'people'))->byHub(null, $from, $to)))->toBe($plain(fn () => true, 'people'))
        ->and($sorted((new EventIndicator('t', ['x.thing'], 'people'))->byHub(null, $from, $to, $ages)))->toBe($plain(fn ($r) => $inRange((string) $r->user_id), 'people'))
        ->and($sorted((new EventIndicator('t', ['x.thing'], 'events', static fn (array $p): bool => (bool) $p['ok']))->byHub(null, $from, $to)))
        ->toBe($plain(fn ($r) => (bool) json_decode((string) $r->payload, true)['ok'], 'events'));
});
