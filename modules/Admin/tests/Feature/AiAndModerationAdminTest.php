<?php

declare(strict_types=1);

use Modules\Admin\Tests\Console;
use Modules\Core\Ai\Models\AiFeatureSetting;
use Modules\Core\Ai\Models\AiRequestLog;
use Modules\Core\Ai\Models\ModerationFlag;
use Modules\Core\Identity\Models\AuditLog;
use Modules\Core\Tests\Structure;

beforeEach(fn () => Structure::seed($this));

it('shows AI usage and cost per feature', function (): void {
    Console::as($this, 'operations_admin');
    AiRequestLog::query()->create(['feature' => 'core.translate', 'prompt_key' => 'core.translate', 'prompt_version' => 1, 'model' => 'm', 'tier' => 'fast', 'cost_cents' => 120, 'outcome' => 'ok', 'input' => 'secret text']);

    $this->get('/admin/ai')->assertInertia(fn ($page) => $page->component('Admin/Ai')
        ->where('monthCost', 120)
        ->where('features', fn ($features) => collect($features)->firstWhere('feature', 'core.translate')['costCents'] === 120)
        ->where('recent.0.input', null) // texts only for people who manage AI
        ->where('canManage', false));
});

it('lets only super admins switch AI features and set budgets, with a reason', function (): void {
    Console::as($this, 'operations_admin');
    $this->put('/admin/ai', ['feature' => 'core.translate', 'enabled' => false, 'reason' => 'Testing it'])->assertForbidden();

    Console::as($this, 'super_admin');
    $this->put('/admin/ai', ['feature' => 'core.translate', 'enabled' => false])->assertSessionHasErrors('reason');
    $this->put('/admin/ai', ['feature' => 'core.translate', 'enabled' => false, 'monthly_budget_cents' => 50000, 'reason' => 'Bad Xitsonga output'])->assertSessionHasNoErrors();

    expect(AiFeatureSetting::query()->find('core.translate'))->enabled->toBeFalse()->monthly_budget_cents->toBe(50000)
        ->and(AuditLog::query()->where('event', 'ai.settings_changed')->exists())->toBeTrue();
});

it('lets reviewers approve, reject or escalate flagged content with a reason', function (): void {
    Console::as($this, 'content_reviewer');
    $flag = ModerationFlag::query()->create(['subject_type' => 'hub_event', 'subject_id' => '01JTESTEVENT00000000000000', 'excerpt' => 'Pay a fee', 'reasons' => ['Asks applicants to pay'], 'source' => 'rules']);

    $this->get('/admin/moderation')->assertInertia(fn ($page) => $page->has('flags.data', 1)->where('flags.data.0.link', '/hub-ops/events/01JTESTEVENT00000000000000'));
    $this->post("/admin/moderation/{$flag->id}", ['decision' => 'rejected'])->assertSessionHasErrors('reason');
    $this->post("/admin/moderation/{$flag->id}", ['decision' => 'rejected', 'reason' => 'Scam - event removed'])->assertSessionHasNoErrors();

    expect($flag->refresh()->status)->toBe('rejected');
    $this->post("/admin/moderation/{$flag->id}", ['decision' => 'approved', 'reason' => 'Changed my mind'])->assertStatus(409);
});

it('keeps the review queue from people without the permission', function (): void {
    Console::as($this, 'finance_admin');

    $this->get('/admin/moderation')->assertForbidden();
    $this->get('/admin/ai')->assertForbidden();
});
