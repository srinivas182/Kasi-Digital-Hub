<?php

declare(strict_types=1);

namespace Modules\Commercial\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;

/**
 * Four-eyes approval (ADR-027): prices, split rules and hub bank accounts change only when a second
 * commercial or finance admin approves. The requester can never approve their own change.
 */
final readonly class Approvals
{
    public const KINDS = ['price', 'split_rule', 'bank_account'];

    public function __construct(private AuditLogger $audit) {}

    /** @param array<string, mixed> $change */
    public function request(string $kind, string $subjectId, array $change, User $by): int
    {
        $id = (int) DB::table('com_approvals')->insertGetId(['kind' => $kind, 'subject_id' => $subjectId, 'change' => json_encode($change),
            'requested_by' => $by->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->audit->record('commercial.change_requested', meta: ['approval' => $id, 'kind' => $kind, 'subject' => $subjectId], actor: $by);

        return $id;
    }

    /** @return array{kind: string, subject_id: string, change: array<string, mixed>} */
    public function decide(int $id, User $by, bool $approve, ?string $reason = null): array
    {
        $row = DB::table('com_approvals')->where('id', $id)->where('status', 'pending')->first();
        if ($row === null) {
            throw new DomainException(__('commercial.approval.gone'));
        }
        if ($row->requested_by === $by->id) {
            throw new DomainException(__('commercial.approval.not_own'));
        }
        DB::table('com_approvals')->where('id', $id)->update(['status' => $approve ? 'approved' : 'rejected', 'decided_by' => $by->id, 'reason' => $reason, 'updated_at' => now()]);
        $this->audit->record('commercial.change_'.($approve ? 'approved' : 'rejected'), meta: ['approval' => $id, 'kind' => $row->kind], actor: $by);

        return ['kind' => (string) $row->kind, 'subject_id' => (string) $row->subject_id, 'change' => (array) json_decode((string) $row->change, true)];
    }
}
