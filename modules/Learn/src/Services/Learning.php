<?php

declare(strict_types=1);

namespace Modules\Learn\Services;

use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Documents\DocumentVault;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Identity\Services\ConsentService;
use Modules\Core\Notifications\Notifier;
use Modules\Core\Structure\Models\RoleAssignment;
use Modules\Learn\Events\AssignmentAssessed;
use Modules\Learn\Events\CourseCompleted;
use Modules\Learn\Events\Enrolled;
use Modules\Learn\Events\LessonCompleted;
use Modules\Learn\Events\QuizPassed;
use Modules\Learn\Models\Course;
use Modules\Learn\Models\Enrolment;
use Modules\Learn\Models\Submission;
use Modules\Learn\Notifications\LearnerNotification;

/**
 * Enrolment, progress, quizzes, assignments and completion (ADR-022). Everything works on the frozen
 * course version the learner enrolled in. Progress updates are idempotent ("most progress wins"), so
 * offline events can be sent more than once safely.
 */
final readonly class Learning
{
    public const RETRY_WAIT_HOURS = 24;

    public function __construct(
        private ConsentService $consents,
        private Notifier $notifier,
        private AuditLogger $audit,
        private DocumentVault $vault,
    ) {}

    public function enrol(User $user, Course $course): Enrolment
    {
        if ($course->status !== 'published' || $course->current_version_id === null) {
            throw new DomainException(__('learn.enrol.not_open'));
        }
        if ($user->isMinor() && $course->min_age >= 18) {
            throw new DomainException(__('learn.enrol.age'));
        }
        if (! ($this->consents->state($user)['learning_records'] ?? false)) {
            throw new DomainException('consent');
        }

        $enrolment = Enrolment::query()->firstOrNew(['user_id' => $user->id, 'course_id' => $course->id]);
        if ($enrolment->exists && $enrolment->status !== 'left') {
            return $enrolment;
        }

        $new = ! $enrolment->exists;
        $enrolment->forceFill(['version_id' => $new ? $course->current_version_id : $enrolment->version_id, 'status' => 'active', 'left_at' => null])->save();
        if ($new) {
            event(new Enrolled($user, null, ['course' => $course->id, 'enrolment' => $enrolment->id]));
            $this->audit->record('learn.enrolled', $user, meta: ['course' => $course->id]);
        }
        DB::table('learn_saved_courses')->where('user_id', $user->id)->where('course_id', $course->id)->delete();

        return $enrolment;
    }

    public function leave(Enrolment $enrolment): void
    {
        $enrolment->forceFill(['status' => 'left', 'left_at' => now()])->save();
    }

    /** Move to the latest published version; progress is kept for lessons that still exist. */
    public function switchVersion(Enrolment $enrolment): void
    {
        $course = $enrolment->course;
        if ($course->current_version_id === null || $course->current_version_id === $enrolment->version_id) {
            return;
        }
        $enrolment->forceFill(['version_id' => $course->current_version_id])->save();
        $enrolment->unsetRelation('version');
        DB::table('learn_progress')->where('enrolment_id', $enrolment->id)->whereNotIn('lesson_id', array_keys($this->lessons($enrolment)))->delete();
        $this->recalculate($enrolment);
    }

    /** @return array<string, array<string, mixed>> lesson id => lesson in the enrolled version */
    public function lessons(Enrolment $enrolment): array
    {
        $lessons = [];
        foreach ((array) ($enrolment->version->snapshot['modules'] ?? []) as $module) {
            foreach ((array) ($module['lessons'] ?? []) as $lesson) {
                $lessons[(string) $lesson['id']] = $lesson;
            }
        }

        return $lessons;
    }

    /** @return array<string, mixed> */
    public function lesson(Enrolment $enrolment, string $lessonId): array
    {
        $lesson = $this->lessons($enrolment)[$lessonId] ?? null;
        if ($lesson === null) {
            throw new DomainException(__('learn.player.no_lesson'));
        }

        return $lesson;
    }

    /**
     * Apply a progress event (online or synced from offline). Idempotent: completing twice, an older
     * position or a lower practice score changes nothing.
     *
     * @param  'completed'|'position'|'practice'  $type
     */
    public function record(Enrolment $enrolment, string $lessonId, string $type, int $value = 0): void
    {
        $lesson = $this->lesson($enrolment, $lessonId);
        if ($type === 'completed' && in_array($lesson['kind'], ['quiz', 'assignment'], true) && ($lesson['quiz']['graded'] ?? true)) {
            return; // graded work completes only through the server
        }

        $row = DB::table('learn_progress')->where('enrolment_id', $enrolment->id)->where('lesson_id', $lessonId)->first();
        $values = match ($type) {
            'completed' => ['completed_at' => $row->completed_at ?? now()],
            'position' => ['position' => max((int) ($row->position ?? 0), max(0, $value))],
            'practice' => ['practice_score' => max((int) ($row->practice_score ?? 0), min(100, max(0, $value))), 'completed_at' => $row->completed_at ?? now()],
        };
        DB::table('learn_progress')->updateOrInsert(['enrolment_id' => $enrolment->id, 'lesson_id' => $lessonId], [...$values, 'updated_at' => now(), 'created_at' => $row->created_at ?? now()]);
        $enrolment->forceFill(['last_lesson_id' => $lessonId])->save();

        if ($row?->completed_at === null && isset($values['completed_at'])) {
            event(new LessonCompleted(User::query()->findOrFail($enrolment->user_id), null, ['course' => $enrolment->course_id, 'lesson' => $lessonId]));
        }
        $this->recalculate($enrolment);
    }

    /**
     * Mark a graded quiz on the server.
     *
     * @param  array<int|string, list<int>>  $answers  question id => chosen option indexes
     * @return array{score: int, passed: bool, results: list<array{id: int|string, correct: bool, explanation: string|null}>, attemptsLeft: int}
     */
    public function attemptQuiz(Enrolment $enrolment, string $lessonId, array $answers): array
    {
        $lesson = $this->lesson($enrolment, $lessonId);
        $quiz = $lesson['quiz'] ?? null;
        if ($lesson['kind'] !== 'quiz' || ! is_array($quiz) || ! ($quiz['graded'] ?? true)) {
            throw new DomainException(__('learn.player.no_lesson'));
        }

        $previous = DB::table('learn_quiz_attempts')->where('enrolment_id', $enrolment->id)->where('lesson_id', $lessonId);
        if ((clone $previous)->where('passed', true)->exists()) {
            throw new DomainException(__('learn.quiz.already_passed'));
        }
        $recent = (clone $previous)->where('created_at', '>=', now()->subHours(self::RETRY_WAIT_HOURS))->count();
        if ($recent >= (int) $quiz['max_attempts']) {
            throw new DomainException(__('learn.quiz.wait', ['hours' => self::RETRY_WAIT_HOURS]));
        }

        $results = [];
        $right = 0;
        foreach ((array) $quiz['questions'] as $question) {
            $correct = array_keys(array_filter(array_map(static fn (array $o): bool => (bool) ($o['correct'] ?? false), (array) $question['options'])));
            $chosen = array_values(array_unique(array_map('intval', (array) ($answers[$question['id']] ?? []))));
            sort($chosen);
            $isRight = $chosen === $correct;
            $right += $isRight ? 1 : 0;
            $results[] = ['id' => $question['id'], 'correct' => $isRight, 'explanation' => $question['explanation'] ?? null];
        }

        $count = max(1, count($results));
        $score = (int) round(100 * $right / $count);
        $passed = $score >= (int) $quiz['pass_mark'];

        DB::table('learn_quiz_attempts')->insert(['id' => (string) Str::ulid(), 'enrolment_id' => $enrolment->id, 'lesson_id' => $lessonId,
            'answers' => json_encode($answers), 'score' => $score, 'passed' => $passed, 'created_at' => now()]);

        if ($passed) {
            DB::table('learn_progress')->updateOrInsert(['enrolment_id' => $enrolment->id, 'lesson_id' => $lessonId], ['completed_at' => now(), 'updated_at' => now(), 'created_at' => now()]);
            event(new QuizPassed(User::query()->findOrFail($enrolment->user_id), null, ['course' => $enrolment->course_id, 'lesson' => $lessonId, 'score' => $score]));
            $this->recalculate($enrolment);
        }

        return ['score' => $score, 'passed' => $passed, 'results' => $results, 'attemptsLeft' => max(0, (int) $quiz['max_attempts'] - $recent - 1)];
    }

    public function submit(Enrolment $enrolment, string $lessonId, ?string $text, ?UploadedFile $file, User $user): Submission
    {
        $lesson = $this->lesson($enrolment, $lessonId);
        $assignment = $lesson['assignment'] ?? null;
        if ($lesson['kind'] !== 'assignment' || ! is_array($assignment)) {
            throw new DomainException(__('learn.player.no_lesson'));
        }

        $previous = Submission::query()->where('enrolment_id', $enrolment->id)->where('lesson_id', $lessonId)->latest('created_at')->get();
        if ($previous->contains(fn (Submission $s): bool => in_array($s->status, ['submitted', 'competent'], true))) {
            throw new DomainException(__('learn.assignment.waiting'));
        }
        if ($previous->count() > (int) $assignment['max_resubmissions']) {
            throw new DomainException(__('learn.assignment.no_more'));
        }
        if (trim((string) $text) === '' && $file === null) {
            throw new DomainException(__('learn.assignment.empty'));
        }

        $document = $file !== null ? $this->vault->store($user, $file, 'learning_evidence') : null;
        $submission = Submission::query()->create(['enrolment_id' => $enrolment->id, 'lesson_id' => $lessonId, 'text' => $text !== null ? mb_substr(trim($text), 0, 10000) : null,
            'document_id' => $document?->id, 'attempt' => $previous->count() + 1, 'status' => 'submitted']);

        $assessors = RoleAssignment::query()->whereIn('role', ['assessor_moderator', 'provider_admin'])->where('scope_type', 'organisation')->where('scope_id', $enrolment->course->organisation_id)->pluck('user_id');
        User::query()->whereIn('id', $assessors)->get()->each(fn (User $a) => $this->notifier->send($a, new LearnerNotification('to_assess', ['title' => $enrolment->course->title], '/learn/assess')));

        return $submission;
    }

    /** @param list<string> $criteriaMet */
    public function assess(Submission $submission, User $assessor, bool $competent, array $criteriaMet, string $feedback): void
    {
        if ($submission->status !== 'submitted') {
            throw new DomainException(__('learn.assignment.already_assessed'));
        }

        $submission->forceFill(['status' => $competent ? 'competent' : 'not_yet', 'rubric' => $criteriaMet, 'feedback' => $feedback, 'assessor_id' => $assessor->id, 'assessed_at' => now()])->save();
        $enrolment = $submission->enrolment;
        $learner = User::query()->findOrFail($enrolment->user_id);
        $this->audit->record('learn.assessed', $learner, meta: ['submission' => $submission->id, 'competent' => $competent], actor: $assessor);
        event(new AssignmentAssessed($learner, $assessor->id, ['course' => $enrolment->course_id, 'lesson' => $submission->lesson_id, 'competent' => $competent]));

        app(Moderation::class)->consider($submission);
        if ($competent) {
            DB::table('learn_progress')->updateOrInsert(['enrolment_id' => $enrolment->id, 'lesson_id' => $submission->lesson_id], ['completed_at' => now(), 'updated_at' => now(), 'created_at' => now()]);
            $this->recalculate($enrolment);
        }
        $this->notifier->send($learner, new LearnerNotification($competent ? 'competent' : 'not_yet', ['title' => $enrolment->course->title],
            "/learn/my/{$enrolment->id}/lessons/{$submission->lesson_id}"));
    }

    /** Progress = required lessons finished; complete when all are (practice quizzes are optional). */
    public function recalculate(Enrolment $enrolment): void
    {
        $required = array_keys(array_filter($this->lessons($enrolment), static fn (array $l): bool => ! ($l['kind'] === 'quiz' && ! ($l['quiz']['graded'] ?? true))));
        $done = DB::table('learn_progress')->where('enrolment_id', $enrolment->id)->whereIn('lesson_id', $required)->whereNotNull('completed_at')->count();
        $progress = $required === [] ? 0 : (int) floor(100 * $done / count($required));

        $attendance = app(Cohorts::class)->attendance($enrolment);
        $attendanceOk = $attendance === null || $attendance['percent'] >= (int) $enrolment->course->attendance_percent;

        $enrolment->forceFill(['progress' => $progress])->save();
        if ($progress === 100 && $attendanceOk && $enrolment->completed_at === null) {
            $enrolment->forceFill(['status' => 'completed', 'completed_at' => CarbonImmutable::now()])->save();
            $learner = User::query()->findOrFail($enrolment->user_id);
            event(new CourseCompleted($learner, null, ['course' => $enrolment->course_id, 'enrolment' => $enrolment->id]));
            $this->notifier->send($learner, new LearnerNotification('completed', ['title' => $enrolment->course->title], "/learn/my/{$enrolment->id}"));
            app(Certificates::class)->issue($enrolment);
        }
    }

    /**
     * What a learner's phone may see of a lesson: graded quizzes lose their answers and feedback.
     *
     * @param  array<string, mixed>  $lesson
     * @return array<string, mixed>
     */
    public static function forLearner(array $lesson): array
    {
        if (($lesson['kind'] ?? null) === 'quiz' && ($lesson['quiz']['graded'] ?? true) && isset($lesson['quiz']['questions'])) {
            $lesson['quiz']['questions'] = array_map(static function (array $q): array {
                $q['options'] = array_map(static fn (array $o): array => ['text' => $o['text']], (array) $q['options']);
                unset($q['explanation']);

                return $q;
            }, (array) $lesson['quiz']['questions']);
        }

        return $lesson;
    }
}
