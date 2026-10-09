<?php

declare(strict_types=1);

namespace Modules\Work\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An occupation from the Organising Framework for Occupations (OFO). Codes come from the official
 * list (kasi:work:import-ofo); the starter list has titles and major groups only.
 *
 * @property int $id
 * @property string|null $code
 * @property string $title
 * @property int $major_group
 * @property bool $official
 */
final class OfoOccupation extends Model
{
    public $timestamps = false;

    protected $table = 'ofo_occupations';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['official' => 'boolean', 'major_group' => 'integer'];
    }
}
