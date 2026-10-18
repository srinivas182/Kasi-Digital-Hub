<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Modules\Core\Documents\FileDelivery;

/* S22: with object storage, files are delivered by a short-lived presigned link (no PHP streaming). */
it('redirects to a short-lived presigned link when the disk is object storage', function (): void {
    config(['filesystems.disks.documents' => ['driver' => 's3', 'key' => 'test', 'secret' => 'test', 'region' => 'af-south-1', 'bucket' => 'kasi-docs', 'visibility' => 'private', 'throw' => true]]);
    Storage::forgetDisk('documents');

    $response = FileDelivery::send('documents', 'u1/id.pdf', 'id.pdf', ['Content-Type' => 'application/pdf'], 'attachment');

    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toContain('kasi-docs')->toContain('X-Amz-Signature')->toMatch('/X-Amz-Expires=(299|300)\b/') // a second can pass while signing
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('streams from local disk as before', function (): void {
    Storage::fake('documents');
    Storage::disk('documents')->put('u1/id.pdf', '%PDF-1.4 test');

    $response = FileDelivery::send('documents', 'u1/id.pdf', 'id.pdf', ['Content-Type' => 'application/pdf'], 'attachment');
    expect($response->getStatusCode())->toBe(200);
});
