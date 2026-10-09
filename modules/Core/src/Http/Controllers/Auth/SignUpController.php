<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Auth;

use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Events\UserRegistered;
use Modules\Core\Http\Controllers\Auth\Concerns\InteractsWithAuthFlow;
use Modules\Core\Identity\Models\ConsentDocument;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AccountCreator;
use Modules\Core\Identity\Services\AgePolicy;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Identity\Services\Authenticator;
use Modules\Core\Identity\Services\ConsentService;
use Modules\Core\Identity\Services\PinPolicy;
use Modules\Core\Structure\Models\Hub;
use Modules\Core\Structure\Options;

/**
 * New account after the phone number is verified: PIN, date of birth (age policy),
 * names and consent per purpose.
 */
final class SignUpController
{
    use InteractsWithAuthFlow;

    public function show(Request $request, ConsentService $consents): Response|RedirectResponse
    {
        if (! $this->canSignUp($request)) {
            return to_route('login');
        }

        return Inertia::render('Core/Auth/SignUp', [
            'phone' => $this->maskPhone((string) $this->flowPhone($request)),
            'purposes' => array_keys(array_filter($consents->purposes(), static fn (array $p): bool => ! $p['required'])),
            'documents' => $consents->currentDocuments()->map(static fn (ConsentDocument $d): array => [
                'key' => $d->key, 'version' => $d->version, 'title' => $d->title, 'summary' => $d->summary,
            ])->values(),
            'age' => ['fullAccess' => (int) config('kasi.age.full_access_age'), 'minorMin' => (int) config('kasi.age.minor_min_age')],
            'hubOptions' => Options::hubs(),
        ]);
    }

    public function store(Request $request, AccountCreator $creator, AuditLogger $audit, Authenticator $authenticator): RedirectResponse
    {
        if (! $this->canSignUp($request)) {
            return to_route('login');
        }

        $validated = $request->validate([
            'pin' => ['required', 'string', 'confirmed'],
            'date_of_birth' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'preferred_name' => ['nullable', 'string', 'max:80'],
            'home_hub_id' => ['nullable', 'string', Rule::exists(Hub::class, 'id')->where('status', 'live')],
            'accept_terms' => ['accepted'],
            'consents' => ['array'],
            'consents.*' => ['boolean'],
            'remember' => ['boolean'],
        ]);

        if ($problem = PinPolicy::problem($validated['pin'])) {
            return back()->withErrors(['pin' => __($problem)]);
        }

        $band = AgePolicy::classify(CarbonImmutable::parse($validated['date_of_birth']));

        if ($band === AgePolicy::TOO_YOUNG) {
            $audit->record('signup.declined_age', outcome: 'blocked');
            $request->session()->forget('auth_flow');

            return to_route('signup.declined');
        }

        /** @var array<string, bool> $optional */
        $optional = array_map('boolval', $validated['consents'] ?? []);
        $user = $creator->create((string) $this->flowPhone($request), $validated, $band, $optional);

        if ($band === AgePolicy::MINOR) {
            $this->updateFlow($request, ['stage' => 'guardian', 'user_id' => $user->id]);

            return to_route('signup.guardian');
        }

        event(new UserRegistered($user));
        $authenticator->login($user, $request->boolean('remember'));

        return to_route('hub.home')->with('welcome', true);
    }

    public function declined(): Response
    {
        return Inertia::render('Core/Auth/Declined', ['minimumAge' => (int) config('kasi.age.minor_min_age')]);
    }

    private function canSignUp(Request $request): bool
    {
        $flow = $this->flow($request);
        $phone = $this->flowPhone($request);

        return $phone !== null
            && ($flow['stage'] ?? null) === 'signup'
            && ($flow['verified'] ?? false) === true
            && ! User::withTrashed()->where('phone', $phone)->exists();
    }
}
