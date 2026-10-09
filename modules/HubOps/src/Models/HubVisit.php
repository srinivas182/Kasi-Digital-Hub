<?php

declare(strict_types=1);

namespace Modules\HubOps\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Access\BelongsToHub;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\Hub;

/**
 * @property string $id
 * @property string $hub_id
 * @property string|null $user_id
 * @property CarbonImmutable $visit_date
 * @property string $purpose
 * @property string $method
 * @property string|null $checked_in_by
 * @property CarbonImmutable $created_at
 * @property-read User|null $user
 * @property-read Hub $hub
 */
final class HubVisit extends Model
{
    use BelongsToHub;
    use HasUlids;

    public const PURPOSES = ['jobs', 'learning', 'business', 'computer', 'printing', 'event', 'other'];

    public const METHODS = ['qr', 'desk', 'walk_in', 'event', 'assisted'];

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['visit_date' => 'immutable_date', 'created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Hub, $this> */
    public function hub(): BelongsTo
    {
        return $this->belongsTo(Hub::class);
    }
}
