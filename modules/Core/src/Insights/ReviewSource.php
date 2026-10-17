<?php

declare(strict_types=1);

namespace Modules\Core\Insights;

/**
 * Extension point (ADR-029): items waiting for KasiHub review, gathered into one inbox. Tag with TAG.
 */
interface ReviewSource
{
    public const TAG = 'kasi.review_sources';

    /** @return list<array{type: string, id: string, title: string, reason: string, since: string, url: string, bulk: bool}> */
    public function waiting(): array;
}
