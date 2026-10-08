<?php

declare(strict_types=1);

namespace Modules\Core\Home;

use Modules\Core\Access\AccessResolver;
use Modules\Core\Documents\Models\Document;
use Modules\Core\Identity\Models\User;

/**
 * Core next steps: a complete profile, a home hub, key documents and WhatsApp updates.
 */
final readonly class ProfileHomeContributor implements HomeContributor
{
    public function __construct(private AccessResolver $access) {}

    public function nextSteps(User $user): array
    {
        $documents = Document::query()->where('user_id', $user->id)->whereIn('status', [Document::UPLOADED, Document::VERIFIED, Document::PENDING_SCAN])->pluck('type')->all();
        $roles = $this->access->roleKeys($user);
        $needsMatric = array_intersect($roles, ['job_seeker', 'learner']) !== [];

        $steps = [
            new NextStep('home_hub', __('home.steps.home_hub.title'), __('home.steps.home_hub.body'), '/account?tab=profile', $user->home_hub_id !== null, 10),
            new NextStep('location', __('home.steps.location.title'), __('home.steps.location.body'), '/account?tab=profile', $user->municipality_id !== null, 20),
            new NextStep('id_document', __('home.steps.id_document.title'), __('home.steps.id_document.body'), '/account?tab=documents', in_array('id_document', $documents, true), 30),
        ];

        if ($needsMatric) {
            $steps[] = new NextStep('matric', __('home.steps.matric.title'), __('home.steps.matric.body'), '/account?tab=documents', in_array('matric_certificate', $documents, true), 40);
        }

        $steps[] = new NextStep('whatsapp', __('home.steps.whatsapp.title'), __('home.steps.whatsapp.body'), '/account?tab=privacy', (bool) $user->whatsapp_opt_in, 60);

        return $steps;
    }
}
