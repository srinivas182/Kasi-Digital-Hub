<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Account;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Core\Documents\DocumentVault;
use Modules\Core\Documents\Models\Document;
use Modules\Core\Identity\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Upload, open (signed short-lived link) and delete documents in a person's vault.
 */
final class DocumentController
{
    public function __construct(private readonly DocumentVault $vault) {}

    public function store(Request $request): RedirectResponse
    {
        $user = $this->user($request);

        $validated = $request->validate([
            'type' => ['required', Rule::in((array) config('kasi.documents.types'))],
            'file' => ['required', 'file', 'max:'.config('kasi.documents.max_kb'), 'mimes:'.implode(',', (array) config('kasi.documents.mimes'))],
            'expires_on' => ['nullable', 'date', 'after:today'],
        ]);

        $this->vault->store($user, $validated['file'], $validated['type'], $validated['expires_on'] ?? null);

        return back()->with('status', __('documents.uploaded'));
    }

    /** Opened through a signed link that expires after a few minutes; permission is checked again here. */
    public function download(Request $request, Document $document): StreamedResponse
    {
        abort_unless($request->hasValidSignature(), 403);
        abort_unless($this->vault->canOpen($this->user($request), $document), 403);

        return $this->vault->download($document, $this->user($request));
    }

    public function destroy(Request $request, Document $document): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($document->user_id === $user->id, 404);

        $this->vault->delete($document, $user);

        return back()->with('status', __('documents.deleted'));
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
