<?php

declare(strict_types=1);

namespace Modules\HubOps\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\HubOps\Services\HubMetrics;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Hub performance for hub staff, and a comparison across hubs for coordinators.
 */
final class DashboardController extends StaffController
{
    public function index(Request $request, HubMetrics $metrics): Response
    {
        $hub = $this->hub($request);
        [$from, $to, $days] = $this->period($request);

        return Inertia::render('HubOps/Dashboard', [
            'hubs' => $this->hubProps($request, $hub),
            'period' => $days,
            'metrics' => $metrics->forHub($hub, $from, $to),
            'canCompare' => $this->can($request, 'hubops.compare'),
        ]);
    }

    public function compare(Request $request, HubMetrics $metrics): Response
    {
        [$from, $to, $days] = $this->period($request);

        return Inertia::render('HubOps/Compare', [
            'period' => $days,
            'rows' => $metrics->compare($this->currentHub->hubs($this->actor($request))->where('status', '!=', 'planned'), $from, $to),
        ]);
    }

    public function export(Request $request, HubMetrics $metrics): StreamedResponse
    {
        $hub = $this->hub($request);
        [$from, $to] = $this->period($request);
        $data = $metrics->forHub($hub, $from, $to);

        return response()->streamDownload(function () use ($data): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['date', 'visits'], escape: '\\');
            foreach ($data['byDay'] as $row) {
                fputcsv($out, [$row['date'], $row['visits']], escape: '\\');
            }
            fputcsv($out, [], escape: '\\');
            fputcsv($out, ['measure', 'value'], escape: '\\');
            foreach ($data['totals'] as $key => $value) {
                fputcsv($out, [$key, $value], escape: '\\');
            }
            foreach ($data['purposes'] as $purpose => $value) {
                fputcsv($out, ['purpose:'.$purpose, $value], escape: '\\');
            }
            fclose($out);
        }, 'kasihub-'.($hub->slug ?? $hub->code).'-'.$from->toDateString().'-to-'.$to->toDateString().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: int} */
    private function period(Request $request): array
    {
        $days = in_array((int) $request->query('period'), [7, 30, 90], true) ? (int) $request->query('period') : 30;
        $to = CarbonImmutable::now('Africa/Johannesburg')->startOfDay();

        return [$to->subDays($days - 1), $to, $days];
    }
}
