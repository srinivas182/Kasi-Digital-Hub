<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Admin\Tests\Console;
use Modules\Core\Documents\DocumentVault;
use Modules\Core\Documents\Models\Document;
use Modules\Core\Identity\Models\AuditLog;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Platform\Models\Enquiry;
use Modules\Core\Platform\Models\Update;
use Modules\Core\Tests\Structure;

beforeEach(function (): void {
    Structure::seed($this);
    Storage::fake('documents');
    $this->travelTo(now()->setTimezone('Africa/Johannesburg')->setTime(10, 0)->utc());
});

function uploaded(User $owner, string $type = 'id_document'): Document
{
    return app(DocumentVault::class)->store($owner, UploadedFile::fake()->createWithContent('doc.pdf', '%PDF-1.4 test'), $type)->refresh();
}

it('shows documents waiting for review, oldest first, with inline previews', function (): void {
    Console::as($this, 'support_agent');
    $old = uploaded(User::factory()->create());
    $old->forceFill(['created_at' => now()->subDays(2)])->save();
    uploaded(User::factory()->create(), 'matric_certificate');

    $this->get('/admin/verification')->assertInertia(fn ($page) => $page
        ->component('Admin/Verification/Index')
        ->has('documents.data', 2)
        ->where('documents.data.0.id', $old->id)
        ->where('documents.data.0.waitingHours', 48)
        ->where('documents.data.0.previewUrl', fn ($url) => str_contains($url, 'inline=1') && str_contains($url, 'signature=')));
});

it('lets a reviewer open a document inline (audited as a view)', function (): void {
    $agent = Console::as($this, 'support_agent');
    $document = uploaded(User::factory()->create());

    $this->get(app(DocumentVault::class)->temporaryUrl($document, inline: true))->assertOk()->assertHeader('Content-Disposition', 'inline; filename=doc.pdf');

    expect(AuditLog::query()->where('event', 'document.viewed')->value('actor_id'))->toBe($agent->id);
});

it('verifies and rejects documents, telling the person why', function (): void {
    Console::as($this, 'support_agent');
    $owner = User::factory()->create();
    $good = uploaded($owner);
    $bad = uploaded($owner, 'proof_of_address');

    $this->post("/admin/verification/{$good->id}/verify")->assertSessionHasNoErrors();
    $this->post("/admin/verification/{$bad->id}/reject", ['reason' => 'other'])->assertSessionHasErrors('note');
    $this->post("/admin/verification/{$bad->id}/reject", ['reason' => 'blurry'])->assertSessionHasNoErrors();

    expect($good->refresh()->status)->toBe('verified')
        ->and($bad->refresh()->status)->toBe('rejected')
        ->and($bad->rejection_reason)->toBe('The image is too blurry to read')
        ->and(Update::query()->where('user_id', $owner->id)->where('body', 'like', '%too blurry%')->exists())->toBeTrue();

    $this->post("/admin/verification/{$good->id}/verify")->assertStatus(409); // already decided
});

it('handles website enquiries', function (): void {
    Console::as($this, 'support_agent');
    $enquiry = Enquiry::query()->create(['kind' => 'employer', 'name' => 'Sipho', 'organisation' => 'Shop', 'email' => 's@example.co.za', 'message' => 'We want to hire']);

    $this->get('/admin/enquiries')->assertInertia(fn ($page) => $page->has('enquiries.data', 1));
    $this->put("/admin/enquiries/{$enquiry->id}", ['status' => 'handled'])->assertSessionHasNoErrors();
    $this->get('/admin/enquiries')->assertInertia(fn ($page) => $page->has('enquiries.data', 0));
    $this->get('/admin/enquiries?status=handled')->assertInertia(fn ($page) => $page->has('enquiries.data', 1));
});

it('filters the audit log and exports it (the export itself is audited)', function (): void {
    $admin = Console::as($this, 'super_admin');
    $person = User::factory()->create();
    app(AuditLogger::class)->record('role.assigned', $person, meta: ['role' => 'job_seeker']);

    $this->get('/admin/audit?event=role.')->assertInertia(fn ($page) => $page->where('logs.data', fn ($logs) => collect($logs)->every(fn ($l) => str_starts_with($l['event'], 'role.'))));

    $csv = $this->get('/admin/audit/export?event=role.')->assertOk()->streamedContent();
    expect($csv)->toStartWith('time,event,outcome')->toContain('role.assigned')
        ->and(AuditLog::query()->where('event', 'audit.exported')->value('actor_id'))->toBe($admin->id);
});

it('rate-limits audit exports', function (): void {
    Console::as($this, 'super_admin');

    foreach (range(1, 5) as $i) {
        $this->get('/admin/audit/export')->assertOk();
    }
    $this->get('/admin/audit/export')->assertStatus(429);
});
