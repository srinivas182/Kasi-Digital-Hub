<?php

declare(strict_types=1);

namespace Modules\Core\Ai\Embeddings;

/**
 * Text embeddings driver (ADR-004). Only short phrases such as skill names and job titles are
 * ever sent - never personal details.
 */
interface EmbeddingProvider
{
    /**
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embed(array $texts): array;

    public function model(): string;
}
