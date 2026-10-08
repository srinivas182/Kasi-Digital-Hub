<?php

declare(strict_types=1);

namespace Modules\Core\Notifications\Messages;

use App\Support\Format\SaFormat;
use Modules\Core\Documents\Models\Document;
use Modules\Core\Identity\Models\User;
use Modules\Core\Notifications\KasiNotification;

final class DocumentExpiringNotification extends KasiNotification
{
    public const KEY = 'document_expiring';

    public const CATEGORY = 'account';

    public const WHATSAPP_TEMPLATE = 'kasihub_document_expiring';

    public function __construct(private readonly Document $document) {}

    public function title(User $user): string
    {
        return $this->t($user, 'notify.document_expiring.title', ['document' => $this->t($user, 'documents.type.'.$this->document->type)]);
    }

    public function body(User $user): string
    {
        return $this->t($user, 'notify.document_expiring.body', ['date' => SaFormat::date((string) $this->document->expires_on)]);
    }

    public function cause(User $user): string
    {
        return $this->t($user, 'notify.document_expiring.cause');
    }

    public function url(): string
    {
        return '/account?tab=documents';
    }

    public function dedupeKey(): string
    {
        return 'document_expiring:'.$this->document->id;
    }

    public static function whatsappBody(): string
    {
        return 'Reminder from KasiHub: your {{1}} expires on {{2}}. Upload a new one so your applications are not held up.';
    }

    public function whatsappParams(User $user): array
    {
        return [$this->t($user, 'documents.type.'.$this->document->type), SaFormat::date((string) $this->document->expires_on)];
    }
}
