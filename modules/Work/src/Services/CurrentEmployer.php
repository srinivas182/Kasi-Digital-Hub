<?php

declare(strict_types=1);

namespace Modules\Work\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\Organisation;
use Modules\Core\Structure\Models\RoleAssignment;

/**
 * Employers a person works for (as employer admin or recruiter), and which one they are using.
 */
final class CurrentEmployer
{
    private const SESSION = 'work.employer';

    /** @return Collection<int, Organisation> */
    public function all(User $user): Collection
    {
        $ids = RoleAssignment::query()->where('user_id', $user->id)->whereIn('role', ['employer_admin', 'recruiter'])
            ->where('scope_type', 'organisation')->pluck('scope_id');

        return Organisation::query()->whereIn('id', $ids)->where('type', 'employer')->orderBy('name')->get();
    }

    public function resolve(Request $request, User $user): ?Organisation
    {
        $employers = $this->all($user);
        $wanted = $request->query('employer') ?? $request->session()->get(self::SESSION);
        $employer = $employers->firstWhere('id', $wanted) ?? $employers->first();

        if ($employer !== null) {
            $request->session()->put(self::SESSION, $employer->id);
        }

        return $employer;
    }

    public function isAdmin(User $user, Organisation $employer): bool
    {
        return RoleAssignment::query()->where('user_id', $user->id)->where('role', 'employer_admin')
            ->where('scope_type', 'organisation')->where('scope_id', $employer->id)->exists();
    }
}
