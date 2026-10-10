<?php

declare(strict_types=1);

namespace Modules\Core\Enterprise;

/** Extension point (implemented by the partner portal): support offers a business qualifies for. */
interface SupportOffers
{
    /** Number of open offers the business fully qualifies for. */
    public function eligibleCount(string $businessId): int;
}
