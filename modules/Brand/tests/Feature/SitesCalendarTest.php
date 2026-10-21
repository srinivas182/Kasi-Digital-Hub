<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Modules\Brand\Services\Calendar;
use Modules\Brand\Services\Sites;
use Modules\Core\Access\Scope;
use Modules\Core\Documents\Models\Document;
use Modules\Core\Enterprise\Signals;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\HubModule;
use Modules\Core\Tests\Structure;
use Modules\HubOps\Tests\Staff;
use Modules\Market\Models\Shop;
use Modules\Start\Database\Seeders\StepSeeder;
use Modules\Start\Services\Businesses;

beforeEach(function (): void {
    Structure::seed($this);
    $this->seed(StepSeeder::class);
    $this->hub = Structure::hub('LP-GIY-TSU');
    HubModule::query()->updateOrCreate(['hub_id' => $this->hub->id, 'module' => 'Brand'], ['enabled' => true]);
    $this->owner = User::factory()->create(['home_hub_id' => $this->hub->id, 'date_of_birth' => now()->subYears(30)]);
    $this->business = app(Businesses::class)->create($this->owner, ['name' => 'Nomsa Hair', 'sells' => 'Braids', 'sector' => 'beauty', 'stage' => 'informal', 'people' => 1]);
    $this->sites = app(Sites::class);
});

function verifyId(User $user): void
{
    Document::query()->create(['user_id' => $user->id, 'type' => 'id_document', 'disk' => 'documents', 'path' => 'id.pdf',
        'original_name' => 'id.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 1, 'sha256' => str_repeat('d', 64), 'status' => 'verified']);
}

it('checks website addresses: format, reserved, offensive, taken, once a month', function (): void {
    $id = $this->business->id;
    expect($this->sites->nameProblem('ab', $id))->toBe('format')->and($this->sites->nameProblem('Nomsa_Hair', $id))->toBe('format')
        ->and($this->sites->nameProblem('market', $id))->toBe('reserved')->and($this->sites->nameProblem('sars', $id))->toBe('reserved')
        ->and($this->sites->nameProblem('poesbraids', $id))->toBe('offensive')->and($this->sites->nameProblem('nomsahair', $id))->toBeNull();
    $this->sites->save($id, $this->owner, ['name' => 'nomsahair']);
    $other = app(Businesses::class)->create($this->owner, ['name' => 'Other', 'sells' => 'x', 'sector' => 'beauty', 'stage' => 'informal', 'people' => 1]);
    expect($this->sites->nameProblem('nomsahair', $other->id))->toBe('taken');
    $this->sites->save($id, $this->owner, ['name' => 'nomsabraids']); // first change allowed
    expect(fn () => $this->sites->save($id, $this->owner, ['name' => 'nomsa-hair']))->toThrow(DomainException::class);
});

it('publishes only with a verified ID and nothing prohibited, and holds text that moderation flags', function (): void {
    $id = $this->business->id;
    $this->sites->save($id, $this->owner, ['name' => 'nomsahair', 'about' => 'Braids and treatments in Tsutsumani.']);
    expect(fn () => $this->sites->publish($id, $this->owner, $this->owner))->toThrow(DomainException::class); // no verified ID
    verifyId($this->owner);
    $this->sites->save($id, $this->owner, ['about' => 'We also sell cheap cigarette packs.']);
    expect(fn () => $this->sites->publish($id, $this->owner, $this->owner))->toThrow(DomainException::class); // prohibited
    $this->sites->save($id, $this->owner, ['about' => 'Braids and treatments in Tsutsumani.']);
    expect($this->sites->publish($id, $this->owner, $this->owner))->toBe('published')
        ->and(Signals::for($id)['brand.has_website'])->toBeTrue()
        ->and(DB::table('platform_events')->where('name', 'brand.site.published')->count())->toBe(1);
});

it('serves the same small, findable page at the subdomain and /b/name, counting visits without cookies', function (): void {
    $id = $this->business->id;
    Shop::query()->create(['business_id' => $id, 'slug' => 'nomsa', 'name' => 'Nomsa Hair Studio', 'status' => 'open', 'away' => false, 'approved_products' => 3,
        'opened_by' => $this->owner->id, 'whatsapp' => '+27725550303', 'whatsapp_public' => false, 'area' => 'Tsutsumani']);
    verifyId($this->owner);
    $this->sites->save($id, $this->owner, ['name' => 'nomsahair', 'about' => 'Braids & treatments <b>in</b> Tsutsumani.']);
    $this->sites->publish($id, $this->owner, $this->owner);

    $page = $this->get('/b/nomsahair')->assertOk()->assertHeader('Cache-Control', 'max-age=300, public');
    $html = (string) $page->getContent();
    expect(strlen($html))->toBeLessThan(50 * 1024) // the page budget (images excluded)
        ->and($html)->toContain('"@type":"LocalBusiness"')->toContain('Braids &amp; treatments &lt;b&gt;in&lt;/b&gt;')
        ->not->toContain('<script src')->not->toContain('+27725550303') // no app code; the private number isn't shown
        ->and($page->headers->getCookies())->toBeEmpty();

    config(['kasi.subdomains.enabled' => true, 'kasi.subdomains.domain' => 'kasi.test']);
    $sub = $this->get('http://nomsahair.kasi.test/')->assertOk();
    expect((string) $sub->getContent())->toContain('Nomsa Hair');
    $this->get('http://nobody-here.kasi.test/')->assertRedirect(); // unknown subdomains still go to the main site
    expect((int) DB::table('brand_site_stats')->sum('views'))->toBe(2);
});

it('lets moderators take a website down and restore it, telling the owner', function (): void {
    verifyId($this->owner);
    $this->sites->save($this->business->id, $this->owner, ['name' => 'nomsahair', 'about' => 'Braids']);
    $this->sites->publish($this->business->id, $this->owner, $this->owner);
    $moderator = Structure::personWith('content_reviewer', Scope::national());
    $this->sites->takeDown($this->business->id, $moderator, 'Misleading claims');
    $this->get('/b/nomsahair')->assertNotFound();
    expect(fn () => $this->sites->publish($this->business->id, $this->owner, $this->owner))->toThrow(DomainException::class)
        ->and(DB::table('updates')->where('user_id', $this->owner->id)->count())->toBe(1);
    $this->sites->takeDown($this->business->id, $moderator, 'Appeal accepted', restore: true);
    expect($this->sites->publish($this->business->id, $this->owner, $this->owner))->toBe('published');
});

it('plans a month from the South African calendar with AI wording, and reminds owners once when a post is due', function (): void {
    $this->travelTo(now()->setDate(2026, 12, 1)->setTime(7, 0));
    $n = app(Calendar::class)->planMonth($this->business->id, $this->owner, 2026, 12);
    $posts = DB::table('brand_posts')->orderBy('planned_at')->get();
    expect($n)->toBeGreaterThanOrEqual(8)->toBeLessThanOrEqual(12)->and($posts->pluck('planned_at')->map(fn ($d) => substr((string) $d, 0, 10)))->toContain('2026-12-16', '2026-12-25')
        ->and($posts->every(fn ($p) => (bool) $p->suggested && trim((string) $p->text) !== ''))->toBeTrue()
        ->and(DB::table('ai_requests')->where('feature', 'brand.month_plan')->exists())->toBeTrue();

    $this->travelTo(now()->setDate(2026, 12, 1)->setTime(8, 5));
    $this->artisan('kasi:brand:reminders')->assertSuccessful();
    $this->artisan('kasi:brand:reminders')->assertSuccessful();
    expect(DB::table('updates')->where('user_id', $this->owner->id)->count())->toBe(1); // once

    Staff::signIn($this, $this->owner);
    $post = (int) DB::table('brand_posts')->orderBy('planned_at')->value('id');
    $this->post("/brand/calendar/posts/{$post}/mark", ['status' => 'posted'])->assertRedirect();
    expect(DB::table('brand_posts')->where('id', $post)->value('status'))->toBe('posted')
        ->and(DB::table('platform_events')->where('name', 'brand.post.posted')->count())->toBe(1);
});
