<?php

declare(strict_types=1);

namespace Modules\Learn\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A published version of a course: a frozen copy learners use, so later edits never change a
 * course under someone who is busy with it.
 *
 * @property string $id
 * @property string $course_id
 * @property int $number
 * @property array<string, mixed> $snapshot
 * @property int $data_bytes
 * @property string|null $published_by
 * @property CarbonImmutable $published_at
 */
final class CourseVersion extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $table = 'learn_course_versions';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'number' => 'integer', 'data_bytes' => 'integer', 'published_at' => 'immutable_datetime'];
    }
}
