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
        // Where website enquiries are sent (placeholder until Ku Tirhisana confirms).
        'contact_email' => env('KASI_CONTACT_EMAIL', 'hello@kasidigitalhub.co.za'),
        'url' => env('APP_URL', 'https://kasidigitalhub.co.za'),
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
        'whatsapp_fail_numbers' => [], // test hook: numbers the log driver treats as undeliverable
        'payments' => env('KASI_PAYMENTS_DRIVER', 'fake'),
        'search' => env('KASI_SEARCH_DRIVER', 'meilisearch'),
        'bot_check' => env('KASI_BOT_CHECK_DRIVER', 'fake'),
        'virus_scan' => env('KASI_VIRUS_SCAN_DRIVER', 'fake'),
        'clamav_socket' => env('KASI_CLAMAV_SOCKET', '/var/run/clamav/clamd.ctl'),
    ],

    /*
    | Identity (Sprint 2): phone + PIN sign-in, one-time codes and abuse protection.
    */
    'identity' => [
        'otp' => [
            'length' => 6,
            'ttl_minutes' => 5,
            'max_attempts' => 5,
            // Per phone number: 3 codes per 15 minutes and 10 per day.
            'per_phone_15_min' => (int) env('KASI_OTP_PER_PHONE_15_MIN', 3),
            'per_phone_day' => (int) env('KASI_OTP_PER_PHONE_DAY', 10),
            'per_ip_hour' => (int) env('KASI_OTP_PER_IP_HOUR', 60),
            'per_device_hour' => (int) env('KASI_OTP_PER_DEVICE_HOUR', 20),
            // Hubs share one internet connection, so many people sign in from the same IP address.
            // Hub IP addresses / ranges (comma separated, CIDR allowed) get a much higher per-IP limit.
            'trusted_ips' => array_values(array_filter(array_map('trim', explode(',', (string) env('KASI_OTP_TRUSTED_IPS', ''))))),
            'per_trusted_ip_hour' => (int) env('KASI_OTP_PER_TRUSTED_IP_HOUR', 1000),
            // Platform-wide daily SMS cap - a cost guard against SMS pumping fraud.
            'daily_cap' => (int) env('KASI_SMS_DAILY_CAP', 20000),
            // Block a number range (first 6 digits) after this many codes in an hour.
            'per_range_hour' => (int) env('KASI_OTP_PER_RANGE_HOUR', 200),
        ],
        'pin' => [
            'length' => 5,
            'max_attempts' => 5,
            'lockout_minutes' => 15,
        ],
        'remember_device_days' => 30,
        // Raised only for automated browser tests that run many sign-ins from one IP.
        'throttle_multiplier' => (int) env('KASI_THROTTLE_MULTIPLIER', 1),
        'idle_minutes' => [
            'citizen' => 120,
            'staff' => 30,
        ],
        'audit_retention_days' => (int) env('KASI_AUDIT_RETENTION_DAYS', 1095),
    ],

    /*
    | Age policy. Under minor_min_age: sign-up declined. minor_min_age to full_access_age - 1:
    | guardian consent required and access limited to minor_modules. Configurable per programme.
    */
    'age' => [
        'full_access_age' => 18,
        'minor_min_age' => 16,
        'minor_modules' => ['Learn'],
    ],

    /*
    | Consent purposes (POPIA). 'platform' is required and covers the terms of use and
    | privacy notice; all others are optional and off until the person switches them on.
    */
    'consent' => [
        'purposes' => [
            'platform' => ['required' => true, 'documents' => ['terms', 'privacy']],
            'job_matching' => ['required' => false],
            'learning_records' => ['required' => false],
            'partner_sharing' => ['required' => false],
            'whatsapp_updates' => ['required' => false],
            'marketing' => ['required' => false],
        ],
    ],

    /*
    | Hub packages (Sprint 3). A package switches on the portals a hub may deliver locally
    | (assisted onboarding, cohorts, local events). National services - the job pool, course
    | catalogue and mentors - stay open to everyone online. Prices are set in the Commercial
    | console (S19); add-ons can switch on single portals for one hub.
    */
    'hubs' => [
        'delivered_modules' => ['HubOps', 'Work', 'Learn', 'Start', 'Connect'],
        'packages' => [
            'base' => ['HubOps', 'Work'],
            'growth' => ['HubOps', 'Work', 'Learn'],
            'enterprise' => ['HubOps', 'Work', 'Learn', 'Start'],
            'full' => ['HubOps', 'Work', 'Learn', 'Start', 'Connect'],
        ],
    ],

    /*
    | Document vault (Sprint 4). Files live on a private disk and are only reachable
    | through short-lived signed links checked against permissions.
    */
    'documents' => [
        'disk' => env('KASI_DOCUMENTS_DISK', 'documents'),
        'max_kb' => 10240,
        'mimes' => ['pdf', 'jpg', 'jpeg', 'png'],
        'image_max_px' => 2000,
        'link_minutes' => 5,
        'types' => [
            'id_document', 'matric_certificate', 'qualification', 'cipc_certificate',
            'proof_of_address', 'bank_confirmation', 'tax_clearance', 'other',
        ],
        'expiry_reminder_days' => 30,
    ],

    /*
    | Notifications (Sprint 4). Security messages ignore preferences and quiet hours.
    | Costs are estimates in ZAR cents for the delivery log and cost dashboard (S21).
    */
    'notifications' => [
        'categories' => ['security', 'account', 'jobs', 'learning', 'business', 'mentoring', 'hub_news', 'marketing'],
        'channels' => ['in_app', 'whatsapp', 'sms', 'email'],
        'quiet_hours' => ['start' => 20, 'end' => 7],
        'max_messages_per_day' => (int) env('KASI_MAX_MESSAGES_PER_DAY', 6),
        'dedupe_hours' => 24,
        'cost_cents' => ['whatsapp' => 35, 'sms' => 30, 'email' => 0, 'in_app' => 0],
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
        // Times are stored in UTC and displayed in SAST.
        'timezone' => 'Africa/Johannesburg',

        /*
         * Interface languages. 'draft' languages have machine-drafted sample strings
         * awaiting professional review; they are hidden in production unless
         * KASI_SHOW_DRAFT_LANGUAGES=true. Full translations are planned for S23.
         */
        'languages' => [
            'en' => ['name' => 'English', 'native' => 'English', 'draft' => false],
            'zu' => ['name' => 'isiZulu', 'native' => 'isiZulu', 'draft' => true],
            'ts' => ['name' => 'Xitsonga', 'native' => 'Xitsonga', 'draft' => true],
        ],
        'show_drafts' => env('KASI_SHOW_DRAFT_LANGUAGES'),
    ],

    // Internal component catalogue at /ui-kit (never in production).
    'ui_kit' => [
        'enabled' => env('KASI_UI_KIT'),
    ],

];
