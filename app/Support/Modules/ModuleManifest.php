<?php

declare(strict_types=1);

namespace App\Support\Modules;

use InvalidArgumentException;

/**
 * Immutable view of a module's `module.json` manifest.
 *
 * Every portal on the platform is a module under `modules/<Name>`. The manifest
 * declares how the module plugs into the shared core: its routes, navigation,
 * roles, permissions, hub entitlement, events and demo seeders. This is the
 * foundation of the Portal SDK - new portals are added by dropping in a new
 * module with a valid manifest, without touching existing modules.
 */
final readonly class ModuleManifest
{
    public const GROUPS = ['core', 'front', 'service', 'operations', 'national'];

    public const RELEASES = ['r1', 'later'];

    /**
     * @param  list<string>  $dependsOn
     * @param  list<string>  $providers  Service provider class names
     * @param  list<array{key: string, label: string, category: string, scope: string, staff: bool, access: array<string, string>, permissions: list<string>}>  $roles
     * @param  list<string>  $permissions
     * @param  list<string>  $publishes
     * @param  list<string>  $consumes
     * @param  list<string>  $impactMetrics
     * @param  list<string>  $demoSeeders  Demo seeder class names
     * @param  array{icon: string|null, href: string|null, items: list<array{label: string, href: string, icon: string|null, permission: string|null}>}|null  $nav
     */
    public function __construct(
        public string $name,
        public string $slug,
        public string $title,
        public string $description,
        public string $group,
        public string $release,
        public string $version,
        public string $path,
        public array $dependsOn,
        public array $providers,
        public bool $webRoutes,
        public bool $apiRoutes,
        public ?string $entitlement,
        public array $roles,
        public array $permissions,
        public array $publishes,
        public array $consumes,
        public array $impactMetrics,
        public array $demoSeeders,
        public ?array $nav = null,
    ) {}

    /**
     * Build a manifest from decoded JSON, validating required fields.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, string $path): self
    {
        foreach (['name', 'slug', 'title', 'group', 'release', 'version'] as $key) {
            if (! isset($data[$key]) || ! is_string($data[$key]) || $data[$key] === '') {
                throw new InvalidArgumentException("Module manifest at {$path} is missing required string field [{$key}].");
            }
        }

        $name = $data['name'];

        if (basename($path) !== $name) {
            throw new InvalidArgumentException("Module [{$name}] must live in a folder named [{$name}], found [".basename($path).'].');
        }

        if (! in_array($data['group'], self::GROUPS, true)) {
            throw new InvalidArgumentException("Module [{$name}] has invalid group [{$data['group']}].");
        }

        if (! in_array($data['release'], self::RELEASES, true)) {
            throw new InvalidArgumentException("Module [{$name}] has invalid release [{$data['release']}].");
        }

        $routes = is_array($data['routes'] ?? null) ? $data['routes'] : [];
        $events = is_array($data['events'] ?? null) ? $data['events'] : [];
        $entitlement = $data['entitlement'] ?? null;

        return new self(
            name: $name,
            slug: $data['slug'],
            title: $data['title'],
            description: is_string($data['description'] ?? null) ? $data['description'] : '',
            group: $data['group'],
            release: $data['release'],
            version: $data['version'],
            path: $path,
            dependsOn: self::stringList($data['depends_on'] ?? []),
            providers: self::stringList($data['providers'] ?? []),
            webRoutes: (bool) ($routes['web'] ?? false),
            apiRoutes: (bool) ($routes['api'] ?? false),
            entitlement: is_string($entitlement) ? $entitlement : null,
            roles: self::roles($data['roles'] ?? [], $name),
            permissions: self::stringList($data['permissions'] ?? []),
            publishes: self::stringList($events['publishes'] ?? []),
            consumes: self::stringList($events['consumes'] ?? []),
            impactMetrics: self::stringList($data['impact_metrics'] ?? []),
            demoSeeders: self::stringList($data['demo_seeders'] ?? []),
            nav: self::navigation($data['nav'] ?? null, $name),
        );
    }

    public function path(string $relative = ''): string
    {
        return $relative === '' ? $this->path : $this->path.DIRECTORY_SEPARATOR.ltrim($relative, '/\\');
    }

    public const ROLE_SCOPES = ['self', 'organisation', 'hub', 'municipality', 'province', 'national'];

    public const ACCESS_LEVELS = ['view', 'use', 'assist', 'manage'];

    /**
     * Roles the module defines. Each role grants access levels to portals (by module name).
     *
     * @return list<array{key: string, label: string, category: string, scope: string, staff: bool, access: array<string, string>, permissions: list<string>}>
     */
    private static function roles(mixed $roles, string $module): array
    {
        if (! is_array($roles)) {
            return [];
        }

        $parsed = [];

        foreach ($roles as $role) {
            if (! is_array($role) || ! is_string($role['key'] ?? null) || ! is_string($role['label'] ?? null)) {
                throw new InvalidArgumentException("Module [{$module}] has a role without a string key and label.");
            }

            $scope = $role['scope'] ?? null;
            if (! in_array($scope, self::ROLE_SCOPES, true)) {
                throw new InvalidArgumentException("Role [{$role['key']}] in module [{$module}] has an invalid scope.");
            }

            $access = [];
            foreach (is_array($role['access'] ?? null) ? $role['access'] : [] as $portal => $level) {
                if (! is_string($portal) || ! in_array($level, self::ACCESS_LEVELS, true)) {
                    throw new InvalidArgumentException("Role [{$role['key']}] in module [{$module}] has an invalid access level.");
                }
                $access[$portal] = $level;
            }

            $parsed[] = [
                'key' => $role['key'],
                'label' => $role['label'],
                'category' => is_string($role['category'] ?? null) ? $role['category'] : 'individual',
                'scope' => $scope,
                'staff' => (bool) ($role['staff'] ?? false),
                'access' => $access,
                // Fine-grained permissions inside portals, e.g. "admin.documents.verify"; "admin.*" = all in that portal.
                'permissions' => self::stringList($role['permissions'] ?? []),
            ];
        }

        return $parsed;
    }

    /**
     * Navigation declared by the module: its entry point and menu items. Labels are translation keys.
     *
     * @return array{icon: string|null, href: string|null, items: list<array{label: string, href: string, icon: string|null, permission: string|null}>}|null
     */
    private static function navigation(mixed $nav, string $module): ?array
    {
        if (! is_array($nav)) {
            return null;
        }

        $items = [];

        foreach (is_array($nav['items'] ?? null) ? $nav['items'] : [] as $item) {
            if (! is_array($item) || ! is_string($item['label'] ?? null) || ! is_string($item['href'] ?? null)) {
                throw new InvalidArgumentException("Module [{$module}] has a nav item without a string label and href.");
            }

            $items[] = [
                'label' => $item['label'],
                'href' => $item['href'],
                'icon' => is_string($item['icon'] ?? null) ? $item['icon'] : null,
                'permission' => is_string($item['permission'] ?? null) ? $item['permission'] : null,
            ];
        }

        return [
            'icon' => is_string($nav['icon'] ?? null) ? $nav['icon'] : null,
            'href' => is_string($nav['href'] ?? null) ? $nav['href'] : null,
            'items' => $items,
        ];
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn (mixed $item): bool => is_string($item) && $item !== ''));
    }
}
