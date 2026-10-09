<?php

declare(strict_types=1);

namespace Modules\HubOps\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Access\BelongsToHub;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\Hub;

/**
 * A job day, workshop, info session or class at a hub.
 *
 * @property string $id
 * @property string $hub_id
 * @property string $type
 * @property string $title
 * @property string|null $description
 * @property string|null $room
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property int $capacity
 * @property string $audience
 * @property string $status
 * @property string|null $cancel_reason
 * @property string|null $created_by
 * @property-read Hub $hub
 * @property-read int|null $registered_count
 * @property-read int|null $waitlisted_count
 * @property-read int|null $attended_count
 */
final class HubEvent extends Model
{
    use BelongsToHub;
    use HasUlids;

    public const TYPES = ['job_day', 'workshop', 'info_session', 'class'];

    /** public: listed on the website, adults; members: signed-in adults; learners: open to 16-17 too */
    public const AUDIENCES = ['public', 'members', 'learners'];

    protected $table = 'hub_events';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'capacity' => 'integer'];
    }

    /** @return BelongsTo<Hub, $this> */
    public function hub(): BelongsTo
    {
        return $this->belongsTo(Hub::class);
    }

    /** @return HasMany<EventRegistration, $this> */
    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class, 'event_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isPast(): bool
    {
        return $this->ends_at->isPast();
    }

    /** Attendance can be taken from 30 minutes before the start until the end. */
    public function attendanceOpen(): bool
    {
        return ! $this->isCancelled() && now()->between($this->starts_at->subMinutes(30), $this->ends_at);
    }

    public function allows(User $user): bool
    {
        return $this->audience === 'learners' || ! $user->isMinor();
    }
}
