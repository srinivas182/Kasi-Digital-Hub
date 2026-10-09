<?php

declare(strict_types=1);

namespace Modules\Core\Ai;

final readonly class AiResponse
{
    public function __construct(
        public string $text,
        public int $inputTokens,
        public int $outputTokens,
        public string $model,
    ) {}
}
