<?php

declare(strict_types=1);

namespace Modules\Admin\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\Municipality;

/**
 * Search and filter people for the admin console. Indexed columns only; server-side paging.
 */
final class PeopleDirectory
{
    /**
     * @param  array{q?: string|null, hub?: string|null, province?: string|null, role?: string|null, status?: string|null}  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function search(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $query = User::query()->with('homeHub:id,name');
        $q = trim((string) ($filters['q'] ?? ''));

        if ($q !== '') {
            $digits = preg_replace('/\D/', '', $q) ?? '';

            $query->where(function (Builder $where) use ($q, $digits): void {
                if (strlen($digits) >= 4) {
                    // Phone search by trailing digits (people often know only the last few).
                    $where->where('phone', 'like', '%'.$digits);
                }
                foreach (preg_split('/\s+/', $q) ?: [] as $word) {
                    if ($word !== '' && ! ctype_digit($word)) {
                        $where->orWhere('first_name', 'like', $word.'%')->orWhere('last_name', 'like', $word.'%')->orWhere('preferred_name', 'like', $word.'%');
                    }
                }
            });
        }

        if (! empty($filters['hub'])) {
            $query->where('home_hub_id', $filters['hub']);
        }

        if (! empty($filters['province'])) {
            $query->whereIn('municipality_id', Municipality::query()->where('province_id', $filters['province'])->select('id'));
        }

        if (! empty($filters['role'])) {
            $query->whereHas('roleAssignments', fn (Builder $r) => $r->where('role', $filters['role']));
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage)->withQueryString();
    }
}
