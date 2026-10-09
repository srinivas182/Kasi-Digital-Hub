<?php

declare(strict_types=1);

namespace Modules\HubOps\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Modules\Core\Access\AccessResolver;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\Hub;

/**
 * Which hub a staff member is working on. Staff of one hub always get that hub; coordinators
 * and national staff pick one (remembered in the session).
 */
final readonly class CurrentHub
{
    private const SESSION = 'hubops.hub';

    public function __construct(private AccessResolver $access) {}

    /** @return Collection<int, Hub> */
    public function hubs(User $user): Collection
    {
        $visible = $this->access->visibleHubIds($user, 'HubOps');

        return Hub::query()
            ->when($visible !== '*', fn ($q) => $q->whereIn('id', (array) $visible))
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'code', 'status', 'municipality_id', 'opening_hours', 'phone', 'email', 'description', 'trusted_ips']);
    }

    public function resolve(Request $request, User $user): Hub
    {
        $hubs = $this->hubs($user);
        abort_if($hubs->isEmpty(), 403);

        $wanted = $request->query('hub') ?? $request->session()->get(self::SESSION);
        $hub = $hubs->firstWhere('id', $wanted) ?? $hubs->first();
        $request->session()->put(self::SESSION, $hub->id);

        return Hub::query()->findOrFail($hub->id);
    }

    /** @return list<array{id: string, name: string}> */
    public function options(User $user): array
    {
        return $this->hubs($user)->map(static fn (Hub $h): array => ['id' => $h->id, 'name' => $h->name])->values()->all(); // @phpstan-ignore return.type
    }
}
