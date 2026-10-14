<?php

declare(strict_types=1);

namespace Modules\Commercial\Services;

use Illuminate\Support\Facades\DB;
use Modules\Core\Documents\Models\Document;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Structure\Models\Hub;

/**
 * Hub readiness to sell (ADR-027): company registration, VAT status, a verified bank account (four-eyes),
 * and the current franchise agreement accepted. Unpaid package invoices over 30 days block new paid
 * sales - never services for young people.
 */
final readonly class HubFinance
{
    public function __construct(private Approvals $approvals, private AuditLogger $audit) {}

    /** @return array{company: bool, vat: bool, bank: bool, franchise: bool, not_blocked: bool} */
    public function checklist(Hub $hub): array
    {
        $f = DB::table('com_hub_finance')->where('hub_id', $hub->id)->first();

        return [
            'company' => $f !== null && trim((string) $f->company_registration) !== '',
            'vat' => $f !== null && (! $f->vat_registered || trim((string) $f->vat_number) !== ''),
            'bank' => $f !== null && (bool) $f->bank_verified,
            'franchise' => $f !== null && $f->franchise_version === config('kasi.commercial.franchise_version'),
            'not_blocked' => $f === null || ! $f->sales_blocked,
        ];
    }

    public function canSell(Hub $hub): bool
    {
        return ! in_array(false, $this->checklist($hub), true);
    }

    public function vatRegistered(?Hub $hub): bool
    {
        return $hub !== null && (bool) DB::table('com_hub_finance')->where('hub_id', $hub->id)->value('vat_registered');
    }

    /** @param array{company_registration?: string|null, vat_registered?: bool, vat_number?: string|null} $data */
    public function saveDetails(Hub $hub, array $data, User $by): void
    {
        self::upsert($hub->id, $data);
        $this->audit->record('commercial.hub_details', meta: ['hub' => $hub->id], actor: $by);
    }

    public function acceptFranchise(Hub $hub, User $by): void
    {
        self::upsert($hub->id, ['franchise_version' => config('kasi.commercial.franchise_version'), 'franchise_accepted_at' => now(), 'franchise_accepted_by' => $by->id]);
        $this->audit->record('commercial.franchise_accepted', meta: ['hub' => $hub->id, 'version' => config('kasi.commercial.franchise_version')], actor: $by);
    }

    /** Request a bank account change; it applies only after a second finance admin approves. */
    public function requestBank(Hub $hub, Document $letter, string $label, ?string $subaccount, User $by): int
    {
        return $this->approvals->request('bank_account', $hub->id, ['document_id' => $letter->id, 'label' => $label, 'subaccount' => $subaccount], $by);
    }

    public function decideBank(int $approvalId, User $by, bool $approve, ?string $reason = null): void
    {
        $d = $this->approvals->decide($approvalId, $by, $approve, $reason);
        if (! $approve || $d['kind'] !== 'bank_account') {
            return;
        }
        $verified = Document::query()->whereKey($d['change']['document_id'] ?? null)->where('status', Document::VERIFIED)->exists();
        self::upsert($d['subject_id'], ['bank_document_id' => $d['change']['document_id'] ?? null, 'bank_account_label' => $d['change']['label'] ?? null,
            'provider_subaccount' => $d['change']['subaccount'] ?? null, 'bank_verified' => $verified]);
    }

    /** @param array<string, mixed> $values */
    public static function upsert(string $hubId, array $values): void
    {
        if (DB::table('com_hub_finance')->where('hub_id', $hubId)->exists()) {
            DB::table('com_hub_finance')->where('hub_id', $hubId)->update([...$values, 'updated_at' => now()]);
        } else {
            DB::table('com_hub_finance')->insert([...$values, 'hub_id' => $hubId, 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
