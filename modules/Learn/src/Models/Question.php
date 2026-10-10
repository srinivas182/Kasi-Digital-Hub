<?php

declare(strict_types=1);

namespace Modules\Learn\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $quiz_id
 * @property string $kind
 * @property string $prompt
 * @property list<array{text: string, correct: bool, feedback?: string|null}> $options
 * @property string|null $explanation
 * @property bool $ai_drafted
 * @property int $position
 */
final class Question extends Model
{
    public $timestamps = false;

    protected $table = 'learn_questions';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['options' => 'array', 'ai_drafted' => 'boolean', 'position' => 'integer'];
    }
}
