<?php

declare(strict_types=1);

namespace Modules\Core\Events;

final class HubPackageChanged extends PlatformEvent
{
    public const NAME = 'core.hub.package_changed';

    public const DESCRIPTION = 'The portals a hub may deliver changed (package, add-on or switch-off).';

    /**
     * @param  list<string>  $modules  Portals now switched on
     */
    public function __construct(public readonly string $hub, public readonly string $change, public readonly array $modules, public readonly ?string $changedBy = null) {}

    public function hubId(): string
    {
        return $this->hub;
    }

    public function actorId(): ?string
    {
        return $this->changedBy;
    }

    public function subject(): array
    {
        return ['hub', $this->hub];
    }

    public function payload(): array
    {
        return ['change' => $this->change, 'modules' => $this->modules];
    }
}
