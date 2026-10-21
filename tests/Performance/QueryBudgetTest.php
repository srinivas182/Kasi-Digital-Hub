<?php

declare(strict_types=1);

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\DB;
use Modules\Core\Identity\Models\User;
use Modules\HubOps\Tests\Staff;

/*
| S22 query budgets: with the full demo data, key pages stay within a fixed number of database queries.
| Budgets are the measured counts plus a little headroom - a page that starts querying per row fails here.
| If a feature genuinely needs more, raise its budget on purpose (with a reason in the commit).
*/
it('keeps key pages within their query budgets', function (): void {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DemoSeeder::class);
    $budgets = [
        ['+27720000001', '/home', 32], ['+27720000001', '/work/jobs', 14], ['+27720000001', '/work/matches', 14], ['+27720000001', '/work/applications', 12],
        ['+27720000002', '/learn/courses', 19], ['+27720000002', '/learn/my', 10], ['+27720000003', '/start', 17], ['+27720000003', '/support', 20] /* S29: eligibility includes KasiBiz signals (compliance, stock); measured 19 */,
        ['+27720000010', '/work/employer', 20], ['+27720000024', '/hub-ops', 28], ['+27720000020', '/hub-ops/check-in?q=Th', 20],
        ['+27720000030', '/funder', 12], ['+27720000023', '/region', 40] /* one grouped count per indicator + health signals */, ['+27720000040', '/admin', 18],
    ];
    $over = [];
    foreach ($budgets as [$phone, $url, $budget]) {
        Staff::signIn($this, User::query()->where('phone', $phone)->firstOrFail());
        $this->get($url); // warm per-person caches (access, navigation) like a real session
        DB::flushQueryLog();
        DB::enableQueryLog();
        $status = $this->get($url)->getStatusCode();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();
        if ($status !== 200 || $count > $budget) {
            $over[] = "{$url}: {$count} queries (budget {$budget}), status {$status}";
        }
    }

    expect($over)->toBe([]);
});
