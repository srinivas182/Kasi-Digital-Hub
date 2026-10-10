<?php

declare(strict_types=1);

namespace Modules\Learn\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\Organisation;
use Modules\Core\Structure\Models\RoleAssignment;

/** Training providers a person works for (admin, author or assessor), and their role there. */
final class CurrentProvider
{
    public const ROLES = ['provider_admin', 'course_author', 'assessor_moderator'];

    /** @return Collection<int, Organisation> */
    public function all(User $user): Collection
    {
        $ids = RoleAssignment::query()->where('user_id', $user->id)->whereIn('role', self::ROLES)->where('scope_type', 'organisation')->pluck('scope_id');

        return Organisation::query()->whereIn('id', $ids)->where('type', 'training_provider')->orderBy('name')->get();
    }

    public function resolve(Request $request, User $user): ?Organisation
    {
        $providers = $this->all($user);
        $wanted = $request->query('provider') ?? $request->session()->get('learn.provider');
        $provider = $providers->firstWhere('id', $wanted) ?? $providers->first();
        if ($provider !== null) {
            $request->session()->put('learn.provider', $provider->id);
        }

        return $provider;
    }

    public function has(User $user, Organisation $provider, string $role): bool
    {
        return RoleAssignment::query()->where('user_id', $user->id)->where('role', $role)->where('scope_type', 'organisation')->where('scope_id', $provider->id)->exists();
    }

    public function canAuthor(User $user, Organisation $provider): bool
    {
        return $this->has($user, $provider, 'course_author') || $this->has($user, $provider, 'provider_admin');
    }
}
