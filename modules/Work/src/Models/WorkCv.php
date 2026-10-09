<?php

declare(strict_types=1);

namespace Modules\Work\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Documents\Generation\GeneratedDocument;

/**
 * @property string $id
 * @property string $user_id
 * @property string|null $generated_document_id
 * @property string $template
 * @property array<string, mixed> $content
 * @property string|null $created_by
 * @property CarbonImmutable $created_at
 * @property-read GeneratedDocument|null $document
 */
final class WorkCv extends Model
{
    use HasUlids;

    public const TEMPLATES = ['classic', 'simple'];

    public const TEMPLATE_VERSION = 1;

    protected $table = 'work_cvs';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['content' => 'array', 'created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<GeneratedDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(GeneratedDocument::class, 'generated_document_id');
    }
}
