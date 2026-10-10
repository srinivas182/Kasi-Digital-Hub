<?php

declare(strict_types=1);

namespace Modules\Start\Http\Controllers;

use App\Support\Format\SaFormat;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Ai\AiBudget;
use Modules\Core\Documents\DocumentVault;
use Modules\Core\Documents\Generation\DocumentIssuer;
use Modules\Core\Documents\Generation\PdfRenderer;
use Modules\Core\Documents\Models\Document;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\Municipality;
use Modules\Start\Models\Business;
use Modules\Start\Models\Step;
use Modules\Start\Services\Businesses;
use Modules\Start\Services\Plans;
use Modules\Start\Services\StartSubject;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * KasiStart for entrepreneurs: businesses, the formalisation journey, the plan builder and PDFs.
 */
final class StartController
{
    public function __construct(private readonly StartSubject $subject, private readonly Businesses $businesses) {}

    public function index(Request $request): Response
    {
        [$user, $by] = $this->subject->resolve($request);

        return Inertia::render('Start/Index', [
            'person' => ['name' => $user->fullName(), 'assisted' => $by !== null],
            'businesses' => $this->businesses->of($user)->map(fn (Business $b): array => ['id' => $b->id, 'name' => $b->name, 'stage' => $b->stage, 'readiness' => $b->readiness,
                'next' => $this->businesses->readiness($b)['next']])->values(),
            'options' => self::options(),
            'cities' => Municipality::query()->where('category', '!=', 'district')->orderBy('name')->get(['id', 'name']),
            'defaultCity' => $user->municipality_id,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$user, $by] = $this->subject->resolve($request);
        $business = $this->businesses->create($user, $this->validated($request), $by);

        return to_route('start.business', $business)->with('status', __('start.created'));
    }

    public function show(Request $request, Business $business): Response
    {
        [$user, $by] = $this->member($request, $business);
        $done = DB::table('start_business_steps')->where('business_id', $business->id)->get()->keyBy('step_id');

        return Inertia::render('Start/Business', [
            'person' => ['name' => $user->fullName(), 'assisted' => $by !== null],
            'business' => [...$business->only(['id', 'name', 'sells', 'sector', 'stage', 'place_name', 'people', 'turnover_band', 'customers', 'readiness']),
                'legalForm' => $business->legal_form, 'municipalityId' => $business->municipality_id, 'formalised' => $business->formalised_at !== null],
            'role' => $this->businesses->role($business, $user),
            'readiness' => $this->businesses->readiness($business),
            'steps' => $this->businesses->steps($business)->map(static function (Step $s) use ($done): array {
                $d = $done[$s->id] ?? null;
                $doc = $d?->document_id !== null ? Document::query()->whereKey($d->document_id)->first(['id', 'status']) : null;

                return [...$s->only(['id', 'key', 'title', 'summary', 'why', 'needs', 'where', 'link', 'cost_note', 'duration', 'document_type']),
                    'lastChecked' => $s->last_checked_on?->toDateString(), 'done' => $d !== null, 'proofStatus' => $doc?->status];
            })->values(),
            'members' => DB::table('start_business_members')->join('users', 'users.id', '=', 'start_business_members.user_id')->where('business_id', $business->id)
                ->get(['users.id', 'users.first_name', 'users.last_name', 'users.phone', 'start_business_members.role'])
                ->map(static fn (object $m): array => ['id' => (string) $m->id, 'name' => $m->first_name.' '.$m->last_name, 'phone' => SaFormat::maskedPhone((string) $m->phone), 'role' => (string) $m->role]),
            'documents' => Document::query()->where('user_id', $user->id)->whereIn('status', [Document::UPLOADED, Document::VERIFIED])->get(['id', 'type', 'status']),
            'options' => self::options(),
            'cities' => Municipality::query()->where('category', '!=', 'district')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Business $business): RedirectResponse
    {
        $this->member($request, $business);
        $business->update($this->validated($request));
        $this->businesses->refresh($business);

        return back()->with('status', __('work.saved'));
    }

    public function guide(Request $request, Business $business): Response
    {
        $this->member($request, $business);

        return Inertia::render('Start/Guide', ['business' => ['id' => $business->id, 'name' => $business->name, 'legalForm' => $business->legal_form, 'people' => $business->people]]);
    }

    public function chooseForm(Request $request, Business $business): RedirectResponse
    {
        [$user, $by] = $this->member($request, $business);
        $data = $request->validate(['legal_form' => ['required', Rule::in(Business::FORMS)]]);
        $this->businesses->chooseForm($business, $data['legal_form'], $by ?? $user);

        return to_route('start.business', $business)->with('status', __('start.form_chosen'));
    }

    /** Mark a step done, with optional proof: a new upload or a document already in the vault. */
    public function done(Request $request, Business $business, Step $step, DocumentVault $vault): RedirectResponse
    {
        [$user, $by] = $this->member($request, $business);
        $data = $request->validate([
            'file' => ['nullable', File::types(['pdf', 'jpg', 'jpeg', 'png'])->max(10 * 1024)],
            'document_id' => ['nullable', 'string', Rule::exists('documents', 'id')->where('user_id', $user->id)],
        ]);
        $documentId = $data['document_id'] ?? null;
        if ($request->file('file') !== null && $step->document_type !== null) {
            $documentId = $vault->store($user, $request->file('file'), $step->document_type, uploadedBy: $by)->id;
        }

        try {
            $this->businesses->markDone($business, $step, $by ?? $user, $documentId);
        } catch (DomainException $e) {
            return back()->withErrors(['step' => $e->getMessage()]);
        }

        return back()->with('status', __('start.steps.done'));
    }

    public function undo(Request $request, Business $business, Step $step): RedirectResponse
    {
        [$user, $by] = $this->member($request, $business);
        $this->businesses->undo($business, $step, $by ?? $user);

        return back();
    }

    public function addMember(Request $request, Business $business): RedirectResponse
    {
        [$user, $by] = $this->owner($request, $business);
        $data = $request->validate(['phone' => ['required', 'string', 'max:20']]);
        $phone = SaFormat::normalisePhone($data['phone']);
        $person = $phone !== null ? User::query()->where('phone', $phone)->first() : null;
        if ($person === null) {
            return back()->withErrors(['phone' => __('work.team.no_account')]);
        }

        try {
            $this->businesses->addCoOwner($business, $person, $by ?? $user);
        } catch (DomainException $e) {
            return back()->withErrors(['phone' => $e->getMessage()]);
        }

        return back()->with('status', __('start.members.added', ['name' => $person->fullName()]));
    }

    public function removeMember(Request $request, Business $business, User $member): RedirectResponse
    {
        [$user, $by] = $this->owner($request, $business);
        $this->businesses->removeCoOwner($business, $member, $by ?? $user);

        return back()->with('status', __('start.members.removed'));
    }

    public function plan(Request $request, Business $business, Plans $plans): Response
    {
        [$user, $by] = $this->member($request, $business);

        return Inertia::render('Start/Plan', ['business' => ['id' => $business->id, 'name' => $business->name], 'person' => ['name' => $user->fullName(), 'assisted' => $by !== null],
            'plan' => $plans->for($business), 'sections' => Plans::SECTIONS, 'ai' => app(AiBudget::class)->enabled('start.plan')]);
    }

    public function savePlan(Request $request, Business $business, Plans $plans): RedirectResponse
    {
        [$user, $by] = $this->member($request, $business);
        $data = $request->validate([
            'sections' => ['array'], 'sections.*' => ['nullable', 'string', 'max:3000'],
            'numbers' => ['array'], 'numbers.*' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
        ]);
        $plans->save($business, array_map(static fn ($v): string => (string) $v, $data['sections'] ?? []), $data['numbers'] ?? [], $by ?? $user);

        return back()->with('status', __('work.saved'));
    }

    public function improve(Request $request, Business $business, Plans $plans): JsonResponse
    {
        [$user, $by] = $this->member($request, $business);
        $data = $request->validate(['section' => ['required', Rule::in(Plans::SECTIONS)], 'text' => ['required', 'string', 'min:20', 'max:3000']]);
        $result = $plans->improve($business, $data['section'], $data['text'], $by ?? $user);

        return response()->json($result['ok'] ? $result : ['ok' => false, 'message' => __('ai.fallback.'.($result['reason'] ?? 'unavailable'))]);
    }

    /** One-page business summary, verifiable (QR code). */
    public function summary(Request $request, Business $business, Plans $plans, DocumentIssuer $issuer): StreamedResponse
    {
        [$user] = $this->member($request, $business);
        $plan = $plans->for($business);
        $document = $issuer->issue(owner: $user, type: 'start_summary', title: 'Business summary - '.$business->name, view: 'start::summary',
            data: ['business' => $business, 'plan' => $plan, 'readiness' => $this->businesses->readiness($business), 'calc' => Plans::calculate(
                isset($plan['numbers']['cost']) ? (float) $plan['numbers']['cost'] : null, isset($plan['numbers']['markup']) ? (float) $plan['numbers']['markup'] : null,
                isset($plan['numbers']['price']) ? (float) $plan['numbers']['price'] : null, isset($plan['numbers']['fixed']) ? (float) $plan['numbers']['fixed'] : null),
                'steps' => $this->businesses->steps($business)->filter(fn (Step $s): bool => DB::table('start_business_steps')->where('business_id', $business->id)->where('step_id', $s->id)->exists())->pluck('title')->all()],
            templateVersion: 1, subject: ['start_business', $business->id]);

        return $issuer->download($document);
    }

    /** Pre-filled B-BBEE sworn affidavit to print and sign in front of a commissioner of oaths (not stored). */
    public function affidavit(Request $request, Business $business, PdfRenderer $renderer): \Illuminate\Http\Response
    {
        [$user] = $this->member($request, $business);
        /** @var view-string $view */
        $view = 'start::affidavit';
        $html = view($view, ['business' => $business, 'person' => $user])->render();

        return response($renderer->render($html), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="bbbee-affidavit.pdf"']);
    }

    /** @return array<string, list<string>> */
    public static function options(): array
    {
        return ['sectors' => Business::SECTORS, 'stages' => Business::STAGES, 'forms' => Business::FORMS, 'turnover' => Business::TURNOVER];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:140'],
            'sells' => ['nullable', 'string', 'max:300'],
            'sector' => ['required', Rule::in(Business::SECTORS)],
            'stage' => ['required', Rule::in(Business::STAGES)],
            'municipality_id' => ['nullable', 'integer', Rule::exists('municipalities', 'id')->whereNot('category', 'district')],
            'place_name' => ['nullable', 'string', 'max:120'],
            'people' => ['required', 'integer', 'min:1', 'max:10000'],
            'turnover_band' => ['nullable', Rule::in(Business::TURNOVER)],
            'customers' => ['nullable', 'string', 'max:300'],
        ]);
    }

    /** @return array{0: User, 1: User|null} */
    private function member(Request $request, Business $business): array
    {
        [$user, $by] = $this->subject->resolve($request);
        abort_if($this->businesses->role($business, $user) === null, 404);

        return [$user, $by];
    }

    /** @return array{0: User, 1: User|null} */
    private function owner(Request $request, Business $business): array
    {
        [$user, $by] = $this->member($request, $business);
        abort_unless($this->businesses->role($business, $user) === 'owner', 403);

        return [$user, $by];
    }
}
