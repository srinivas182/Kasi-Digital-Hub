<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Documents\DocumentVault;
use Modules\Core\Documents\Models\Document;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\Hub;

/**
 * Document verification queue: oldest first, preview side by side, verify or reject with a reason.
 */
final class VerificationController
{
    use Concerns;

    public const REJECTION_REASONS = ['blurry', 'wrong_document', 'expired', 'name_mismatch', 'incomplete', 'other'];

    public function index(Request $request, DocumentVault $vault): Response
    {
        $type = $request->string('type')->toString() ?: null;
        $hub = $request->string('hub')->toString() ?: null;

        $queue = Document::query()
            ->with('owner:id,first_name,last_name,home_hub_id')
            ->where('status', Document::UPLOADED)
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($hub, fn ($q) => $q->whereIn('user_id', User::query()->where('home_hub_id', $hub)->select('id')))
            ->orderBy('created_at')
            ->paginate(15)->withQueryString();

        return Inertia::render('Admin/Verification/Index', [
            'filters' => ['type' => $type, 'hub' => $hub],
            'documents' => $queue->through(static fn (Document $d): array => [
                'id' => $d->id,
                'type' => $d->type,
                'owner' => $d->owner?->fullName(),
                'ownerId' => $d->user_id,
                'mime' => $d->mime_type,
                'uploadedAt' => $d->created_at->toIso8601String(),
                'waitingHours' => (int) $d->created_at->diffInHours(now()),
                'expiresOn' => $d->expires_on?->toDateString(),
                'previewUrl' => $vault->temporaryUrl($d, inline: true),
            ]),
            'types' => config('kasi.documents.types'),
            'hubs' => Hub::query()->orderBy('name')->get(['id', 'name']),
            'reasons' => self::REJECTION_REASONS,
        ]);
    }

    public function verify(Request $request, Document $document, DocumentVault $vault): RedirectResponse
    {
        abort_unless($document->status === Document::UPLOADED, 409);
        $vault->verify($document, $this->actor($request));

        return back()->with('status', __('admin.verification.verified'));
    }

    public function reject(Request $request, Document $document, DocumentVault $vault): RedirectResponse
    {
        abort_unless($document->status === Document::UPLOADED, 409);
        $validated = $request->validate([
            'reason' => ['required', Rule::in(self::REJECTION_REASONS)],
            'note' => ['nullable', 'string', 'max:300', 'required_if:reason,other'],
        ]);

        $text = __('admin.verification.reason.'.$validated['reason']).(! empty($validated['note']) ? ' - '.$validated['note'] : '');
        $vault->reject($document, $text, $this->actor($request));

        return back()->with('status', __('admin.verification.rejected'));
    }
}
