<?php

declare(strict_types=1);

namespace Modules\Core\Notifications\Contracts;

/**
 * Sends approved WhatsApp template messages through a WhatsApp Business provider (ADR-004).
 * Business-initiated WhatsApp messages must use templates approved by Meta
 * (see docs/whatsapp-templates.md).
 */
interface WhatsAppSender
{
    /**
     * @param  list<string>  $params  Values for the template's {{1}}, {{2}}... placeholders
     * @return string Provider message reference
     *
     * @throws \RuntimeException when the message cannot be delivered
     */
    public function send(string $phone, string $template, string $language, array $params): string;
}
