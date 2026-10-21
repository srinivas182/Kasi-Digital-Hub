# ADR-037: KasiBiz - stock, compliance calendar and shared summaries

- **Status:** Accepted
- **Date:** 2026-10-21
- **Sprint:** S29

## Decisions

1. **Stock** items with a reorder level and a full movement history (bought, sold, used, damaged, lost, counted - a
   stock-take records the difference). "Bought" can also write the cash book entry. Items linked to a KasiMarket product
   share KasiMarket's quantity (single source of truth) through the new core `ProductStock` contract.
2. **Low stock** is listed first and announced at most once a day per business.
3. **Compliance calendar** from KasiStart facts plus a compliance profile the owner confirms (pre-filled where possible):
   CIPC annual return (30 business days after the anniversary), turnover tax interim and annual, VAT (two-monthly),
   EMP201 (7th), COIDA (31 May), B-BBEE renewal, trading permit. SARS dates on a weekend move to the business day before.
   **Starter rules - a tax adviser must confirm them before launch;** the page says it is not tax or legal advice.
4. **Reminders** at 14 and 3 days and weekly when overdue (daily job, heartbeat on the system status page); owners can
   move a date; done items can carry private proof in the document vault.
5. **Shared summary:** bands and counts only (months with records, turnover band, profitable months of 6, stock value
   band, compliance up to date, KasiMarket rating); shared by a 7-day link shown once (only its hash is kept),
   withdrawable, every view logged; the owner previews exactly what the recipient sees.
6. **Signals** for partner eligibility: "compliance up to date" and "tracks stock"; funder indicator for deadlines met.
7. KasiBiz's access rules (whose books, assisted sessions, hub availability) live in one shared trait used by every
   KasiBiz controller.
