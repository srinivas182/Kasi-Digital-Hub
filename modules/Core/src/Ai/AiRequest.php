<?php

declare(strict_types=1);

namespace Modules\Core\Ai;

/**
 * One call to a language model. The system prompt is ours; the user content is data only.
 */
final readonly class AiRequest
{
    /**
     * @param  list<string>  $outputFields  When set, the model must answer with a JSON object with these keys.
     */
    public function __construct(
        public string $model,
        public string $system,
        public string $user,
        public int $maxTokens,
        public array $outputFields = [],
    ) {}
}
