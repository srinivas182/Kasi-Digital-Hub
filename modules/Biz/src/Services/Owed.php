<?php

declare(strict_types=1);

namespace Modules\Biz\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Core\Identity\Services\PhoneNumbers;

/** Customers and suppliers, and who owes what (S28). Marking a debt paid writes the cash book entry. */
final readonly class Owed
{
    public function __construct(private Books $books, private AuditLogger $audit) {}

    public function contact(string $businessId, string $kind, string $name, ?string $phone, ?string $notes): int
    {
        if (! in_array($kind, ['customer', 'supplier'], true) || trim($name) === '') {
            throw new DomainException(__('biz.owed.invalid'));
        }

        return (int) DB::table('biz_contacts')->insertGetId(['business_id' => $businessId, 'kind' => $kind, 'name' => mb_substr(trim($name), 0, 80),
            'phone' => $phone !== null && $phone !== '' ? PhoneNumbers::normalise($phone) : null, 'notes' => $notes !== null ? mb_substr($notes, 0, 300) : null,
            'created_at' => now(), 'updated_at' => now()]);
    }

    public function add(string $businessId, int $contactId, string $direction, int $cents, string $description, ?string $dueOn, User $by): int
    {
        if (! in_array($direction, ['owed_me', 'i_owe'], true) || $cents <= 0 || ! DB::table('biz_contacts')->where('id', $contactId)->where('business_id', $businessId)->exists()) {
            throw new DomainException(__('biz.owed.invalid'));
        }
        $id = (int) DB::table('biz_credits')->insertGetId(['business_id' => $businessId, 'contact_id' => $contactId, 'direction' => $direction, 'amount_cents' => $cents,
            'description' => mb_substr(trim($description), 0, 200), 'due_on' => $dueOn, 'created_at' => now(), 'updated_at' => now()]);
        $this->audit->record('biz.credit_added', meta: ['business' => $businessId, 'direction' => $direction], actor: $by);

        return $id;
    }

    /** Paid: the money goes into the cash book (in for money owed to me, out for money I owe). */
    public function paid(string $businessId, int $creditId, string $method, User $by, ?string $category = null, ?User $subject = null): void
    {
        $credit = DB::table('biz_credits')->where('id', $creditId)->where('business_id', $businessId)->whereNull('paid_at')->first();
        if ($credit === null) {
            throw new DomainException(__('biz.owed.not_open'));
        }
        $in = $credit->direction === 'owed_me';
        $entry = $this->books->record($businessId, $by, ['direction' => $in ? 'in' : 'out', 'amount_cents' => (int) $credit->amount_cents,
            'category' => $category ?? ($in ? 'sales' : 'stock_materials'), 'method' => $method, 'note' => (string) $credit->description, 'contact_id' => (int) $credit->contact_id],
            null, 'credit', 'credit:'.$creditId, $subject);
        DB::table('biz_credits')->where('id', $creditId)->update(['paid_entry_id' => $entry->id, 'paid_at' => now(), 'updated_at' => now()]);
    }

    /** @return array{owed_me: list<array<string, mixed>>, i_owe: list<array<string, mixed>>} oldest first */
    public static function open(string $businessId): array
    {
        $rows = DB::table('biz_credits')->join('biz_contacts', 'biz_contacts.id', '=', 'biz_credits.contact_id')->where('biz_credits.business_id', $businessId)
            ->whereNull('biz_credits.paid_at')->orderBy('biz_credits.created_at')
            ->get(['biz_credits.id', 'biz_credits.direction', 'biz_credits.amount_cents', 'biz_credits.description', 'biz_credits.due_on', 'biz_credits.created_at', 'biz_contacts.name', 'biz_contacts.phone']);
        $map = static fn (\stdClass $r): array => ['id' => (int) $r->id, 'cents' => (int) $r->amount_cents, 'description' => (string) $r->description, 'due' => $r->due_on,
            'since' => (string) $r->created_at, 'name' => (string) $r->name, 'phone' => $r->phone];

        return ['owed_me' => array_values($rows->where('direction', 'owed_me')->map($map)->all()), 'i_owe' => array_values($rows->where('direction', 'i_owe')->map($map)->all())];
    }
}
