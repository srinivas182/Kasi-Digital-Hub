<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Account;

use App\Support\Locale\Languages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Access\RoleAssignments;
use Modules\Core\Access\RoleRegistry;
use Modules\Core\Access\Scope;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Models\UserDevice;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Identity\Services\ConsentService;
use Modules\Core\Identity\Services\DeviceManager;
use Modules\Core\Identity\Services\OtpService;
use Modules\Core\Identity\Services\PhoneNumbers;
use Modules\Core\Identity\Services\PinPolicy;
use Modules\Core\Identity\Services\SecurityAlerts;
use Modules\Core\Structure\Models\Hub;
use Modules\Core\Structure\Models\Municipality;
use Modules\Core\Structure\Models\RoleAssignment;
use Modules\Core\Structure\Options;

/**
 * Account settings: profile, email, PIN, phone number, devices, consent and deletion request.
 */
final class AccountController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function show(Request $request, ConsentService $consents, RoleAssignments $roleAssignments, RoleRegistry $roles): Response
    {
        $user = $this->user($request);
        $currentDevice = $request->session()->get('device_id');

        return Inertia::render('Core/Account/Index', [
            'profile' => [
                'firstName' => $user->first_name,
                'lastName' => $user->last_name,
                'preferredName' => $user->preferred_name,
                'phone' => $user->phone,
                'email' => $user->email,
                'emailVerified' => $user->email_verified_at !== null,
                'dateOfBirth' => $user->date_of_birth->toDateString(),
                'preferredLocale' => $user->preferred_locale,
                'deletionRequested' => $user->deletion_requested_at !== null,
                'twoFactor' => $user->two_factor_required,
                'homeHubId' => $user->home_hub_id,
                'provinceId' => $user->province_id,
                'municipalityId' => $user->municipality_id,
                'placeName' => $user->place_name,
            ],
            'roles' => $roleAssignments->for($user)->map(static fn (RoleAssignment $a): array => [
                'role' => $roles->find($a->role)->label ?? $a->role,
                'where' => (new Scope($a->scope_type, $a->scope_id))->describe(),
            ])->values(),
            'hubOptions' => Options::hubs(),
            'locations' => Options::locations(),
            'consents' => collect($consents->state($user))->map(fn (?bool $granted, string $purpose): array => [
                'purpose' => $purpose,
                'granted' => (bool) $granted,
                'required' => (bool) ($consents->purposes()[$purpose]['required'] ?? false),
            ])->values(),
            'devices' => $user->devices()->whereNull('revoked_at')->latest('last_seen_at')->get()->map(static fn (UserDevice $device): array => [
                'id' => $device->id,
                'name' => $device->name,
                'lastSeen' => $device->last_seen_at?->toIso8601String(),
                'remembered' => $device->isRemembered(),
                'current' => $device->id === $currentDevice,
            ]),
            'phoneChangePending' => $request->session()->has('pending_phone'),
            'demoCode' => $request->session()->get('demo_code'),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $this->user($request);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'preferred_name' => ['nullable', 'string', 'max:80'],
            'preferred_locale' => ['required', Rule::in(array_keys(Languages::available()))],
            'email' => ['nullable', 'email:rfc', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'home_hub_id' => ['nullable', 'string', Rule::exists(Hub::class, 'id')->where('status', 'live')],
            'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
            'municipality_id' => ['nullable', 'integer', Rule::exists(Municipality::class, 'id')->where('province_id', $request->integer('province_id'))],
            'place_name' => ['nullable', 'string', 'max:120'],
        ]);

        $emailChanged = ($validated['email'] ?? null) !== $user->email;

        $user->fill([
            'first_name' => trim($validated['first_name']),
            'last_name' => trim($validated['last_name']),
            'preferred_name' => isset($validated['preferred_name']) ? trim($validated['preferred_name']) : null,
            'preferred_locale' => $validated['preferred_locale'],
            'email' => $validated['email'] ?? null,
            'home_hub_id' => $validated['home_hub_id'] ?? null,
            'province_id' => $validated['province_id'] ?? null,
            'municipality_id' => $validated['municipality_id'] ?? null,
            'place_name' => isset($validated['place_name']) ? trim($validated['place_name']) : null,
        ]);

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();
        $this->audit->record('account.profile_updated', $user, meta: ['email_changed' => $emailChanged]);

        if ($emailChanged && $user->email !== null) {
            $this->sendEmailVerification($user);
        }

        Cookie::queue(Cookie::forever(Languages::COOKIE, $user->preferred_locale));

        return back()->with('status', __('account.saved'));
    }

    public function verifyEmail(Request $request, string $id, string $hash): RedirectResponse
    {
        $user = User::query()->findOrFail($id);

        abort_unless($request->hasValidSignature() && $user->email !== null && hash_equals(sha1($user->email), $hash), 403);

        $user->forceFill(['email_verified_at' => now()])->save();
        $this->audit->record('account.email_verified', $user);

        return to_route('account')->with('status', __('account.email_verified'));
    }

    public function updatePin(Request $request, SecurityAlerts $alerts): RedirectResponse
    {
        $user = $this->user($request);
        $request->validate(['current_pin' => ['required', 'string'], 'pin' => ['required', 'string', 'confirmed']]);

        if ($user->pin === null || ! Hash::check((string) $request->input('current_pin'), $user->pin)) {
            $this->audit->record('account.pin_change_failed', $user, 'failed');

            return back()->withErrors(['current_pin' => __('auth.pin.wrong_current')]);
        }

        if ($problem = PinPolicy::problem((string) $request->input('pin'))) {
            return back()->withErrors(['pin' => __($problem)]);
        }

        $user->forceFill(['pin' => $request->input('pin')])->save();
        $this->audit->record('account.pin_changed', $user);
        $alerts->send($user, 'pin_changed');

        return back()->with('status', __('account.pin_changed'));
    }

    public function requestPhoneChange(Request $request, OtpService $otp, DeviceManager $devices): RedirectResponse
    {
        $user = $this->user($request);
        $request->validate(['phone' => ['required', 'string', 'max:20']]);
        $phone = PhoneNumbers::normalise((string) $request->input('phone'));

        if ($phone === null || ! PhoneNumbers::canReceiveCodes($phone) || $phone === $user->phone) {
            return back()->withErrors(['phone' => __('auth.phone.invalid')]);
        }

        if (User::withTrashed()->where('phone', $phone)->exists()) {
            // Same message as success would leak registration; tell them gently to use another number.
            return back()->withErrors(['phone' => __('account.phone_unavailable')]);
        }

        $result = $otp->request($phone, 'change_phone', $request->ip(), $devices->fingerprint());

        if (! $result['sent']) {
            return back()->withErrors(['phone' => __('auth.code.'.$result['reason'], ['seconds' => $result['retry_after'] ?? 60])]);
        }

        $request->session()->put('pending_phone', $phone);

        return back()->with('demo_code', $result['demo_code']);
    }

    public function confirmPhoneChange(Request $request, OtpService $otp, SecurityAlerts $alerts): RedirectResponse
    {
        $user = $this->user($request);
        $request->validate(['code' => ['required', 'string', 'max:12'], 'current_pin' => ['required', 'string']]);
        $phone = $request->session()->get('pending_phone');

        if (! is_string($phone)) {
            return back();
        }

        if ($user->pin === null || ! Hash::check((string) $request->input('current_pin'), $user->pin)) {
            return back()->withErrors(['current_pin' => __('auth.pin.wrong_current')]);
        }

        if (! $otp->verify($phone, 'change_phone', (string) $request->input('code'))) {
            return back()->withErrors(['code' => __('auth.code.wrong')]);
        }

        $oldPhone = $user->phone;
        $user->forceFill(['phone' => $phone, 'phone_verified_at' => now()])->save();
        $request->session()->forget('pending_phone');
        $this->audit->record('account.phone_changed', $user);
        $alerts->send($user, 'phone_changed', phone: $oldPhone);
        $alerts->send($user, 'phone_changed');

        return back()->with('status', __('account.phone_changed'));
    }

    public function cancelPhoneChange(Request $request): RedirectResponse
    {
        $request->session()->forget('pending_phone');

        return back();
    }

    public function revokeDevice(Request $request, UserDevice $device, DeviceManager $devices): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($device->user_id === $user->id, 404);

        $devices->revoke($device);
        $this->audit->record('account.device_signed_out', $user, meta: ['device' => $device->name]);

        return back()->with('status', __('account.device_signed_out'));
    }

    public function revokeOtherDevices(Request $request, DeviceManager $devices): RedirectResponse
    {
        $user = $this->user($request);
        $current = $request->session()->get('device_id');
        $count = $devices->revokeAllExcept($user, is_string($current) ? $current : null);
        $this->audit->record('account.other_devices_signed_out', $user, meta: ['count' => $count]);

        return back()->with('status', __('account.other_devices_signed_out', ['count' => $count]));
    }

    public function updateConsents(Request $request, ConsentService $consents): RedirectResponse
    {
        $user = $this->user($request);
        $optional = array_keys(array_filter($consents->purposes(), static fn (array $p): bool => ! $p['required']));

        $validated = $request->validate([
            'consents' => ['required', 'array'],
            'consents.*' => ['boolean'],
        ]);

        /** @var array<string, bool> $choices */
        $choices = array_intersect_key(array_map('boolval', $validated['consents']), array_flip($optional));
        $consents->record($user, $choices);

        return back()->with('status', __('account.saved'));
    }

    public function requestDeletion(Request $request): RedirectResponse
    {
        $user = $this->user($request);
        $request->validate(['confirm' => ['accepted']]);

        $user->forceFill(['deletion_requested_at' => now(), 'status' => User::STATUS_DELETION_REQUESTED])->save();
        $this->audit->record('account.deletion_requested', $user);

        return back()->with('status', __('account.deletion_requested'));
    }

    private function sendEmailVerification(User $user): void
    {
        $url = URL::temporarySignedRoute('account.email.verify', now()->addDay(), ['id' => $user->id, 'hash' => sha1((string) $user->email)]);

        Mail::raw(
            "Hi {$user->displayName()},\n\nPlease confirm your email address for KasiHub by opening this link:\n{$url}\n\nIf you did not add this email address, you can ignore this message.",
            static fn ($message) => $message->to((string) $user->email)->subject('Confirm your email address'),
        );
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
