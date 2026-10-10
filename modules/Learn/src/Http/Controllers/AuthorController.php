<?php

declare(strict_types=1);

namespace Modules\Learn\Http\Controllers;

use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Modules\Core\Ai\AiBudget;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\Organisation;
use Modules\Learn\Media\MediaLibrary;
use Modules\Learn\Models\Accreditation;
use Modules\Learn\Models\Course;
use Modules\Learn\Models\CourseModule;
use Modules\Learn\Models\Lesson;
use Modules\Learn\Models\Media;
use Modules\Learn\Services\AuthoringAssistant;
use Modules\Learn\Services\ContentRenderer;
use Modules\Learn\Services\CourseChecks;
use Modules\Learn\Services\CourseWorkflow;
use Modules\Learn\Services\CurrentProvider;

/**
 * Course authoring for a provider's authors and admins: course details, modules, lessons, media,
 * AI writing help, checks, submit; admins approve or send back.
 */
final class AuthorController
{
    public function __construct(private readonly CurrentProvider $providers) {}

    public function store(Request $request): RedirectResponse
    {
        [$user, $provider] = $this->author($request);
        $data = $this->validatedCourse($request, $provider);
        $course = Course::query()->create([...$data, 'organisation_id' => $provider->id, 'created_by' => $user->id,
            'slug' => Str::slug($data['title']).'-'.Str::lower(Str::random(5))]);
        CourseModule::query()->create(['course_id' => $course->id, 'title' => __('learn.author.first_module'), 'position' => 0]);

        return to_route('learn.author.course', $course)->with('status', __('learn.author.created'));
    }

    public function show(Request $request, Course $course, CourseChecks $checks, ContentRenderer $renderer): Response
    {
        [$user, $provider] = $this->author($request, $course);
        $course->load(['modules.lessons.media']);

        return Inertia::render('Learn/Author/Course', [
            'course' => [
                'id' => $course->id, 'slug' => $course->slug, 'title' => $course->title, 'summary' => $course->summary, 'outcomes' => $course->outcomes ?? [],
                'topic' => $course->topic, 'level' => $course->level, 'hours' => $course->hours, 'prerequisites' => $course->prerequisites,
                'language' => $course->language, 'minAge' => $course->min_age, 'delivery' => $course->delivery, 'accreditationId' => $course->accreditation_id,
                'nqfLevel' => $course->nqf_level, 'credits' => $course->credits, 'licence' => $course->licence, 'attribution' => $course->attribution,
                'status' => $course->status, 'statusReason' => $course->status_reason, 'changed' => $course->changed_since_publish,
            ],
            'modules' => $course->modules->map(fn (CourseModule $m): array => [
                'id' => $m->id, 'title' => $m->title,
                'lessons' => $m->lessons->map(fn (Lesson $l): array => [
                    'id' => $l->id, 'title' => $l->title, 'kind' => $l->kind, 'aiDrafted' => $l->ai_drafted, 'preview' => $l->preview,
                    'bytes' => $checks->lessonBytes($l), 'mediaStatus' => $l->media?->status,
                ])->values(),
            ])->values(),
            'problems' => $checks->problems($course),
            'history' => DB::table('learn_reviews')->leftJoin('users', 'users.id', '=', 'learn_reviews.author_id')->where('course_id', $course->id)
                ->orderByDesc('learn_reviews.created_at')->limit(30)->get(['learn_reviews.kind', 'learn_reviews.body', 'learn_reviews.created_at', 'users.first_name'])
                ->map(static fn (object $r): array => ['kind' => $r->kind, 'body' => $r->body, 'at' => (string) $r->created_at, 'by' => $r->first_name]),
            'accreditations' => Accreditation::query()->where('organisation_id', $provider->id)->where('status', '!=', 'rejected')->get(['id', 'body', 'number', 'status']),
            'isAdmin' => $this->providers->has($user, $provider, 'provider_admin'),
            'options' => self::options(),
            'ai' => app(AiBudget::class)->enabled('learn.authoring'),
        ]);
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        [, $provider] = $this->author($request, $course);
        $course->fill($this->validatedCourse($request, $provider))->save();
        $this->touched($course);

        return back()->with('status', __('work.saved'));
    }

    public function addModule(Request $request, Course $course): RedirectResponse
    {
        $this->author($request, $course);
        $data = $request->validate(['title' => ['required', 'string', 'max:140']]);
        CourseModule::query()->create(['course_id' => $course->id, 'title' => $data['title'], 'position' => CourseModule::query()->where('course_id', $course->id)->count()]);
        $this->touched($course);

        return back();
    }

    public function updateModule(Request $request, Course $course, CourseModule $module): RedirectResponse
    {
        $this->author($request, $course);
        abort_unless($module->course_id === $course->id, 404);
        $data = $request->validate(['title' => ['required', 'string', 'max:140'], 'move' => ['nullable', Rule::in(['up', 'down'])]]);
        $module->update(['title' => $data['title']]);
        if (($data['move'] ?? null) !== null) {
            $this->reorder(CourseModule::query()->where('course_id', $course->id)->orderBy('position')->get()->all(), $module->id, $data['move']);
        }
        $this->touched($course);

        return back();
    }

    public function deleteModule(Request $request, Course $course, CourseModule $module): RedirectResponse
    {
        $this->author($request, $course);
        abort_unless($module->course_id === $course->id, 404);
        abort_if(CourseModule::query()->where('course_id', $course->id)->count() <= 1, 422);
        $module->delete();
        $this->touched($course);

        return back();
    }

    public function addLesson(Request $request, Course $course): RedirectResponse
    {
        $this->author($request, $course);
        $data = $request->validate([
            'module_id' => ['required', 'integer', Rule::exists('learn_modules', 'id')->where('course_id', $course->id)],
            'title' => ['required', 'string', 'max:140'],
            'kind' => ['required', Rule::in(Lesson::KINDS)],
        ]);
        $lesson = Lesson::query()->create([...$data, 'course_id' => $course->id, 'position' => Lesson::query()->where('module_id', $data['module_id'])->count()]);
        $this->touched($course);

        return to_route('learn.author.lesson', [$course, $lesson]);
    }

    public function lesson(Request $request, Course $course, Lesson $lesson, CourseChecks $checks, ContentRenderer $renderer): Response
    {
        [, $provider] = $this->author($request, $course);
        abort_unless($lesson->course_id === $course->id, 404);
        $lesson->load('media');

        return Inertia::render('Learn/Author/Lesson', [
            'course' => ['id' => $course->id, 'title' => $course->title, 'status' => $course->status],
            'lesson' => [
                'id' => $lesson->id, 'title' => $lesson->title, 'kind' => $lesson->kind, 'content' => $lesson->content, 'transcript' => $lesson->transcript,
                'minutes' => $lesson->minutes, 'aiDrafted' => $lesson->ai_drafted, 'preview' => $lesson->preview, 'bytes' => $checks->lessonBytes($lesson),
                'media' => $lesson->media === null ? null : ['id' => $lesson->media->id, 'kind' => $lesson->media->kind, 'status' => $lesson->media->status,
                    'name' => $lesson->media->original_name, 'duration' => $lesson->media->duration_seconds, 'reason' => $lesson->media->status === 'failed' ? $lesson->media->alt : null,
                    'versions' => collect($lesson->media->renditions ?? [])->map(static fn (array $r): int => $r['bytes'])->all()],
            ],
            'problems' => $lesson->kind === 'text' ? $renderer->problems($lesson->content) : [],
            'ai' => app(AiBudget::class)->enabled('learn.authoring'),
            'maxUploadMb' => (int) (config('kasi.learn.max_upload_kb') / 1024),
            'providerId' => $provider->id,
        ]);
    }

    public function updateLesson(Request $request, Course $course, Lesson $lesson): RedirectResponse
    {
        $this->author($request, $course);
        abort_unless($lesson->course_id === $course->id, 404);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:140'],
            'content' => ['nullable', 'array'],
            'transcript' => ['nullable', 'string', 'max:50000'],
            'minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'preview' => ['boolean'],
            'ai_drafted' => ['boolean'],
            'media_id' => ['nullable', 'string', Rule::exists('learn_media', 'id')->where('organisation_id', $course->organisation_id)],
            'move' => ['nullable', Rule::in(['up', 'down'])],
        ]);
        if (strlen((string) json_encode($data['content'] ?? null)) > 400_000) {
            return back()->withErrors(['content' => __('learn.author.too_long')]);
        }

        $lesson->update([
            'title' => $data['title'], 'content' => $data['content'] ?? null, 'transcript' => $data['transcript'] ?? null, 'minutes' => $data['minutes'] ?? null,
            'preview' => (bool) ($data['preview'] ?? false), 'ai_drafted' => (bool) ($data['ai_drafted'] ?? false),
            'media_id' => array_key_exists('media_id', $data) ? $data['media_id'] : $lesson->media_id,
        ]);
        if (($data['move'] ?? null) !== null) {
            $this->reorder(Lesson::query()->where('module_id', $lesson->module_id)->orderBy('position')->get()->all(), $lesson->id, $data['move']);
        }
        $this->touched($course);

        return back()->with('status', __('work.saved'));
    }

    public function deleteLesson(Request $request, Course $course, Lesson $lesson): RedirectResponse
    {
        $this->author($request, $course);
        abort_unless($lesson->course_id === $course->id, 404);
        $lesson->delete();
        $this->touched($course);

        return to_route('learn.author.course', $course);
    }

    /** Upload a picture (for the editor), video, audio or download. */
    public function upload(Request $request, Course $course, MediaLibrary $library): JsonResponse
    {
        [$user, $provider] = $this->author($request, $course);
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(MediaLibrary::TYPES))],
            'file' => ['required', 'file', 'max:'.(int) config('kasi.learn.max_upload_kb')],
            'alt' => ['nullable', 'string', 'max:300'],
        ]);
        if ($data['kind'] === 'image' && $request->file('file')?->getSize() > 10 * 1024 * 1024) {
            return response()->json(['ok' => false, 'message' => __('learn.media.too_big')], 422);
        }

        try {
            $media = $library->store($provider, $request->file('file'), $data['kind'], $user, $data['alt'] ?? null);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'id' => $media->id, 'url' => '/learn/media/'.$media->id, 'status' => $media->status]);
    }

    /** AI help: draft a lesson, suggest outcomes, plain-language rewrite. */
    public function assist(Request $request, Course $course, AuthoringAssistant $assistant): JsonResponse
    {
        [$user] = $this->author($request, $course);
        $data = $request->validate([
            'action' => ['required', Rule::in(['draft', 'outcomes', 'plain'])],
            'title' => ['nullable', 'string', 'max:140'],
            'text' => ['required', 'string', 'min:10', 'max:6000'],
        ]);

        $result = match ($data['action']) {
            'draft' => $assistant->draftLesson($course->title, (string) ($data['title'] ?? $course->title), $data['text'], $user),
            'outcomes' => $assistant->outcomes($course->title, $data['text'], $user),
            default => $assistant->plainLanguage($data['text'], $user),
        };

        return response()->json($result['ok'] ? $result : ['ok' => false, 'message' => __('ai.fallback.'.($result['reason'] ?? 'unavailable'))]);
    }

    public function submit(Request $request, Course $course, CourseWorkflow $workflow): RedirectResponse
    {
        [$user] = $this->author($request, $course);

        try {
            $workflow->submit($course, $user);
        } catch (DomainException $e) {
            return back()->withErrors(['course' => $e->getMessage()]);
        }

        return back()->with('status', __('learn.review.submitted'));
    }

    /** Provider admins approve (publishes, or sends to KasiHub review) or send back with comments. */
    public function decide(Request $request, Course $course, CourseWorkflow $workflow): RedirectResponse
    {
        [$user, $provider] = $this->author($request, $course);
        abort_unless($this->providers->has($user, $provider, 'provider_admin'), 403);
        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'changes'])], 'comment' => ['required_if:decision,changes', 'nullable', 'string', 'max:2000']]);

        try {
            $data['decision'] === 'approve' ? $workflow->providerApprove($course, $user) : $workflow->requestChanges($course, $user, (string) $data['comment']);
        } catch (DomainException $e) {
            return back()->withErrors(['course' => $e->getMessage()]);
        }

        return back()->with('status', __($course->refresh()->status === 'published' ? 'learn.review.published' : ($data['decision'] === 'approve' ? 'learn.review.sent_to_kasihub' : 'learn.review.sent_back')));
    }

    /** @return array<string, list<string>> */
    public static function options(): array
    {
        return ['topics' => Course::TOPICS, 'levels' => Course::LEVELS, 'delivery' => Course::DELIVERY, 'licences' => Course::LICENCES,
            'languages' => ['English', 'isiZulu', 'isiXhosa', 'Afrikaans', 'Sepedi', 'Setswana', 'Sesotho', 'Xitsonga', 'siSwati', 'Tshivenda', 'isiNdebele'],
            'kinds' => Lesson::KINDS];
    }

    /** @return array<string, mixed> */
    private function validatedCourse(Request $request, Organisation $provider): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:140'],
            'summary' => ['nullable', 'string', 'max:600'],
            'outcomes' => ['array', 'max:8'], 'outcomes.*' => ['string', 'max:200'],
            'topic' => ['required', Rule::in(Course::TOPICS)],
            'level' => ['required', Rule::in(Course::LEVELS)],
            'hours' => ['nullable', 'numeric', 'min:0.5', 'max:500'],
            'prerequisites' => ['nullable', 'string', 'max:300'],
            'language' => ['required', 'string', 'max:24'],
            'min_age' => ['required', Rule::in([16, 18])],
            'delivery' => ['required', Rule::in(Course::DELIVERY)],
            'accreditation_id' => ['nullable', 'string', Rule::exists('learn_accreditations', 'id')->where('organisation_id', $provider->id)],
            'nqf_level' => ['nullable', 'integer', 'min:1', 'max:10', 'required_with:accreditation_id'],
            'credits' => ['nullable', 'integer', 'min:1', 'max:600'],
            'licence' => ['required', Rule::in(Course::LICENCES)],
            'attribution' => ['nullable', 'string', 'max:300'],
        ]);

        return [...$data, 'outcomes' => array_values(array_filter(array_map('trim', $data['outcomes'] ?? [])))];
    }

    /** Editing a published course prepares the next version (learners keep theirs). */
    private function touched(Course $course): void
    {
        if ($course->status === 'published' || $course->status === 'unpublished') {
            $course->forceFill(['changed_since_publish' => true])->save();
        } else {
            $course->touch();
        }
    }

    /**
     * @param  array<int, CourseModule|Lesson>  $items
     */
    private function reorder(array $items, int|string $id, string $direction): void
    {
        $items = array_values($items);
        $index = array_search($id, array_map(static fn ($i) => $i->getKey(), $items), true);
        $swap = $direction === 'up' ? $index - 1 : $index + 1;
        if ($index === false || ! isset($items[$swap])) {
            return;
        }
        [$items[$index], $items[$swap]] = [$items[$swap], $items[$index]];
        foreach ($items as $position => $item) {
            $item->forceFill(['position' => $position])->save();
        }
    }

    /** @return array{0: User, 1: Organisation} */
    private function author(Request $request, ?Course $course = null): array
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_if($user->isMinor(), 403);
        $provider = $course !== null
            ? $this->providers->all($user)->firstWhere('id', $course->organisation_id)
            : $this->providers->resolve($request, $user);
        abort_unless($provider !== null && $this->providers->canAuthor($user, $provider), $course !== null ? 404 : 403);

        return [$user, $provider];
    }
}
