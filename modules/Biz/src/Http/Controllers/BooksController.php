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
use Modules\Biz\Models\Entry;
use Modules\Biz\Services\Books;
use Modules\Biz\Services\Owed;
use Modules\Core\Assist\AssistedSession;
use Modules\Core\Documents\Generation\PdfRenderer;
use Modules\Core\Enterprise\BusinessFacts;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\HubModule;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** KasiBiz (S28): the cash book, who owes what, the month summary and exports. */
final class BooksController
{
    public function __construct(private readonly Books $books, private readonly Owed $owed, private readonly BusinessFacts $businesses, private readonly AssistedSession $assist) {}

    public function index(Request $request): Response
    {
        [$person] = $this->who($request);
        $business = $this->business($request, $person, false);
        if ($business === null) {
            return Inertia::render('Biz/Start', ['hasBusiness' => $this->businesses->businessesOf($person) !== []]);
        }
        $this->books->sync($business['id'], $person);
        $month = $this->month($request);
        $book = DB::table('biz_books')->where('business_id', $business['id'])->first();

        return Inertia::render('Biz/Books', [
            'business' => ['id' => $business['id'], 'name' => $business['name']],
            'businesses' => array_map(static fn (array $b): array => ['id' => $b['id'], 'name' => $b['name']], $this->businesses->businessesOf($person)),
            'month' => $month,
            'summary' => $this->books->summary($business['id'], $month),
            'entries' => $this->books->visible($business['id'])->where('occurred_on', 'like', $month.'%')->orderByDesc('occurred_on')->orderByDesc('created_at')->limit(200)->get()
                ->map(static fn (Entry $e): array => ['id' => $e->id, 'direction' => $e->direction, 'cents' => $e->amount_cents, 'category' => $e->category, 'method' => $e->method,
                    'on' => $e->occurred_on->toDateString(), 'note' => $e->note, 'source' => $e->source, 'receipt' => $e->receipt_document_id !== null,
                    'canChange' => in_array($e->source, ['manual', 'credit'], true) && $e->created_at->gte(now()->subDays(Books::CORRECT_DAYS))]),
            'opening' => (array) json_decode((string) ($book->opening ?? '{}'), true),
            'categories' => Books::CATEGORIES, 'methods' => Books::METHODS, 'notTrading' => Books::NOT_TRADING,
            'contacts' => DB::table('biz_contacts')->where('business_id', $business['id'])->orderBy('name')->get(['id', 'kind', 'name']),
        ]);
    }

    public function record(Request $request): RedirectResponse
    {
        [$person, $actor] = $this->who($request);
        $business = $this->business($request, $person);
        $data = $this->entryData($request);
        $receipt = $request->file('receipt');

        return $this->attempt(fn () => $this->books->record($business['id'], $actor ?? $person, $data, $receipt instanceof UploadedFile ? $receipt : null,
            'manual', null, $actor !== null ? $person : null), 'biz.entry.saved');
    }

    public function edit(Request $request, Entry $entry): RedirectResponse
    {
        [$person, $actor] = $this->who($request);
        abort_unless($entry->business_id === $this->business($request, $person)['id'], 404);

        return $this->attempt(fn () => $this->books->edit($entry, $actor ?? $person, $this->entryData($request)), 'biz.entry.saved');
    }

    public function remove(Request $request, Entry $entry): RedirectResponse
    {
        [$person, $actor] = $this->who($request);
        abort_unless($entry->business_id === $this->business($request, $person)['id'], 404);

        return $this->attempt(fn () => $this->books->remove($entry, $actor ?? $person), 'biz.entry.removed');
    }

    public function hide(Request $request, Entry $entry): RedirectResponse
    {
        [$person, $actor] = $this->who($request);
        abort_unless($entry->business_id === $this->business($request, $person)['id'], 404);
        $this->books->hide($entry, $actor ?? $person, $request->boolean('hidden'));

        return back();
    }

    public function opening(Request $request): RedirectResponse
    {
        [$person, $actor] = $this->who($request);
        $business = $this->business($request, $person);
        $data = $request->validate(['cash' => ['required', 'integer', 'min:0'], 'card' => ['required', 'integer', 'min:0'], 'mobile' => ['required', 'integer', 'min:0']]);
        $this->books->setOpening($business['id'], $data, $actor ?? $person);

        return back()->with('status', __('biz.opening.saved'));
    }

    public function owed(Request $request): Response
    {
        [$person] = $this->who($request);
        $business = $this->business($request, $person);

        return Inertia::render('Biz/Owed', ['business' => ['id' => $business['id'], 'name' => $business['name']], 'open' => Owed::open($business['id']),
            'contacts' => DB::table('biz_contacts')->where('business_id', $business['id'])->orderBy('name')->get(['id', 'kind', 'name', 'phone']), 'methods' => Books::METHODS]);
    }

    public function contact(Request $request): RedirectResponse
    {
        [$person] = $this->who($request);
        $business = $this->business($request, $person);
        $data = $request->validate(['kind' => ['required', Rule::in(['customer', 'supplier'])], 'name' => ['required', 'string', 'max:80'], 'phone' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:300']]);

        return $this->attempt(fn () => $this->owed->contact($business['id'], $data['kind'], $data['name'], $data['phone'] ?? null, $data['notes'] ?? null), 'biz.owed.contact_saved');
    }

    public function addOwed(Request $request): RedirectResponse
    {
        [$person, $actor] = $this->who($request);
        $business = $this->business($request, $person);
        $data = $request->validate(['contact_id' => ['required', 'integer'], 'direction' => ['required', Rule::in(['owed_me', 'i_owe'])], 'amount_cents' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:200'], 'due_on' => ['nullable', 'date']]);

        return $this->attempt(fn () => $this->owed->add($business['id'], (int) $data['contact_id'], $data['direction'], (int) $data['amount_cents'], $data['description'],
            $data['due_on'] ?? null, $actor ?? $person), 'biz.owed.saved');
    }

    public function paid(Request $request, int $credit): RedirectResponse
    {
        [$person, $actor] = $this->who($request);
        $business = $this->business($request, $person);
        $data = $request->validate(['method' => ['required', Rule::in(Books::METHODS)]]);

        return $this->attempt(fn () => $this->owed->paid($business['id'], $credit, $data['method'], $actor ?? $person, null, $actor !== null ? $person : null), 'biz.owed.paid');
    }

    public function csv(Request $request): StreamedResponse
    {
        [$person] = $this->who($request);
        $business = $this->business($request, $person);
        $month = $this->month($request);

        return response()->streamDownload(function () use ($business, $month): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['date', 'money', 'amount_rand', 'category', 'paid_by', 'note', 'from']);
            foreach ($this->books->visible($business['id'])->where('occurred_on', 'like', $month.'%')->orderBy('occurred_on')->get() as $e) {
                fputcsv($out, [$e->occurred_on->toDateString(), $e->direction, number_format($e->amount_cents / 100, 2, '.', ''), $e->category, $e->method, (string) $e->note, $e->source]);
            }
            fclose($out);
        }, 'kasibiz-'.$month.'.csv', ['Content-Type' => 'text/csv']);
    }

    /** A one-page monthly summary, e.g. for a bank, a funder application or turnover tax. */
    public function pdf(Request $request, PdfRenderer $pdf): HttpResponse
    {
        [$person] = $this->who($request);
        $business = $this->business($request, $person);
        $month = $this->month($request);
        $html = view('biz::summary', ['business' => $business['name'], 'summary' => $this->books->summary($business['id'], $month)])->render();

        return response($pdf->render($html), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="kasibiz-'.$month.'.pdf"']);
    }

    /** @return array{direction: string, amount_cents: int, category: string, method: string, occurred_on: string|null, note: string|null, contact_id: int|null} */
    private function entryData(Request $request): array
    {
        $d = $request->validate(['direction' => ['required', Rule::in(['in', 'out'])], 'amount_cents' => ['required', 'integer', 'min:1', 'max:100000000'],
            'category' => ['required', 'string', 'max:20'], 'method' => ['required', Rule::in(Books::METHODS)], 'occurred_on' => ['nullable', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:300'], 'contact_id' => ['nullable', 'integer'], 'receipt' => ['nullable', File::image()->max(8 * 1024)]]);

        return ['direction' => (string) $d['direction'], 'amount_cents' => (int) $d['amount_cents'], 'category' => (string) $d['category'], 'method' => (string) $d['method'],
            'occurred_on' => isset($d['occurred_on']) ? (string) $d['occurred_on'] : null, 'note' => isset($d['note']) ? (string) $d['note'] : null,
            'contact_id' => isset($d['contact_id']) ? (int) $d['contact_id'] : null];
    }

    private function month(Request $request): string
    {
        $m = (string) $request->query('month', now()->format('Y-m'));

        return preg_match('/^\d{4}-\d{2}$/', $m) === 1 ? $m : now()->format('Y-m');
    }

    /**
     * The person whose books these are (in a hub assisted session, the person being helped) and the facilitator.
     *
     * @return array{0: User, 1: User|null}
     */
    private function who(Request $request): array
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $helped = $this->assist->person($user);

        return [$helped ?? $user, $helped !== null ? $user : null];
    }

    /** @return ($required is true ? array{id: string, name: string} : array{id: string, name: string}|null) */
    private function business(Request $request, User $person, bool $required = true): ?array
    {
        $mine = $this->businesses->businessesOf($person);
        $wanted = (string) $request->input('business', '');
        $business = collect($mine)->firstWhere('id', $wanted) ?? ($mine[0] ?? null);
        $available = $business !== null && $this->hubHasBiz($business['id'], $person);
        abort_if($required && ! $available, 404);

        return $available ? ['id' => (string) $business['id'], 'name' => (string) $business['name']] : null;
    }

    private function hubHasBiz(string $businessId, User $person): bool
    {
        $hubId = $this->businesses->facts($businessId)['hub_id'] ?? $person->home_hub_id;

        return $hubId !== null && HubModule::query()->where('hub_id', $hubId)->where('module', 'Biz')->where('enabled', true)->exists();
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
