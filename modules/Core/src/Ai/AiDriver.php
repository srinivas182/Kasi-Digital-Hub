<?php

declare(strict_types=1);

namespace Modules\Core\Ai;

/**
 * A language-model provider (ADR-004 drivers). Throws AiUnavailable when the provider fails.
 */
interface AiDriver
{
    public function complete(AiRequest $request): AiResponse;
}
