<?php

declare(strict_types=1);

namespace Modules\Work\Matching;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Recalculates matches after a profile or advert changes (queue: matching). */
final class RefreshMatches implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 60;

    /** @param 'seeker'|'listing' $kind */
    public function __construct(public readonly string $kind, public readonly string $id)
    {
        $this->onQueue('matching');
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return $this->kind.':'.$this->id;
    }

    public function handle(MatchIndex $index): void
    {
        $this->kind === 'seeker' ? $index->refreshSeeker($this->id) : $index->refreshListing($this->id);
    }
}
