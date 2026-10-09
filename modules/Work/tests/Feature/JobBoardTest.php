<?php

declare(strict_types=1);

use Modules\Admin\Tests\Console;
use Modules\Core\Identity\Models\User;
use Modules\Core\Search\SearchService;
use Modules\Core\Structure\Models\Organisation;
use Modules\Core\Tests\Structure;
use Modules\HubOps\Tests\Staff;
use Modules\Work\Models\JobListing;

beforeEach(function (): void {
    Structure::seed($this);
    $employer = Organisation::query()->create(['type' => 'employer', 'name' => 'Giyani Bakery', 'verification_status' => 'verified', 'contact_phone' => '+27155550000', 'contact_email' => 'boss@bakery.example']);
    $giyani = Structure::city('LIM331');
    $soweto = Structure::city('JHB');
    $make = fn (string $title, $city, array $extra = []) => JobListing::query()->create([
        'organisation_id' => $employer->id, 'title' => $title, 'type' => 'part_time', 'positions' => 1, 'municipality_id' => $city->id,
        'latitude' => $city->latitude ?? -23.3, 'longitude' => $city->longitude ?? 30.7, 'pay_min_cents' => 3200, 'pay_period' => 'hour',
        'experience' => 'none', 'description' => 'Serve customers at the bakery counter.', 'closes_on' => now()->addDays(10)->toDateString(),
        'status' => 'live', 'published_at' => now(), ...$extra,
    ]);
    $this->near = $make('Cashier', $giyani);
    $this->far = $make('Baker', $soweto, ['latitude' => -26.27, 'longitude' => 27.86, 'experience' => '1_year']);
    $this->draft = $make('Cleaner', $giyani, ['status' => 'draft']);
    $this->seeker = Staff::signIn($this, User::factory()->create(['home_hub_id' => Structure::hub('LP-GIY-TSU')->id]));
});

it('lists live jobs, nearest first, with filters', function (): void {
    $this->get('/work/jobs')->assertInertia(fn ($page) => $page->component('Work/Jobs/Index')->where('jobs.0.title', 'Cashier')->has('jobs', 2)->where('hasLocation', true));
    $this->get('/work/jobs?distance=50')->assertInertia(fn ($page) => $page->has('jobs', 1));
    $this->get('/work/jobs?no_experience=1')->assertInertia(fn ($page) => $page->has('jobs', 1)->where('jobs.0.title', 'Cashier'));
    $this->get('/work/jobs?q=Cashier')->assertInertia(fn ($page) => $page->has('jobs', 1)->where('jobs.0.title', 'Cashier'));
});

it('saves jobs and counts views', function (): void {
    $this->get("/work/jobs/{$this->near->id}")->assertOk()->assertInertia(fn ($page) => $page->where('saved', false));
    $this->post("/work/jobs/{$this->near->id}/save")->assertSessionHasNoErrors();

    $this->get('/work/jobs?saved=1')->assertInertia(fn ($page) => $page->has('jobs', 1));
    expect((int) DB::table('work_listing_views')->where('listing_id', $this->near->id)->sum('views'))->toBe(1);
    $this->get("/work/jobs/{$this->draft->id}")->assertNotFound();
});

it('shows a public job page with JobPosting data and no employer contact details', function (): void {
    $this->post('/logout');

    $response = $this->get("/jobs/{$this->near->id}")->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Work/Jobs/Public')->where('open', true)
        ->where('seo.jsonLd.0.@type', 'JobPosting')->where('seo.jsonLd.0.baseSalary.currency', 'ZAR'));

    expect($response->getContent())->not->toContain('+27155550000')->not->toContain('boss@bakery.example');
    $this->get("/jobs/{$this->draft->id}")->assertInertia(fn ($page) => $page->where('open', false));
});

it('makes live jobs searchable for adults only', function (): void {
    app(SearchService::class)->reindex('job');

    expect(app(SearchService::class)->search('Cashier', $this->seeker))->not->toBe([])
        ->and(app(SearchService::class)->search('Cashier', User::factory()->create(['age_band' => 'minor', 'date_of_birth' => now()->subYears(17)])))->toBe([]);
});

it('lets content reviewers take a live advert down with a reason', function (): void {
    $this->post("/jobs/{$this->near->id}/take-down", ['reason' => 'Not allowed'])->assertForbidden();

    Console::as($this, 'content_reviewer');
    $this->post("/jobs/{$this->near->id}/take-down", ['reason' => 'Reported as a scam by two people'])->assertSessionHasNoErrors();

    expect($this->near->refresh()->status)->toBe('taken_down');
});
