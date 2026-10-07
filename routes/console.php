<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;
use Modules\Core\Identity\Models\AuditLog;
use Modules\Core\Identity\Models\OtpChallenge;

// Retention: expired one-time codes daily; audit logs after the retention period (config/kasi.php).
Schedule::command('model:prune', ['--model' => [OtpChallenge::class, AuditLog::class]])->dailyAt('02:30');
