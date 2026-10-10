<?php

declare(strict_types=1);

namespace Modules\Partner\Http\Controllers;

use App\Support\Format\SaFormat;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Modules\Core\Documents\DocumentVault;
use Modules\Core\Documents\Models\Document;
use Modules\Core\Enterprise\BusinessFacts;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Structure\Models\Hub;
use Modules\Core\Structure\Models\Municipality;
use Modules\Core\Structure\Models\Organisation;
use Modules\Core\Structure\Models\Province;
use Modules\Core\Structure\Models\RoleAssignment;
use Modules\Core\Structure\OrganisationRegistration;
use Modules\Partner\Models\Offer;
use Modules\Partner\Models\Referral;
use Modules\Partner\Services\Offers;
use Modules\Partner\Services\Referrals;

/**
 * The partner portal: registration, data-sharing agreement, team, offers, and the referral pipeline.
 */
final class PartnerController
{
    public function dashboard(Request $request): Response|RedirectResponse
    {
        $user = $this->adult($request);
        $partner = $this->partnerOf($user);
        if ($partner === null) {
            return to_route('partner.register');
        }
        $isAdmin = $this->has($user, $partner, 'partner_admin');

        return Inertia::render('Partner/Partner/Dashboard', [
            'partner' => ['id' => $partner->id, 'name' => $partner->displayName(), 'status' => $partner->verification_status, 'agreement' => Offers::agreementAccepted($partner)],
            'agreementVersion' => (string) config('kasi.partner.agreement_version'),
            'isAdmin' => $isAdmin,
            'referrals' => Referral::query()->with('offer')->where('organisation_id', $partner->id)->latest('created_at')->limit(100)->get()
                ->map(static fn (Referral $r): array => ['id' => $r->id, 'offer' => $r->offer->title, 'stage' => $r->stage, 'sentAt' => $r->created_at->toIso8601String(),
                    'waitingDays' => $r->first_response_at === null && $r->stage === 'new' ? (int) $r->created_at->diffInDays(now()) : null]),
            'offers' => Offer::query()->where('organisation_id', $partner->id)->latest('created_at')->get()
                ->map(static fn (Offer $o): array => ['id' => $o->id, 'title' => $o->title, 'status' => $o->status, 'reason' => $o->status_reason, 'closesOn' => $o->closes_on?->toDateString()]),
            'team' => RoleAssignment::query()->with('user:id,first_name,last_name,phone')->where('scope_type', 'organisation')->where('scope_id', $partner->id)
                ->whereIn('role', ['partner_admin', 'partner_agent'])->get()
                ->map(static fn (RoleAssignment $a): array => ['userId' => $a->user_id, 'name' => $a->user?->fullName(), 'role' => $a->role]),
        ]);
    }

    public function registerForm(Request $request): Response
    {
        $user = $this->adult($request);

        return Inertia::render('Partner/Partner/Register', ['cities' => Municipality::query()->where('category', '!=', 'district')->orderBy('name')->get(['id', 'name']), 'defaultCity' => $user->municipality_id]);
    }

    public function register(Request $request, OrganisationRegistration $registration): RedirectResponse
    {
        $user = $this->adult($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'], 'trading_name' => ['nullable', 'string', 'max:160'],
            'registration_number' => ['required', 'string', 'max:30'],
            'certificate' => ['required', File::types(['pdf', 'jpg', 'jpeg', 'png'])->max(10 * 1024)],
            'municipality_id' => ['required', 'integer', Rule::exists('municipalities', 'id')->whereNot('category', 'district')],
            'contact_email' => ['nullable', 'email:rfc', 'max:190'], 'description' => ['nullable', 'string', 'max:1000'], 'confirm' => ['accepted'],
        ]);
        $registration->register($user, 'partner', 'partner_admin', [...$data, 'sector' => 'finance'], $request->file('certificate'));

        return to_route('partner.home')->with('status', __('partner.registered'));
    }

    public function acceptAgreement(Request $request, AuditLogger $audit): RedirectResponse
    {
        [$user, $partner] = $this->admin($request);
        DB::table('partner_agreements')->insert(['organisation_id' => $partner->id, 'accepted_by' => $user->id, 'version' => (string) config('kasi.partner.agreement_version'), 'accepted_at' => now()]);
        $audit->record('partner.agreement_accepted', meta: ['organisation' => $partner->id, 'version' => config('kasi.partner.agreement_version')], actor: $user);

        return back()->with('status', __('partner.agreement.accepted'));
    }

    public function addMember(Request $request, OrganisationRegistration $registration): RedirectResponse
    {
        [$user, $partner] = $this->admin($request);
        $data = $request->validate(['phone' => ['required', 'string', 'max:20']]);
        $phone = SaFormat::normalisePhone($data['phone']);
        $person = $phone !== null ? User::query()->where('phone', $phone)->first() : null;
        if ($person === null || $person->isMinor()) {
            return back()->withErrors(['phone' => __('work.team.no_account')]);
        }
        try {
            $registration->addMember($partner, $person, 'partner_agent', $user, 'Agent');
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['phone' => $e->getMessage()]);
        }

        return back()->with('status', __('work.team.added', ['name' => $person->fullName()]));
    }

    public function offerForm(Request $request, ?Offer $offer = null): Response
    {
        [, $partner] = $this->admin($request);
        abort_if($offer !== null && $offer->organisation_id !== $partner->id, 404);

        return Inertia::render('Partner/Partner/OfferEdit', [
            'offer' => $offer === null ? null : [...$offer->only(['id', 'title', 'type', 'description', 'capacity', 'criteria', 'documents', 'status', 'status_reason']),
                'valueMin' => $offer->value_min_cents !== null ? $offer->value_min_cents / 100 : null, 'valueMax' => $offer->value_max_cents !== null ? $offer->value_max_cents / 100 : null,
                'opensOn' => $offer->opens_on->toDateString(), 'closesOn' => $offer->closes_on?->toDateString()],
            'options' => ['types' => Offer::TYPES, 'stages' => ['idea', 'informal', 'registered'], 'forms' => ['sole', 'pty', 'coop', 'npc'],
                'sectors' => (array) config('kasi.business_sectors'), 'turnover' => ['none', 'under_5k', '5k_20k', '20k_80k', 'over_80k'],
                'steps' => ['cipc_register', 'sars_tax', 'bank_account', 'bbbee_affidavit', 'uif_coida', 'municipal_permit', 'food_certificate'],
                'documents' => (array) config('kasi.documents.types'),
                'provinces' => Province::query()->orderBy('name')->get(['id', 'name']), 'hubs' => Hub::query()->orderBy('name')->get(['id', 'name'])],
        ]);
    }

    public function saveOffer(Request $request, ?Offer $offer = null): RedirectResponse
    {
        [$user, $partner] = $this->admin($request);
        abort_if($offer !== null && $offer->organisation_id !== $partner->id, 404);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:140'], 'type' => ['required', Rule::in(Offer::TYPES)], 'description' => ['required', 'string', 'min:30', 'max:5000'],
            'value_min' => ['nullable', 'numeric', 'min:0', 'max:100000000'], 'value_max' => ['nullable', 'numeric', 'gte:value_min', 'max:100000000'],
            'opens_on' => ['required', 'date'], 'closes_on' => ['nullable', 'date', 'after_or_equal:opens_on'], 'capacity' => ['nullable', 'integer', 'min:1'],
            'criteria' => ['array'], 'criteria.stages' => ['array'], 'criteria.sectors' => ['array'], 'criteria.forms' => ['array'], 'criteria.province_ids' => ['array'],
            'criteria.hub_ids' => ['array'], 'criteria.turnover' => ['array'], 'criteria.steps' => ['array'], 'criteria.age_min' => ['nullable', 'integer', 'min:18', 'max:120'],
            'criteria.age_max' => ['nullable', 'integer', 'min:18', 'max:120'], 'criteria.min_readiness' => ['nullable', 'integer', 'min:0', 'max:100'],
            'documents' => ['array'], 'documents.*' => [Rule::in((array) config('kasi.documents.types'))],
        ]);
        if (Offers::chargesFee($data['title']."\n".$data['description'])) {
            return back()->withErrors(['description' => __('partner.offer.no_fees')]);
        }
        $values = ['title' => $data['title'], 'type' => $data['type'], 'description' => $data['description'], 'opens_on' => $data['opens_on'], 'closes_on' => $data['closes_on'] ?? null,
            'capacity' => $data['capacity'] ?? null, 'criteria' => $data['criteria'] ?? [], 'documents' => array_values($data['documents'] ?? []),
            'value_min_cents' => isset($data['value_min']) ? (int) round((float) $data['value_min'] * 100) : null, 'value_max_cents' => isset($data['value_max']) ? (int) round((float) $data['value_max'] * 100) : null];
        $offer = $offer === null ? Offer::query()->create([...$values, 'organisation_id' => $partner->id, 'created_by' => $user->id]) : tap($offer)->update([...$values, 'status' => 'draft']);

        return to_route('partner.offers.edit', $offer)->with('status', __('work.saved'));
    }

    public function submitOffer(Request $request, Offer $offer, Offers $offers): RedirectResponse
    {
        [$user, $partner] = $this->admin($request);
        abort_if($offer->organisation_id !== $partner->id, 404);
        try {
            $offers->submit($offer->load('organisation'), $user);
        } catch (DomainException $e) {
            return back()->withErrors(['offer' => $e->getMessage()]);
        }

        return back()->with('status', __($offer->status === 'open' ? 'partner.offer.open' : 'partner.offer.in_review'));
    }

    public function closeOffer(Request $request, Offer $offer): RedirectResponse
    {
        [, $partner] = $this->admin($request);
        abort_if($offer->organisation_id !== $partner->id, 404);
        $offer->forceFill(['status' => 'closed'])->save();

        return back()->with('status', __('partner.offer.closed'));
    }

    public function referral(Request $request, Referral $referral, BusinessFacts $businesses): Response
    {
        $user = $this->member($request, $referral);
        $referral->load('offer');
        $facts = $businesses->facts($referral->business_id);
        $shared = $referral->shared;
        $docs = $referral->partnerCanSeeDocuments() ? Document::query()->whereIn('id', $shared['documents'] ?? [])->get(['id', 'type', 'status']) : collect();
        $owners = $facts !== null ? User::query()->whereIn('id', $facts['owner_ids'])->get() : collect();

        return Inertia::render('Partner/Partner/Referral', [
            'referral' => ['id' => $referral->id, 'stage' => $referral->stage, 'offer' => $referral->offer->title, 'message' => $referral->message, 'note' => $referral->facilitator_note,
                'assisted' => $referral->assisted_by !== null, 'sentAt' => $referral->created_at->toIso8601String(), 'outcome' => $referral->outcome,
                'confirmed' => $referral->outcome_confirmed_at !== null, 'withdrawn' => $referral->stage === 'withdrawn'],
            'business' => $referral->stage === 'withdrawn' || $facts === null ? null : [
                'name' => $facts['name'], 'stage' => $facts['stage'], 'sector' => $facts['sector'], 'legalForm' => $facts['legal_form'],
                'people' => $facts['people'], 'readiness' => ($shared['readiness'] ?? false) ? $facts['readiness'] : null,
                'summary' => ($shared['summary'] ?? false) ? $businesses->summary($referral->business_id) : null,
                'contacts' => $owners->map(static fn (User $u): array => ['name' => $u->fullName(), 'phone' => SaFormat::phone($u->phone)])->values(),
            ],
            'documents' => $docs->map(static fn (Document $d): array => ['id' => $d->id, 'type' => $d->type])->values(),
            'timeline' => SupportController::timeline($referral, true),
            'messages' => SupportController::messages($referral, $user->id),
            'reasons' => Referral::DECLINE_REASONS,
        ]);
    }

    public function move(Request $request, Referral $referral, Referrals $referrals): RedirectResponse
    {
        $user = $this->member($request, $referral);
        $data = $request->validate(['stage' => ['required', Rule::in(['reviewing', 'info', 'approved', 'declined'])], 'message' => ['nullable', 'string', 'max:2000'],
            'reason' => ['nullable', Rule::in(Referral::DECLINE_REASONS)]]);
        try {
            $referrals->move($referral->load('offer.organisation'), $data['stage'], $user, $data['message'] ?? null, $data['reason'] ?? null);
        } catch (DomainException $e) {
            return back()->withErrors(['referral' => $e->getMessage()]);
        }

        return back()->with('status', __('partner.pipeline.moved'));
    }

    public function message(Request $request, Referral $referral, Referrals $referrals): RedirectResponse
    {
        $user = $this->member($request, $referral);
        abort_if($referral->stage === 'withdrawn', 409);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        return back()->with('status', __($referrals->message($referral->load('offer.organisation'), $user, true, $data['body']) ? 'work.messages.sent' : 'work.messages.held'));
    }

    public function outcome(Request $request, Referral $referral, Referrals $referrals): RedirectResponse
    {
        $user = $this->member($request, $referral);
        $data = $request->validate(['outcome' => ['required', 'string', 'max:300'], 'value' => ['nullable', 'numeric', 'min:0', 'max:100000000']]);
        try {
            $referrals->recordOutcome($referral->load('offer.organisation'), $user, $data['outcome'], isset($data['value']) ? (int) round((float) $data['value'] * 100) : null);
        } catch (DomainException $e) {
            return back()->withErrors(['referral' => $e->getMessage()]);
        }

        return back()->with('status', __('partner.pipeline.outcome_recorded'));
    }

    /** Shared documents: only those the entrepreneur chose, only within the access window, every view logged. */
    public function document(Request $request, Referral $referral, Document $document, DocumentVault $vault, AuditLogger $audit): RedirectResponse
    {
        $user = $this->member($request, $referral);
        abort_unless($referral->partnerCanSeeDocuments() && in_array($document->id, $referral->shared['documents'] ?? [], true), 404);
        DB::table('partner_document_views')->insert(['referral_id' => $referral->id, 'document_id' => $document->id, 'viewed_by' => $user->id, 'viewed_at' => now()]);
        $audit->record('partner.document_viewed', meta: ['referral' => $referral->id, 'document' => $document->id], actor: $user);

        return redirect()->away($vault->temporaryUrl($document, inline: true));
    }

    private function partnerOf(User $user): ?Organisation
    {
        $ids = RoleAssignment::query()->where('user_id', $user->id)->whereIn('role', ['partner_admin', 'partner_agent'])->where('scope_type', 'organisation')->pluck('scope_id');

        return Organisation::query()->whereIn('id', $ids)->where('type', 'partner')->orderBy('name')->first();
    }

    private function has(User $user, Organisation $partner, string $role): bool
    {
        return RoleAssignment::query()->where('user_id', $user->id)->where('role', $role)->where('scope_type', 'organisation')->where('scope_id', $partner->id)->exists();
    }

    private function adult(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_if($user->isMinor(), 403);

        return $user;
    }

    /** @return array{0: User, 1: Organisation} */
    private function admin(Request $request): array
    {
        $user = $this->adult($request);
        $partner = $this->partnerOf($user);
        abort_unless($partner !== null && $this->has($user, $partner, 'partner_admin'), 403);

        return [$user, $partner];
    }

    private function member(Request $request, Referral $referral): User
    {
        $user = $this->adult($request);
        abort_unless($this->has($user, Organisation::query()->findOrFail($referral->organisation_id), 'partner_admin')
            || $this->has($user, Organisation::query()->findOrFail($referral->organisation_id), 'partner_agent'), 404);

        return $user;
    }
}
