<?php

declare(strict_types=1);

namespace Modules\Biz\Http\Concerns;

use Illuminate\Http\Request;
use Modules\Core\Assist\AssistedSession;
use Modules\Core\Enterprise\BusinessFacts;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\HubModule;

/**
 * Whose books these are (shared by every KasiBiz controller): the signed-in person or, in a hub assisted session, the
 * person being helped (the facilitator is the actor); and their business, only where the hub has KasiBiz.
 */
trait ResolvesBusiness
{
    /** @return array{0: User, 1: User|null} [person, facilitator or null] */
    private function who(Request $request): array
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $helped = app(AssistedSession::class)->person($user);

        return [$helped ?? $user, $helped !== null ? $user : null];
    }

    /** @return ($required is true ? array{id: string, name: string} : array{id: string, name: string}|null) */
    private function business(Request $request, User $person, bool $required = true): ?array
    {
        $mine = app(BusinessFacts::class)->businessesOf($person);
        $wanted = (string) $request->input('business', '');
        $business = collect($mine)->firstWhere('id', $wanted) ?? ($mine[0] ?? null);
        $available = $business !== null && $this->hubHasBiz($business['id'], $person);
        abort_if($required && ! $available, 404);

        return $available ? ['id' => (string) $business['id'], 'name' => (string) $business['name']] : null;
    }

    private function hubHasBiz(string $businessId, User $person): bool
    {
        $hubId = app(BusinessFacts::class)->facts($businessId)['hub_id'] ?? $person->home_hub_id;

        return $hubId !== null && HubModule::query()->where('hub_id', $hubId)->where('module', 'Biz')->where('enabled', true)->exists();
    }
}
