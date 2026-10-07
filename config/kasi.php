<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Kasi Digital Hub platform configuration
|--------------------------------------------------------------------------
|
| Branding, drivers and platform-wide defaults. Every external service sits
| behind a driver so demos and CI never call paid providers (ADR-004).
| Rebranding the platform is a configuration change, not a code change.
|
*/

return [

    'brand' => [
        'name' => env('KASI_BRAND_NAME', 'KasiHub'),
        'full_name' => env('KASI_BRAND_FULL_NAME', 'Kasi Digital Hub'),
        'tagline' => env('KASI_BRAND_TAGLINE', 'Jobs, skills and enterprise - one platform for every hub'),
        'owner' => env('KASI_BRAND_OWNER', 'Ku Tirhisana Consultancy (Pty) Ltd'),
    ],

    'version' => [
        'number' => trim((string) @file_get_contents(base_path('VERSION'))) ?: '0.0.0',
        'commit' => env('GIT_COMMIT', 'local'),
        'built_at' => env('BUILD_TIME'),
    ],

    'modules' => [
        // Module names to switch off platform-wide, e.g. ['Connect'].
        'disabled' => array_values(array_filter(explode(',', (string) env('KASI_MODULES_DISABLED', '')))),
    ],

    'drivers' => [
        'ai' => env('KASI_AI_DRIVER', 'fake'),
        'sms' => env('KASI_SMS_DRIVER', 'log'),
        'whatsapp' => env('KASI_WHATSAPP_DRIVER', 'log'),
        'payments' => env('KASI_PAYMENTS_DRIVER', 'fake'),
        'search' => env('KASI_SEARCH_DRIVER', 'meilisearch'),
    ],

    'demo' => [
        // Demo mode enables the demo banner, role switcher and demo reset command.
        // It must never be enabled in production.
        'enabled' => (bool) env('KASI_DEMO_MODE', false),
    ],

    'locale' => [
        'country' => 'ZA',
        'currency' => 'ZAR',
        'phone_prefix' => '+27',
        'timezone' => 'Africa/Johannesburg',
        // Interface languages planned for the platform (translations arrive from S1; full coverage in S23).
        'languages' => ['en', 'zu', 'xh', 'af', 'nso', 'tn', 'st', 'ts', 've', 'ss', 'nr'],
    ],

];
