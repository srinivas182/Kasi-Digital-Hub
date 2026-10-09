<?php

declare(strict_types=1);

namespace Modules\Work\Notifications\Messages;

use App\Support\Format\SaFormat;
use Modules\Core\Identity\Models\User;
use Modules\Core\Notifications\KasiNotification;
use Modules\Work\Models\JobListing;

/**
 * Tells the employer what happened to a listing: held for review, live after review, taken down
 * (with the reason), or closing soon.
 */
final class ListingStatusNotification extends KasiNotification
{
    public const KEY = 'listing_status';

    public const CATEGORY = 'jobs';

    public const WHATSAPP_TEMPLATE = 'kasihub_listing_status';

    /** @param 'review'|'live'|'taken_down'|'expiring' $state */
    public function __construct(private readonly JobListing $listing, private readonly string $state) {}

    public function channels(): array
    {
        return ['in_app', 'whatsapp', 'email'];
    }

    public function title(User $user): string
    {
        return $this->t($user, "notify.listing_{$this->state}.title", ['title' => $this->listing->title]);
    }

    public function body(User $user): string
    {
        return $this->t($user, "notify.listing_{$this->state}.body", [
            'reason' => (string) $this->listing->status_reason,
            'date' => SaFormat::date($this->listing->closes_on),
        ]);
    }

    public function url(): string
    {
        return '/work/employer/listings/'.$this->listing->id.'/edit';
    }

    public function important(): bool
    {
        return $this->state === 'taken_down';
    }

    public function dedupeKey(): string
    {
        return "listing:{$this->listing->id}:{$this->state}";
    }

    public static function whatsappBody(): string
    {
        return 'KasiWork: {{1}} - {{2}}';
    }

    public function whatsappParams(User $user): array
    {
        return [$this->title($user), $this->body($user)];
    }
}
