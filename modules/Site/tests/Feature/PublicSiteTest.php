<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\ConsentService;
use Modules\Core\Structure\Models\Hub;
use Modules\Core\Tests\Helpers;
use Modules\Core\Tests\Structure;
use Modules\Site\Http\Controllers\SeoController;
use Modules\Site\Models\Enquiry;

beforeEach(fn () => Structure::seed($this));

it('renders every public page with search and link-preview tags', function (string $path, string $component, string $title): void {
    $response = $this->get($path)->assertOk()->assertInertia(fn (Assert $page) => $page->component($component)->has('seo'));

    $html = $response->getContent();
    expect($html)
        ->toContain('<meta name="description"')
        ->toContain('<meta property="og:title" content="'.e($title).'"')
        ->toContain('<link rel="canonical" href="')
        ->not->toContain('name="robots" content="noindex"');
})->with([
    ['/', 'Site/Home', 'Kasi Digital Hub'],
    ['/hubs', 'Site/Hubs/Index', 'Find a hub'],
    ['/hubs/tsutsumani', 'Site/Hubs/Show', 'Tsutsumani Digital Hub'],
    ['/employers', 'Site/Employers', 'For employers'],
    ['/funders', 'Site/Funders', 'For funders and partners'],
    ['/about', 'Site/About', 'About Kasi Digital Hub'],
    ['/help', 'Site/Help', 'Help and questions'],
    ['/contact', 'Site/Contact', 'Contact us'],
]);

it('keeps private pages out of search engines', function (): void {
    expect($this->get('/login')->getContent())->toContain('<meta name="robots" content="noindex">');
});

it('includes valid structured data for the organisation, hubs and FAQ', function (string $path, string $type): void {
    preg_match_all('#<script type="application/ld\+json">(.+?)</script>#s', (string) $this->get($path)->getContent(), $matches);
    $types = array_map(static fn (string $json): string => json_decode($json, true, flags: JSON_THROW_ON_ERROR)['@type'], $matches[1]);

    expect($types)->toContain($type);
})->with([['/', 'Organization'], ['/hubs/tsutsumani', 'LocalBusiness'], ['/help', 'FAQPage']]);

it('shows live hubs and hubs opening soon, but hides paused hubs', function (): void {
    Structure::hub('LP-TZA-NKO')->update(['status' => 'paused']);

    $this->get('/hubs')->assertInertia(fn (Assert $page) => $page
        ->has('hubs', 11)
        ->where('hubs', fn ($hubs) => collect($hubs)->firstWhere('slug', 'inanda')['status'] === 'planned'
            && collect($hubs)->firstWhere('slug', 'nkowankowa') === null));

    $this->get('/hubs/nkowankowa')->assertNotFound();
});

it('lists the services each hub offers from its package', function (): void {
    $this->get('/hubs/tsutsumani')->assertInertia(fn (Assert $page) => $page->where('hub.services', ['Work', 'Learn', 'Start', 'Connect']));
    $this->get('/hubs/nkowankowa')->assertInertia(fn (Assert $page) => $page->where('hub.services', ['Work']));
});

it('shows live impact numbers on the home page', function (): void {
    User::factory()->count(3)->create();

    $this->get('/')->assertInertia(fn (Assert $page) => $page->where('impact.people', 3)->where('impact.hubs', 11)->has('hubs', 6));
});

it('sends signed-in people from the public home to their hub home', function (): void {
    $user = User::factory()->create();
    app(ConsentService::class)->record($user, ['platform' => true]);
    Helpers::signedIn($this, $user);

    $this->get('/')->assertRedirect('/home');
});

it('publishes a sitemap with every public page and hub', function (): void {
    $xml = (string) $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();

    expect(simplexml_load_string($xml))->not->toBeFalse()
        ->and(substr_count($xml, '<url>'))->toBe(count(SeoController::PUBLIC_PATHS) + Hub::query()->whereIn('status', ['live', 'planned'])->count())
        ->and($xml)->toContain('/hubs/tsutsumani')->not->toContain('/account');
});

it('blocks private areas in robots.txt in production, and everything elsewhere', function (): void {
    expect($this->get('/robots.txt')->getContent())->toContain("Disallow: /\n");

    app()->detectEnvironment(fn () => 'production');
    $robots = (string) $this->get('/robots.txt')->getContent();
    expect($robots)->toContain('Disallow: /account')->toContain('Disallow: /home')->toContain('Sitemap: ')->not->toContain("Disallow: /\n");
});

it('stores enquiries and emails the platform team', function (): void {
    Mail::fake();

    $this->post('/enquiries/employer', [
        'name' => 'Sipho Nkuna', 'organisation' => 'Mopani Fresh Market', 'phone' => '072 418 3390',
        'message' => 'We want to hire three cashiers in Giyani.', 'consent' => true,
    ])->assertSessionHasNoErrors()->assertSessionHas('status');

    $enquiry = Enquiry::query()->firstOrFail();
    expect($enquiry->kind)->toBe('employer')->and($enquiry->phone)->toBe('+27724183390');
});

it('validates enquiries', function (): void {
    $this->post('/enquiries/contact', ['name' => 'A', 'message' => 'short'])->assertSessionHasErrors(['phone', 'email', 'message', 'consent']);
    $this->post('/enquiries/employer', ['name' => 'A', 'email' => 'a@b.co.za', 'message' => 'A proper message here', 'consent' => true])->assertSessionHasErrors('organisation');
    $this->post('/enquiries/nonsense', [])->assertNotFound();
});

it('quietly ignores spam bots that fill the hidden field', function (): void {
    $this->post('/enquiries/contact', ['name' => 'Bot', 'email' => 'bot@spam.test', 'message' => 'Buy cheap things now!!', 'consent' => true, 'website' => 'http://spam.test'])
        ->assertSessionHas('status');

    expect(Enquiry::query()->count())->toBe(0);
});

it('rate-limits enquiries', function (): void {
    foreach (range(1, 5) as $i) {
        $this->post('/enquiries/contact', ['name' => 'A', 'email' => 'a@b.co.za', 'message' => "Message number {$i} here", 'consent' => true]);
    }

    $this->post('/enquiries/contact', ['name' => 'A', 'email' => 'a@b.co.za', 'message' => 'One message too many', 'consent' => true])->assertStatus(429);
});

it('counts page views per page per day without storing anything personal', function (): void {
    $this->get('/');
    $this->get('/');
    $this->get('/hubs');
    $this->withHeader('User-Agent', 'Googlebot/2.1')->get('/');

    expect(DB::table('page_views')->where('page', 'site.home')->value('views'))->toBe(2)
        ->and(DB::table('page_views')->where('page', 'site.hubs')->value('views'))->toBe(1)
        ->and(array_keys((array) DB::table('page_views')->first()))->toBe(['id', 'day', 'page', 'views']);
});

it('keeps the enquiry even when email delivery fails', function (): void {
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);

    $this->post('/enquiries/contact', ['name' => 'Ayanda', 'email' => 'a@example.co.za', 'message' => 'Where is my nearest hub?', 'consent' => true])
        ->assertSessionHasNoErrors()->assertSessionHas('status');

    expect(Enquiry::query()->count())->toBe(1);
});
