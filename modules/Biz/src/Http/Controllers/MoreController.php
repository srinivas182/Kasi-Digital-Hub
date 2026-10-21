<?php

declare(strict_types=1);

namespace Modules\Biz\Http\Controllers;

use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Biz\Http\Concerns\ResolvesBusiness;
use Modules\Biz\Services\Compliance;
use Modules\Biz\Services\Stock;
use Modules\Biz\Services\Summaries;
use Modules\Core\Enterprise\BusinessFacts;
use Modules\Core\Enterprise\ProductStock;
use Modules\Core\Identity\Models\User;

/** KasiBiz part 2 (S29): stock, the compliance calendar, and sharing a summary. */
final class MoreController
{
    use ResolvesBusiness;

    public function __construct(private readonly Stock $stock, private readonly Compliance $compliance, private readonly Summaries $summaries) {}

    public function stock(Request $request): Response
    {
        [$person] = $this->who($request);
        $business = $this->business($request, $person);
        $items = $this->stock->items($business['id']);
        $linked = DB::table('biz_stock_items')->where('business_id', $business['id'])->whereNotNull('market_product_id')->pluck('market_product_id')->all();

        return Inertia::render('Biz/Stock', [
            'business' => $business, 'items' => $items, 'value_cents' => array_sum(array_column($items, 'value_cents')), 'units' => Stock::UNITS,
            'products' => array_values(array_filter(app(ProductStock::class)->products($business['id']), static fn (array $p): bool => ! in_array($p['id'], $linked, true))),
            'history' => $request->integer('item') > 0 ? ['item' => $request->integer('item'), 'moves' => $this->stock->history($business['id'], $request->integer('item'))] : null,
        ]);
    }

    public function addItem(Request $request): RedirectResponse
    {
        [$person, $helper] = $this->who($request);
        $business = $this->business($request, $person);
        $data = $request->validate(['name' => ['required', 'string', 'max:80'], 'unit' => ['required', Rule::in(Stock::UNITS)], 'cost_cents' => ['nullable', 'integer', 'min:0'],
            'price_cents' => ['nullable', 'integer', 'min:0'], 'quantity' => ['nullable', 'integer', 'min:0', 'max:1000000'], 'reorder_level' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'market_product_id' => ['nullable', 'string', 'max:26']]);

        return $this->attempt(fn () => $this->stock->add($business['id'], $helper ?? $person, $data), 'biz.stock.added');
    }

    public function move(Request $request, int $item): RedirectResponse
    {
        [$person, $helper] = $this->who($request);
        $business = $this->business($request, $person);
        $data = $request->validate(['kind' => ['required', Rule::in(['bought', 'counted', ...Stock::OUT_KINDS])], 'quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'cost_cents' => ['nullable', 'integer', 'min:0'], 'note' => ['nullable', 'string', 'max:200'], 'to_books' => ['boolean']]);

        return $this->attempt(fn () => $this->stock->move($business['id'], $item, $helper ?? $person, $data['kind'], (int) $data['quantity'], isset($data['cost_cents']) ? (int) $data['cost_cents'] : null,
            $data['note'] ?? null, (bool) ($data['to_books'] ?? false), $helper !== null ? $person : null), 'biz.stock.moved');
    }

    public function compliance(Request $request): Response
    {
        [$person] = $this->who($request);
        $business = $this->business($request, $person);
        $this->compliance->refresh($business['id']); // reacts to changes in KasiStart (e.g. a new step done) on every visit
        $rows = DB::table('biz_obligations')->where('business_id', $business['id'])->orderBy('due_on')->get();
        $today = now()->toDateString();
        $row = static fn (\stdClass $o): array => ['id' => (int) $o->id, 'rule' => (string) $o->rule, 'due_on' => (string) $o->due_on, 'status' => (string) $o->status,
            'overdue' => $o->status === 'due' && (string) $o->due_on < $today, 'guide' => Compliance::RULES[(string) $o->rule] ?? null];

        return Inertia::render('Biz/Compliance', [
            'business' => $business, 'profile' => $this->compliance->profile($business['id']),
            'legalForm' => app(BusinessFacts::class)->facts($business['id'])['legal_form'] ?? null,
            'open' => $rows->where('status', 'due')->values()->map($row), 'done' => $rows->where('status', 'done')->sortByDesc('done_at')->take(12)->values()->map($row),
        ]);
    }

    public function profile(Request $request): RedirectResponse
    {
        [$person, $helper] = $this->who($request);
        $business = $this->business($request, $person);
        $data = $request->validate(['registered_on' => ['nullable', 'date', 'before_or_equal:today'], 'turnover_tax' => ['boolean'], 'vat_registered' => ['boolean'], 'employs' => ['boolean'],
            'bbbee_on' => ['nullable', 'date'], 'permit_renewal_on' => ['nullable', 'date']]);
        $this->compliance->saveProfile($business['id'], $helper ?? $person, $data);

        return back()->with('status', __('work.saved'));
    }

    public function done(Request $request, int $obligation): RedirectResponse
    {
        [$person, $helper] = $this->who($request);
        $business = $this->business($request, $person);
        $request->validate(['proof' => ['nullable', File::types(['pdf', 'jpg', 'jpeg', 'png'])->max(10 * 1024)]]);
        $proof = $request->file('proof');

        return $this->attempt(fn () => $this->compliance->done($business['id'], $obligation, $helper ?? $person, $proof instanceof UploadedFile ? $proof : null,
            $helper !== null ? $person : null), 'biz.compliance.marked_done');
    }

    public function moveDate(Request $request, int $obligation): RedirectResponse
    {
        [$person, $helper] = $this->who($request);
        $business = $this->business($request, $person);
        $data = $request->validate(['due_on' => ['required', 'date']]);

        return $this->attempt(fn () => $this->compliance->moveDate($business['id'], $obligation, $helper ?? $person, $data['due_on']), 'biz.compliance.moved');
    }

    public function share(Request $request): Response
    {
        [$person] = $this->who($request);
        $business = $this->business($request, $person);

        return Inertia::render('Biz/Share', ['business' => $business, 'preview' => $this->summaries->build($business['id'], $business['name']),
            'shares' => $this->summaries->shares($business['id']), 'newLink' => session('share_link')]);
    }

    public function createShare(Request $request): RedirectResponse
    {
        [$person, $helper] = $this->who($request);
        $business = $this->business($request, $person);
        $data = $request->validate(['recipient' => ['nullable', 'string', 'max:120']]);
        $token = $this->summaries->share($business['id'], $helper ?? $person, $data['recipient'] ?? null);

        // The link is shown once (only its hash is kept).
        return back()->with('share_link', url('/biz/shared/'.$token))->with('status', __('biz.share.created'));
    }

    public function withdraw(Request $request, string $share): RedirectResponse
    {
        [$person, $helper] = $this->who($request);
        $business = $this->business($request, $person);

        return $this->attempt(fn () => $this->summaries->withdraw($business['id'], $share, $helper ?? $person), 'biz.share.withdrawn');
    }

    /** The shared summary, opened by its link (no account needed); every view is logged. */
    public function shared(Request $request, string $token): Response|\Illuminate\Http\Response
    {
        $viewer = $request->user();
        $businessId = $this->summaries->open($token, $viewer instanceof User ? $viewer : null, $request->ip());
        if ($businessId === null) {
            return response(__('biz.share.gone'), 410);
        }
        $name = (string) (app(BusinessFacts::class)->facts($businessId)['name'] ?? '');

        return Inertia::render('Biz/Shared', ['summary' => $this->summaries->build($businessId, $name)]);
    }

    private function attempt(callable $fn, string $ok): RedirectResponse
    {
        try {
            $fn();
        } catch (DomainException $e) {
            return back()->withErrors(['biz' => $e->getMessage()]);
        }

        return back()->with('status', __($ok));
    }
}
