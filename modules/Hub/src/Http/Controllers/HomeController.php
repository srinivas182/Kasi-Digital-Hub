<?php

declare(strict_types=1);

namespace Modules\Hub\Http\Controllers;

use App\Support\Navigation\NavigationBuilder;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Home\HomeRegistry;
use Modules\Core\Home\HubActivity;
use Modules\Core\Home\NextStep;
use Modules\Core\Identity\Models\User;
use Modules\Core\Platform\Models\Update;
use Modules\Core\Structure\Models\Hub;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Personal hub home: home hub card, next steps (from every portal), service tiles and latest updates.
 */
final class HomeController
{
    public function __invoke(Request $request, HomeRegistry $home, NavigationBuilder $navigation, Router $router, HubActivity $activity): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $hub = $user->home_hub_id !== null ? Hub::query()->with('place')->find($user->home_hub_id) : null;
        $steps = $home->nextSteps($user);

        return Inertia::render('Hub/Home', [
            'welcome' => (bool) $request->session()->get('welcome', false),
            'hub' => $hub === null ? null : [
                'name' => $hub->name,
                'slug' => $hub->slug,
                'address' => $hub->address,
                'phone' => $hub->phone,
                'openingHours' => $hub->opening_hours ?? [],
                'latitude' => $hub->latitude !== null ? (float) $hub->latitude : null,
                'longitude' => $hub->longitude !== null ? (float) $hub->longitude : null,
            ],
            'steps' => array_map(static fn (NextStep $step): array => $step->toArray(), $steps),
            // Events and classes the person signed up for (from KasiHub Ops and later portals).
            'agenda' => array_slice($activity->personalItems($user), 0, 3),
            'services' => $this->ordered(array_values(array_map(fn (array $portal): array => [
                'module' => $portal['module'],
                'title' => $portal['title'],
                'icon' => $portal['icon'],
                'href' => $portal['href'],
                'group' => $portal['group'],
                'available' => $portal['href'] !== null && $this->routeExists($router, $portal['href']),
            ], array_filter($navigation->portals($user), static fn (array $portal): bool => $portal['module'] !== 'Hub')))),
            'updates' => Update::query()->where('user_id', $user->id)->latest('created_at')->latest('id')->limit(5)->get()
                ->map(static fn (Update $u): array => ['id' => $u->id, 'title' => $u->title, 'module' => $u->module, 'read' => $u->read_at !== null, 'createdAt' => $u->created_at->toIso8601String()]),
        ]);
    }

    /** Citizen services first, in the order people meet them; consoles after. */
    private const ORDER = ['Work', 'Learn', 'Start', 'Connect', 'Market', 'Biz', 'Brand'];

    /**
     * @template T of array{module: string}
     *
     * @param  list<T>  $services
     * @return list<T>
     */
    private function ordered(array $services): array
    {
        usort($services, static function (array $a, array $b): int {
            $rank = static fn (string $m): int => ($i = array_search($m, self::ORDER, true)) === false ? 100 : $i;

            return $rank($a['module']) <=> $rank($b['module']);
        });

        return $services;
    }

    /** A portal tile is live once the portal's start page exists (portals arrive sprint by sprint). */
    private function routeExists(Router $router, string $href): bool
    {
        try {
            $router->getRoutes()->match(Request::create($href));

            return true;
        } catch (HttpException) {
            return false;
        }
    }
}
