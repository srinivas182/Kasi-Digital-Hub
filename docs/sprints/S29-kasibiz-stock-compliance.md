# Sprint 29 - KasiBiz part 2: stock, compliance calendar and summaries

- **Status:** Complete (KasiBiz complete)
- **Version:** v0.29.0

## Delivered

Stock with history, stock-takes and KasiMarket-linked quantities; once-a-day low-stock messages; the compliance
calendar with reminders, moved dates and private proof; shared summaries by withdrawable 7-day links; new partner
signals; home-page cards and menu items (ADR-037).

## Deviations

- **Summaries are shared by link only**, not attached directly to partner referrals (that would mean the partner
  portal reading KasiBiz's data); owners can include the link with a referral or send it on WhatsApp.
- **The calendar refreshes when it is opened and in the daily job**, rather than instantly when a KasiStart step is
  completed - KasiBiz doesn't depend on KasiStart's internals, and changes show within a day.
- "How to do this" opens the business's KasiStart guide page (the guide has no per-step links yet).

## Issues found and fixed during the sprint

- KasiMarket's notifications used a category (`market`) that isn't in people's notification settings, so they couldn't
  be controlled; KasiMarket and KasiBiz notifications are now under "business".
- The menu icons `wallet` (KasiBiz, since S28), `package` and `share` didn't exist and fell back to a generic icon;
  added.
- The support-offers page went over its query budget (20 vs 18) because eligibility now includes the new KasiBiz
  signals; the signals were made leaner (one query each for compliance and for cash-book figures), measured 19, and
  the budget set to 20 with the reason recorded.
- The turnover band labels (`biz.band.*`) had no text; added.

## Waiting on Ku Tirhisana

A tax adviser's confirmation of the compliance rules and dates; wording of the "not tax or legal advice" note.
