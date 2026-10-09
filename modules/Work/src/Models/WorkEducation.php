<?php

declare(strict_types=1);

namespace Modules\Work\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Documents\Models\Document;

/**
 * @property string $id
 * @property string $user_id
 * @property string $kind
 * @property string $name
 * @property string|null $institution
 * @property int|null $year
 * @property bool $in_progress
 * @property string|null $details
 * @property string|null $document_id
 * @property-read Document|null $document
 */
final class WorkEducation extends Model
{
    use HasUlids;

    public const KINDS = ['school', 'matric', 'certificate', 'diploma', 'degree', 'learnership', 'short_course'];

    protected $table = 'work_education';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['in_progress' => 'boolean', 'year' => 'integer'];
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
