<?php

declare(strict_types=1);

namespace Modules\Core\Access;

use Illuminate\Support\Facades\Cache;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\Hub;
use Modules\Core\Structure\Models\HubModule;
use Modules\Core\Structure\Models\Municipality;
use Modules\Core\Structure\Models\RoleAssignment;

/**
 * Answers "what may this person do, and where?" from their role assignments.
 *
 * - Access level per portal = the highest level any of their roles grants.
 * - Hub-scoped roles only count for a portal if that hub has the portal switched on.
 * - Results are cached per person; the key includes their access version (bumped on
 *   role changes) and the entitlements version (bumped on hub package changes).
 */
final readonly class AccessResolver
{
    public function __construct(private RoleRegistry $roles) {}

    public function level(User $user, string $module): ?AccessLevel
    {
        $level = $this->resolved($user)['levels'][$module] ?? null;

        return $level === null ? null : AccessLevel::from($level);
    }

    public function can(User $user, string $module, AccessLevel $minimum = AccessLevel::View): bool
    {
        return $this->level($user, $module)?->atLeast($minimum) ?? false;
    }

    /**
     * Portal => level name, for menus and the interface.
     *
     * @return array<string, string>
     */
    public function levels(User $user): array
    {
        return array_map(static fn (int $level): string => AccessLevel::from($level)->label(), $this->resolved($user)['levels']);
    }

    /**
     * Hubs whose data this person may see in a portal: '*' for national access,
     * otherwise a list of hub ids (empty when none).
     *
     * @return '*'|list<string>
     */
    public function visibleHubIds(User $user, string $module): string|array
    {
        $scopes = $this->resolved($user)['hubs'][$module] ?? [];

        return in_array('*', $scopes, true) ? '*' : array_values(array_unique($scopes));
    }

    /** @return list<string> Role keys the person holds (any scope). */
    public function roleKeys(User $user): array
    {
        return array_values(array_unique(array_column($this->resolved($user)['assignments'], 'role')));
    }

    public function forget(User $user): void
    {
        Cache::forget($this->cacheKey($user));
    }

    /**
     * @return array{levels: array<string, int>, hubs: array<string, list<string>>, assignments: list<array{role: string, scope_type: string, scope_id: string|null}>}
     */
    private function resolved(User $user): array
    {
        /** @var array{levels: array<string, int>, hubs: array<string, list<string>>, assignments: list<array{role: string, scope_type: string, scope_id: string|null}>} */
        return Cache::remember($this->cacheKey($user), now()->addMinutes(30), fn (): array => $this->compute($user));
    }

    /**
     * @return array{levels: array<string, int>, hubs: array<string, list<string>>, assignments: list<array{role: string, scope_type: string, scope_id: string|null}>}
     */
    private function compute(User $user): array
    {
        $levels = [];
        $hubs = [];
        $assignments = [];

        $rows = RoleAssignment::query()->where('user_id', $user->id)->active()->get(['role', 'scope_type', 'scope_id']);

        foreach ($rows as $row) {
            $role = $this->roles->find($row->role);
            if ($role === null) {
                continue; // role from a disabled module
            }

            $assignments[] = ['role' => $row->role, 'scope_type' => $row->scope_type, 'scope_id' => $row->scope_id];
            $scopeHubs = $this->hubsInScope($row->scope_type, $row->scope_id);

            foreach ($role->access as $module => $level) {
                if ($row->scope_type === 'hub' && HubEntitlements::isHubDelivered($module)
                    && ! HubModule::query()->where('hub_id', $row->scope_id)->where('module', $module)->where('enabled', true)->exists()) {
                    continue; // the hub's package does not include this portal
                }

                $levels[$module] = max($levels[$module] ?? 0, $level->value);
                $hubs[$module] = [...($hubs[$module] ?? []), ...$scopeHubs];
            }
        }

        return ['levels' => $levels, 'hubs' => $hubs, 'assignments' => $assignments];
    }

    /** @return list<string> */
    private function hubsInScope(string $type, ?string $id): array
    {
        /** @var list<string> */
        return match ($type) {
            'national' => ['*'],
            'hub' => $id !== null ? [$id] : [],
            'municipality' => Hub::query()->where('municipality_id', $id)->pluck('id')->all(),
            'province' => Hub::query()->whereIn('municipality_id', Municipality::query()->where('province_id', $id)->select('id'))->pluck('id')->all(),
            default => [],
        };
    }

    private function cacheKey(User $user): string
    {
        return sprintf('kasi:access:%s:%d:%d', $user->id, $user->access_version, (int) Cache::get(HubEntitlements::VERSION_KEY, 0));
    }
}
