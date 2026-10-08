<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Documents\DocumentVault;
use Modules\Core\Documents\Drivers\FakeVirusScanner;
use Modules\Core\Documents\Models\Document;
use Modules\Core\Identity\Models\AuditLog;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\ConsentService;
use Modules\Core\Platform\Models\PlatformEventRecord;
use Modules\Core\Platform\Models\Update;
use Modules\Core\Tests\Helpers;
use Modules\Core\Tests\Structure;

beforeEach(function (): void {
    Storage::fake('documents');
    Storage::fake('quarantine');
    $this->travelTo(now()->setTimezone('Africa/Johannesburg')->setTime(10, 0)->utc()); // outside quiet hours
    $this->owner = User::factory()->create();
    app(ConsentService::class)->record($this->owner, ['platform' => true]);
});

function pdf(string $name = 'id.pdf', string $contents = "%PDF-1.4\nhello"): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $contents);
}

it('uploads a document, scans it and makes it available to its owner', function (): void {
    Helpers::signedIn($this, $this->owner);

    $this->post('/account/documents', ['type' => 'id_document', 'file' => pdf()])->assertSessionHasNoErrors();

    $document = Document::query()->firstOrFail();
    expect($document->status)->toBe(Document::UPLOADED) // scanned (queue runs synchronously in tests)
        ->and($document->user_id)->toBe($this->owner->id);
    Storage::disk('documents')->assertExists($document->path);

    $this->get(app(DocumentVault::class)->temporaryUrl($document))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    expect(AuditLog::query()->where('event', 'document.downloaded')->exists())->toBeTrue()
        ->and(PlatformEventRecord::query()->where('name', 'core.document.uploaded')->exists())->toBeTrue();
});

it('quarantines infected files so they can never be opened', function (): void {
    $document = app(DocumentVault::class)->store($this->owner, pdf('bad.pdf', FakeVirusScanner::EICAR), 'other');

    expect($document->refresh()->status)->toBe(Document::QUARANTINED);
    Storage::disk('documents')->assertMissing($document->path);
    Storage::disk('quarantine')->assertExists($document->path);
    expect(app(DocumentVault::class)->canOpen($this->owner, $document))->toBeFalse()
        ->and(PlatformEventRecord::query()->where('name', 'core.document.quarantined')->exists())->toBeTrue();
});

it('only opens documents through valid, unexpired signed links and for allowed people', function (): void {
    $document = app(DocumentVault::class)->store($this->owner, pdf(), 'id_document');
    $url = app(DocumentVault::class)->temporaryUrl($document);
    $stranger = User::factory()->create();
    app(ConsentService::class)->record($stranger, ['platform' => true]);

    Helpers::signedIn($this, $stranger);
    $this->get($url)->assertForbidden();
    $this->post('/logout');

    Helpers::signedIn($this, $this->owner);
    $this->get(route('documents.download', $document))->assertForbidden(); // unsigned
    $this->travel(6)->minutes();
    $this->get($url)->assertForbidden(); // expired
});

it('lets an organisation see a shared document until the share is withdrawn', function (): void {
    Structure::seed($this);
    $vault = app(DocumentVault::class);
    $document = $vault->store($this->owner, pdf(), 'matric_certificate');
    $employer = Structure::org('Mopani Fresh Market');
    $recruiter = User::factory()->create();
    $employer->members()->attach($recruiter->id);

    expect($vault->canOpen($recruiter, $document))->toBeFalse();

    $share = $vault->share($document, $employer, 'Job application: Cashier');
    expect($vault->canOpen($recruiter, $document->refresh()))->toBeTrue();

    $vault->revokeShare($share);
    expect($vault->canOpen($recruiter, $document->refresh()))->toBeFalse();
});

it('lets national admins open documents (audited as them)', function (): void {
    Structure::seed($this);
    $document = app(DocumentVault::class)->store($this->owner, pdf(), 'id_document');
    $admin = Structure::personWith('super_admin');

    expect(app(DocumentVault::class)->canOpen($admin, $document->refresh()))->toBeTrue();
});

it('deletes the file itself, not just the record', function (): void {
    Helpers::signedIn($this, $this->owner);
    $document = app(DocumentVault::class)->store($this->owner, pdf(), 'id_document');

    $this->delete("/account/documents/{$document->id}")->assertSessionHasNoErrors();

    Storage::disk('documents')->assertMissing($document->path);
    expect(Document::query()->count())->toBe(0)->and(AuditLog::query()->where('event', 'document.deleted')->exists())->toBeTrue();
});

it("can't delete someone else's document", function (): void {
    $document = app(DocumentVault::class)->store(User::factory()->create(), pdf(), 'id_document');
    Helpers::signedIn($this, $this->owner);

    $this->delete("/account/documents/{$document->id}")->assertNotFound();
});

it('rejects files of the wrong type or too large', function (): void {
    Helpers::signedIn($this, $this->owner);

    $this->post('/account/documents', ['type' => 'id_document', 'file' => UploadedFile::fake()->create('virus.exe', 10)])->assertSessionHasErrors('file');
    $this->post('/account/documents', ['type' => 'id_document', 'file' => UploadedFile::fake()->create('big.pdf', 11000, 'application/pdf')])->assertSessionHasErrors('file');
    $this->post('/account/documents', ['type' => 'passport_scan', 'file' => pdf()])->assertSessionHasErrors('type');
});

it('shrinks large phone photos to save data', function (): void {
    $image = imagecreatetruecolor(3000, 4000);
    ob_start();
    imagejpeg($image, null, 95);
    $photo = UploadedFile::fake()->createWithContent('photo.jpg', (string) ob_get_clean());

    $document = app(DocumentVault::class)->store($this->owner, $photo, 'proof_of_address');
    [$width, $height] = getimagesizefromstring(Storage::disk('documents')->get($document->path));

    expect(max($width, $height))->toBe(2000);
});

it('tells the owner when a document is verified - in their feed and on WhatsApp', function (): void {
    Structure::seed($this);
    $this->owner->forceFill(['whatsapp_opt_in' => true])->save();
    $document = app(DocumentVault::class)->store($this->owner, pdf(), 'id_document');

    app(DocumentVault::class)->verify($document, Structure::personWith('super_admin'));

    expect(Update::query()->where('user_id', $this->owner->id)->where('title', 'Your ID document is verified')->exists())->toBeTrue()
        ->and($this->owner->refresh()->notificationDeliveries()->where('channel', 'whatsapp')->where('status', 'sent')->exists())->toBeTrue()
        ->and(PlatformEventRecord::query()->where('name', 'core.document.verified')->value('user_id'))->toBe($this->owner->id);
});

it('falls back to SMS when an important WhatsApp message cannot be delivered', function (): void {
    Structure::seed($this);
    $this->owner->forceFill(['whatsapp_opt_in' => true])->save();
    config(['kasi.drivers.whatsapp_fail_numbers' => [$this->owner->phone]]);
    $document = app(DocumentVault::class)->store($this->owner, pdf(), 'proof_of_address');

    app(DocumentVault::class)->reject($document, 'The photo is too blurry', Structure::personWith('super_admin'));

    $deliveries = $this->owner->notificationDeliveries()->where('notification', 'document_rejected')->get();
    expect($deliveries->firstWhere('channel', 'whatsapp')->status)->toBe('failed')
        ->and($deliveries->firstWhere('channel', 'sms')->status)->toBe('sent')
        ->and($deliveries->firstWhere('channel', 'sms')->fallback_for)->toBe($deliveries->firstWhere('channel', 'whatsapp')->id)
        ->and(PlatformEventRecord::query()->where('name', 'core.notification.failed')->exists())->toBeTrue();
});

it('reminds people once about documents that are about to expire', function (): void {
    $document = app(DocumentVault::class)->store($this->owner, pdf(), 'tax_clearance', now()->addDays(10)->toDateString());

    $this->artisan('kasi:documents:remind-expiring')->assertSuccessful();
    $this->artisan('kasi:documents:remind-expiring')->assertSuccessful();

    expect(Update::query()->where('user_id', $this->owner->id)->where('title', 'like', '%expires soon%')->count())->toBe(1)
        ->and($document->refresh()->expiry_reminded_at)->not->toBeNull();
});
