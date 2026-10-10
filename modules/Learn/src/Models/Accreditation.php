<?php

declare(strict_types=1);

namespace Modules\Learn\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $organisation_id
 * @property string $body
 * @property string $number
 * @property string|null $document_id
 * @property string $status
 * @property string|null $reason
 */
final class Accreditation extends Model
{
    use HasUlids;

    protected $table = 'learn_accreditations';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['decided_at' => 'immutable_datetime'];
    }
}
