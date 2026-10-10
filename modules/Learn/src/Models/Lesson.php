<?php

declare(strict_types=1);

namespace Modules\Learn\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $course_id
 * @property int $module_id
 * @property string $title
 * @property string $kind
 * @property array<string, mixed>|null $content
 * @property string|null $media_id
 * @property string|null $transcript
 * @property int|null $minutes
 * @property int $position
 * @property bool $ai_drafted
 * @property bool $preview
 * @property-read Media|null $media
 */
final class Lesson extends Model
{
    use HasUlids;

    public const KINDS = ['text', 'video', 'audio', 'download', 'quiz', 'assignment'];

    protected $table = 'learn_lessons';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['content' => 'array', 'ai_drafted' => 'boolean', 'preview' => 'boolean', 'minutes' => 'integer', 'position' => 'integer'];
    }

    /** @return BelongsTo<Media, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
