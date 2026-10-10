<?php

declare(strict_types=1);

namespace Modules\Learn\Http\Controllers;

use App\Support\Seo\Seo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Access\AccessResolver;
use Modules\Core\Identity\Models\User;
use Modules\Learn\Models\Course;
use Modules\Learn\Models\Media;
use Modules\Learn\Services\CourseSuggestions;
use Modules\Learn\Services\CurrentProvider;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The course catalogue: browse, filter, course pages, previews, saved courses, public pages, and
 * access-checked media.
 */
final class CatalogueController
{
    public function index(Request $request, CourseSuggestions $suggestions): Response
    {
        $user = $request->user();
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:80'], 'topic' => ['nullable', Rule::in(Course::TOPICS)], 'level' => ['nullable', Rule::in(Course::LEVELS)],
            'language' => ['nullable', 'string', 'max:24'], 'delivery' => ['nullable', Rule::in(Course::DELIVERY)],
            'small' => ['nullable', 'boolean'], 'accredited' => ['nullable', 'boolean'], 'saved' => ['nullable', 'boolean'],
        ]);
        /** @var list<string> $saved */
        $saved = $user instanceof User ? array_values(array_map('strval', DB::table('learn_saved_courses')->where('user_id', $user->id)->pluck('course_id')->all())) : [];

        $courses = $this->visible($user)->with(['organisation', 'currentVersion', 'accreditation'])
            ->when($filters['q'] ?? null, fn (Builder $q, string $t) => $q->where(fn (Builder $w) => $w->where('title', 'like', "%{$t}%")->orWhere('summary', 'like', "%{$t}%")))
            ->when($filters['topic'] ?? null, fn (Builder $q, string $v) => $q->where('topic', $v))
            ->when($filters['level'] ?? null, fn (Builder $q, string $v) => $q->where('level', $v))
            ->when($filters['language'] ?? null, fn (Builder $q, string $v) => $q->where('language', $v))
            ->when($filters['delivery'] ?? null, fn (Builder $q, string $v) => $q->where('delivery', $v))
            ->when($filters['accredited'] ?? false, fn (Builder $q) => $q->whereHas('accreditation', fn (Builder $a) => $a->where('status', 'verified')))
            ->when($filters['small'] ?? false, fn (Builder $q) => $q->whereHas('currentVersion', fn (Builder $v) => $v->where('data_bytes', '<=', 50 * 1024 * 1024)))
            ->when($filters['saved'] ?? false, fn (Builder $q) => $q->whereIn('id', $saved))
            ->latest('updated_at')->limit(100)->get()
            ->map(fn (Course $c): array => $this->card($c, $saved));

        return Inertia::render('Learn/Catalogue/Index', [
            'filters' => $filters,
            'courses' => $courses,
            'suggested' => $user instanceof User ? $suggestions->for($user)->map(fn (Course $c): array => $this->card($c, $saved))->values() : [],
            'options' => AuthorController::options(),
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        $user = $request->user();
        $course = $this->visible($user)->with(['organisation', 'currentVersion', 'accreditation'])->where('slug', $slug)->firstOrFail();

        return Inertia::render($user instanceof User ? 'Learn/Catalogue/Show' : 'Learn/Catalogue/Public', [
            'course' => $this->detail($course),
            'saved' => $user instanceof User && DB::table('learn_saved_courses')->where('user_id', $user->id)->where('course_id', $course->id)->exists(),
            'seo' => Seo::page($course->title, mb_strimwidth((string) $course->summary, 0, 155, '...'), '/learn/courses/'.$course->slug, [[
                '@context' => 'https://schema.org', '@type' => 'Course', 'name' => $course->title, 'description' => (string) $course->summary,
                'provider' => ['@type' => 'Organization', 'name' => $course->organisation->displayName()], 'inLanguage' => $course->language,
                'isAccessibleForFree' => true, 'educationalLevel' => $course->level,
            ]]),
        ]);
    }

    public function preview(Request $request, string $slug, string $lesson): Response
    {
        $course = $this->visible($request->user())->with('currentVersion')->where('slug', $slug)->firstOrFail();
        $found = null;
        foreach ((array) ($course->currentVersion->snapshot['modules'] ?? []) as $module) {
            foreach ((array) ($module['lessons'] ?? []) as $item) {
                if (($item['id'] ?? null) === $lesson) {
                    $found = $item;
                }
            }
        }
        abort_unless(is_array($found) && ($found['preview'] ?? false), 404);

        return Inertia::render('Learn/Catalogue/Preview', ['course' => ['title' => $course->title, 'slug' => $course->slug], 'lesson' => $found]);
    }

    public function save(Request $request, string $slug): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $course = $this->visible($user)->where('slug', $slug)->firstOrFail();
        DB::table('learn_saved_courses')->insertOrIgnore(['user_id' => $user->id, 'course_id' => $course->id, 'created_at' => now()]);

        return back()->with('status', __('learn.catalogue.saved'));
    }

    public function unsave(Request $request, string $slug): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $courseId = Course::query()->where('slug', $slug)->value('id');
        DB::table('learn_saved_courses')->where('user_id', $user->id)->where('course_id', $courseId)->delete();

        return back()->with('status', __('learn.catalogue.unsaved'));
    }

    /**
     * Course media: the provider's team and reviewers always; everyone else only for media used by
     * a published course (S14 limits non-preview lessons to enrolled learners).
     */
    public function media(Request $request, Media $media, ?string $version = null): StreamedResponse
    {
        $user = $request->user();
        $team = $user instanceof User && (app(CurrentProvider::class)->all($user)->contains('id', $media->organisation_id)
            || app(AccessResolver::class)->hasPermission($user, 'learn.review'));

        if (! $team) {
            $published = Course::query()->where('status', 'published')->where(fn (Builder $q) => $q
                ->where('image_id', $media->id)
                ->orWhereHas('lessons', fn (Builder $l) => $l->where('media_id', $media->id)->orWhere('content', 'like', '%'.$media->id.'%')))->exists();
            abort_unless($published, 404);
        }

        $path = $version !== null ? ($media->renditions[$version]['path'] ?? null) : ($media->renditions['image']['path'] ?? $media->original_path);
        abort_if($path === null || ! Storage::disk('learn')->exists($path), 404);

        return Storage::disk('learn')->response($path, null, ['Cache-Control' => 'private, max-age=86400', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** @return Builder<Course> */
    private function visible(mixed $user): Builder
    {
        return Course::query()->where('status', 'published')->whereNotNull('current_version_id')
            ->when($user instanceof User && $user->isMinor(), fn (Builder $q) => $q->where('min_age', '<', 18));
    }

    /**
     * @param  list<string>  $saved
     * @return array<string, mixed>
     */
    private function card(Course $c, array $saved): array
    {
        return [
            'slug' => $c->slug, 'title' => $c->title, 'summary' => $c->summary, 'provider' => $c->organisation->displayName(),
            'verified' => $c->organisation->isVerified(), 'accredited' => $c->accredited(), 'topic' => $c->topic, 'level' => $c->level,
            'hours' => $c->hours !== null ? (float) $c->hours : null, 'language' => $c->language, 'delivery' => $c->delivery,
            'dataBytes' => $c->currentVersion !== null ? $c->currentVersion->data_bytes : 0, 'saved' => in_array($c->id, $saved, true),
        ];
    }

    /** @return array<string, mixed> */
    private function detail(Course $c): array
    {
        $snapshot = $c->currentVersion->snapshot ?? [];

        return [
            ...$this->card($c, []),
            'outcomes' => $snapshot['outcomes'] ?? [], 'prerequisites' => $c->prerequisites, 'minAge' => $c->min_age,
            'nqfLevel' => $snapshot['nqf_level'] ?? null, 'credits' => $snapshot['credits'] ?? null,
            'accreditedBy' => $c->accredited() ? $c->accreditation?->body : null, 'licence' => $c->licence, 'attribution' => $c->attribution,
            'providerDescription' => $c->organisation->description, 'version' => $c->currentVersion?->number,
            'modules' => array_map(static fn (array $m): array => ['title' => $m['title'], 'lessons' => array_map(
                static fn (array $l): array => ['id' => $l['id'], 'title' => $l['title'], 'kind' => $l['kind'], 'minutes' => $l['minutes'], 'bytes' => $l['bytes'], 'preview' => $l['preview']],
                (array) $m['lessons'],
            )], (array) ($snapshot['modules'] ?? [])),
        ];
    }
}
