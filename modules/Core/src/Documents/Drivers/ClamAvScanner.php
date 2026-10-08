<?php

declare(strict_types=1);

namespace Modules\Core\Documents\Drivers;

use Modules\Core\Documents\Contracts\VirusScanner;
use RuntimeException;

/**
 * Scans files with a local ClamAV daemon (clamd) over its socket using INSTREAM.
 * Fails closed: if the scanner is unreachable an exception is thrown and the file stays unscanned.
 */
final readonly class ClamAvScanner implements VirusScanner
{
    public function __construct(private string $socket) {}

    public function isClean(string $absolutePath): bool
    {
        $connection = @stream_socket_client('unix://'.$this->socket, $errno, $error, 10);

        if ($connection === false) {
            throw new RuntimeException("ClamAV is not reachable at {$this->socket}: {$error}");
        }

        $file = fopen($absolutePath, 'rb');
        if ($file === false) {
            throw new RuntimeException("Cannot read {$absolutePath}.");
        }

        fwrite($connection, "zINSTREAM\0");
        while (! feof($file)) {
            $chunk = (string) fread($file, 8192);
            fwrite($connection, pack('N', strlen($chunk)).$chunk);
        }
        fwrite($connection, pack('N', 0));
        fclose($file);

        $reply = trim((string) stream_get_contents($connection), "\0\n ");
        fclose($connection);

        if (str_ends_with($reply, 'OK')) {
            return true;
        }

        if (str_ends_with($reply, 'FOUND')) {
            return false;
        }

        throw new RuntimeException("Unexpected ClamAV reply: {$reply}");
    }
}
