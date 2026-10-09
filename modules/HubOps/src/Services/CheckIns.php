<?php

declare(strict_types=1);

namespace Modules\HubOps\Services;

use Illuminate\Database\UniqueConstraintViolationException;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\Hub;
use Modules\HubOps\Events\VisitRecorded;
use Modules\HubOps\Models\HubVisit;

/**
 * Records visits: one per person per hub per day (South African date). Walk-ins who are not
 * registered are counted without a person.
 */
final class CheckIns
{
    /**
     * @return array{visit: HubVisit, created: bool}
     */
    public function record(Hub $hub, ?User $person, string $purpose, string $method, ?User $by = null): array
    {
        $today = now('Africa/Johannesburg')->toDateString();

        if ($person !== null) {
            $existing = HubVisit::query()->where('hub_id', $hub->id)->where('user_id', $person->id)->whereDate('visit_date', $today)->first();
            if ($existing !== null) {
                return ['visit' => $existing, 'created' => false];
            }
        }

        try {
            $visit = HubVisit::query()->create([
                'hub_id' => $hub->id,
                'user_id' => $person?->id,
                'visit_date' => $today,
                'purpose' => $purpose,
                'method' => $method,
                'checked_in_by' => $by?->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Two check-ins at the same moment (e.g. a double scan): the first one wins.
            return ['visit' => HubVisit::query()->where('hub_id', $hub->id)->where('user_id', $person?->id)->whereDate('visit_date', $today)->firstOrFail(), 'created' => false];
        }

        event(new VisitRecorded($visit));

        return ['visit' => $visit, 'created' => true];
    }

    public function visitedToday(Hub $hub, User $person): bool
    {
        return HubVisit::query()->where('hub_id', $hub->id)->where('user_id', $person->id)
            ->whereDate('visit_date', now('Africa/Johannesburg')->toDateString())->exists();
    }
}
