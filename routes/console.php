<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;
use Modules\Core\Identity\Models\AuditLog;
use Modules\Core\Identity\Models\OtpChallenge;

/*
| Scheduled jobs (run by `php artisan schedule:work` in the scheduler container).
| Each job records a heartbeat so a missed run can be detected (alerts in S24):
| Cache key kasi:heartbeat:<name> holds the time of the last successful run.
*/

$heartbeat = static fn (string $name): Closure => static fn () => Cache::forever("kasi:heartbeat:{$name}", now()->toIso8601String());

// Messages held during quiet hours (20:00-07:00 SAST) are released from 07:00.
Schedule::command('kasi:notifications:release')->everyFiveMinutes()->withoutOverlapping()->onSuccess($heartbeat('notifications-release'));

// Document expiry reminders (once per document, 30 days before expiry).
Schedule::command('kasi:documents:remind-expiring')->dailyAt('08:00')->timezone('Africa/Johannesburg')->onSuccess($heartbeat('documents-expiry'));

// Retention: expired one-time codes daily; audit logs after the retention period (config/kasi.php).
Schedule::command('model:prune', ['--model' => [OtpChallenge::class, AuditLog::class]])->dailyAt('02:30')->onSuccess($heartbeat('prune'));

// KasiHub Ops (S7): event reminders hourly; visit anonymisation monthly (POPIA retention).
Schedule::command('kasi:hub-ops:remind-events')->hourly()->withoutOverlapping()->onSuccess($heartbeat('event-reminders'));
Schedule::command('kasi:hub-ops:anonymise-visits')->monthlyOn(1, '03:00')->timezone('Africa/Johannesburg')->onSuccess($heartbeat('visit-retention'));

// S8: clear AI inputs/outputs after the retention period; rebuild the search index nightly.
Schedule::command('kasi:ai:purge')->dailyAt('02:45')->onSuccess($heartbeat('ai-purge'));
Schedule::command('kasi:search:reindex')->dailyAt('03:30')->timezone('Africa/Johannesburg')->withoutOverlapping()->onSuccess($heartbeat('search-reindex'));

// S10: expire job listings past their closing date; remind employers 3 days before.
Schedule::command('kasi:work:listings')->dailyAt('06:00')->timezone('Africa/Johannesburg')->onSuccess($heartbeat('work-listings'));

// S11: refresh job matches and send at most one job alert per person (after quiet hours end).
Schedule::command('kasi:work:matches')->dailyAt('07:15')->timezone('Africa/Johannesburg')->withoutOverlapping()->onSuccess($heartbeat('work-matches'));

// S12: no-ghosting outcomes, interview reminders, retention check-ins, applying-open notices, anonymisation.
Schedule::command('kasi:work:hiring')->dailyAt('09:00')->timezone('Africa/Johannesburg')->withoutOverlapping()->onSuccess($heartbeat('work-hiring'));

// S14: one-time "you can now enrol" messages for saved courses.
Schedule::command('kasi:learn:daily')->dailyAt('09:30')->timezone('Africa/Johannesburg')->withoutOverlapping()->onSuccess($heartbeat('learn-daily'));

// S17: partner referral reminders, "no response" at 30 days, follow-ups.
Schedule::command('kasi:partner:daily')->dailyAt('09:45')->timezone('Africa/Johannesburg')->withoutOverlapping()->onSuccess($heartbeat('partner-daily'));
