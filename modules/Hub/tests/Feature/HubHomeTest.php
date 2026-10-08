<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Documents\DocumentVault;
use Modules\Core\Home\HomeContributor;
use Modules\Core\Home\HomeRegistry;
use Modules\Core\Home\NextStep;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\ConsentService;
use Modules\Core\Platform\Models\Update;
use Modules\Core\Tests\Helpers;
use Modules\Core\Tests\Structure;

beforeEach(function (): void {
    Structure::seed($this);
    Storage::fake('documents');
});

function homeFor(object $test, User $user): Assert
{
    app(ConsentService::class)->record($user, ['platform' => true]);
    Helpers::signedIn($test, $user);
    $page = null;
    $test->get('/home')->assertOk()->assertInertia(function (Assert $assert) use (&$page) {
        $page = $assert;

        return $assert->component('Hub/Home');
    });

    return $page;
}

it('shows a new job seeker their next steps', function (): void {
    $user = Structure::personWith('job_seeker');

    homeFor($this, $user)
        ->where('steps', fn ($steps) => collect($steps)->pluck('key')->all() === ['home_hub', 'location', 'id_document', 'matric', 'whatsapp']
            && collect($steps)->every(fn ($step) => $step['done'] === false))
        ->where('hub', null);
});

it('ticks off steps as they are done and puts open steps first', function (): void {
    $hub = Structure::hub('LP-GIY-TSU');
    $user = Structure::personWith('job_seeker', attributes: ['home_hub_id' => $hub->id, 'whatsapp_opt_in' => true]);
    app(DocumentVault::class)->store($user, UploadedFile::fake()->createWithContent('id.pdf', '%PDF-1.4'), 'id_document');

    homeFor($this, $user)
        ->where('steps', fn ($steps) => collect($steps)->pluck('done', 'key')->all() === [
            'location' => false, 'matric' => false, 'home_hub' => true, 'id_document' => true, 'whatsapp' => true,
        ])
        ->where('hub.name', 'Tsutsumani Digital Hub')
        ->where('hub.slug', 'tsutsumani');
});

it('only asks for a matric certificate from job seekers and learners', function (): void {
    homeFor($this, Structure::personWith('entrepreneur'))
        ->where('steps', fn ($steps) => ! collect($steps)->pluck('key')->contains('matric'));
});

it('shows only the services the person can open, marking portals not built yet as coming soon', function (): void {
    homeFor($this, Structure::personWith('job_seeker'))
        ->where('services', fn ($services) => collect($services)->pluck('module')->all() === ['Work', 'Learn']
            && collect($services)->every(fn ($s) => $s['available'] === false));
});

it('shows the latest updates', function (): void {
    $user = User::factory()->create();
    Update::query()->create(['user_id' => $user->id, 'module' => 'Hub', 'category' => 'account', 'title' => 'Hello', 'created_at' => now()]);

    homeFor($this, $user)->has('updates', 1)->where('updates.0.title', 'Hello');
});

it('lets portals add their own next steps', function (): void {
    app()->bind('test.contributor', fn () => new class implements HomeContributor
    {
        public function nextSteps(User $user): array
        {
            return [new NextStep('cv', 'Complete your CV', 'Takes 10 minutes', '/work/cv', false, 5)];
        }
    });
    app()->tag(['test.contributor'], HomeRegistry::TAG);

    $steps = app(HomeRegistry::class)->nextSteps(User::factory()->create());
    expect($steps[0]->key)->toBe('cv');
});
