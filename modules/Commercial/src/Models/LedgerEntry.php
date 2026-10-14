<?php

declare(strict_types=1);

namespace Modules\Commercial\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An immutable ledger line (ADR-027): never updated or deleted - corrections are new entries.
 *
 * @property int $id
 * @property string|null $order_id
 * @property string|null $invoice_id
 * @property string|null $hub_id
 * @property string|null $partner_organisation_id
 * @property string $kind
 * @property string $account
 * @property int $amount_cents
 * @property string|null $memo
 */
final class LedgerEntry extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(static fn (): never => throw new \LogicException('Ledger entries are immutable.'));
        self::deleting(static fn (): never => throw new \LogicException('Ledger entries are immutable.'));
    }

    protected $table = 'com_ledger';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'created_at' => 'immutable_datetime'];
    }
}
