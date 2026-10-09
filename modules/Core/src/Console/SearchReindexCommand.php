<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use Illuminate\Console\Command;
use Modules\Core\Search\SearchService;

final class SearchReindexCommand extends Command
{
    protected $signature = 'kasi:search:reindex {type? : Only this type (e.g. hub, help, event)}';

    protected $description = 'Rebuild the search index from every portal\'s searchable content';

    public function handle(SearchService $search): int
    {
        foreach ($search->reindex($this->argument('type') ? (string) $this->argument('type') : null) as $type => $count) {
            $this->line("{$type}: {$count}");
        }

        return self::SUCCESS;
    }
}
