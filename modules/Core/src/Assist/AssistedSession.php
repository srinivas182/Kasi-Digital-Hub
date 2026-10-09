<?php

declare(strict_types=1);

namespace Modules\Core\Assist;

use Illuminate\Contracts\Session\Session;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Structure\Models\Hub;

/**
 * Portal SDK extension point: a facilitator helping a person who is present at the hub.
 *
 * This is NOT "log in as user". The facilitator stays signed in as themselves; portals that
 * offer assisted features (profile, documents now; CV builder in S9, enrolment in S13) read the
 * current assisted person from here and record every action as "by facilitator, for person".
 * Sessions end after 30 minutes or when the facilitator finishes.
 */
final readonly class AssistedSession
{
    private const KEY = 'assist';

    public const MINUTES = 30;

    public function __construct(private Session $session, private AuditLogger $audit) {}

    public function start(User $facilitator, User $person, Hub $hub): void
    {
        $this->session->put(self::KEY, [
            'person' => $person->id,
            'hub' => $hub->id,
            'by' => $facilitator->id,
            'expires' => now()->addMinutes(self::MINUTES)->getTimestamp(),
        ]);
        $this->audit->record('assist.started', $person, meta: ['hub' => $hub->id], actor: $facilitator);
    }

    /**
     * The person being helped, if the session is still valid for this facilitator.
     */
    public function person(User $facilitator): ?User
    {
        $state = $this->state($facilitator);

        return $state === null ? null : User::query()->find($state['person']);
    }

    public function hubId(User $facilitator): ?string
    {
        return $this->state($facilitator)['hub'] ?? null;
    }

    public function end(User $facilitator): void
    {
        $state = $this->state($facilitator);
        $this->session->forget(self::KEY);

        if ($state !== null && ($person = User::query()->find($state['person'])) !== null) {
            $this->audit->record('assist.ended', $person, actor: $facilitator);
        }
    }

    /**
     * For the shared page props: who is being helped (name only) and until when.
     *
     * @return array{name: string, expiresAt: string}|null
     */
    public function summary(?User $facilitator): ?array
    {
        $state = $facilitator === null ? null : $this->state($facilitator);
        $person = $state === null ? null : User::query()->find($state['person']);

        if ($state === null || $person === null) {
            return null;
        }

        return ['name' => $person->fullName(), 'expiresAt' => date(DATE_ATOM, $state['expires'])];
    }

    /** @return array{person: string, hub: string, by: string, expires: int}|null */
    private function state(User $facilitator): ?array
    {
        /** @var array{person: string, hub: string, by: string, expires: int}|null $state */
        $state = $this->session->get(self::KEY);

        if ($state === null || $state['by'] !== $facilitator->id || $state['expires'] < now()->getTimestamp()) {
            return null;
        }

        return $state;
    }
}
