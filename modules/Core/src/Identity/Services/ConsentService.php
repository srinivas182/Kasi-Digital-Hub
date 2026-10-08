<?php

declare(strict_types=1);

namespace Modules\Core\Identity\Services;

use Illuminate\Support\Collection;
use Modules\Core\Events\ConsentChanged;
use Modules\Core\Identity\Models\Consent;
use Modules\Core\Identity\Models\ConsentDocument;
use Modules\Core\Identity\Models\User;

/**
 * Consent per purpose (POPIA): append-only history, versioned documents and
 * re-acceptance when the terms or privacy notice change.
 */
final readonly class ConsentService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @return array<string, array{required: bool, documents?: list<string>}>
     */
    public function purposes(): array
    {
        /** @var array<string, array{required: bool, documents?: list<string>}> $purposes */
        $purposes = config('kasi.consent.purposes');

        return $purposes;
    }

    /**
     * Latest published version of each legal document, keyed by document key.
     *
     * @return Collection<string, ConsentDocument>
     */
    public function currentDocuments(): Collection
    {
        return ConsentDocument::query()
            ->where('published_at', '<=', now())
            ->orderBy('version')
            ->get()
            ->keyBy('key');
    }

    /**
     * Record decisions. The required 'platform' purpose stores the document versions accepted.
     *
     * @param  array<string, bool>  $choices
     */
    public function record(User $user, array $choices, string $channel = 'self', ?User $assistedBy = null): void
    {
        $versions = $this->currentDocuments()->map(fn (ConsentDocument $document): int => $document->version)->all();

        foreach ($choices as $purpose => $granted) {
            if (! array_key_exists($purpose, $this->purposes())) {
                continue;
            }

            $current = $this->state($user)[$purpose] ?? null;
            $isPlatform = $purpose === 'platform';

            if ($current === $granted && ! $isPlatform) {
                continue;
            }

            Consent::query()->create([
                'user_id' => $user->id,
                'purpose' => $purpose,
                'granted' => $granted,
                'document_versions' => $isPlatform ? $versions : null,
                'channel' => $channel,
                'assisted_by' => $assistedBy?->id,
                'locale' => app()->getLocale(),
                'created_at' => now(),
            ]);

            $this->audit->record($granted ? 'consent.granted' : 'consent.withdrawn', $user, meta: ['purpose' => $purpose, 'channel' => $channel], actor: $assistedBy);
            event(new ConsentChanged($user, $purpose, $granted, $assistedBy?->id));
        }

        if (array_key_exists('whatsapp_updates', $choices)) {
            $user->forceFill(['whatsapp_opt_in' => $choices['whatsapp_updates']])->save();
        }
    }

    /**
     * Current decision per purpose (null when never asked).
     *
     * @return array<string, bool|null>
     */
    public function state(User $user): array
    {
        $latest = Consent::query()
            ->where('user_id', $user->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->keyBy('purpose');

        return collect($this->purposes())
            ->map(fn (array $config, string $purpose): ?bool => $latest->get($purpose)?->granted)
            ->all();
    }

    /** True when the user has not accepted the latest terms and privacy notice. */
    public function needsReacceptance(User $user): bool
    {
        $accepted = Consent::query()
            ->where('user_id', $user->id)
            ->where('purpose', 'platform')
            ->where('granted', true)
            ->latest('created_at')
            ->latest('id')
            ->first();

        if ($accepted === null) {
            return true;
        }

        $versions = $accepted->document_versions ?? [];

        return $this->currentDocuments()->contains(
            fn (ConsentDocument $document): bool => ($versions[$document->key] ?? 0) < $document->version,
        );
    }
}
