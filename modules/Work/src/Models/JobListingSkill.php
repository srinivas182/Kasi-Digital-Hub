<?php

declare(strict_types=1);

namespace Modules\Work\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $listing_id
 * @property string $name
 * @property bool $must
 */
final class JobListingSkill extends Model
{
    public $timestamps = false;

    protected $table = 'work_listing_skills';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['must' => 'boolean'];
    }
}
