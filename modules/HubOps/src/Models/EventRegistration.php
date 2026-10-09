<?php

declare(strict_types=1);

namespace Modules\HubOps\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Identity\Models\User;

/**
 * @property string $id
 * @property string $event_id
 * @property string $user_id
 * @property string $status
 * @property string|null $registered_by
 * @property CarbonImmutable|null $attended_at
 * @property CarbonImmutable|null $reminded_at
 * @property CarbonImmutable $created_at
 * @property-read HubEvent $event
 * @property-read User $user
 */
final class EventRegistration extends Model
{
    use HasUlids;

    public const REGISTERED = 'registered';

    public const WAITLISTED = 'waitlisted';

    public const CANCELLED = 'cancelled';

    protected $table = 'hub_event_registrations';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['attended_at' => 'immutable_datetime', 'reminded_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<HubEvent, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(HubEvent::class, 'event_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
