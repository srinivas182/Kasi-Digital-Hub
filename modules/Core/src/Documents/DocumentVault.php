<?php

declare(strict_types=1);

namespace Modules\Core\Documents;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Modules\Core\Access\AccessResolver;
use Modules\Core\Documents\Contracts\VirusScanner;
use Modules\Core\Documents\Jobs\ScanDocument;
use Modules\Core\Documents\Models\Document;
use Modules\Core\Documents\Models\DocumentShare;
use Modules\Core\Events\DocumentQuarantined;
use Modules\Core\Events\DocumentRejected;
use Modules\Core\Events\DocumentUploaded;
use Modules\Core\Events\DocumentVerified;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Structure\Models\Organisation;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The private document vault: upload (scanned before use), verify once / reuse everywhere,
 * share with consent, signed short-lived downloads, and deletion that removes the file (POPIA).
 * Every access is written to the audit log.
 */
final readonly class DocumentVault
{
    public function __construct(
        private VirusScanner $scanner,
        private AuditLogger $audit,
        private AccessResolver $access,
    ) {}

    public function store(User $owner, UploadedFile $file, string $type, ?string $expiresOn = null, ?User $uploadedBy = null): Document
    {
        $disk = (string) config('kasi.documents.disk');
        $extension = strtolower($file->getClientOriginalExtension() ?: (string) $file->extension());
        $path = sprintf('%s/%s.%s', $owner->id, Str::ulid(), $extension);

        $contents = $this->shrinkIfPhoto((string) file_get_contents($file->getRealPath()), $extension);
        Storage::disk($disk)->put($path, $contents);

        $document = Document::query()->create([
            'user_id' => $owner->id,
            'type' => $type,
            'disk' => $disk,
            'path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName(), 180, ''),
            'mime_type' => (string) $file->getMimeType(),
            'size_bytes' => strlen($contents),
            'sha256' => hash('sha256', $contents),
            'status' => Document::PENDING_SCAN,
            'expires_on' => $expiresOn,
            'uploaded_by' => $uploadedBy?->id,
        ]);

        $this->audit->record('document.uploaded', $owner, meta: ['document' => $document->id, 'type' => $type], actor: $uploadedBy);
        event(new DocumentUploaded($document, $uploadedBy?->id));
        ScanDocument::dispatch($document->id);

        return $document;
    }

    /** Virus scan: clean files become usable; infected files move to quarantine and cannot be opened. */
    public function scan(Document $document): void
    {
        $absolute = Storage::disk($document->disk)->path($document->path);

        if ($this->scanner->isClean($absolute)) {
            $document->update(['status' => Document::UPLOADED]);
            $this->audit->record('document.scanned_clean', $document->owner, meta: ['document' => $document->id]);

            return;
        }

        Storage::disk('quarantine')->put($document->path, Storage::disk($document->disk)->get($document->path) ?? '');
        Storage::disk($document->disk)->delete($document->path);
        $document->update(['status' => Document::QUARANTINED, 'disk' => 'quarantine']);

        $this->audit->record('document.quarantined', $document->owner, 'blocked', ['document' => $document->id]);
        event(new DocumentQuarantined($document));
    }

    public function verify(Document $document, User $by): void
    {
        $document->update(['status' => Document::VERIFIED, 'verified_by' => $by->id, 'verified_at' => now(), 'rejection_reason' => null]);
        $this->audit->record('document.verified', $document->owner, meta: ['document' => $document->id], actor: $by);
        event(new DocumentVerified($document, $by->id));
    }

    public function reject(Document $document, string $reason, User $by): void
    {
        $document->update(['status' => Document::REJECTED, 'verified_by' => $by->id, 'verified_at' => now(), 'rejection_reason' => $reason]);
        $this->audit->record('document.rejected', $document->owner, meta: ['document' => $document->id], actor: $by);
        event(new DocumentRejected($document, $by->id));
    }

    /** Remove the record and the file itself immediately. */
    public function delete(Document $document, User $by): void
    {
        DB::transaction(function () use ($document, $by): void {
            Storage::disk($document->disk)->delete($document->path);
            $document->shares()->whereNull('revoked_at')->update(['revoked_at' => now()]);
            $document->delete();
            $this->audit->record('document.deleted', $document->owner, meta: ['document' => $document->id], actor: $by->id === $document->user_id ? null : $by);
        });
    }

    public function share(Document $document, Organisation $organisation, string $purpose, ?\DateTimeInterface $expiresAt = null): DocumentShare
    {
        $share = DocumentShare::query()->create([
            'document_id' => $document->id,
            'organisation_id' => $organisation->id,
            'purpose' => $purpose,
            'expires_at' => $expiresAt,
        ]);

        $this->audit->record('document.shared', $document->owner, meta: ['document' => $document->id, 'organisation' => $organisation->id, 'purpose' => $purpose]);

        return $share;
    }

    public function revokeShare(DocumentShare $share): void
    {
        $share->update(['revoked_at' => now()]);
        $this->audit->record('document.share_revoked', $share->document?->owner, meta: ['document' => $share->document_id, 'organisation' => $share->organisation_id]);
    }

    /**
     * Who may open a document: the owner; members of an organisation it is actively shared
     * with; and national support/admin roles (always audited).
     */
    public function canOpen(User $user, Document $document): bool
    {
        if (! $document->canBeOpened()) {
            return false;
        }

        if ($document->user_id === $user->id) {
            return true;
        }

        $sharedWithMyOrganisation = $document->shares()
            ->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->whereIn('organisation_id', DB::table('organisation_members')->where('user_id', $user->id)->select('organisation_id'))
            ->exists();

        return $sharedWithMyOrganisation || $this->access->visibleHubIds($user, 'Admin') === '*';
    }

    /** A short-lived signed link; the permission check happens again when it is opened. */
    /** @param bool $inline Show in the browser (reviewer preview) instead of downloading. */
    public function temporaryUrl(Document $document, bool $inline = false): string
    {
        return URL::temporarySignedRoute('documents.download', now()->addMinutes((int) config('kasi.documents.link_minutes')), array_filter(['document' => $document->id, 'inline' => $inline ? 1 : null]));
    }

    public function download(Document $document, User $by, bool $inline = false): StreamedResponse
    {
        $this->audit->record($inline ? 'document.viewed' : 'document.downloaded', $document->owner, meta: ['document' => $document->id], actor: $by->id === $document->user_id ? null : $by);

        $name = Str::slug(pathinfo($document->original_name, PATHINFO_FILENAME)).'.'.pathinfo($document->path, PATHINFO_EXTENSION);
        $headers = [
            'Content-Type' => $document->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ];

        // Never let an uploaded file run scripts. Only PDFs and images are accepted (checked by
        // content on upload) and nosniff stops browsers guessing; images are also sandboxed.
        // PDFs are left without a sandbox so the browser's PDF viewer can show them.
        if ($document->mime_type !== 'application/pdf') {
            $headers['Content-Security-Policy'] = "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox";
        }

        return Storage::disk($document->disk)->response($document->path, $name, $headers, $inline ? 'inline' : 'attachment');
    }

    /** Phone photos of documents are often 4-12 MB; shrink to a readable size to save data and storage. */
    private function shrinkIfPhoto(string $contents, string $extension): string
    {
        if (! in_array($extension, ['jpg', 'jpeg', 'png'], true) || ! function_exists('imagecreatefromstring')) {
            return $contents;
        }

        $image = @imagecreatefromstring($contents);
        if ($image === false) {
            return $contents;
        }

        $max = (int) config('kasi.documents.image_max_px');
        $width = imagesx($image);
        $height = imagesy($image);

        if (max($width, $height) <= $max) {
            imagedestroy($image);

            return $contents;
        }

        $ratio = $max / max($width, $height);
        $resized = imagescale($image, max(1, (int) round($width * $ratio)), max(1, (int) round($height * $ratio)));
        imagedestroy($image);

        if ($resized === false) {
            return $contents;
        }

        ob_start();
        $extension === 'png' ? imagepng($resized, null, 6) : imagejpeg($resized, null, 82);
        $output = (string) ob_get_clean();
        imagedestroy($resized);

        return $output;
    }
}
