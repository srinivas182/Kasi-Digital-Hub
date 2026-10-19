<?php

declare(strict_types=1);

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Modules\Core\Identity\Models\User;
use Modules\HubOps\Tests\Staff;
use Modules\Work\Models\JobListing;

/* S23: the public job board - live jobs only, searchable, nothing personal; signed-in adults use the in-app board. */
it('lists live jobs for anyone, filters them, and hides closed or draft adverts', function (): void {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DemoSeeder::class);
    $live = JobListing::query()->live()->count();
    expect($live)->toBeGreaterThan(0);

    $this->get('/jobs')->assertOk()->assertInertia(fn ($page) => $page->component('Work/Board')->where('jobs.total', $live)
        ->where('jobs.data', fn ($jobs) => collect($jobs)->every(fn ($j) => ! array_key_exists('phone', $j) && ! array_key_exists('applicants', $j))));
    $closed = JobListing::query()->live()->firstOrFail();
    $closed->forceFill(['status' => 'closed'])->save();
    $this->get('/jobs')->assertInertia(fn ($page) => $page->where('jobs.total', $live - 1));
    $this->get('/jobs?type=internship')->assertInertia(fn ($page) => $page->where('jobs.data', fn ($jobs) => collect($jobs)->every(fn ($j) => $j['type'] === 'internship')));

    Staff::signIn($this, User::query()->where('phone', '+27720000001')->firstOrFail());
    $this->get('/jobs')->assertRedirect(route('work.jobs'));
});
