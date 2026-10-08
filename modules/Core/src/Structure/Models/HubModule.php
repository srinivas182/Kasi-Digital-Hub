<?php

declare(strict_types=1);

namespace Modules\Core\Structure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Access\BelongsToHub;

/**
 * Whether a hub may deliver a portal locally (from its package or as an add-on).
 *
 * @property int $id
 * @property string $hub_id
 * @property string $module
 * @property bool $enabled
 * @property string $source
 * @property string|null $updated_by
 */
final class HubModule extends Model
{
    use BelongsToHub;

    protected $fillable = ['hub_id', 'module', 'enabled', 'source', 'updated_by'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    /** @return BelongsTo<Hub, $this> */
    public function hub(): BelongsTo
    {
        return $this->belongsTo(Hub::class);
    }
}
