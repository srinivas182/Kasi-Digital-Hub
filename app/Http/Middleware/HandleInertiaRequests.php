<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Locale\Languages;
use App\Support\Navigation\NavigationBuilder;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Modules\Core\Access\AccessResolver;
use Modules\Core\Identity\Models\User;

/**
 * Shares platform-wide props with every Inertia page.
 */
final class HandleInertiaRequests extends Middleware
{
    /**
     * The root template loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $locale = app()->getLocale();

        return [
            ...parent::share($request),
            'platform' => [
                'brand' => config('kasi.brand.name'),
                'fullName' => config('kasi.brand.full_name'),
                'tagline' => config('kasi.brand.tagline'),
                'owner' => config('kasi.brand.owner'),
                'version' => config('kasi.version.number'),
                'demo' => config('kasi.demo.enabled') === true,
            ],
            'i18n' => fn (): array => [
                'locale' => $locale,
                'strings' => Languages::strings($locale),
                'languages' => array_map(
                    static fn (string $code, array $language): array => ['code' => $code, 'name' => $language['native'], 'draft' => $language['draft']],
                    array_keys(Languages::available()),
                    Languages::available(),
                ),
            ],
            'navigation' => fn (): array => ['portals' => app(NavigationBuilder::class)->portals($this->currentUser($request))],
            'auth' => fn (): array => ['user' => $this->userSummary($request)],
            'flash' => fn (): array => [
                'status' => $request->hasSession() ? $request->session()->get('status') : null,
            ],
        ];
    }

    /**
     * Minimal signed-in user details for the interface. Never include PINs, codes or full ID data.
     *
     * @return array{id: string, name: string, displayName: string, ageBand: string, staff: bool, homeHub: string|null, access: array<string, string>}|null
     */
    private function userSummary(Request $request): ?array
    {
        $user = $this->currentUser($request);

        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->fullName(),
            'displayName' => $user->displayName(),
            'ageBand' => $user->age_band,
            'staff' => $user->two_factor_required,
            'homeHub' => $user->homeHub?->name,
            'access' => app(AccessResolver::class)->levels($user),
        ];
    }

    private function currentUser(Request $request): ?User
    {
        // Errors such as "page not found" render before the session starts.
        $user = $request->hasSession() ? $request->user() : null;

        return $user instanceof User ? $user : null;
    }
}
