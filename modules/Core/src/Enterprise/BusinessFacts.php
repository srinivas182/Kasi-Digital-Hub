<?php

declare(strict_types=1);

namespace Modules\Core\Enterprise;

use Modules\Core\Identity\Models\User;

/**
 * Extension point (implemented by KasiStart): what other portals may know about a business, e.g. to
 * check eligibility for partner offers. Only facts a referral could need - no plan text or turnover
 * beyond its band.
 */
interface BusinessFacts
{
    /**
     * @return array{id: string, name: string, stage: string, sector: string, legal_form: string|null, municipality_id: int|null,
     *     province_id: int|null, hub_id: string|null, people: int, turnover_band: string|null, readiness: int, steps_done: list<string>,
     *     owner_ids: list<string>, youngest_owner_age: int|null}|null
     */
    public function facts(string $businessId): ?array;

    /** @return list<array{id: string, name: string}> businesses the person owns or co-owns */
    public function businessesOf(User $user): array;

    /** Whether the person is an owner or co-owner of the business. */
    public function isMember(string $businessId, User $user): bool;

    /** @return array<string, string> plain-text summary for a referral (what the business does, plan highlights) */
    public function summary(string $businessId): array;
}
