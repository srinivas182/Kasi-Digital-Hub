<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Identity\Models\User;

it('counts rate limits per route, so one feature cannot use up another\'s allowance', function (): void {
    Route::middleware(['throttle:2,10'])->get('/_test/a', fn () => 'a')->name('test.a');
    Route::middleware(['throttle:2,10'])->get('/_test/b', fn () => 'b')->name('test.b');
    $this->actingAs(User::factory()->create());

    $this->get('/_test/a')->assertOk();
    $this->get('/_test/a')->assertOk();
    $this->get('/_test/a')->assertStatus(429);
    $this->get('/_test/b')->assertOk(); // not affected by /_test/a
});
