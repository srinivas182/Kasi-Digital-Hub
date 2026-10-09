<?php

declare(strict_types=1);

namespace Modules\Core\Documents\Generation;

use DateTimeInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Support\Qr;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Issues branded PDFs with a QR code linking to the public verification page, and manages
 * share links. Templates are Blade views; their version is stored with every document.
 */
final readonly class DocumentIssuer
{
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0/O or 1/I

    public function __construct(private PdfRenderer $renderer, private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data  View data
     * @param  array{0: string, 1: string}|null  $subject  What it is about, e.g. ['hub_event', $id]
     */
    public function issue(
        User $owner,
        string $type,
        string $title,
        string $view,
        array $data,
        int $templateVersion,
        ?array $subject = null,
        bool $showName = true,
        ?DateTimeInterface $expiresAt = null,
    ): GeneratedDocument {
        $code = $this->newCode();
        $verifyUrl = route('verify.show', ['code' => $code]);
        /** @var view-string $view */
        $html = view($view, [...$data, 'title' => $title, 'code' => $code, 'verifyUrl' => $verifyUrl, 'qr' => Qr::svg($verifyUrl, 140), 'issuedAt' => now(), 'owner' => $owner])->render();
        $pdf = $this->renderer->render($html);

        $document = new GeneratedDocument([
            'user_id' => $owner->id, 'type' => $type, 'template_version' => $templateVersion, 'title' => $title,
            'disk' => (string) config('kasi.documents.disk'), 'sha256' => hash('sha256', $pdf), 'verification_code' => $code,
            'show_name' => $showName, 'subject_type' => $subject[0] ?? null, 'subject_id' => $subject[1] ?? null,
            'issued_at' => now(), 'expires_at' => $expiresAt,
        ]);
        $document->id = (string) Str::ulid();
        $document->path = "generated/{$owner->id}/{$document->id}.pdf";
        Storage::disk($document->disk)->put($document->path, $pdf);
        $document->save();

        $this->audit->record('document.generated', $owner, meta: ['document' => $document->id, 'type' => $type]);

        return $document;
    }

    public function download(GeneratedDocument $document, string $disposition = 'attachment'): StreamedResponse
    {
        return Storage::disk($document->disk)->response($document->path, Str::slug($document->title).'.pdf', [
            'Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ], $disposition);
    }

    public function revoke(GeneratedDocument $document, string $reason, User $by): void
    {
        $document->forceFill(['revoked_at' => now(), 'revoke_reason' => $reason])->save();
        $this->audit->record('document.revoked', $document->owner, meta: ['document' => $document->id, 'reason' => $reason], actor: $by);
    }

    /** A link anyone can open until it expires or is revoked. The token is shown once. */
    public function share(GeneratedDocument $document, int $days, User $by): string
    {
        $token = Str::random(32);
        DocumentShareLink::query()->create([
            'generated_document_id' => $document->id, 'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays($days), 'created_by' => $by->id,
        ]);
        $this->audit->record('document.shared', $document->owner, meta: ['document' => $document->id, 'days' => $days], actor: $by);

        return route('share.show', ['token' => $token]);
    }

    public function revokeShare(DocumentShareLink $link, User $by): void
    {
        $link->forceFill(['revoked_at' => now()])->save();
        $this->audit->record('document.share_revoked', $link->document->owner, meta: ['document' => $link->generated_document_id], actor: $by);
    }

    private function newCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 10; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
        } while (GeneratedDocument::query()->where('verification_code', $code)->exists());

        return $code;
    }
}
