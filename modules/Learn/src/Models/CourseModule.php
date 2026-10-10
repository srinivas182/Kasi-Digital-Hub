<?php

declare(strict_types=1);

namespace Modules\Learn\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $course_id
 * @property string $title
 * @property int $position
 */
final class CourseModule extends Model
{
    public $timestamps = false;

    protected $table = 'learn_modules';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    /** @return HasMany<Lesson, $this> */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class, 'module_id')->orderBy('position');
    }
}
