<?php

declare(strict_types=1);

namespace Modules\Core\Search;

/**
 * One searchable item. visibility: public (everyone), members (signed-in adults),
 * learners (signed-in, including 16-17 year olds).
 */
final readonly class SearchDocument
{
    /** @param array<string, string|int|float|bool|null> $meta */
    public function __construct(
        public string $type,
        public string $ref,
        public string $title,
        public string $body,
        public string $url,
        public string $visibility = 'public',
        public ?string $hubId = null,
        public array $meta = [],
    ) {}
}
