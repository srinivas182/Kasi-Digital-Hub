<?php

declare(strict_types=1);

namespace Modules\Learn\Media;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Modules\Learn\Models\Media;

/** Background conversion of an uploaded video or audio file (queue: media). */
final class ConvertMedia implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 2;

    public function __construct(public readonly string $mediaId)
    {
        $this->onQueue('media');
        $this->afterCommit();
    }

    public function handle(MediaConverter $converter): void
    {
        $media = Media::query()->find($this->mediaId);
        if ($media === null || $media->status !== 'processing') {
            return;
        }

        try {
            $result = $converter->convert('learn', $media->original_path, $media->kind, dirname($media->original_path).'/renditions');
            $media->forceFill(['status' => 'ready', 'renditions' => $result['renditions'], 'duration_seconds' => $result['duration']])->save();
        } catch (MediaRejected $e) {
            $media->forceFill(['status' => 'failed', 'alt' => $e->getMessage()])->save();
        } catch (\Throwable $e) {
            Log::error('Media conversion failed', ['media' => $media->id, 'error' => $e->getMessage()]);
            $media->forceFill(['status' => 'failed', 'alt' => 'conversion_failed'])->save();
        }
    }
}
