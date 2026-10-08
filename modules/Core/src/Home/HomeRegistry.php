<?php

declare(strict_types=1);

namespace Modules\Core\Home;

use Illuminate\Contracts\Container\Container;
use Modules\Core\Identity\Models\User;

/**
 * Collects next steps from every portal's HomeContributor.
 */
final readonly class HomeRegistry
{
    public const TAG = 'kasi.home.contributors';

    public function __construct(private Container $container) {}

    /**
     * Open steps first (by priority), then completed ones.
     *
     * @return list<NextStep>
     */
    public function nextSteps(User $user): array
    {
        $steps = [];

        /** @var iterable<HomeContributor> $contributors */
        $contributors = $this->container->tagged(self::TAG);
        foreach ($contributors as $contributor) {
            array_push($steps, ...$contributor->nextSteps($user));
        }

        usort($steps, static fn (NextStep $a, NextStep $b): int => [$a->done, $a->priority] <=> [$b->done, $b->priority]);

        return $steps;
    }
}
