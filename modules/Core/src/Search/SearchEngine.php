<?php

declare(strict_types=1);

namespace Modules\Core\Search;

/** Search driver (ADR-004): database for small installs and CI, Meilisearch in production. */
interface SearchEngine
{
    /** @param list<SearchDocument> $documents */
    public function upsert(array $documents): void;

    public function delete(string $type, string $ref): void;

    public function flush(string $type): void;

    /**
     * @param  list<string>  $visibilities
     * @return list<array{type: string, ref: string, title: string, body: string, url: string}>
     */
    public function search(string $query, array $visibilities, int $limit = 30): array;
}
