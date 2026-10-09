<?php

declare(strict_types=1);

namespace Modules\Core\Documents\Generation;

use Modules\Core\Database\Seeders\DemoDocumentsSeeder;

/** A small valid PDF holding the page's text - for demos and CI, no PDF service needed. */
final class FakePdfRenderer implements PdfRenderer
{
    /** @var list<string> */
    public static array $rendered = [];

    public function render(string $html): string
    {
        self::$rendered[] = $html;
        $text = trim((string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) preg_replace('#<(style|svg)\b.*?</\1>#is', '', $html)))));

        return DemoDocumentsSeeder::dummyPdf('DEMO PDF - '.mb_substr($text, 0, 400));
    }
}
