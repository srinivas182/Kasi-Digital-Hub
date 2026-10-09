<?php

declare(strict_types=1);

namespace Modules\Core\Search;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Keeps the search index up to date after content changes (queue: search). */
final class RefreshSearchDocument implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly string $type, public readonly string $ref)
    {
        $this->onQueue('search');
    }

    public function handle(SearchService $search): void
    {
        $search->refresh($this->type, $this->ref);
    }
}
