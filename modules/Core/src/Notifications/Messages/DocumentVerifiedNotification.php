<?php

declare(strict_types=1);

namespace Modules\Core\Notifications\Messages;

use Modules\Core\Documents\Models\Document;
use Modules\Core\Identity\Models\User;
use Modules\Core\Notifications\KasiNotification;

final class DocumentVerifiedNotification extends KasiNotification
{
    public const KEY = 'document_verified';

    public const CATEGORY = 'account';

    public const WHATSAPP_TEMPLATE = 'kasihub_document_verified';

    public function __construct(private readonly Document $document) {}

    public function title(User $user): string
    {
        return $this->t($user, 'notify.document_verified.title', ['document' => $this->t($user, 'documents.type.'.$this->document->type)]);
    }

    public function body(User $user): string
    {
        return $this->t($user, 'notify.document_verified.body');
    }

    public function cause(User $user): string
    {
        return $this->t($user, 'notify.document_verified.cause');
    }

    public function url(): string
    {
        return '/account?tab=documents';
    }

    public function dedupeKey(): string
    {
        return 'document_verified:'.$this->document->id;
    }

    public static function whatsappBody(): string
    {
        return 'Good news: your {{1}} has been verified on KasiHub. You won\'t need to upload it again - every KasiHub service can use it.';
    }

    public function whatsappParams(User $user): array
    {
        return [$this->t($user, 'documents.type.'.$this->document->type)];
    }
}
