<?php

declare(strict_types=1);

namespace Modules\Work\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $user_id
 * @property string|null $headline
 * @property string|null $summary
 * @property string|null $drivers_licence
 * @property bool $own_transport
 * @property list<string>|null $work_types
 * @property list<string>|null $sectors
 * @property int|null $max_travel_km
 * @property string|null $available_from
 * @property bool $show_age_on_cv
 * @property int $completeness
 * @property CarbonImmutable|null $completed_at
 */
final class WorkProfile extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'work_profiles';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['work_types' => 'array', 'sectors' => 'array', 'own_transport' => 'boolean', 'show_age_on_cv' => 'boolean', 'completed_at' => 'immutable_datetime'];
    }
}
