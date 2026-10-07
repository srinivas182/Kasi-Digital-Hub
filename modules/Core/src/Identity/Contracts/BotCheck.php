<?php

declare(strict_types=1);

namespace Modules\Core\Identity\Contracts;

/**
 * Invisible human check in front of code requests (protects against SMS pumping).
 * The fake driver always passes; a real provider is chosen at deployment.
 */
interface BotCheck
{
    public function passes(?string $token, ?string $ip): bool;
}
