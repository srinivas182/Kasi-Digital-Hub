<?php

declare(strict_types=1);

namespace Modules\Core\Notifications\Messages;

use Modules\Core\Documents\Models\Document;
use Modules\Core\Identity\Models\User;
use Modules\Core\Notifications\KasiNotification;

final class DocumentRejectedNotification extends KasiNotification
{
    public const KEY = 'document_rejected';

    public const CATEGORY = 'account';

    public const WHATSAPP_TEMPLATE = 'kasihub_document_rejected';

    public function __construct(private readonly Document $document) {}

    public function channels(): array
    {
        return ['in_app', 'whatsapp', 'sms', 'email'];
    }

    public function title(User $user): string
    {
        return $this->t($user, 'notify.document_rejected.title', ['document' => $this->t($user, 'documents.type.'.$this->document->type)]);
    }

    public function body(User $user): string
    {
        return $this->t($user, 'notify.document_rejected.body', ['reason' => (string) $this->document->rejection_reason]);
    }

    public function cause(User $user): string
    {
        return $this->t($user, 'notify.document_rejected.cause');
    }

    public function url(): string
    {
        return '/account?tab=documents';
    }

    public function important(): bool
    {
        return true;
    }

    public function dedupeKey(): string
    {
        return 'document_rejected:'.$this->document->id;
    }

    public static function whatsappBody(): string
    {
        return 'We couldn\'t accept your {{1}} on KasiHub. Reason: {{2}}. Please upload a clear copy, or ask your hub facilitator for help.';
    }

    public function whatsappParams(User $user): array
    {
        return [$this->t($user, 'documents.type.'.$this->document->type), (string) $this->document->rejection_reason];
    }
}
