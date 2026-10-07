<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;

it('renders the platform shell with the release 1 portals', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Site/Home')
            ->where('platform.brand', 'KasiHub')
            ->has('portals', 12)
        );
});
