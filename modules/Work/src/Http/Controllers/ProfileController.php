<?php

declare(strict_types=1);

namespace Modules\Work\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Ai\AiBudget;
use Modules\Core\Ai\Speech\VoiceNotes;
use Modules\Core\Documents\Models\Document;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Work\Models\WorkEducation;
use Modules\Work\Models\WorkExperience;
use Modules\Work\Services\SeekerProfile;

/**
 * The six-step job profile. Each step saves on its own.
 */
final class ProfileController extends WorkController
{
    public const STEPS = ['about', 'experience', 'education', 'skills', 'looking_for', 'visibility'];

    public const WORK_TYPES = ['full_time', 'part_time', 'piece_work', 'learnership', 'internship'];

    public const SECTORS = ['retail', 'hospitality', 'agriculture', 'construction', 'cleaning', 'security', 'transport', 'manufacturing', 'office', 'call_centre', 'health_care', 'education', 'beauty', 'it', 'other'];

    public const LICENCES = ['none', 'A1', 'A', 'B', 'C1', 'C', 'EB', 'EC1', 'EC'];

    public const LANGUAGES = ['English', 'isiZulu', 'isiXhosa', 'Afrikaans', 'Sepedi', 'Setswana', 'Sesotho', 'Xitsonga', 'siSwati', 'Tshivenda', 'isiNdebele', 'South African Sign Language'];

    /** Suggestions only - people can type any skill. */
    public const SKILL_SUGGESTIONS = ['Customer service', 'Cash handling', 'Till operation', 'Stock counting', 'Shelf packing', 'Cleaning', 'Cooking', 'Food safety', 'Waiter / waitress', 'Barista', 'Microsoft Word', 'Microsoft Excel', 'Email and internet', 'Typing', 'Data capturing', 'Call centre', 'Sales', 'Driving', 'Forklift', 'General labour', 'Bricklaying', 'Painting', 'Plumbing basics', 'Electrical basics', 'Welding', 'Gardening', 'Farm work', 'Child care', 'Elderly care', 'First aid', 'Security', 'Hairdressing', 'Nail technician', 'Sewing', 'Social media', 'Teamwork', 'Time keeping', 'Problem solving', 'Bookkeeping basics', 'Selling at a stall'];

    public function show(Request $request, SeekerProfile $profiles, AiBudget $ai): Response
    {
        [$user, $by] = $this->who($request);
        $profile = $profiles->for($user);
        $step = in_array($request->query('step'), self::STEPS, true) ? (string) $request->query('step') : 'about';

        return Inertia::render('Work/Profile', [
            'step' => $step,
            'person' => ['name' => $user->fullName(), 'assisted' => $by !== null],
            'profile' => [
                'headline' => $profile->headline, 'summary' => $profile->summary, 'driversLicence' => $profile->drivers_licence ?? 'none',
                'ownTransport' => $profile->own_transport, 'workTypes' => $profile->work_types ?? [], 'sectors' => $profile->sectors ?? [],
                'maxTravelKm' => $profile->max_travel_km, 'availableFrom' => $profile->available_from ?? 'now',
                'showAge' => $profile->show_age_on_cv, 'completeness' => $profile->completeness,
            ],
            'parts' => $profile->exists ? $profiles->parts($user, $profile) : [],
            'experiences' => WorkExperience::query()->where('user_id', $user->id)->orderBy('position')->orderByDesc('started')->get()
                ->map(static fn (WorkExperience $e): array => [
                    'id' => $e->id, 'kind' => $e->kind, 'title' => $e->title, 'organisation' => $e->organisation, 'place' => $e->place,
                    'started' => $e->started, 'ended' => $e->ended, 'duration' => $e->duration, 'description' => $e->description,
                    'bullets' => $e->bullets ?? [], 'suggestedBullets' => $e->suggested_bullets ?? [],
                ]),
            'education' => WorkEducation::query()->where('user_id', $user->id)->orderByDesc('year')->get()
                ->map(static fn (WorkEducation $e): array => [
                    'id' => $e->id, 'kind' => $e->kind, 'name' => $e->name, 'institution' => $e->institution, 'year' => $e->year,
                    'inProgress' => $e->in_progress, 'details' => $e->details, 'documentId' => $e->document_id,
                ]),
            'skills' => DB::table('work_skills')->where('user_id', $user->id)->orderBy('id')->pluck('name'),
            'languages' => DB::table('work_languages')->where('user_id', $user->id)->orderBy('id')->get(['language', 'level']),
            'documents' => Document::query()->where('user_id', $user->id)->whereIn('status', [Document::UPLOADED, Document::VERIFIED])->get()
                ->map(static fn (Document $d): array => ['id' => $d->id, 'type' => $d->type, 'status' => $d->status]),
            'jobMatchingConsent' => (bool) DB::table('consents')->where('user_id', $user->id)->where('purpose', 'job_matching')->latest('created_at')->value('granted'),
            'options' => [
                'kinds' => WorkExperience::KINDS, 'educationKinds' => WorkEducation::KINDS, 'workTypes' => self::WORK_TYPES, 'sectors' => self::SECTORS,
                'licences' => self::LICENCES, 'languages' => self::LANGUAGES, 'skillSuggestions' => self::SKILL_SUGGESTIONS,
            ],
            'ai' => ['writer' => $ai->enabled('work.cv_writer'), 'voiceLanguages' => VoiceNotes::languages()],
        ]);
    }

    public function about(Request $request, SeekerProfile $profiles): RedirectResponse
    {
        [$user, $by] = $this->who($request);
        $data = $request->validate([
            'headline' => ['required', 'string', 'max:120'],
            'summary' => ['required', 'string', 'max:1200'],
            'drivers_licence' => ['required', Rule::in(self::LICENCES)],
            'own_transport' => ['boolean'],
            'show_age_on_cv' => ['boolean'],
        ]);
        $profiles->save($user, $data, $by);

        return to_route('work.profile', ['step' => 'experience'])->with('status', __('work.saved'));
    }

    public function lookingFor(Request $request, SeekerProfile $profiles): RedirectResponse
    {
        [$user, $by] = $this->who($request);
        $data = $request->validate([
            'work_types' => ['array'], 'work_types.*' => [Rule::in(self::WORK_TYPES)],
            'sectors' => ['array', 'max:6'], 'sectors.*' => [Rule::in(self::SECTORS)],
            'max_travel_km' => ['nullable', 'integer', 'min:1', 'max:500'],
            'available_from' => ['required', Rule::in(['now', '2_weeks', '1_month'])],
        ]);
        $profiles->save($user, $data, $by);

        return to_route('work.profile', ['step' => 'visibility'])->with('status', __('work.saved'));
    }

    public function skills(Request $request, SeekerProfile $profiles, AuditLogger $audit): RedirectResponse
    {
        [$user, $by] = $this->who($request);
        $data = $request->validate([
            'skills' => ['array', 'max:20'], 'skills.*' => ['string', 'min:2', 'max:60'],
            'languages' => ['array', 'max:12'], 'languages.*.language' => ['required', Rule::in(self::LANGUAGES)], 'languages.*.level' => ['required', Rule::in(['basic', 'good', 'fluent'])],
        ]);

        /** @var list<string> $skillInput */
        $skillInput = (array) ($data['skills'] ?? []);
        /** @var list<array{language: string, level: string}> $languageInput */
        $languageInput = (array) ($data['languages'] ?? []);

        DB::transaction(function () use ($user, $skillInput, $languageInput): void {
            DB::table('work_skills')->where('user_id', $user->id)->delete();
            $skills = collect($skillInput)->map(static fn (string $s): string => mb_substr(trim($s), 0, 60))->filter()->unique(static fn (string $s): string => mb_strtolower($s));
            DB::table('work_skills')->insert($skills->map(static fn (string $s): array => ['user_id' => $user->id, 'name' => $s])->values()->all());

            DB::table('work_languages')->where('user_id', $user->id)->delete();
            DB::table('work_languages')->insert(collect($languageInput)->unique('language')
                ->map(static fn (array $l): array => ['user_id' => $user->id, 'language' => $l['language'], 'level' => $l['level']])->values()->all());
        });
        $audit->record('work.skills_saved', $user, actor: $by);
        $profiles->save($user, [], $by);

        return to_route('work.profile', ['step' => 'looking_for'])->with('status', __('work.saved'));
    }
}
