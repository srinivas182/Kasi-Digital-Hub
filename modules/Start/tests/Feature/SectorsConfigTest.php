<?php

declare(strict_types=1);

use Modules\Start\Models\Business;

it('keeps the shared business sector list in step with KasiStart', function (): void {
    expect(config('kasi.business_sectors'))->toBe(Business::SECTORS);
});
