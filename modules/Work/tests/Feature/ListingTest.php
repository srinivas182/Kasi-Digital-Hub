<?php

declare(strict_types=1);

use Modules\Admin\Tests\Console;
use Modules\Core\Access\Scope;
use Modules\Core\Ai\Drivers\FakeAiDriver;
use Modules\Core\Ai\Models\ModerationFlag;
use Modules\Core\Platform\Models\PlatformEventRecord;
use Modules\Core\Platform\Models\Update;
use Modules\Core\Search\SearchService;
use Modules\Core\Structure\Models\Organisation;
use Modules\Core\Tests\Structure;
use Modules\HubOps\Tests\Staff;
use Modules\Work\Database\Seeders\OccupationSeeder;
use Modules\Work\Models\JobListing;
use Modules\Work\Models\OfoOccupation;
use Modules\Work\Services\ListingChecks;
use Modules\Work\Services\Listings;

beforeEach(function (): void {
    Structure::seed($this);
    $this->seed(OccupationSeeder::class);
    $this->employer = Organisation::query()->create(['type' => 'employer', 'name' => 'Giyani Bakery', 'verification_status' => 'verified', 'verified_at' => now(), 'municipality_id' => Structure::city('LIM331')->id]);
    $this->owner = Structure::personWith('employer_admin', Scope::organisation($this->employer));
    Staff::signIn($this, $this->owner);
});

function advert(array $overrides = []): array
{
    return [
        'title' => 'Cashier', 'occupation_id' => OfoOccupation::query()->where('title', 'Cashier')->value('id'), 'type' => 'part_time', 'positions' => 2,
        'municipality_id' => Structure::city('LIM331')->id, 'place_name' => 'Giyani', 'pay_min' => 32, 'pay_period' => 'hour', 'experience' => 'none',
        'description' => 'Serve customers at our busy bakery counter on weekends. We train you. Bring energy and a smile.',
        'closes_on' => now()->addDays(20)->toDateString(), 'skills' => [['name' => 'Customer service', 'must' => true]],
        'questions' => [['question' => 'Can you work weekends?', 'kind' => 'yes_no']], ...$overrides,
    ];
}

it('saves a draft and publishes it live', function (): void {
    $this->post('/work/employer/listings', advert())->assertSessionHasNoErrors();
    $listing = JobListing::query()->sole();
    expect($listing->status)->toBe('draft')->and($listing->skills()->count())->toBe(1)->and($listing->latitude)->not->toBeNull();

    $this->post("/work/employer/listings/{$listing->id}/publish")->assertRedirect('/work/employer');
    expect($listing->refresh()->status)->toBe('live')
        ->and(PlatformEventRecord::query()->where('name', 'work.listing.published')->exists())->toBeTrue()
        ->and(SearchService::class)->not->toBeNull();
});

it('refuses pay below the national minimum wage, but allows learnership stipends', function (): void {
    $this->post('/work/employer/listings', advert(['pay_min' => 20, 'pay_period' => 'hour']))->assertSessionHasErrors('pay_min');
    $this->post('/work/employer/listings', advert(['pay_min' => 3000, 'pay_period' => 'month']))->assertSessionHasErrors('pay_min');
    $this->post('/work/employer/listings', advert(['pay_min' => 5200, 'pay_period' => 'month']))->assertSessionHasNoErrors();
    $this->post('/work/employer/listings', advert(['type' => 'learnership', 'pay_min' => 2500, 'pay_period' => 'month']))->assertSessionHasNoErrors();
});

it('refuses screening questions about protected or private things', function (string $question): void {
    $this->post('/work/employer/listings', advert(['questions' => [['question' => $question, 'kind' => 'short']]]))->assertSessionHasErrors('questions.0.question');
})->with(['How old are you?', 'Which church do you attend?', 'Are you married?', 'Are you pregnant?', 'What is your ID number?', 'Are you HIV positive?']);

it('holds unfair or suspicious adverts for review, and a reviewer decides', function (): void {
    $this->post('/work/employer/listings', advert(['description' => 'Ladies only, under 30. Serve customers at the bakery counter on weekends.', 'publish' => true]))->assertSessionHasNoErrors();

    $listing = JobListing::query()->sole();
    expect($listing->status)->toBe('review')->and($listing->status_reason)->toContain('Prefers a gender')->toContain('Prefers an age')
        ->and(Update::query()->where('user_id', $this->owner->id)->where('title', 'like', 'Your job advert is being checked%')->exists())->toBeTrue();

    $flag = ModerationFlag::query()->where('subject_type', 'job_listing')->sole();
    Console::as($this, 'content_reviewer');
    $this->post("/admin/moderation/{$flag->id}", ['decision' => 'rejected', 'reason' => 'Unfair gender and age preference'])->assertSessionHasNoErrors();

    expect($listing->refresh()->status)->toBe('taken_down')->and($listing->status_reason)->toBe('Unfair gender and age preference')
        ->and(Update::query()->where('user_id', $this->owner->id)->where('title', 'like', 'Your job advert was taken down%')->exists())->toBeTrue();
});

it('allows lawful employment equity wording', function (): void {
    expect(app(ListingChecks::class)->reviewReasons('Preference will be given to black candidates only in line with our employment equity plan.'))->toBe([]);
});

it('publishes an approved advert after review', function (): void {
    $this->post('/work/employer/listings', advert(['description' => 'Pay a R100 registration fee to secure your place. Serve customers on weekends.', 'publish' => true]));
    $listing = JobListing::query()->sole();
    expect($listing->status)->toBe('review');

    $flag = ModerationFlag::query()->sole();
    Console::as($this, 'operations_admin');
    $this->post("/admin/moderation/{$flag->id}", ['decision' => 'approved', 'reason' => 'Fee removed after a call'])->assertSessionHasNoErrors();

    expect($listing->refresh()->status)->toBe('live');
});

it('only lets verified employers publish, within the free listing limit', function (): void {
    $this->employer->forceFill(['verification_status' => 'pending'])->save();
    $this->post('/work/employer/listings', advert(['publish' => true]));
    expect(JobListing::query()->sole()->status)->toBe('draft');

    $this->employer->forceFill(['verification_status' => 'verified'])->save();
    JobListing::query()->delete();
    foreach (range(1, 4) as $i) {
        $this->post('/work/employer/listings', advert(['title' => "Cashier {$i}", 'publish' => true]));
    }

    expect(JobListing::query()->where('status', 'live')->count())->toBe(3)->and(JobListing::query()->where('status', 'draft')->count())->toBe(1);
});

it('re-checks a live advert when it is changed', function (): void {
    $this->post('/work/employer/listings', advert(['publish' => true]));
    $listing = JobListing::query()->sole();

    $this->put("/work/employer/listings/{$listing->id}", advert(['description' => 'Males only. Serve customers at our busy bakery counter on weekends.']))->assertSessionHasNoErrors();

    expect($listing->refresh()->status)->toBe('review');
});

it('closes, expires, reminds and renews adverts', function (): void {
    $this->post('/work/employer/listings', advert(['publish' => true, 'closes_on' => now()->addDays(2)->toDateString()]));
    $listing = JobListing::query()->sole();

    expect(app(Listings::class)->housekeeping())->toBe(['expired' => 0, 'reminded' => 1]);
    $this->travel(3)->days();
    expect(app(Listings::class)->housekeeping()['expired'])->toBe(1)->and($listing->refresh()->status)->toBe('expired');

    Staff::signIn($this, $this->owner);
    $this->post("/work/employer/listings/{$listing->id}/renew", ['closes_on' => now()->addDays(10)->toDateString()]);
    expect($listing->refresh()->status)->toBe('live');

    $this->post("/work/employer/listings/{$listing->id}/close", ['status' => 'filled']);
    expect($listing->refresh()->status)->toBe('filled');
});

it('suggests advert fields from rough notes, with the occupation from the list', function (): void {
    FakeAiDriver::respondWith((string) json_encode([
        'title' => 'Cashier', 'occupation' => 'Cashier', 'description' => 'Serve customers at our bakery on weekends.',
        'must_skills' => ['Customer service'], 'nice_skills' => ['Cash handling'], 'questions' => ['Can you work weekends?', 'How old are you?'],
    ]));

    $this->postJson('/work/employer/listings/write', ['notes' => 'need 2 cashiers giyani bakery weekends R32 an hour'])
        ->assertOk()->assertJson(['ok' => true, 'title' => 'Cashier', 'occupation' => ['title' => 'Cashier']])
        ->assertJsonPath('questions', ['Can you work weekends?']); // the age question is dropped
});

it('does not let other employers edit an advert', function (): void {
    $this->post('/work/employer/listings', advert());
    $listing = JobListing::query()->sole();
    $other = Organisation::query()->create(['type' => 'employer', 'name' => 'Other Co', 'verification_status' => 'verified']);
    Staff::signIn($this, Structure::personWith('employer_admin', Scope::organisation($other)));

    $this->get("/work/employer/listings/{$listing->id}/edit")->assertNotFound();
    $this->post("/work/employer/listings/{$listing->id}/publish")->assertNotFound();
});
