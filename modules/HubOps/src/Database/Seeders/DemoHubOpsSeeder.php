<?php

declare(strict_types=1);

namespace Modules\HubOps\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\Hub;
use Modules\HubOps\Models\EventRegistration;
use Modules\HubOps\Models\HubEvent;

/**
 * Six weeks of hub visits and a set of past and upcoming events at the demo hubs.
 * Door screens open with a fixed demo link: /kiosk/{hub-slug}/demo-door (demo only).
 */
final class DemoHubOpsSeeder extends Seeder
{
    public const DEMO_KIOSK_TOKEN = 'demo-door';

    private const PURPOSES = ['jobs' => 40, 'learning' => 22, 'computer' => 14, 'business' => 10, 'printing' => 9, 'other' => 5];

    public function run(): void
    {
        if (DB::table('hub_visits')->exists()) {
            return;
        }

        mt_srand(7);
        $hubs = Hub::query()->where('status', 'live')->get();
        $citizens = User::query()->where('phone', 'like', '+2784000%')->get(['id', 'home_hub_id'])->groupBy('home_hub_id');

        foreach ($hubs as $hub) {
            $hub->forceFill(['kiosk_token_hash' => hash('sha256', self::DEMO_KIOSK_TOKEN)])->save();
            /** @var list<string> $members */
            $members = array_values(array_map('strval', $citizens->get($hub->id, collect())->pluck('id')->all()));
            $this->visits($hub, $members);
            $this->events($hub, $members);
        }

        $this->personas();
    }

    /** @param list<string> $members */
    private function visits(Hub $hub, array $members): void
    {
        $size = match ($hub->package) {
            'full' => 22, 'enterprise' => 16, 'growth' => 12, default => 7
        };
        $rows = [];
        $today = CarbonImmutable::now('Africa/Johannesburg')->startOfDay();

        for ($d = 42; $d >= 1; $d--) {
            $day = $today->subDays($d);
            if ($day->isSunday()) {
                continue;
            }
            $count = max(1, (int) round($size * ($day->isSaturday() ? 0.5 : 1) * (mt_rand(70, 130) / 100)));
            $people = $members === [] ? [] : array_slice($this->shuffle($members), 0, min($count, count($members)));

            foreach ($people as $userId) {
                $rows[] = $this->visitRow($hub->id, $userId, $day, mt_rand(0, 3) === 0 ? 'desk' : 'qr');
            }
            for ($w = 0; $w < max(1, intdiv($count, 6)); $w++) {
                $rows[] = $this->visitRow($hub->id, null, $day, 'walk_in');
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('hub_visits')->insert($chunk);
        }
    }

    /** @param list<string> $members */
    private function events(Hub $hub, array $members): void
    {
        $now = CarbonImmutable::now('Africa/Johannesburg')->startOfDay();
        $plans = [
            ['job_day', 'Job day: local employers recruiting', 'Main hall', -12, 10, 3, 40, 'public'],
            ['workshop', 'CV and interview skills workshop', 'Training room', -5, 13, 2, 20, 'members'],
            ['workshop', 'Write a CV that gets interviews', 'Training room', 2, 10, 2, 15, 'public'],
            ['info_session', 'Starting a small business: registration and funding', 'Training room', 4, 14, 1, 30, 'public'],
            ['class', 'Digital skills for beginners (16+)', 'Computer lab', 6, 9, 3, 12, 'learners'],
            ['job_day', 'Retail and hospitality job day', 'Main hall', 9, 10, 4, 50, 'public'],
        ];

        foreach ($plans as [$type, $title, $room, $dayOffset, $hour, $hours, $capacity, $audience]) {
            $starts = $now->addDays($dayOffset)->setTime($hour, 0);
            $event = HubEvent::query()->create([
                'hub_id' => $hub->id, 'type' => $type, 'title' => $title, 'room' => $room,
                'description' => 'Demo event - not real. Bring your ID and, for job days, copies of your CV.',
                'starts_at' => $starts->utc(), 'ends_at' => $starts->addHours($hours)->utc(),
                'capacity' => $capacity, 'audience' => $audience, 'status' => 'scheduled',
            ]);

            $signups = array_slice($this->shuffle($members), 0, min(count($members), (int) round($capacity * mt_rand(55, 110) / 100)));
            foreach ($signups as $i => $userId) {
                $registered = $i < $capacity;
                EventRegistration::query()->create([
                    'event_id' => $event->id, 'user_id' => $userId,
                    'status' => $registered ? EventRegistration::REGISTERED : EventRegistration::WAITLISTED,
                    'attended_at' => $registered && $dayOffset < 0 && mt_rand(1, 100) <= 72 ? $starts->addMinutes(mt_rand(0, 40))->utc() : null,
                ]);
            }
        }
    }

    /** The demo personas get something on their hub home. */
    private function personas(): void
    {
        $hub = Hub::query()->where('code', 'LP-GIY-TSU')->first();
        $thandi = User::query()->where('phone', '+27720000001')->first();
        $kurhula = User::query()->where('phone', '+27720000004')->first();
        if ($hub === null || $thandi === null) {
            return;
        }

        $jobDay = HubEvent::query()->where('hub_id', $hub->id)->where('title', 'Retail and hospitality job day')->first();
        if ($jobDay !== null) {
            EventRegistration::query()->firstOrCreate(['event_id' => $jobDay->id, 'user_id' => $thandi->id], ['status' => EventRegistration::REGISTERED]);
        }

        $class = HubEvent::query()->where('audience', 'learners')->whereHas('hub', fn ($q) => $q->where('code', 'LP-COL-MAL'))->first();
        if ($class !== null && $kurhula !== null) {
            EventRegistration::query()->firstOrCreate(['event_id' => $class->id, 'user_id' => $kurhula->id], ['status' => EventRegistration::REGISTERED]);
        }

        foreach ([3, 8, 15] as $daysAgo) {
            DB::table('hub_visits')->insertOrIgnore($this->visitRow($hub->id, $thandi->id, CarbonImmutable::now('Africa/Johannesburg')->subDays($daysAgo), 'qr'));
        }
    }

    /** @return array<string, mixed> */
    private function visitRow(string $hubId, ?string $userId, CarbonImmutable $day, string $method): array
    {
        return [
            'id' => (string) Str::ulid(), 'hub_id' => $hubId, 'user_id' => $userId, 'visit_date' => $day->toDateString(),
            'purpose' => $this->purpose(), 'method' => $method, 'checked_in_by' => null,
            'created_at' => $day->setTime(mt_rand(8, 16), mt_rand(0, 59))->utc()->toDateTimeString(),
        ];
    }

    private function purpose(): string
    {
        $roll = mt_rand(1, 100);
        foreach (self::PURPOSES as $purpose => $weight) {
            if (($roll -= $weight) <= 0) {
                return $purpose;
            }
        }

        return 'other';
    }

    /**
     * @param  list<string>  $items
     * @return list<string>
     */
    private function shuffle(array $items): array
    {
        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }

        return array_values($items);
    }
}
