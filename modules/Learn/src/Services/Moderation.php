<?php

declare(strict_types=1);

namespace Modules\Learn\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Notifications\Notifier;
use Modules\Learn\Models\Certificate;
use Modules\Learn\Models\Submission;
use Modules\Learn\Notifications\LearnerNotification;

/**
 * Internal moderation (ADR-023). Sample: an assessor's first 5 assessments; every competent decision on
 * accredited courses; and every 10th assessment per assessor, course and month (so at least 10% and at
 * least one a month). Moderators never moderate their own work. Disagreement sends the work back to the
 * assessor; a competent outcome that is overturned withdraws the certificate until it is competent again.
 */
final readonly class Moderation
{
    public const NEW_ASSESSOR = 5;

    public const EVERY = 10;

    public function __construct(private Certificates $certificates, private Notifier $notifier, private AuditLogger $audit, private Learning $learning) {}

    /** Called after every assessment: does this one go into the moderation sample? */
    public function consider(Submission $submission): ?string
    {
        $submission->loadMissing('enrolment.course.accreditation');
        $course = $submission->enrolment->course;
        $byAssessor = DB::table('learn_submissions')->join('learn_enrolments', 'learn_enrolments.id', '=', 'learn_submissions.enrolment_id')
            ->join('learn_courses', 'learn_courses.id', '=', 'learn_enrolments.course_id')
            ->where('learn_submissions.assessor_id', $submission->assessor_id)->where('learn_courses.organisation_id', $course->organisation_id)
            ->whereNotNull('learn_submissions.assessed_at');
        $thisMonth = (clone $byAssessor)->where('learn_enrolments.course_id', $course->id)->where('learn_submissions.assessed_at', '>=', now()->startOfMonth())->count();

        $reason = match (true) {
            (clone $byAssessor)->count() <= self::NEW_ASSESSOR => 'new_assessor',
            $course->accredited() && $submission->status === 'competent' => 'accredited',
            $thisMonth % self::EVERY === 1 => 'sample',
            default => null,
        };

        if ($reason !== null) {
            DB::table('learn_moderations')->insertOrIgnore(['submission_id' => $submission->id, 'reason' => $reason, 'status' => 'pending', 'created_at' => now()]);
        }

        return $reason;
    }

    public function decide(int $moderationId, User $moderator, bool $agree, string $notes): void
    {
        $moderation = DB::table('learn_moderations')->where('id', $moderationId)->first();
        $submission = $moderation !== null ? Submission::query()->with('enrolment.course')->whereKey($moderation->submission_id)->first() : null;
        if ($moderation === null || $submission === null || $moderation->status !== 'pending') {
            throw new DomainException(__('learn.moderation.not_open'));
        }
        if ($submission->assessor_id === $moderator->id) {
            throw new DomainException(__('learn.moderation.own_work'));
        }

        DB::table('learn_moderations')->where('id', $moderationId)->update(['status' => $agree ? 'agreed' : 'disagreed', 'moderator_id' => $moderator->id, 'notes' => $notes, 'decided_at' => now()]);
        $learner = User::query()->findOrFail($submission->enrolment->user_id);
        $this->audit->record('learn.moderated', $learner, meta: ['submission' => $submission->id, 'agree' => $agree], actor: $moderator);
        if ($agree) {
            return;
        }

        // Back to the assessor for re-assessment, with the moderator's notes.
        $wasCompetent = $submission->status === 'competent';
        $submission->forceFill(['status' => 'submitted', 'feedback' => trim(($submission->feedback ?? '')."\n\n[Moderator] ".$notes)])->save();
        if ($wasCompetent) {
            $enrolment = $submission->enrolment;
            DB::table('learn_progress')->where('enrolment_id', $enrolment->id)->where('lesson_id', $submission->lesson_id)->update(['completed_at' => null]);
            if ($enrolment->completed_at !== null) {
                $enrolment->forceFill(['status' => 'active', 'completed_at' => null])->save();
                Certificate::query()->where('enrolment_id', $enrolment->id)->whereNull('revoked_at')->get()
                    ->each(fn (Certificate $c) => $this->certificates->revoke($c, __('learn.moderation.revoke_reason'), $moderator));
            }
            $this->learning->recalculate($enrolment->refresh());
            $this->notifier->send($learner, new LearnerNotification('reassessment', ['title' => $enrolment->course->title], "/learn/my/{$enrolment->id}"));
        }
        if ($submission->assessor_id !== null && ($assessor = User::query()->whereKey($submission->assessor_id)->first()) !== null) {
            $this->notifier->send($assessor, new LearnerNotification('to_reassess', ['title' => $submission->enrolment->course->title], '/learn/assess/'.$submission->id));
        }
    }
}
