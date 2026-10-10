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
        'search' => env('KASI_SEARCH_DRIVER', 'database'),
        'pdf' => env('KASI_PDF_DRIVER', 'fake'),
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
            'proof_of_address', 'bank_confirmation', 'tax_clearance', 'learning_evidence',
            'tax_registration', 'bbbee_affidavit', 'trading_permit', 'uif_registration', 'health_certificate', 'other',
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

    /*
    | AI layer (S8, ADR-016). Features ask for a tier, not a model, so models change here.
    | Prices are estimates in South African cents per million tokens - update them from the
    | provider's current price list. Budgets are placeholders until Ku Tirhisana sets them.
    */
    'ai' => [
        'tiers' => [
            'anthropic' => ['fast' => env('KASI_AI_ANTHROPIC_FAST', 'claude-haiku-5-5'), 'strong' => env('KASI_AI_ANTHROPIC_STRONG', 'claude-sonnet-5-5')],
            'openai' => ['fast' => env('KASI_AI_OPENAI_FAST', 'gpt-4o-mini'), 'strong' => env('KASI_AI_OPENAI_STRONG', 'gpt-4o')],
            'fake' => ['fast' => 'fake-fast', 'strong' => 'fake-strong'],
        ],
        'keys' => ['anthropic' => env('ANTHROPIC_API_KEY'), 'openai' => env('OPENAI_API_KEY')],
        'timeout_seconds' => 25,
        'price_cents_per_million' => [
            'fast' => ['input' => (int) env('KASI_AI_FAST_INPUT_CENTS', 1800), 'output' => (int) env('KASI_AI_FAST_OUTPUT_CENTS', 9000)],
            'strong' => ['input' => (int) env('KASI_AI_STRONG_INPUT_CENTS', 5400), 'output' => (int) env('KASI_AI_STRONG_OUTPUT_CENTS', 27000)],
        ],
        'budgets' => [
            'per_person_day_calls' => (int) env('KASI_AI_PER_PERSON_DAY', 20),
            'per_hub_month_cents' => (int) env('KASI_AI_PER_HUB_MONTH_CENTS', 50000),
            'platform_month_cents' => (int) env('KASI_AI_MONTHLY_CAP_CENTS', 200000), // placeholder: R2 000 a month
        ],
        // Inputs and outputs are kept this long for quality review, then deleted (metadata stays).
        'retention_days' => 30,
    ],

    /*
    | Voice notes (S9). Each language is switched on only after testing with real recordings
    | from the hubs. Audio is never stored.
    */
    'speech' => [
        'driver' => env('KASI_SPEECH_DRIVER', 'fake'),
        'languages' => array_values(array_filter(explode(',', (string) env('KASI_SPEECH_LANGUAGES', 'en')))),
        'max_seconds' => 120,
        'per_person_day' => (int) env('KASI_SPEECH_PER_PERSON_DAY', 10),
        'cost_cents_per_minute' => (int) env('KASI_SPEECH_CENTS_PER_MINUTE', 12), // estimate - update from the price list
        'openai_model' => env('KASI_SPEECH_OPENAI_MODEL', 'whisper-1'),
    ],

    /*
    | KasiLearn (S13): media conversion driver (fake in CI, ffmpeg in the media worker).
    */
    'learn' => [
        'media_driver' => env('KASI_MEDIA_DRIVER', 'fake'),
        'max_media_seconds' => 15 * 60,
        'max_upload_kb' => 500 * 1024,
    ],

    /*
    | Embeddings (S11): only skill names and job titles are embedded, never personal details.
    */
    'embeddings' => [
        'driver' => env('KASI_EMBEDDINGS_DRIVER', 'fake'),
        'openai_model' => env('KASI_EMBEDDINGS_OPENAI_MODEL', 'text-embedding-3-small'),
    ],

    'search' => [
        'meilisearch' => ['url' => env('MEILISEARCH_HOST', 'http://meilisearch:7700'), 'key' => env('MEILISEARCH_KEY'), 'index' => env('KASI_SEARCH_INDEX', 'kasi_content')],
    ],

    'pdf' => [
        'gotenberg_url' => env('GOTENBERG_URL', 'http://gotenberg:3000'),
        'timeout_seconds' => 30,
    ],

    /*
    | Verification checklists ticked by the KasiHub team before an organisation is verified (S10).
    */
    'verification' => [
        'checklists' => [
            'employer' => ['cipc_found', 'cipc_active', 'person_linked', 'phone_answered'],
            'employer_community' => ['id_verified', 'address_verified', 'hub_visit', 'phone_answered'],
            'training_provider' => ['cipc_found', 'cipc_active', 'person_linked', 'phone_answered'],
        ],
    ],

    /*
    | KasiWork listings (S10). The minimum wage must be checked against the current gazetted rate
    | every year (the default is the rate from 1 March 2025: R28.79 an hour).
    */
    'work' => [
        'minimum_wage_cents_per_hour' => (int) env('KASI_MINIMUM_WAGE_CENTS', 2879),
        'hours_per_period' => ['hour' => 1, 'day' => 8, 'week' => 40, 'month' => 173],
        'free_listing_limit' => 3,
        'community_listing_limit' => 3,
        'max_listing_days' => 60,
    ],

    'hub_ops' => [
        // POPIA: visits older than this keep only totals (kasi:hub-ops:anonymise-visits, monthly).
        'visit_retention_months' => 24,
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
