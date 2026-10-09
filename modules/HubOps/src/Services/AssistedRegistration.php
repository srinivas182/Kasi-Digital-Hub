<?php

declare(strict_types=1);

namespace Modules\HubOps\Services;

use Modules\Core\Events\UserRegistered;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AccountCreator;
use Modules\Core\Identity\Services\AgePolicy;
use Modules\Core\Identity\Services\OtpService;
use Modules\Core\Notifications\Notifier;
use Modules\Core\Structure\Models\Hub;
use Modules\HubOps\Events\AssistedRegistration as AssistedRegistrationEvent;
use Modules\HubOps\Notifications\Messages\AssistedRegistrationNotification;

/**
 * A facilitator registers someone at the hub desk:
 * 1. a code is sent to the person's own phone (proves the number and that they are present);
 * 2. the person reads the code out, the facilitator fills in their details with them;
 * 3. the person types their own PIN on the device - the facilitator never learns it;
 * 4. consent is recorded as "assisted", the visit counts, and the person gets an SMS naming who helped.
 *
 * Under-18s register with a guardian on their own phone (guardian consent needs the guardian's phone).
 */
final readonly class AssistedRegistration
{
    public const PURPOSE = 'assisted_signup';

    public function __construct(
        private OtpService $otp,
        private AccountCreator $creator,
        private Notifier $notifier,
        private CheckIns $checkIns,
    ) {}

    /**
     * @return array{sent: bool, reason: string|null, retry_after: int|null, demo_code: string|null}
     */
    public function sendCode(string $phone, ?string $ip, ?string $device): array
    {
        return $this->otp->request($phone, self::PURPOSE, $ip, $device);
    }

    public function verifyCode(string $phone, string $code): bool
    {
        return $this->otp->verify($phone, self::PURPOSE, $code);
    }

    /**
     * @param  array{first_name: string, last_name: string, preferred_name?: string|null, date_of_birth: string, pin: string}  $details
     * @param  array<string, bool>  $consents
     */
    public function complete(string $phone, array $details, array $consents, string $purpose, Hub $hub, User $facilitator): User
    {
        $user = $this->creator->create($phone, [...$details, 'home_hub_id' => $hub->id], AgePolicy::ADULT, $consents, $facilitator);

        $this->checkIns->record($hub, $user, $purpose, 'assisted', $facilitator);
        event(new AssistedRegistrationEvent($user, $facilitator->id, $hub->id));
        event(new UserRegistered($user));
        $this->notifier->send($user, new AssistedRegistrationNotification($facilitator->fullName(), $hub->name));

        return $user;
    }
}
