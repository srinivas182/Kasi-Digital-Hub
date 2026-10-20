<?php

declare(strict_types=1);

namespace Modules\Market\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Core\Commerce\Fulfiller;
use Modules\Core\Events\DocumentVerified;
use Modules\Core\Events\ModerationDecided;
use Modules\Core\Insights\EventIndicator;
use Modules\Core\Insights\RegistersIndicators;
use Modules\Core\Search\SearchRegistry;
use Modules\Market\Console\MarketHourlyCommand;
use Modules\Market\Listeners\ApplyModerationDecision;
use Modules\Market\Listeners\BankLetterVerified;
use Modules\Market\Search\MarketSearchSource;
use Modules\Market\Services\OrderPayments;

final class MarketServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        RegistersIndicators::add($this->app, [
            new EventIndicator('market.shops', ['market.shop.opened']),
            new EventIndicator('market.products', ['market.product.published']),
            new EventIndicator('market.orders_completed', ['market.order.completed']),
            new EventIndicator('market.order_value', ['market.order.completed'], 'sum', null, 'total', 'cents'),
        ]);
        $this->app->tag([MarketSearchSource::class], SearchRegistry::TAG);
        $this->app->tag([OrderPayments::class], Fulfiller::TAG); // S26: payments confirmed
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
