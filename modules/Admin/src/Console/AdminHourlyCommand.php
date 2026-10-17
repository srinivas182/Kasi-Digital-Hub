<?php

declare(strict_types=1);

namespace Modules\Admin\Console;

use Illuminate\Console\Command;
use Modules\Admin\Support\AiAlerts;

/** Hourly: AI budget alerts (80% / 100%). */
final class AdminHourlyCommand extends Command
{
    protected $signature = 'kasi:admin:hourly';

    protected $description = 'AI budget alerts';

    public function handle(AiAlerts $alerts): int
    {
        $this->info('AI budget alerts sent: '.$alerts->check());

        return self::SUCCESS;
    }
}
