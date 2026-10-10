<?php

declare(strict_types=1);

namespace Modules\Learn\Services;

use Modules\Learn\Models\Assignment;
use Modules\Learn\Models\Course;
use Modules\Learn\Models\Lesson;
use Modules\Learn\Models\Media;
use Modules\Learn\Models\Question;
use Modules\Learn\Models\Quiz;

/**
 * Checks before a course can be submitted, and the data a learner needs to download.
 * Blocking problems stop submission; "long_sentences" is advice only.
 */
final readonly class CourseChecks
{
    public const ADVICE_ONLY = ['long_sentences'];

    public function __construct(private ContentRenderer $renderer) {}

    /**
     * @return list<array{lesson: string|null, title: string, code: string}>
     */
    public function problems(Course $course): array
    {
        $problems = [];
        if (trim((string) $course->summary) === '' || count($course->outcomes ?? []) === 0) {
            $problems[] = ['lesson' => null, 'title' => $course->title, 'code' => 'course_details'];
        }

        $lessons = Lesson::query()->with('media')->where('course_id', $course->id)->get();
        if ($lessons->isEmpty()) {
            $problems[] = ['lesson' => null, 'title' => $course->title, 'code' => 'no_lessons'];
        }

        foreach ($lessons as $lesson) {
            $codes = match ($lesson->kind) {
                'text' => $this->renderer->problems($lesson->content),
                'video', 'audio' => array_values(array_filter([
                    $lesson->media === null ? 'no_media' : null,
                    $lesson->media?->status === 'failed' ? 'media_failed' : null,
                    trim((string) $lesson->transcript) === '' ? 'transcript' : null,
                ])),
                'download' => $lesson->media === null ? ['no_media'] : [],
                'quiz' => $this->quizProblems($lesson->id),
                'assignment' => Assignment::query()->where('lesson_id', $lesson->id)->whereNot('instructions', '')->exists() ? [] : ['no_instructions'],
                default => [],
            };
            foreach ($codes as $code) {
                $problems[] = ['lesson' => $lesson->id, 'title' => $lesson->title, 'code' => $code];
            }
        }

        return $problems;
    }

    /** @return list<string> */
    private function quizProblems(string $lessonId): array
    {
        $quiz = Quiz::query()->with('questions')->where('lesson_id', $lessonId)->first();
        if ($quiz === null || $quiz->questions->isEmpty()) {
            return ['no_questions'];
        }

        return $quiz->questions->contains(static fn (Question $q): bool => collect($q->options)->where('correct', true)->isEmpty() || count($q->options) < 2)
            ? ['question_answers'] : [];
    }

    /** @return list<array{lesson: string|null, title: string, code: string}> */
    public function blocking(Course $course): array
    {
        return array_values(array_filter($this->problems($course), static fn (array $p): bool => ! in_array($p['code'], self::ADVICE_ONLY, true)));
    }

    /** Estimated bytes a learner downloads for a lesson (text, pictures, standard video or audio, downloads). */
    public function lessonBytes(Lesson $lesson): int
    {
        $bytes = strlen($this->renderer->html($lesson->content)) + strlen((string) $lesson->transcript);
        $images = $this->renderer->imageIds($lesson->content);
        if ($images !== []) {
            $bytes += (int) Media::query()->whereIn('id', $images)->get()->sum(static fn (Media $m): int => $m->deliveredBytes());
        }
        if ($lesson->media !== null) {
            $bytes += $lesson->media->deliveredBytes();
        }

        return $bytes;
    }
}
