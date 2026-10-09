<?php

declare(strict_types=1);

namespace Modules\Work\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Core\Identity\Models\User;
use Modules\Core\Notifications\Notifier;
use Modules\Work\Matching\MatchIndex;
use Modules\Work\Matching\Visibility;
use Modules\Work\Notifications\Messages\JobAlertNotification;

/**
 * Nightly: refresh all matches, then send at most one alert per person listing new jobs that
 * match 70% or more (only for people with job matching switched on).
 */
final class JobAlertsCommand extends Command
{
    public const THRESHOLD = 70;

    protected $signature = 'kasi:work:matches {--alerts-only : Skip the full refresh}';

    protected $description = 'Refresh job matches and send daily job alerts';

    public function handle(MatchIndex $index, Visibility $visibility, Notifier $notifier): int
    {
        if (! $this->option('alerts-only')) {
            $this->info('Matches kept: '.$index->refreshAll());
        }

        $rows = DB::table('work_matches')->join('work_listings', 'work_listings.id', '=', 'work_matches.listing_id')
            ->where('work_matches.score', '>=', self::THRESHOLD)->whereNull('work_matches.alerted_at')->where('work_matches.has_gaps', false)
            ->where('work_listings.status', 'live')
            ->get(['work_matches.user_id', 'work_matches.listing_id', 'work_listings.title', 'work_matches.score'])
            ->groupBy('user_id');

        $consenting = $visibility->consenting(array_values(array_map('strval', $rows->keys()->all())));
        $sent = 0;

        foreach ($consenting as $userId) {
            $user = User::query()->find($userId);
            $matches = $rows->get($userId);
            if ($user === null || $matches === null) {
                continue;
            }
            /** @var list<string> $titles */
            $titles = array_values($matches->sortByDesc('score')->pluck('title')->map(static fn ($t): string => (string) $t)->all());
            $notifier->send($user, new JobAlertNotification($matches->count(), $titles));
            DB::table('work_matches')->where('user_id', $userId)->whereIn('listing_id', $matches->pluck('listing_id'))->update(['alerted_at' => now()]);
            $sent++;
        }

        $this->info("Alerts sent: {$sent}");

        return self::SUCCESS;
    }
}
