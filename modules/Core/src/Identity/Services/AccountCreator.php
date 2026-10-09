<?php

declare(strict_types=1);

namespace Modules\Core\Identity\Services;

use Illuminate\Support\Facades\DB;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\Hub;

/**
 * Creates an account after the phone number was verified - used by self sign-up and by
 * facilitators registering someone at a hub (assisted). Consent is recorded with how it was given.
 */
final readonly class AccountCreator
{
    public function __construct(private ConsentService $consents, private AuditLogger $audit) {}

    /**
     * @param  array{first_name: string, last_name: string, preferred_name?: string|null, date_of_birth: string, pin: string, home_hub_id?: string|null}  $data
     * @param  array<string, bool>  $optionalConsents
     */
    public function create(string $phone, array $data, string $ageBand, array $optionalConsents, ?User $assistedBy = null): User
    {
        return DB::transaction(function () use ($phone, $data, $ageBand, $optionalConsents, $assistedBy): User {
            $user = User::query()->create([
                'phone' => $phone,
                'phone_verified_at' => now(),
                'first_name' => trim($data['first_name']),
                'last_name' => trim($data['last_name']),
                'preferred_name' => isset($data['preferred_name']) && trim((string) $data['preferred_name']) !== '' ? trim((string) $data['preferred_name']) : null,
                'date_of_birth' => $data['date_of_birth'],
                'preferred_locale' => app()->getLocale(),
                'pin' => $data['pin'],
                'status' => $ageBand === AgePolicy::MINOR ? User::STATUS_PENDING_GUARDIAN : User::STATUS_ACTIVE,
                'age_band' => $ageBand,
            ]);

            if (! empty($data['home_hub_id'])) {
                $hub = Hub::query()->with('municipality')->whereKey($data['home_hub_id'])->firstOrFail();
                $user->forceFill([
                    'home_hub_id' => $hub->id,
                    'municipality_id' => $hub->municipality_id,
                    'province_id' => $hub->municipality->province_id,
                ])->save();
            }

            $this->consents->record($user, ['platform' => true, ...$optionalConsents], $assistedBy === null ? 'self' : 'assisted', $assistedBy);
            $this->audit->record($assistedBy === null ? 'signup.completed' : 'signup.assisted', $user, meta: ['age_band' => $ageBand], actor: $assistedBy);

            return $user;
        });
    }
}
