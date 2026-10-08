<?php

declare(strict_types=1);

namespace Modules\Core\Structure\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Identity\Models\User;

/**
 * A role held by a person within a scope (self, organisation, hub, city, province or national).
 *
 * @property string $id
 * @property string $user_id
 * @property string $role
 * @property string $scope_type
 * @property string|null $scope_id
 * @property string|null $granted_by
 * @property CarbonImmutable|null $expires_at
 */
final class RoleAssignment extends Model
{
    use HasUlids;

    protected $fillable = ['user_id', 'role', 'scope_type', 'scope_id', 'granted_by', 'expires_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime'];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
