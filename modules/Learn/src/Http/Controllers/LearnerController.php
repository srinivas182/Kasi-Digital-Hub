<?php

declare(strict_types=1);

namespace Modules\Learn\Http\Controllers;

use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\ConsentService;
use Modules\Learn\Models\Course;
use Modules\Learn\Models\Enrolment;
use Modules\Learn\Models\Submission;
use Modules\Learn\Services\Learning;

/**
 * Learners: enrol, my learning, the player, progress (also synced from offline), quizzes,
 * assignments, offline downloads.
 */
final class LearnerController
{
    public function __construct(private readonly Learning $learning) {}

    public function enrol(Request $request, string $slug, ConsentService $consents): RedirectResponse
    {
        $user = $this->user($request);
        $course = Course::query()->where('slug', $slug)->where('status', 'published')->firstOrFail();
        if ($request->boolean('consent')) {
            $consents->record($user, ['learning_records' => true]);
        }

        try {
            $enrolment = $this->learning->enrol($user, $course);
        } catch (DomainException $e) {
            return back()->withErrors($e->getMessage() === 'consent' ? ['consent' => __('learn.enrol.consent_needed')] : ['enrol' => $e->getMessage()]);
        }

        return to_route('learn.my.course', $enrolment)->with('status', __('learn.enrol.done'));
    }

    public function index(Request $request): Response
    {
        $user = $this->user($request);

        return Inertia::render('Learn/My/Index', [
            'enrolments' => Enrolment::query()->with(['course.organisation'])->where('user_id', $user->id)->where('status', '!=', 'left')->latest('updated_at')->get()
                ->map(static fn (Enrolment $e): array => ['id' => $e->id, 'title' => $e->course->title, 'provider' => $e->course->organisation->displayName(),
                    'progress' => $e->progress, 'status' => $e->status, 'lastLesson' => $e->last_lesson_id]),
        ]);
    }

    public function course(Request $request, Enrolment $enrolment): Response
    {
        $this->own($request, $enrolment);
        $enrolment->load(['course', 'version']);
        $progress = DB::table('learn_progress')->where('enrolment_id', $enrolment->id)->get()->keyBy('lesson_id');
        $snapshot = $enrolment->version->snapshot;

        return Inertia::render('Learn/My/Course', [
            'enrolment' => ['id' => $enrolment->id, 'progress' => $enrolment->progress, 'status' => $enrolment->status, 'lastLesson' => $enrolment->last_lesson_id,
                'completedAt' => $enrolment->completed_at?->toIso8601String(), 'newVersion' => $enrolment->course->current_version_id !== $enrolment->version_id],
            'course' => ['title' => $enrolment->course->title, 'slug' => $enrolment->course->slug, 'dataBytes' => (int) ($snapshot['data_bytes'] ?? 0)],
            'modules' => array_map(static fn (array $m): array => ['title' => $m['title'], 'lessons' => array_map(static fn (array $l): array => [
                'id' => $l['id'], 'title' => $l['title'], 'kind' => $l['kind'], 'minutes' => $l['minutes'], 'bytes' => $l['bytes'],
                'done' => isset($progress[$l['id']]) && $progress[$l['id']]->completed_at !== null,
                'optional' => $l['kind'] === 'quiz' && ! ($l['quiz']['graded'] ?? true),
            ], (array) $m['lessons'])], (array) ($snapshot['modules'] ?? [])),
        ]);
    }

    public function lesson(Request $request, Enrolment $enrolment, string $lesson): Response
    {
        $this->own($request, $enrolment);
        $enrolment->load(['course', 'version']);

        try {
            $data = $this->learning->lesson($enrolment, $lesson);
        } catch (DomainException) {
            abort(404);
        }

        $ids = array_keys($this->learning->lessons($enrolment));
        $index = (int) array_search($lesson, $ids, true);
        $progress = DB::table('learn_progress')->where('enrolment_id', $enrolment->id)->where('lesson_id', $lesson)->first();
        DB::table('learn_enrolments')->where('id', $enrolment->id)->update(['last_lesson_id' => $lesson]);

        return Inertia::render('Learn/My/Lesson', [
            'enrolment' => ['id' => $enrolment->id, 'progress' => $enrolment->progress],
            'course' => ['title' => $enrolment->course->title],
            'lesson' => Learning::forLearner($data),
            'state' => ['done' => $progress?->completed_at !== null, 'position' => (int) ($progress->position ?? 0), 'practiceScore' => $progress?->practice_score],
            'previous' => $ids[$index - 1] ?? null,
            'next' => $ids[$index + 1] ?? null,
            'quiz' => $data['kind'] === 'quiz' ? $this->quizState($enrolment, $lesson, $data) : null,
            'submissions' => $data['kind'] === 'assignment' ? Submission::query()->where('enrolment_id', $enrolment->id)->where('lesson_id', $lesson)->latest('created_at')->get()
                ->map(static fn (Submission $s): array => ['id' => $s->id, 'status' => $s->status, 'attempt' => $s->attempt, 'feedback' => $s->feedback, 'rubric' => $s->rubric ?? [],
                    'text' => $s->text, 'hasFile' => $s->document_id !== null, 'at' => $s->created_at->toIso8601String()]) : [],
        ]);
    }

    /**
     * Progress events (online, or a batch synced from offline). Each: {lesson, type, value}.
     * Idempotent, so retries and duplicates are harmless.
     */
    public function progress(Request $request, Enrolment $enrolment): JsonResponse
    {
        $this->own($request, $enrolment);
        $data = $request->validate([
            'events' => ['required', 'array', 'max:500'],
            'events.*.lesson' => ['required', 'string', 'max:26'],
            'events.*.type' => ['required', Rule::in(['completed', 'position', 'practice'])],
            'events.*.value' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $applied = 0;
        foreach ($data['events'] as $event) {
            try {
                $this->learning->record($enrolment, $event['lesson'], $event['type'], (int) ($event['value'] ?? 0));
                $applied++;
            } catch (DomainException) {
                // lesson not in this version (e.g. after switching) - ignore
            }
        }

        return response()->json(['ok' => true, 'applied' => $applied, 'progress' => $enrolment->refresh()->progress, 'status' => $enrolment->status]);
    }

    public function quiz(Request $request, Enrolment $enrolment, string $lesson): JsonResponse
    {
        $this->own($request, $enrolment);
        $data = $request->validate(['answers' => ['present', 'array'], 'answers.*' => ['array'], 'answers.*.*' => ['integer', 'min:0', 'max:10']]);

        try {
            return response()->json(['ok' => true, ...$this->learning->attemptQuiz($enrolment, $lesson, $data['answers'])]);
        } catch (DomainException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function submit(Request $request, Enrolment $enrolment, string $lesson): RedirectResponse
    {
        $user = $this->own($request, $enrolment);
        $data = $request->validate(['text' => ['nullable', 'string', 'max:10000'], 'file' => ['nullable', File::types(['pdf', 'jpg', 'jpeg', 'png'])->max(10 * 1024)]]);

        try {
            $this->learning->submit($enrolment, $lesson, $data['text'] ?? null, $request->file('file'), $user);
        } catch (DomainException $e) {
            return back()->withErrors(['submission' => $e->getMessage()]);
        }

        return back()->with('status', __('learn.assignment.submitted'));
    }

    public function leave(Request $request, Enrolment $enrolment): RedirectResponse
    {
        $this->own($request, $enrolment);
        $this->learning->leave($enrolment);

        return to_route('learn.my')->with('status', __('learn.enrol.left'));
    }

    public function switchVersion(Request $request, Enrolment $enrolment): RedirectResponse
    {
        $this->own($request, $enrolment);
        $this->learning->switchVersion($enrolment->load('course'));

        return back()->with('status', __('learn.enrol.switched'));
    }

    /**
     * Everything a phone needs to take this course offline: lessons (graded quizzes without answers;
     * practice quizzes with answers so they can be marked offline) and media URLs with sizes.
     */
    public function offline(Request $request, Enrolment $enrolment): JsonResponse
    {
        $this->own($request, $enrolment);
        $enrolment->load(['course', 'version']);
        $snapshot = $enrolment->version->snapshot;
        $media = [];
        $modules = array_map(function (array $m) use (&$media): array {
            $m['lessons'] = array_map(function (array $l) use (&$media): array {
                if (($l['media'] ?? null) !== null) {
                    $versions = (array) ($l['media']['versions'] ?? []);
                    $choice = isset($versions['low']) ? 'low' : (isset($versions['audio']) ? 'audio' : null);
                    $media[] = ['url' => '/learn/media/'.$l['media']['id'].($choice !== null ? '/'.$choice : ''), 'bytes' => $choice !== null ? (int) $versions[$choice] : (int) $l['bytes']];
                }
                preg_match_all('#/learn/media/[0-9a-z]{26}#i', (string) $l['html'], $images);
                foreach (array_unique($images[0]) as $url) {
                    $media[] = ['url' => $url, 'bytes' => 0];
                }

                return $l['kind'] === 'quiz' && ($l['quiz']['graded'] ?? true) ? Learning::forLearner($l) : $l;
            }, (array) $m['lessons']);

            return $m;
        }, (array) ($snapshot['modules'] ?? []));

        return response()->json(['enrolment' => $enrolment->id, 'title' => $enrolment->course->title, 'modules' => $modules, 'media' => $media,
            'bytes' => array_sum(array_column($media, 'bytes')) + strlen((string) json_encode($modules)), 'savedAt' => now()->toIso8601String()]);
    }

    /** The offline reader: no server data; it reads downloads stored on the phone. */
    public function offlineReader(): Response
    {
        return Inertia::render('Learn/My/Offline');
    }

    /**
     * @param  array<string, mixed>  $lesson
     * @return array<string, mixed>
     */
    private function quizState(Enrolment $enrolment, string $lessonId, array $lesson): array
    {
        $attempts = DB::table('learn_quiz_attempts')->where('enrolment_id', $enrolment->id)->where('lesson_id', $lessonId)->orderByDesc('created_at')->get(['score', 'passed', 'created_at']);
        $recent = $attempts->filter(static fn (object $a): bool => CarbonImmutable::parse((string) $a->created_at)->gt(now()->subHours(Learning::RETRY_WAIT_HOURS)))->count();

        return ['best' => $attempts->max('score'), 'passed' => $attempts->contains('passed', true), 'attemptsLeft' => max(0, (int) ($lesson['quiz']['max_attempts'] ?? 3) - $recent)];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function own(Request $request, Enrolment $enrolment): User
    {
        $user = $this->user($request);
        abort_unless($enrolment->user_id === $user->id, 404);

        return $user;
    }
}
