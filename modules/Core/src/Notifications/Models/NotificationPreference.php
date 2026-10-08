<?php

declare(strict_types=1);

namespace Modules\Core\Notifications\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A person's choice for one category on one channel (only stored when it differs from the default).
 *
 * @property int $id
 * @property string $user_id
 * @property string $category
 * @property string $channel
 * @property bool $enabled
 */
final class NotificationPreference extends Model
{
    protected $fillable = ['user_id', 'category', 'channel', 'enabled'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
