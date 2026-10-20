<?php

declare(strict_types=1);

namespace Modules\Market\Services;

use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Documents\DocumentVault;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Notifications\Notifier;
use Modules\Market\Events\DisputeOpened;
use Modules\Market\Events\DisputeResolved;
use Modules\Market\Models\Order;
use Modules\Market\Notifications\MarketNotification;

/**
 * Disputes (ADR-035): the buyer opens one within 14 days (photos kept private in the document vault); the trader
 * responds within 3 days or the two settle; otherwise KasiHub support decides - refunds only for online payments,
 * mediation for cash. Both sides are told.
 */
final readonly class Disputes
{
    public const DAYS = 14;

    public const RESPOND_DAYS = 3;

    public const REASONS = ['not_received', 'not_described', 'damaged', 'charged_wrongly', 'other'];

    public function __construct(private DocumentVault $vault, private AuditLogger $audit, private Notifier $notifier, private Ordering $ordering) {}

    public function canOpen(Order $order, User $buyer): bool
    {
        $since = $order->completed_at ?? $order->accepted_at ?? $order->created_at;

        return $order->buyer_id === $buyer->id && ! in_array($order->status, ['placed', 'declined', 'lapsed'], true)
            && $since->gte(now()->subDays(self::DAYS)) && ! DB::table('market_disputes')->where('order_id', $order->id)->exists();
    }

    /** @param list<UploadedFile> $photos */
    public function open(Order $order, User $buyer, string $reason, string $details, array $photos = []): string
    {
        if (! $this->canOpen($order, $buyer) || ! in_array($reason, self::REASONS, true)) {
            throw new DomainException(__('market.dispute.not_allowed'));
        }
        $id = (string) Str::ulid();
        DB::table('market_disputes')->insert(['id' => $id, 'order_id' => $order->id, 'opened_by' => $buyer->id, 'reason' => $reason, 'details' => mb_substr(trim($details), 0, 1000),
            'status' => 'open', 'respond_by' => now()->addDays(self::RESPOND_DAYS), 'created_at' => now(), 'updated_at' => now()]);
        $this->evidence($id, $buyer, $photos);
        $this->audit->record('market.dispute_opened', meta: ['order' => $order->id, 'reason' => $reason], actor: $buyer);
        event(new DisputeOpened($buyer, null, ['order' => $order->id, 'reason' => $reason]));
        $this->tell($order, 'trader', 'dispute_opened');

        return $id;
    }

    /** @param list<UploadedFile> $photos */
    public function respond(string $disputeId, Order $order, User $trader, string $response, array $photos = []): void
    {
        DB::table('market_disputes')->where('id', $disputeId)->whereIn('status', ['open', 'responded'])
            ->update(['trader_response' => mb_substr(trim($response), 0, 1000), 'status' => 'responded', 'updated_at' => now()]);
        $this->evidence($disputeId, $trader, $photos);
        $this->tell($order, 'buyer', 'dispute_responded');
    }

    /** The buyer says it's sorted (e.g. after a partial refund from the trader). */
    public function settle(string $disputeId, Order $order, User $buyer): void
    {
        DB::table('market_disputes')->where('id', $disputeId)->whereIn('status', ['open', 'responded'])
            ->update(['status' => 'settled', 'outcome' => 'settled', 'resolved_at' => now(), 'updated_at' => now()]);
        $this->audit->record('market.dispute_settled', meta: ['order' => $order->id], actor: $buyer);
        event(new DisputeResolved($buyer, null, ['order' => $order->id, 'outcome' => 'settled']));
        $this->tell($order, 'trader', 'dispute_settled');
    }

    /** KasiHub support decides. */
    public function decide(string $disputeId, Order $order, User $staff, string $outcome, ?int $cents, string $note): void
    {
        $dispute = DB::table('market_disputes')->where('id', $disputeId)->first();
        if ($dispute === null || in_array($dispute->status, ['settled', 'resolved'], true)) {
            throw new DomainException(__('market.dispute.closed'));
        }
        $paidOnline = in_array($order->getAttribute('payment_status'), ['paid', 'part_refunded'], true);
        if (in_array($outcome, ['refund_full', 'refund_part'], true)) {
            if (! $paidOnline) {
                throw new DomainException(__('market.dispute.cash_mediation')); // no money passed through KasiHub
            }
            $left = $order->total_cents - (int) $order->getAttribute('refunded_cents');
            $amount = $outcome === 'refund_full' ? $left : (int) $cents;
            $this->ordering->partialRefund($order, $staff, $amount, __('market.dispute.refund_reason'));
            $cents = $amount;
        } elseif (! in_array($outcome, ['no_refund', 'mediated'], true)) {
            throw new DomainException(__('market.dispute.closed'));
        }
        DB::table('market_disputes')->where('id', $disputeId)->update(['status' => 'resolved', 'outcome' => $outcome, 'outcome_cents' => $cents,
            'outcome_note' => mb_substr($note, 0, 500), 'decided_by' => $staff->id, 'resolved_at' => now(), 'updated_at' => now()]);
        $this->audit->record('market.dispute_decided', meta: ['order' => $order->id, 'outcome' => $outcome, 'cents' => $cents], actor: $staff);
        $buyer = User::query()->find($order->buyer_id);
        if ($buyer instanceof User) {
            event(new DisputeResolved($buyer, $staff->id, ['order' => $order->id, 'outcome' => $outcome]));
        }
        $this->tell($order, 'buyer', 'dispute_decided');
        $this->tell($order, 'trader', 'dispute_decided');
    }

    /** @param list<UploadedFile> $photos */
    private function evidence(string $disputeId, User $by, array $photos): void
    {
        foreach (array_slice($photos, 0, 4) as $photo) {
            $document = $this->vault->store($by, $photo, 'dispute_evidence');
            DB::table('market_dispute_files')->insert(['dispute_id' => $disputeId, 'document_id' => $document->id, 'added_by' => $by->id, 'created_at' => now()]);
        }
    }

    private function tell(Order $order, string $who, string $kind): void
    {
        $user = User::query()->find($who === 'buyer' ? $order->buyer_id : $order->shop()->value('opened_by'));
        if ($user instanceof User) {
            $this->notifier->send($user, new MarketNotification($kind, ['number' => $order->number], ($who === 'buyer' ? '/market/orders/' : '/market/shop/orders/').$order->id));
        }
    }
}
