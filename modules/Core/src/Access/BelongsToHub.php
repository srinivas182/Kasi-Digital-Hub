<?php

declare(strict_types=1);

namespace Modules\Core\Access;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Identity\Models\User;

/**
 * For models with a hub_id column. Use ->visibleTo($user, 'Module') in every query that
 * shows hub data to staff: national roles see all hubs, province/city roles their hubs,
 * hub roles their own hub, everyone else none. A guard test fails the build if a model
 * with a hub_id column does not use this trait.
 */
trait BelongsToHub
{
    /**
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user, string $module): void
    {
        $hubs = app(AccessResolver::class)->visibleHubIds($user, $module);

        if ($hubs === '*') {
            return;
        }

        $query->whereIn($this->qualifyColumn($this->hubColumn()), $hubs);
    }

    protected function hubColumn(): string
    {
        return 'hub_id';
    }
}
