<?php

declare(strict_types=1);

namespace Modules\Start\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Start\Models\Business;
use Modules\Start\Models\Step;

/** The KasiHub team edits the formalisation steps (wording, links, costs, "last checked") - no code change. */
final class StepsAdminController
{
    public function index(): Response
    {
        return Inertia::render('Start/AdminSteps', [
            'steps' => Step::query()->orderBy('position')->get()->map(static fn (Step $s): array => [...$s->only(['id', 'key', 'title', 'summary', 'why', 'needs', 'where', 'link', 'cost_note', 'duration', 'applies_to', 'document_type', 'position', 'active']),
                'lastChecked' => $s->last_checked_on?->toDateString()]),
            'forms' => Business::FORMS, 'sectors' => Business::SECTORS, 'documentTypes' => (array) config('kasi.documents.types'),
        ]);
    }

    public function update(Request $request, Step $step, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'], 'summary' => ['required', 'string', 'max:2000'], 'why' => ['nullable', 'string', 'max:2000'],
            'needs' => ['array', 'max:12'], 'needs.*' => ['string', 'max:200'], 'where' => ['nullable', 'string', 'max:1000'],
            'link' => ['nullable', 'url:https', 'max:255'], 'cost_note' => ['nullable', 'string', 'max:200'], 'duration' => ['nullable', 'string', 'max:80'],
            'forms' => ['array'], 'forms.*' => [Rule::in([...Business::FORMS, '*'])], 'sectors' => ['array'], 'sectors.*' => [Rule::in([...Business::SECTORS, '*'])],
            'employees' => ['nullable', 'boolean'], 'document_type' => ['nullable', Rule::in((array) config('kasi.documents.types'))],
            'active' => ['boolean'], 'checked' => ['boolean'],
        ]);
        $user = $request->user();
        $step->update([
            'title' => $data['title'], 'summary' => $data['summary'], 'why' => $data['why'] ?? null, 'needs' => array_values(array_filter($data['needs'] ?? [])),
            'where' => $data['where'] ?? null, 'link' => $data['link'] ?? null, 'cost_note' => $data['cost_note'] ?? null, 'duration' => $data['duration'] ?? null,
            'applies_to' => ['forms' => $data['forms'] ?? ['*'], 'sectors' => $data['sectors'] ?? ['*'], 'employees' => ($data['employees'] ?? false) ? true : null],
            'document_type' => $data['document_type'] ?? null, 'active' => (bool) ($data['active'] ?? true),
            'last_checked_on' => ($data['checked'] ?? false) ? now()->toDateString() : $step->last_checked_on, 'updated_by' => $user instanceof User ? $user->id : null,
        ]);
        $audit->record('start.step_edited', meta: ['step' => $step->key], actor: $user instanceof User ? $user : null);

        return back()->with('status', __('work.saved'));
    }
}
