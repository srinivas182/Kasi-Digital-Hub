<?php

declare(strict_types=1);

namespace Modules\Core\Home;

use Illuminate\Contracts\Container\Container;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\Hub;

/**
 * Collects hub activity from every portal that provides it (see HubActivityProvider).
 */
final readonly class HubActivity
{
    public const TAG = 'kasi.hub.activity';

    public function __construct(private Container $container) {}

    /** @return list<array<string, mixed>> */
    public function publicItems(Hub $hub): array
    {
        return $this->collect(static fn (HubActivityProvider $p): array => $p->publicItems($hub));
    }

    /** @return list<array<string, mixed>> */
    public function personalItems(User $user): array
    {
        return $this->collect(static fn (HubActivityProvider $p): array => $p->personalItems($user));
    }

    /**
     * @param  callable(HubActivityProvider): list<array<string, mixed>>  $pick
     * @return list<array<string, mixed>>
     */
    private function collect(callable $pick): array
    {
        $items = [];
        foreach ($this->container->tagged(self::TAG) as $provider) {
            if ($provider instanceof HubActivityProvider) {
                array_push($items, ...$pick($provider));
            }
        }

        usort($items, static fn (array $a, array $b): int => strcmp((string) $a['startsAt'], (string) $b['startsAt']));

        return $items;
    }
}
