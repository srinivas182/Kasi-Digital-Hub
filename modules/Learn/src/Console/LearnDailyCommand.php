<?php

declare(strict_types=1);

namespace Modules\Learn\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Core\Identity\Models\User;
use Modules\Core\Notifications\Notifier;
use Modules\Learn\Models\Enrolment;
use Modules\Learn\Notifications\LearnerNotification;
use Modules\Learn\Services\Certificates;
use Modules\Learn\Services\Learning;

/** Daily: tell people who saved courses (before enrolment existed) that they can now enrol - once. */
final class LearnDailyCommand extends Command
{
    protected $signature = 'kasi:learn:daily';

    protected $description = 'One-time "you can now enrol" messages for saved courses';

    public function handle(Notifier $notifier): int
    {
        $sent = 0;
        DB::table('learn_saved_courses')->join('learn_courses', 'learn_courses.id', '=', 'learn_saved_courses.course_id')
            ->whereNull('learn_saved_courses.notified_at')->where('learn_courses.status', 'published')
            ->get(['learn_saved_courses.user_id', 'learn_saved_courses.course_id', 'learn_courses.title'])->groupBy('user_id')
            ->each(function ($saved, $userId) use ($notifier, &$sent): void {
                $user = User::query()->find($userId);
                if ($user !== null) {
                    $notifier->send($user, new LearnerNotification('enrol_open', ['title' => (string) ($saved->first()->title ?? ''), 'count' => (string) $saved->count()], '/learn/courses?saved=1'));
                    $sent++;
                }
                DB::table('learn_saved_courses')->where('user_id', $userId)->whereIn('course_id', $saved->pluck('course_id'))->update(['notified_at' => now()]);
            });
        $this->info("Sent: {$sent}");
        // Blended courses: work finished, waiting for sessions to take place.
        Enrolment::query()->with(['course', 'version'])->where('progress', 100)->whereNull('completed_at')->where('status', 'active')
            ->each(static fn (Enrolment $e) => app(Learning::class)->recalculate($e));
        $this->info('Certificates issued after a delay (e.g. ID verified): '.app(Certificates::class)->issuePending());

        return self::SUCCESS;
    }
}
