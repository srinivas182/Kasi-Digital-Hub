<?php

declare(strict_types=1);

namespace Modules\Core\Documents\Drivers;

use Modules\Core\Documents\Contracts\VirusScanner;

/**
 * Demo/CI scanner: flags the standard EICAR anti-virus test string, accepts everything else.
 */
final class FakeVirusScanner implements VirusScanner
{
    public const EICAR = 'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*';

    public function isClean(string $absolutePath): bool
    {
        return ! str_contains((string) file_get_contents($absolutePath, length: 4096), 'EICAR-STANDARD-ANTIVIRUS-TEST-FILE');
    }
}
