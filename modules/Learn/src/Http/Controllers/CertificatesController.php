<?php

declare(strict_types=1);

namespace Modules\Learn\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Access\AccessResolver;
use Modules\Core\Documents\Generation\DocumentIssuer;
use Modules\Core\Identity\Models\User;
use Modules\Learn\Models\Certificate;
use Modules\Learn\Services\Certificates;
use Modules\Learn\Services\CurrentProvider;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** My certificates (learners), learning record, show on CV, and revocation (provider admins). */
final class CertificatesController
{
    public function index(Request $request): Response
    {
        $user = $this->user($request);

        return Inertia::render('Learn/Certificates', [
            'certificates' => Certificate::query()->with(['enrolment.course.organisation', 'document'])->whereHas('enrolment', fn ($q) => $q->where('user_id', $user->id))
                ->orderByDesc('issued_at')->get()->map(static fn (Certificate $c): array => [
                    'id' => $c->id, 'kind' => $c->kind, 'course' => $c->enrolment->course->title, 'provider' => $c->enrolment->course->organisation->displayName(),
                    'issuedAt' => $c->issued_at->toIso8601String(), 'code' => $c->document?->verification_code, 'showOnCv' => $c->show_on_cv,
                    'revoked' => $c->revoked_at !== null, 'reason' => $c->revoke_reason,
                ]),
        ]);
    }

    public function download(Request $request, Certificate $certificate, DocumentIssuer $issuer): StreamedResponse
    {
        $user = $this->user($request);
        abort_unless($certificate->enrolment->user_id === $user->id && $certificate->document !== null && $certificate->revoked_at === null, 404);

        return $issuer->download($certificate->document);
    }

    public function share(Request $request, Certificate $certificate, DocumentIssuer $issuer): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($certificate->enrolment->user_id === $user->id && $certificate->document !== null && $certificate->revoked_at === null, 404);

        return back()->with('share_url', $issuer->share($certificate->document, 30, $user));
    }

    public function cv(Request $request, Certificate $certificate): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($certificate->enrolment->user_id === $user->id, 404);
        $certificate->forceFill(['show_on_cv' => $request->boolean('show')])->save();

        return back()->with('status', __('work.saved'));
    }

    /** A one-page record of all completed courses (verifiable). */
    public function record(Request $request, DocumentIssuer $issuer): StreamedResponse
    {
        $user = $this->user($request);
        $rows = Certificate::query()->with(['enrolment.course.organisation', 'document'])->whereNull('revoked_at')
            ->whereHas('enrolment', fn ($q) => $q->where('user_id', $user->id))->orderBy('issued_at')->get()
            ->map(static fn (Certificate $c): array => ['course' => $c->enrolment->course->title, 'provider' => $c->enrolment->course->organisation->displayName(),
                'date' => $c->issued_at, 'kind' => $c->kind, 'code' => $c->document?->verification_code])->all();
        abort_if($rows === [], 404);
        $document = $issuer->issue(owner: $user, type: 'learn_record', title: 'Learning record - '.$user->fullName(), view: 'learn::certificates.record',
            data: ['name' => $user->fullName(), 'rows' => $rows], templateVersion: 1, subject: ['learn_record', $user->id]);

        return $issuer->download($document);
    }

    /** Provider admins (or the KasiHub team) revoke a certificate with a reason. */
    public function revoke(Request $request, Certificate $certificate, Certificates $certificates, CurrentProvider $providers): RedirectResponse
    {
        $user = $this->user($request);
        $provider = $certificate->enrolment->course->organisation;
        abort_unless($providers->has($user, $provider, 'provider_admin') || app(AccessResolver::class)->hasPermission($user, 'learn.review'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:300']]);
        $certificates->revoke($certificate, $data['reason'], $user);

        return back()->with('status', __('learn.certificate.revoked'));
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
