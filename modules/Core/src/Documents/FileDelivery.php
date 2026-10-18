<?php

declare(strict_types=1);

namespace Modules\Core\Documents;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Delivering files (S22): permission is always checked by the caller first. When the disk is object storage
 * (S3-compatible), the person is redirected to a short-lived presigned link so the storage provider / CDN sends
 * the bytes - large course videos never tie up app workers. On local disk the file is streamed as before.
 */
final class FileDelivery
{
    public const LINK_MINUTES = 5;

    /** @param array<string, string> $headers */
    public static function send(string $disk, string $path, ?string $name, array $headers, string $disposition = 'attachment'): Response
    {
        $storage = Storage::disk($disk);
        if (config("filesystems.disks.{$disk}.driver") === 's3') {
            $options = array_filter([
                'ResponseContentType' => $headers['Content-Type'] ?? null,
                'ResponseContentDisposition' => $name !== null ? sprintf('%s; filename="%s"', $disposition, addslashes($name)) : null,
                'ResponseCacheControl' => $headers['Cache-Control'] ?? null,
            ]);

            return redirect()->away($storage->temporaryUrl($path, now()->addMinutes(self::LINK_MINUTES), $options), 302, ['Cache-Control' => 'private, no-store']);
        }

        return $storage->response($path, $name, $headers, $disposition);
    }
}
