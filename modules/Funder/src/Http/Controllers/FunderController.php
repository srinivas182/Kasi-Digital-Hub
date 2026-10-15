<?php

declare(strict_types=1);

namespace Modules\Funder\Http\Controllers;

use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Access\AccessResolver;
use Modules\Core\Documents\Generation\DocumentIssuer;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Insights\Insights;
use Modules\Core\Structure\Models\Hub;
use Modules\Core\Structure\Models\Organisation;
use Modules\Core\Structure\Models\Province;
use Modules\Core\Structure\Models\RoleAssignment;
use Modules\Funder\Models\Programme;
use Modules\Funder\Services\Programmes;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The funder portal (ADR-028): programmes, impact against targets, trends and breakdowns - aggregates only,
 * small numbers shown as "<10". Every view and export is audited.
 */
final class FunderController
{
    public function __construct(private readonly Programmes $programmes, private readonly Insights $insights, private readonly AuditLogger $audit) {}

    public function index(Request $request): Response
    {
        $user = $this->user($request);

        return Inertia::render('Funder/Index', [
            'programmes' => $this->visible($user)->map(static fn (Programme $p): array => ['id' => $p->id, 'name' => $p->name, 'funder' => $p->organisation->displayName(),
                'period' => $p->starts_on->toDateString().' - '.$p->ends_on->toDateString(), 'status' => $p->status])->values(),
            'isStaff' => $this->staff($user),
            'funders' => $this->staff($user) ? Organisation::query()->where('type', 'funder')->orderBy('name')->get(['id', 'name']) : [],
            'provinces' => $this->staff($user) ? Province::query()->orderBy('name')->get(['id', 'name']) : [],
            'indicators' => array_keys($this->insights->indicators()),
            'changes' => $this->staff($user) ? DB::table('fund_target_changes')->join('fund_programmes', 'fund_programmes.id', '=', 'fund_target_changes.programme_id')
                ->where('fund_target_changes.status', 'pending')->get(['fund_target_changes.id', 'fund_programmes.name', 'indicator', 'target', 'reason', 'requested_by'])
                ->map(static fn (object $c): array => ['id' => (int) $c->id, 'programme' => (string) $c->name, 'indicator' => (string) $c->indicator, 'target' => (int) $c->target,
                    'reason' => (string) $c->reason, 'mine' => $c->requested_by === $user->id]) : [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($this->staff($user), 403);
        $data = $request->validate([
            'organisation_id' => ['required', 'string', Rule::exists('organisations', 'id')->where('type', 'funder')],
            'name' => ['required', 'string', 'max:160'], 'summary' => ['nullable', 'string', 'max:2000'],
            'province_ids' => ['array'], 'province_ids.*' => ['integer', 'exists:provinces,id'],
            'starts_on' => ['required', 'date'], 'ends_on' => ['required', 'date', 'after:starts_on'],
            'age_min' => ['required', 'integer', 'min:16', 'max:60'], 'age_max' => ['required', 'integer', 'gte:age_min', 'max:120'],
            'targets' => ['array'], 'targets.*' => ['integer', 'min:0'],
        ]);
        $p = Programme::query()->create(['organisation_id' => $data['organisation_id'], 'name' => $data['name'], 'summary' => $data['summary'] ?? null,
            'scope' => ['province_ids' => array_map('intval', $data['province_ids'] ?? [])], 'starts_on' => $data['starts_on'], 'ends_on' => $data['ends_on'],
            'age_min' => $data['age_min'], 'age_max' => $data['age_max'], 'status' => 'active', 'created_by' => $user->id]);
        foreach (array_intersect_key($data['targets'] ?? [], $this->insights->indicators()) as $indicator => $target) {
            DB::table('fund_targets')->insert(['programme_id' => $p->id, 'indicator' => $indicator, 'target' => (int) $target]);
        }
        $this->audit->record('funder.programme_created', meta: ['programme' => $p->id], actor: $user);

        return to_route('funder.programme', $p)->with('status', __('funder.programme.created'));
    }

    public function show(Request $request, Programme $programme): Response
    {
        $user = $this->can($request, $programme);
        $this->audit->record('funder.viewed', meta: ['programme' => $programme->id], actor: $user);
        $indicator = (string) $request->query('indicator', $this->programmes->indicators($programme)[0] ?? 'reach.registered');
        $periods = $this->programmes->periods($programme);
        $current = (string) end($periods);

        return Inertia::render('Funder/Programme', [
            'programme' => ['id' => $programme->id, 'name' => $programme->name, 'summary' => $programme->summary, 'funder' => $programme->organisation->displayName(),
                'starts' => $programme->starts_on->toDateString(), 'ends' => $programme->ends_on->toDateString(), 'ages' => $programme->age_min.'-'.$programme->age_max,
                'scope' => $programme->scope, 'hubs' => count($this->programmes->hubs($programme))],
            'progress' => array_map(static fn (array $p): array => [...$p, 'value' => Insights::cell($p['value'])], $this->programmes->progress($programme)),
            'trend' => array_map(fn (string $period): array => ['period' => $period, ...array_map([Insights::class, 'cell'], ($m = $this->programmes->month($programme, $period))['values']), 'provisional' => $m['provisional']], $periods),
            'indicator' => $indicator,
            'breakdown' => (static function () use ($programme, $indicator, $current): array {
                [$from] = Programmes::bounds((string) ($programme->starts_on->format('Y-m')));
                [, $to] = Programmes::bounds($current !== '' ? $current : now()->format('Y-m'));

                return app(Insights::class)->breakdown($indicator, app(Programmes::class)->hubs($programme), $from, $to);
            })(),
            'units' => array_map(static fn ($i): string => $i->unit(), $this->insights->indicators()),
            'invoices' => DB::table('com_invoices')->where('organisation_id', $programme->organisation_id)->where('kind', 'licence')->orderByDesc('period')->get(['number', 'period', 'amount_cents', 'status'])
                ->map(static fn (object $i): array => ['number' => (string) $i->number, 'period' => (string) $i->period, 'amount' => 'R'.number_format((int) $i->amount_cents / 100, 2, '.', ' '), 'status' => (string) $i->status]),
            'stories' => DB::table('fund_stories')->join('hubs', 'hubs.id', '=', 'fund_stories.hub_id')->where('fund_stories.status', 'published')
                ->where(fn ($q) => $q->where('programme_id', $programme->id)->orWhereNull('programme_id'))->whereIn('fund_stories.hub_id', $this->programmes->hubs($programme))
                ->latest('fund_stories.created_at')->limit(6)->get(['title', 'body', 'hubs.name as hub']),
            'canRequest' => $this->staff($user) || $this->role($user, $programme, 'funder_manager'),
            'indicators' => array_keys($this->insights->indicators()),
            'minCell' => Insights::MIN_CELL,
        ]);
    }

    public function requestTarget(Request $request, Programme $programme): RedirectResponse
    {
        $user = $this->can($request, $programme);
        abort_unless($this->staff($user) || $this->role($user, $programme, 'funder_manager'), 403);
        $data = $request->validate(['indicator' => ['required', 'string', 'max:40'], 'target' => ['required', 'integer', 'min:0'], 'reason' => ['required', 'string', 'min:5', 'max:300']]);
        try {
            $this->programmes->requestTarget($programme, $data['indicator'], (int) $data['target'], $data['reason'], $user);
        } catch (DomainException $e) {
            return back()->withErrors(['target' => $e->getMessage()]);
        }

        return back()->with('status', __('funder.target.requested'));
    }

    public function decideTarget(Request $request, int $change): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($this->staff($user), 403);
        $data = $request->validate(['approve' => ['required', 'boolean']]);
        try {
            $this->programmes->decideTarget($change, $user, (bool) $data['approve']);
        } catch (DomainException $e) {
            return back()->withErrors(['target' => $e->getMessage()]);
        }

        return back()->with('status', __('commercial.approval.decided'));
    }

    /** Verifiable impact report (PDF) for a month - suppressed aggregates only. */
    public function report(Request $request, Programme $programme, DocumentIssuer $issuer): StreamedResponse
    {
        $user = $this->can($request, $programme);
        $period = (string) $request->query('period', now()->format('Y-m'));
        $month = $this->programmes->month($programme, $period);
        $this->audit->record('funder.report', meta: ['programme' => $programme->id, 'period' => $period], actor: $user);
        $document = $issuer->issue(owner: $user, type: 'funder_report', title: 'Impact report - '.$programme->name.' - '.$period, view: 'funder::report',
            data: ['programme' => $programme, 'period' => $period, 'provisional' => $month['provisional'], 'month' => Insights::suppress($month['values']),
                'progress' => array_map(static fn (array $p): array => [...$p, 'value' => Insights::cell($p['value'])], $this->programmes->progress($programme)),
                'units' => array_map(static fn ($i): string => $i->unit(), $this->insights->indicators())],
            templateVersion: 1, subject: ['fund_programme', $programme->id]);

        return $issuer->download($document);
    }

    public function csv(Request $request, Programme $programme): StreamedResponse
    {
        $user = $this->can($request, $programme);
        $this->audit->record('funder.export', meta: ['programme' => $programme->id], actor: $user);
        $rows = [];
        $hubNames = Hub::query()->pluck('name', 'id');
        foreach ($this->programmes->periods($programme) as $period) {
            $m = $this->programmes->month($programme, $period);
            foreach ($m['byHub'] as $indicator => $byHub) {
                foreach ($byHub as $hubId => $value) {
                    $rows[] = [$period, $indicator, $hubNames[$hubId] ?? 'national', Insights::cell((int) $value), $m['provisional'] ? 'provisional' : 'final'];
                }
            }
        }

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['period', 'indicator', 'hub', 'value', 'status'], escape: '\\');
            foreach ($rows as $row) {
                fputcsv($out, $row, escape: '\\');
            }
            fclose($out);
        }, 'programme-'.$programme->id.'.csv', ['Content-Type' => 'text/csv']);
    }

    /** Hub staff add a consented, anonymised success story. */
    public function story(Request $request): RedirectResponse
    {
        $user = $this->user($request);
        $data = $request->validate(['hub_id' => ['required', 'string'], 'programme_id' => ['nullable', 'string', 'exists:fund_programmes,id'], 'title' => ['required', 'string', 'max:140'],
            'body' => ['required', 'string', 'min:30', 'max:3000'], 'consent' => ['accepted']]);
        abort_unless(RoleAssignment::query()->where('user_id', $user->id)->where('scope_type', 'hub')->where('scope_id', $data['hub_id'])->whereIn('role', ['hub_manager', 'hub_owner', 'hub_facilitator'])->exists(), 403);
        DB::table('fund_stories')->insert(['hub_id' => $data['hub_id'], 'programme_id' => $data['programme_id'] ?? null, 'title' => $data['title'], 'body' => $data['body'],
            'consent_recorded' => true, 'written_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('status', __('funder.story.saved'));
    }

    /** @return Collection<int, Programme> */
    private function visible(User $user): Collection
    {
        $q = Programme::query()->with('organisation')->orderByDesc('starts_on');
        if (! $this->staff($user)) {
            $q->whereIn('organisation_id', RoleAssignment::query()->where('user_id', $user->id)->whereIn('role', ['funder_manager', 'funder_viewer'])->where('scope_type', 'organisation')->pluck('scope_id'));
        }

        return $q->get();
    }

    private function can(Request $request, Programme $programme): User
    {
        $user = $this->user($request);
        abort_unless($this->staff($user) || $this->role($user, $programme, 'funder_manager') || $this->role($user, $programme, 'funder_viewer'), 404);
        $programme->loadMissing('organisation');

        return $user;
    }

    private function role(User $user, Programme $programme, string $role): bool
    {
        return RoleAssignment::query()->where('user_id', $user->id)->where('role', $role)->where('scope_type', 'organisation')->where('scope_id', $programme->organisation_id)->exists();
    }

    private function staff(User $user): bool
    {
        return app(AccessResolver::class)->hasPermission($user, 'funder.admin');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
