<?php

declare(strict_types=1);

namespace Modules\Core\Access;

use App\Support\Modules\ModuleRegistry;
use InvalidArgumentException;

/**
 * Every role available on the platform, collected from enabled modules' manifests
 * (Portal SDK: new portals bring their own roles).
 */
final class RoleRegistry
{
    /** @var array<string, RoleDefinition>|null */
    private ?array $roles = null;

    public function __construct(private readonly ModuleRegistry $modules) {}

    /**
     * @return array<string, RoleDefinition>
     */
    public function all(): array
    {
        if ($this->roles !== null) {
            return $this->roles;
        }

        $roles = [];

        foreach ($this->modules->enabled() as $module) {
            foreach ($module->roles as $role) {
                if (isset($roles[$role['key']])) {
                    throw new InvalidArgumentException("Role [{$role['key']}] is declared by more than one module.");
                }

                $roles[$role['key']] = new RoleDefinition(
                    key: $role['key'],
                    label: $role['label'],
                    module: $module->name,
                    category: $role['category'],
                    scope: $role['scope'],
                    staff: $role['staff'],
                    access: array_map(AccessLevel::fromName(...), $role['access']),
                );
            }
        }

        return $this->roles = $roles;
    }

    public function find(string $key): ?RoleDefinition
    {
        return $this->all()[$key] ?? null;
    }

    public function get(string $key): RoleDefinition
    {
        return $this->find($key) ?? throw new InvalidArgumentException("Unknown role [{$key}].");
    }
}
