<?php

declare(strict_types=1);

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Core\Access\HubEntitlements;
use Modules\Core\Structure\Models\Hub;
use Modules\Core\Structure\Models\Municipality;
use Modules\Core\Structure\Models\Organisation;
use Modules\Core\Structure\Models\Place;

/**
 * Demo national structure: 3 provinces, 6 cities, 12 hubs on different packages, and
 * fictitious organisations (employers, a training provider, demo banks/insurer, funders).
 */
final class DemoHierarchySeeder extends Seeder
{
    /** @var list<array{code: string, name: string, city: string, place: string, kind: string, lat: float, lng: float, package: string, status: string}> */
    public const HUBS = [
        ['code' => 'LP-GIY-TSU', 'name' => 'Tsutsumani Digital Hub', 'city' => 'LIM331', 'place' => 'Tsutsumani', 'kind' => 'village', 'lat' => -23.270, 'lng' => 30.780, 'package' => 'full', 'status' => 'live'],
        ['code' => 'LP-GIY-CEN', 'name' => 'Giyani Central Hub', 'city' => 'LIM331', 'place' => 'Giyani', 'kind' => 'town', 'lat' => -23.302, 'lng' => 30.718, 'package' => 'enterprise', 'status' => 'live'],
        ['code' => 'LP-COL-MAL', 'name' => 'Malamulele Hub', 'city' => 'LIM345', 'place' => 'Malamulele', 'kind' => 'township', 'lat' => -23.000, 'lng' => 30.700, 'package' => 'growth', 'status' => 'live'],
        ['code' => 'LP-TZA-NKO', 'name' => 'Nkowankowa Hub', 'city' => 'LIM333', 'place' => 'Nkowankowa', 'kind' => 'township', 'lat' => -23.880, 'lng' => 30.290, 'package' => 'base', 'status' => 'live'],
        ['code' => 'GP-JHB-SOW', 'name' => 'Soweto Hub', 'city' => 'JHB', 'place' => 'Soweto', 'kind' => 'township', 'lat' => -26.267, 'lng' => 27.858, 'package' => 'full', 'status' => 'live'],
        ['code' => 'GP-JHB-ALX', 'name' => 'Alexandra Hub', 'city' => 'JHB', 'place' => 'Alexandra', 'kind' => 'township', 'lat' => -26.103, 'lng' => 28.097, 'package' => 'growth', 'status' => 'live'],
        ['code' => 'GP-JHB-DIE', 'name' => 'Diepsloot Hub', 'city' => 'JHB', 'place' => 'Diepsloot', 'kind' => 'township', 'lat' => -25.933, 'lng' => 28.012, 'package' => 'base', 'status' => 'live'],
        ['code' => 'GP-EKU-TEM', 'name' => 'Tembisa Hub', 'city' => 'EKU', 'place' => 'Tembisa', 'kind' => 'township', 'lat' => -25.996, 'lng' => 28.227, 'package' => 'enterprise', 'status' => 'live'],
        ['code' => 'GP-EKU-KAT', 'name' => 'Katlehong Hub', 'city' => 'EKU', 'place' => 'Katlehong', 'kind' => 'township', 'lat' => -26.335, 'lng' => 28.150, 'package' => 'growth', 'status' => 'live'],
        ['code' => 'KZN-ETH-UML', 'name' => 'Umlazi Hub', 'city' => 'ETH', 'place' => 'Umlazi', 'kind' => 'township', 'lat' => -29.970, 'lng' => 30.884, 'package' => 'full', 'status' => 'live'],
        ['code' => 'KZN-ETH-KWM', 'name' => 'KwaMashu Hub', 'city' => 'ETH', 'place' => 'KwaMashu', 'kind' => 'township', 'lat' => -29.744, 'lng' => 30.973, 'package' => 'enterprise', 'status' => 'live'],
        ['code' => 'KZN-ETH-INA', 'name' => 'Inanda Hub', 'city' => 'ETH', 'place' => 'Inanda', 'kind' => 'township', 'lat' => -29.700, 'lng' => 30.940, 'package' => 'base', 'status' => 'planned'],
    ];

    /** @var list<array{key: string, type: string, name: string, reg: string|null, city: string|null}> */
    public const ORGANISATIONS = [
        ['key' => 'platform', 'type' => 'platform', 'name' => 'Ku Tirhisana Consultancy (Pty) Ltd', 'reg' => '2024/003263/07', 'city' => null],
        ['key' => 'tsu_operator', 'type' => 'hub_operator', 'name' => 'Tsutsumani Youth Development Co-operative', 'reg' => null, 'city' => 'LIM331'],
        ['key' => 'mopani_fresh', 'type' => 'employer', 'name' => 'Mopani Fresh Market', 'reg' => '2017/412233/07', 'city' => 'LIM331'],
        ['key' => 'baloyi_wholesalers', 'type' => 'employer', 'name' => 'Baloyi Wholesalers', 'reg' => '2019/220561/07', 'city' => 'LIM331'],
        ['key' => 'hbm', 'type' => 'training_provider', 'name' => 'HBM EduTech', 'reg' => null, 'city' => null],
        ['key' => 'ubuntu_bank', 'type' => 'partner', 'name' => 'Ubuntu Community Bank (demo)', 'reg' => null, 'city' => 'JHB'],
        ['key' => 'kasi_mutual', 'type' => 'partner', 'name' => 'Kasi Mutual Insurance (demo)', 'reg' => null, 'city' => 'JHB'],
        ['key' => 'lp_youth_fund', 'type' => 'funder', 'name' => 'Limpopo Youth Skills Programme (sample funder)', 'reg' => null, 'city' => null],
        ['key' => 'retail_skills', 'type' => 'funder', 'name' => 'Retail Skills Fund (sample SETA-style funder)', 'reg' => null, 'city' => null],
        ['key' => 'kasi_futures', 'type' => 'funder', 'name' => 'Kasi Futures Foundation (sample corporate sponsor)', 'reg' => null, 'city' => null],
    ];

    public function run(HubEntitlements $entitlements): void
    {
        $this->call(GeographySeeder::class);

        foreach (self::ORGANISATIONS as $org) {
            Organisation::query()->updateOrCreate(['name' => $org['name']], [
                'type' => $org['type'],
                'registration_number' => $org['reg'],
                'verification_status' => 'verified',
                'verified_at' => now(),
                'municipality_id' => $org['city'] !== null ? Municipality::query()->where('code', $org['city'])->value('id') : null,
            ]);
        }

        $operator = Organisation::query()->where('name', 'Tsutsumani Youth Development Co-operative')->value('id');

        foreach (self::HUBS as $data) {
            $city = Municipality::query()->where('code', $data['city'])->firstOrFail();
            $place = Place::query()->updateOrCreate(
                ['municipality_id' => $city->id, 'name' => $data['place']],
                ['kind' => $data['kind'], 'latitude' => $data['lat'], 'longitude' => $data['lng']],
            );

            $slug = Str::slug($data['place']);
            $hub = Hub::query()->updateOrCreate(['code' => $data['code']], [
                'name' => $data['name'],
                'slug' => $slug,
                'description' => "Free help with CVs, job applications, courses and business registration for young people in {$data['place']} and nearby.",
                'phone' => sprintf('+2715%07d', 100000 + crc32($data['code']) % 900000),
                'email' => $slug.'@kasidigitalhub.co.za',
                'address' => "Main Road, {$data['place']}",
                'municipality_id' => $city->id,
                'place_id' => $place->id,
                'latitude' => $data['lat'],
                'longitude' => $data['lng'],
                'status' => $data['status'],
                'opening_hours' => ['mon-fri' => '08:00-17:00', 'sat' => '09:00-13:00'],
                'operator_organisation_id' => $data['code'] === 'LP-GIY-TSU' ? $operator : null,
            ]);

            $entitlements->applyPackage($hub, $data['package']);
        }
    }
}
