<?php

declare(strict_types=1);

namespace Modules\Commercial\Console;

use Illuminate\Console\Command;
use Modules\Commercial\Services\Invoicing;
use Modules\Commercial\Services\Settlement;

/**
 * Daily: reconcile yesterday with the provider and chase package invoices. On the 1st: last month's
 * payouts and this month's package invoices.
 */
final class CommercialDailyCommand extends Command
{
    protected $signature = 'kasi:commercial:daily';

    protected $description = 'Reconciliation, invoice reminders, monthly package invoices and payouts';

    public function handle(Invoicing $invoicing, Settlement $settlement): int
    {
        $this->line('Reconciliation issues: '.$settlement->reconcile(now()->subDay()->toDateString()));
        foreach ($invoicing->chase() as $what => $count) {
            $this->line("{$what}: {$count}");
        }
        if (now('Africa/Johannesburg')->day === 1) {
            $this->line('Payouts planned: '.$settlement->payouts(now()->subMonth()->format('Y-m')));
            $this->line('Package invoices: '.$invoicing->packages(now()->format('Y-m')));
        }

        return self::SUCCESS;
    }
}
