<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Ai\Models\AiFeatureSetting;
use Modules\Core\Ai\Models\AiRequestLog;
use Modules\Core\Ai\PromptRegistry;
use Modules\Core\Identity\Services\AuditLogger;

/**
 * AI controls: switches per feature and for all AI, monthly budgets, usage and cost.
 */
final class AiController
{
    use Concerns;

    public function index(Request $request, PromptRegistry $prompts): Response
    {
        $since = now()->startOfMonth();
        $usage = AiRequestLog::query()->where('created_at', '>=', $since)
            ->selectRaw("feature, count(*) as calls, sum(case when outcome = 'ok' then 1 else 0 end) as ok, sum(case when outcome in ('unavailable', 'invalid') then 1 else 0 end) as failed, sum(case when outcome in ('budget', 'disabled') then 1 else 0 end) as refused, sum(cost_cents) as cost")
            ->groupBy('feature')->get()->keyBy('feature');
        $settings = AiFeatureSetting::query()->get()->keyBy('feature');
        $canManage = $this->can($request, 'admin.ai.manage');

        return Inertia::render('Admin/Ai', [
            'driver' => config('kasi.drivers.ai'),
            'allEnabled' => (bool) ($settings[AiFeatureSetting::ALL]->enabled ?? true),
            'budgets' => config('kasi.ai.budgets'),
            'monthCost' => (int) AiRequestLog::query()->where('created_at', '>=', $since)->sum('cost_cents'),
            'features' => collect($prompts->features())->map(static fn (string $label, string $feature): array => [
                'feature' => $feature,
                'label' => $label,
                'enabled' => (bool) ($settings[$feature]->enabled ?? true),
                'budgetCents' => $settings[$feature]->monthly_budget_cents ?? null,
                'calls' => (int) ($usage[$feature]->calls ?? 0),
                'ok' => (int) ($usage[$feature]->ok ?? 0),
                'failed' => (int) ($usage[$feature]->failed ?? 0),
                'refused' => (int) ($usage[$feature]->refused ?? 0),
                'costCents' => (int) ($usage[$feature]->cost ?? 0),
            ])->values(),
            'daily' => DB::table('ai_requests')->where('created_at', '>=', now()->subDays(13)->startOfDay())
                ->selectRaw('date(created_at) as day, count(*) as calls, sum(cost_cents) as cost')->groupBy(DB::raw('date(created_at)'))->orderBy('day')->get()
                ->map(static fn (object $r): array => ['day' => (string) ($r->day ?? ''), 'calls' => (int) ($r->calls ?? 0), 'costCents' => (int) ($r->cost ?? 0)]),
            'recent' => AiRequestLog::query()->latest('created_at')->limit(30)->get()->map(static fn (AiRequestLog $r): array => [
                'id' => $r->id, 'feature' => $r->feature, 'version' => $r->prompt_version, 'model' => $r->model, 'outcome' => $r->outcome,
                'tokens' => $r->input_tokens + $r->output_tokens, 'costCents' => $r->cost_cents, 'ms' => $r->duration_ms, 'at' => $r->created_at->toIso8601String(),
                'input' => $canManage ? $r->input : null, 'output' => $canManage ? $r->output : null,
            ]),
            'canManage' => $canManage,
        ]);
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'feature' => ['required', 'string', 'max:64'],
            'enabled' => ['required', 'boolean'],
            'monthly_budget_cents' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'reason' => ['required', 'string', 'min:5', 'max:300'],
        ]);

        AiFeatureSetting::query()->updateOrCreate(['feature' => $validated['feature']], [
            'enabled' => $validated['enabled'], 'monthly_budget_cents' => $validated['monthly_budget_cents'] ?? null, 'updated_by' => $this->actor($request)->id,
        ]);
        $audit->record('ai.settings_changed', meta: $validated, actor: $this->actor($request));

        return back()->with('status', __('admin.saved'));
    }
}
