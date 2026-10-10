<?php

declare(strict_types=1);

namespace Modules\Core\Hubs;

/** Raised by the hub operations portal when someone's attendance at a session is recorded. */
final readonly class HubSessionAttended
{
    public function __construct(public string $sessionId, public string $userId) {}
}
