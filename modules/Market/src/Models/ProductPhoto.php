<?php

declare(strict_types=1);

namespace Modules\Market\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $product_id
 * @property string $small_path
 * @property string $medium_path
 * @property string $alt
 * @property int $position
 */
final class ProductPhoto extends Model
{
    public $timestamps = false;

    protected $table = 'market_product_photos';

    protected $guarded = [];
}
