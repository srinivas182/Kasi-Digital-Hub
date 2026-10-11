<?php

declare(strict_types=1);

namespace Modules\Connect\Http\Controllers;

use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Connect\Models\Mentor;
use Modules\Connect\Services\Mentors;
use Modules\Connect\Services\Safeguarding;
use Modules\Core\Documents\Models\Document;
use Modules\Core\Identity\Models\User;

/**
 * Mentor coordinators (vetting, pausing) and safeguarding officers (concern queue). Concern details are
 * visible only to safeguarding officers.
 */
final class CoordinatorController
{
    public function mentors(): Response
    {
        return Inertia::render('Connect/Coordinator', [
            'mentors' => Mentor::query()->with('user')->orderByRaw("CASE status WHEN 'applied' THEN 0 WHEN 'paused' THEN 1 ELSE 2 END")->orderByDesc('created_at')->limit(200)->get()
                ->map(static fn (Mentor $m): array => [
                    'id' => $m->user_id, 'name' => $m->user->fullName(), 'headline' => $m->headline, 'status' => $m->status, 'reason' => $m->status_reason, 'minorsOk' => $m->minors_ok,
                    'idVerified' => Document::query()->where('user_id', $m->user_id)->where('type', 'id_document')->where('status', Document::VERIFIED)->exists(),
                    'checks' => DB::table('connect_mentor_checks')->where('mentor_id', $m->user_id)->pluck('note', 'check'),
                ]),
            'checks' => [...Mentor::CHECKS, 'police_clearance'],
        ]);
    }

    public function check(Request $request, Mentor $mentor, Mentors $mentors): RedirectResponse
    {
        $data = $request->validate(['check' => ['required', Rule::in([...Mentor::CHECKS, 'police_clearance'])], 'done' => ['required', 'boolean'], 'note' => ['nullable', 'string', 'max:300']]);
        if ($data['check'] === 'id_verified' && $data['done'] && ! Document::query()->where('user_id', $mentor->user_id)->where('type', 'id_document')->where('status', Document::VERIFIED)->exists()) {
            return back()->withErrors(['mentor' => __('connect.coordinator.id_first')]);
        }
        $mentors->check($mentor->load('user'), $data['check'], $this->user($request), $data['note'] ?? null, (bool) $data['done']);

        return back();
    }

    public function decide(Request $request, Mentor $mentor, Mentors $mentors): RedirectResponse
    {
        $data = $request->validate(['action' => ['required', Rule::in(['approve', 'pause', 'remove'])], 'reason' => ['required_unless:action,approve', 'nullable', 'string', 'max:300']]);
        try {
            $data['action'] === 'approve' ? $mentors->approve($mentor->load('user'), $this->user($request))
                : $mentors->restrict($mentor->load('user'), $data['action'] === 'pause' ? 'paused' : 'removed', (string) $data['reason'], $this->user($request));
        } catch (DomainException $e) {
            return back()->withErrors(['mentor' => $e->getMessage()]);
        }

        return back()->with('status', __('work.saved'));
    }

    public function concerns(): Response
    {
        return Inertia::render('Connect/Safeguarding', [
            'concerns' => DB::table('connect_concerns')->leftJoin('users as about', 'about.id', '=', 'connect_concerns.about_user_id')
                ->leftJoin('users as reporter', 'reporter.id', '=', 'connect_concerns.reporter_id')
                ->orderByRaw("CASE connect_concerns.status WHEN 'open' THEN 0 WHEN 'actioned' THEN 1 ELSE 2 END")->orderByDesc('connect_concerns.urgent')->orderBy('connect_concerns.created_at')->limit(200)
                ->get(['connect_concerns.*', 'about.first_name as about_first', 'about.last_name as about_last', 'reporter.first_name as reporter_first', 'reporter.last_name as reporter_last'])
                ->map(static fn (object $c): array => ['id' => (string) $c->id, 'kind' => (string) $c->kind, 'urgent' => (bool) $c->urgent, 'body' => (string) $c->body, 'status' => (string) $c->status,
                    'actions' => $c->actions, 'at' => (string) $c->created_at, 'overdue' => $c->status === 'open' && CarbonImmutable::parse((string) $c->created_at)->lt(now()->subDay()),
                    'about' => $c->about_first !== null ? $c->about_first.' '.$c->about_last : null, 'aboutId' => $c->about_user_id, 'reporter' => $c->reporter_first !== null ? $c->reporter_first.' '.$c->reporter_last : null]),
        ]);
    }

    public function act(Request $request, string $concern, Safeguarding $safeguarding): RedirectResponse
    {
        $data = $request->validate(['actions' => ['required', 'string', 'min:5', 'max:2000'], 'pause_mentor' => ['boolean'], 'close' => ['boolean']]);
        $safeguarding->act($concern, $this->user($request), $data['actions'], (bool) ($data['pause_mentor'] ?? false), (bool) ($data['close'] ?? false));

        return back()->with('status', __('work.saved'));
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
