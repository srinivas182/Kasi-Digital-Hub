<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Modules\Core\Access\Scope;
use Modules\Core\Documents\Models\Document;
use Modules\Core\Enterprise\SupportOffers;
use Modules\Core\Identity\Models\User;
use Modules\Core\Platform\Models\PlatformEventRecord;
use Modules\Core\Platform\Models\Update;
use Modules\Core\Structure\Models\Organisation;
use Modules\Core\Tests\Structure;
use Modules\HubOps\Tests\Staff;
use Modules\Partner\Models\Offer;
use Modules\Partner\Models\Referral;
use Modules\Partner\Services\Offers;
use Modules\Partner\Services\Referrals;
use Modules\Start\Database\Seeders\StepSeeder;
use Modules\Start\Models\Step;
use Modules\Start\Services\Businesses;
use Tests\TestCase;

beforeEach(function (): void {
    Structure::seed($this);
    Storage::fake('documents');
    $this->seed(StepSeeder::class);
    $this->partner = Organisation::query()->create(['type' => 'partner', 'name' => 'Fund', 'verification_status' => 'verified']);
    $this->agent = Structure::personWith('partner_admin', Scope::organisation($this->partner));
    DB::table('partner_agreements')->insert(['organisation_id' => $this->partner->id, 'version' => config('kasi.partner.agreement_version'), 'accepted_at' => now()]);

    $this->owner = User::factory()->create(['date_of_birth' => now()->subYears(24)]);
    $this->business = app(Businesses::class)->create($this->owner, ['name' => 'Nomsa Hair', 'sector' => 'beauty', 'stage' => 'informal', 'people' => 1]);
    app(Businesses::class)->chooseForm($this->business, 'sole', $this->owner);
    $this->offer = Offer::query()->create(['organisation_id' => $this->partner->id, 'title' => 'Equipment grant', 'type' => 'equipment', 'description' => str_repeat('Equipment for youth. ', 3),
        'opens_on' => now()->subDay(), 'status' => 'open', 'criteria' => ['stages' => ['informal'], 'age_max' => 35, 'steps' => ['bank_account']]]);
    $this->idDoc = Document::query()->create(['user_id' => $this->owner->id, 'type' => 'id_document', 'disk' => 'documents', 'path' => 'id.pdf', 'original_name' => 'id.pdf',
        'mime_type' => 'application/pdf', 'size_bytes' => 10, 'sha256' => str_repeat('a', 64), 'status' => 'verified']);
});

function bankDone(TestCase $t): void
{
    app(Businesses::class)->markDone($t->business, Step::query()->where('key', 'bank_account')->firstOrFail(), $t->owner);
}

it('explains eligibility and links missing items to the KasiStart step', function (): void {
    Staff::signIn($this, $this->owner);
    $this->get('/support')->assertInertia(fn ($page) => $page->where('offers.0.eligibility.eligible', false)->where('offers.0.eligibility.met', 2)
        ->where('offers.0.eligibility.checks.2.step', 'bank_account'));

    bankDone($this);
    $this->get('/support')->assertInertia(fn ($page) => $page->where('offers.0.eligibility.eligible', true));
    expect(app(SupportOffers::class)->eligibleCount($this->business->id))->toBe(1);
});

it('sends a consented referral sharing exactly what was chosen', function (): void {
    bankDone($this);
    Staff::signIn($this, $this->owner);
    $this->post("/support/offers/{$this->offer->id}/refer", ['business_id' => $this->business->id, 'profile' => true, 'summary' => false, 'readiness' => true, 'documents' => [$this->idDoc->id]])
        ->assertSessionHasErrors('consent');
    $this->post("/support/offers/{$this->offer->id}/refer", ['business_id' => $this->business->id, 'profile' => true, 'summary' => false, 'readiness' => true, 'documents' => [$this->idDoc->id], 'consent' => true])
        ->assertSessionHasNoErrors();

    $referral = Referral::query()->sole();
    expect($referral->shared)->toEqual(['profile' => true, 'summary' => false, 'readiness' => true, 'documents' => [$this->idDoc->id]])
        ->and(PlatformEventRecord::query()->where('name', 'partner.referral.sent')->exists())->toBeTrue()
        ->and(Update::query()->where('user_id', $this->agent->id)->where('title', 'New referral: Equipment grant')->exists())->toBeTrue();

    Staff::signIn($this, $this->agent);
    $this->get("/partner/referrals/{$referral->id}")->assertInertia(fn ($page) => $page->where('business.summary', null)->where('business.readiness', fn ($r) => $r !== null)->has('documents', 1));
    $this->get("/partner/referrals/{$referral->id}/documents/{$this->idDoc->id}")->assertRedirect();
    expect(DB::table('partner_document_views')->count())->toBe(1);
});

it('refuses unverified or someone else\'s documents, ineligible businesses and duplicates', function (): void {
    Staff::signIn($this, $this->owner);
    $this->post("/support/offers/{$this->offer->id}/refer", ['business_id' => $this->business->id, 'consent' => true])->assertSessionHasErrors('refer'); // not eligible yet
    bankDone($this);
    $other = Document::query()->create(['user_id' => User::factory()->create()->id, 'type' => 'id_document', 'disk' => 'documents', 'path' => 'x.pdf', 'original_name' => 'x.pdf',
        'mime_type' => 'application/pdf', 'size_bytes' => 1, 'sha256' => str_repeat('b', 64), 'status' => 'verified']);
    $this->post("/support/offers/{$this->offer->id}/refer", ['business_id' => $this->business->id, 'documents' => [$other->id], 'consent' => true])->assertSessionHasErrors('refer');
    $this->post("/support/offers/{$this->offer->id}/refer", ['business_id' => $this->business->id, 'consent' => true])->assertSessionHasNoErrors();
    $this->post("/support/offers/{$this->offer->id}/refer", ['business_id' => $this->business->id, 'consent' => true])->assertSessionHasErrors('refer');
});

it('needs the data-sharing agreement before partners receive referrals', function (): void {
    bankDone($this);
    DB::table('partner_agreements')->delete();
    Staff::signIn($this, $this->owner);
    $this->post("/support/offers/{$this->offer->id}/refer", ['business_id' => $this->business->id, 'consent' => true])->assertSessionHasErrors('refer');
});

it('ends partner access when the entrepreneur withdraws', function (): void {
    bankDone($this);
    $referral = app(Referrals::class)->send($this->offer, $this->business->id, $this->owner, ['documents' => [$this->idDoc->id]], null);

    Staff::signIn($this, $this->owner);
    $this->post("/support/referrals/{$referral->id}/withdraw");
    Staff::signIn($this, $this->agent);
    $this->get("/partner/referrals/{$referral->id}")->assertInertia(fn ($page) => $page->where('business', null)->has('documents', 0));
    $this->get("/partner/referrals/{$referral->id}/documents/{$this->idDoc->id}")->assertNotFound();
});

it('runs the pipeline to a two-sided outcome with follow-ups', function (): void {
    bankDone($this);
    $referral = app(Referrals::class)->send($this->offer, $this->business->id, $this->owner, [], 'Please help with clippers');

    Staff::signIn($this, $this->agent);
    $this->post("/partner/referrals/{$referral->id}/move", ['stage' => 'info', 'message' => 'Please send a photo of your workspace.']);
    $this->post("/partner/referrals/{$referral->id}/outcome", ['outcome' => 'Clippers'])->assertSessionHasErrors('referral'); // approve first
    $this->post("/partner/referrals/{$referral->id}/move", ['stage' => 'approved']);
    $this->post("/partner/referrals/{$referral->id}/outcome", ['outcome' => 'Clippers and a chair', 'value' => 6500]);

    Staff::signIn($this, $this->owner);
    $this->get("/support/referrals/{$referral->id}")->assertInertia(fn ($page) => $page->where('referral.outcome', 'Clippers and a chair')->where('referral.value', "R6\u{00A0}500.00"));
    $this->post("/support/referrals/{$referral->id}/confirm", ['received' => true]);
    expect(PlatformEventRecord::query()->where('name', 'partner.support.confirmed')->exists())->toBeTrue()
        ->and(DB::table('partner_followups')->count())->toBe(2);

    $this->travel(3)->months();
    $this->artisan('kasi:partner:daily')->assertSuccessful();
    $followup = DB::table('partner_followups')->where('months', 3)->value('id');
    Staff::signIn($this, $this->owner);
    $this->post("/support/followups/{$followup}", ['trading' => true]);
    expect(PlatformEventRecord::query()->where('name', 'partner.followup.answered')->exists())->toBeTrue();
});

it('reminds partners at 7 and 14 days and closes as "no response" at 30', function (): void {
    bankDone($this);
    $referral = app(Referrals::class)->send($this->offer, $this->business->id, $this->owner, [], null);
    $ops = Structure::personWith('operations_admin', Scope::national());

    $reminders = fn () => Update::query()->where('user_id', $this->agent->id)->where('title', 'like', 'Waiting%')->count();
    $this->travel(7)->days();
    $this->artisan('kasi:partner:daily');
    $this->artisan('kasi:partner:daily');
    expect($reminders())->toBe(1);
    $this->travel(7)->days();
    $this->artisan('kasi:partner:daily');
    expect($reminders())->toBe(2);
    $this->travel(16)->days();
    $this->artisan('kasi:partner:daily');

    expect($referral->refresh()->stage)->toBe('no_response')
        ->and(Update::query()->where('user_id', $this->owner->id)->where('title', 'like', 'No response yet%')->exists())->toBeTrue()
        ->and(Update::query()->where('user_id', $ops->id)->where('title', 'like', 'Partner did not respond%')->exists())->toBeTrue();
});

it('blocks offers that charge a fee to apply, and reviews a partner\'s first offer', function (): void {
    expect(Offers::chargesFee('Pay a R200 application fee to be considered'))->toBeTrue()
        ->and(Offers::chargesFee('Grants up to R15 000 for equipment'))->toBeFalse();

    Staff::signIn($this, $this->agent);
    $this->post('/partner/offers', ['title' => 'Loans', 'type' => 'loan', 'description' => 'Small loans. There is an application fee of R150 to apply.', 'opens_on' => now()->toDateString()])
        ->assertSessionHasErrors('description');
    Offer::query()->delete();
    $this->post('/partner/offers', ['title' => 'Mentoring', 'type' => 'mentoring', 'description' => 'Monthly mentoring sessions with experienced business owners.', 'opens_on' => now()->toDateString()]);
    $offer = Offer::query()->sole();
    $this->post("/partner/offers/{$offer->id}/submit");
    expect($offer->refresh()->status)->toBe('review');
});

it('keeps referrals to their partner and business', function (): void {
    bankDone($this);
    $referral = app(Referrals::class)->send($this->offer, $this->business->id, $this->owner, [], null);
    $other = Organisation::query()->create(['type' => 'partner', 'name' => 'Other', 'verification_status' => 'verified']);

    Staff::signIn($this, Structure::personWith('partner_admin', Scope::organisation($other)));
    $this->get("/partner/referrals/{$referral->id}")->assertNotFound();
    Staff::signIn($this, User::factory()->create());
    $this->get("/support/referrals/{$referral->id}")->assertNotFound();
});
