<?php

declare(strict_types=1);

namespace Modules\Commercial\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Commercial\Console\CommercialDailyCommand;
use Modules\Commercial\Payments\FakeGateway;
use Modules\Commercial\Payments\PaymentGateway;
use Modules\Commercial\Payments\PaystackGateway;

final class CommercialServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentGateway::class, static fn (): PaymentGateway => match (config('kasi.commercial.payments_driver')) {
            'paystack' => new PaystackGateway((string) config('kasi.commercial.paystack_secret')),
            default => new FakeGateway,
        });
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'commercial');
        if ($this->app->runningInConsole()) {
            $this->commands([CommercialDailyCommand::class]);
        }
    }
}
