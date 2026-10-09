<?php

declare(strict_types=1);

namespace Modules\Work\Services;

use Modules\Work\Models\JobListing;

/**
 * Rules every listing must pass. Some block saving (pay below the national minimum wage, forbidden
 * screening questions); others send the listing to human review (unfair preferences, scam signs).
 */
final class ListingChecks
{
    /** Topics screening questions may not ask about (Employment Equity Act, POPIA, safety). */
    private const FORBIDDEN_QUESTIONS = [
        'age' => '/\b(how old|your age|age\b|date of birth|born in)/i',
        'religion' => '/\b(religio|church|muslim|christian|pray)/i',
        'pregnancy' => '/\b(pregnan|children of your own|plan to have (a )?(baby|child))/i',
        'health' => '/\b(hiv|aids|disab|illness|medical condition|chronic)/i',
        'marital status' => '/\b(married|marital|single\b|husband|wife|boyfriend|girlfriend)/i',
        'race or culture' => '/\b(race|racial|tribe|ethnic|black|white|coloured|indian)\b/i',
        'ID or bank details' => '/\b(id number|identity number|id copy|bank|account number|card number|pin)\b/i',
    ];

    /** Signs a person should look at the listing before it goes live. */
    private const REVIEW_RULES = [
        'Prefers a gender' => '/\b(females? only|males? only|ladies only|men only|women only|male candidates|female candidates|must be (a )?(male|female|man|woman))\b/i',
        'Prefers an age' => '/\b(under \d{2}|below \d{2}|aged? \d{2}\s*(-|to)\s*\d{2}|between the ages|young (lady|man|men|ladies|person|people)|not older than|max(imum)? age)\b/i',
        'Prefers a race (outside an employment equity plan)' => '/\b(black|white|coloured|indian|african) (only|candidates only|people only)\b/i',
        'Prefers marital status or no children' => '/\b(must be single|unmarried|no (kids|children)|not pregnant)\b/i',
        'Asks for ID copies before an interview' => '/\b(send|bring|attach|submit)\b.{0,30}\b(id copy|copy of (your )?id|certified copies)\b/i',
        'Pay looks unrealistic' => '/\b(earn|make)\s+R\s?\d{2}[\s,]?\d{3,}\s*(a|per|every)\s*(day|week)\b/i',
    ];

    /** @return string|null An error when pay is below the national minimum wage (or a stipend is missing). */
    public function payProblem(string $type, string $period, int $payMinCents): ?string
    {
        if ($payMinCents <= 0) {
            return (string) __('work.listing.pay_required');
        }

        if (in_array($type, JobListing::STIPEND_TYPES, true)) {
            return null; // learnership/internship stipends follow their own rules
        }

        $hours = (int) (config("kasi.work.hours_per_period.{$period}") ?? 1);
        $minimum = (int) config('kasi.work.minimum_wage_cents_per_hour');

        return intdiv($payMinCents, max(1, $hours)) < $minimum
            ? (string) __('work.listing.below_minimum', ['rate' => number_format($minimum / 100, 2, '.', ' ')])
            : null;
    }

    /** @return string|null Why a screening question is not allowed. */
    public function questionProblem(string $question): ?string
    {
        foreach (self::FORBIDDEN_QUESTIONS as $topic => $pattern) {
            if (preg_match($pattern, $question) === 1) {
                return (string) __('work.listing.question_forbidden', ['topic' => (string) __($topic)]);
            }
        }

        return null;
    }

    /** @return list<string> Reasons the listing must be reviewed by a person before it goes live. */
    public function reviewReasons(string $text): array
    {
        $employmentEquity = preg_match('/\b(employment equity|EE plan|EE candidates|designated groups)\b/i', $text) === 1;
        $reasons = [];

        foreach (self::REVIEW_RULES as $reason => $pattern) {
            if (str_starts_with($reason, 'Prefers a race') && $employmentEquity) {
                continue;
            }
            if (preg_match($pattern, $text) === 1) {
                $reasons[] = $reason;
            }
        }

        return $reasons;
    }
}
