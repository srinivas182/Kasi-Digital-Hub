<?php

declare(strict_types=1);

namespace Modules\Connect\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Identity\Models\User;

/**
 * @property string $user_id
 * @property string $headline
 * @property string|null $bio
 * @property list<string> $expertise
 * @property list<string> $goals
 * @property list<string> $languages
 * @property list<string> $formats
 * @property string|null $hub_id
 * @property int|null $municipality_id
 * @property string|null $availability
 * @property int $capacity
 * @property string $status
 * @property string|null $status_reason
 * @property bool $minors_ok
 * @property int $minutes_mentored
 * @property-read User $user
 */
final class Mentor extends Model
{
    public const GOALS = ['job', 'business', 'career', 'confidence'];

    public const FORMATS = ['phone', 'video', 'hub'];

    public const CHECKS = ['id_verified', 'reference_1', 'reference_2', 'code_of_conduct', 'safeguarding_course'];

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'connect_mentors';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['expertise' => 'array', 'goals' => 'array', 'languages' => 'array', 'formats' => 'array', 'capacity' => 'integer', 'minors_ok' => 'boolean', 'minutes_mentored' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
