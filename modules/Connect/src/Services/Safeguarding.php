<?php

declare(strict_types=1);

namespace Modules\Connect\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Connect\Events\ConcernRaised;
use Modules\Connect\Models\Mentor;
use Modules\Connect\Models\Mentorship;
use Modules\Connect\Notifications\ConnectNotification;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Notifications\Notifier;
use Modules\Core\Structure\Models\RoleAssignment;

/**
 * Safeguarding concerns (ADR-026): anyone can report; urgent concerns pause the mentorship at once and
 * alert every safeguarding officer on all channels; officers act within 24 hours, everything audited.
 */
final readonly class Safeguarding
{
    public const KINDS = ['inappropriate', 'contact', 'safety', 'other'];

    public function __construct(private Notifier $notifier, private AuditLogger $audit, private Mentors $mentors) {}

    public function report(User $reporter, ?string $aboutUserId, ?string $mentorshipId, string $kind, bool $urgent, string $body): string
    {
        $id = (string) Str::ulid();
        DB::table('connect_concerns')->insert(['id' => $id, 'reporter_id' => $reporter->id, 'about_user_id' => $aboutUserId, 'mentorship_id' => $mentorshipId,
            'kind' => $kind, 'urgent' => $urgent, 'body' => mb_substr(trim($body), 0, 4000), 'created_at' => now(), 'updated_at' => now()]);
        if ($urgent && $mentorshipId !== null) {
            Mentorship::query()->whereKey($mentorshipId)->whereIn('status', ['proposed', 'active'])->update(['status' => 'paused']);
        }
        $this->audit->record('connect.concern_raised', meta: ['concern' => $id, 'urgent' => $urgent], actor: $reporter);
        event(new ConcernRaised($reporter, null, ['urgent' => $urgent]));
        foreach ($this->officers() as $officer) {
            $this->notifier->send($officer, new ConnectNotification($urgent ? 'concern_urgent' : 'concern', [], '/connect/safeguarding/'.$id));
        }

        return $id;
    }

    /** An officer records what was done; optionally pauses the mentor platform-wide and closes the concern. */
    public function act(string $concernId, User $officer, string $actions, bool $pauseMentor, bool $close): void
    {
        $c = DB::table('connect_concerns')->where('id', $concernId)->first();
        if ($c === null) {
            return;
        }
        DB::table('connect_concerns')->where('id', $concernId)->update([
            'officer_id' => $officer->id, 'status' => $close ? 'closed' : 'actioned', 'actions' => trim(($c->actions ?? '')."\n".now()->toDateTimeString().': '.$actions),
            'resolved_at' => $close ? now() : null, 'updated_at' => now(),
        ]);
        if ($pauseMentor && $c->about_user_id !== null && ($mentor = Mentor::query()->find($c->about_user_id)) instanceof Mentor) {
            $this->mentors->restrict($mentor, 'paused', __('connect.safeguarding.paused_reason'), $officer);
        }
        $this->audit->record('connect.concern_actioned', meta: ['concern' => $concernId, 'pause' => $pauseMentor, 'close' => $close], actor: $officer);
    }

    /** @return list<User> */
    private function officers(): array
    {
        return array_values(User::query()->whereIn('id', RoleAssignment::query()->where('role', 'safeguarding_officer')->pluck('user_id'))->get()->all());
    }
}
