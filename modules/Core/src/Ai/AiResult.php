<?php

declare(strict_types=1);

namespace Modules\Core\Ai;

/**
 * What a feature gets back. When ok is false the feature falls back to "write it yourself";
 * reason says why: disabled | budget | unavailable | invalid.
 */
final readonly class AiResult
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public bool $ok,
        public array $data = [],
        public string $text = '',
        public ?string $reason = null,
    ) {}
}
