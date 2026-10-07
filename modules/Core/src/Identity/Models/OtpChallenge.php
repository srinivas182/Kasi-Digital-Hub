<?php

declare(strict_types=1);

namespace Modules\Core\Identity\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * A one-time SMS code. Only an HMAC of the code is stored.
 *
 * @property string $id
 * @property string $phone
 * @property string $purpose
 * @property string $code_hash
 * @property int $attempts
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $consumed_at
 * @property string|null $ip_address
 * @property string|null $device_hash
 * @property CarbonImmutable $created_at
 */
final class OtpChallenge extends Model
{
    use HasUlids;
    use MassPrunable;

    protected $fillable = ['phone', 'purpose', 'code_hash', 'attempts', 'expires_at', 'consumed_at', 'ip_address', 'device_hash'];

    protected $hidden = ['code_hash'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function isUsable(int $maxAttempts): bool
    {
        return $this->consumed_at === null && $this->expires_at->isFuture() && $this->attempts < $maxAttempts;
    }

    /**
     * Records older than the retention period are deleted by the scheduled model:prune.
     *
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return self::query()->where('created_at', '<', now()->subDays(1));
    }
}
