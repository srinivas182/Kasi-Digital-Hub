<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Support\Format\SaFormat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Platform\Models\Enquiry;

/**
 * Website enquiries inbox (contact, employer, funder).
 */
final class EnquiriesController
{
    use Concerns;

    public function index(Request $request): Response
    {
        $kind = $request->string('kind')->toString() ?: null;
        $status = $request->string('status')->toString() ?: 'new';

        return Inertia::render('Admin/Enquiries/Index', [
            'filters' => ['kind' => $kind, 'status' => $status],
            'enquiries' => Enquiry::query()
                ->when($kind, fn ($q) => $q->where('kind', $kind))
                ->where('status', $status)
                ->latest()->paginate(20)->withQueryString()
                ->through(static fn (Enquiry $e): array => [
                    'id' => $e->id, 'kind' => $e->kind, 'name' => $e->name, 'organisation' => $e->organisation,
                    'phone' => $e->phone !== null ? SaFormat::phone($e->phone) : null, 'email' => $e->email,
                    'topic' => $e->topic, 'message' => $e->message, 'status' => $e->status,
                    'receivedAt' => $e->created_at?->toIso8601String(),
                ]),
            'kinds' => Enquiry::KINDS,
        ]);
    }

    public function update(Request $request, Enquiry $enquiry, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', Rule::in(['new', 'handled', 'spam'])]]);
        $enquiry->update($validated);
        $audit->record('enquiry.updated', meta: ['enquiry' => $enquiry->id, 'status' => $validated['status']], actor: $this->actor($request));

        return back()->with('status', __('admin.saved'));
    }
}
