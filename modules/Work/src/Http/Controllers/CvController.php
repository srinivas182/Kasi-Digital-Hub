<?php

declare(strict_types=1);

namespace Modules\Work\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Ai\Speech\VoiceNotes;
use Modules\Core\Documents\Generation\DocumentIssuer;
use Modules\Core\Documents\Generation\DocumentShareLink;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Work\Models\WorkCv;
use Modules\Work\Services\CvAssistant;
use Modules\Work\Services\CvComposer;
use Modules\Work\Services\SeekerProfile;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CVs: preview, AI help with the profile summary, create PDF versions, download, share by link,
 * switch links off. Voice notes are transcribed here too.
 */
final class CvController extends WorkController
{
    public function index(Request $request, CvComposer $composer, SeekerProfile $profiles): Response
    {
        [$user, $by] = $this->who($request);
        $profile = $profiles->for($user);

        return Inertia::render('Work/Cv', [
            'person' => ['name' => $user->fullName(), 'assisted' => $by !== null],
            'ready' => $profile->exists && $profile->headline !== null,
            'completeness' => $profile->completeness,
            'preview' => $composer->content($user),
            'templates' => WorkCv::TEMPLATES,
            'shareUrl' => $request->session()->get('share_url'),
            'versions' => WorkCv::query()->with('document')->where('user_id', $user->id)->latest('created_at')->limit(10)->get()
                ->map(static fn (WorkCv $cv): array => [
                    'id' => $cv->id, 'template' => $cv->template, 'createdAt' => $cv->created_at->toIso8601String(),
                    'code' => $cv->document?->verification_code,
                    'links' => $cv->document === null ? [] : DocumentShareLink::query()->where('generated_document_id', $cv->document->id)->latest('created_at')->get()
                        ->map(static fn (DocumentShareLink $l): array => ['id' => $l->id, 'expiresAt' => $l->expires_at->toIso8601String(), 'active' => $l->usable(), 'views' => $l->views]),
                ]),
        ]);
    }

    public function suggestSummary(Request $request, CvAssistant $assistant, SeekerProfile $profiles): JsonResponse
    {
        [$user, $by] = $this->who($request);
        $result = $assistant->suggestSummary($user, $profiles->for($user), $by);

        return response()->json($result['ok'] ? $result : ['ok' => false, 'message' => __('work.ai.fallback.'.($result['reason'] ?? 'unavailable'))]);
    }

    public function acceptSummary(Request $request, SeekerProfile $profiles): RedirectResponse
    {
        [$user, $by] = $this->who($request);
        $data = $request->validate(['summary' => ['required', 'string', 'max:1200']]);
        $profiles->save($user, ['summary' => $data['summary']], $by);

        return back()->with('status', __('work.saved'));
    }

    public function store(Request $request, CvComposer $composer): RedirectResponse
    {
        [$user, $by] = $this->who($request);
        $data = $request->validate(['template' => ['required', Rule::in(WorkCv::TEMPLATES)]]);
        $composer->create($user, $data['template'], $by);

        return back()->with('status', __('work.cv.created'));
    }

    public function download(Request $request, WorkCv $cv, DocumentIssuer $issuer, AuditLogger $audit): StreamedResponse
    {
        [$user, $by] = $this->who($request);
        abort_unless($cv->user_id === $user->id && $cv->document !== null, 404);
        $audit->record('work.cv_downloaded', $user, meta: ['cv' => $cv->id], actor: $by);

        return $issuer->download($cv->document);
    }

    public function share(Request $request, WorkCv $cv, DocumentIssuer $issuer): RedirectResponse
    {
        [$user, $by] = $this->who($request);
        abort_unless($cv->user_id === $user->id && $cv->document !== null, 404);

        return back()->with('share_url', $issuer->share($cv->document, 30, $by ?? $user));
    }

    public function stopSharing(Request $request, WorkCv $cv, DocumentShareLink $link, DocumentIssuer $issuer): RedirectResponse
    {
        [$user, $by] = $this->who($request);
        abort_unless($cv->user_id === $user->id && $link->generated_document_id === $cv->generated_document_id, 404);
        $issuer->revokeShare($link, $by ?? $user);

        return back()->with('status', __('work.cv.link_stopped'));
    }

    public function destroy(Request $request, WorkCv $cv, DocumentIssuer $issuer): RedirectResponse
    {
        [$user, $by] = $this->who($request);
        abort_unless($cv->user_id === $user->id, 404);
        if ($cv->document !== null) {
            $issuer->revoke($cv->document, 'Deleted by the owner', $by ?? $user);
        }
        $cv->delete();

        return back()->with('status', __('work.deleted'));
    }

    /** A voice note becomes text the person can edit. The audio is not kept. */
    public function voice(Request $request, VoiceNotes $voice): JsonResponse
    {
        [$user, $by] = $this->who($request);
        $data = $request->validate([
            'audio' => ['required', 'file', 'max:5120', 'mimetypes:audio/webm,audio/ogg,audio/mp4,audio/mpeg,audio/wav,audio/x-wav,audio/aac,video/webm'],
            'language' => ['required', 'string', 'max:8'],
            'seconds' => ['required', 'integer', 'min:1', 'max:'.(int) config('kasi.speech.max_seconds')],
        ]);

        $result = $voice->transcribe($request->file('audio'), $data['language'], (int) $data['seconds'], $user, $by);

        return response()->json($result->ok ? ['ok' => true, 'text' => $result->text] : ['ok' => false, 'message' => __('work.voice.fallback.'.$result->reason)]);
    }
}
