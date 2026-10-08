<?php

declare(strict_types=1);

namespace Modules\Core\Platform\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Access\BelongsToHub;

/**
 * One row in the append-only platform event log.
 *
 * @property string $id
 * @property string $name
 * @property string|null $actor_id
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string|null $user_id
 * @property string|null $hub_id
 * @property array<string, mixed>|null $payload
 * @property CarbonImmutable $occurred_at
 */
final class PlatformEventRecord extends Model
{
    use BelongsToHub;
    use HasUlids;

    public $timestamps = false;

    protected $table = 'platform_events';

    protected $fillable = ['name', 'actor_id', 'subject_type', 'subject_id', 'user_id', 'hub_id', 'payload', 'occurred_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['payload' => 'array', 'occurred_at' => 'immutable_datetime'];
    }
}
