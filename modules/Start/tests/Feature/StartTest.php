<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Admin\Tests\Console;
use Modules\Core\Ai\Drivers\FakeAiDriver;
use Modules\Core\Documents\Generation\FakePdfRenderer;
use Modules\Core\Documents\Models\Document;
use Modules\Core\Identity\Models\User;
use Modules\Core\Platform\Models\PlatformEventRecord;
use Modules\Core\Structure\Models\RoleAssignment;
use Modules\Core\Tests\Structure;
use Modules\HubOps\Tests\Staff;
use Modules\Start\Database\Seeders\StepSeeder;
use Modules\Start\Models\Business;
use Modules\Start\Models\Step;
use Modules\Start\Services\Businesses;
use Modules\Start\Services\Plans;
use Tests\TestCase;

beforeEach(function (): void {
    Structure::seed($this);
    Storage::fake('documents');
    $this->seed(StepSeeder::class);
    $this->owner = Staff::signIn($this, User::factory()->create(['first_name' => 'Nomsa', 'last_name' => 'Mthembu']));
});

function newBusiness(TestCase $t, array $o = []): Business
{
    $data = ['name' => 'Nomsa Hair', 'sells' => 'Braids', 'sector' => 'beauty', 'stage' => 'informal', 'people' => 1, ...$o];
    $t->post('/start/businesses', $data)->assertSessionHasNoErrors();

    return Business::query()->where('name', $data['name'])->firstOrFail(); // by name: several can be created in the same second
}

it('creates a business, makes the person an entrepreneur and starts with choosing a legal form', function (): void {
    $business = newBusiness($this);

    expect(RoleAssignment::query()->where('user_id', $this->owner->id)->where('role', 'entrepreneur')->exists())->toBeTrue()
        ->and(PlatformEventRecord::query()->where('name', 'start.business.created')->exists())->toBeTrue();
    $this->get("/start/businesses/{$business->id}")->assertInertia(fn ($page) => $page->component('Start/Business')
        ->where('steps', fn ($s) => collect($s)->pluck('key')->all() === ['choose_form'])->where('readiness.next', 'choose_form'));
});

it('chooses steps by legal form, sector and employees', function (): void {
    $pty = newBusiness($this, ['sector' => 'food', 'people' => 3]);
    $this->post("/start/businesses/{$pty->id}/form", ['legal_form' => 'pty']);
    $sole = newBusiness($this, ['name' => 'Solo', 'sector' => 'beauty', 'people' => 1]);
    $this->post("/start/businesses/{$sole->id}/form", ['legal_form' => 'sole']);

    $keys = fn (Business $b) => app(Businesses::class)->steps($b->refresh())->pluck('key')->all();
    expect($keys($pty))->toContain('cipc_register', 'uif_coida', 'food_certificate', 'bbbee_affidavit')
        ->and($keys($sole))->not->toContain('cipc_register')->not->toContain('uif_coida')->not->toContain('food_certificate')->toContain('sars_tax', 'bank_account');
});

it('marks steps done with proof in the vault, and formalises when all are done', function (): void {
    $business = newBusiness($this);
    $this->post("/start/businesses/{$business->id}/form", ['legal_form' => 'sole']);
    $steps = app(Businesses::class)->steps($business->refresh());

    $tax = $steps->firstWhere('key', 'sars_tax');
    $this->post("/start/businesses/{$business->id}/steps/{$tax->id}", ['file' => UploadedFile::fake()->createWithContent('tax.pdf', '%PDF-1.4 tax')])->assertSessionHasNoErrors();
    expect(Document::query()->where('user_id', $this->owner->id)->where('type', 'tax_registration')->exists())->toBeTrue()
        ->and(PlatformEventRecord::query()->where('name', 'start.step.completed')->count())->toBeGreaterThanOrEqual(2); // choose_form + tax

    foreach ($steps as $step) {
        $this->post("/start/businesses/{$business->id}/steps/{$step->id}");
    }
    expect($business->refresh()->formalised_at)->not->toBeNull()
        ->and(PlatformEventRecord::query()->where('name', 'start.business.formalised')->count())->toBe(1);

    $cipc = Step::query()->where('key', 'cipc_register')->firstOrFail();
    $this->post("/start/businesses/{$business->id}/steps/{$cipc->id}")->assertSessionHasErrors('step'); // not for sole proprietors
});

it('explains the readiness score and counts only verified proof', function (): void {
    $business = newBusiness($this, ['customers' => 'Neighbours', 'turnover_band' => 'under_5k']);
    $this->post("/start/businesses/{$business->id}/form", ['legal_form' => 'sole']);
    $tax = Step::query()->where('key', 'sars_tax')->firstOrFail();
    $this->post("/start/businesses/{$business->id}/steps/{$tax->id}", ['file' => UploadedFile::fake()->createWithContent('tax.pdf', '%PDF-1.4 tax')]);

    $r = app(Businesses::class)->readiness($business->refresh());
    expect($r['parts']['profile']['points'])->toBe(20)->and($r['parts']['proof']['points'])->toBe(0);

    Document::query()->where('type', 'tax_registration')->update(['status' => 'verified']);
    expect(app(Businesses::class)->readiness($business)['parts']['proof']['points'])->toBeGreaterThan(0);
});

it('keeps businesses to their owners; only the owner manages co-owners', function (): void {
    $business = newBusiness($this);
    $co = User::factory()->create(['phone' => '+27724180001']);
    $this->post("/start/businesses/{$business->id}/members", ['phone' => '072 418 0001'])->assertSessionHasNoErrors();

    Staff::signIn($this, $co);
    $this->get("/start/businesses/{$business->id}")->assertOk();
    $this->post("/start/businesses/{$business->id}/members", ['phone' => '0724180002'])->assertForbidden();

    Staff::signIn($this, User::factory()->create());
    $this->get("/start/businesses/{$business->id}")->assertNotFound();
    Staff::signIn($this, User::factory()->create(['age_band' => 'minor', 'date_of_birth' => now()->subYears(17)]));
    $this->get('/start')->assertForbidden();
});

it('calculates prices and break-even, and warns about selling at a loss', function (): void {
    expect(Plans::calculate(100, 50, null, 1000))->toBe(['price' => 150.0, 'profit_per_item' => 50.0, 'break_even_items' => 20, 'warning' => null])
        ->and(Plans::calculate(100, null, 90, 1000))->toBe(['price' => 90.0, 'profit_per_item' => -10.0, 'break_even_items' => null, 'warning' => 'start.calc.loss'])
        ->and(Plans::calculate(null, null, null, null)['price'])->toBeNull();
});

it('saves the plan, completes it and improves text without adding numbers', function (): void {
    $business = newBusiness($this);
    $sections = array_fill_keys(Plans::SECTIONS, 'We braid hair for women in Zone 6 on weekends, close to home.');
    $this->put("/start/businesses/{$business->id}/plan", ['sections' => $sections, 'numbers' => ['cost' => 120, 'markup' => 150, 'fixed' => 1500]])->assertSessionHasNoErrors();
    expect(PlatformEventRecord::query()->where('name', 'start.plan.completed')->exists())->toBeTrue();

    FakeAiDriver::respondWith('{"text": "We offer braiding for women in Zone 6 at weekends, serving 500 clients a month."}');
    $this->postJson("/start/businesses/{$business->id}/plan/improve", ['section' => 'offer', 'text' => 'we braid hair for women in zone 6 on weekends'])
        ->assertJson(['ok' => true])->assertJsonPath('warnings', ['500']);
});

it('makes a verifiable summary and a pre-filled affidavit', function (): void {
    $business = newBusiness($this);
    $this->get("/start/businesses/{$business->id}/summary")->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(end(FakePdfRenderer::$rendered))->toContain('Nomsa Hair');

    $this->get("/start/businesses/{$business->id}/affidavit")->assertOk();
    expect(end(FakePdfRenderer::$rendered))->toContain('Sworn affidavit')->toContain('Nomsa Mthembu')->toContain('commissioner of oaths');
});

it('lets only the KasiHub content team edit steps', function (): void {
    $step = Step::query()->where('key', 'bank_account')->firstOrFail();
    $this->get('/start/admin/steps')->assertForbidden();

    Console::as($this, 'operations_admin');
    $this->put("/start/admin/steps/{$step->id}", ['title' => 'Open a business bank account', 'summary' => 'Updated summary.', 'forms' => ['*'], 'sectors' => ['*'], 'checked' => true, 'active' => true])
        ->assertSessionHasNoErrors();
    expect($step->refresh()->summary)->toBe('Updated summary.')->and($step->last_checked_on)->not->toBeNull();
});

it('shows the business on the hub home with its next step', function (): void {
    newBusiness($this);
    $this->get('/home')->assertInertia(fn ($page) => $page->where('steps', fn ($steps) => collect($steps)->contains('key', 'start_business')));
});
