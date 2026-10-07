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
     * @param  list<string>  $roles
     * @param  list<string>  $permissions
     * @param  list<string>  $publishes
     * @param  list<string>  $consumes
     * @param  list<string>  $impactMetrics
     * @param  list<string>  $demoSeeders  Demo seeder class names
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
            roles: self::stringList($data['roles'] ?? []),
            permissions: self::stringList($data['permissions'] ?? []),
            publishes: self::stringList($events['publishes'] ?? []),
            consumes: self::stringList($events['consumes'] ?? []),
            impactMetrics: self::stringList($data['impact_metrics'] ?? []),
            demoSeeders: self::stringList($data['demo_seeders'] ?? []),
        );
    }

    public function path(string $relative = ''): string
    {
        return $relative === '' ? $this->path : $this->path.DIRECTORY_SEPARATOR.ltrim($relative, '/\\');
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
