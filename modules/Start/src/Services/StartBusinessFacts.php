<?php

declare(strict_types=1);

namespace Modules\Start\Services;

use Illuminate\Support\Facades\DB;
use Modules\Core\Enterprise\BusinessFacts;
use Modules\Core\Identity\Models\User;
use Modules\Start\Models\Business;
use Modules\Start\Models\Step;

/** KasiStart's implementation of the BusinessFacts extension point. */
final readonly class StartBusinessFacts implements BusinessFacts
{
    public function __construct(private Businesses $businesses) {}

    public function facts(string $businessId): ?array
    {
        $b = Business::query()->find($businessId);
        if (! $b instanceof Business) {
            return null;
        }
        $ownerIds = array_values(array_map('strval', DB::table('start_business_members')->where('business_id', $b->id)->pluck('user_id')->all()));
        $ages = User::query()->whereIn('id', $ownerIds)->whereNotNull('date_of_birth')->get()->map(static fn (User $u): int => (int) $u->date_of_birth->age)->all();

        return [
            'id' => $b->id, 'name' => $b->name, 'stage' => $b->stage, 'sector' => $b->sector, 'legal_form' => $b->legal_form,
            'municipality_id' => $b->municipality_id, 'province_id' => $b->municipality_id !== null ? (int) DB::table('municipalities')->where('id', $b->municipality_id)->value('province_id') : null,
            'hub_id' => $b->hub_id, 'people' => $b->people, 'turnover_band' => $b->turnover_band, 'readiness' => $b->readiness,
            'steps_done' => array_values(array_map('strval', Step::query()->whereIn('id', DB::table('start_business_steps')->where('business_id', $b->id)->pluck('step_id'))->pluck('key')->all())),
            'owner_ids' => $ownerIds, 'youngest_owner_age' => $ages === [] ? null : (int) min($ages),
        ];
    }

    public function businessesOf(User $user): array
    {
        return array_values($this->businesses->of($user)->map(static fn (Business $b): array => ['id' => $b->id, 'name' => $b->name])->all());
    }

    public function isMember(string $businessId, User $user): bool
    {
        return DB::table('start_business_members')->where('business_id', $businessId)->where('user_id', $user->id)->exists();
    }

    public function summary(string $businessId): array
    {
        $b = Business::query()->findOrFail($businessId);
        $plan = (array) json_decode((string) DB::table('start_plans')->where('business_id', $b->id)->value('sections'), true);

        return array_filter([
            'sells' => (string) $b->sells, 'customers' => (string) $b->customers, 'place' => (string) $b->place_name,
            'problem' => (string) ($plan['problem'] ?? ''), 'offer' => (string) ($plan['offer'] ?? ''), 'money' => (string) ($plan['money'] ?? ''),
        ], static fn (string $v): bool => trim($v) !== '');
    }
}
