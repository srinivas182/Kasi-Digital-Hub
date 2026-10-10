<?php

declare(strict_types=1);

namespace Modules\Start\Services;

use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Core\Access\RoleAssignments;
use Modules\Core\Access\Scope;
use Modules\Core\Documents\Models\Document;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Structure\Models\RoleAssignment;
use Modules\Start\Events\BusinessCreated;
use Modules\Start\Events\BusinessFormalised;
use Modules\Start\Events\StepCompleted;
use Modules\Start\Models\Business;
use Modules\Start\Models\Step;

/**
 * Businesses, owners and the formalisation journey (ADR-024): steps chosen by legal form, sector and
 * employees; "done" with optional proof in the document vault; an explainable readiness score.
 */
final readonly class Businesses
{
    public function __construct(private AuditLogger $audit, private RoleAssignments $roles) {}

    /** @param array<string, mixed> $data */
    public function create(User $owner, array $data, ?User $by = null): Business
    {
        $business = DB::transaction(function () use ($owner, $data): Business {
            $business = Business::query()->create([...$data, 'hub_id' => $data['hub_id'] ?? $owner->home_hub_id]);
            DB::table('start_business_members')->insert(['business_id' => $business->id, 'user_id' => $owner->id, 'role' => 'owner', 'created_at' => now()]);

            return $business;
        });
        if (! RoleAssignment::query()->where('user_id', $owner->id)->where('role', 'entrepreneur')->exists()) {
            $this->roles->assign($owner, 'entrepreneur', Scope::self(), $by);
        }
        $this->audit->record('start.business_created', $owner, meta: ['business' => $business->id], actor: $by);
        event(new BusinessCreated($owner, $by?->id, ['business' => $business->id, 'sector' => $business->sector, 'stage' => $business->stage]));
        $this->refresh($business);

        return $business;
    }

    /** @return Collection<int, Business> */
    public function of(User $user): Collection
    {
        return Business::query()->whereIn('id', DB::table('start_business_members')->where('user_id', $user->id)->pluck('business_id'))->orderBy('name')->get();
    }

    public function role(Business $business, User $user): ?string
    {
        $role = DB::table('start_business_members')->where('business_id', $business->id)->where('user_id', $user->id)->value('role');

        return $role === null ? null : (string) $role;
    }

    public function addCoOwner(Business $business, User $person, User $by): void
    {
        if ($person->isMinor()) {
            throw new DomainException(__('start.members.adult'));
        }
        DB::table('start_business_members')->insertOrIgnore(['business_id' => $business->id, 'user_id' => $person->id, 'role' => 'coowner', 'created_at' => now()]);
        $this->audit->record('start.coowner_added', $person, meta: ['business' => $business->id], actor: $by);
    }

    public function removeCoOwner(Business $business, User $person, User $by): void
    {
        DB::table('start_business_members')->where('business_id', $business->id)->where('user_id', $person->id)->where('role', 'coowner')->delete();
        $this->audit->record('start.coowner_removed', $person, meta: ['business' => $business->id], actor: $by);
    }

    /** @return Collection<int, Step> steps that apply to this business, in order */
    public function steps(Business $business): Collection
    {
        if ($business->legal_form === null) {
            return Step::query()->where('key', 'choose_form')->where('active', true)->get();
        }

        return Step::query()->where('active', true)->orderBy('position')->get()->filter(fn (Step $s): bool => $s->appliesTo($business))->values();
    }

    public function markDone(Business $business, Step $step, User $by, ?string $documentId = null): void
    {
        if (! $this->steps($business)->contains('id', $step->id)) {
            throw new DomainException(__('start.steps.not_applicable'));
        }
        $first = ! DB::table('start_business_steps')->where('business_id', $business->id)->where('step_id', $step->id)->exists();
        DB::table('start_business_steps')->updateOrInsert(['business_id' => $business->id, 'step_id' => $step->id], ['document_id' => $documentId, 'done_by' => $by->id, 'done_at' => now()]);
        if ($step->key === 'cipc_register' && $business->stage !== 'registered') {
            $business->forceFill(['stage' => 'registered'])->save();
        }
        $this->audit->record('start.step_done', $by, meta: ['business' => $business->id, 'step' => $step->key]);
        if ($first) {
            event(new StepCompleted($by, null, ['business' => $business->id, 'step' => $step->key]));
        }
        $this->refresh($business);
    }

    public function undo(Business $business, Step $step, User $by): void
    {
        DB::table('start_business_steps')->where('business_id', $business->id)->where('step_id', $step->id)->delete();
        $this->audit->record('start.step_undone', $by, meta: ['business' => $business->id, 'step' => $step->key]);
        $this->refresh($business);
    }

    public function chooseForm(Business $business, string $form, User $by): void
    {
        $business->forceFill(['legal_form' => $form])->save();
        $choose = Step::query()->where('key', 'choose_form')->first();
        if ($choose !== null) {
            $this->markDone($business, $choose, $by);
        }
        $this->refresh($business);
    }

    /**
     * Readiness (0-100) with reasons: profile 20, plan 20, steps done 40, proof verified 20.
     *
     * @return array{score: int, parts: array<string, array{points: int, of: int}>, next: string|null}
     */
    public function readiness(Business $business): array
    {
        $profile = count(array_filter([$business->name, $business->sells, $business->sector, $business->customers, $business->turnover_band]));
        $plan = DB::table('start_plans')->where('business_id', $business->id)->value('completed_at') !== null ? 20 : (DB::table('start_plans')->where('business_id', $business->id)->exists() ? 8 : 0);
        $steps = $this->steps($business)->where('key', '!=', 'choose_form');
        $done = DB::table('start_business_steps')->where('business_id', $business->id)->whereIn('step_id', $steps->pluck('id'))->get();
        $withProof = $steps->whereNotNull('document_type')->pluck('id');
        $verified = $done->whereIn('step_id', $withProof)->filter(static fn (object $d): bool => $d->document_id !== null
            && Document::query()->whereKey($d->document_id)->where('status', Document::VERIFIED)->exists())->count();

        $parts = [
            'profile' => ['points' => (int) round(20 * $profile / 5), 'of' => 20],
            'plan' => ['points' => $plan, 'of' => 20],
            'steps' => ['points' => $steps->isEmpty() ? 0 : (int) round(40 * $done->count() / $steps->count()), 'of' => 40],
            'proof' => ['points' => $withProof->isEmpty() ? 0 : (int) round(20 * $verified / $withProof->count()), 'of' => 20],
        ];
        $next = $business->legal_form === null ? 'choose_form' : $steps->first(fn (Step $s): bool => ! $done->contains('step_id', $s->id))?->key;

        return ['score' => array_sum(array_column($parts, 'points')), 'parts' => $parts, 'next' => $next];
    }

    public function refresh(Business $business): void
    {
        $readiness = $this->readiness($business);
        $steps = $this->steps($business);
        $allDone = $business->legal_form !== null && $steps->isNotEmpty()
            && DB::table('start_business_steps')->where('business_id', $business->id)->whereIn('step_id', $steps->pluck('id'))->count() === $steps->count();

        $business->forceFill(['readiness' => $readiness['score']])->save();
        if ($allDone && $business->formalised_at === null) {
            $business->forceFill(['formalised_at' => now()])->save();
            $owner = User::query()->whereKey(DB::table('start_business_members')->where('business_id', $business->id)->where('role', 'owner')->value('user_id'))->first();
            if ($owner !== null) {
                event(new BusinessFormalised($owner, null, ['business' => $business->id, 'form' => (string) $business->legal_form]));
            }
        }
    }
}
