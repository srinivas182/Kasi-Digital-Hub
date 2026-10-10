<?php

declare(strict_types=1);

namespace Modules\Learn\Media;

/**
 * Converts uploaded video and audio into low-data versions (ADR-021). Returns renditions keyed by
 * name (low, standard, audio, thumb) with their stored paths and sizes, plus the duration.
 */
interface MediaConverter
{
    /**
     * @return array{renditions: array<string, array{path: string, bytes: int}>, duration: int}
     *
     * @throws MediaRejected when the file is not usable (too long, unreadable)
     */
    public function convert(string $disk, string $path, string $kind, string $targetDirectory): array;
}
