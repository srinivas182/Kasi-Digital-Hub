<?php

declare(strict_types=1);

namespace Modules\Learn\Media;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Production converter using ffmpeg/ffprobe in the media worker container:
 * video -> 240p ("low") and 360p ("standard") H.264/AAC mono, audio-only AAC and a thumbnail;
 * audio -> AAC mono 48 kbps. Videos longer than the limit are rejected.
 */
final class FfmpegConverter implements MediaConverter
{
    public function convert(string $disk, string $path, string $kind, string $targetDirectory): array
    {
        $storage = Storage::disk($disk);
        $source = $storage->path($path);
        $duration = $this->duration($source);

        $limit = (int) config('kasi.learn.max_media_seconds');
        if ($duration <= 0 || $duration > $limit) {
            throw new MediaRejected($duration <= 0 ? 'unreadable' : 'too_long');
        }

        $storage->makeDirectory($targetDirectory);
        $out = static fn (string $name): string => $storage->path("{$targetDirectory}/{$name}");
        $jobs = $kind === 'video' ? [
            'low.mp4' => ['-vf', 'scale=-2:240', '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '32', '-c:a', 'aac', '-b:a', '48k', '-ac', '1', '-movflags', '+faststart'],
            'standard.mp4' => ['-vf', 'scale=-2:360', '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '28', '-c:a', 'aac', '-b:a', '64k', '-ac', '1', '-movflags', '+faststart'],
            'audio.m4a' => ['-vn', '-c:a', 'aac', '-b:a', '48k', '-ac', '1'],
            'thumb.jpg' => ['-ss', '1', '-frames:v', '1', '-vf', 'scale=480:-2'],
        ] : [
            'audio.m4a' => ['-vn', '-c:a', 'aac', '-b:a', '48k', '-ac', '1'],
        ];

        $renditions = [];
        foreach ($jobs as $file => $options) {
            $process = new Process(['ffmpeg', '-y', '-loglevel', 'error', '-i', $source, ...$options, $out($file)]);
            $process->setTimeout(1800)->mustRun();
            $renditions[pathinfo($file, PATHINFO_FILENAME)] = ['path' => "{$targetDirectory}/{$file}", 'bytes' => (int) filesize($out($file))];
        }

        return ['renditions' => $renditions, 'duration' => $duration];
    }

    private function duration(string $file): int
    {
        $process = new Process(['ffprobe', '-v', 'error', '-show_entries', 'format=duration', '-of', 'default=noprint_wrappers=1:nokey=1', $file]);
        $process->setTimeout(60)->run();

        return $process->isSuccessful() ? (int) ceil((float) trim($process->getOutput())) : 0;
    }
}
