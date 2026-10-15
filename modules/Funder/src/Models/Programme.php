<?php

declare(strict_types=1);

namespace Modules\Funder\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Structure\Models\Organisation;

/**
 * @property string $id
 * @property string $organisation_id
 * @property string $name
 * @property string|null $summary
 * @property array{province_ids?: list<int>, municipality_ids?: list<int>, hub_ids?: list<string>} $scope
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property int $age_min
 * @property int $age_max
 * @property string $status
 * @property-read Organisation $organisation
 */
final class Programme extends Model
{
    use HasUlids;

    protected $table = 'fund_programmes';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['scope' => 'array', 'starts_on' => 'immutable_date', 'ends_on' => 'immutable_date', 'age_min' => 'integer', 'age_max' => 'integer'];
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
