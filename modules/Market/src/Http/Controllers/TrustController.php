<?php

declare(strict_types=1);

namespace Modules\Market\Http\Controllers;

use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Ai\Moderation\ModerationService;
use Modules\Core\Enterprise\BusinessFacts;
use Modules\Core\Identity\Models\User;
use Modules\Market\Models\Order;
use Modules\Market\Models\Shop;
use Modules\Market\Services\Disputes;
use Modules\Market\Services\Insights;
use Modules\Market\Services\Reviews;
use Modules\Market\Services\Trust;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Reviews, disputes, no-shows, insights, shop reports and moderation (S27). */
final class TrustController
{
    public function __construct(private readonly Reviews $reviews, private readonly Disputes $disputes, private readonly Trust $trust, private readonly BusinessFacts $businesses) {}

    public function review(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate(['rating' => ['required', 'integer', 'between:1,5'], 'body' => ['nullable', 'string', 'max:500']]);

        return $this->attempt(fn () => $this->reviews->add($order, $this->user($request), (int) $data['rating'], $data['body'] ?? null), 'market.review.thanks');
    }

    public function reply(Request $request, int $review): RedirectResponse
    {
        $shop = $this->myShop($request);
        abort_unless(DB::table('market_reviews')->where('id', $review)->where('shop_id', $shop->id)->exists(), 404);
        $data = $request->validate(['reply' => ['required', 'string', 'max:500']]);

        return $this->attempt(fn () => $this->reviews->reply($review, $this->user($request), $data['reply']), 'market.review.replied');
    }

    public function openDispute(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', Rule::in(Disputes::REASONS)], 'details' => ['required', 'string', 'min:10', 'max:1000'],
            'photos' => ['array', 'max:4'], 'photos.*' => [File::image()->max(8 * 1024)]]);

        return $this->attempt(fn () => $this->disputes->open($order, $this->user($request), $data['reason'], $data['details'], $this->photos($request)), 'market.dispute.opened');
    }

    public function respondDispute(Request $request, string $dispute): RedirectResponse
    {
        $order = $this->disputeOrder($dispute);
        abort_unless($order->shop_id === $this->myShop($request)->id, 404);
        $data = $request->validate(['response' => ['required', 'string', 'min:5', 'max:1000'], 'photos' => ['array', 'max:4'], 'photos.*' => [File::image()->max(8 * 1024)]]);

        return $this->attempt(fn () => $this->disputes->respond($dispute, $order, $this->user($request), $data['response'], $this->photos($request)), 'market.dispute.responded');
    }

    public function settleDispute(Request $request, string $dispute): RedirectResponse
    {
        $order = $this->disputeOrder($dispute);
        abort_unless($order->buyer_id === $this->user($request)->id, 404);

        return $this->attempt(fn () => $this->disputes->settle($dispute, $order, $this->user($request)), 'market.dispute.settled');
    }

    /** Support's view of one dispute, with both sides' evidence. */
    public function showDispute(string $dispute): Response
    {
        $d = DB::table('market_disputes')->where('id', $dispute)->first();
        abort_if($d === null, 404);
        $order = Order::query()->with(['shop', 'items'])->whereKey((string) $d->order_id)->firstOrFail();

        return Inertia::render('Market/Dispute', [
            'dispute' => ['id' => (string) $d->id, 'reason' => (string) $d->reason, 'details' => (string) $d->details, 'status' => (string) $d->status,
                'response' => $d->trader_response !== null ? (string) $d->trader_response : null, 'respondBy' => (string) $d->respond_by, 'opened' => (string) $d->created_at,
                'outcome' => $d->outcome !== null ? (string) $d->outcome : null, 'outcomeNote' => $d->outcome_note !== null ? (string) $d->outcome_note : null],
            'order' => ['number' => $order->number, 'shop' => $order->shop->name, 'shopSlug' => $order->shop->slug, 'total_cents' => $order->total_cents, 'payment' => (string) $order->getAttribute('payment'),
                'paymentStatus' => (string) $order->getAttribute('payment_status'), 'refunded_cents' => (int) $order->getAttribute('refunded_cents'), 'status' => $order->status,
                'items' => $order->items->map(static fn ($i): string => $i->quantity.' × '.$i->title)->values()],
            // Evidence opens through the vault's short-lived signed links (each look-up is audited there).
            'files' => DB::table('market_dispute_files')->where('dispute_id', $dispute)->get(['document_id', 'added_by'])
                ->map(static fn (object $f): array => ['url' => route('documents.download', $f->document_id), 'by' => $f->added_by === $order->buyer_id ? 'buyer' : 'trader']),
            'canDecide' => ! in_array($d->status, ['settled', 'resolved'], true),
        ]);
    }

    public function decideDispute(Request $request, string $dispute): RedirectResponse
    {
        $order = $this->disputeOrder($dispute);
        $data = $request->validate(['outcome' => ['required', Rule::in(['refund_full', 'refund_part', 'no_refund', 'mediated'])], 'cents' => ['nullable', 'integer', 'min:1'],
            'note' => ['required', 'string', 'min:5', 'max:500']]);

        return $this->attempt(fn () => $this->disputes->decide($dispute, $order, $this->user($request), $data['outcome'], isset($data['cents']) ? (int) $data['cents'] : null, $data['note']),
            'market.dispute.decided');
    }

    public function noShow(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->shop_id === $this->myShop($request)->id, 404);

        return $this->attempt(fn () => $this->trust->noShow($order, $this->user($request)), 'market.noshow.recorded');
    }

    public function insights(Request $request): Response
    {
        $shop = $this->myShop($request);
        $days = in_array((int) $request->query('days', 30), [30, 90], true) ? (int) $request->query('days', 30) : 30;

        return Inertia::render('Market/Trader/Insights', ['insights' => Insights::for($shop, $days), 'signals' => Trust::signals($shop), 'days' => $days,
            'quick' => Trust::repliesQuickly($shop), 'strikes' => Trust::activeStrikes($shop),
            'reviews' => DB::table('market_reviews')->where('shop_id', $shop->id)->latest()->limit(30)->get(['id', 'rating', 'body', 'status', 'reply'])
                ->map(static fn (object $r): array => ['id' => (int) $r->id, 'rating' => (int) $r->rating, 'body' => $r->body !== null ? (string) $r->body : null,
                    'shown' => $r->status === 'sent', 'reply' => $r->reply !== null ? (string) $r->reply : null])]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $shop = $this->myShop($request);

        return response()->streamDownload(static function () use ($shop): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            foreach (Insights::csvRows($shop, 365) as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, 'kasimarket-orders-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function reportShop(Request $request, string $slug): RedirectResponse
    {
        $user = $this->user($request);
        $shop = Shop::query()->where('slug', $slug)->firstOrFail();
        $data = $request->validate(['reason' => ['required', Rule::in(['prohibited', 'scam', 'wrong_info', 'offensive', 'other'])], 'note' => ['nullable', 'string', 'max:500']]);
        DB::table('market_reports')->insert(['shop_id' => $shop->id, 'reported_by' => $user->id, 'reason' => $data['reason'], 'note' => $data['note'] ?? null, 'created_at' => now()]);
        app(ModerationService::class)->check($shop->name.' '.$shop->description, 'market_shop', $shop->id, null, useAi: false,
            extraReasons: [__('market.report.reason.'.$data['reason']).(($data['note'] ?? '') !== '' ? ': '.$data['note'] : '')]);

        return back()->with('status', __('market.report.thanks'));
    }

    /** Moderators: a shop's strikes and reports, give a strike, suspend, lift. */
    public function moderate(string $slug): Response
    {
        $shop = Shop::query()->where('slug', $slug)->firstOrFail();

        return Inertia::render('Market/Moderate', [
            'shop' => ['slug' => $shop->slug, 'name' => $shop->name, 'status' => $shop->status, 'suspendedReason' => $shop->getAttribute('suspended_reason')],
            'strikes' => DB::table('market_strikes')->where('shop_id', $shop->id)->latest('created_at')->get(['reason', 'source', 'created_at', 'expires_at'])
                ->map(static fn (object $s): array => ['reason' => (string) $s->reason, 'source' => (string) $s->source, 'at' => (string) $s->created_at, 'active' => now()->lt($s->expires_at)]),
            'reports' => DB::table('market_reports')->where('shop_id', $shop->id)
                ->orWhereIn('product_id', DB::table('market_products')->where('shop_id', $shop->id)->pluck('id'))->latest('created_at')->limit(50)
                ->get(['reason', 'note', 'created_at'])->map(static fn (object $r): array => ['reason' => (string) $r->reason, 'note' => $r->note, 'at' => (string) $r->created_at]),
            'active' => Trust::activeStrikes($shop), 'limit' => Trust::STRIKE_LIMIT,
        ]);
    }

    public function moderateAction(Request $request, string $slug): RedirectResponse
    {
        $shop = Shop::query()->where('slug', $slug)->firstOrFail();
        $data = $request->validate(['action' => ['required', Rule::in(['strike', 'suspend', 'lift'])], 'reason' => ['required', 'string', 'min:5', 'max:300'],
            'source' => ['nullable', Rule::in(['report', 'dispute', 'other'])]]);
        $user = $this->user($request);

        return $this->attempt(fn () => match ($data['action']) {
            'strike' => $this->trust->strike($shop, $user, $data['reason'], $data['source'] ?? 'other'),
            'suspend' => $this->trust->suspend($shop, $user, $data['reason']),
            'lift' => $this->trust->lift($shop, $user, $data['reason']),
            default => throw new DomainException(__('market.enforce.not_enough')),
        }, 'market.enforce.done');
    }

    private function disputeOrder(string $dispute): Order
    {
        return Order::query()->whereKey((string) DB::table('market_disputes')->where('id', $dispute)->value('order_id'))->firstOrFail();
    }

    /** @return list<UploadedFile> */
    private function photos(Request $request): array
    {
        return array_values(array_filter((array) $request->file('photos', []), static fn ($f): bool => $f instanceof UploadedFile));
    }

    private function myShop(Request $request): Shop
    {
        $shop = Shop::query()->whereIn('business_id', array_column($this->businesses->businessesOf($this->user($request)), 'id'))->first();
        abort_unless($shop instanceof Shop, 404);

        return $shop;
    }

    private function attempt(callable $fn, string $ok): RedirectResponse
    {
        try {
            $fn();
        } catch (DomainException $e) {
            return back()->withErrors(['trust' => $e->getMessage()]);
        }

        return back()->with('status', __($ok));
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
