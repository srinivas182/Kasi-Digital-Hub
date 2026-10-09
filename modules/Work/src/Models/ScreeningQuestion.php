<?php

declare(strict_types=1);

namespace Modules\Work\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $listing_id
 * @property string $question
 * @property string $kind
 * @property int $position
 */
final class ScreeningQuestion extends Model
{
    public $timestamps = false;

    protected $table = 'work_screening_questions';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }
}
