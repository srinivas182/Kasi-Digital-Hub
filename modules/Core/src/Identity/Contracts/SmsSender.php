<?php

declare(strict_types=1);

namespace Modules\Core\Identity\Contracts;

/**
 * Sends SMS messages (ADR-004). Demo/CI use the log driver; a South African gateway
 * driver is configured at deployment.
 */
interface SmsSender
{
    public function send(string $phone, string $message): void;
}
