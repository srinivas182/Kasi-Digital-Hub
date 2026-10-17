<?php

declare(strict_types=1);

namespace Modules\Site\Help;

/**
 * The help centre's simple, safe text format (ADR-029): blank lines separate paragraphs, "## " starts a
 * heading, "- " starts a bullet. Everything is escaped - no HTML from authors ever reaches the page.
 */
final class HelpText
{
    public static function html(string $text): string
    {
        $out = [];
        foreach (preg_split("/\n\s*\n/", str_replace("\r", '', trim($text))) ?: [] as $block) {
            $lines = array_values(array_filter(array_map('trim', explode("\n", $block)), static fn (string $l): bool => $l !== ''));
            if ($lines === []) {
                continue;
            }
            if (str_starts_with($lines[0], '## ')) {
                $out[] = '<h2>'.e(mb_substr($lines[0], 3)).'</h2>';
                $lines = array_slice($lines, 1);
                if ($lines === []) {
                    continue;
                }
            }
            if (array_filter($lines, static fn (string $l): bool => ! str_starts_with($l, '- ')) === []) {
                $out[] = '<ul>'.implode('', array_map(static fn (string $l): string => '<li>'.e(mb_substr($l, 2)).'</li>', $lines)).'</ul>';
            } else {
                $out[] = '<p>'.implode('<br>', array_map('e', $lines)).'</p>';
            }
        }

        return implode("\n", $out);
    }

    public static function plain(string $text): string
    {
        return trim((string) preg_replace('/^(## |- )/m', '', $text));
    }
}
