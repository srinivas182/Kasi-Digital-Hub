<?php

declare(strict_types=1);

namespace Modules\Connect\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Connect\Events\MentorApproved;
use Modules\Connect\Models\Mentor;
use Modules\Connect\Models\Mentorship;
use Modules\Connect\Notifications\ConnectNotification;
use Modules\Core\Access\RoleAssignments;
use Modules\Core\Access\Scope;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Notifications\Notifier;
use Modules\Core\Structure\Models\RoleAssignment;

/**
 * Mentor applications and vetting (ADR-026): verified ID, two references, code of conduct and the
 * safeguarding course for everyone; a police clearance in addition to mentor 16-17-year-olds.
 * Coordinators approve; coordinators and safeguarding officers can pause or remove at any time.
 */
final readonly class Mentors
{
    public function __construct(private AuditLogger $audit, private Notifier $notifier, private RoleAssignments $roles) {}

    /** @param array<string, mixed> $data */
    public function apply(User $user, array $data): Mentor
    {
        if ($user->isMinor()) {
            throw new DomainException(__('connect.mentor.adults'));
        }
        $mentor = Mentor::query()->updateOrCreate(['user_id' => $user->id], [...$data, 'status' => Mentor::query()->whereKey($user->id)->value('status') ?? 'applied']);
        $this->audit->record('connect.mentor_applied', $user);

        return $mentor;
    }

    public function check(Mentor $mentor, string $check, User $by, ?string $note = null, bool $done = true): void
    {
        if (! in_array($check, [...Mentor::CHECKS, 'police_clearance'], true)) {
            throw new DomainException('Unknown check');
        }
        if ($done) {
            DB::table('connect_mentor_checks')->updateOrInsert(['mentor_id' => $mentor->user_id, 'check' => $check], ['note' => $note, 'checked_by' => $by->id, 'checked_at' => now()]);
        } else {
            DB::table('connect_mentor_checks')->where('mentor_id', $mentor->user_id)->where('check', $check)->delete();
        }
        if ($check === 'police_clearance') {
            $mentor->forceFill(['minors_ok' => $done])->save();
        }
        $this->audit->record('connect.mentor_check', $mentor->user, meta: ['check' => $check, 'done' => $done], actor: $by);
    }

    /** @return list<string> checks still missing for approval */
    public function missing(Mentor $mentor): array
    {
        $done = DB::table('connect_mentor_checks')->where('mentor_id', $mentor->user_id)->pluck('check')->all();

        return array_values(array_diff(Mentor::CHECKS, $done));
    }

    public function approve(Mentor $mentor, User $by): void
    {
        if ($this->missing($mentor) !== []) {
            throw new DomainException(__('connect.mentor.checks_missing'));
        }
        $mentor->forceFill(['status' => 'approved', 'status_reason' => null, 'approved_by' => $by->id, 'approved_at' => now()])->save();
        if (! RoleAssignment::query()->where('user_id', $mentor->user_id)->where('role', 'mentor')->exists()) {
            $this->roles->assign($mentor->user, 'mentor', Scope::self(), $by);
        }
        $this->audit->record('connect.mentor_approved', $mentor->user, actor: $by);
        event(new MentorApproved($mentor->user, $by->id, ['minors_ok' => $mentor->minors_ok]));
        $this->notifier->send($mentor->user, new ConnectNotification('mentor_approved', [], '/connect/mentor'));
    }

    /** Pause or remove a mentor: their active mentorships are paused and mentees are told kindly. */
    public function restrict(Mentor $mentor, string $status, string $reason, User $by): void
    {
        $mentor->forceFill(['status' => $status, 'status_reason' => $reason])->save();
        Mentorship::query()->where('mentor_id', $mentor->user_id)->whereIn('status', ['proposed', 'active'])->get()->each(function (Mentorship $m): void {
            $m->forceFill(['status' => 'paused'])->save();
            if (($mentee = User::query()->find($m->mentee_id)) instanceof User) {
                $this->notifier->send($mentee, new ConnectNotification('mentorship_paused', [], '/connect'));
            }
        });
        $this->audit->record('connect.mentor_'.$status, $mentor->user, meta: ['reason' => $reason], actor: $by);
        $this->notifier->send($mentor->user, new ConnectNotification('mentor_paused', ['reason' => $reason], '/connect/mentor'));
    }

    public function activeCount(string $mentorId): int
    {
        return Mentorship::query()->where('mentor_id', $mentorId)->whereIn('status', ['proposed', 'active'])->count();
    }
}
