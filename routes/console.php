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
