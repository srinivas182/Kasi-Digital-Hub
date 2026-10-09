<?php

declare(strict_types=1);

namespace Modules\Core\Documents\Generation;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $generated_document_id
 * @property string $token_hash
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $revoked_at
 * @property int $views
 * @property CarbonImmutable|null $last_viewed_at
 * @property-read GeneratedDocument $document
 */
final class DocumentShareLink extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime', 'last_viewed_at' => 'immutable_datetime', 'views' => 'integer'];
    }

    /** @return BelongsTo<GeneratedDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(GeneratedDocument::class, 'generated_document_id');
    }

    public function usable(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture() && $this->document->status() === 'valid';
    }
}
