<?php

declare(strict_types=1);

namespace App\Support\Navigation;

use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleRegistry;

/**
 * Builds the portal switcher and menus from module manifests.
 *
 * Sprint 1: every enabled module with navigation is listed. From Sprint 3 the
 * list is filtered by the user's roles and their hub's package (entitlements).
 */
final readonly class NavigationBuilder
{
    private const GROUP_ORDER = ['front' => 0, 'service' => 1, 'operations' => 2, 'national' => 3];

    public function __construct(private ModuleRegistry $registry) {}

    /**
     * @return list<array{module: string, title: string, group: string, icon: string|null, href: string|null, items: list<array{label: string, href: string, icon: string|null}>}>
     */
    public function portals(): array
    {
        $modules = array_filter(
            $this->registry->enabled(),
            static fn (ModuleManifest $module): bool => $module->nav !== null && $module->group !== 'core',
        );

        usort($modules, static fn (ModuleManifest $a, ModuleManifest $b): int => (self::GROUP_ORDER[$a->group] ?? 9) <=> (self::GROUP_ORDER[$b->group] ?? 9));

        return array_map(static function (ModuleManifest $module): array {
            /** @var array{icon: string|null, href: string|null, items: list<array{label: string, href: string, icon: string|null, permission: string|null}>} $nav */
            $nav = $module->nav;

            return [
                'module' => $module->name,
                'title' => $module->title,
                'group' => $module->group,
                'icon' => $nav['icon'],
                'href' => $nav['href'],
                'items' => array_map(
                    static fn (array $item): array => ['label' => $item['label'], 'href' => $item['href'], 'icon' => $item['icon']],
                    $nav['items'],
                ),
            ];
        }, $modules);
    }
}
