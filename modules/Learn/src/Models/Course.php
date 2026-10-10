<?php

declare(strict_types=1);

namespace Modules\Learn\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Structure\Models\Organisation;

/**
 * @property string $id
 * @property string $organisation_id
 * @property string $slug
 * @property string $title
 * @property string|null $summary
 * @property list<string>|null $outcomes
 * @property string $topic
 * @property string $level
 * @property string|null $hours
 * @property string|null $prerequisites
 * @property string $language
 * @property string|null $translation_group
 * @property int $min_age
 * @property string $delivery
 * @property int $attendance_percent
 * @property string|null $accreditation_id
 * @property int|null $nqf_level
 * @property int|null $credits
 * @property string $licence
 * @property string|null $attribution
 * @property string|null $image_id
 * @property string $status
 * @property string|null $status_reason
 * @property string|null $current_version_id
 * @property bool $changed_since_publish
 * @property string|null $created_by
 * @property CarbonImmutable $created_at
 * @property-read Organisation $organisation
 * @property-read CourseVersion|null $currentVersion
 * @property-read Accreditation|null $accreditation
 */
final class Course extends Model
{
    use HasUlids;

    public const TOPICS = ['digital_skills', 'work_readiness', 'customer_service', 'business', 'finance', 'trades', 'agriculture', 'health_care', 'hospitality', 'creative', 'languages', 'other'];

    public const LEVELS = ['beginner', 'intermediate', 'advanced'];

    public const DELIVERY = ['self_paced', 'blended', 'hub'];

    public const LICENCES = ['all_rights', 'cc_by', 'cc_by_sa'];

    protected $table = 'learn_courses';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['outcomes' => 'array', 'min_age' => 'integer', 'attendance_percent' => 'integer', 'nqf_level' => 'integer', 'credits' => 'integer', 'changed_since_publish' => 'boolean', 'created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return BelongsTo<CourseVersion, $this> */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(CourseVersion::class, 'current_version_id');
    }

    /** @return BelongsTo<Accreditation, $this> */
    public function accreditation(): BelongsTo
    {
        return $this->belongsTo(Accreditation::class);
    }

    /** @return HasMany<CourseModule, $this> */
    public function modules(): HasMany
    {
        return $this->hasMany(CourseModule::class)->orderBy('position');
    }

    /** @return HasMany<Lesson, $this> */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('position');
    }

    /** Accredited only when the claim was verified by the KasiHub team. */
    public function accredited(): bool
    {
        return $this->accreditation?->status === 'verified';
    }
}
