<?php

declare(strict_types=1);

namespace Modules\HubOps\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Core\Access\AccessResolver;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\Hub;
use Modules\HubOps\Services\CurrentHub;

/**
 * Shared helpers for hub staff screens.
 */
abstract class StaffController
{
    public function __construct(protected readonly CurrentHub $currentHub) {}

    protected function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    protected function hub(Request $request): Hub
    {
        return $this->currentHub->resolve($request, $this->actor($request));
    }

    protected function can(Request $request, string $permission): bool
    {
        return app(AccessResolver::class)->hasPermission($this->actor($request), $permission);
    }

    /**
     * The hub selector shown on every staff screen.
     *
     * @return array{current: array{id: string, name: string}, options: list<array{id: string, name: string}>}
     */
    protected function hubProps(Request $request, Hub $hub): array
    {
        return ['current' => ['id' => $hub->id, 'name' => $hub->name], 'options' => $this->currentHub->options($this->actor($request))];
    }

    /** Staff may only act on hubs they can see. */
    protected function guardHub(Request $request, string $hubId): void
    {
        abort_unless($this->currentHub->hubs($this->actor($request))->contains('id', $hubId), 404);
    }
}
