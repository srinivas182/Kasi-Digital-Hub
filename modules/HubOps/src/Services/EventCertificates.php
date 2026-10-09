<?php

declare(strict_types=1);

namespace Modules\HubOps\Services;

use App\Support\Format\SaFormat;
use Modules\Core\Documents\Generation\DocumentIssuer;
use Modules\Core\Documents\Generation\GeneratedDocument;
use Modules\Core\Identity\Models\User;
use Modules\HubOps\Models\HubEvent;

/**
 * Certificates of attendance for hub events, issued once per person per event and verifiable
 * by QR code. Proof of participation for job, bursary and learnership applications.
 */
final readonly class EventCertificates
{
    public const TEMPLATE_VERSION = 1;

    public function __construct(private DocumentIssuer $issuer) {}

    public function forAttendee(HubEvent $event, User $person): GeneratedDocument
    {
        $existing = GeneratedDocument::query()->where('user_id', $person->id)->where('subject_type', 'hub_event')
            ->where('subject_id', $event->id)->whereNull('revoked_at')->first();

        if ($existing !== null) {
            return $existing;
        }

        $event->loadMissing('hub.place');

        return $this->issuer->issue(
            owner: $person,
            type: 'attendance_certificate',
            title: 'Certificate of attendance - '.$event->title,
            view: 'hubops::certificates.attendance',
            data: [
                'person' => $person->fullName(),
                'eventTitle' => $event->title,
                'eventType' => __('hubops.events.type.'.$event->type, [], 'en'),
                'eventDate' => SaFormat::date($event->starts_at),
                'hubName' => $event->hub->name,
                'hubPlace' => $event->hub->place?->name,
            ],
            templateVersion: self::TEMPLATE_VERSION,
            subject: ['hub_event', $event->id],
        );
    }
}
