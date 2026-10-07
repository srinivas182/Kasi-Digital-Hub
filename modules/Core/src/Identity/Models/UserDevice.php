<?php

declare(strict_types=1);

namespace Modules\Core\Identity\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A browser or phone a user has signed in from. Remembered devices allow
 * phone + PIN sign-in without an SMS code for 30 days.
 *
 * @property string $id
 * @property string $user_id
 * @property string|null $token_hash
 * @property string|null $session_id
 * @property string $name
 * @property string|null $ip_address
 * @property CarbonImmutable|null $last_seen_at
 * @property CarbonImmutable|null $remembered_until
 * @property CarbonImmutable|null $revoked_at
 * @property CarbonImmutable $created_at
 */
final class UserDevice extends Model
{
    use HasUlids;

    protected $fillable = ['user_id', 'token_hash', 'session_id', 'name', 'ip_address', 'last_seen_at', 'remembered_until', 'revoked_at'];

    protected $hidden = ['token_hash', 'session_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'immutable_datetime',
            'remembered_until' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function isRemembered(): bool
    {
        return $this->revoked_at === null && $this->remembered_until !== null && $this->remembered_until->isFuture();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
