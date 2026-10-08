<?php

declare(strict_types=1);

namespace Modules\Core\Documents\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Structure\Models\Organisation;

/**
 * Consent to let one organisation see one document for a stated purpose. Revocable.
 *
 * @property string $id
 * @property string $document_id
 * @property string $organisation_id
 * @property string $purpose
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $revoked_at
 */
final class DocumentShare extends Model
{
    use HasUlids;

    protected $fillable = ['document_id', 'organisation_id', 'purpose', 'expires_at', 'revoked_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
