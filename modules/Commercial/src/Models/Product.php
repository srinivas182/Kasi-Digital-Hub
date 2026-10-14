<?php

declare(strict_types=1);

namespace Modules\Commercial\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $key
 * @property string $stream
 * @property string $name
 * @property string $unit
 * @property string $merchant
 * @property string|null $partner_organisation_id
 * @property bool $active
 */
final class Product extends Model
{
    public const STREAMS = ['package', 'work', 'learn', 'start', 'onsite', 'licence'];

    protected $table = 'com_products';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
