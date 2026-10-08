<?php

declare(strict_types=1);

namespace App\Support\Navigation;

use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleRegistry;
use Modules\Core\Access\AccessResolver;
use Modules\Core\Identity\Models\User;

/**
 * Builds the portal switcher and menus from module manifests.
 *
 * Only portals the person can open are listed: the hub home for every signed-in
 * person, and each other portal where one of their roles grants access (hub-scoped
 * roles also need the hub's package to include the portal). Guests see none.
 */
final readonly class NavigationBuilder
{
    private const GROUP_ORDER = ['front' => 0, 'service' => 1, 'operations' => 2, 'national' => 3];

    public function __construct(private ModuleRegistry $registry, private AccessResolver $access) {}

    /**
     * @return list<array{module: string, title: string, group: string, icon: string|null, href: string|null, items: list<array{label: string, href: string, icon: string|null}>}>
     */
    public function portals(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $levels = $this->access->levels($user);

        return $this->build(static fn (ModuleManifest $module): bool => $module->name === 'Hub' || isset($levels[$module->name]));
    }

    /**
     * Every portal, unfiltered - for the internal UI kit previews only.
     *
     * @return list<array{module: string, title: string, group: string, icon: string|null, href: string|null, items: list<array{label: string, href: string, icon: string|null}>}>
     */
    public function allPortals(): array
    {
        return $this->build(static fn (): bool => true);
    }

    /**
     * @param  callable(ModuleManifest): bool  $include
     * @return list<array{module: string, title: string, group: string, icon: string|null, href: string|null, items: list<array{label: string, href: string, icon: string|null}>}>
     */
    private function build(callable $include): array
    {
        $modules = array_filter(
            $this->registry->enabled(),
            static fn (ModuleManifest $module): bool => $module->nav !== null && $module->group !== 'core' && $include($module),
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
