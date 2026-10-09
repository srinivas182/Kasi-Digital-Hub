<?php

declare(strict_types=1);

namespace Modules\Core\Ai;

/**
 * Removes personal identifiers before text leaves the platform for an AI provider:
 * SA ID numbers (and other long digit runs), phone numbers, email addresses and street addresses.
 */
final class Redactor
{
    public const MARK = '[removed]';

    public static function redact(string $text): string
    {
        $patterns = [
            '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i',                         // email
            '/(?<!\d)(?:\+?27|0)[\s-]?\d{2}[\s-]?\d{3}[\s-]?\d{4}(?!\d)/',      // SA phone numbers
            '/(?<!\d)\d{9,}(?!\d)/',                                              // ID numbers, account numbers
            '/\b\d{1,5}\s+(?:[A-Z][a-z]+\s+){1,3}(?:Street|St|Road|Rd|Avenue|Ave|Drive|Dr|Lane|Close|Crescent|Way)\b\.?/', // street address
        ];

        return (string) preg_replace($patterns, self::MARK, $text);
    }

    /**
     * @param  array<string, string>  $vars
     * @return array<string, string>
     */
    public static function redactAll(array $vars): array
    {
        return array_map(self::redact(...), $vars);
    }
}
