<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Support\Format\SaFormat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Access\HubEntitlements;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Structure\Models\Hub;
use Modules\Core\Structure\Models\Municipality;
use Modules\Core\Structure\Models\Organisation;
use Modules\Core\Structure\Models\Place;
use Modules\Core\Structure\Models\RoleAssignment;

/**
 * Hubs: list, create, edit, package and add-ons, staff.
 */
final class HubsController
{
    use Concerns;

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Hubs/Index', [
            'hubs' => Hub::query()->with('municipality.province')->withCount(['modules' => fn ($q) => $q->where('enabled', true)])->orderBy('name')->get()
                ->map(static fn (Hub $h): array => [
                    'id' => $h->id, 'code' => $h->code, 'name' => $h->name, 'city' => $h->municipality->name,
                    'province' => $h->municipality->province->name, 'status' => $h->status, 'package' => $h->package,
                    'people' => DB::table('users')->where('home_hub_id', $h->id)->count(),
                ]),
            'canManage' => $this->can($request, 'admin.hubs.manage'),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->form($request, null);
    }

    public function edit(Request $request, Hub $hub): Response
    {
        return $this->form($request, $hub);
    }

    public function store(Request $request, HubEntitlements $entitlements, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request, null);
        $hub = Hub::query()->create($this->attributes($data));
        $entitlements->applyPackage($hub, $data['package'], $this->actor($request));
        $audit->record('hub.created', meta: ['hub' => $hub->code], actor: $this->actor($request));

        return to_route('admin.hubs.edit', $hub)->with('status', __('admin.hubs.created'));
    }

    public function update(Request $request, Hub $hub, HubEntitlements $entitlements, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request, $hub);
        $packageChanged = $data['package'] !== $hub->package;
        $hub->update($this->attributes($data));

        if ($packageChanged) {
            $entitlements->applyPackage($hub, $data['package'], $this->actor($request));
        }

        $audit->record('hub.updated', meta: ['hub' => $hub->code, 'changed' => array_keys($hub->getChanges())], actor: $this->actor($request));

        return back()->with('status', __('admin.saved'));
    }

    public function toggleModule(Request $request, Hub $hub, HubEntitlements $entitlements): RedirectResponse
    {
        $validated = $request->validate([
            'module' => ['required', Rule::in((array) config('kasi.hubs.delivered_modules'))],
            'enabled' => ['required', 'boolean'],
        ]);

        $validated['enabled']
            ? $entitlements->addOn($hub, $validated['module'], $this->actor($request))
            : $entitlements->disable($hub, $validated['module'], $this->actor($request));

        return back()->with('status', __('admin.saved'));
    }

    private function form(Request $request, ?Hub $hub): Response
    {
        return Inertia::render('Admin/Hubs/Edit', [
            'hub' => $hub === null ? null : [
                'id' => $hub->id, 'code' => $hub->code, 'name' => $hub->name, 'slug' => $hub->slug, 'description' => $hub->description,
                'municipalityId' => $hub->municipality_id, 'placeName' => $hub->place?->name, 'address' => $hub->address,
                'latitude' => $hub->latitude, 'longitude' => $hub->longitude, 'phone' => $hub->phone, 'email' => $hub->email,
                'status' => $hub->status, 'package' => $hub->package, 'operatorId' => $hub->operator_organisation_id,
                'openingHours' => $hub->opening_hours ?? [],
            ],
            'modules' => $hub === null ? [] : collect((array) config('kasi.hubs.delivered_modules'))->map(fn (string $m): array => [
                'module' => $m,
                'enabled' => app(HubEntitlements::class)->enabled($hub, $m),
                'inPackage' => in_array($m, HubEntitlements::packageModules($hub->package), true),
            ])->values(),
            'staff' => $hub === null ? [] : RoleAssignment::query()->with('user')->where('scope_type', 'hub')->where('scope_id', $hub->id)->get()
                ->map(static fn (RoleAssignment $a): array => ['userId' => $a->user_id, 'name' => $a->user?->fullName(), 'role' => $a->role]),
            'options' => [
                'cities' => Municipality::query()->with('province:id,name')->where('category', '!=', 'district')->orderBy('name')->get()
                    ->map(static fn (Municipality $m): array => ['id' => $m->id, 'name' => $m->name.' ('.$m->province->name.')']),
                'operators' => Organisation::query()->where('type', 'hub_operator')->orderBy('name')->get(['id', 'name']),
                'packages' => HubEntitlements::packages(),
                'statuses' => Hub::STATUSES,
            ],
            'canManage' => $this->can($request, 'admin.hubs.manage'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Hub $hub): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:24', 'regex:/^[A-Z0-9-]+$/', Rule::unique('hubs', 'code')->ignore($hub?->id)],
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/', Rule::unique('hubs', 'slug')->ignore($hub?->id)],
            'description' => ['nullable', 'string', 'max:400'],
            'municipality_id' => ['required', 'integer', Rule::exists(Municipality::class, 'id')->whereNot('category', 'district')],
            'place_name' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-35,-22'],
            'longitude' => ['nullable', 'numeric', 'between:16,33'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'status' => ['required', Rule::in(Hub::STATUSES)],
            'package' => ['required', Rule::in(HubEntitlements::packages())],
            'operator_organisation_id' => ['nullable', 'string', Rule::exists(Organisation::class, 'id')->where('type', 'hub_operator')],
            'opening_hours' => ['nullable', 'array'],
            'opening_hours.*' => ['nullable', 'string', 'max:40'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $place = null;
        if (! empty($data['place_name'])) {
            $place = Place::query()->firstOrCreate(
                ['municipality_id' => $data['municipality_id'], 'name' => trim((string) $data['place_name'])],
                ['kind' => 'village', 'latitude' => $data['latitude'] ?? null, 'longitude' => $data['longitude'] ?? null],
            );
        }

        return [
            'code' => $data['code'], 'name' => $data['name'], 'slug' => $data['slug'], 'description' => $data['description'] ?? null,
            'municipality_id' => $data['municipality_id'], 'place_id' => $place?->id, 'address' => $data['address'] ?? null,
            'latitude' => $data['latitude'] ?? null, 'longitude' => $data['longitude'] ?? null,
            'phone' => isset($data['phone']) ? SaFormat::normalisePhone((string) $data['phone']) : null,
            'email' => $data['email'] ?? null, 'status' => $data['status'], 'package' => $data['package'],
            'operator_organisation_id' => $data['operator_organisation_id'] ?? null,
            'opening_hours' => array_filter((array) ($data['opening_hours'] ?? [])) ?: null,
        ];
    }
}
