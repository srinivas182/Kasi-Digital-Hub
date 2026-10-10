<?php

declare(strict_types=1);

namespace Modules\Partner\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Ai\Moderation\ModerationService;
use Modules\Core\Documents\Models\Document;
use Modules\Core\Enterprise\BusinessFacts;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Notifications\Notifier;
use Modules\Core\Structure\Models\RoleAssignment;
use Modules\Partner\Events\FollowupAnswered;
use Modules\Partner\Events\ReferralSent;
use Modules\Partner\Events\ReferralStageChanged;
use Modules\Partner\Events\SupportConfirmed;
use Modules\Partner\Models\Offer;
use Modules\Partner\Models\Referral;
use Modules\Partner\Notifications\PartnerNotification;

/**
 * Consented referrals and the partner pipeline (ADR-025): exactly what the entrepreneur chose is shared;
 * withdrawal ends access; reminders at 7 and 14 days and "no response" at 30; outcomes confirmed by both
 * sides with 3- and 6-month follow-ups.
 */
final readonly class Referrals
{
    public const REMIND_DAYS = [7, 14];

    public const NO_RESPONSE_DAYS = 30;

    public function __construct(
        private BusinessFacts $businesses,
        private Eligibility $eligibility,
        private Offers $offers,
        private Notifier $notifier,
        private AuditLogger $audit,
        private ModerationService $moderation,
    ) {}

    /**
     * @param  array{profile?: bool, summary?: bool, readiness?: bool, documents?: list<string>}  $shared
     */
    public function send(Offer $offer, string $businessId, User $person, array $shared, ?string $message, ?User $by = null, ?string $note = null): Referral
    {
        $facts = $this->businesses->facts($businessId);
        if ($facts === null || ! $this->businesses->isMember($businessId, $person)) {
            throw new DomainException(__('partner.refer.not_yours'));
        }
        if (! $this->offers->isOpen($offer) || ! Offers::agreementAccepted($offer->organisation)) {
            throw new DomainException(__('partner.refer.closed'));
        }
        if (! $this->eligibility->check($offer, $facts)['eligible']) {
            throw new DomainException(__('partner.refer.not_eligible'));
        }
        if (Referral::query()->where('offer_id', $offer->id)->where('business_id', $businessId)->whereIn('stage', Referral::OPEN)->exists()) {
            throw new DomainException(__('partner.refer.already'));
        }

        // Only verified documents of the business's owners can be shared.
        $documents = array_values(array_unique(array_map('strval', (array) ($shared['documents'] ?? []))));
        $allowed = Document::query()->whereIn('id', $documents)->whereIn('user_id', $facts['owner_ids'])->where('status', Document::VERIFIED)->pluck('id')->map(static fn ($id): string => (string) $id)->all();
        if (count($allowed) !== count($documents)) {
            throw new DomainException(__('partner.refer.documents'));
        }

        $referral = Referral::query()->create([
            'offer_id' => $offer->id, 'organisation_id' => $offer->organisation_id, 'business_id' => $businessId, 'sent_by' => $person->id,
            'assisted_by' => $by?->id, 'facilitator_note' => $by !== null ? $note : null, 'message' => $message !== null ? mb_substr(trim($message), 0, 1000) : null,
            'shared' => ['profile' => (bool) ($shared['profile'] ?? true), 'summary' => (bool) ($shared['summary'] ?? false), 'readiness' => (bool) ($shared['readiness'] ?? false), 'documents' => $allowed],
        ]);
        $this->event($referral, 'sent', 'new', $by ?? $person, ['assisted' => $by !== null]);
        $this->audit->record('partner.referral_sent', $person, meta: ['referral' => $referral->id, 'shared' => $referral->shared], actor: $by);
        event(new ReferralSent($person, $by?->id, ['referral' => $referral->id, 'offer' => $offer->id, 'type' => $offer->type, 'assisted' => $by !== null]));
        $this->tellPartner($referral, 'new_referral', ['title' => $offer->title]);

        return $referral;
    }

    public function withdraw(Referral $referral, User $person): void
    {
        if (! in_array($referral->stage, Referral::OPEN, true)) {
            throw new DomainException(__('partner.refer.not_open'));
        }
        $referral->forceFill(['stage' => 'withdrawn', 'closed_at' => now()])->save();
        $this->event($referral, 'stage', 'withdrawn', $person);
        $this->tellPartner($referral, 'withdrawn', ['title' => $referral->offer->title]);
    }

    /** @param 'reviewing'|'info'|'approved'|'declined' $stage */
    public function move(Referral $referral, string $stage, User $by, ?string $message = null, ?string $reason = null): void
    {
        if (! in_array($referral->stage, Referral::OPEN, true)) {
            throw new DomainException(__('partner.refer.not_open'));
        }
        $referral->forceFill([
            'stage' => $stage, 'first_response_at' => $referral->first_response_at ?? now(),
            'decline_reason' => $stage === 'declined' ? $reason : null, 'closed_at' => $stage === 'declined' ? now() : null,
        ])->save();
        $this->event($referral, 'stage', $stage, $by);
        if ($message !== null && trim($message) !== '') {
            $this->message($referral, $by, true, $message);
        }
        event(new ReferralStageChanged($by, null, ['referral' => $referral->id, 'stage' => $stage]));
        $this->tellOwners($referral, $stage, ['title' => $referral->offer->title, 'partner' => $referral->offer->organisation->displayName(), 'message' => (string) $message]);
    }

    public function message(Referral $referral, User $sender, bool $fromPartner, string $body): bool
    {
        $id = (string) Str::ulid();
        $held = $this->moderation->check($body, 'partner_message', $id, $sender, useAi: false)['verdict'] === 'flag';
        DB::table('partner_referral_messages')->insert(['id' => $id, 'referral_id' => $referral->id, 'sender_id' => $sender->id, 'from_partner' => $fromPartner,
            'body' => mb_substr(trim($body), 0, 2000), 'held' => $held, 'created_at' => now()]);
        if ($fromPartner && $referral->first_response_at === null) {
            $referral->forceFill(['first_response_at' => now()])->save();
        }
        if (! $held) {
            $fromPartner ? $this->tellOwners($referral, 'message', ['title' => $referral->offer->title, 'partner' => $referral->offer->organisation->displayName()])
                : $this->tellPartner($referral, 'message', ['title' => $referral->offer->title]);
        }

        return ! $held;
    }

    /** The partner records what was provided; the entrepreneur then confirms it. */
    public function recordOutcome(Referral $referral, User $by, string $outcome, ?int $valueCents): void
    {
        if ($referral->stage !== 'approved') {
            throw new DomainException(__('partner.refer.approve_first'));
        }
        $referral->forceFill(['stage' => 'outcome', 'outcome' => mb_substr(trim($outcome), 0, 300), 'outcome_value_cents' => $valueCents, 'closed_at' => now()])->save();
        $this->event($referral, 'stage', 'outcome', $by, ['value' => $valueCents]);
        $this->tellOwners($referral, 'confirm', ['title' => $referral->offer->title, 'partner' => $referral->offer->organisation->displayName(), 'outcome' => (string) $referral->outcome]);
    }

    public function confirm(Referral $referral, User $person, bool $received): void
    {
        if ($referral->stage !== 'outcome' || $referral->outcome_confirmed_at !== null) {
            throw new DomainException(__('partner.refer.not_open'));
        }
        $this->event($referral, 'confirmed', null, $person, ['received' => $received]);
        if (! $received) {
            $this->tellPartner($referral, 'not_received', ['title' => $referral->offer->title]);

            return;
        }
        $referral->forceFill(['outcome_confirmed_at' => now()])->save();
        foreach ([3, 6] as $months) {
            DB::table('partner_followups')->insertOrIgnore(['referral_id' => $referral->id, 'months' => $months, 'due_on' => now()->addMonths($months)->toDateString()]);
        }
        event(new SupportConfirmed($person, null, ['referral' => $referral->id, 'type' => $referral->offer->type, 'value_cents' => (int) $referral->outcome_value_cents]));
    }

    public function answerFollowup(int $followupId, User $person, bool $trading): void
    {
        $f = DB::table('partner_followups')->where('id', $followupId)->first();
        $referral = $f !== null ? Referral::query()->find($f->referral_id) : null;
        if ($f === null || ! $referral instanceof Referral || $f->answered_at !== null || ! $this->businesses->isMember($referral->business_id, $person)) {
            throw new DomainException(__('partner.refer.not_open'));
        }
        DB::table('partner_followups')->where('id', $followupId)->update(['answer' => $trading ? 'yes' : 'no', 'answered_at' => now()]);
        event(new FollowupAnswered($person, null, ['referral' => $referral->id, 'months' => (int) $f->months, 'trading' => $trading]));
    }

    /**
     * Daily: reminders at 7 and 14 days without a response; "no response" at 30 days (entrepreneur told
     * kindly, KasiHub alerted); follow-ups due.
     *
     * @return array<string, int>
     */
    public function housekeeping(): array
    {
        $done = ['reminders' => 0, 'no_response' => 0, 'followups' => 0];
        Referral::query()->with('offer.organisation')->where('stage', 'new')->whereNull('first_response_at')->get()->each(function (Referral $r) use (&$done): void {
            $age = (int) $r->created_at->diffInDays(now());
            if ($age >= self::NO_RESPONSE_DAYS) {
                $r->forceFill(['stage' => 'no_response', 'closed_at' => now()])->save();
                $this->event($r, 'stage', 'no_response', null);
                $this->tellOwners($r, 'no_response', ['title' => $r->offer->title, 'partner' => $r->offer->organisation->displayName()]);
                $staff = RoleAssignment::query()->whereIn('role', ['operations_admin', 'super_admin'])->pluck('user_id');
                User::query()->whereIn('id', $staff)->get()->each(fn (User $u) => $this->notifier->send($u, new PartnerNotification('no_response_partner', ['title' => $r->offer->title, 'partner' => $r->offer->organisation->displayName()], '/partner/admin')));
                $done['no_response']++;
            } else {
                // Reminder 1 from day 7; reminder 2 from day 14 (each sent once).
                $due = $age >= 14 ? $r->created_at->addDays(14) : ($age >= 7 ? $r->created_at->addDays(7) : null);
                if ($due !== null && ($r->reminded_at === null || $r->reminded_at->lt($due))) {
                    $this->tellPartner($r, 'reminder', ['title' => $r->offer->title, 'days' => (string) $age]);
                    $r->forceFill(['reminded_at' => now()])->save();
                    $done['reminders']++;
                }
            }
        });

        DB::table('partner_followups')->whereNull('sent_at')->where('due_on', '<=', now()->toDateString())->get()->each(function (object $f) use (&$done): void {
            $r = Referral::query()->with('offer.organisation')->find($f->referral_id);
            if ($r instanceof Referral) {
                $this->tellOwners($r, 'followup', ['partner' => $r->offer->organisation->displayName(), 'months' => (string) $f->months]);
            }
            DB::table('partner_followups')->where('id', $f->id)->update(['sent_at' => now()]);
            $done['followups']++;
        });

        return $done;
    }

    /** @param array<string, string> $params */
    private function tellPartner(Referral $referral, string $kind, array $params): void
    {
        $team = RoleAssignment::query()->whereIn('role', ['partner_admin', 'partner_agent'])->where('scope_type', 'organisation')->where('scope_id', $referral->organisation_id)->pluck('user_id');
        User::query()->whereIn('id', $team)->get()->each(fn (User $u) => $this->notifier->send($u, new PartnerNotification($kind, $params, '/partner/referrals/'.$referral->id)));
    }

    /** @param array<string, string> $params */
    private function tellOwners(Referral $referral, string $kind, array $params): void
    {
        $facts = $this->businesses->facts($referral->business_id);
        User::query()->whereIn('id', $facts['owner_ids'] ?? [])->get()->each(fn (User $u) => $this->notifier->send($u, new PartnerNotification($kind, $params, '/support/referrals/'.$referral->id)));
    }

    /** @param array<string, mixed> $meta */
    private function event(Referral $referral, string $kind, ?string $stage, ?User $actor, array $meta = []): void
    {
        DB::table('partner_referral_events')->insert(['referral_id' => $referral->id, 'kind' => $kind, 'stage' => $stage, 'actor_id' => $actor?->id,
            'meta' => $meta === [] ? null : json_encode($meta), 'created_at' => now()]);
    }
}
