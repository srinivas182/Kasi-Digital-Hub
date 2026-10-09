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
use Modules\Core\Ai\AiDriver;
use Modules\Core\Ai\Drivers\AnthropicDriver;
use Modules\Core\Ai\Drivers\FakeAiDriver;
use Modules\Core\Ai\Drivers\OpenAiDriver;
use Modules\Core\Ai\PromptRegistry;
use Modules\Core\Console\AiLockCommand;
use Modules\Core\Console\AiPurgeCommand;
use Modules\Core\Console\GeographyImportCommand;
use Modules\Core\Console\RoleCommand;
use Modules\Core\Console\SearchReindexCommand;
use Modules\Core\Documents\Contracts\VirusScanner;
use Modules\Core\Documents\Drivers\ClamAvScanner;
use Modules\Core\Documents\Drivers\FakeVirusScanner;
use Modules\Core\Documents\Generation\FakePdfRenderer;
use Modules\Core\Documents\Generation\GotenbergRenderer;
use Modules\Core\Documents\Generation\PdfRenderer;
use Modules\Core\Documents\RemindExpiringDocuments;
use Modules\Core\Events\IsPlatformEvent;
use Modules\Core\Home\HomeRegistry;
use Modules\Core\Home\ProfileHomeContributor;
use Modules\Core\Http\Middleware\EnsureAccountReady;
use Modules\Core\Http\Middleware\EnsureAdult;
use Modules\Core\Http\Middleware\EnsurePermission;
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
use Modules\Core\Search\Engines\DatabaseSearchEngine;
use Modules\Core\Search\Engines\MeilisearchEngine;
use Modules\Core\Search\SearchEngine;

/**
 * Shared kernel services: identity drivers and middleware used by every portal.
 */
final class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RoleRegistry::class);
        $this->app->singleton(PromptRegistry::class);

        // AI, search and PDF drivers (S8, ADR-016).
        $this->app->singleton(AiDriver::class, fn (): AiDriver => match (config('kasi.drivers.ai')) {
            'fake' => new FakeAiDriver,
            'anthropic' => new AnthropicDriver,
            'openai' => new OpenAiDriver,
            default => throw new InvalidArgumentException('Unknown AI driver ['.config('kasi.drivers.ai').'].'),
        });
        $this->app->singleton(SearchEngine::class, fn (): SearchEngine => match (config('kasi.drivers.search')) {
            'database' => new DatabaseSearchEngine,
            'meilisearch' => new MeilisearchEngine,
            default => throw new InvalidArgumentException('Unknown search driver ['.config('kasi.drivers.search').'].'),
        });
        $this->app->singleton(PdfRenderer::class, fn (): PdfRenderer => match (config('kasi.drivers.pdf')) {
            'fake' => new FakePdfRenderer,
            'gotenberg' => new GotenbergRenderer,
            default => throw new InvalidArgumentException('Unknown PDF driver ['.config('kasi.drivers.pdf').'].'),
        });
        $this->app->tag([ProfileHomeContributor::class], HomeRegistry::TAG);

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
                AiLockCommand::class, AiPurgeCommand::class, SearchReindexCommand::class,
            ]);
        }

        // Every platform event goes to the event log; core events trigger notifications.
        Event::listen(IsPlatformEvent::class, EventRecorder::class);
        Event::subscribe(SendCoreNotifications::class);

        $router->aliasMiddleware('account.ready', EnsureAccountReady::class);
        $router->aliasMiddleware('adult', EnsureAdult::class);
        $router->aliasMiddleware('portal', EnsurePortalAccess::class);
        $router->aliasMiddleware('permission', EnsurePermission::class);

        // @can('portal', ['Work', 'assist']) / Gate::allows('portal', ['HubOps', 'manage'])
        Gate::define('portal', static fn (User $user, string $module, string $level = 'view'): bool => app(AccessResolver::class)->can($user, $module, AccessLevel::fromName($level)));
        // Gate::allows('permission', 'admin.documents.verify')
        Gate::define('permission', static fn (User $user, string $permission): bool => app(AccessResolver::class)->hasPermission($user, $permission));

        // Per-IP route throttles for sign-in steps (a first line of defence; OtpService adds
        // per-number, per-device and range limits). The multiplier is raised only for automated tests.
        $multiplier = max(1, (int) config('kasi.identity.throttle_multiplier', 1));
        foreach (['auth-step' => 20, 'auth-sms' => 6, 'auth-signup' => 10] as $name => $perMinute) {
            RateLimiter::for($name, static fn (Request $request): Limit => Limit::perMinute($perMinute * $multiplier)->by($name.'|'.$request->ip()));
        }
    }
}
