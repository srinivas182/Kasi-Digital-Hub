<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Ai\Models\ModerationFlag;
use Modules\Core\Identity\Services\AuditLogger;

/**
 * Content review queue: approve, reject or escalate what the automatic checks flagged.
 */
final class ModerationController
{
    use Concerns;

    /** Where staff can see the flagged item. Portals add theirs here as they arrive. */
    private const LINKS = ['hub_event' => '/hub-ops/events/'];

    public function index(Request $request): Response
    {
        $status = in_array($request->query('status'), ModerationFlag::STATUSES, true) ? (string) $request->query('status') : 'pending';

        return Inertia::render('Admin/Moderation', [
            'status' => $status,
            'flags' => ModerationFlag::query()->with('author:id,first_name,last_name')->where('status', $status)->oldest()->paginate(20)->withQueryString()
                ->through(static fn (ModerationFlag $f): array => [
                    'id' => $f->id, 'type' => $f->subject_type, 'excerpt' => $f->excerpt, 'reasons' => $f->reasons, 'source' => $f->source,
                    'author' => $f->author?->fullName(), 'authorId' => $f->author_id, 'decision' => $f->decision_reason,
                    'link' => isset(self::LINKS[$f->subject_type]) ? self::LINKS[$f->subject_type].$f->subject_id : null,
                    'at' => $f->created_at->toIso8601String(),
                ]),
        ]);
    }

    public function decide(Request $request, ModerationFlag $flag, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected', 'escalated'])],
            'reason' => ['required', 'string', 'min:5', 'max:300'],
        ]);
        abort_unless(in_array($flag->status, ['pending', 'escalated'], true), 409);

        $flag->forceFill(['status' => $validated['decision'], 'decision_reason' => $validated['reason'], 'reviewed_by' => $this->actor($request)->id, 'reviewed_at' => now()])->save();
        $audit->record('moderation.'.$validated['decision'], meta: ['flag' => $flag->id, 'subject' => $flag->subject_type.':'.$flag->subject_id, 'reason' => $validated['reason']], actor: $this->actor($request));

        return back()->with('status', __('admin.moderation.done'));
    }
}
