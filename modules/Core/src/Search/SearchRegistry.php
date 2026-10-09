<?php

declare(strict_types=1);

namespace Modules\Core\Search;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final readonly class SearchRegistry
{
    public const TAG = 'kasi.search.sources';

    public function __construct(private Container $container) {}

    /** @return array<string, SearchSource> */
    public function all(): array
    {
        $sources = [];
        foreach ($this->container->tagged(self::TAG) as $source) {
            if ($source instanceof SearchSource) {
                $sources[$source->type()] = $source;
            }
        }

        return $sources;
    }

    public function get(string $type): SearchSource
    {
        return $this->all()[$type] ?? throw new InvalidArgumentException("Unknown search type [{$type}].");
    }
}
