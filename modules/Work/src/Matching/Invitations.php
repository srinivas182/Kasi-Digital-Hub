<?php

declare(strict_types=1);

namespace Modules\Work\Matching;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Notifications\Notifier;
use Modules\Work\Events\InvitationAnswered;
use Modules\Work\Events\InvitationSent;
use Modules\Work\Models\JobListing;
use Modules\Work\Notifications\Messages\InvitationAnsweredNotification;
use Modules\Work\Notifications\Messages\JobInvitationNotification;

/**
 * "Invite to apply": verified employers invite suggested candidates (at most 20 per advert per day).
 * The person accepts or declines, no reason needed. Contact details are shared only on acceptance.
 */
final readonly class Invitations
{
    public const DAILY_LIMIT = 20;

    public function __construct(private Visibility $visibility, private Notifier $notifier, private AuditLogger $audit) {}

    public function invite(JobListing $listing, User $person, User $by, ?string $message = null): string
    {
        if ($listing->status !== 'live' || ! $listing->organisation->isVerified()) {
            throw new DomainException(__('work.invite.not_live'));
        }
        if (! $this->visibility->canSee($listing->organisation_id, $person->id)) {
            throw new DomainException(__('work.invite.not_visible'));
        }
        if (DB::table('work_invitations')->where('listing_id', $listing->id)->where('user_id', $person->id)->exists()) {
            throw new DomainException(__('work.invite.already'));
        }
        if (DB::table('work_invitations')->where('listing_id', $listing->id)->where('created_at', '>=', now()->subDay())->count() >= self::DAILY_LIMIT) {
            throw new DomainException(__('work.invite.limit', ['limit' => self::DAILY_LIMIT]));
        }

        $id = (string) Str::ulid();
        DB::table('work_invitations')->insert(['id' => $id, 'listing_id' => $listing->id, 'user_id' => $person->id, 'invited_by' => $by->id,
            'status' => 'sent', 'message' => $message, 'created_at' => now(), 'updated_at' => now()]);
        $this->audit->record('work.invitation_sent', $person, meta: ['listing' => $listing->id], actor: $by);
        event(new InvitationSent($person, $by->id, ['listing' => $listing->id]));
        $this->notifier->send($person, new JobInvitationNotification($listing));

        return $id;
    }

    public function answer(string $invitationId, User $person, bool $accept): void
    {
        $invitation = DB::table('work_invitations')->where('id', $invitationId)->where('user_id', $person->id)->first();
        if ($invitation === null || $invitation->status !== 'sent') {
            throw new DomainException(__('work.invite.closed'));
        }

        DB::table('work_invitations')->where('id', $invitationId)->update(['status' => $accept ? 'accepted' : 'declined', 'responded_at' => now(), 'updated_at' => now()]);
        $this->audit->record($accept ? 'work.invitation_accepted' : 'work.invitation_declined', $person, meta: ['listing' => $invitation->listing_id]);
        event(new InvitationAnswered($person, null, ['listing' => (string) $invitation->listing_id, 'accepted' => $accept]));

        $listing = JobListing::query()->whereKey($invitation->listing_id)->first();
        $inviter = $invitation->invited_by !== null ? User::query()->whereKey($invitation->invited_by)->first() : null;
        if ($listing !== null && $inviter !== null) {
            $name = $accept ? $person->fullName() : $person->first_name;
            $this->notifier->send($inviter, new InvitationAnsweredNotification($name, $listing->title, $accept, $listing->id));
        }
    }

    /** Invitations end when the advert closes. */
    public function expireFor(string $listingId): void
    {
        DB::table('work_invitations')->where('listing_id', $listingId)->where('status', 'sent')->update(['status' => 'expired', 'updated_at' => now()]);
    }
}
