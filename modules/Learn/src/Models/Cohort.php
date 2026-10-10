<?php

declare(strict_types=1);

namespace Modules\Learn\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Structure\Models\Hub;

/**
 * @property string $id
 * @property string $course_id
 * @property string $organisation_id
 * @property string|null $hub_id
 * @property string $name
 * @property string $code
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property int $capacity
 * @property string $status
 * @property string|null $status_reason
 * @property-read Course $course
 * @property-read Hub|null $hub
 */
final class Cohort extends Model
{
    use HasUlids;

    protected $table = 'learn_cohorts';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_on' => 'immutable_date', 'ends_on' => 'immutable_date', 'capacity' => 'integer'];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<Hub, $this> */
    public function hub(): BelongsTo
    {
        return $this->belongsTo(Hub::class);
    }
}
