<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use Illuminate\Console\Command;
use Modules\Core\Ai\Models\AiRequestLog;

/** Daily: clear AI inputs/outputs older than the retention period (costs and outcomes stay). */
final class AiPurgeCommand extends Command
{
    protected $signature = 'kasi:ai:purge';

    protected $description = 'Remove AI inputs and outputs older than the retention period';

    public function handle(): int
    {
        $count = AiRequestLog::query()->where('created_at', '<', now()->subDays((int) config('kasi.ai.retention_days')))
            ->where(fn ($q) => $q->whereNotNull('input')->orWhereNotNull('output'))
            ->update(['input' => null, 'output' => null]);
        $this->info("AI records cleared: {$count}");

        return self::SUCCESS;
    }
}
