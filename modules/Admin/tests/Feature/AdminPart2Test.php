<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Admin\Http\Controllers\DemoController;
use Modules\Admin\Support\AiAlerts;
use Modules\Core\Access\Scope;
use Modules\Core\Ai\Moderation\ModerationService;
use Modules\Core\Identity\Drivers\LogSmsSender;
use Modules\Core\Identity\Models\AuditLog;
use Modules\Core\Identity\Models\User;
use Modules\Core\Platform\Models\Update;
use Modules\Core\Tests\Structure;
use Modules\HubOps\Tests\Staff;
use Modules\Site\Database\Seeders\HelpSeeder;

beforeEach(function (): void {
    Structure::seed($this);
    $this->agent = Structure::personWith('support_agent', Scope::national());
    $this->person = User::factory()->create(['phone' => '+27725550101', 'first_name' => 'Zodwa']);
});

it('opens a help request, routes safety worries to safeguarding, and replies reach the person', function (): void {
    $officer = Structure::personWith('safeguarding_officer', Scope::national());
    Staff::signIn($this, $this->person);
    $this->post('/help/requests', ['category' => 'safety', 'subject' => 'Worried', 'body' => 'Someone keeps asking to meet me alone.'])->assertRedirect();
    expect(DB::table('support_tickets')->value('status'))->toBe('escalated')
        ->and(Update::query()->where('user_id', $officer->id)->exists())->toBeTrue();

    Staff::signIn($this, $this->agent);
    $id = (string) DB::table('support_tickets')->value('id');
    $this->post("/admin/support/{$id}/reply", ['body' => 'Thank you - our safeguarding team is with you.'])->assertSessionHasNoErrors();
    $this->post("/admin/support/{$id}/reply", ['body' => 'Internal: called the hub.', 'internal' => true]);
    expect(Update::query()->where('user_id', $this->person->id)->exists())->toBeTrue();

    Staff::signIn($this, $this->person);
    $this->get("/help/requests/{$id}")->assertInertia(fn ($page) => $page->has('messages', 2)); // the internal note is hidden
});

it('shows support a safe summary - counts and statuses, never content - and audits the reason', function (): void {
    Staff::signIn($this, $this->agent);
    $this->get("/admin/support/people/{$this->person->id}")->assertSessionHasErrors('reason');
    $response = $this->get("/admin/support/people/{$this->person->id}?reason=".urlencode('Ticket HELP-000001'));
    $response->assertInertia(fn ($page) => $page->where('summary.phone', '072 *** 0101')->has('summary.counts'));
    expect(json_encode($response->viewData('page')))->not->toContain('+27725550101')
        ->and(AuditLog::query()->where('event', 'support.summary_viewed')->exists())->toBeTrue();
});

it('unlocks an account only with the code the person reads back', function (): void {
    $this->person->forceFill(['pin_failed_attempts' => 5, 'pin_locked_until' => now()->addHour()])->save();
    Staff::signIn($this, $this->agent);
    $this->post("/admin/support/people/{$this->person->id}/code", ['action' => 'unlock'])->assertSessionHasNoErrors();
    preg_match('/\d{6}/', (string) LogSmsSender::lastMessage('+27725550101'), $m);

    $this->post("/admin/support/people/{$this->person->id}/act", ['action' => 'unlock', 'code' => '000000'])->assertSessionHasErrors('code');
    $this->post("/admin/support/people/{$this->person->id}/act", ['action' => 'unlock', 'code' => $m[0]])->assertSessionHasNoErrors();
    expect($this->person->refresh()->isPinLocked())->toBeFalse();
});

it('changes a phone number only after a hub ID check and the code sent to the new number', function (): void {
    Staff::signIn($this, $this->agent);
    $this->post("/admin/support/people/{$this->person->id}/code", ['action' => 'phone_change', 'new_phone' => '072 555 0202']);
    preg_match('/\d{6}/', (string) LogSmsSender::lastMessage('+27725550202'), $m);
    $this->post("/admin/support/people/{$this->person->id}/act", ['action' => 'phone_change', 'code' => $m[0], 'new_phone' => '0725550202'])->assertSessionHasErrors('hub_check');
    $this->post("/admin/support/people/{$this->person->id}/act", ['action' => 'phone_change', 'code' => $m[0], 'new_phone' => '0725550202',
        'hub_check' => 'Green ID book seen at Tsutsumani hub by Rhulani'])->assertSessionHasNoErrors();
    expect($this->person->refresh()->phone)->toBe('+27725550202')->and(LogSmsSender::lastMessage('+27725550101'))->not->toBeNull();
});

it('publishes help articles only after a second person reviews them, and renders them safely', function (): void {
    $this->seed(HelpSeeder::class);
    $author = Structure::personWith('content_reviewer', Scope::national());
    $reviewer = Structure::personWith('content_reviewer', Scope::national());
    Staff::signIn($this, $author);
    $this->post('/admin/help', ['slug' => 'test-article', 'category' => 'start', 'title' => 'Test', 'summary' => 'Short',
        'body' => "## Heading\n<script>alert(1)</script>\n\n- one\n- two"])->assertSessionHasNoErrors();
    $id = (int) DB::table('help_articles')->where('slug', 'test-article')->value('id');
    $this->get('/help/test-article')->assertNotFound(); // in review
    $this->post("/admin/help/{$id}/review", ['publish' => true])->assertSessionHasErrors('help'); // not your own

    Staff::signIn($this, $reviewer);
    $this->post("/admin/help/{$id}/review", ['publish' => true]);
    $this->get('/help/test-article')->assertInertia(fn ($page) => $page->where('article.html', fn ($h) => str_contains($h, '<h2>Heading</h2>') && str_contains($h, '&lt;script&gt;') && str_contains($h, '<li>one</li>')));
    $this->postJson('/help/test-article/feedback', ['helpful' => true])->assertOk();
    expect((int) DB::table('help_articles')->where('id', $id)->value('helpful_yes'))->toBe(1);
});

it('alerts super admins once at 80% and 100% of the AI budget', function (): void {
    $super = Structure::personWith('super_admin', Scope::national());
    config(['kasi.ai.budgets.platform_month_cents' => 1000]);
    DB::table('ai_requests')->insert(['id' => (string) Str::ulid(), 'feature' => 'work.cv', 'prompt_key' => 'x', 'prompt_version' => 1, 'model' => 'm', 'tier' => 'fast',
        'cost_cents' => 850, 'outcome' => 'ok', 'created_at' => now()]);

    expect(app(AiAlerts::class)->check())->toBe(1)->and(app(AiAlerts::class)->check())->toBe(0);
    DB::table('ai_requests')->insert(['id' => (string) Str::ulid(), 'feature' => 'work.cv', 'prompt_key' => 'x', 'prompt_version' => 1, 'model' => 'm', 'tier' => 'fast',
        'cost_cents' => 200, 'outcome' => 'ok', 'created_at' => now()]);
    expect(app(AiAlerts::class)->check())->toBe(1)
        ->and(Update::query()->where('user_id', $super->id)->count())->toBeGreaterThanOrEqual(2)
        ->and(AiAlerts::projection(1000))->toBeGreaterThanOrEqual(1000);
});

it('gathers held content into one inbox and approves harmless items in bulk', function (): void {
    $reviewer = Structure::personWith('content_reviewer', Scope::national());
    $flag = app(ModerationService::class)->check('Pay R200 to apply for this job, WhatsApp me', 'job_listing', (string) Str::ulid(), $this->person, useAi: false);
    expect($flag['verdict'])->toBe('flag');

    Staff::signIn($this, $reviewer);
    $this->get('/admin/inbox')->assertInertia(fn ($page) => $page->where('counts.job_listing', 1));
    $id = (string) DB::table('moderation_flags')->value('id');
    $this->post('/admin/inbox/approve', ['ids' => [$id], 'reason' => 'Checked with the employer'])->assertSessionHasNoErrors();
    expect(DB::table('moderation_flags')->value('status'))->toBe('approved');
});

it('shows system status with overdue scheduled jobs', function (): void {
    Cache::forever('kasi:heartbeat:work-hiring', now()->subDays(3)->toIso8601String());
    Cache::forever('kasi:heartbeat:admin-hourly', now()->toIso8601String());
    Staff::signIn($this, Structure::personWith('operations_admin', Scope::national()));

    $this->get('/admin/system')->assertInertia(fn ($page) => $page->where('jobs', fn ($jobs) => collect($jobs)->firstWhere('name', 'work-hiring')['overdue'] === true
        && collect($jobs)->firstWhere('name', 'admin-hourly')['overdue'] === false));
});

it('refuses the role switcher and demo reset outside demo mode, in production, and for real accounts', function (): void {
    $demoPerson = User::factory()->create(['phone' => '+27720000099']);
    Staff::signIn($this, $this->person);

    config(['kasi.demo.enabled' => false]);
    $this->post("/demo/switch/{$demoPerson->id}")->assertNotFound();
    $this->get('/demo')->assertNotFound();

    config(['kasi.demo.enabled' => true]);
    // The production guard is checked directly: switching a test request to "production" also turns CSRF checks on.
    $this->app['env'] = 'production';
    expect(DemoController::allowed())->toBeFalse();
    $this->app['env'] = 'testing';
    expect(DemoController::allowed())->toBeTrue();

    $this->post("/demo/switch/{$this->person->id}")->assertNotFound(); // not a demo account
    $this->post("/demo/switch/{$demoPerson->id}")->assertRedirect('/home');
    expect(auth()->id())->toBe($demoPerson->id)->and(AuditLog::query()->where('event', 'demo.switched')->exists())->toBeTrue();
});
