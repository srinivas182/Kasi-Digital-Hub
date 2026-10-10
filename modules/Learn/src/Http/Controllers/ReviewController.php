<?php

declare(strict_types=1);

namespace Modules\Learn\Http\Controllers;

use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Documents\DocumentVault;
use Modules\Core\Documents\Models\Document;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Learn\Models\Accreditation;
use Modules\Learn\Models\Course;
use Modules\Learn\Services\CourseChecks;
use Modules\Learn\Services\CourseWorkflow;

/**
 * KasiHub review (permission learn.review): courses waiting (first course, accreditation claims,
 * courses for 16-17-year-olds), accreditation claims, and unpublishing any course with a reason.
 */
final class ReviewController
{
    public function index(): Response
    {
        return Inertia::render('Learn/Review/Index', [
            'courses' => Course::query()->with('organisation')->where('status', 'in_review')->oldest('updated_at')->get()
                ->map(static fn (Course $c): array => ['id' => $c->id, 'title' => $c->title, 'provider' => $c->organisation->displayName(), 'minAge' => $c->min_age,
                    'accreditation' => $c->accreditation_id !== null, 'waitingSince' => (string) $c->getAttribute('updated_at')]),
            'accreditations' => Accreditation::query()->where('status', 'pending')->get()->map(fn (Accreditation $a): array => [
                'id' => $a->id, 'body' => $a->body, 'number' => $a->number,
                'provider' => DB::table('organisations')->where('id', $a->organisation_id)->value('name'),
                'evidenceUrl' => $this->evidenceUrl($a),
            ]),
            'published' => Course::query()->with('organisation')->where('status', 'published')->latest('updated_at')->limit(50)->get()
                ->map(static fn (Course $c): array => ['id' => $c->id, 'title' => $c->title, 'provider' => $c->organisation->displayName(), 'slug' => $c->slug]),
        ]);
    }

    public function show(Course $course, CourseWorkflow $workflow, CourseChecks $checks): Response
    {
        return Inertia::render('Learn/Review/Course', [
            'course' => ['id' => $course->id, 'title' => $course->title, 'status' => $course->status, 'provider' => $course->organisation->displayName(),
                'minAge' => $course->min_age, 'accreditation' => $course->accreditation?->only(['body', 'number', 'status'])],
            'preview' => $workflow->snapshot($course),
            'aiDrafted' => DB::table('learn_lessons')->where('course_id', $course->id)->where('ai_drafted', true)->pluck('title'),
            'problems' => $checks->problems($course),
            'history' => DB::table('learn_reviews')->leftJoin('users', 'users.id', '=', 'learn_reviews.author_id')->where('course_id', $course->id)
                ->orderByDesc('learn_reviews.created_at')->limit(30)->get(['learn_reviews.kind', 'learn_reviews.body', 'learn_reviews.created_at', 'users.first_name'])
                ->map(static fn (object $r): array => ['kind' => $r->kind, 'body' => $r->body, 'at' => (string) $r->created_at, 'by' => $r->first_name]),
        ]);
    }

    public function decide(Request $request, Course $course, CourseWorkflow $workflow): RedirectResponse
    {
        $user = $this->user($request);
        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'changes', 'unpublish'])], 'comment' => ['required_unless:decision,approve', 'nullable', 'string', 'max:2000']]);

        try {
            match ($data['decision']) {
                'approve' => $workflow->kasiHubApprove($course, $user),
                'changes' => $workflow->requestChanges($course, $user, (string) $data['comment']),
                default => $workflow->unpublish($course, $user, (string) $data['comment']),
            };
        } catch (DomainException $e) {
            return back()->withErrors(['course' => $e->getMessage()]);
        }

        return to_route('learn.review')->with('status', __('learn.review.done'));
    }

    public function accreditation(Request $request, Accreditation $accreditation, AuditLogger $audit): RedirectResponse
    {
        $user = $this->user($request);
        $data = $request->validate(['decision' => ['required', Rule::in(['verified', 'rejected'])], 'reason' => ['required_if:decision,rejected', 'nullable', 'string', 'max:300']]);
        $accreditation->forceFill(['status' => $data['decision'], 'reason' => $data['reason'] ?? null, 'decided_by' => $user->id, 'decided_at' => now()])->save();
        $audit->record('learn.accreditation_'.$data['decision'], meta: ['accreditation' => $accreditation->id], actor: $user);

        return back()->with('status', __('learn.review.done'));
    }

    private function evidenceUrl(Accreditation $a): ?string
    {
        $document = $a->document_id !== null ? Document::query()->whereKey($a->document_id)->first() : null;

        return $document !== null && $document->canBeOpened() ? app(DocumentVault::class)->temporaryUrl($document, inline: true) : null;
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
