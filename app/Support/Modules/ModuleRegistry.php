<?php

declare(strict_types=1);

namespace App\Support\Modules;

use InvalidArgumentException;
use JsonException;

/**
 * Discovers modules under `modules/`, validates their manifests and returns
 * them in dependency order (dependencies before dependants).
 */
final class ModuleRegistry
{
    /** @var array<string, ModuleManifest>|null */
    private ?array $modules = null;

    /**
     * @param  list<string>  $disabled  Module names switched off in config/kasi.php
     */
    public function __construct(
        private readonly string $modulesPath,
        private readonly array $disabled = [],
    ) {}

    /**
     * All discovered modules (enabled and disabled), keyed by name, in dependency order.
     *
     * @return array<string, ModuleManifest>
     */
    public function all(): array
    {
        return $this->modules ??= $this->sort($this->discover());
    }

    /**
     * Enabled modules only, in dependency order.
     *
     * @return array<string, ModuleManifest>
     */
    public function enabled(): array
    {
        return array_filter(
            $this->all(),
            fn (ModuleManifest $module): bool => ! in_array($module->name, $this->disabled, true),
        );
    }

    public function find(string $name): ?ModuleManifest
    {
        return $this->all()[$name] ?? null;
    }

    public function isEnabled(string $name): bool
    {
        return array_key_exists($name, $this->enabled());
    }

    /**
     * @return array<string, ModuleManifest>
     */
    private function discover(): array
    {
        $modules = [];
        $files = glob($this->modulesPath.'/*/module.json') ?: [];
        sort($files);

        foreach ($files as $file) {
            $path = dirname($file);

            try {
                $data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                throw new InvalidArgumentException("Invalid JSON in {$file}: {$e->getMessage()}", 0, $e);
            }

            if (! is_array($data)) {
                throw new InvalidArgumentException("Manifest {$file} must be a JSON object.");
            }

            /** @var array<string, mixed> $data */
            $manifest = ModuleManifest::fromArray($data, $path);

            if (isset($modules[$manifest->name])) {
                throw new InvalidArgumentException("Duplicate module name [{$manifest->name}].");
            }

            $modules[$manifest->name] = $manifest;
        }

        return $modules;
    }

    /**
     * Topologically sort modules so dependencies are registered first.
     *
     * @param  array<string, ModuleManifest>  $modules
     * @return array<string, ModuleManifest>
     */
    private function sort(array $modules): array
    {
        $sorted = [];
        $state = [];

        $visit = function (string $name, array $trail) use (&$visit, &$sorted, &$state, $modules): void {
            if (($state[$name] ?? null) === 'done') {
                return;
            }

            if (($state[$name] ?? null) === 'visiting') {
                throw new InvalidArgumentException('Circular module dependency: '.implode(' -> ', [...$trail, $name]));
            }

            if (! isset($modules[$name])) {
                throw new InvalidArgumentException('Module ['.end($trail)."] depends on unknown module [{$name}].");
            }

            $state[$name] = 'visiting';

            foreach ($modules[$name]->dependsOn as $dependency) {
                $visit($dependency, [...$trail, $name]);
            }

            $state[$name] = 'done';
            $sorted[$name] = $modules[$name];
        };

        foreach (array_keys($modules) as $name) {
            $visit($name, []);
        }

        return $sorted;
    }
}
