<?php

declare(strict_types=1);

namespace Modules\Learn\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $lesson_id
 * @property string $instructions
 * @property list<string> $rubric
 * @property list<string> $evidence
 * @property int $max_resubmissions
 */
final class Assignment extends Model
{
    public $timestamps = false;

    protected $table = 'learn_assignments';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['rubric' => 'array', 'evidence' => 'array', 'max_resubmissions' => 'integer'];
    }
}
