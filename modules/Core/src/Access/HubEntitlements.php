<?php

declare(strict_types=1);

namespace Modules\Core\Access;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Events\HubPackageChanged;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Structure\Models\Hub;
use Modules\Core\Structure\Models\HubModule;

/**
 * Which portals each hub may deliver locally, from its package plus add-ons.
 * Every change is audited and invalidates cached access.
 */
final readonly class HubEntitlements
{
    public const VERSION_KEY = 'kasi:entitlements:version';

    public function __construct(private AuditLogger $audit) {}

    /** @return list<string> */
    public static function packages(): array
    {
        /** @var array<string, list<string>> $packages */
        $packages = config('kasi.hubs.packages');

        return array_keys($packages);
    }

    /** @return list<string> */
    public static function packageModules(string $package): array
    {
        /** @var array<string, list<string>> $packages */
        $packages = config('kasi.hubs.packages');

        return $packages[$package] ?? throw new InvalidArgumentException("Unknown package [{$package}].");
    }

    public static function isHubDelivered(string $module): bool
    {
        return in_array($module, (array) config('kasi.hubs.delivered_modules'), true);
    }

    /** Switch a hub to a package. Add-ons are kept; package modules are synced. */
    public function applyPackage(Hub $hub, string $package, ?User $by = null): void
    {
        $modules = self::packageModules($package);

        DB::transaction(function () use ($hub, $package, $modules, $by): void {
            $hub->forceFill(['package' => $package])->save();

            HubModule::query()->where('hub_id', $hub->id)->where('source', 'package')->whereNotIn('module', $modules)->delete();

            foreach ($modules as $module) {
                HubModule::query()->updateOrCreate(
                    ['hub_id' => $hub->id, 'module' => $module],
                    ['enabled' => true, 'source' => 'package', 'updated_by' => $by?->id],
                );
            }
        });

        $this->audit->record('hub.package_applied', actor: $by, meta: ['hub' => $hub->code, 'package' => $package]);
        $this->bump();
        event(new HubPackageChanged($hub->id, 'package:'.$package, $this->modulesFor($hub), $by?->id));
    }

    public function addOn(Hub $hub, string $module, ?User $by = null): void
    {
        if (! self::isHubDelivered($module)) {
            throw new InvalidArgumentException("[{$module}] is not delivered by hubs.");
        }

        HubModule::query()->updateOrCreate(
            ['hub_id' => $hub->id, 'module' => $module],
            ['enabled' => true, 'source' => HubModule::query()->where('hub_id', $hub->id)->where('module', $module)->value('source') ?? 'addon', 'updated_by' => $by?->id],
        );

        $this->audit->record('hub.module_enabled', actor: $by, meta: ['hub' => $hub->code, 'module' => $module]);
        $this->bump();
        event(new HubPackageChanged($hub->id, 'enabled:'.$module, $this->modulesFor($hub), $by?->id));
    }

    public function disable(Hub $hub, string $module, ?User $by = null): void
    {
        HubModule::query()->where('hub_id', $hub->id)->where('module', $module)->update(['enabled' => false, 'updated_by' => $by?->id]);

        $this->audit->record('hub.module_disabled', actor: $by, meta: ['hub' => $hub->code, 'module' => $module]);
        $this->bump();
        event(new HubPackageChanged($hub->id, 'disabled:'.$module, $this->modulesFor($hub), $by?->id));
    }

    public function enabled(Hub|string $hub, string $module): bool
    {
        return in_array($module, $this->modulesFor($hub), true);
    }

    /** @return list<string> */
    public function modulesFor(Hub|string $hub): array
    {
        $id = $hub instanceof Hub ? $hub->id : $hub;

        /** @var list<string> */
        return HubModule::query()->where('hub_id', $id)->where('enabled', true)->pluck('module')->all();
    }

    private function bump(): void
    {
        Cache::forever(self::VERSION_KEY, (int) Cache::get(self::VERSION_KEY, 0) + 1);
    }
}
