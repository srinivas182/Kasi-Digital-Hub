<?php

declare(strict_types=1);

namespace Modules\Partner\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Core\Ai\Moderation\ModerationService;
use Modules\Core\Enterprise\BusinessFacts;
use Modules\Core\Enterprise\SupportOffers;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Structure\Models\Organisation;
use Modules\Partner\Models\Offer;

/**
 * Partner offers: reviewed by KasiHub for a partner's first offer and anything flagged; fees to apply
 * are refused outright (ADR-025).
 */
final readonly class Offers implements SupportOffers
{
    private const FEES = '/\b(application|registration|processing|admin(istration)?|joining|upfront)\s+fee|pay\s+(a\s+)?(fee|deposit)|fee\s+to\s+apply|R\s?\d+\s+to\s+(apply|register)\b/i';

    public function __construct(private ModerationService $moderation, private AuditLogger $audit, private Eligibility $eligibility) {}

    public static function chargesFee(string $text): bool
    {
        return preg_match(self::FEES, $text) === 1;
    }

    public function submit(Offer $offer, User $by): void
    {
        $partner = $offer->organisation;
        if (! $partner->isVerified()) {
            throw new DomainException(__('partner.offer.not_verified'));
        }
        $text = $offer->title."\n".$offer->description;
        if (self::chargesFee($text)) {
            throw new DomainException(__('partner.offer.no_fees'));
        }

        $first = ! Offer::query()->where('organisation_id', $partner->id)->whereIn('status', ['open', 'closed'])->exists();
        $check = $this->moderation->check($text, 'partner_offer', $offer->id, $by, extraReasons: $first ? ['First offer from this partner'] : []);
        $offer->forceFill(['status' => $check['verdict'] === 'flag' ? 'review' : 'open', 'status_reason' => $check['verdict'] === 'flag' ? implode('; ', $check['reasons']) : null])->save();
        $this->audit->record('partner.offer_'.$offer->status, meta: ['offer' => $offer->id], actor: $by);
    }

    public function reviewed(Offer $offer, string $decision, string $reason): void
    {
        if ($offer->status !== 'review') {
            return;
        }
        $offer->forceFill(['status' => $decision === 'approved' ? 'open' : 'rejected', 'status_reason' => $decision === 'approved' ? null : $reason])->save();
    }

    public function isOpen(Offer $offer): bool
    {
        return $offer->status === 'open' && $offer->opens_on->lte(now()) && ($offer->closes_on === null || $offer->closes_on->gte(now()->startOfDay()));
    }

    public function eligibleCount(string $businessId): int
    {
        $facts = app(BusinessFacts::class)->facts($businessId);
        if ($facts === null) {
            return 0;
        }

        return Offer::query()->where('status', 'open')->get()->filter(fn (Offer $o): bool => $this->isOpen($o) && $this->eligibility->check($o, $facts)['eligible'])->count();
    }

    public static function agreementAccepted(Organisation $partner): bool
    {
        return DB::table('partner_agreements')->where('organisation_id', $partner->id)->where('version', config('kasi.partner.agreement_version'))->exists();
    }
}
