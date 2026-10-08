<?php

declare(strict_types=1);

namespace Modules\Core\Documents\Contracts;

/**
 * Scans uploaded files (ADR-004). ClamAV in production; the fake scanner in demo and CI.
 */
interface VirusScanner
{
    /** True when the file is clean. */
    public function isClean(string $absolutePath): bool;
}
