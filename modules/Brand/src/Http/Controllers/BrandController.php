<?php

declare(strict_types=1);

namespace Modules\Brand\Http\Controllers;

use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Brand\Http\Concerns\ResolvesBusiness;
use Modules\Brand\Services\Catalogue;
use Modules\Brand\Services\Designs;
use Modules\Brand\Services\Kits;
use Modules\Core\Enterprise\BusinessProducts;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/** KasiBrand (S30): brand kit, designs from templates, and the WhatsApp catalogue. */
final class BrandController
{
    use ResolvesBusiness;

    public function __construct(private readonly Kits $kits, private readonly Designs $designs, private readonly Catalogue $catalogue) {}

    public function home(Request $request): Response
    {
        [$person] = $this->who($request);
        $b = $this->business($request, $person);

        return Inertia::render('Brand/Home', ['business' => $b, 'kit' => $this->kitProps($b), 'templates' => array_keys(Designs::TEMPLATES),
            'recent' => DB::table('brand_designs')->where('business_id', $b['id'])->latest('updated_at')->limit(4)->get(['id', 'template', 'title'])]);
    }

    public function kit(Request $request): Response
    {
        [$person] = $this->who($request);
        $b = $this->business($request, $person);

        return Inertia::render('Brand/Kit', ['business' => $b, 'kit' => $this->kitProps($b), 'palettes' => Kits::PALETTES, 'shapes' => Kits::SHAPES, 'lettering' => Kits::LETTERING,
            'ideas' => session('tagline_ideas', [])]);
    }

    public function saveKit(Request $request): RedirectResponse
    {
        [$person, $helper] = $this->who($request);
        $b = $this->business($request, $person);
        $data = $request->validate(['logo_shape' => ['required', Rule::in(Kits::SHAPES)], 'logo_text' => ['nullable', 'string', 'max:24'],
            'primary' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'], 'secondary' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'], 'lettering' => ['required', Rule::in(Kits::LETTERING)],
            'tagline' => ['nullable', 'string', 'max:60'], 'contact' => ['array'], 'contact.*' => ['boolean']]);
        $this->kits->save($b['id'], $helper ?? $person, $data, $helper !== null ? $person : null);

        return back()->with('status', __('brand.kit.saved'));
    }

    public function logo(Request $request): RedirectResponse
    {
        [$person, $helper] = $this->who($request);
        $b = $this->business($request, $person);
        if ($request->boolean('use_text')) {
            $this->kits->useTextLogo($b['id']);

            return back();
        }
        $request->validate(['logo' => ['required', File::image()->max(8 * 1024)], 'own' => ['accepted']]); // "this logo is mine to use"
        $this->kits->uploadLogo($b['id'], $helper ?? $person, $request->file('logo'));

        return back()->with('status', __('brand.kit.logo_saved'));
    }

    public function taglines(Request $request): RedirectResponse
    {
        [$person, $helper] = $this->who($request);
        $b = $this->business($request, $person);

        return back()->with('tagline_ideas', $this->kits->taglineIdeas($b['id'], $helper ?? $person, $helper !== null ? $person : null));
    }

    public function designs(Request $request): Response
    {
        [$person] = $this->who($request);
        $b = $this->business($request, $person);

        return Inertia::render('Brand/Designs', ['business' => $b, 'designs' => DB::table('brand_designs')->where('business_id', $b['id'])->latest('updated_at')->get(['id', 'template', 'title', 'updated_at'])]);
    }

    public function edit(Request $request, string $template, ?int $design = null): Response
    {
        [$person] = $this->who($request);
        $b = $this->business($request, $person);
        abort_unless(isset(Designs::TEMPLATES[$template]), 404);
        $row = $design !== null ? DB::table('brand_designs')->where('id', $design)->where('business_id', $b['id'])->first() : null;
        abort_if($design !== null && $row === null, 404);
        $prefill = [];
        if ($row === null && $template === 'price_list') {
            // Pre-filled from the KasiMarket shop (only listed products).
            $prefill['items'] = implode("\n", array_map(static fn (array $p): string => $p['title'].' | R'.number_format($p['price_cents'] / 100, 2, '.', ' '),
                array_slice(app(BusinessProducts::class)->products($b['id'], Designs::MAX_PRICE_LINES), 0, Designs::MAX_PRICE_LINES)));
        }

        return Inertia::render('Brand/Editor', ['business' => $b, 'kit' => $this->kitProps($b), 'template' => $template, 'limits' => Designs::TEMPLATES[$template]['fields'],
            'size' => Designs::TEMPLATES[$template]['size'], 'design' => $row !== null ? ['id' => (int) $row->id, 'title' => (string) $row->title, 'fields' => json_decode((string) $row->fields, true)] : null,
            'prefill' => $prefill]);
    }

    public function save(Request $request, string $template): RedirectResponse
    {
        [$person, $helper] = $this->who($request);
        $b = $this->business($request, $person);
        $data = $request->validate(['title' => ['nullable', 'string', 'max:80'], 'fields' => ['required', 'array'], 'design' => ['nullable', 'integer']]);
        try {
            $id = $this->designs->save($b['id'], $helper ?? $person, $template, (string) ($data['title'] ?? ''), $data['fields'], isset($data['design']) ? (int) $data['design'] : null);
        } catch (DomainException $e) {
            return back()->withErrors(['brand' => $e->getMessage()]);
        }

        return to_route('brand.design', [$template, $id])->with('status', __('brand.design.saved'));
    }

    public function duplicate(Request $request, int $design): RedirectResponse
    {
        [$person] = $this->who($request);
        $b = $this->business($request, $person);
        $id = $this->designs->duplicate($b['id'], $design);

        return to_route('brand.design', [(string) DB::table('brand_designs')->where('id', $id)->value('template'), $id])->with('status', __('brand.design.duplicated'));
    }

    /** The PNG is made on the phone; this only records it (for the funder indicator). */
    public function exported(Request $request, string $template): HttpResponse
    {
        [$person, $helper] = $this->who($request);
        $b = $this->business($request, $person);
        abort_unless(isset(Designs::TEMPLATES[$template]), 404);
        $this->designs->exported($b['id'], $helper ?? $person, $template, 'png', $helper !== null ? $person : null);

        return response()->noContent();
    }

    public function pdf(Request $request, string $template): HttpResponse
    {
        [$person, $helper] = $this->who($request);
        $b = $this->business($request, $person);
        abort_unless(isset(Designs::TEMPLATES[$template]), 404);
        $fields = Designs::clean($template, (array) $request->input('fields', []));
        $kit = $this->kitProps($b);
        $pdf = $this->designs->pdf($kit, $template, $fields, $kit['logo_kind'] === 'text' ? $kit['logo_svg'] : null, $b['name']);
        $this->designs->exported($b['id'], $helper ?? $person, $template, 'pdf', $helper !== null ? $person : null);

        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$template.'.pdf"']);
    }

    public function catalogue(Request $request): Response
    {
        [$person] = $this->who($request);
        $b = $this->business($request, $person);
        $chosen = array_values(array_filter((array) $request->query('p', []), 'is_string'));

        return Inertia::render('Brand/Catalogue', ['business' => $b, 'kit' => $this->kitProps($b), 'products' => $this->catalogue->available($b['id']),
            'chosen' => $chosen, 'built' => $chosen !== [] ? $this->catalogue->build($b['id'], $chosen, $b['name']) : null, 'max' => Catalogue::MAX]);
    }

    /**
     * @param  array{id: string, name: string}  $b
     * @return array<string, mixed>
     */
    private function kitProps(array $b): array
    {
        $kit = $this->kits->kit($b['id']);
        $shop = app(BusinessProducts::class)->shop($b['id']);

        return [...$kit, 'logo_svg' => Kits::textLogoSvg($kit['logo_text'], $kit['logo_shape'], $kit['primary'], $kit['secondary']),
            'on_primary' => Kits::readableOn($kit['primary']), 'contrast' => Kits::contrast($kit['primary'], Kits::readableOn($kit['primary'])),
            'suggested_primary' => Kits::readableShade($kit['primary']),
            // Contact details the owner chose to show, from the public KasiMarket shop.
            'contact_lines' => array_values(array_filter([
                ($kit['contact']['whatsapp'] ?? false) && ($shop['whatsapp'] ?? null) !== null ? 'WhatsApp '.$shop['whatsapp'] : null,
                ($kit['contact']['area'] ?? false) ? ($shop['area'] ?? null) : null,
            ])),
            'shop_url' => $shop['url'] ?? null];
    }
}
