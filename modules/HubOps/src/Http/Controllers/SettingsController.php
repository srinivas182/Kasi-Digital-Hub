<?php

declare(strict_types=1);

namespace Modules\HubOps\Http\Controllers;

use App\Support\Format\SaFormat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Modules\Core\Access\RoleAssignments;
use Modules\Core\Access\Scope;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Structure\Models\RoleAssignment;
use Modules\HubOps\Services\CheckInCodes;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Hub managers: public details, the hub's internet connection, the door-screen link and facilitators.
 */
final class SettingsController extends StaffController
{
    public function show(Request $request): Response
    {
        $hub = $this->hub($request);

        return Inertia::render('HubOps/Settings', [
            'hubs' => $this->hubProps($request, $hub),
            'hub' => [
                'id' => $hub->id, 'slug' => $hub->slug, 'description' => $hub->description, 'phone' => $hub->phone !== null ? SaFormat::phone($hub->phone) : null,
                'email' => $hub->email, 'openingHours' => $hub->opening_hours ?? [], 'trustedIps' => $hub->trusted_ips ?? [],
                'kioskConfigured' => $hub->kiosk_token_hash !== null,
            ],
            'kioskUrl' => $request->session()->get('kiosk_url'),
            'currentIp' => $request->ip(),
            'facilitators' => RoleAssignment::query()->with('user:id,first_name,last_name,phone')->where('role', 'hub_facilitator')
                ->where('scope_type', 'hub')->where('scope_id', $hub->id)->get()
                ->map(static fn (RoleAssignment $a): array => ['id' => $a->id, 'name' => $a->user?->fullName(), 'phone' => $a->user !== null ? SaFormat::maskedPhone($a->user->phone) : null]),
        ]);
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $hub = $this->hub($request);
        $validated = $request->validate([
            'description' => ['nullable', 'string', 'max:400'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'opening_hours' => ['nullable', 'array'],
            'opening_hours.*' => ['nullable', 'string', 'max:40'],
            'trusted_ips' => ['nullable', 'array', 'max:10'],
            'trusted_ips.*' => ['string', 'max:64', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_string($value) || ! $this->validIpOrRange($value)) {
                    $fail(__('hubops.settings.bad_ip'));
                }
            }],
        ]);

        $hub->update([
            'description' => $validated['description'] ?? null,
            'phone' => isset($validated['phone']) ? SaFormat::normalisePhone((string) $validated['phone']) : null,
            'email' => $validated['email'] ?? null,
            'opening_hours' => array_filter((array) ($validated['opening_hours'] ?? [])) ?: null,
            'trusted_ips' => array_values(array_unique(array_filter((array) ($validated['trusted_ips'] ?? [])))) ?: null,
        ]);
        $audit->record('hub.settings_updated', meta: ['hub' => $hub->id, 'changed' => array_keys($hub->getChanges())], actor: $this->actor($request));

        return back()->with('status', __('hubops.saved'));
    }

    /** A new door-screen link; the old one stops working. Shown once. */
    public function kiosk(Request $request, CheckInCodes $codes, AuditLogger $audit): RedirectResponse
    {
        $hub = $this->hub($request);
        $token = $codes->issueKioskToken($hub);
        $audit->record('hub.kiosk_link_issued', meta: ['hub' => $hub->id], actor: $this->actor($request));

        return back()->with('kiosk_url', route('kiosk.door', ['hub' => $hub->slug, 'token' => $token]));
    }

    public function appoint(Request $request, RoleAssignments $assignments): RedirectResponse
    {
        $hub = $this->hub($request);
        $validated = $request->validate(['phone' => ['required', 'string', 'max:20'], 'reason' => ['required', 'string', 'min:5', 'max:300']]);
        $phone = SaFormat::normalisePhone($validated['phone']);
        $person = $phone !== null ? User::query()->where('phone', $phone)->first() : null;

        if ($person === null) {
            return back()->withErrors(['phone' => __('hubops.events.no_account')]);
        }

        try {
            $assignments->assign($person, 'hub_facilitator', Scope::hub($hub), $this->actor($request), reason: $validated['reason']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['phone' => $e->getMessage()]);
        }

        return back()->with('status', __('hubops.settings.appointed', ['name' => $person->fullName()]));
    }

    public function remove(Request $request, RoleAssignment $assignment, RoleAssignments $assignments): RedirectResponse
    {
        $hub = $this->hub($request);
        abort_unless($assignment->role === 'hub_facilitator' && $assignment->scope_type === 'hub' && $assignment->scope_id === $hub->id, 404);
        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:300']]);
        abort_if($assignment->user_id === $this->actor($request)->id || $assignment->user === null, 403);

        $assignments->revoke($assignment->user, 'hub_facilitator', Scope::hub($hub), $this->actor($request), $validated['reason']);

        return back()->with('status', __('hubops.settings.removed'));
    }

    private function validIpOrRange(string $value): bool
    {
        [$ip, $bits] = array_pad(explode('/', $value, 2), 2, null);

        if (! is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        // Ranges no wider than /16 (IPv4) or /48 (IPv6): one hub, not a whole network.
        return $bits === null || (ctype_digit($bits) && (int) $bits >= (str_contains($ip, ':') ? 48 : 16) && IpUtils::checkIp($ip, $value));
    }
}
