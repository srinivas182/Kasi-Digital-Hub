<?php

declare(strict_types=1);

namespace Modules\Biz\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A cash book entry.
 *
 * @property string $id
 * @property string $business_id
 * @property string $direction
 * @property int $amount_cents
 * @property string $category
 * @property string $method
 * @property CarbonImmutable $occurred_on
 * @property string|null $note
 * @property int|null $contact_id
 * @property string|null $receipt_document_id
 * @property string $source
 * @property string|null $source_ref
 * @property bool $hidden
 * @property string|null $created_by
 * @property CarbonImmutable $created_at
 */
final class Entry extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $table = 'biz_entries';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'occurred_on' => 'immutable_date', 'hidden' => 'boolean', 'created_at' => 'immutable_datetime'];
    }
}
