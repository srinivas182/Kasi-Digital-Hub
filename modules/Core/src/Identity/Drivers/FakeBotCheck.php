<?php

declare(strict_types=1);

namespace Modules\Core\Identity\Drivers;

use Modules\Core\Identity\Contracts\BotCheck;

final class FakeBotCheck implements BotCheck
{
    public function passes(?string $token, ?string $ip): bool
    {
        return true;
    }
}
