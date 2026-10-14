# ADR-027: Commercial and finance - catalogue, split settlement, ledger

- **Status:** Accepted
- **Date:** 2026-10-11
- **Sprint:** S19

## Context

The commercial model agreed in planning: young people never pay; two separate layers of money (hub packages Kasi
invoices to hubs, and local sales by hubs); royalties taken automatically at payment (split settlement), set by who
delivers the value; attribution referral -> assisted -> nearest hub -> national; customer data belongs to the platform.

## Decisions

1. **Money** is integer cents (ZAR); prices are VAT-inclusive. VAT (15%) is extracted only for a VAT-registered merchant
   of record and stays with that merchant. Shares are on the amount excluding VAT; hub and partner shares round down
   and the remainder goes to Kasi, so shares always add up to the cent (`Money`).
2. **Catalogue:** products per stream with metro/rural tiers, floors and ceilings (no price wars). Split rules per
   stream, versioned by date. Every order records the rule it used. Seeded values are **placeholders**.
3. **Four-eyes:** prices, split rules and hub bank accounts change only after a second commercial/finance admin
   approves; the requester can't approve their own change (`Approvals`).
4. **Merchant of record:** the hub for local sales, Kasi for hub packages, licences and national sales (to be confirmed
   by a tax adviser). A hub sells only when ready: company registration, VAT status, verified bank account (four-eyes),
   current franchise agreement accepted, no package invoice over 30 days. Otherwise online sales go national; the point
   of sale refuses.
5. **Payments** go through a `PaymentGateway` driver with split settlement to the hub's sub-account: `fake` (CI, demo,
   default) and `paystack` (live keys and legal sign-off required). Notifications are signature-checked, stored once
   per provider event id and applied idempotently.
6. **Ledger:** immutable entries (no update or delete; corrections are new entries) per sale, refund, cash-sale royalty,
   package and licence payment. Refunds reverse shares proportionally and add up exactly.
7. **Cash sales** (hub point of sale): royalty recorded as owed by the hub and netted against the next payout;
   negative balances are carried as owed.
8. **Hub packages** invoiced monthly; reminders at 7 and 14 days; at 30 days **new paid sales are blocked** - youth
   services and hub portals are never switched off for a hub's debt.
9. **Reconciliation** daily against the provider's settlements; differences become issues for finance.
10. **Fulfilment** through the core `Fulfiller` extension point: KasiWork extra advert slots (30 days) in Release 1.
11. **Live payments stay off** until Ku Tirhisana has a merchant account and its attorney (franchise agreement, Consumer
    Protection Act) and tax adviser (merchant of record, VAT) have signed off.
