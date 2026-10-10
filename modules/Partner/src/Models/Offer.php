<?php

declare(strict_types=1);

namespace Modules\Partner\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Structure\Models\Organisation;

/**
 * @property string $id
 * @property string $organisation_id
 * @property string $title
 * @property string $type
 * @property string $description
 * @property int|null $value_min_cents
 * @property int|null $value_max_cents
 * @property CarbonImmutable $opens_on
 * @property CarbonImmutable|null $closes_on
 * @property int|null $capacity
 * @property array<string, mixed> $criteria
 * @property list<string>|null $documents
 * @property string $status
 * @property string|null $status_reason
 * @property-read Organisation $organisation
 */
final class Offer extends Model
{
    use HasUlids;

    public const TYPES = ['grant', 'loan', 'equipment', 'training', 'mentoring', 'market', 'legal'];

    protected $table = 'partner_offers';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['criteria' => 'array', 'documents' => 'array', 'opens_on' => 'immutable_date', 'closes_on' => 'immutable_date', 'value_min_cents' => 'integer', 'value_max_cents' => 'integer', 'capacity' => 'integer'];
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
