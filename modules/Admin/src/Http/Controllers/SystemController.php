<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

/** System status for operations (ADR-029): queues, failed jobs, scheduled-job heartbeats, services, storage. */
final class SystemController
{
    /** Scheduled job => hours after which a missing heartbeat is overdue. */
    public const JOBS = [
        'notifications-release' => 2, 'documents-expiry' => 26, 'prune' => 26, 'event-reminders' => 2, 'visit-retention' => 24 * 32, 'ai-purge' => 26,
        'search-reindex' => 24 * 8, 'work-listings' => 26, 'work-matches' => 26, 'work-hiring' => 26, 'learn-daily' => 26, 'partner-daily' => 26,
        'connect-daily' => 2, 'commercial-daily' => 26, 'funder-freeze' => 24 * 32, 'admin-hourly' => 2, 'market-hourly' => 2,
    ];

    public function index(): Response
    {
        $jobs = [];
        foreach (self::JOBS as $name => $hours) {
            $last = Cache::get("kasi:heartbeat:{$name}");
            $jobs[] = ['name' => $name, 'last' => $last, 'overdue' => $last === null || CarbonImmutable::parse((string) $last)->diffInHours(now()) > $hours]; // Carbon 3 diffs are signed: measure past -> now
        }

        return Inertia::render('Admin/System', [
            'queues' => Schema::hasTable('jobs') ? DB::table('jobs')->groupBy('queue')->selectRaw('queue, count(*) as n, min(created_at) as oldest')->get() : [],
            'failed' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->orderByDesc('failed_at')->limit(20)->get(['id', 'queue', 'failed_at'])
                ->map(static fn (object $f): array => [...(array) $f, 'job' => '']) : [],
            'jobs' => $jobs,
            'services' => [
                'ai' => config('kasi.drivers.ai'), 'search' => config('kasi.drivers.search'), 'pdf' => config('kasi.drivers.pdf'), 'media' => config('kasi.learn.media_driver'),
                'sms' => config('kasi.drivers.sms', config('services.sms.driver', 'log')), 'whatsapp' => config('kasi.drivers.whatsapp', 'log'),
                'payments' => config('kasi.commercial.payments_driver'), 'mail' => config('mail.default'),
            ],
            'storage' => ['documents' => self::size(storage_path('app/private')), 'learn' => self::size(storage_path('app/learn')), 'logs' => self::size(storage_path('logs'))],
            'backup' => Cache::get('kasi:backup:last'),
            'demo' => (bool) config('kasi.demo.enabled'),
        ]);
    }

    public function retry(string $id): RedirectResponse
    {
        Artisan::call('queue:retry', ['id' => [$id]]);

        return back()->with('status', __('admin.system.retried'));
    }

    private static function size(string $dir): int
    {
        if (! is_dir($dir)) {
            return 0;
        }
        $bytes = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $bytes += $file->getSize();
        }

        return $bytes;
    }
}
