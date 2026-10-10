<?php

declare(strict_types=1);

namespace Modules\Learn\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A picture, video, audio file or download. Videos and audio get low-data versions (renditions).
 *
 * @property string $id
 * @property string $organisation_id
 * @property string $kind
 * @property string $original_path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property string $status
 * @property array<string, array{path: string, bytes: int}>|null $renditions
 * @property int|null $duration_seconds
 * @property string|null $alt
 */
final class Media extends Model
{
    use HasUlids;

    protected $table = 'learn_media';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['renditions' => 'array', 'size_bytes' => 'integer', 'duration_seconds' => 'integer'];
    }

    /** Bytes a learner downloads by default (the standard version for video, the original otherwise). */
    public function deliveredBytes(): int
    {
        return (int) ($this->renditions['standard']['bytes'] ?? $this->renditions['image']['bytes'] ?? $this->size_bytes);
    }
}
