<?php

declare(strict_types=1);

namespace Modules\Site\Http\Controllers;

use App\Support\Format\SaFormat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Modules\Core\Identity\Contracts\BotCheck;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Structure\Models\Hub;
use Modules\Site\Models\Enquiry;

/**
 * Stores website enquiries (contact, employer interest, funder/partner enquiry) and emails
 * the platform team. Spam protection: hidden honeypot field, bot check and rate limits.
 */
final class EnquiryController
{
    public function store(Request $request, string $kind, BotCheck $botCheck, AuditLogger $audit): RedirectResponse
    {
        abort_unless(in_array($kind, Enquiry::KINDS, true), 404);

        // Honeypot: people never see or fill this field; bots usually do. Pretend success.
        if (filled($request->input('website'))) {
            return back()->with('status', __('site.form.sent'));
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'organisation' => [Rule::requiredIf($kind !== 'contact'), 'nullable', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:20', 'required_without:email'],
            'email' => ['nullable', 'email:rfc', 'max:190', 'required_without:phone'],
            'topic' => ['nullable', Rule::in(Enquiry::TOPICS)],
            'hub' => ['nullable', 'string', Rule::exists(Hub::class, 'slug')],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
            'consent' => ['accepted'],
            'bot_token' => ['nullable', 'string', 'max:2048'],
        ]);

        if (! $botCheck->passes($validated['bot_token'] ?? null, $request->ip())) {
            return back()->withErrors(['message' => __('auth.phone.bot_check_failed')]);
        }

        $phone = isset($validated['phone']) ? SaFormat::normalisePhone($validated['phone']) : null;
        if (isset($validated['phone']) && $phone === null) {
            return back()->withErrors(['phone' => __('auth.phone.invalid')]);
        }

        $enquiry = Enquiry::query()->create([
            'kind' => $kind,
            'name' => trim($validated['name']),
            'organisation' => $validated['organisation'] ?? null,
            'phone' => $phone,
            'email' => $validated['email'] ?? null,
            'topic' => $validated['topic'] ?? null,
            'hub_id' => isset($validated['hub']) ? Hub::query()->where('slug', $validated['hub'])->value('id') : null,
            'message' => trim($validated['message']),
            'ip_hash' => hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')),
        ]);

        Mail::raw(
            "New {$kind} enquiry on the KasiHub website\n\nFrom: {$enquiry->name}\nOrganisation: ".($enquiry->organisation ?? '-')
            ."\nPhone: ".($enquiry->phone ?? '-')."\nEmail: ".($enquiry->email ?? '-')."\nTopic: ".($enquiry->topic ?? '-')
            ."\n\n{$enquiry->message}\n\nReference: {$enquiry->id}",
            static fn ($message) => $message->to((string) config('kasi.brand.contact_email'))->subject("Website {$kind} enquiry from {$enquiry->name}"),
        );

        $audit->record('site.enquiry_received', meta: ['enquiry' => $enquiry->id, 'kind' => $kind]);

        return back()->with('status', __('site.form.sent'));
    }
}
