<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Identity\Models\AuditLog;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Audit log viewer with filters and CSV export (export is rate-limited and itself audited).
 */
final class AuditController
{
    use Concerns;

    public function index(Request $request): Response
    {
        $logs = $this->query($request)->paginate(50)->withQueryString();
        $names = User::query()->whereIn('id', collect($logs->items())->flatMap(fn (AuditLog $l) => [$l->user_id, $l->actor_id])->filter()->unique())->get()->keyBy('id');

        return Inertia::render('Admin/Audit/Index', [
            'filters' => $request->only(['event', 'person', 'actor', 'from', 'to']),
            'logs' => $logs->through(static fn (AuditLog $l): array => [
                'id' => $l->id,
                'event' => $l->event,
                'outcome' => $l->outcome,
                'person' => $l->user_id !== null ? ($names[$l->user_id]?->fullName() ?? $l->user_id) : null,
                'personId' => $l->user_id,
                'actor' => $l->actor_id !== null ? ($names[$l->actor_id]?->fullName() ?? $l->actor_id) : null,
                'meta' => $l->meta,
                'ip' => $l->ip_address,
                'at' => $l->created_at->toIso8601String(),
            ]),
            'canExport' => $this->can($request, 'admin.audit.export'),
        ]);
    }

    public function export(Request $request, AuditLogger $audit): StreamedResponse
    {
        $audit->record('audit.exported', meta: ['filters' => $request->only(['event', 'person', 'actor', 'from', 'to'])], actor: $this->actor($request));
        $query = $this->query($request);

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['time', 'event', 'outcome', 'person_id', 'actor_id', 'ip', 'details'], escape: '\\');
            $query->chunk(1000, function ($logs) use ($out): void {
                foreach ($logs as $log) {
                    fputcsv($out, [$log->created_at->toIso8601String(), $log->event, $log->outcome, $log->user_id, $log->actor_id, $log->ip_address, json_encode($log->meta)], escape: '\\');
                }
            });
            fclose($out);
        }, 'kasihub-audit-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    /** @return Builder<AuditLog> */
    private function query(Request $request): Builder
    {
        $validated = $request->validate([
            'event' => ['nullable', 'string', 'max:64'],
            'person' => ['nullable', 'string', 'size:26'],
            'actor' => ['nullable', 'string', 'size:26'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return AuditLog::query()
            ->when($validated['event'] ?? null, fn ($q, $event) => $q->where('event', 'like', $event.'%'))
            ->when($validated['person'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->when($validated['actor'] ?? null, fn ($q, $id) => $q->where('actor_id', $id))
            ->when($validated['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', $from))
            ->when($validated['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<', CarbonImmutable::parse($to)->addDay()))
            ->latest('created_at')->latest('id');
    }
}
