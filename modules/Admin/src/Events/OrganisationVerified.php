<?php

declare(strict_types=1);

namespace Modules\Admin\Events;

use Modules\Core\Events\PlatformEvent;
use Modules\Core\Structure\Models\Organisation;

final class OrganisationVerified extends PlatformEvent
{
    public const NAME = 'admin.organisation.verified';

    public const DESCRIPTION = "An organisation's details were checked and it was verified.";

    public function __construct(public readonly Organisation $organisation, public readonly string $by, public readonly ?string $reason = null) {}

    public function actorId(): string
    {
        return $this->by;
    }

    public function subject(): array
    {
        return ['organisation', $this->organisation->id];
    }

    public function payload(): array
    {
        return array_filter(['type' => $this->organisation->type, 'reason' => $this->reason]);
    }
}
