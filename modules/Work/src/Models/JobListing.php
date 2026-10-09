<?php

declare(strict_types=1);

namespace Modules\Work\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Structure\Models\Municipality;
use Modules\Core\Structure\Models\Organisation;

/**
 * @property string $id
 * @property string $organisation_id
 * @property string|null $created_by
 * @property string $title
 * @property int|null $occupation_id
 * @property string $type
 * @property int $positions
 * @property int|null $municipality_id
 * @property string|null $place_name
 * @property string|null $latitude
 * @property string|null $longitude
 * @property int $pay_min_cents
 * @property int|null $pay_max_cents
 * @property string $pay_period
 * @property string|null $hours
 * @property string|null $education
 * @property string|null $licence
 * @property string $experience
 * @property list<string>|null $languages
 * @property string $description
 * @property CarbonImmutable $closes_on
 * @property string $status
 * @property string|null $status_reason
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $closed_at
 * @property CarbonImmutable|null $reminded_at
 * @property CarbonImmutable $created_at
 * @property-read Organisation $organisation
 * @property-read OfoOccupation|null $occupation
 * @property-read Municipality|null $municipality
 */
final class JobListing extends Model
{
    use HasUlids;

    public const TYPES = ['full_time', 'part_time', 'piece_work', 'learnership', 'internship', 'temporary'];

    public const PERIODS = ['hour', 'day', 'week', 'month'];

    public const EDUCATION = ['none', 'grade_9', 'matric', 'certificate', 'diploma', 'degree'];

    public const EXPERIENCE = ['none', 'some', '1_year', '2_years'];

    /** Statuses that count towards an employer's active listing limit. */
    public const ACTIVE = ['review', 'live'];

    /** Stipend-based types: the national minimum wage check does not apply. */
    public const STIPEND_TYPES = ['learnership', 'internship'];

    protected $table = 'work_listings';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'languages' => 'array', 'closes_on' => 'immutable_date', 'published_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime', 'reminded_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime',
            'pay_min_cents' => 'integer', 'pay_max_cents' => 'integer', 'positions' => 'integer',
        ];
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return BelongsTo<OfoOccupation, $this> */
    public function occupation(): BelongsTo
    {
        return $this->belongsTo(OfoOccupation::class);
    }

    /** @return BelongsTo<Municipality, $this> */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /** @return HasMany<JobListingSkill, $this> */
    public function skills(): HasMany
    {
        return $this->hasMany(JobListingSkill::class, 'listing_id');
    }

    /** @return HasMany<ScreeningQuestion, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(ScreeningQuestion::class, 'listing_id')->orderBy('position');
    }

    /** @param Builder<JobListing> $query */
    public function scopeLive(Builder $query): void
    {
        $query->where('status', 'live')->whereDate('closes_on', '>=', now('Africa/Johannesburg')->toDateString());
    }
}
