<?php

declare(strict_types=1);

use Modules\Admin\Tests\Console;
use Modules\Core\Access\Scope;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\ConsentService;
use Modules\Core\Platform\Models\Update;
use Modules\Core\Structure\Models\Organisation;
use Modules\Core\Tests\Structure;
use Modules\HubOps\Tests\Staff;
use Modules\Work\Database\Seeders\SkillSynonymSeeder;
use Modules\Work\Matching\Invitations;
use Modules\Work\Matching\MatchIndex;
use Modules\Work\Models\JobListing;
use Modules\Work\Models\WorkExperience;
use Modules\Work\Services\Listings;
use Modules\Work\Services\SeekerProfile;

beforeEach(function (): void {
    Structure::seed($this);
    $this->seed(SkillSynonymSeeder::class);
    $this->employer = Organisation::query()->create(['type' => 'employer', 'name' => 'Giyani Bakery', 'sector' => 'retail', 'verification_status' => 'verified', 'verified_at' => now()]);
    $this->owner = Structure::personWith('employer_admin', Scope::organisation($this->employer));
    $giyani = Structure::city('LIM331');
    $this->listing = JobListing::query()->create([
        'organisation_id' => $this->employer->id, 'created_by' => $this->owner->id, 'title' => 'Cashier', 'type' => 'part_time', 'positions' => 2,
        'municipality_id' => $giyani->id, 'latitude' => -23.30, 'longitude' => 30.72, 'pay_min_cents' => 3200, 'pay_period' => 'hour',
        'experience' => 'none', 'description' => 'Serve customers at the bakery.', 'closes_on' => now()->addDays(10)->toDateString(), 'status' => 'live', 'published_at' => now(),
    ]);
    $this->listing->skills()->create(['name' => 'Cash handling', 'must' => true]);
});

function seeker(bool $consent = true, array $user = []): User
{
    $person = User::factory()->create(['home_hub_id' => Structure::hub('LP-GIY-TSU')->id, 'first_name' => 'Thandi', 'last_name' => 'Mabasa', ...$user]);
    app(ConsentService::class)->record($person, ['platform' => true, 'job_matching' => $consent]);
    app(SeekerProfile::class)->save($person, ['headline' => 'Friendly cashier', 'summary' => 'Good with people', 'drivers_licence' => 'none', 'max_travel_km' => 40]);
    DB::table('work_skills')->insert(['user_id' => $person->id, 'name' => 'Till operation']);
    WorkExperience::query()->create(['user_id' => $person->id, 'kind' => 'family_business', 'title' => 'Shop assistant', 'duration' => '2 years']);
    app(MatchIndex::class)->refreshSeeker($person->id);

    return $person;
}

it('shows young people the jobs that suit them, with reasons', function (): void {
    $person = seeker();
    Staff::signIn($this, $person);

    $this->get('/work/matches')->assertInertia(fn ($page) => $page->component('Work/Matches')
        ->where('matches.0.title', 'Cashier')->where('matches.0.score', fn ($s) => $s >= 90)
        ->where('matches.0.reasons', fn ($r) => collect($r)->contains('You have 1 of 1 must-have skills'))
        ->where('visible', true));
});

it('gives identical scores to identical profiles whatever the name or age', function (): void {
    $a = seeker(user: ['first_name' => 'Thandi', 'date_of_birth' => '2004-01-01']);
    $b = seeker(user: ['first_name' => 'Johannes', 'last_name' => 'van der Merwe', 'date_of_birth' => '1985-06-30']);

    expect(DB::table('work_matches')->where('user_id', $a->id)->value('score'))->toBe(DB::table('work_matches')->where('user_id', $b->id)->value('score'));
});

it('suggests only consenting, visible candidates to verified employers - anonymised', function (): void {
    $visible = seeker();
    $private = seeker(false, ['first_name' => 'Private']);
    $hiding = seeker(user: ['first_name' => 'Hiding']);
    DB::table('work_hidden_employers')->insert(['user_id' => $hiding->id, 'organisation_id' => $this->employer->id]);
    Staff::signIn($this, $this->owner);

    $this->get("/work/employer/listings/{$this->listing->id}/candidates")->assertInertia(fn ($page) => $page->component('Work/Employer/Candidates')
        ->has('candidates', 1)->where('candidates.0.id', $visible->id)->where('candidates.0.name', 'Thandi M.')->where('candidates.0.phone', null));
});

it('stops suggesting someone the moment they switch job matching off', function (): void {
    $person = seeker();
    app(ConsentService::class)->record($person, ['job_matching' => false]);
    Staff::signIn($this, $this->owner);

    $this->get("/work/employer/listings/{$this->listing->id}/candidates")->assertInertia(fn ($page) => $page->has('candidates', 0));
});

it('records every profile view and shows it to the person', function (): void {
    $person = seeker();
    Staff::signIn($this, $this->owner);
    $this->get("/work/employer/listings/{$this->listing->id}/candidates/{$person->id}")->assertOk()
        ->assertInertia(fn ($page) => $page->where('candidate.name', 'Thandi M.')->where('candidate.phone', null));

    Staff::signIn($this, $person);
    $this->get('/work/matches?tab=views')->assertInertia(fn ($page) => $page->where('views.0.employer', 'Giyani Bakery'));
});

it('invites, notifies and shares contact details only after the person accepts', function (): void {
    $person = seeker();
    Staff::signIn($this, $this->owner);
    $this->post("/work/employer/listings/{$this->listing->id}/candidates/{$person->id}/invite", ['message' => 'Come for a chat on Friday'])->assertSessionHasNoErrors();
    $this->post("/work/employer/listings/{$this->listing->id}/candidates/{$person->id}/invite")->assertSessionHasErrors('invite'); // once only

    expect(Update::query()->where('user_id', $person->id)->where('title', 'Giyani Bakery invited you to apply')->exists())->toBeTrue();

    Staff::signIn($this, $person);
    $invitation = DB::table('work_invitations')->value('id');
    $this->post("/work/invitations/{$invitation}", ['accept' => true])->assertSessionHasNoErrors();

    Staff::signIn($this, $this->owner);
    $this->get("/work/employer/listings/{$this->listing->id}/candidates")->assertInertia(fn ($page) => $page->where('candidates.0.name', 'Thandi Mabasa')->where('candidates.0.phone', fn ($p) => $p !== null));
    expect(Update::query()->where('user_id', $this->owner->id)->where('title', 'like', 'Thandi Mabasa accepted%')->exists())->toBeTrue();
});

it('limits invitations and only for verified employers with live adverts', function (): void {
    $person = seeker();
    $this->employer->forceFill(['verification_status' => 'pending'])->save();
    Staff::signIn($this, $this->owner);

    $this->get("/work/employer/listings/{$this->listing->id}/candidates")->assertForbidden();
});

it('expires invitations when the advert closes', function (): void {
    $person = seeker();
    app(Invitations::class)->invite($this->listing, $person, $this->owner);

    app(Listings::class)->close($this->listing, 'filled', $this->owner);

    expect(DB::table('work_invitations')->value('status'))->toBe('expired')->and(DB::table('work_matches')->where('listing_id', $this->listing->id)->count())->toBe(0);
});

it('sends at most one daily alert, only to people with job matching on', function (): void {
    $on = seeker();
    $off = seeker(false);

    $this->artisan('kasi:work:matches')->assertSuccessful();
    $this->artisan('kasi:work:matches')->assertSuccessful();

    expect(Update::query()->where('user_id', $on->id)->where('title', '1 new jobs match you')->count())->toBe(1)
        ->and(Update::query()->where('user_id', $off->id)->where('title', 'like', '%match you')->count())->toBe(0);
});

it('lets people hide their profile from an employer', function (): void {
    $person = seeker();
    Staff::signIn($this, $person);

    $this->post("/work/jobs/{$this->listing->id}/hide-employer")->assertSessionHasNoErrors();
    $this->get('/work/matches?tab=hidden')->assertInertia(fn ($page) => $page->where('hidden.0.name', 'Giyani Bakery'));
    $this->delete("/work/hidden/{$this->employer->id}");
    expect(DB::table('work_hidden_employers')->count())->toBe(0);
});

it('updates matches when the advert changes', function (): void {
    $person = seeker();
    $this->listing->forceFill(['status' => 'closed'])->save();

    expect(DB::table('work_matches')->where('user_id', $person->id)->count())->toBe(0);
});

it('shows matching insights and the fairness check to the KasiHub team only', function (): void {
    seeker();
    Staff::signIn($this, $this->owner);
    $this->get('/work/insights')->assertForbidden();

    Console::as($this, 'operations_admin');
    $this->get('/work/insights')->assertInertia(fn ($page) => $page->component('Work/Insights')->where('totals.profiles', 1)
        ->where('fairness', fn ($rows) => collect($rows)->firstWhere('group', 'informal_only')['people'] === 1));
});
