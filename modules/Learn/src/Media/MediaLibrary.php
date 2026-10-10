<?php

declare(strict_types=1);

namespace Modules\Learn\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\Organisation;
use Modules\Learn\Models\Media;

/**
 * Stores course media on the private `learn` disk. Pictures are re-encoded (removes hidden
 * metadata and scripts, max 1280 px, WebP); videos and audio are queued for low-data conversion.
 */
final class MediaLibrary
{
    public const TYPES = [
        'image' => ['image/jpeg', 'image/png', 'image/webp'],
        'video' => ['video/mp4', 'video/quicktime', 'video/webm', 'video/3gpp'],
        'audio' => ['audio/mpeg', 'audio/mp4', 'audio/x-m4a', 'audio/ogg', 'audio/wav', 'audio/x-wav', 'audio/aac', 'audio/webm'],
        'download' => ['application/pdf'],
    ];

    public function store(Organisation $provider, UploadedFile $file, string $kind, User $by, ?string $alt = null): Media
    {
        $mime = (string) $file->getMimeType();
        if (! in_array($mime, self::TYPES[$kind] ?? [], true)) {
            throw new InvalidArgumentException(__('learn.media.wrong_type'));
        }

        $directory = 'media/'.$provider->id.'/'.Str::ulid();
        $disk = Storage::disk('learn');
        $renditions = null;

        if ($kind === 'image') {
            $webp = $this->reencode((string) file_get_contents((string) $file->getRealPath()));
            $path = $directory.'/image.webp';
            $disk->put($path, $webp);
            $renditions = ['image' => ['path' => $path, 'bytes' => strlen($webp)]];
        } else {
            $path = (string) $file->storeAs($directory, 'original.'.($file->guessExtension() ?? 'bin'), 'learn');
        }

        $media = Media::query()->create([
            'organisation_id' => $provider->id, 'kind' => $kind, 'original_path' => $path, 'original_name' => mb_substr($file->getClientOriginalName(), 0, 190),
            'mime_type' => $kind === 'image' ? 'image/webp' : $mime, 'size_bytes' => (int) $disk->size($path),
            'status' => in_array($kind, ['video', 'audio'], true) ? 'processing' : 'ready', 'renditions' => $renditions,
            'alt' => $alt, 'uploaded_by' => $by->id,
        ]);

        if ($media->status === 'processing') {
            ConvertMedia::dispatch($media->id);
        }

        return $media;
    }

    private function reencode(string $contents): string
    {
        $image = @imagecreatefromstring($contents);
        if ($image === false) {
            throw new InvalidArgumentException(__('learn.media.unreadable'));
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, 1280 / max($width, $height));
        if ($scale < 1) {
            $resized = imagescale($image, (int) round($width * $scale), (int) round($height * $scale));
            if ($resized !== false) {
                imagedestroy($image);
                $image = $resized;
            }
        }

        ob_start();
        imagewebp($image, null, 72);
        imagedestroy($image);

        return (string) ob_get_clean();
    }
}
