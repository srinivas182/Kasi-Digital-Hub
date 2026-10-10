<?php

declare(strict_types=1);

namespace Modules\Learn\Media;

use Illuminate\Support\Facades\Storage;

/** CI/demo converter: copies the original as each version, so nothing heavy runs in tests. */
final class FakeMediaConverter implements MediaConverter
{
    public function convert(string $disk, string $path, string $kind, string $targetDirectory): array
    {
        $storage = Storage::disk($disk);
        $renditions = [];
        foreach ($kind === 'video' ? ['low' => 'mp4', 'standard' => 'mp4', 'audio' => 'm4a'] : ['audio' => 'm4a'] as $name => $ext) {
            $target = "{$targetDirectory}/{$name}.{$ext}";
            $storage->copy($path, $target);
            $renditions[$name] = ['path' => $target, 'bytes' => (int) $storage->size($target)];
        }

        return ['renditions' => $renditions, 'duration' => 60];
    }
}
