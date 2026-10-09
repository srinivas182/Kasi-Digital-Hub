<?php

declare(strict_types=1);

namespace Modules\Work\Services;

use App\Support\Format\SaFormat;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Core\Documents\Generation\DocumentIssuer;
use Modules\Core\Documents\Models\Document;
use Modules\Core\Identity\Models\User;
use Modules\Work\Events\CvCreated;
use Modules\Work\Models\WorkCv;
use Modules\Work\Models\WorkEducation;
use Modules\Work\Models\WorkExperience;

/**
 * Builds the CV from the profile and issues it as a verifiable PDF. By default the CV leaves out
 * the ID number, photo, date of birth/age and street address (bias and identity-fraud risks);
 * age is shown only if the person chooses.
 */
final readonly class CvComposer
{
    public function __construct(private SeekerProfile $profiles, private DocumentIssuer $issuer) {}

    /** @return array<string, mixed> */
    public function content(User $user): array
    {
        $profile = $this->profiles->for($user);
        $verifiedDocs = Document::query()->where('user_id', $user->id)->where('status', Document::VERIFIED)->pluck('id')->all();

        return [
            'name' => $user->fullName(),
            'phone' => SaFormat::phone($user->phone),
            'email' => $user->email_verified_at !== null ? $user->email : null,
            'town' => $user->place_name ?? $user->homeHub?->municipality?->name,
            'age' => $profile->show_age_on_cv ? $user->date_of_birth->age : null,
            'headline' => $profile->headline,
            'summary' => $profile->summary,
            'licence' => $profile->drivers_licence && $profile->drivers_licence !== 'none' ? $profile->drivers_licence : null,
            'ownTransport' => $profile->own_transport,
            'experience' => WorkExperience::query()->where('user_id', $user->id)->orderBy('position')->orderByDesc('started')->get()
                ->map(static fn (WorkExperience $e): array => [
                    'title' => $e->title,
                    'organisation' => $e->organisation,
                    'place' => $e->place,
                    'kind' => $e->kind,
                    'period' => self::period($e),
                    'bullets' => $e->bullets ?: (trim((string) $e->description) === '' ? [] : [trim((string) $e->description)]),
                ])->all(),
            'education' => WorkEducation::query()->where('user_id', $user->id)->orderByDesc('year')->get()
                ->map(static fn (WorkEducation $e): array => [
                    'name' => $e->name, 'institution' => $e->institution, 'year' => $e->year, 'inProgress' => $e->in_progress,
                    'details' => $e->details, 'verified' => $e->document_id !== null && in_array($e->document_id, $verifiedDocs, true),
                ])->all(),
            'skills' => DB::table('work_skills')->where('user_id', $user->id)->orderBy('id')->pluck('name')->all(),
            'languages' => DB::table('work_languages')->where('user_id', $user->id)->orderBy('id')->get(['language', 'level'])
                ->map(static fn (object $l): array => ['language' => (string) $l->language, 'level' => (string) $l->level])->all(),
        ];
    }

    public function create(User $user, string $template, ?User $by = null): WorkCv
    {
        $content = $this->content($user);

        // One transaction: a failed PDF never leaves a CV version without its document.
        $cv = DB::transaction(function () use ($user, $template, $content, $by): WorkCv {
            $cv = WorkCv::query()->create(['user_id' => $user->id, 'template' => $template, 'content' => $content, 'created_by' => $by?->id]);
            $document = $this->issuer->issue(
                owner: $user,
                type: 'cv',
                title: 'CV - '.$user->fullName(),
                view: "work::cv.{$template}",
                data: ['cv' => $content],
                templateVersion: WorkCv::TEMPLATE_VERSION,
                subject: ['work_cv', $cv->id],
            );
            $cv->forceFill(['generated_document_id' => $document->id])->save();

            return $cv;
        });

        event(new CvCreated($user, $by?->id, ['template' => $template]));

        return $cv;
    }

    private static function period(WorkExperience $e): ?string
    {
        if ($e->started === null) {
            return $e->duration;
        }

        $from = CarbonImmutable::createFromFormat('Y-m', $e->started);
        $to = $e->ended !== null ? CarbonImmutable::createFromFormat('Y-m', $e->ended) : null;

        return ($from?->format('M Y') ?? $e->started).' - '.($to?->format('M Y') ?? 'now');
    }
}
