<?php

declare(strict_types=1);

namespace Modules\Core\Identity\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * Security and activity trail. Append-only.
 *
 * @property string $id
 * @property string|null $user_id
 * @property string|null $actor_id
 * @property string $event
 * @property string $outcome
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property array<string, mixed>|null $meta
 * @property CarbonImmutable $created_at
 */
final class AuditLog extends Model
{
    use HasUlids;
    use MassPrunable;

    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'actor_id', 'event', 'outcome', 'ip_address', 'user_agent', 'meta', 'created_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'immutable_datetime'];
    }

    /**
     * Records older than the retention period are deleted by the scheduled model:prune.
     *
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return self::query()->where('created_at', '<', now()->subDays((int) config('kasi.identity.audit_retention_days')));
    }
}
