<?php

declare(strict_types=1);

namespace Modules\Work\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Core\Identity\Models\User;
use Modules\Work\Models\WorkEducation;
use Modules\Work\Models\WorkExperience;
use Modules\Work\Services\CvAssistant;
use Modules\Work\Services\SeekerProfile;

/**
 * Experience (formal and informal) and education entries, with AI help for CV bullet points.
 */
final class ExperienceController extends WorkController
{
    public function store(Request $request, SeekerProfile $profiles): RedirectResponse
    {
        [$user, $by] = $this->who($request);
        $profiles->save($user, [], $by);
        WorkExperience::query()->create([...$this->validated($request), 'user_id' => $user->id, 'position' => WorkExperience::query()->where('user_id', $user->id)->count()]);
        $profiles->refresh($user, $by);

        return back()->with('status', __('work.saved'));
    }

    public function update(Request $request, WorkExperience $experience, SeekerProfile $profiles): RedirectResponse
    {
        [$user, $by] = $this->who($request);
        $this->own($experience->user_id, $user);
        $experience->update($this->validated($request));

        return back()->with('status', __('work.saved'));
    }

    public function destroy(Request $request, WorkExperience $experience, SeekerProfile $profiles): RedirectResponse
    {
        [$user, $by] = $this->who($request);
        $this->own($experience->user_id, $user);
        $experience->delete();
        $profiles->refresh($user, $by);

        return back()->with('status', __('work.deleted'));
    }

    /** AI suggestion for this experience's CV bullet points (shown next to the person's own words). */
    public function suggest(Request $request, WorkExperience $experience, CvAssistant $assistant): JsonResponse
    {
        [$user, $by] = $this->who($request);
        $this->own($experience->user_id, $user);
        $result = $assistant->suggestBullets($experience, $user, $by);

        return response()->json($result['ok'] ? $result : ['ok' => false, 'message' => __('work.ai.fallback.'.($result['reason'] ?? 'unavailable'))]);
    }

    /** The person accepts (possibly edited) bullet points. */
    public function bullets(Request $request, WorkExperience $experience): RedirectResponse
    {
        [$user] = $this->who($request);
        $this->own($experience->user_id, $user);
        $data = $request->validate(['bullets' => ['present', 'array', 'max:6'], 'bullets.*' => ['string', 'max:200']]);
        $experience->forceFill(['bullets' => array_values(array_filter(array_map('trim', $data['bullets']))), 'suggested_bullets' => null])->save();

        return back()->with('status', __('work.saved'));
    }

    public function storeEducation(Request $request, SeekerProfile $profiles): RedirectResponse
    {
        [$user, $by] = $this->who($request);
        $profiles->save($user, [], $by);
        WorkEducation::query()->create([...$this->validatedEducation($request, $user), 'user_id' => $user->id]);
        $profiles->refresh($user, $by);

        return back()->with('status', __('work.saved'));
    }

    public function updateEducation(Request $request, WorkEducation $education): RedirectResponse
    {
        [$user] = $this->who($request);
        $this->own($education->user_id, $user);
        $education->update($this->validatedEducation($request, $user));

        return back()->with('status', __('work.saved'));
    }

    public function destroyEducation(Request $request, WorkEducation $education, SeekerProfile $profiles): RedirectResponse
    {
        [$user, $by] = $this->who($request);
        $this->own($education->user_id, $user);
        $education->delete();
        $profiles->refresh($user, $by);

        return back()->with('status', __('work.deleted'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(WorkExperience::KINDS)],
            'title' => ['required', 'string', 'max:120'],
            'organisation' => ['nullable', 'string', 'max:120'],
            'place' => ['nullable', 'string', 'max:120'],
            'started' => ['nullable', 'date_format:Y-m', 'before_or_equal:'.now()->format('Y-m')],
            'ended' => ['nullable', 'date_format:Y-m', 'after_or_equal:started', 'before_or_equal:'.now()->format('Y-m')],
            'duration' => ['nullable', 'string', 'max:60', 'required_without:started'],
            'description' => ['nullable', 'string', 'max:1500'],
        ]);

        return [...$data, 'started' => $data['started'] ?? null, 'ended' => $data['ended'] ?? null];
    }

    /** @return array<string, mixed> */
    private function validatedEducation(Request $request, User $user): array
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(WorkEducation::KINDS)],
            'name' => ['required', 'string', 'max:160'],
            'institution' => ['nullable', 'string', 'max:160'],
            'year' => ['nullable', 'integer', 'min:1970', 'max:'.(now()->year + 6)],
            'in_progress' => ['boolean'],
            'details' => ['nullable', 'string', 'max:300'],
            'document_id' => ['nullable', 'string', Rule::exists('documents', 'id')->where('user_id', $user->id)],
        ]);

        return [...$data, 'in_progress' => (bool) ($data['in_progress'] ?? false)];
    }

    private function own(string $ownerId, User $user): void
    {
        abort_unless($ownerId === $user->id, 404);
    }
}
