<?php

declare(strict_types=1);

namespace Modules\Core\Identity\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * A versioned legal document (terms of use, privacy notice). Publishing a new
 * version makes every user accept it again at their next sign-in.
 *
 * @property int $id
 * @property string $key
 * @property int $version
 * @property string $title
 * @property string $summary
 * @property string $body
 * @property CarbonImmutable $published_at
 */
final class ConsentDocument extends Model
{
    protected $fillable = ['key', 'version', 'title', 'summary', 'body', 'published_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['version' => 'integer', 'published_at' => 'immutable_datetime'];
    }
}
