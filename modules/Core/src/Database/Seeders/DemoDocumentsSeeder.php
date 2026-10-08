<?php

declare(strict_types=1);

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Core\Documents\Models\Document;
use Modules\Core\Documents\Models\DocumentShare;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\Organisation;

/**
 * Clearly marked dummy PDFs for demo accounts: verified, pending review, rejected and expired.
 */
final class DemoDocumentsSeeder extends Seeder
{
    /** @var list<array{phone: string, type: string, status: string, expires?: string, reason?: string, share?: string}> */
    public const DOCUMENTS = [
        ['phone' => '+27720000001', 'type' => 'id_document', 'status' => 'verified'],
        ['phone' => '+27720000001', 'type' => 'matric_certificate', 'status' => 'verified', 'share' => 'Mopani Fresh Market'],
        ['phone' => '+27720000001', 'type' => 'qualification', 'status' => 'uploaded'],
        ['phone' => '+27720000002', 'type' => 'id_document', 'status' => 'verified'],
        ['phone' => '+27720000002', 'type' => 'proof_of_address', 'status' => 'rejected', 'reason' => 'The photo is too blurry to read the address'],
        ['phone' => '+27720000003', 'type' => 'cipc_certificate', 'status' => 'verified', 'share' => 'Ubuntu Community Bank (demo)'],
        ['phone' => '+27720000003', 'type' => 'tax_clearance', 'status' => 'verified', 'expires' => '-10 days'],
        ['phone' => '+27720000003', 'type' => 'bank_confirmation', 'status' => 'uploaded', 'expires' => '+20 days'],
    ];

    public function run(): void
    {
        $disk = (string) config('kasi.documents.disk');
        $reviewer = User::query()->where('phone', '+27720000040')->first();

        foreach (self::DOCUMENTS as $row) {
            $user = User::query()->where('phone', $row['phone'])->first();

            if ($user === null || Document::query()->where('user_id', $user->id)->where('type', $row['type'])->exists()) {
                continue;
            }

            $contents = self::dummyPdf("DEMO DOCUMENT - NOT REAL\n{$row['type']} for {$user->fullName()}");
            $path = sprintf('%s/%s.pdf', $user->id, Str::ulid());
            Storage::disk($disk)->put($path, $contents);

            $document = Document::query()->create([
                'user_id' => $user->id,
                'type' => $row['type'],
                'disk' => $disk,
                'path' => $path,
                'original_name' => str_replace('_', '-', $row['type']).'.pdf',
                'mime_type' => 'application/pdf',
                'size_bytes' => strlen($contents),
                'sha256' => hash('sha256', $contents),
                'status' => $row['status'],
                'rejection_reason' => $row['reason'] ?? null,
                'verified_by' => in_array($row['status'], ['verified', 'rejected'], true) ? $reviewer?->id : null,
                'verified_at' => in_array($row['status'], ['verified', 'rejected'], true) ? now()->subDays(20) : null,
                'expires_on' => isset($row['expires']) ? now()->modify($row['expires'])->toDateString() : null,
            ]);

            if (isset($row['share'])) {
                DocumentShare::query()->create([
                    'document_id' => $document->id,
                    'organisation_id' => Organisation::query()->where('name', $row['share'])->value('id'),
                    'purpose' => 'Demo: application',
                ]);
            }
        }
    }

    /** A tiny valid one-page PDF with a line of text. */
    public static function dummyPdf(string $text): string
    {
        $lines = array_map(static fn (string $line): string => '('.str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line).') Tj T*', explode("\n", $text));
        $stream = "BT /F1 18 Tf 50 750 Td 24 TL\n".implode("\n", $lines)."\nET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
