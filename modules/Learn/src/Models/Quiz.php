<?php

declare(strict_types=1);

namespace Modules\Learn\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $lesson_id
 * @property bool $graded
 * @property int $pass_mark
 * @property int $max_attempts
 * @property bool $shuffle
 */
final class Quiz extends Model
{
    public $timestamps = false;

    protected $table = 'learn_quizzes';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['graded' => 'boolean', 'shuffle' => 'boolean', 'pass_mark' => 'integer', 'max_attempts' => 'integer'];
    }

    /** @return HasMany<Question, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('position');
    }
}
