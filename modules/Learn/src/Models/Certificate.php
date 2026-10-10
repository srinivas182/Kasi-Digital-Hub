<?php

declare(strict_types=1);

namespace Modules\Learn\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Documents\Generation\GeneratedDocument;

/**
 * @property string $id
 * @property string $enrolment_id
 * @property string|null $generated_document_id
 * @property string $kind
 * @property bool $show_on_cv
 * @property CarbonImmutable $issued_at
 * @property CarbonImmutable|null $revoked_at
 * @property string|null $revoke_reason
 * @property-read Enrolment $enrolment
 * @property-read GeneratedDocument|null $document
 */
final class Certificate extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $table = 'learn_certificates';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['show_on_cv' => 'boolean', 'issued_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Enrolment, $this> */
    public function enrolment(): BelongsTo
    {
        return $this->belongsTo(Enrolment::class);
    }

    /** @return BelongsTo<GeneratedDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(GeneratedDocument::class, 'generated_document_id');
    }
}
