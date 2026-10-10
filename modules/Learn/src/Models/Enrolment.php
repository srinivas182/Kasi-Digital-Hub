<?php

declare(strict_types=1);

namespace Modules\Learn\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $user_id
 * @property string $course_id
 * @property string $version_id
 * @property string $status
 * @property int $progress
 * @property string|null $last_lesson_id
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable $created_at
 * @property-read Course $course
 * @property-read CourseVersion $version
 */
final class Enrolment extends Model
{
    use HasUlids;

    protected $table = 'learn_enrolments';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['progress' => 'integer', 'completed_at' => 'immutable_datetime', 'left_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<CourseVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(CourseVersion::class);
    }
}
