<?php

declare(strict_types=1);

namespace Modules\Admin\Support;

use Modules\Core\Ai\Models\ModerationFlag;
use Modules\Core\Insights\ReviewSource;

/** Content held by the moderation checks (events, adverts, offers, messages) - for the review inbox. */
final class HeldContent implements ReviewSource
{
    public function waiting(): array
    {
        return array_values(ModerationFlag::query()->whereIn('status', ['pending', 'escalated'])->oldest('created_at')->limit(500)->get()
            ->map(static fn (ModerationFlag $f): array => ['type' => $f->subject_type, 'id' => $f->id, 'title' => mb_substr((string) $f->excerpt, 0, 120),
                'reason' => implode('; ', (array) $f->reasons), 'since' => (string) $f->created_at, 'url' => '/admin/moderation?flag='.$f->id, 'bulk' => $f->status === 'pending'])->all());
    }
}
