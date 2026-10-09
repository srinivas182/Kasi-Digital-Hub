<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Modules\Core\Documents\Generation\DocumentIssuer;
use Modules\Core\Documents\Generation\DocumentShareLink;
use Modules\Core\Documents\Generation\FakePdfRenderer;
use Modules\Core\Identity\Models\AuditLog;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\ConsentService;
use Modules\Core\Search\SearchEngine;
use Modules\Core\Search\SearchService;
use Modules\Core\Tests\Helpers;
use Modules\Core\Tests\Structure;
use Tests\TestCase;

beforeEach(function (): void {
    Structure::seed($this);
    Storage::fake('documents');
    app(SearchService::class)->reindex();
});

function searcher(TestCase $test): User
{
    $user = User::factory()->create();
    app(ConsentService::class)->record($user, ['platform' => true]);

    return Helpers::signedIn($test, $user);
}

it('finds hubs and help, forgiving small typos', function (): void {
    searcher($this);

    $this->get('/search?q=Tsutsumani')->assertOk()->assertInertia(fn ($page) => $page->component('Core/Search')->where('groups.0.type', 'hub')->where('groups.0.items.0.url', '/hubs/tsutsumani'));
    $this->get('/search?q=Gyani')->assertInertia(fn ($page) => $page->where('groups', fn ($groups) => collect($groups)->firstWhere('type', 'hub') !== null));
    $this->get('/search?q=forgot PIN')->assertInertia(fn ($page) => $page->where('groups.0.type', 'help')->where('groups.0.items.0.url', '/help#q-forgot_pin'));
    $this->get('/search?q=zzqqxx')->assertInertia(fn ($page) => $page->has('groups', 0));
});

it('keeps search for signed-in people only', function (): void {
    $this->get('/search?q=hub')->assertRedirect('/login');
});

it('keeps the page working when search is down', function (): void {
    config(['kasi.drivers.search' => 'meilisearch', 'kasi.search.meilisearch.url' => 'http://127.0.0.1:1']);
    app()->forgetInstance(SearchEngine::class);
    app()->forgetInstance(SearchService::class);
    searcher($this);

    $this->get('/search?q=Tsutsumani')->assertOk()->assertInertia(fn ($page) => $page->where('unavailable', true));
});

it('limits results to what the person may see', function (): void {
    $adult = SearchService::visibilitiesFor(User::factory()->create());
    $minor = SearchService::visibilitiesFor(User::factory()->create(['age_band' => 'minor', 'date_of_birth' => now()->subYears(17)]));

    expect($adult)->toBe(['public', 'members', 'learners'])->and($minor)->toBe(['public', 'learners'])->and(SearchService::visibilitiesFor(null))->toBe(['public']);
});

it('issues a PDF with a verification code anyone can check', function (): void {
    $person = User::factory()->create(['first_name' => 'Thandi', 'last_name' => 'Mabasa']);
    $doc = app(DocumentIssuer::class)->issue($person, 'attendance_certificate', 'Certificate of attendance - CV workshop', 'hubops::certificates.attendance', [
        'person' => 'Thandi Mabasa', 'eventTitle' => 'CV workshop', 'eventType' => 'Workshop', 'eventDate' => '9 Oct 2026', 'hubName' => 'Tsutsumani Digital Hub', 'hubPlace' => 'Tsutsumani',
    ], 1, ['hub_event', '01JTESTEVENT00000000000000']);

    Storage::disk('documents')->assertExists($doc->path);
    expect(FakePdfRenderer::$rendered[0])->toContain('Thandi Mabasa')->toContain($doc->verification_code)->toContain('<svg');

    $this->get("/verify/{$doc->verification_code}")->assertInertia(fn ($page) => $page->component('Core/Verify')->where('result.status', 'valid')->where('result.holder', 'Thandi Mabasa'));
    $this->get('/verify?code='.strtolower($doc->verification_code))->assertRedirect("/verify/{$doc->verification_code}");
    $this->get('/verify/ABCDEFGH23')->assertInertia(fn ($page) => $page->where('result.status', 'not_found'));

    app(DocumentIssuer::class)->revoke($doc, 'Issued in error', User::factory()->create());
    $this->get("/verify/{$doc->verification_code}")->assertInertia(fn ($page) => $page->where('result.status', 'revoked'));
});

it('hides the holder name when they chose to', function (): void {
    $doc = app(DocumentIssuer::class)->issue(User::factory()->create(), 'attendance_certificate', 'Certificate', 'hubops::certificates.attendance', [
        'person' => 'X', 'eventTitle' => 'E', 'eventType' => 'Class', 'eventDate' => 'today', 'hubName' => 'H', 'hubPlace' => null,
    ], 1, showName: false);

    $this->get("/verify/{$doc->verification_code}")->assertInertia(fn ($page) => $page->where('result.holder', null));
});

it('shares a document by link until it expires or is switched off, and only the owner can download it', function (): void {
    $owner = User::factory()->create();
    $issuer = app(DocumentIssuer::class);
    $doc = $issuer->issue($owner, 'attendance_certificate', 'Certificate', 'hubops::certificates.attendance', [
        'person' => 'X', 'eventTitle' => 'E', 'eventType' => 'Class', 'eventDate' => 'today', 'hubName' => 'H', 'hubPlace' => null,
    ], 1);

    $url = $issuer->share($doc, 7, $owner);
    $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(AuditLog::query()->where('event', 'document.share_viewed')->exists())->toBeTrue();

    $this->travel(8)->days();
    $this->get($url)->assertStatus(410);
    $this->travelBack();

    $second = $issuer->share($doc, 7, $owner);
    $issuer->revokeShare(DocumentShareLink::query()->where('token_hash', hash('sha256', basename($second)))->firstOrFail(), $owner);
    $this->get($second)->assertStatus(410);

    searcher($this);
    $this->get("/my-documents/{$doc->id}/download")->assertNotFound();
    $this->post('/logout');
    app(ConsentService::class)->record($owner, ['platform' => true]);
    Helpers::signedIn($this, $owner);
    $this->get("/my-documents/{$doc->id}/download")->assertOk();
});
