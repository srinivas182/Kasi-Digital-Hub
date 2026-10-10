<?php

declare(strict_types=1);

namespace Modules\Start\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * A formalisation step (editable content, checked by a legal adviser).
 *
 * @property int $id
 * @property string $key
 * @property string $title
 * @property string $summary
 * @property string|null $why
 * @property list<string>|null $needs
 * @property string|null $where
 * @property string|null $link
 * @property string|null $cost_note
 * @property string|null $duration
 * @property array{forms?: list<string>, sectors?: list<string>, employees?: bool|null} $applies_to
 * @property string|null $document_type
 * @property int $position
 * @property CarbonImmutable|null $last_checked_on
 * @property bool $active
 */
final class Step extends Model
{
    protected $table = 'start_steps';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['needs' => 'array', 'applies_to' => 'array', 'last_checked_on' => 'immutable_date', 'active' => 'boolean', 'position' => 'integer'];
    }

    /** Does this step apply to a business? (KasiStart journey) */
    public function appliesTo(Business $b): bool
    {
        $forms = $this->applies_to['forms'] ?? ['*'];
        $sectors = $this->applies_to['sectors'] ?? ['*'];
        $employees = $this->applies_to['employees'] ?? null;

        return $this->active
            && (in_array('*', $forms, true) || in_array((string) $b->legal_form, $forms, true))
            && (in_array('*', $sectors, true) || in_array($b->sector, $sectors, true))
            && ($employees !== true || $b->people > 1);
    }
}
