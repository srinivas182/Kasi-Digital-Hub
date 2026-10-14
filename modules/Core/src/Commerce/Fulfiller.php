<?php

declare(strict_types=1);

namespace Modules\Core\Commerce;

/**
 * Extension point: a portal delivers what was paid for (e.g. KasiWork extra adverts) once the commercial
 * console confirms payment, and takes it back on a full refund. Tag implementations with TAG.
 */
interface Fulfiller
{
    public const TAG = 'kasi.fulfillers';

    /** @return list<string> product keys this portal fulfils */
    public function products(): array;

    /** @param array<string, mixed> $meta */
    public function fulfil(string $productKey, int $quantity, array $meta): void;

    /** @param array<string, mixed> $meta */
    public function revoke(string $productKey, int $quantity, array $meta): void;
}
