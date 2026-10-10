<?php

declare(strict_types=1);

namespace Modules\Partner\Http\Controllers;

use App\Support\Format\SaFormat;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Assist\AssistedSession;
use Modules\Core\Documents\Models\Document;
use Modules\Core\Enterprise\BusinessFacts;
use Modules\Core\Identity\Models\User;
use Modules\Partner\Models\Offer;
use Modules\Partner\Models\Referral;
use Modules\Partner\Services\Eligibility;
use Modules\Partner\Services\Offers;
use Modules\Partner\Services\Referrals;

/**
 * Entrepreneurs: "Support for you", offer details and consented referrals, and their referrals.
 * Hub facilitators in a help session act for the person (assisted referrals).
 */
final class SupportController
{
    public function __construct(private readonly BusinessFacts $businesses, private readonly Eligibility $eligibility, private readonly Offers $offers, private readonly AssistedSession $assist) {}

    public function index(Request $request): Response
    {
        [$user] = $this->who($request);
        $mine = $this->businesses->businessesOf($user);
        $businessId = in_array($request->query('business'), array_column($mine, 'id'), true) ? (string) $request->query('business') : ($mine[0]['id'] ?? null);
        $facts = $businessId !== null ? $this->businesses->facts($businessId) : null;

        $offers = Offer::query()->with('organisation')->where('status', 'open')->get()->filter(fn (Offer $o): bool => $this->offers->isOpen($o))
            ->map(fn (Offer $o): array => [...$this->card($o), 'eligibility' => $facts !== null ? $this->eligibility->check($o, $facts) : null])
            ->sortByDesc(static fn (array $o): int => ($o['eligibility']['eligible'] ?? false) ? 1000 : (int) ($o['eligibility']['met'] ?? 0) - (int) ($o['eligibility']['total'] ?? 0))
            ->values();

        return Inertia::render('Partner/Support/Index', ['businesses' => $mine, 'businessId' => $businessId, 'offers' => $offers]);
    }

    public function show(Request $request, Offer $offer): Response
    {
        [$user, $by] = $this->who($request);
        abort_unless($offer->status === 'open', 404);
        $mine = $this->businesses->businessesOf($user);
        $businessId = in_array($request->query('business'), array_column($mine, 'id'), true) ? (string) $request->query('business') : ($mine[0]['id'] ?? null);
        $facts = $businessId !== null ? $this->businesses->facts($businessId) : null;

        return Inertia::render('Partner/Support/Offer', [
            'offer' => [...$this->card($offer), 'description' => $offer->description, 'documents' => $offer->documents ?? [], 'partnerDescription' => $offer->organisation->description],
            'businessId' => $businessId,
            'eligibility' => $facts !== null ? $this->eligibility->check($offer, $facts) : null,
            'verifiedDocuments' => $facts !== null ? Document::query()->whereIn('user_id', $facts['owner_ids'])->where('status', Document::VERIFIED)->get(['id', 'type'])
                ->map(static fn (Document $d): array => ['id' => $d->id, 'type' => $d->type])->values() : [],
            'open' => $this->offers->isOpen($offer) && Offers::agreementAccepted($offer->organisation),
            'assisted' => $by !== null,
            'existing' => $businessId !== null ? Referral::query()->where('offer_id', $offer->id)->where('business_id', $businessId)->whereIn('stage', Referral::OPEN)->value('id') : null,
        ]);
    }

    public function refer(Request $request, Offer $offer, Referrals $referrals): RedirectResponse
    {
        [$user, $by] = $this->who($request);
        $data = $request->validate([
            'business_id' => ['required', 'string'], 'profile' => ['boolean'], 'summary' => ['boolean'], 'readiness' => ['boolean'],
            'documents' => ['array'], 'documents.*' => ['string'], 'message' => ['nullable', 'string', 'max:1000'], 'note' => ['nullable', 'string', 'max:1000'],
            'consent' => ['accepted'],
        ]);

        try {
            $referral = $referrals->send($offer, $data['business_id'], $user, ['profile' => (bool) ($data['profile'] ?? true), 'summary' => (bool) ($data['summary'] ?? false),
                'readiness' => (bool) ($data['readiness'] ?? false), 'documents' => array_values($data['documents'] ?? [])], $data['message'] ?? null, $by, $data['note'] ?? null);
        } catch (DomainException $e) {
            return back()->withErrors(['refer' => $e->getMessage()]);
        }

        return to_route('support.referral', $referral)->with('status', __('partner.refer.sent'));
    }

    public function referrals(Request $request): Response
    {
        [$user] = $this->who($request);
        $ids = array_column($this->businesses->businessesOf($user), 'id');

        return Inertia::render('Partner/Support/Referrals', [
            'referrals' => Referral::query()->with('offer.organisation')->whereIn('business_id', $ids)->latest('created_at')->get()
                ->map(static fn (Referral $r): array => ['id' => $r->id, 'offer' => $r->offer->title, 'partner' => $r->offer->organisation->displayName(), 'stage' => $r->stage, 'sentAt' => $r->created_at->toIso8601String()]),
        ]);
    }

    public function referral(Request $request, Referral $referral): Response
    {
        [$user] = $this->who($request);
        abort_unless($this->businesses->isMember($referral->business_id, $user), 404);
        $referral->load('offer.organisation');

        return Inertia::render('Partner/Support/Referral', [
            'referral' => ['id' => $referral->id, 'stage' => $referral->stage, 'offer' => $referral->offer->title, 'partner' => $referral->offer->organisation->displayName(),
                'shared' => $referral->shared, 'message' => $referral->message, 'outcome' => $referral->outcome, 'value' => $referral->outcome_value_cents !== null ? SaFormat::money($referral->outcome_value_cents) : null,
                'confirmed' => $referral->outcome_confirmed_at !== null, 'sentAt' => $referral->created_at->toIso8601String()],
            'timeline' => self::timeline($referral, false),
            'messages' => self::messages($referral, $user->id),
            'views' => DB::table('partner_document_views')->join('documents', 'documents.id', '=', 'partner_document_views.document_id')->where('referral_id', $referral->id)
                ->orderByDesc('viewed_at')->get(['documents.type', 'partner_document_views.viewed_at'])->map(static fn (object $v): array => ['type' => (string) $v->type, 'at' => (string) $v->viewed_at]),
            'followups' => DB::table('partner_followups')->where('referral_id', $referral->id)->whereNotNull('sent_at')->whereNull('answered_at')->get(['id', 'months'])
                ->map(static fn (object $f): array => ['id' => (int) $f->id, 'months' => (int) $f->months]),
        ]);
    }

    public function withdraw(Request $request, Referral $referral, Referrals $referrals): RedirectResponse
    {
        [$user] = $this->own($request, $referral);
        try {
            $referrals->withdraw($referral->load('offer'), $user);
        } catch (DomainException $e) {
            return back()->withErrors(['referral' => $e->getMessage()]);
        }

        return back()->with('status', __('partner.refer.withdrawn'));
    }

    public function message(Request $request, Referral $referral, Referrals $referrals): RedirectResponse
    {
        [$user] = $this->own($request, $referral);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        return back()->with('status', __($referrals->message($referral->load('offer.organisation'), $user, false, $data['body']) ? 'work.messages.sent' : 'work.messages.held'));
    }

    public function confirm(Request $request, Referral $referral, Referrals $referrals): RedirectResponse
    {
        [$user] = $this->own($request, $referral);
        $data = $request->validate(['received' => ['required', 'boolean']]);
        try {
            $referrals->confirm($referral->load('offer.organisation'), $user, (bool) $data['received']);
        } catch (DomainException $e) {
            return back()->withErrors(['referral' => $e->getMessage()]);
        }

        return back()->with('status', __('partner.refer.thanks'));
    }

    public function followup(Request $request, int $followup, Referrals $referrals): RedirectResponse
    {
        [$user] = $this->who($request);
        $data = $request->validate(['trading' => ['required', 'boolean']]);
        try {
            $referrals->answerFollowup($followup, $user, (bool) $data['trading']);
        } catch (DomainException $e) {
            return back()->withErrors(['referral' => $e->getMessage()]);
        }

        return back()->with('status', __('partner.refer.thanks'));
    }

    /** @return array<string, mixed> */
    private function card(Offer $o): array
    {
        return ['id' => $o->id, 'title' => $o->title, 'type' => $o->type, 'partner' => $o->organisation->displayName(), 'verified' => $o->organisation->isVerified(),
            'value' => $o->value_min_cents !== null ? SaFormat::money($o->value_min_cents).($o->value_max_cents !== null ? ' - '.SaFormat::money($o->value_max_cents) : '') : null,
            'closesOn' => $o->closes_on?->toDateString()];
    }

    /** @return list<array<string, mixed>> */
    public static function timeline(Referral $r, bool $forPartner): array
    {
        return array_values(DB::table('partner_referral_events')->where('referral_id', $r->id)->orderBy('created_at')->orderBy('id')->get()
            ->map(static function (object $e) use ($forPartner): array {
                $meta = (array) json_decode((string) ($e->meta ?? '{}'), true);

                return ['kind' => (string) $e->kind, 'stage' => $e->stage !== null ? (string) $e->stage : null, 'at' => (string) $e->created_at, 'meta' => $forPartner ? $meta : array_intersect_key($meta, ['assisted' => 1])];
            })->all());
    }

    /** @return list<array<string, mixed>> */
    public static function messages(Referral $r, string $viewerId): array
    {
        return array_values(DB::table('partner_referral_messages')->where('referral_id', $r->id)->orderBy('created_at')->orderBy('id')->get()
            ->filter(static fn (object $m): bool => ! $m->held || $m->sender_id === $viewerId)
            ->map(static fn (object $m): array => ['id' => (string) $m->id, 'fromEmployer' => (bool) $m->from_partner, 'body' => (string) $m->body, 'held' => (bool) $m->held,
                'mine' => $m->sender_id === $viewerId, 'at' => (string) $m->created_at])->all());
    }

    /** @return array{0: User, 1: User|null} */
    private function who(Request $request): array
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $helped = $this->assist->person($user);
        $subject = $helped ?? $user;
        abort_if($subject->isMinor(), 403);

        return [$subject, $helped !== null ? $user : null];
    }

    /** @return array{0: User, 1: User|null} */
    private function own(Request $request, Referral $referral): array
    {
        [$user, $by] = $this->who($request);
        abort_unless($this->businesses->isMember($referral->business_id, $user), 404);

        return [$user, $by];
    }
}
