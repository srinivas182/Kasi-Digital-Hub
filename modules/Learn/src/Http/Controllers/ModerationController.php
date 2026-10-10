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
use Modules\Core\Identity\Models\User;
use Modules\Learn\Services\CurrentProvider;
use Modules\Learn\Services\Moderation;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Moderation queue (assessor/moderator role), decisions and the CSV report for external moderators. */
final class ModerationController
{
    public function __construct(private readonly CurrentProvider $providers) {}

    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $rows = $this->query($this->providerIds($user))->orderBy('learn_moderations.created_at')->limit(100)->get();

        return Inertia::render('Learn/Moderation', [
            'items' => $rows->map(static fn (object $r): array => [
                'id' => (int) $r->id, 'reason' => (string) $r->reason, 'status' => (string) $r->status, 'course' => (string) $r->title,
                'submission' => (string) $r->submission_id, 'outcome' => (string) $r->outcome, 'assessor' => (string) $r->assessor_name,
                'own' => $r->assessor_id === $user->id, 'notes' => $r->notes,
            ])->values(),
        ]);
    }

    public function decide(Request $request, int $moderation, Moderation $service): RedirectResponse
    {
        $user = $this->user($request);
        $row = $this->query($this->providerIds($user))->where('learn_moderations.id', $moderation)->first();
        abort_if($row === null, 404);
        $data = $request->validate(['agree' => ['required', 'boolean'], 'notes' => ['required', 'string', 'min:5', 'max:4000']]);

        try {
            $service->decide($moderation, $user, (bool) $data['agree'], $data['notes']);
        } catch (DomainException $e) {
            return back()->withErrors(['moderation' => $e->getMessage()]);
        }

        return back()->with('status', __('learn.moderation.done'));
    }

    public function report(Request $request): StreamedResponse
    {
        $user = $this->user($request);
        $rows = $this->query($this->providerIds($user))->orderBy('learn_moderations.created_at')->get();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['date', 'course', 'assessor', 'reason', 'assessor_outcome', 'moderation', 'moderator_notes'], escape: '\\');
            foreach ($rows as $r) {
                fputcsv($out, [(string) $r->created_at, (string) $r->title, (string) $r->assessor_name, (string) $r->reason, (string) $r->outcome, (string) $r->status, (string) $r->notes], escape: '\\');
            }
            fclose($out);
        }, 'moderation-report-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /** @param list<string> $providerIds */
    private function query(array $providerIds): Builder
    {
        return DB::table('learn_moderations')->join('learn_submissions', 'learn_submissions.id', '=', 'learn_moderations.submission_id')
            ->join('learn_enrolments', 'learn_enrolments.id', '=', 'learn_submissions.enrolment_id')
            ->join('learn_courses', 'learn_courses.id', '=', 'learn_enrolments.course_id')
            ->leftJoin('users as assessors', 'assessors.id', '=', 'learn_submissions.assessor_id')
            ->whereIn('learn_courses.organisation_id', $providerIds)
            ->select(['learn_moderations.id', 'learn_moderations.reason', 'learn_moderations.status', 'learn_moderations.notes', 'learn_moderations.created_at',
                'learn_moderations.submission_id', 'learn_submissions.status as outcome', 'learn_submissions.assessor_id', 'learn_courses.title',
                'assessors.first_name as assessor_name']); // portable: no string concatenation (differs between MySQL and SQLite)
    }

    /** @return list<string> */
    private function providerIds(User $user): array
    {
        $ids = $this->providers->all($user)->filter(fn ($p) => $this->providers->has($user, $p, 'assessor_moderator'))->pluck('id')->all();
        abort_if($ids === [], 403);

        return array_values(array_map('strval', $ids));
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
