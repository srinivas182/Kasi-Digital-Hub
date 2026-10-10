<?php

declare(strict_types=1);

namespace Modules\Learn\Http\Controllers;

use DomainException;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Documents\DocumentVault;
use Modules\Core\Documents\Models\Document;
use Modules\Core\Identity\Models\User;
use Modules\Learn\Models\Submission;
use Modules\Learn\Services\CurrentProvider;
use Modules\Learn\Services\Learning;

/**
 * Assessors (assessor/moderator role, or provider admins) mark assignments for their provider's
 * courses: Competent or Not yet competent, with rubric ticks and feedback. Every decision is audited.
 */
final class AssessorController
{
    public function __construct(private readonly CurrentProvider $providers) {}

    public function index(Request $request): Response
    {
        $ids = $this->providerIds($this->user($request));

        return Inertia::render('Learn/Assess/Index', [
            'waiting' => $this->query($ids)->where('learn_submissions.status', 'submitted')->oldest('learn_submissions.created_at')->limit(100)->get()->map(fn (object $s): array => $this->row($s)),
            'recent' => $this->query($ids)->where('learn_submissions.status', '!=', 'submitted')->latest('learn_submissions.assessed_at')->limit(30)->get()->map(fn (object $s): array => $this->row($s)),
        ]);
    }

    public function show(Request $request, Submission $submission, Learning $learning, DocumentVault $vault): Response
    {
        $user = $this->user($request);
        $submission->load('enrolment.course', 'enrolment.version');
        abort_unless(in_array($submission->enrolment->course->organisation_id, $this->providerIds($user), true), 404);
        $lesson = $learning->lesson($submission->enrolment, $submission->lesson_id);
        $document = $submission->document_id !== null ? Document::query()->whereKey($submission->document_id)->first() : null;
        $learner = User::query()->findOrFail($submission->enrolment->user_id);

        return Inertia::render('Learn/Assess/Show', [
            'submission' => ['id' => $submission->id, 'status' => $submission->status, 'attempt' => $submission->attempt, 'text' => $submission->text,
                'fileUrl' => $document !== null && $document->canBeOpened() ? $vault->temporaryUrl($document, inline: true) : null,
                'feedback' => $submission->feedback, 'rubric' => $submission->rubric ?? [], 'at' => $submission->created_at->toIso8601String()],
            'learner' => ['name' => $learner->first_name.' '.mb_substr((string) $learner->last_name, 0, 1).'.'],
            'course' => $submission->enrolment->course->title,
            'lesson' => ['title' => $lesson['title'], 'instructions' => $lesson['assignment']['instructions'] ?? '', 'rubric' => $lesson['assignment']['rubric'] ?? []],
        ]);
    }

    public function assess(Request $request, Submission $submission, Learning $learning): RedirectResponse
    {
        $user = $this->user($request);
        $submission->load('enrolment.course', 'enrolment.version');
        abort_unless(in_array($submission->enrolment->course->organisation_id, $this->providerIds($user), true), 404);
        $data = $request->validate(['competent' => ['required', 'boolean'], 'criteria' => ['array'], 'criteria.*' => ['string', 'max:300'], 'feedback' => ['required', 'string', 'min:5', 'max:4000']]);

        try {
            $learning->assess($submission, $user, (bool) $data['competent'], array_values($data['criteria'] ?? []), $data['feedback']);
        } catch (DomainException $e) {
            return back()->withErrors(['assess' => $e->getMessage()]);
        }

        return to_route('learn.assess')->with('status', __('learn.assess.done'));
    }

    /** @return list<string> */
    private function providerIds(User $user): array
    {
        $ids = $this->providers->all($user)->filter(fn ($p) => $this->providers->has($user, $p, 'assessor_moderator') || $this->providers->has($user, $p, 'provider_admin'))->pluck('id')->all();
        abort_if($ids === [], 403);

        return array_values(array_map('strval', $ids));
    }

    /** @param list<string> $providerIds */
    private function query(array $providerIds): Builder
    {
        return DB::table('learn_submissions')->join('learn_enrolments', 'learn_enrolments.id', '=', 'learn_submissions.enrolment_id')
            ->join('learn_courses', 'learn_courses.id', '=', 'learn_enrolments.course_id')->join('users', 'users.id', '=', 'learn_enrolments.user_id')
            ->whereIn('learn_courses.organisation_id', $providerIds)
            ->select(['learn_submissions.id', 'learn_submissions.status', 'learn_submissions.attempt', 'learn_submissions.created_at', 'learn_courses.title', 'users.first_name', 'users.last_name']);
    }

    /** @return array<string, mixed> */
    private function row(\stdClass $s): array
    {
        $v = (array) $s;

        return ['id' => (string) $v['id'], 'status' => (string) $v['status'], 'attempt' => (int) $v['attempt'], 'course' => (string) $v['title'], 'at' => (string) $v['created_at'],
            'learner' => $v['first_name'].' '.mb_substr((string) $v['last_name'], 0, 1).'.'];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
