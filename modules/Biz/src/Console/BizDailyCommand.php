<?php

declare(strict_types=1);

namespace Modules\Biz\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Biz\Notifications\BizNotification;
use Modules\Biz\Services\Compliance;
use Modules\Biz\Services\Stock;
use Modules\Core\Enterprise\BusinessFacts;
use Modules\Core\Identity\Models\User;
use Modules\Core\Notifications\Notifier;

/** Daily (S29): refresh each business's compliance calendar, send deadline reminders, and one low-stock message. */
final class BizDailyCommand extends Command
{
    protected $signature = 'kasi:biz:daily';

    protected $description = 'KasiBiz compliance reminders and low-stock messages';

    public function handle(Compliance $compliance, Stock $stock, BusinessFacts $businesses, Notifier $notifier): int
    {
        $today = CarbonImmutable::today();
        $sent = 0;
        $ids = DB::table('biz_entries')->distinct()->pluck('business_id')->merge(DB::table('biz_stock_items')->distinct()->pluck('business_id'))
            ->merge(DB::table('biz_compliance_profiles')->pluck('business_id'))->unique();
        foreach ($ids as $businessId) {
            $businessId = (string) $businessId;
            $owners = User::query()->whereIn('id', $businesses->facts($businessId)['owner_ids'] ?? [])->get();
            if ($owners->isEmpty()) {
                continue;
            }
            $compliance->refresh($businessId);
            foreach (DB::table('biz_obligations')->where('business_id', $businessId)->where('status', 'due')->get() as $o) {
                $due = CarbonImmutable::parse((string) $o->due_on);
                $kind = match (true) {
                    $due->lt($today) && ($o->reminded_overdue === null || CarbonImmutable::parse((string) $o->reminded_overdue)->lte($today->subDays(7))) => 'overdue',
                    $due->gte($today) && $due->lte($today->addDays(3)) && $o->reminded_3 === null => 'soon_3',
                    $due->gt($today->addDays(3)) && $due->lte($today->addDays(14)) && $o->reminded_14 === null => 'soon_14',
                    default => null,
                };
                if ($kind === null) {
                    continue;
                }
                DB::table('biz_obligations')->where('id', $o->id)->update([['overdue' => 'reminded_overdue', 'soon_3' => 'reminded_3', 'soon_14' => 'reminded_14'][$kind] => $today->toDateString()]);
                foreach ($owners as $owner) {
                    $notifier->send($owner, new BizNotification('deadline_'.$kind, ['what' => __('biz.compliance.rule.'.$o->rule), 'date' => $due->format('j M Y')], '/biz/compliance'));
                    $sent++;
                }
            }
            $low = $stock->takeNewLow($businessId);
            if ($low !== []) {
                foreach ($owners as $owner) {
                    $notifier->send($owner, new BizNotification('low_stock', ['items' => implode(', ', array_slice($low, 0, 5)), 'count' => (string) count($low)], '/biz/stock'));
                    $sent++;
                }
            }
        }
        $this->info("Messages: {$sent}");

        return self::SUCCESS;
    }
}
