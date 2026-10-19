<?php

declare(strict_types=1);

use App\Support\Portals\Portals;
use Modules\Core\Identity\Models\User;
use Modules\HubOps\Tests\Staff;

/* S23: portal subdomains - which host owns which page, redirects that keep old links working, single sign-on. */
beforeEach(function (): void {
    config(['kasi.subdomains.enabled' => true, 'kasi.subdomains.domain' => 'kasi.test', 'app.url' => 'http://kasi.test', 'session.domain' => '.kasi.test']);
});

it('knows which portal owns each path, longest prefix first, with shared paths left alone', function (): void {
    expect(Portals::owner('/work/applications'))->toBe('jobs')
        ->and(Portals::owner('/jobs/01abc'))->toBe('jobs')
        ->and(Portals::owner('/commercial/hub'))->toBe('hub')
        ->and(Portals::owner('/commercial'))->toBe('admin')
        ->and(Portals::owner('/commercial/buy/work_extra_advert'))->toBeNull()
        ->and(Portals::owner('/help/requests/1'))->toBe('my')
        ->and(Portals::owner('/help/forgot-pin'))->toBe('')
        ->and(Portals::owner('/learnings'))->toBeNull(); // a prefix only matches whole path segments
});

it('sends old main-domain links to the portal permanently, keeping the query', function (): void {
    $this->get('http://kasi.test/learn/courses?q=cv')->assertStatus(301)->assertRedirect('http://learn.kasi.test/learn/courses?q=cv');
    $this->get('http://jobs.kasi.test/help')->assertStatus(301)->assertRedirect('http://kasi.test/help');
});

it('switches in-app navigation to the other portal with a full page load', function (): void {
    $this->get('http://jobs.kasi.test/learn/courses', ['X-Inertia' => 'true'])->assertStatus(409)->assertHeader('X-Inertia-Location', 'http://learn.kasi.test/learn/courses');
});

it('opens a portal from its own address, and sends unknown subdomains to the main site', function (): void {
    $this->get('http://jobs.kasi.test/')->assertRedirect('http://jobs.kasi.test/jobs');
    $this->get('http://learn.kasi.test/')->assertRedirect('http://learn.kasi.test/learn/courses');
    $this->get('http://nonsense.kasi.test/anything')->assertRedirect('http://kasi.test/');
});

it('serves any other host (the server IP, load balancer health checks) without redirecting', function (): void {
    $this->get('http://127.0.0.1/up')->assertOk();
    $this->get('http://10.0.0.5/learn/courses')->assertOk();
});

it('turns redirects to another portal into one full-page switch for in-app requests', function (): void {
    Staff::signIn($this, User::factory()->create());
    // Signing out on my. finishes on the main site: one full-page switch straight there.
    $response = $this->post('http://my.kasi.test/logout', [], ['X-Inertia' => 'true']);
    $response->assertStatus(409);
    expect((string) $response->headers->get('X-Inertia-Location'))->toStartWith('http://kasi.test/');
});

it('keeps the response cookies when switching portals (e.g. the session cookie after sign-in)', function (): void {
    Staff::signIn($this, User::factory()->create());
    $response = $this->post('http://my.kasi.test/logout', [], ['X-Inertia' => 'true']);
    $response->assertStatus(409);
    expect(collect($response->headers->getCookies())->map->getName()->all())->toContain(config('session.cookie'));
});

it('never redirects form posts or webhooks', function (): void {
    $this->post('http://kasi.test/commercial/webhooks/payments', [])->assertStatus(400); // reached the handler (bad signature), not redirected
});

it('serves pages on their own portal, and sends signed-out people to sign in on the main domain and back', function (): void {
    $this->get('http://jobs.kasi.test/jobs')->assertOk();
    $this->get('http://jobs.kasi.test/work/applications')->assertRedirect('http://kasi.test/login');
    expect(session('url.intended'))->toBe('http://jobs.kasi.test/work/applications');
});

it('keeps one session across portals (single sign-on) and ends it everywhere', function (): void {
    expect(config('session.domain'))->toBe('.kasi.test');
    Staff::signIn($this, User::factory()->create());
    $this->get('http://my.kasi.test/home')->assertOk();
    $this->get('http://jobs.kasi.test/work/applications')->assertOk();
    $this->post('http://kasi.test/logout');
    $this->get('http://my.kasi.test/home')->assertRedirect('http://kasi.test/login');
});

it('behaves exactly as before when subdomains are off', function (): void {
    config(['kasi.subdomains.enabled' => false]);
    $this->get('/learn/courses')->assertOk();
    $this->get('/work/applications')->assertRedirect('/login');
});
