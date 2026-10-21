<?php

declare(strict_types=1);

namespace Modules\Brand\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Brand\Events\KitSaved;
use Modules\Core\Ai\AiService;
use Modules\Core\Enterprise\BusinessFacts;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Media\WebpImage;

/**
 * Brand kits (ADR-038): logo (upload, or a generated text logo drawn as SVG), two colours from tested palettes or custom
 * with a WCAG AA contrast check, lettering, tagline (AI suggestions within the budget), and which contact details show.
 */
final readonly class Kits
{
    public const SHAPES = ['circle', 'square', 'rounded', 'badge', 'banner', 'plain'];

    public const LETTERING = ['modern', 'friendly', 'classic'];

    /** Tested palettes: [main, second]; main carries white text, second sits on white or main. */
    public const PALETTES = [
        'deep_sea' => ['#1F4E79', '#F5B700'], 'berry' => ['#7A1F5C', '#F2C14E'], 'forest' => ['#1E5631', '#E8A33D'], 'earth' => ['#6B3E26', '#D9B26F'],
        'night' => ['#22223B', '#C9ADA7'], 'coral' => ['#B23A48', '#FCB9B2'], 'ocean' => ['#0B6E4F', '#08A045'], 'plum' => ['#4B2142', '#E3A587'],
    ];

    public function __construct(private AuditLogger $audit, private BusinessFacts $businesses) {}

    /** @return array{logo_kind: string, logo_url: string|null, logo_shape: string, logo_text: string, primary: string, secondary: string, lettering: string, tagline: string|null, contact: array<string, bool>, saved: bool} */
    public function kit(string $businessId): array
    {
        $k = DB::table('brand_kits')->where('business_id', $businessId)->first();
        $name = (string) ($this->businesses->facts($businessId)['name'] ?? '');

        return [
            'logo_kind' => (string) ($k->logo_kind ?? 'text'), 'logo_url' => isset($k->logo_path) ? asset('storage/'.$k->logo_path) : null,
            'logo_shape' => (string) ($k->logo_shape ?? 'circle'), 'logo_text' => (string) ($k->logo_text ?? self::initials($name)),
            'primary' => (string) ($k->primary ?? self::PALETTES['deep_sea'][0]), 'secondary' => (string) ($k->secondary ?? self::PALETTES['deep_sea'][1]),
            'lettering' => (string) ($k->lettering ?? 'modern'), 'tagline' => isset($k->tagline) ? (string) $k->tagline : null,
            'contact' => (array) json_decode((string) ($k->contact ?? '{"whatsapp":true,"area":true,"hours":true}'), true), 'saved' => $k !== null,
        ];
    }

    /** @param array{logo_shape?: string, logo_text?: string|null, primary: string, secondary: string, lettering?: string, tagline?: string|null, contact?: array<string, bool>} $data */
    public function save(string $businessId, User $by, array $data, ?User $subject = null): void
    {
        $existed = DB::table('brand_kits')->where('business_id', $businessId)->exists();
        $row = [
            'logo_shape' => in_array($data['logo_shape'] ?? 'circle', self::SHAPES, true) ? ($data['logo_shape'] ?? 'circle') : 'circle',
            'logo_text' => isset($data['logo_text']) ? mb_substr(trim((string) $data['logo_text']), 0, 24) : null,
            'primary' => self::hex($data['primary']), 'secondary' => self::hex($data['secondary']),
            'lettering' => in_array($data['lettering'] ?? 'modern', self::LETTERING, true) ? ($data['lettering'] ?? 'modern') : 'modern',
            'tagline' => isset($data['tagline']) && $data['tagline'] !== '' ? mb_substr(trim((string) $data['tagline']), 0, 60) : null,
            'contact' => json_encode(array_map('boolval', array_intersect_key($data['contact'] ?? [], array_flip(['whatsapp', 'area', 'hours'])))),
            'updated_at' => now(), 'created_at' => now(),
        ];
        // Atomic insert-or-update (S31): two saves at once for the same business can't both insert.
        DB::table('brand_kits')->upsert([['business_id' => $businessId, ...$row]], ['business_id'], array_values(array_diff(array_keys($row), ['created_at'])));
        $this->audit->record('brand.kit_saved', meta: ['business' => $businessId], actor: $by);
        if (! $existed) {
            event(new KitSaved($subject ?? $by, $subject !== null ? $by->id : null, ['business' => $businessId]));
        }
    }

    public function uploadLogo(string $businessId, User $by, UploadedFile $file): void
    {
        $webp = WebpImage::make((string) file_get_contents((string) $file->getRealPath()), 600, 80); // metadata (e.g. GPS) stripped
        $path = "brand/{$businessId}/logo-".Str::random(8).'.webp';
        Storage::disk('public')->put($path, $webp);
        $old = DB::table('brand_kits')->where('business_id', $businessId)->value('logo_path');
        if (is_string($old)) {
            Storage::disk('public')->delete($old);
        }
        $row = ['logo_kind' => 'upload', 'logo_path' => $path, 'updated_at' => now(), 'created_at' => now()];
        // Atomic insert-or-update (S31): two saves at once for the same business can't both insert.
        DB::table('brand_kits')->upsert([['business_id' => $businessId, ...$row]], ['business_id'], array_values(array_diff(array_keys($row), ['created_at'])));
        $this->audit->record('brand.logo_uploaded', meta: ['business' => $businessId], actor: $by);
    }

    public function useTextLogo(string $businessId): void
    {
        DB::table('brand_kits')->where('business_id', $businessId)->update(['logo_kind' => 'text', 'updated_at' => now()]);
    }

    /**
     * Tagline ideas from the AI writing help (budgets and safety apply).
     *
     * @return list<string>
     */
    public function taglineIdeas(string $businessId, User $by, ?User $subject = null): array
    {
        $facts = $this->businesses->facts($businessId);
        $result = app(AiService::class)->run('brand.tagline', ['name' => (string) ($facts['name'] ?? ''), 'sells' => (string) ($facts['sector'] ?? ''), 'area' => ''], $subject ?? $by, $by,
            $facts['hub_id'] ?? null);
        if (! $result->ok) {
            return [];
        }

        return array_values(array_map(static fn ($t): string => mb_substr(trim((string) $t), 0, 60), array_slice((array) ($result->data['taglines'] ?? []), 0, 3)));
    }

    /** The generated text logo as SVG (text escaped; colours validated). */
    public static function textLogoSvg(string $text, string $shape, string $primary, string $secondary): string
    {
        $text = htmlspecialchars(mb_substr(trim($text), 0, 24), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        [$primary, $secondary] = [self::hex($primary), self::hex($secondary)];
        $fg = self::readableOn($primary);
        $size = mb_strlen($text) <= 3 ? 72 : (mb_strlen($text) <= 6 ? 44 : 26);
        $bg = match ($shape) {
            'square' => "<rect width=\"200\" height=\"200\" fill=\"{$primary}\"/>",
            'rounded' => "<rect width=\"200\" height=\"200\" rx=\"36\" fill=\"{$primary}\"/>",
            'badge' => "<circle cx=\"100\" cy=\"100\" r=\"96\" fill=\"{$primary}\" stroke=\"{$secondary}\" stroke-width=\"8\"/>",
            'banner' => "<rect y=\"55\" width=\"200\" height=\"90\" rx=\"12\" fill=\"{$primary}\"/><rect y=\"140\" width=\"200\" height=\"8\" fill=\"{$secondary}\"/>",
            'plain' => '',
            default => "<circle cx=\"100\" cy=\"100\" r=\"100\" fill=\"{$primary}\"/>",
        };
        $color = $shape === 'plain' ? $primary : $fg;

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" role="img"><title>'.$text.'</title>'.$bg
            ."<text x=\"100\" y=\"100\" dominant-baseline=\"central\" text-anchor=\"middle\" font-family=\"system-ui, sans-serif\" font-weight=\"700\" font-size=\"{$size}\" fill=\"{$color}\">{$text}</text></svg>";
    }

    /** WCAG contrast ratio between two colours. */
    public static function contrast(string $a, string $b): float
    {
        [$l1, $l2] = [self::luminance($a), self::luminance($b)];

        return round((max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05), 2);
    }

    /** Black or white, whichever reads better on this background. */
    public static function readableOn(string $background): string
    {
        return self::contrast($background, '#FFFFFF') >= self::contrast($background, '#000000') ? '#FFFFFF' : '#000000';
    }

    /** The closest darker shade of $color that gives white text WCAG AA (4.5:1), or the colour itself if it already does. */
    public static function readableShade(string $color): string
    {
        $c = self::hex($color);
        for ($i = 0; $i < 40 && self::contrast($c, '#FFFFFF') < 4.5; $i++) {
            $c = self::darken($c, 0.05);
        }

        return $c;
    }

    public static function initials(string $name): string
    {
        $words = preg_split('/\s+/', trim($name)) ?: [];

        return mb_strtoupper(implode('', array_map(static fn (string $w): string => mb_substr($w, 0, 1), array_slice(array_filter($words), 0, 3)))) ?: 'K';
    }

    private static function hex(string $color): string
    {
        return preg_match('/^#[0-9A-Fa-f]{6}$/', $color) === 1 ? strtoupper($color) : '#1F4E79';
    }

    private static function luminance(string $color): float
    {
        $rgb = sscanf(self::hex($color), '#%02x%02x%02x') ?? [0, 0, 0];
        $channel = static function (int $v): float {
            $s = $v / 255;

            return $s <= 0.03928 ? $s / 12.92 : (($s + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $channel((int) $rgb[0]) + 0.7152 * $channel((int) $rgb[1]) + 0.0722 * $channel((int) $rgb[2]);
    }

    private static function darken(string $color, float $by): string
    {
        $rgb = sscanf($color, '#%02x%02x%02x') ?? [0, 0, 0];

        return sprintf('#%02X%02X%02X', ...array_map(static fn ($v): int => (int) max(0, round((int) $v * (1 - $by))), $rgb));
    }
}
