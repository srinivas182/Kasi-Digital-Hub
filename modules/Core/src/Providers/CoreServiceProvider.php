<?php

declare(strict_types=1);

namespace Modules\Core\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Modules\Core\Access\AccessLevel;
use Modules\Core\Access\AccessResolver;
use Modules\Core\Access\RoleRegistry;
use Modules\Core\Console\GeographyImportCommand;
use Modules\Core\Console\RoleCommand;
use Modules\Core\Documents\Contracts\VirusScanner;
use Modules\Core\Documents\Drivers\ClamAvScanner;
use Modules\Core\Documents\Drivers\FakeVirusScanner;
use Modules\Core\Documents\RemindExpiringDocuments;
use Modules\Core\Events\IsPlatformEvent;
use Modules\Core\Http\Middleware\EnsureAccountReady;
use Modules\Core\Http\Middleware\EnsureAdult;
use Modules\Core\Http\Middleware\EnsurePortalAccess;
use Modules\Core\Identity\Contracts\BotCheck;
use Modules\Core\Identity\Contracts\SmsSender;
use Modules\Core\Identity\Drivers\FakeBotCheck;
use Modules\Core\Identity\Drivers\LogSmsSender;
use Modules\Core\Identity\Models\User;
use Modules\Core\Notifications\Contracts\WhatsAppSender;
use Modules\Core\Notifications\Drivers\LogWhatsAppSender;
use Modules\Core\Notifications\Listeners\SendCoreNotifications;
use Modules\Core\Notifications\ReleaseHeldNotifications;
use Modules\Core\Platform\GenerateDocsCommand;
use Modules\Core\Platform\Listeners\EventRecorder;

/**
 * Shared kernel services: identity drivers and middleware used by every portal.
 */
final class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RoleRegistry::class);

        $this->app->singleton(SmsSender::class, fn (): SmsSender => match (config('kasi.drivers.sms')) {
            'log' => new LogSmsSender,
            default => throw new InvalidArgumentException('Unknown SMS driver ['.config('kasi.drivers.sms').']. Real gateway drivers are added at deployment (S24).'),
        });

        $this->app->singleton(WhatsAppSender::class, fn (): WhatsAppSender => match (config('kasi.drivers.whatsapp')) {
            'log' => new LogWhatsAppSender,
            default => throw new InvalidArgumentException('Unknown WhatsApp driver ['.config('kasi.drivers.whatsapp').']. The provider driver is added at deployment (S24).'),
        });

        $this->app->singleton(VirusScanner::class, fn (): VirusScanner => match (config('kasi.drivers.virus_scan')) {
            'fake' => new FakeVirusScanner,
            'clamav' => new ClamAvScanner((string) config('kasi.drivers.clamav_socket')),
            default => throw new InvalidArgumentException('Unknown virus scan driver ['.config('kasi.drivers.virus_scan').'].'),
        });

        $this->app->singleton(BotCheck::class, fn (): BotCheck => match (config('kasi.drivers.bot_check')) {
            'fake' => new FakeBotCheck,
            default => throw new InvalidArgumentException('Unknown bot check driver ['.config('kasi.drivers.bot_check').'].'),
        });
    }

    public function boot(Router $router): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                RoleCommand::class, GeographyImportCommand::class, ReleaseHeldNotifications::class,
                RemindExpiringDocuments::class, GenerateDocsCommand::class,
            ]);
        }

        // Every platform event goes to the event log; core events trigger notifications.
        Event::listen(IsPlatformEvent::class, EventRecorder::class);
        Event::subscribe(SendCoreNotifications::class);

        $router->aliasMiddleware('account.ready', EnsureAccountReady::class);
        $router->aliasMiddleware('adult', EnsureAdult::class);
        $router->aliasMiddleware('portal', EnsurePortalAccess::class);

        // @can('portal', ['Work', 'assist']) / Gate::allows('portal', ['HubOps', 'manage'])
        Gate::define('portal', static fn (User $user, string $module, string $level = 'view'): bool => app(AccessResolver::class)->can($user, $module, AccessLevel::fromName($level)));

        // Per-IP route throttles for sign-in steps (a first line of defence; OtpService adds
        // per-number, per-device and range limits). The multiplier is raised only for automated tests.
        $multiplier = max(1, (int) config('kasi.identity.throttle_multiplier', 1));
        foreach (['auth-step' => 20, 'auth-sms' => 6, 'auth-signup' => 10] as $name => $perMinute) {
            RateLimiter::for($name, static fn (Request $request): Limit => Limit::perMinute($perMinute * $multiplier)->by($name.'|'.$request->ip()));
        }
    }
}
