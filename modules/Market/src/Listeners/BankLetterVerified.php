<?php

declare(strict_types=1);

namespace Modules\Market\Listeners;

use Modules\Core\Events\DocumentVerified;
use Modules\Market\Models\Shop;

/**
 * A trader's bank confirmation letter was verified in the document queue (S26): the shop's bank account counts as
 * verified. The payout account at the payment provider is created when live payments are switched on (S34);
 * until then the demo provider uses a demo payout account.
 */
final class BankLetterVerified
{
    public function handle(DocumentVerified $event): void
    {
        foreach (Shop::query()->where('bank_document_id', $event->document->id)->get() as $shop) {
            $shop->forceFill(['bank_verified' => true,
                'payout_subaccount' => $shop->getAttribute('payout_subaccount') ?? (config('kasi.commercial.payments_driver') === 'fake' ? 'DEMO-'.$shop->id : null)])->save();
        }
    }
}
