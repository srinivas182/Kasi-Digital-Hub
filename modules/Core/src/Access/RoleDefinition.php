<?php

declare(strict_types=1);

namespace Modules\Core\Access;

/**
 * A role as declared in a module manifest.
 */
final readonly class RoleDefinition
{
    /**
     * @param  array<string, AccessLevel>  $access  Portal (module name) => level
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $module,
        public string $category,
        public string $scope,
        public bool $staff,
        public array $access,
    ) {}
}
