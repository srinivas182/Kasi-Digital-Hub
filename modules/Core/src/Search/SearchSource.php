<?php

declare(strict_types=1);

namespace Modules\Core\Search;

/**
 * Portal SDK extension point: a portal makes its content searchable by implementing this and
 * tagging it with SearchRegistry::TAG. Never index personal details of other people.
 */
interface SearchSource
{
    public function type(): string;

    /** @return iterable<SearchDocument> */
    public function all(): iterable;

    /** The current document, or null when it should no longer be found. */
    public function find(string $ref): ?SearchDocument;
}
