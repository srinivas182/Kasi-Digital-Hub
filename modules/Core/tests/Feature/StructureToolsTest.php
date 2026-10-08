<?php

declare(strict_types=1);

use Database\Seeders\DemoSeeder;
use Modules\Core\Database\Seeders\GeographySeeder;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Geography;
use Modules\Core\Structure\Models\Hub;
use Modules\Core\Structure\Models\Municipality;
use Modules\Core\Structure\Models\Province;
use Modules\Core\Structure\Models\RoleAssignment;
use Modules\Core\Tests\Structure;

it('seeds all 9 provinces, the 8 metros and the pilot municipalities', function (): void {
    $this->seed(GeographySeeder::class);

    expect(Province::query()->count())->toBe(9)
        ->and(Municipality::query()->where('category', 'metro')->count())->toBe(8)
        ->and(Municipality::query()->where('code', 'LIM331')->first()->district->code)->toBe('DC33')
        ->and(Municipality::query()->value('source'))->toBe(GeographySeeder::SOURCE);
});

it('imports the official municipal list from CSV, safely re-runnable', function (): void {
    $file = tempnam(sys_get_temp_dir(), 'mdb');
    file_put_contents($file, "code,name,category,province_code,district_code,latitude,longitude\nDC39,Dr Ruth Segomotsi Mompati,district,NW,,,\nNW392,Naledi,local,NW,DC39,-26.95,24.73\nBAD,Bad row,town,XX,,,\n");

    $this->artisan('kasi:geography:import', ['file' => $file, '--source' => 'MDB test'])->assertFailed(); // one bad row reported
    $this->artisan('kasi:geography:import', ['file' => $file, '--source' => 'MDB test'])->assertFailed();

    expect(Municipality::query()->where('code', 'NW392')->count())->toBe(1)
        ->and(Municipality::query()->where('code', 'NW392')->first()->district->code)->toBe('DC39');
});

it('assigns, lists and revokes roles from the command line', function (): void {
    Structure::seed($this);
    $user = User::factory()->create(['phone' => '+27724183390']);

    $this->artisan('kasi:roles', ['action' => 'assign', 'phone' => '0724183390', 'role' => 'hub_facilitator', 'scope' => 'hub:LP-GIY-TSU'])->assertSuccessful();
    $this->artisan('kasi:roles', ['action' => 'list', 'phone' => '0724183390'])->expectsOutputToContain('Tsutsumani Digital Hub')->assertSuccessful();
    $this->artisan('kasi:roles', ['action' => 'assign', 'phone' => '0724183390', 'role' => 'hub_facilitator', 'scope' => 'national'])->assertFailed();
    $this->artisan('kasi:roles', ['action' => 'revoke', 'phone' => '0724183390', 'role' => 'hub_facilitator', 'scope' => 'hub:LP-GIY-TSU'])->assertSuccessful();
    $this->artisan('kasi:roles', ['action' => 'available'])->expectsOutputToContain('provincial_coordinator')->assertSuccessful();

    expect(RoleAssignment::query()->where('user_id', $user->id)->count())->toBe(0);
});

it('measures distances between places', function (): void {
    // Giyani to Polokwane is roughly 150 km in a straight line.
    expect(Geography::distanceKm(-23.302, 30.718, -23.904, 29.469))->toBeGreaterThan(130)->toBeLessThan(160);
});

it('builds the full demo structure with every demo account in its role', function (): void {
    config(['kasi.demo.enabled' => true]);
    $this->seed(DemoSeeder::class);

    expect(Hub::query()->count())->toBe(12)
        ->and(User::query()->where('phone', 'like', '+2784000%')->count())->toBe(500)
        ->and(User::query()->where('phone', '+27720000020')->first()->roleAssignments()->value('role'))->toBe('hub_facilitator')
        ->and(User::query()->where('phone', '+27720000040')->first()->two_factor_required)->toBeTrue()
        ->and(User::query()->whereNull('home_hub_id')->where('phone', 'like', '+2784000%')->count())->toBe(0);
});
