<?php

declare(strict_types=1);

namespace App\Http\Middleware;

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
        return [
            ...parent::share($request),
            'platform' => [
                'brand' => config('kasi.brand.name'),
                'fullName' => config('kasi.brand.full_name'),
                'tagline' => config('kasi.brand.tagline'),
                'version' => config('kasi.version.number'),
                'demo' => config('kasi.demo.enabled') === true,
                'locale' => app()->getLocale(),
            ],
        ];
    }
}
