<?php

declare(strict_types=1);

namespace Modules\Market\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Core\Commerce\Fulfiller;
use Modules\Core\Enterprise\BusinessProducts;
use Modules\Core\Enterprise\BusinessSignals;
use Modules\Core\Enterprise\BusinessTransactions;
use Modules\Core\Enterprise\ProductStock;
use Modules\Core\Events\DocumentVerified;
use Modules\Core\Events\ModerationDecided;
use Modules\Core\Insights\EventIndicator;
use Modules\Core\Insights\RegistersIndicators;
use Modules\Core\Insights\ReviewSource;
use Modules\Core\Search\SearchRegistry;
use Modules\Market\Console\MarketHourlyCommand;
use Modules\Market\Listeners\ApplyModerationDecision;
use Modules\Market\Listeners\BankLetterVerified;
use Modules\Market\Search\MarketSearchSource;
use Modules\Market\Services\DisputesWaiting;
use Modules\Market\Services\MarketBusinessProducts;
use Modules\Market\Services\MarketProductStock;
use Modules\Market\Services\MarketSignals;
use Modules\Market\Services\MarketTransactions;
use Modules\Market\Services\OrderPayments;

final class MarketServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ProductStock::class, MarketProductStock::class); // S29: shared stock
        $this->app->bind(BusinessProducts::class, MarketBusinessProducts::class); // S30: for KasiBrand
        RegistersIndicators::add($this->app, [
            new EventIndicator('market.shops', ['market.shop.opened']),
            new EventIndicator('market.products', ['market.product.published']),
            new EventIndicator('market.orders_completed', ['market.order.completed']),
            new EventIndicator('market.reviews', ['market.review.added']),
            new EventIndicator('market.disputes_opened', ['market.dispute.opened']),
            new EventIndicator('market.disputes_resolved', ['market.dispute.resolved']),
            new EventIndicator('market.order_value', ['market.order.completed'], 'sum', null, 'total', 'cents'),
        ]);
        $this->app->tag([MarketSearchSource::class], SearchRegistry::TAG);
        $this->app->tag([OrderPayments::class], Fulfiller::TAG); // S26: payments confirmed
        $this->app->tag([MarketTransactions::class], BusinessTransactions::TAG); // S28: sales into KasiBiz
        $this->app->tag([MarketSignals::class], BusinessSignals::TAG); // S27
        $this->app->tag([DisputesWaiting::class], ReviewSource::TAG); // S27
    }

    public function boot(): void
    {
        Event::listen(ModerationDecided::class, ApplyModerationDecision::class);
        Event::listen(DocumentVerified::class, BankLetterVerified::class);
        if ($this->app->runningInConsole()) {
            $this->commands([MarketHourlyCommand::class]);
        }
    }
}
