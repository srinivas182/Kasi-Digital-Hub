<?php

declare(strict_types=1);

namespace Modules\Core\Identity\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * One consent decision (append-only history). The latest row per purpose is the
 * current state; earlier rows are kept as evidence (POPIA).
 *
 * @property string $id
 * @property string $user_id
 * @property string $purpose
 * @property bool $granted
 * @property array<string, int>|null $document_versions
 * @property string $channel
 * @property string|null $assisted_by
 * @property string $locale
 * @property CarbonImmutable $created_at
 */
final class Consent extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'purpose', 'granted', 'document_versions', 'channel', 'assisted_by', 'locale', 'created_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['granted' => 'boolean', 'document_versions' => 'array', 'created_at' => 'immutable_datetime'];
    }
}
