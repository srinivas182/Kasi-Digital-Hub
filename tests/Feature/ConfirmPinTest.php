<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Modules\Core\Access\Scope;
use Modules\Core\Identity\Services\ConsentService;
use Modules\Core\Tests\Structure;

/* S23: staff confirm their PIN before sensitive actions, at most every 15 minutes. */
it('asks staff to confirm their PIN before a sensitive action, then lets them through for 15 minutes', function (): void {
    Structure::seed($this);
    $admin = Structure::personWith('super_admin', Scope::national());
    $admin->forceFill(['pin' => Hash::make('24680')])->save();
    $device = $admin->devices()->create(['name' => 'Test browser']);
    app(ConsentService::class)->record($admin, ['platform' => true]);
    $this->actingAs($admin)->withSession(['device_id' => $device->id, 'last_activity_at' => now()->getTimestamp()]);

    $this->get('/admin/audit/export')->assertRedirect(route('pin.confirm'));
    $this->post('/confirm-pin', ['pin' => '11111'])->assertSessionHasErrors('pin');
    $this->post('/confirm-pin', ['pin' => '24680'])->assertRedirect('/admin/audit/export');
    $this->get('/admin/audit/export')->assertOk();

    $this->travel(16)->minutes();
    $this->get('/admin/audit/export')->assertRedirect(route('pin.confirm'));
});

it('signs the person out after five wrong PINs', function (): void {
    Structure::seed($this);
    $admin = Structure::personWith('super_admin', Scope::national());
    $admin->forceFill(['pin' => Hash::make('24680')])->save();
    $this->actingAs($admin);
    foreach (range(1, 5) as $i) {
        $this->post('/confirm-pin', ['pin' => '00000']);
    }
    $this->assertGuest();
});
