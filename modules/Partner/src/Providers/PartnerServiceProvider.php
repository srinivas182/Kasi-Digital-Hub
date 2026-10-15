<?php

declare(strict_types=1);

namespace Modules\Partner\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Core\Enterprise\SupportOffers;
use Modules\Core\Events\ModerationDecided;
use Modules\Core\Insights\EventIndicator;
use Modules\Core\Insights\HealthSignal;
use Modules\Core\Insights\RegistersIndicators;
use Modules\Partner\Console\PartnerDailyCommand;
use Modules\Partner\Models\Offer;
use Modules\Partner\Services\Offers;
use Modules\Partner\Services\ReferralsWaiting;

final class PartnerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([ReferralsWaiting::class], HealthSignal::TAG);
        // Outcome indicators for funders and coordinators (S20, ADR-028).
        RegistersIndicators::add($this->app, [
            new EventIndicator('partner.referrals', ['partner.referral.sent']),
            new EventIndicator('partner.support', ['partner.support.confirmed']),
            new EventIndicator('partner.support_value', ['partner.support.confirmed'], 'sum', null, 'value_cents', 'cents'),
            new EventIndicator('partner.trading_6m', ['partner.followup.answered'], 'events', static fn (array $p): bool => (int) ($p['months'] ?? 0) === 6 && (bool) ($p['trading'] ?? false)),
        ]);
        $this->app->bind(SupportOffers::class, Offers::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([PartnerDailyCommand::class]);
        }

        // KasiHub reviewers' decisions on offers and held messages.
        Event::listen(ModerationDecided::class, static function (ModerationDecided $e): void {
            if ($e->subjectType === 'partner_offer' && ($offer = Offer::query()->find($e->subjectId)) instanceof Offer) {
                app(Offers::class)->reviewed($offer, $e->decision, $e->reason);
            }
            if ($e->subjectType === 'partner_message' && $e->decision === 'approved') {
                DB::table('partner_referral_messages')->where('id', $e->subjectId)->update(['held' => false]);
            }
        });
    }
}
