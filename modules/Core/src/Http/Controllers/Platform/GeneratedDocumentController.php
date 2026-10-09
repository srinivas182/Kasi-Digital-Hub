<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Platform;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Documents\Generation\DocumentIssuer;
use Modules\Core\Documents\Generation\DocumentShareLink;
use Modules\Core\Documents\Generation\GeneratedDocument;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Public verification of issued documents, share links, and owners downloading their own.
 */
final class GeneratedDocumentController
{
    public function verifyForm(Request $request): Response|RedirectResponse
    {
        $code = strtoupper(trim((string) $request->query('code', '')));

        return $code !== '' ? to_route('verify.show', ['code' => $code]) : Inertia::render('Core/Verify', ['code' => null, 'result' => null]);
    }

    public function verify(string $code): Response
    {
        $document = GeneratedDocument::query()->with('owner:id,first_name,last_name')->where('verification_code', strtoupper($code))->first();

        return Inertia::render('Core/Verify', [
            'code' => strtoupper($code),
            'result' => $document === null ? ['status' => 'not_found'] : [
                'status' => $document->status(),
                'type' => $document->type,
                'title' => $document->title,
                'issuedAt' => $document->issued_at->toIso8601String(),
                'expiresAt' => $document->expires_at?->toIso8601String(),
                'holder' => $document->show_name ? $document->owner->fullName() : null,
            ],
        ]);
    }

    public function download(Request $request, GeneratedDocument $document, DocumentIssuer $issuer, AuditLogger $audit): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $document->user_id === $user->id, 404);
        $audit->record('document.generated_downloaded', $user, meta: ['document' => $document->id]);

        return $issuer->download($document);
    }

    public function shared(Request $request, string $token, DocumentIssuer $issuer, AuditLogger $audit): \Symfony\Component\HttpFoundation\Response
    {
        $link = DocumentShareLink::query()->with('document.owner')->where('token_hash', hash('sha256', $token))->first();

        if ($link === null || ! $link->usable()) {
            return Inertia::render('Core/Verify', ['code' => null, 'result' => ['status' => 'link_expired']])->toResponse($request)->setStatusCode(410);
        }

        $link->forceFill(['views' => $link->views + 1, 'last_viewed_at' => now()])->save();
        $audit->record('document.share_viewed', $link->document->owner, meta: ['document' => $link->generated_document_id]);

        return $issuer->download($link->document, 'inline');
    }
}
