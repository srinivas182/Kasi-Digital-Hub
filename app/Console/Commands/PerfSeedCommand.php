<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * S22: fill a NON-production database with large volumes (people, platform events, hub visits) to measure
 * queries and indexes at realistic scale. Refuses to run in production.
 */
final class PerfSeedCommand extends Command
{
    protected $signature = 'kasi:perf:seed {--people=50000} {--events=500000}';

    protected $description = 'Large-volume test data for performance measurement (never production)';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Refusing to run in production.');

            return self::FAILURE;
        }
        $hubs = DB::table('hubs')->pluck('id')->all();
        if ($hubs === []) {
            $this->error('Seed the base data first.');

            return self::FAILURE;
        }
        $people = (int) $this->option('people');
        $events = (int) $this->option('events');
        $names = ['core.user.registered', 'hubops.visit.recorded', 'work.application.submitted', 'work.profile.completed', 'learn.enrolled', 'learn.course.completed', 'work.hire.confirmed', 'start.business.created'];

        $ids = [];
        foreach (array_chunk(range(1, $people), 1000) as $chunk) {
            $rows = [];
            foreach ($chunk as $i) {
                $id = (string) Str::ulid();
                $ids[] = [$id, $hubs[$i % count($hubs)]];
                $rows[] = ['id' => $id, 'phone' => '+2789'.str_pad((string) $i, 7, '0', STR_PAD_LEFT), 'first_name' => 'Perf', 'last_name' => 'Person '.$i,
                    'date_of_birth' => now()->subYears(16 + ($i % 25))->subDays($i % 365)->toDateString(), 'home_hub_id' => $hubs[$i % count($hubs)],
                    'status' => 'active', 'created_at' => now(), 'updated_at' => now()];
            }
            DB::table('users')->insertOrIgnore($rows);
        }
        $this->info("People: {$people}");

        $done = 0;
        while ($done < $events) {
            $rows = [];
            for ($j = 0; $j < 2000 && $done < $events; $j++, $done++) {
                [$uid, $hub] = $ids[random_int(0, count($ids) - 1)];
                $rows[] = ['id' => (string) Str::ulid(), 'name' => $names[$done % count($names)], 'user_id' => $uid, 'hub_id' => $hub,
                    'payload' => '{}', 'occurred_at' => now()->subMinutes(random_int(0, 60 * 24 * 180))];
            }
            DB::table('platform_events')->insert($rows);
        }
        $this->info("Events: {$events}");

        return self::SUCCESS;
    }
}
