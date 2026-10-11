<?php

declare(strict_types=1);

namespace Modules\Connect\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Connect\Models\Mentorship;
use Modules\Connect\Notifications\ConnectNotification;
use Modules\Core\Identity\Models\User;
use Modules\Core\Notifications\Notifier;

/** Hourly-safe: session reminders (next 24 hours), group-session hours, end-of-agreement prompts. */
final class ConnectDailyCommand extends Command
{
    protected $signature = 'kasi:connect:daily';

    protected $description = 'Mentoring reminders, group-session hours and end-of-agreement prompts';

    public function handle(Notifier $notifier): int
    {
        $reminded = 0;
        DB::table('connect_sessions')->where('status', 'scheduled')->whereNull('reminded_at')->whereBetween('starts_at', [now(), now()->addDay()])->get()
            ->each(function (object $s) use ($notifier, &$reminded): void {
                $m = Mentorship::query()->find($s->mentorship_id);
                if ($m instanceof Mentorship && $m->status === 'active') {
                    User::query()->whereIn('id', [$m->mentor_id, $m->mentee_id])->get()
                        ->each(fn (User $u) => $notifier->send($u, new ConnectNotification('reminder', [], '/connect/mentorships/'.$m->id)));
                    $reminded++;
                }
                DB::table('connect_sessions')->where('id', $s->id)->update(['reminded_at' => now()]);
            });

        DB::table('connect_group_sessions')->where('counted', false)->where('starts_at', '<', now()->subHours(4))->get()->each(function (object $g): void {
            DB::table('connect_mentors')->where('user_id', $g->mentor_id)->increment('minutes_mentored', (int) $g->minutes);
            DB::table('connect_group_sessions')->where('id', $g->id)->update(['counted' => true]);
        });

        Mentorship::query()->where('status', 'active')->whereDate('ends_on', now()->toDateString())->get()->each(function (Mentorship $m) use ($notifier): void {
            User::query()->whereIn('id', [$m->mentor_id, $m->mentee_id])->get()
                ->each(fn (User $u) => $notifier->send($u, new ConnectNotification('ending', [], '/connect/mentorships/'.$m->id)));
        });
        $this->info("Reminders: {$reminded}");

        return self::SUCCESS;
    }
}
