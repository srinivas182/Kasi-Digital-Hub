<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Modules\Learn\Media\FfmpegConverter;
use Modules\Learn\Media\MediaRejected;
use Symfony\Component\Process\Process;

/*
| Contract check for the real ffmpeg converter (S13). Runs in the integration job (KASI_INTEGRATION=1),
| which installs ffmpeg; locally it runs when ffmpeg is present.
*/

beforeEach(function (): void {
    if (! env('KASI_INTEGRATION') && ! is_executable('/usr/bin/ffmpeg')) {
        $this->markTestSkipped('Needs ffmpeg (set KASI_INTEGRATION=1 in CI).');
    }
});

function sampleVideo(string $path, int $seconds): void
{
    (new Process(['ffmpeg', '-y', '-loglevel', 'error', '-f', 'lavfi', '-i', "testsrc=size=1280x720:rate=25:duration={$seconds}",
        '-f', 'lavfi', '-i', "sine=frequency=440:duration={$seconds}", '-shortest', '-c:v', 'libx264', '-c:a', 'aac', $path]))->mustRun();
}

it('converts a video into small 240p and 360p versions, audio-only and a thumbnail', function (): void {
    $disk = Storage::fake('learn');
    $disk->makeDirectory('media/test');
    sampleVideo($disk->path('media/test/original.mp4'), 4);

    $result = (new FfmpegConverter)->convert('learn', 'media/test/original.mp4', 'video', 'media/test/renditions');

    expect(array_keys($result['renditions']))->toBe(['low', 'standard', 'audio', 'thumb'])->and($result['duration'])->toBe(4);
    $probe = static fn (string $path): string => trim((new Process(['ffprobe', '-v', 'error', '-select_streams', 'v:0', '-show_entries', 'stream=height', '-of', 'csv=p=0', $disk->path($path)]))->mustRun()->getOutput());
    expect($probe($result['renditions']['low']['path']))->toBe('240')
        ->and($probe($result['renditions']['standard']['path']))->toBe('360')
        ->and($result['renditions']['low']['bytes'])->toBeLessThan($result['renditions']['standard']['bytes']);
});

it('rejects videos longer than the limit', function (): void {
    config(['kasi.learn.max_media_seconds' => 2]);
    $disk = Storage::fake('learn');
    $disk->makeDirectory('media/long');
    sampleVideo($disk->path('media/long/original.mp4'), 4);

    expect(fn () => (new FfmpegConverter)->convert('learn', 'media/long/original.mp4', 'video', 'media/long/renditions'))->toThrow(MediaRejected::class, 'too_long');
});
