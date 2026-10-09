<?php

declare(strict_types=1);

namespace Modules\Core\Ai\Speech;

/** Speech-to-text driver (ADR-004). Throws AiUnavailable when the provider fails. */
interface SpeechToText
{
    /** @param string $language ISO code, e.g. "en", "zu", "ts" */
    public function transcribe(string $path, string $mimeType, string $language): string;

    public function model(): string;
}
