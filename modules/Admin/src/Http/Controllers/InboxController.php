<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Admin\Support\Support;
use Modules\Core\Ai\Models\ModerationFlag;
use Modules\Core\Events\ModerationDecided;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Insights\ReviewSource;

/** One review inbox (ADR-029) for everything held across the portals; the per-portal screens stay. */
final class InboxController
{
    public function index(): Response
    {
        $items = [];
        /** @var iterable<ReviewSource> $sources */
        $sources = app()->tagged(ReviewSource::TAG);
        foreach ($sources as $source) {
            foreach ($source->waiting() as $item) {
                $items[] = [...$item, 'overdue' => Support::workingDaysSince(CarbonImmutable::parse($item['since'])) >= 2];
            }
        }
        usort($items, static fn (array $a, array $b): int => strcmp($a['since'], $b['since']));

        return Inertia::render('Admin/Inbox', ['items' => $items, 'counts' => array_count_values(array_column($items, 'type'))]);
    }

    /** Approve several held items at once (pending moderation flags only - courses keep their own review). */
    public function bulkApprove(Request $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $data = $request->validate(['ids' => ['required', 'array', 'max:100'], 'ids.*' => ['string'], 'reason' => ['required', 'string', 'min:5', 'max:300']]);
        $count = 0;
        ModerationFlag::query()->whereIn('id', $data['ids'])->where('status', 'pending')->get()->each(function (ModerationFlag $f) use ($user, $data, $audit, &$count): void {
            $f->forceFill(['status' => 'approved', 'decision_reason' => $data['reason'], 'reviewed_by' => $user->id, 'reviewed_at' => now()])->save();
            event(new ModerationDecided($f->subject_type, $f->subject_id, 'approved', $data['reason'], $user->id));
            $audit->record('moderation.approved', meta: ['flag' => $f->id, 'bulk' => true], actor: $user);
            $count++;
        });

        return back()->with('status', __('admin.inbox.approved', ['count' => $count]));
    }
}
