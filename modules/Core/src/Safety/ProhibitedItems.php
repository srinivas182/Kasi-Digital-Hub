<?php

declare(strict_types=1);

namespace Modules\Core\Safety;

/**
 * The platform's prohibited-items list (moved to Core in S31 so KasiMarket listings and KasiBrand websites share one
 * list). A placeholder until Ku Tirhisana confirms it.
 */
final class ProhibitedItems
{
    /** term => reason key (market.prohibited.<key> in the text files) */
    public const LIST = [
        'alcohol' => 'alcohol', 'beer' => 'alcohol', 'wine' => 'alcohol', 'brandy' => 'alcohol', 'whisky' => 'alcohol', 'vodka' => 'alcohol', 'liquor' => 'alcohol',
        'cigarette' => 'tobacco', 'tobacco' => 'tobacco', 'vape' => 'tobacco', 'hubbly' => 'tobacco', 'snuff' => 'tobacco',
        'dagga' => 'drugs', 'cannabis' => 'drugs', 'weed' => 'drugs', 'pills' => 'medicine', 'medicine' => 'medicine', 'antibiotic' => 'medicine', 'muti' => 'medicine',
        'gun' => 'weapons', 'firearm' => 'weapons', 'ammunition' => 'weapons', 'pepper spray' => 'weapons', 'knife set' => null,
        'replica' => 'counterfeit', 'fake' => 'counterfeit', 'first copy' => 'counterfeit',
        'adult' => 'adult', 'sexy' => 'adult',
        'puppy' => 'animals', 'puppies' => 'animals', 'kitten' => 'animals', 'live chicken' => 'animals',
        'airtime voucher' => 'airtime', 'free money' => 'scam', 'investment' => 'scam', 'loan' => 'scam',
    ];

    /** Reason key when the text clearly names a prohibited item, else null. */
    public static function reason(string $text): ?string
    {
        $haystack = ' '.mb_strtolower((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text)).' ';
        foreach (self::LIST as $term => $reason) {
            if ($reason !== null && str_contains($haystack, ' '.$term.' ')) {
                return $reason;
            }
        }

        return null;
    }
}
