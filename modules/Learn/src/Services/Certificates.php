<?php

declare(strict_types=1);

namespace Modules\Learn\Services;

use Illuminate\Support\Facades\DB;
use Modules\Core\Achievements\AchievementSource;
use Modules\Core\Documents\Generation\DocumentIssuer;
use Modules\Core\Documents\Models\Document;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Notifications\Notifier;
use Modules\Learn\Models\Certificate;
use Modules\Learn\Models\Course;
use Modules\Learn\Models\Enrolment;
use Modules\Learn\Notifications\LearnerNotification;

/**
 * Certificates (ADR-023): issued on completion as verifiable PDFs. Short courses get a "Certificate of
 * completion"; accredited courses a "Statement of results" (official certification is by QCTO/SETA)
 * and need a verified ID first, so the name matches official records.
 */
final readonly class Certificates implements AchievementSource
{
    public const TEMPLATE_VERSION = 1;

    public function __construct(private DocumentIssuer $issuer, private Notifier $notifier, private AuditLogger $audit) {}

    public function issue(Enrolment $enrolment): ?Certificate
    {
        if ($enrolment->completed_at === null) {
            return null;
        }
        $active = Certificate::query()->where('enrolment_id', $enrolment->id)->whereNull('revoked_at')->first();
        if ($active !== null) {
            return $active;
        }

        $learner = User::query()->findOrFail($enrolment->user_id);
        $course = $enrolment->course()->with(['organisation', 'accreditation'])->firstOrFail();
        $accredited = $course->accredited();
        if ($accredited && ! Document::query()->where('user_id', $learner->id)->where('type', 'id_document')->where('status', Document::VERIFIED)->exists()) {
            $this->notifier->send($learner, new LearnerNotification('verify_id', ['title' => $course->title], '/account?tab=documents'));

            return null;
        }

        $snapshot = $enrolment->version()->firstOrFail()->snapshot;
        $settings = DB::table('learn_provider_settings')->where('organisation_id', $course->organisation_id)->first();
        $certificate = Certificate::query()->create(['enrolment_id' => $enrolment->id, 'kind' => $accredited ? 'statement' : 'completion', 'issued_at' => now()]);

        $document = $this->issuer->issue(
            owner: $learner,
            type: 'learn_certificate',
            title: ($accredited ? 'Statement of results - ' : 'Certificate of completion - ').$course->title,
            view: 'learn::certificates.certificate',
            data: [
                'kind' => $certificate->kind, 'name' => $learner->fullName(), 'course' => $course->title, 'provider' => $course->organisation->displayName(),
                'outcomes' => (array) ($snapshot['outcomes'] ?? []), 'hours' => $course->hours, 'completed' => $enrolment->completed_at,
                'nqf' => $accredited ? $course->nqf_level : null, 'credits' => $accredited ? $course->credits : null,
                'body' => $accredited ? $course->accreditation?->body : null, 'version' => (int) ($enrolment->version()->value('number') ?? 1),
                'signatory' => $settings?->signatory_name, 'signatoryTitle' => $settings?->signatory_title,
            ],
            templateVersion: self::TEMPLATE_VERSION,
            subject: ['learn_certificate', $certificate->id],
        );
        $certificate->forceFill(['generated_document_id' => $document->id])->save();
        $this->audit->record('learn.certificate_issued', $learner, meta: ['certificate' => $certificate->id, 'course' => $course->id]);
        $this->notifier->send($learner, new LearnerNotification('certificate', ['title' => $course->title], '/learn/certificates'));

        return $certificate;
    }

    public function revoke(Certificate $certificate, string $reason, ?User $by = null): void
    {
        if ($certificate->revoked_at !== null) {
            return;
        }
        $certificate->forceFill(['revoked_at' => now(), 'revoke_reason' => $reason])->save();
        if ($certificate->document !== null) {
            $this->issuer->revoke($certificate->document, $reason, $by ?? User::query()->findOrFail($certificate->enrolment->user_id));
        }
        $learner = User::query()->findOrFail($certificate->enrolment->user_id);
        $this->audit->record('learn.certificate_revoked', $learner, meta: ['certificate' => $certificate->id, 'reason' => $reason], actor: $by);
        $this->notifier->send($learner, new LearnerNotification('certificate_revoked', ['title' => $certificate->enrolment->course->title, 'reason' => $reason], '/learn/certificates'));
    }

    /** Certificates shown on the KasiWork CV (the learner can hide each one). */
    public function achievements(User $user): array
    {
        return array_values(Certificate::query()->with(['enrolment.course.organisation', 'document'])->whereNull('revoked_at')->where('show_on_cv', true)
            ->whereHas('enrolment', fn ($q) => $q->where('user_id', $user->id))->orderByDesc('issued_at')->get()
            ->map(static fn (Certificate $c): array => [
                'title' => $c->enrolment->course->title, 'issuer' => $c->enrolment->course->organisation->displayName(),
                'date' => $c->issued_at->format('M Y'), 'code' => $c->document?->verification_code,
            ])->all());
    }

    /** Courses completed but waiting for a certificate (e.g. ID not yet verified). */
    public function issuePending(): int
    {
        $count = 0;
        Enrolment::query()->whereNotNull('completed_at')
            ->whereDoesntHave('certificates', fn ($q) => $q->whereNull('revoked_at'))
            ->each(function (Enrolment $e) use (&$count): void {
                $count += $this->issue($e) !== null ? 1 : 0;
            });

        return $count;
    }

    public static function isAccredited(Course $course): bool
    {
        return $course->accredited();
    }
}
