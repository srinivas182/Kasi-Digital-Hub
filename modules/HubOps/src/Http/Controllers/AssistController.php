<?php

declare(strict_types=1);

namespace Modules\HubOps\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Assist\AssistedSession;
use Modules\Core\Documents\DocumentVault;
use Modules\Core\Documents\Models\Document;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;

/**
 * Helping a person who is at the hub: profile details and a document photo, with the person
 * present and agreeing. Every change is audited as "by facilitator, for person".
 */
final class AssistController extends StaffController
{
    public function show(Request $request, AssistedSession $assist): Response|RedirectResponse
    {
        $person = $assist->person($this->actor($request));
        if ($person === null) {
            return to_route('hubops.checkin')->withErrors(['assist' => __('hubops.assist.expired')]);
        }

        return Inertia::render('HubOps/Assist', [
            'person' => [
                'id' => $person->id, 'name' => $person->fullName(), 'preferredName' => $person->preferred_name,
                'placeName' => $person->place_name, 'minor' => $person->isMinor(),
            ],
            'documents' => Document::query()->where('user_id', $person->id)->latest()->get()
                ->map(static fn (Document $d): array => ['id' => $d->id, 'type' => $d->type, 'status' => $d->status]),
            'documentTypes' => config('kasi.documents.types'),
        ]);
    }

    public function updateProfile(Request $request, AssistedSession $assist, AuditLogger $audit): RedirectResponse
    {
        $person = $this->person($request, $assist);
        $validated = $request->validate([
            'preferred_name' => ['nullable', 'string', 'max:80'],
            'place_name' => ['nullable', 'string', 'max:120'],
            'present' => ['accepted'],
        ]);

        $person->forceFill(['preferred_name' => ($validated['preferred_name'] ?? '') ?: null, 'place_name' => ($validated['place_name'] ?? '') ?: null])->save();
        $audit->record('profile.updated_assisted', $person, meta: ['changed' => array_keys($person->getChanges())], actor: $this->actor($request));

        return back()->with('status', __('hubops.assist.saved'));
    }

    public function uploadDocument(Request $request, AssistedSession $assist, DocumentVault $vault): RedirectResponse
    {
        $person = $this->person($request, $assist);
        $validated = $request->validate([
            'type' => ['required', Rule::in((array) config('kasi.documents.types'))],
            'file' => ['required', File::types(['pdf', 'jpg', 'jpeg', 'png'])->max(10 * 1024)],
            'present' => ['accepted'],
        ]);

        $vault->store($person, $request->file('file'), $validated['type'], uploadedBy: $this->actor($request));

        return back()->with('status', __('hubops.assist.document_saved'));
    }

    public function end(Request $request, AssistedSession $assist): RedirectResponse
    {
        $assist->end($this->actor($request));

        return to_route('hubops.checkin')->with('status', __('hubops.assist.ended'));
    }

    private function person(Request $request, AssistedSession $assist): User
    {
        $person = $assist->person($this->actor($request));
        abort_if($person === null, 409, __('hubops.assist.expired'));

        return $person;
    }
}
