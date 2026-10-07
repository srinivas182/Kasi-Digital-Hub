<?php

declare(strict_types=1);

namespace App\Support\Demo;

use Random\Engine\Mt19937;
use Random\Engine\Secure;
use Random\Randomizer;

/**
 * Generates realistic but entirely fictitious South African demo data.
 *
 * ID numbers are deliberately generated with an INVALID Luhn check digit so they
 * can never match a real person (POPIA). Phone numbers use the +27 format.
 */
final class SouthAfricanFaker
{
    public const FIRST_NAMES = [
        'Thandi', 'Sipho', 'Lwazi', 'Nomsa', 'Tsakani', 'Rhulani', 'Kulani', 'Nyiko', 'Kurhula', 'Mpho',
        'Lerato', 'Thabo', 'Zanele', 'Bongani', 'Palesa', 'Karabo', 'Ayanda', 'Sizwe', 'Naledi', 'Tshepo',
        'Amahle', 'Mandla', 'Refilwe', 'Vusi', 'Hlengiwe', 'Musa', 'Dineo', 'Kagiso', 'Ntombi', 'Lindiwe',
    ];

    public const SURNAMES = [
        'Mabasa', 'Baloyi', 'Chauke', 'Maluleke', 'Hlungwani', 'Mathebula', 'Mabunda', 'Ngobeni', 'Nkuna', 'Shibambu',
        'Dlamini', 'Nkosi', 'Mokoena', 'Khumalo', 'Ndlovu', 'Molefe', 'Mahlangu', 'Zulu', 'Sithole', 'Mthembu',
    ];

    /** @var array<string, list<string>> Province => places (villages, townships and towns) */
    public const PLACES = [
        'Limpopo' => ['Tsutsumani', 'Giyani', 'Malamulele', 'Nkowankowa', 'Tzaneen', 'Polokwane', 'Ngove', 'Siyandhani'],
        'Gauteng' => ['Soweto', 'Alexandra', 'Tembisa', 'Diepsloot', 'Mamelodi', 'Soshanguve', 'Katlehong'],
        'KwaZulu-Natal' => ['Umlazi', 'KwaMashu', 'Inanda', 'Clermont', 'Edendale', 'Imbali'],
    ];

    private Randomizer $random;

    public function __construct(?int $seed = null)
    {
        $this->random = new Randomizer($seed === null ? new Secure : new Mt19937($seed));
    }

    public function firstName(): string
    {
        return $this->pick(self::FIRST_NAMES);
    }

    public function surname(): string
    {
        return $this->pick(self::SURNAMES);
    }

    public function fullName(): string
    {
        return $this->firstName().' '.$this->surname();
    }

    /**
     * @return array{province: string, place: string}
     */
    public function location(): array
    {
        $province = $this->pick(array_keys(self::PLACES));

        return ['province' => $province, 'place' => $this->pick(self::PLACES[$province])];
    }

    /**
     * A South African mobile number in E.164 format, e.g. +27721234567.
     */
    public function mobileNumber(): string
    {
        $prefix = $this->pick(['60', '61', '62', '63', '71', '72', '73', '74', '76', '78', '79', '81', '82', '83', '84']);

        return '+27'.$prefix.str_pad((string) $this->random->getInt(0, 9_999_999), 7, '0', STR_PAD_LEFT);
    }

    /**
     * A 13-digit, SA-shaped ID number that always FAILS the Luhn check.
     */
    public function invalidIdNumber(int $minAge = 18, int $maxAge = 35): string
    {
        $year = (int) date('Y') - $this->random->getInt($minAge, $maxAge);
        $date = sprintf('%02d%02d%02d', $year % 100, $this->random->getInt(1, 12), $this->random->getInt(1, 28));
        $body = $date.str_pad((string) $this->random->getInt(0, 9999), 4, '0', STR_PAD_LEFT).'0'.'8';
        $valid = self::luhnCheckDigit($body);

        return $body.(($valid + 1 + $this->random->getInt(0, 8)) % 10);
    }

    /**
     * Luhn check digit for a numeric string (used by SA ID numbers).
     */
    public static function luhnCheckDigit(string $digits): int
    {
        $sum = 0;
        $double = true;

        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $n = (int) $digits[$i];

            if ($double) {
                $n *= 2;
                $n = $n > 9 ? $n - 9 : $n;
            }

            $sum += $n;
            $double = ! $double;
        }

        return (10 - ($sum % 10)) % 10;
    }

    public static function passesLuhn(string $number): bool
    {
        return self::luhnCheckDigit(substr($number, 0, -1)) === (int) substr($number, -1);
    }

    /**
     * @template T
     *
     * @param  array<int, T>  $items
     * @return T
     */
    private function pick(array $items): mixed
    {
        $items = array_values($items);

        return $items[$this->random->getInt(0, count($items) - 1)];
    }
}
