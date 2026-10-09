<?php

declare(strict_types=1);

namespace Modules\Work\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Formal or informal experience. Informal work counts: piece jobs, own or family business,
 * caregiving, volunteering.
 *
 * @property string $id
 * @property string $user_id
 * @property string $kind
 * @property string $title
 * @property string|null $organisation
 * @property string|null $place
 * @property string|null $started
 * @property string|null $ended
 * @property string|null $duration
 * @property string|null $description
 * @property list<string>|null $bullets
 * @property list<string>|null $suggested_bullets
 * @property int $position
 */
final class WorkExperience extends Model
{
    use HasUlids;

    public const KINDS = ['job', 'piece_work', 'own_business', 'family_business', 'caregiving', 'volunteer', 'learnership'];

    protected $table = 'work_experiences';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['bullets' => 'array', 'suggested_bullets' => 'array'];
    }
}
