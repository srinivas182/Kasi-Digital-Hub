<?php

declare(strict_types=1);

namespace Modules\Core\Documents\Generation;

/** Turns an HTML page into PDF bytes (ADR-004 driver: gotenberg in production, fake in CI). */
interface PdfRenderer
{
    public function render(string $html): string;
}
