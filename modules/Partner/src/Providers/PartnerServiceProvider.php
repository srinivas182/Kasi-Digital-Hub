<?php

declare(strict_types=1);

namespace Modules\Partner\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Core\Enterprise\SupportOffers;
use Modules\Core\Events\ModerationDecided;
use Modules\Partner\Console\PartnerDailyCommand;
use Modules\Partner\Models\Offer;
use Modules\Partner\Services\Offers;

final class PartnerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
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
