<?php

declare(strict_types=1);

namespace Modules\Brand\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Brand\Events\DesignExported;
use Modules\Core\Documents\Generation\PdfRenderer;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;

/**
 * Designs from templates (ADR-038): each template has plain fields with length limits so the text always fits; the
 * brand kit is applied when the design is drawn (in the browser for PNG, as HTML for the PDF service).
 */
final readonly class Designs
{
    /** template => [size, fields => max length] */
    public const TEMPLATES = [
        'flyer' => ['size' => 'a5', 'fields' => ['headline' => 40, 'text' => 200, 'detail' => 80]],
        'poster' => ['size' => 'a4', 'fields' => ['headline' => 30, 'text' => 160, 'detail' => 80]],
        'price_list' => ['size' => 'a4', 'fields' => ['headline' => 40, 'items' => 1200]], // "name | price" per line, up to 20 lines
        'offer' => ['size' => 'square', 'fields' => ['headline' => 30, 'text' => 120, 'until' => 10]],
        'now_open' => ['size' => 'square', 'fields' => ['headline' => 30, 'text' => 120]],
        'hiring' => ['size' => 'a5', 'fields' => ['headline' => 40, 'text' => 200, 'link' => 120]],
        'square' => ['size' => 'square', 'fields' => ['headline' => 40, 'text' => 140]],
        'status' => ['size' => 'story', 'fields' => ['headline' => 30, 'text' => 160]],
    ];

    public const MAX_PRICE_LINES = 20;

    public function __construct(private AuditLogger $audit) {}

    /**
     * Keep only the template's fields, trimmed to their limits.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, string>
     */
    public static function clean(string $template, array $fields): array
    {
        if (! isset(self::TEMPLATES[$template])) {
            throw new DomainException(__('brand.design.unknown_template'));
        }
        $out = [];
        foreach (self::TEMPLATES[$template]['fields'] as $key => $max) {
            $value = trim((string) ($fields[$key] ?? ''));
            if ($key === 'items') {
                $value = implode("\n", array_slice(array_filter(array_map('trim', explode("\n", $value))), 0, self::MAX_PRICE_LINES));
            }
            if ($key === 'until' && $value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
                $value = '';
            }
            $out[$key] = mb_substr($value, 0, $max);
        }

        return $out;
    }

    /** @param array<string, mixed> $fields */
    public function save(string $businessId, User $by, string $template, string $title, array $fields, ?int $designId = null): int
    {
        $values = ['template' => $template, 'title' => mb_substr(trim($title), 0, 80) ?: __('brand.template.'.$template), 'fields' => json_encode(self::clean($template, $fields)), 'updated_at' => now()];
        if ($designId !== null) {
            $updated = DB::table('brand_designs')->where('id', $designId)->where('business_id', $businessId)->update($values);
            if ($updated === 0) {
                throw new DomainException(__('brand.design.not_found'));
            }
        } else {
            $designId = (int) DB::table('brand_designs')->insertGetId([...$values, 'business_id' => $businessId, 'created_at' => now()]);
        }
        $this->audit->record('brand.design_saved', meta: ['design' => $designId], actor: $by);

        return $designId;
    }

    public function duplicate(string $businessId, int $designId): int
    {
        $d = DB::table('brand_designs')->where('id', $designId)->where('business_id', $businessId)->first();
        if ($d === null) {
            throw new DomainException(__('brand.design.not_found'));
        }

        return (int) DB::table('brand_designs')->insertGetId(['business_id' => $businessId, 'template' => $d->template, 'title' => mb_substr(__('brand.design.copy_of', ['title' => $d->title]), 0, 80),
            'fields' => $d->fields, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function exported(string $businessId, User $by, string $template, string $format, ?User $subject = null): void
    {
        DB::table('brand_exports')->insert(['business_id' => $businessId, 'template' => $template, 'format' => $format, 'created_at' => now()]);
        event(new DesignExported($subject ?? $by, $subject !== null ? $by->id : null, ['template' => $template, 'format' => $format]));
    }

    /**
     * A print-ready PDF through the platform's PDF service (placeholder driver on staging/tests).
     *
     * @param  array<string, mixed>  $kit
     * @param  array<string, string>  $fields
     */
    public function pdf(array $kit, string $template, array $fields, ?string $logoSvg, string $businessName): string
    {
        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        $size = ['a5' => 'A5', 'a4' => 'A4', 'square' => '200mm 200mm', 'story' => '108mm 192mm'][self::TEMPLATES[$template]['size']];
        $items = '';
        if ($template === 'price_list') {
            foreach (explode("\n", $fields['items'] ?? '') as $line) {
                [$name, $price] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
                $items .= '<tr><td>'.$e($name).'</td><td style="text-align:right">'.$e($price).'</td></tr>';
            }
        }
        $fg = Kits::readableOn((string) $kit['primary']);

        return app(PdfRenderer::class)->render('<!doctype html><html><head><meta charset="utf-8"><style>@page{size:'.$size.';margin:0}body{margin:0;font-family:system-ui,sans-serif}'
            .'.top{background:'.$kit['primary'].';color:'.$fg.';padding:12mm}.band{height:4mm;background:'.$kit['secondary'].'}h1{margin:0 0 4mm;font-size:28pt}'
            .'.body{padding:12mm;font-size:13pt}table{width:100%;border-collapse:collapse}td{padding:2mm 0;border-bottom:1px solid #ddd}.logo{width:24mm;height:24mm;float:right}</style></head><body>'
            .'<div class="top">'.($logoSvg ?? '').'<h1>'.$e($fields['headline'] ?? '').'</h1><div>'.$e($businessName).'</div></div><div class="band"></div>'
            .'<div class="body"><p>'.nl2br($e($fields['text'] ?? '')).'</p>'.($items !== '' ? '<table>'.$items.'</table>' : '').'<p>'.$e($fields['detail'] ?? '').'</p></div></body></html>');
    }
}
