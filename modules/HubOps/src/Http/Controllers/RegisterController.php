<?php

declare(strict_types=1);

namespace Modules\HubOps\Http\Controllers;

use App\Support\Format\SaFormat;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AgePolicy;
use Modules\Core\Identity\Services\ConsentService;
use Modules\Core\Identity\Services\DeviceManager;
use Modules\Core\Identity\Services\PinPolicy;
use Modules\HubOps\Models\HubVisit;
use Modules\HubOps\Services\AssistedRegistration;

/**
 * Assisted registration at the hub desk (see AssistedRegistration for the safeguards).
 */
final class RegisterController extends StaffController
{
    private const SESSION = 'hubops.register';

    public function phone(Request $request): Response
    {
        $hub = $this->hub($request);

        return Inertia::render('HubOps/Register/Phone', ['hubs' => $this->hubProps($request, $hub)]);
    }

    public function sendCode(Request $request, AssistedRegistration $registration, DeviceManager $devices): RedirectResponse
    {
        $hub = $this->hub($request);
        $validated = $request->validate(['phone' => ['required', 'string', 'max:20']]);
        $phone = SaFormat::normalisePhone($validated['phone']);

        if ($phone === null) {
            return back()->withErrors(['phone' => __('auth.phone.invalid')]);
        }

        if (User::query()->where('phone', $phone)->exists()) {
            // Staff may know this: the person can simply be checked in instead.
            return back()->withErrors(['phone' => __('hubops.register.exists')]);
        }

        $result = $registration->sendCode($phone, $request->ip(), $devices->fingerprint());
        if (! $result['sent']) {
            $reason = in_array($result['reason'], ['rate_limited', 'invalid_number'], true) ? $result['reason'] : 'unavailable';

            return back()->withErrors(['phone' => __('auth.code.'.$reason, ['seconds' => (string) ($result['retry_after'] ?? 60)])]);
        }

        $request->session()->put(self::SESSION, ['phone' => $phone, 'hub' => $hub->id, 'expires' => now()->addMinutes(15)->getTimestamp()]);

        return to_route('hubops.register.details')->with('demo_code', $result['demo_code']);
    }

    public function details(Request $request, ConsentService $consents): Response|RedirectResponse
    {
        $flow = $this->flow($request);
        if ($flow === null) {
            return to_route('hubops.register');
        }

        return Inertia::render('HubOps/Register/Details', [
            'phone' => SaFormat::phone($flow['phone']),
            'demoCode' => $request->session()->get('demo_code'),
            'purposes' => array_keys(array_filter($consents->purposes(), static fn (array $p): bool => ! $p['required'])),
            'visitPurposes' => HubVisit::PURPOSES,
        ]);
    }

    public function store(Request $request, AssistedRegistration $registration): RedirectResponse
    {
        $flow = $this->flow($request);
        if ($flow === null) {
            return to_route('hubops.register')->withErrors(['phone' => __('hubops.register.expired')]);
        }
        $hub = $this->hub($request);
        abort_unless($hub->id === $flow['hub'], 409);

        $validated = $request->validate([
            'code' => ['required', 'digits:6'],
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'preferred_name' => ['nullable', 'string', 'max:80'],
            'date_of_birth' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'pin' => ['required', 'string', 'confirmed'],
            'consents' => ['array'],
            'consents.*' => ['boolean'],
            'visit_purpose' => ['required', Rule::in(HubVisit::PURPOSES)],
            'accept_terms' => ['accepted'],
            'present' => ['accepted'],
        ]);

        if (AgePolicy::classify(CarbonImmutable::parse($validated['date_of_birth'])) !== AgePolicy::ADULT) {
            return back()->withErrors(['date_of_birth' => __('hubops.register.adults_only')]);
        }

        if ($problem = PinPolicy::problem($validated['pin'])) {
            return back()->withErrors(['pin' => __($problem)]);
        }

        if (! $registration->verifyCode($flow['phone'], $validated['code'])) {
            return back()->withErrors(['code' => __('auth.code.wrong')]);
        }

        /** @var array<string, bool> $optional */
        $optional = array_map('boolval', $validated['consents'] ?? []);
        $person = $registration->complete($flow['phone'], $validated, $optional, $validated['visit_purpose'], $hub, $this->actor($request));
        $request->session()->forget(self::SESSION);

        return to_route('hubops.checkin')->with('status', __('hubops.register.done', ['name' => $person->fullName()]));
    }

    /** @return array{phone: string, hub: string, expires: int}|null */
    private function flow(Request $request): ?array
    {
        /** @var array{phone: string, hub: string, expires: int}|null $flow */
        $flow = $request->session()->get(self::SESSION);

        return $flow !== null && $flow['expires'] >= now()->getTimestamp() ? $flow : null;
    }
}
