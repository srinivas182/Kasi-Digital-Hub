<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Locale\Languages;
use App\Support\Navigation\NavigationBuilder;
use Illuminate\Http\Request;
use Inertia\Middleware;

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
            'navigation' => fn (): array => ['portals' => app(NavigationBuilder::class)->portals()],
        ];
    }
}
