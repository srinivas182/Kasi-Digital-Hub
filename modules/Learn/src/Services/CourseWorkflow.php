<?php

declare(strict_types=1);

namespace Modules\Learn\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Notifications\Notifier;
use Modules\Core\Search\RefreshSearchDocument;
use Modules\Core\Structure\Models\RoleAssignment;
use Modules\Learn\Events\CoursePublished;
use Modules\Learn\Events\CourseSubmitted;
use Modules\Learn\Events\CourseUnpublished;
use Modules\Learn\Models\Course;
use Modules\Learn\Models\CourseVersion;
use Modules\Learn\Notifications\CourseNotification;

/**
 * Course review and publishing (ADR-021): author submits -> provider admin approves -> KasiHub review
 * only for a provider's first course, accreditation claims and courses for 16-17-year-olds ->
 * published as a frozen, numbered version. Editing a published course prepares the next version.
 */
final readonly class CourseWorkflow
{
    public function __construct(
        private CourseChecks $checks,
        private ContentRenderer $renderer,
        private Notifier $notifier,
        private AuditLogger $audit,
    ) {}

    public function submit(Course $course, User $by): void
    {
        if (! in_array($course->status, ['draft', 'published', 'unpublished'], true) || ($course->status === 'published' && ! $course->changed_since_publish)) {
            throw new DomainException(__('learn.review.cannot_submit'));
        }
        if ($this->checks->blocking($course) !== []) {
            throw new DomainException(__('learn.review.fix_first'));
        }

        $course->forceFill(['status' => 'submitted', 'status_reason' => null])->save();
        $this->log($course, $by, 'submitted');
        event(new CourseSubmitted($by, null, ['course' => $course->id]));
        $this->tell($course, ['provider_admin'], 'submitted', '/learn/author/courses/'.$course->id);
    }

    public function providerApprove(Course $course, User $by): void
    {
        if ($course->status !== 'submitted') {
            throw new DomainException(__('learn.review.not_waiting'));
        }

        if ($this->needsKasiHubReview($course)) {
            $course->forceFill(['status' => 'in_review'])->save();
            $this->log($course, $by, 'approved', __('learn.review.sent_to_kasihub'));
            $this->tell($course, ['provider_admin', 'course_author'], 'in_review', '/learn/author/courses/'.$course->id);

            return;
        }

        $this->log($course, $by, 'approved');
        $this->publish($course, $by);
    }

    /** Provider admins or KasiHub reviewers send a course back with comments. */
    public function requestChanges(Course $course, User $by, string $comment): void
    {
        if (! in_array($course->status, ['submitted', 'in_review'], true)) {
            throw new DomainException(__('learn.review.not_waiting'));
        }

        $course->forceFill(['status' => $course->current_version_id !== null ? 'published' : 'draft', 'status_reason' => $comment])->save();
        $this->log($course, $by, 'changes', $comment);
        $this->tell($course, ['course_author', 'provider_admin'], 'changes', '/learn/author/courses/'.$course->id, ['comment' => $comment]);
    }

    /** KasiHub reviewer approves a course waiting in the review queue. */
    public function kasiHubApprove(Course $course, User $by): void
    {
        if ($course->status !== 'in_review') {
            throw new DomainException(__('learn.review.not_waiting'));
        }

        $this->log($course, $by, 'approved', 'KasiHub');
        $this->publish($course, $by);
    }

    public function needsKasiHubReview(Course $course): bool
    {
        $firstCourse = ! CourseVersion::query()->whereIn('course_id', Course::query()->where('organisation_id', $course->organisation_id)->select('id'))->exists();

        return $firstCourse || $course->accreditation_id !== null || $course->min_age < 18;
    }

    public function publish(Course $course, User $by): CourseVersion
    {
        $version = DB::transaction(function () use ($course, $by): CourseVersion {
            $snapshot = $this->snapshot($course);
            $version = CourseVersion::query()->create([
                'course_id' => $course->id,
                'number' => (int) CourseVersion::query()->where('course_id', $course->id)->max('number') + 1,
                'snapshot' => $snapshot, 'data_bytes' => $snapshot['data_bytes'], 'published_by' => $by->id, 'published_at' => now(),
            ]);
            $course->forceFill(['status' => 'published', 'status_reason' => null, 'current_version_id' => $version->id, 'changed_since_publish' => false])->save();

            return $version;
        });

        $this->log($course, $by, 'published', 'Version '.$version->number);
        $this->audit->record('learn.course_published', meta: ['course' => $course->id, 'version' => $version->number], actor: $by);
        event(new CoursePublished($by, null, ['course' => $course->id, 'version' => $version->number]));
        RefreshSearchDocument::dispatch('course', $course->id);
        $this->tell($course, ['course_author', 'provider_admin'], 'published', '/learn/courses/'.$course->slug);

        return $version;
    }

    public function unpublish(Course $course, User $by, string $reason): void
    {
        $course->forceFill(['status' => 'unpublished', 'status_reason' => $reason])->save();
        $this->log($course, $by, 'unpublished', $reason);
        $this->audit->record('learn.course_unpublished', meta: ['course' => $course->id, 'reason' => $reason], actor: $by);
        event(new CourseUnpublished($by, null, ['course' => $course->id]));
        RefreshSearchDocument::dispatch('course', $course->id);
        $this->tell($course, ['provider_admin', 'course_author'], 'unpublished', '/learn/author/courses/'.$course->id, ['reason' => $reason]);
    }

    /** @return array<string, mixed> */
    public function snapshot(Course $course): array
    {
        $course->load(['modules.lessons.media', 'organisation', 'accreditation']);
        $total = 0;
        $modules = [];

        foreach ($course->modules as $module) {
            $lessons = [];
            foreach ($module->lessons as $lesson) {
                $bytes = $this->checks->lessonBytes($lesson);
                $total += $bytes;
                $lessons[] = [
                    'id' => $lesson->id, 'title' => $lesson->title, 'kind' => $lesson->kind, 'minutes' => $lesson->minutes, 'preview' => $lesson->preview,
                    'html' => $this->renderer->html($lesson->content), 'transcript' => $lesson->transcript, 'bytes' => $bytes,
                    'media' => $lesson->media === null ? null : [
                        'id' => $lesson->media->id, 'kind' => $lesson->media->kind, 'duration' => $lesson->media->duration_seconds, 'name' => $lesson->media->original_name,
                        'versions' => collect($lesson->media->renditions ?? [])->map(static fn (array $r): int => $r['bytes'])->all(),
                    ],
                ];
            }
            $modules[] = ['title' => $module->title, 'lessons' => $lessons];
        }

        return [
            'title' => $course->title, 'summary' => $course->summary, 'outcomes' => $course->outcomes ?? [], 'level' => $course->level,
            'hours' => $course->hours, 'language' => $course->language, 'min_age' => $course->min_age, 'delivery' => $course->delivery,
            'nqf_level' => $course->accredited() ? $course->nqf_level : null, 'credits' => $course->accredited() ? $course->credits : null,
            'licence' => $course->licence, 'attribution' => $course->attribution, 'modules' => $modules, 'data_bytes' => $total,
        ];
    }

    private function log(Course $course, User $by, string $kind, ?string $body = null): void
    {
        DB::table('learn_reviews')->insert(['course_id' => $course->id, 'author_id' => $by->id, 'kind' => $kind, 'body' => $body, 'created_at' => now()]);
    }

    /**
     * @param  list<string>  $roles
     * @param  array<string, string>  $extra
     */
    private function tell(Course $course, array $roles, string $kind, string $link, array $extra = []): void
    {
        $ids = RoleAssignment::query()->whereIn('role', $roles)->where('scope_type', 'organisation')->where('scope_id', $course->organisation_id)->pluck('user_id');
        if ($course->created_by !== null && in_array('course_author', $roles, true)) {
            $ids->push($course->created_by);
        }
        User::query()->whereIn('id', $ids->unique())->get()
            ->each(fn (User $u) => $this->notifier->send($u, new CourseNotification($kind, ['title' => $course->title, ...$extra], $link)));
    }
}
