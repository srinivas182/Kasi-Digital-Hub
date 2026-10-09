<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Ai\Drivers\FakeAiDriver;
use Modules\Core\Ai\Models\AiFeatureSetting;
use Modules\Core\Ai\Models\AiRequestLog;
use Modules\Core\Ai\Speech\FakeSpeechToText;
use Modules\Core\Documents\Generation\DocumentShareLink;
use Modules\Core\Documents\Generation\FakePdfRenderer;
use Modules\Core\Documents\Models\Document;
use Modules\Core\Identity\Models\User;
use Modules\Core\Tests\Structure;
use Modules\HubOps\Tests\Staff;
use Modules\Work\Models\WorkCv;
use Modules\Work\Models\WorkEducation;
use Modules\Work\Models\WorkExperience;
use Modules\Work\Services\CvAssistant;

beforeEach(function (): void {
    Structure::seed($this);
    Storage::fake('documents');
    $this->person = Staff::signIn($this, User::factory()->create([
        'first_name' => 'Thandi', 'last_name' => 'Mabasa', 'phone' => '+27724183390', 'date_of_birth' => '2003-05-14', 'place_name' => 'Tsutsumani',
    ]));
    $this->put('/work/profile/about', ['headline' => 'Friendly cashier', 'summary' => 'I like helping people and I am good with money.', 'drivers_licence' => 'none']);
    $this->experience = WorkExperience::query()->create([
        'user_id' => $this->person->id, 'kind' => 'family_business', 'title' => 'Shop assistant', 'duration' => 'about 2 years',
        'description' => 'I help at my aunt\'s tuck shop, I count stock and serve customers and do the cash. Call me 0724183390.',
    ]);
});

it('suggests CV points without sending contact details, and the person decides', function (): void {
    FakeAiDriver::respondWith('{"bullets": ["Served customers and handled cash at a family spaza shop", "Counted and recorded stock"]}');

    $this->postJson("/work/experience/{$this->experience->id}/suggest")->assertOk()->assertJson(['ok' => true, 'warnings' => []]);
    expect(FakeAiDriver::sent()[0]->user)->not->toContain('0724183390')->not->toContain('Thandi')
        ->and(AiRequestLog::query()->value('feature'))->toBe('work.cv_writer')
        ->and($this->experience->refresh()->bullets)->toBeNull(); // nothing used until accepted

    $this->put("/work/experience/{$this->experience->id}/bullets", ['bullets' => ['Served customers and handled cash at a family spaza shop']])->assertSessionHasNoErrors();
    expect($this->experience->refresh()->bullets)->toBe(['Served customers and handled cash at a family spaza shop'])->and($this->experience->suggested_bullets)->toBeNull();
});

it('warns about facts the person never wrote', function (): void {
    FakeAiDriver::respondWith('{"bullets": ["Promoted to supervisor after 3 years at Shoprite", "Served customers"]}');

    $this->postJson("/work/experience/{$this->experience->id}/suggest")->assertJson(['ok' => true])
        ->assertJsonPath('warnings', fn ($w) => in_array('3', $w, true) && in_array('Shoprite', $w, true) && in_array('promoted', $w, true) && in_array('supervisor', $w, true));

    expect(app(CvAssistant::class)->unsupported('I sold 20 loaves at the Spar', 'Sold 20 loaves at the Spar'))->toBe([]);
});

it('falls back to the person\'s own words when writing help is off', function (): void {
    AiFeatureSetting::query()->create(['feature' => 'work.cv_writer', 'enabled' => false]);

    $this->postJson("/work/experience/{$this->experience->id}/suggest")->assertJson(['ok' => false, 'message' => __('work.ai.fallback.disabled')]);
    $this->get('/work/profile?step=experience')->assertInertia(fn ($page) => $page->where('ai.writer', false));
});

it('suggests a profile summary to accept or ignore', function (): void {
    FakeAiDriver::respondWith('{"summary": "Reliable and friendly, good with money and people."}');

    $this->postJson('/work/cv/summary/suggest')->assertJson(['ok' => true, 'summary' => 'Reliable and friendly, good with money and people.']);
    $this->put('/work/cv/summary', ['summary' => 'Reliable and friendly, good with money and people.'])->assertSessionHasNoErrors();

    $this->get('/work/cv')->assertInertia(fn ($page) => $page->where('preview.summary', 'Reliable and friendly, good with money and people.'));
});

it('creates a CV PDF without ID number, date of birth or street address, and marks verified education', function (): void {
    $matric = Document::query()->create([
        'user_id' => $this->person->id, 'type' => 'matric_certificate', 'disk' => 'documents', 'path' => 'm.pdf', 'original_name' => 'm.pdf',
        'mime_type' => 'application/pdf', 'size_bytes' => 10, 'sha256' => str_repeat('b', 64), 'status' => 'verified',
    ]);
    WorkEducation::query()->create(['user_id' => $this->person->id, 'kind' => 'matric', 'name' => 'National Senior Certificate', 'year' => 2022, 'document_id' => $matric->id]);
    WorkEducation::query()->create(['user_id' => $this->person->id, 'kind' => 'short_course', 'name' => 'Computer course', 'year' => 2023]);

    $this->post('/work/cv', ['template' => 'classic'])->assertSessionHasNoErrors();

    $html = FakePdfRenderer::$rendered[0];
    expect($html)->toContain('Thandi Mabasa')->toContain('072 418 3390')->toContain('Tsutsumani')
        ->not->toContain('2003')->not->toContain('14 May')->not->toContain('Age ')
        ->and(substr_count($html, 'Verified</span>'))->toBe(1);

    $cv = WorkCv::query()->sole();
    expect($cv->document->type)->toBe('cv');
    $this->get("/work/cv/{$cv->id}/download")->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->get("/verify/{$cv->document->verification_code}")->assertInertia(fn ($page) => $page->where('result.status', 'valid'));
});

it('shows the age only when the person asks', function (): void {
    $this->put('/work/profile/about', ['headline' => 'Friendly cashier', 'summary' => 'Reliable.', 'drivers_licence' => 'none', 'show_age_on_cv' => true]);

    $this->post('/work/cv', ['template' => 'simple']);

    expect(FakePdfRenderer::$rendered[0])->toContain('Age '.$this->person->date_of_birth->age);
});

it('shares a CV by link, counts views and switches links off', function (): void {
    $this->post('/work/cv', ['template' => 'classic']);
    $cv = WorkCv::query()->sole();

    $url = $this->post("/work/cv/{$cv->id}/share")->assertSessionHas('share_url')->getSession()->get('share_url');
    $this->post('/logout');
    $this->get($url)->assertOk();
    $this->get($url)->assertOk();

    Staff::signIn($this, $this->person);
    $this->get('/work/cv')->assertInertia(fn ($page) => $page->where('versions.0.links.0.views', 2)->where('versions.0.links.0.active', true));
    $link = DocumentShareLink::query()->sole();
    $this->delete("/work/cv/{$cv->id}/share/{$link->id}")->assertSessionHasNoErrors();

    $this->post('/logout');
    $this->get($url)->assertStatus(410);
});

it('deletes a CV version and withdraws its verification', function (): void {
    $this->post('/work/cv', ['template' => 'classic']);
    $cv = WorkCv::query()->sole();
    $code = $cv->document->verification_code;

    $this->delete("/work/cv/{$cv->id}")->assertSessionHasNoErrors();

    expect(WorkCv::query()->count())->toBe(0);
    $this->get("/verify/{$code}")->assertInertia(fn ($page) => $page->where('result.status', 'revoked'));
});

it('keeps other people\'s CVs private', function (): void {
    $this->post('/work/cv', ['template' => 'classic']);
    $cv = WorkCv::query()->sole();
    Staff::signIn($this, User::factory()->create());

    $this->get("/work/cv/{$cv->id}/download")->assertNotFound();
    $this->post("/work/cv/{$cv->id}/share")->assertNotFound();
});

it('turns a voice note into text and does not keep the audio', function (): void {
    FakeSpeechToText::respondWith('I packed shelves at the Spar for one year.');
    $audio = UploadedFile::fake()->createWithContent('note.webm', str_repeat('a', 2000))->mimeType('audio/webm');

    $this->post('/work/voice', ['audio' => $audio, 'language' => 'en', 'seconds' => 30], ['Accept' => 'application/json'])
        ->assertOk()->assertJson(['ok' => true, 'text' => 'I packed shelves at the Spar for one year.']);

    expect(is_file(FakeSpeechToText::$calls[0]['path']))->toBeFalse()
        ->and(AiRequestLog::query()->where('feature', 'core.voice_note')->value('cost_cents'))->toBeGreaterThan(0);
});

it('only accepts voice notes in switched-on languages, within the limits', function (): void {
    $audio = fn () => UploadedFile::fake()->createWithContent('note.webm', 'abc')->mimeType('audio/webm');

    $this->post('/work/voice', ['audio' => $audio(), 'language' => 'ts', 'seconds' => 10], ['Accept' => 'application/json'])->assertJson(['ok' => false, 'message' => __('work.voice.fallback.language')]);
    $this->post('/work/voice', ['audio' => $audio(), 'language' => 'en', 'seconds' => 300], ['Accept' => 'application/json'])->assertStatus(422);

    config(['kasi.speech.per_person_day' => 1]);
    $this->post('/work/voice', ['audio' => $audio(), 'language' => 'en', 'seconds' => 10], ['Accept' => 'application/json'])->assertJson(['ok' => true]);
    $this->post('/work/voice', ['audio' => $audio(), 'language' => 'en', 'seconds' => 10], ['Accept' => 'application/json'])->assertJson(['ok' => false, 'message' => __('work.voice.fallback.budget')]);
    expect(FakeSpeechToText::$calls)->toHaveCount(1);
});

it('does not treat digits inside a phone number as the same fact', function (): void {
    expect(app(CvAssistant::class)->unsupported('call 0724183390', 'Worked for 3 years'))->toContain('3');
});
