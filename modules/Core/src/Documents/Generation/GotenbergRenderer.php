<?php

declare(strict_types=1);

namespace Modules\Core\Documents\Generation;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Gotenberg (a Chromium-based PDF service in its own container) - full CSS, our self-hosted fonts.
 * A4, printed backgrounds, no headers/footers added by the browser.
 */
final class GotenbergRenderer implements PdfRenderer
{
    public function render(string $html): string
    {
        try {
            $response = Http::timeout((int) config('kasi.pdf.timeout_seconds'))
                ->attach('files', $html, 'index.html')
                ->post(rtrim((string) config('kasi.pdf.gotenberg_url'), '/').'/forms/chromium/convert/html', [
                    'paperWidth' => '8.27', 'paperHeight' => '11.69',
                    'marginTop' => '0', 'marginBottom' => '0', 'marginLeft' => '0', 'marginRight' => '0',
                    'printBackground' => 'true',
                ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('PDF service unavailable: '.$e->getMessage(), previous: $e);
        }

        if (! $response->successful() || ! str_starts_with($response->body(), '%PDF')) {
            throw new RuntimeException('PDF service failed with status '.$response->status());
        }

        return $response->body();
    }
}
